<?php

namespace App\Services\Policy;

use App\Enums\PolicyStatus;
use App\Exceptions\InsurerTemporaryFailureException;
use App\Exceptions\InsurerTimeoutException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\Insurer\MockInsurerService;
use App\Services\Pricing\PremiumCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolicyRequestService
{
    public function __construct(
        protected PremiumCalculator $pricingCalculator,
        protected PolicyStateMachine $stateMachine,
        protected MockInsurerService $mockInsurer
    ) {}

    /**
     * Intake policy request with database-enforced idempotency.
     * Returns array: ['policy_request' => PolicyRequest, 'created' => bool]
     */
    public function createOrGet(array $data): array
    {
        $agentId = $data['agent_id'];
        $clientRequestId = $data['client_request_id'];

        // 1. Check for existing request (fast path)
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing) {
            return [
                'policy_request' => $existing,
                'created' => false,
            ];
        }

        // 2. Calculate immutable pricing snapshot
        $pricing = $this->pricingCalculator->calculate(
            planCode: $data['plan_code'],
            sumInsured: $data['sum_insured'],
            destination: $data['destination'],
            dateOfBirth: $data['date_of_birth'],
            tripStart: $data['trip_start'],
            tripEnd: $data['trip_end']
        );

        // 3. Persist atomically with race-condition catch
        try {
            return DB::transaction(function () use ($data, $pricing) {
                $policyRequest = PolicyRequest::create([
                    'client_request_id' => $data['client_request_id'],
                    'agent_id' => $data['agent_id'],
                    'customer_name' => $data['customer_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'destination' => strtoupper($data['destination']),
                    'trip_start' => $data['trip_start'],
                    'trip_end' => $data['trip_end'],
                    'plan_code' => strtoupper($data['plan_code']),
                    'sum_insured' => $data['sum_insured'],
                    'travel_days' => $pricing->travelDays,
                    'premium_amount' => $pricing->premiumAmount,
                    'pricing_breakdown' => $pricing->breakdown,
                    'status' => PolicyStatus::RECEIVED->value,
                ]);

                // Transition from RECEIVED to VALIDATED
                $this->stateMachine->transition(
                    policyRequest: $policyRequest,
                    targetStatus: PolicyStatus::VALIDATED,
                    reason: 'Request validated and premium calculated successfully.',
                    metadata: ['pricing' => $pricing->toArray()]
                );

                return [
                    'policy_request' => $policyRequest->fresh(['stateHistories']),
                    'created' => true,
                ];
            });
        } catch (QueryException $e) {
            // Check for duplicate key violation in concurrent race condition
            // SQLite: code 19 / 23000, MySQL: code 1062 / 23000
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') ||
                str_contains($e->getMessage(), 'Duplicate entry') ||
                $e->getCode() == 23000) {

                $concurrencyWinner = PolicyRequest::where('agent_id', $agentId)
                    ->where('client_request_id', $clientRequestId)
                    ->firstOrFail();

                return [
                    'policy_request' => $concurrencyWinner,
                    'created' => false,
                ];
            }

            throw $e;
        }
    }

    /**
     * Submit policy to external mock insurer with safe retry and timeout handling.
     */
    public function submitForIssuance(PolicyRequest $policyRequest, ?string $scenario = null): array
    {
        // 1. Idempotency: If already issued, return immediately without re-submitting
        if ($policyRequest->isIssued()) {
            return [
                'status' => 'ALREADY_ISSUED',
                'policy_request' => $policyRequest,
                'message' => 'Policy has already been issued. Issuance is idempotent and cannot be repeated.',
            ];
        }

        // 2. Enforce reconciliation if currently in ambiguous timeout state
        if ($policyRequest->isNeedsReconciliation()) {
            return [
                'status' => 'NEEDS_RECONCILIATION',
                'policy_request' => $policyRequest,
                'message' => 'Policy is awaiting reconciliation after a previous timeout. Run reconciliation first to prevent duplicate issuance.',
            ];
        }

        $attemptNumber = $policyRequest->attempt_count + 1;
        $policyRequest->attempt_count = $attemptNumber;
        $policyRequest->save();

        // 3. Move state to SUBMITTED
        $this->stateMachine->transition(
            policyRequest: $policyRequest,
            targetStatus: PolicyStatus::SUBMITTED,
            reason: "Issuance attempt #{$attemptNumber} initiated with provider.",
            metadata: ['scenario' => $scenario, 'attempt' => $attemptNumber]
        );

        $requestPayload = [
            'client_request_id' => $policyRequest->client_request_id,
            'agent_id' => $policyRequest->agent_id,
            'customer_name' => $policyRequest->customer_name,
            'sum_insured' => $policyRequest->sum_insured,
            'premium_amount' => $policyRequest->premium_amount,
            'plan_code' => $policyRequest->plan_code,
            'scenario' => $scenario,
        ];

        try {
            $response = $this->mockInsurer->issuePolicy($policyRequest, $scenario);

            if ($response['status'] === 'ISSUED') {
                $policyRequest->policy_number = $response['policy_number'];
                $policyRequest->provider_reference = $response['provider_reference'];
                $policyRequest->last_response = $response;
                $policyRequest->last_error = null;
                $policyRequest->save();

                $this->stateMachine->transition(
                    policyRequest: $policyRequest,
                    targetStatus: PolicyStatus::ISSUED,
                    reason: 'Provider confirmed policy issuance.',
                    metadata: $response
                );

                IssuanceAttempt::create([
                    'policy_request_id' => $policyRequest->id,
                    'attempt_number' => $attemptNumber,
                    'scenario' => $scenario,
                    'status' => 'SUCCESS',
                    'request_payload' => $requestPayload,
                    'response_payload' => $response,
                ]);

                return [
                    'status' => 'ISSUED',
                    'policy_request' => $policyRequest->fresh(['issuanceAttempts', 'stateHistories']),
                    'message' => 'Policy successfully issued.',
                ];
            }

            if ($response['status'] === 'PENDING') {
                $policyRequest->provider_reference = $response['provider_reference'];
                $policyRequest->last_response = $response;
                $policyRequest->last_error = null;
                $policyRequest->save();

                $this->stateMachine->transition(
                    policyRequest: $policyRequest,
                    targetStatus: PolicyStatus::PENDING_CONFIRMATION,
                    reason: 'Provider accepted request; awaiting webhook callback.',
                    metadata: $response
                );

                IssuanceAttempt::create([
                    'policy_request_id' => $policyRequest->id,
                    'attempt_number' => $attemptNumber,
                    'scenario' => $scenario,
                    'status' => 'PENDING',
                    'request_payload' => $requestPayload,
                    'response_payload' => $response,
                ]);

                return [
                    'status' => 'PENDING_CONFIRMATION',
                    'policy_request' => $policyRequest->fresh(['issuanceAttempts', 'stateHistories']),
                    'message' => 'Policy issuance pending callback.',
                ];
            }

            throw new \RuntimeException('Unexpected response status: ' . ($response['status'] ?? 'unknown'));

        } catch (InsurerTimeoutException $e) {
            // RELIABILITY CASE 2: Timeout After Success
            // Do not reissue blindly! Move to NEEDS_RECONCILIATION.
            if ($e->providerReference) {
                $policyRequest->provider_reference = $e->providerReference;
            }
            $policyRequest->last_error = $e->getMessage();
            $policyRequest->save();

            $this->stateMachine->transition(
                policyRequest: $policyRequest,
                targetStatus: PolicyStatus::NEEDS_RECONCILIATION,
                reason: 'Timeout occurred while contacting provider. Reconcile required to avoid duplicate policy.',
                metadata: ['error' => $e->getMessage(), 'provider_reference' => $e->providerReference]
            );

            IssuanceAttempt::create([
                'policy_request_id' => $policyRequest->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'status' => 'TIMEOUT',
                'request_payload' => $requestPayload,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'status' => 'NEEDS_RECONCILIATION',
                'policy_request' => $policyRequest->fresh(['issuanceAttempts', 'stateHistories']),
                'message' => 'Provider call timed out. Policy state marked as NEEDS_RECONCILIATION to protect against duplicate issuance.',
            ];

        } catch (InsurerTemporaryFailureException $e) {
            // RELIABILITY CASE 4: Retry After Temporary Failure
            // Record failure, revert safely to VALIDATED for clean retry.
            $policyRequest->last_error = $e->getMessage();
            $policyRequest->save();

            $this->stateMachine->transition(
                policyRequest: $policyRequest,
                targetStatus: PolicyStatus::VALIDATED,
                reason: 'Temporary provider failure (HTTP 503). Rolled back to VALIDATED for safe retry.',
                metadata: ['error' => $e->getMessage(), 'retriable' => true]
            );

            IssuanceAttempt::create([
                'policy_request_id' => $policyRequest->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'status' => 'FAILED',
                'request_payload' => $requestPayload,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'status' => 'TEMPORARY_FAILURE',
                'policy_request' => $policyRequest->fresh(['issuanceAttempts', 'stateHistories']),
                'message' => 'Provider encountered a temporary failure. Request is preserved and eligible for retry.',
            ];

        } catch (\Throwable $e) {
            $policyRequest->last_error = $e->getMessage();
            $policyRequest->save();

            $this->stateMachine->transition(
                policyRequest: $policyRequest,
                targetStatus: PolicyStatus::FAILED,
                reason: 'Terminal failure during submission: ' . $e->getMessage(),
                metadata: ['error' => $e->getMessage()]
            );

            IssuanceAttempt::create([
                'policy_request_id' => $policyRequest->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'status' => 'FAILED',
                'request_payload' => $requestPayload,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'status' => 'FAILED',
                'policy_request' => $policyRequest->fresh(['issuanceAttempts', 'stateHistories']),
                'message' => 'Submission failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reconcile a request in NEEDS_RECONCILIATION or PENDING_CONFIRMATION state against provider.
     */
    public function reconcile(PolicyRequest $policyRequest): array
    {
        if ($policyRequest->isIssued()) {
            return [
                'status' => 'ALREADY_ISSUED',
                'policy_request' => $policyRequest,
                'message' => 'Policy already confirmed issued.',
            ];
        }

        // Determine provider reference
        $providerRef = $policyRequest->provider_reference;
        if (! $providerRef) {
            $providerRef = 'PRV-' . strtoupper(substr(md5($policyRequest->agent_id . ':' . $policyRequest->client_request_id), 0, 10));
            $policyRequest->provider_reference = $providerRef;
            $policyRequest->save();
        }

        $lookup = $this->mockInsurer->lookupStatus($providerRef);

        if (! $lookup) {
            // No policy exists on provider side! Safe to rollback to VALIDATED for re-submission.
            $this->stateMachine->transition(
                policyRequest: $policyRequest,
                targetStatus: PolicyStatus::VALIDATED,
                reason: 'Reconciliation checked provider: no policy was created. Reset to VALIDATED for safe retry.',
                metadata: ['reconciliation' => 'NOT_FOUND_ON_PROVIDER']
            );

            return [
                'status' => 'VALIDATED',
                'policy_request' => $policyRequest->fresh(),
                'message' => 'Reconciliation confirmed provider has no record. State rolled back to VALIDATED for safe submission.',
            ];
        }

        if ($lookup['status'] === 'ISSUED') {
            $policyRequest->policy_number = $lookup['policy_number'];
            $policyRequest->last_response = $lookup;
            $policyRequest->last_error = null;
            $policyRequest->save();

            $this->stateMachine->transition(
                policyRequest: $policyRequest,
                targetStatus: PolicyStatus::ISSUED,
                reason: 'Reconciliation confirmed policy was issued by provider.',
                metadata: $lookup
            );

            return [
                'status' => 'ISSUED',
                'policy_request' => $policyRequest->fresh(),
                'message' => 'Successfully reconciled: policy confirmed as ISSUED.',
            ];
        }

        return [
            'status' => $policyRequest->status,
            'policy_request' => $policyRequest->fresh(),
            'message' => 'Provider status is currently: ' . $lookup['status'],
        ];
    }

    /**
     * Process incoming provider webhook callback idempotently.
     */
    public function handleCallback(array $payload): array
    {
        $eventId = $payload['event_id'] ?? null;
        $providerRef = $payload['provider_reference'] ?? null;
        $eventType = $payload['event_type'] ?? 'POLICY_ISSUED';

        if (! $eventId || ! $providerRef) {
            return [
                'status' => 'INVALID_PAYLOAD',
                'message' => 'Missing event_id or provider_reference in callback payload.',
            ];
        }

        // RELIABILITY CASE 3: Duplicate Callback Handling
        $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
        if ($existingEvent) {
            return [
                'status' => 'DUPLICATE_IGNORED',
                'message' => "Event [{$eventId}] has already been processed idempotently.",
                'event_id' => $eventId,
            ];
        }

        return DB::transaction(function () use ($eventId, $providerRef, $eventType, $payload) {
            // Save event for deduplication
            $event = ProviderEvent::create([
                'event_id' => $eventId,
                'provider_reference' => $providerRef,
                'event_type' => $eventType,
                'payload' => $payload,
                'processed' => false,
            ]);

            $policyRequest = PolicyRequest::where('provider_reference', $providerRef)->first();

            if (! $policyRequest) {
                // Also check if matches deterministic reference
                $allMatching = PolicyRequest::all()->first(function ($pr) use ($providerRef) {
                    $expected = 'PRV-' . strtoupper(substr(md5($pr->agent_id . ':' . $pr->client_request_id), 0, 10));
                    return $expected === $providerRef;
                });
                $policyRequest = $allMatching;
            }

            if (! $policyRequest) {
                return [
                    'status' => 'POLICY_NOT_FOUND',
                    'message' => "No policy request found matching provider_reference [{$providerRef}].",
                    'event_id' => $eventId,
                ];
            }

            // If policy is already ISSUED, do NOT alter or overwrite!
            if ($policyRequest->isIssued()) {
                $event->update(['processed' => true, 'processed_at' => now()]);

                return [
                    'status' => 'ALREADY_ISSUED',
                    'message' => 'Policy is already in confirmed ISSUED state. Callback acknowledged idempotently.',
                    'policy_number' => $policyRequest->policy_number,
                ];
            }

            if ($eventType === 'POLICY_ISSUED') {
                $policyNumber = $payload['policy_number'] ?? ('POL-CB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)));
                $policyRequest->policy_number = $policyNumber;
                $policyRequest->provider_reference = $providerRef;
                $policyRequest->last_response = $payload;
                $policyRequest->save();

                $this->stateMachine->transition(
                    policyRequest: $policyRequest,
                    targetStatus: PolicyStatus::ISSUED,
                    reason: "Policy issued via confirmed provider callback [Event: {$eventId}].",
                    metadata: $payload
                );
            } elseif ($eventType === 'POLICY_REJECTED') {
                $this->stateMachine->transition(
                    policyRequest: $policyRequest,
                    targetStatus: PolicyStatus::FAILED,
                    reason: "Provider rejected policy via callback [Event: {$eventId}].",
                    metadata: $payload
                );
            }

            $event->update(['processed' => true, 'processed_at' => now()]);

            return [
                'status' => 'PROCESSED',
                'policy_request' => $policyRequest->fresh(),
                'message' => 'Provider callback processed successfully.',
            ];
        });
    }
}

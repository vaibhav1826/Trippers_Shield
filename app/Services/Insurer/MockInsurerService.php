<?php

namespace App\Services\Insurer;

use App\Enums\MockScenario;
use App\Exceptions\InsurerTemporaryFailureException;
use App\Exceptions\InsurerTimeoutException;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class MockInsurerService
{
    /**
     * Submit policy to simulated insurer under the given scenario.
     *
     * @throws InsurerTimeoutException
     * @throws InsurerTemporaryFailureException
     */
    public function issuePolicy(PolicyRequest $request, ?string $scenario = null): array
    {
        $chosenScenario = $scenario ? strtoupper($scenario) : MockScenario::SUCCESS->value;

        // Deterministic provider reference based on agent and client request ID
        $providerRef = 'PRV-' . strtoupper(substr(md5($request->agent_id . ':' . $request->client_request_id), 0, 10));

        switch ($chosenScenario) {
            case MockScenario::SUCCESS->value:
                $existing = MockInsurerPolicy::where('provider_reference', $providerRef)->first();
                if ($existing) {
                    $policyNumber = $existing->policy_number;
                } else {
                    $policyNumber = 'POL-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                    MockInsurerPolicy::create([
                        'policy_request_id' => (string) $request->id,
                        'provider_reference' => $providerRef,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'metadata' => [
                            'agent_id' => $request->agent_id,
                            'client_request_id' => $request->client_request_id,
                            'sum_insured' => $request->sum_insured,
                            'premium_amount' => $request->premium_amount,
                        ],
                    ]);
                }

                return [
                    'status' => 'ISSUED',
                    'provider_reference' => $providerRef,
                    'policy_number' => $policyNumber,
                    'message' => 'Policy successfully issued by insurer.',
                ];

            case MockScenario::TEMPORARY_FAILURE->value:
                throw new InsurerTemporaryFailureException(
                    message: "Simulated Provider Error 503: Service Unavailable. Downstream gateway timeout.",
                    payload: [
                        'code' => 'GATEWAY_ERROR',
                        'provider_reference' => $providerRef,
                        'retriable' => true,
                    ]
                );

            case MockScenario::TIMEOUT_AFTER_ISSUE->value:
                // The insurer actually generated and saved the policy internally!
                $existing = MockInsurerPolicy::where('provider_reference', $providerRef)->first();
                if ($existing) {
                    $policyNumber = $existing->policy_number;
                } else {
                    $policyNumber = 'POL-TIMEOUT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                    MockInsurerPolicy::create([
                        'policy_request_id' => (string) $request->id,
                        'provider_reference' => $providerRef,
                        'policy_number' => $policyNumber,
                        'status' => 'ISSUED',
                        'metadata' => [
                            'agent_id' => $request->agent_id,
                            'client_request_id' => $request->client_request_id,
                            'timeout_simulated' => true,
                        ],
                    ]);
                }

                // BUT client call times out before receiving the HTTP response!
                throw new InsurerTimeoutException(
                    message: "HTTP Connection Timeout: No response received from provider within 5000ms.",
                    providerReference: $providerRef,
                    payload: [
                        'provider_reference' => $providerRef,
                        'simulated_scenario' => MockScenario::TIMEOUT_AFTER_ISSUE->value,
                    ]
                );

            case MockScenario::DELAYED_CALLBACK->value:
                $existing = MockInsurerPolicy::where('provider_reference', $providerRef)->first();
                if (! $existing) {
                    $policyNumber = 'POL-ASYNC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
                    MockInsurerPolicy::create([
                        'policy_request_id' => (string) $request->id,
                        'provider_reference' => $providerRef,
                        'policy_number' => $policyNumber,
                        'status' => 'PENDING',
                        'metadata' => [
                            'agent_id' => $request->agent_id,
                            'client_request_id' => $request->client_request_id,
                            'delayed_callback' => true,
                        ],
                    ]);
                }

                return [
                    'status' => 'PENDING',
                    'provider_reference' => $providerRef,
                    'message' => 'Policy issuance queued by provider; confirmation will be sent via webhook callback.',
                ];

            default:
                throw new \InvalidArgumentException("Unknown mock scenario: {$chosenScenario}");
        }
    }

    /**
     * Provider status lookup/reconciliation endpoint logic.
     */
    public function lookupStatus(string $providerReference): ?array
    {
        $policy = MockInsurerPolicy::where('provider_reference', $providerReference)->first();

        if (! $policy) {
            return null;
        }

        return [
            'provider_reference' => $policy->provider_reference,
            'policy_number' => $policy->policy_number,
            'status' => $policy->status,
            'metadata' => $policy->metadata,
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ];
    }
}

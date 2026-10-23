<?php

namespace App\Services\Policy;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;

class PolicyStateMachine
{
    /**
     * Map of valid transitions: [current_status => [allowed_next_statuses]]
     */
    private const ALLOWED_TRANSITIONS = [
        PolicyStatus::RECEIVED->value => [
            PolicyStatus::VALIDATED->value,
            PolicyStatus::FAILED->value,
        ],
        PolicyStatus::VALIDATED->value => [
            PolicyStatus::SUBMITTED->value,
            PolicyStatus::FAILED->value,
        ],
        PolicyStatus::SUBMITTED->value => [
            PolicyStatus::ISSUED->value,
            PolicyStatus::PENDING_CONFIRMATION->value,
            PolicyStatus::NEEDS_RECONCILIATION->value,
            PolicyStatus::FAILED->value,
            PolicyStatus::VALIDATED->value, // Rollback to VALIDATED on temporary retriable failure
        ],
        PolicyStatus::PENDING_CONFIRMATION->value => [
            PolicyStatus::ISSUED->value,
            PolicyStatus::FAILED->value,
            PolicyStatus::NEEDS_RECONCILIATION->value,
        ],
        PolicyStatus::NEEDS_RECONCILIATION->value => [
            PolicyStatus::ISSUED->value,
            PolicyStatus::FAILED->value,
            PolicyStatus::VALIDATED->value, // Reconciled that no policy exists; ready for retry
        ],
        PolicyStatus::FAILED->value => [
            PolicyStatus::VALIDATED->value, // Authorized retry
        ],
        PolicyStatus::ISSUED->value => [
            // TERMINAL STATE: Cannot transition out of ISSUED!
        ],
    ];

    /**
     * Check if a transition is allowed.
     */
    public function canTransition(string|PolicyStatus $from, string|PolicyStatus $to): bool
    {
        $fromVal = $from instanceof PolicyStatus ? $from->value : $from;
        $toVal = $to instanceof PolicyStatus ? $to->value : $to;

        if ($fromVal === $toVal) {
            return true; // Idempotent same-state
        }

        $allowed = self::ALLOWED_TRANSITIONS[$fromVal] ?? [];

        return in_array($toVal, $allowed, true);
    }

    /**
     * Execute a state transition safely within database transaction and log audit trail.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        PolicyRequest $policyRequest,
        string|PolicyStatus $targetStatus,
        ?string $reason = null,
        array $metadata = []
    ): PolicyRequest {
        $toStatus = $targetStatus instanceof PolicyStatus ? $targetStatus->value : $targetStatus;
        $fromStatus = $policyRequest->status;

        // Idempotent: already in target status
        if ($fromStatus === $toStatus) {
            return $policyRequest;
        }

        if (! $this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidStateTransitionException(
                from: $fromStatus,
                to: $toStatus,
                message: "Cannot transition policy [ID: {$policyRequest->id}] from [{$fromStatus}] to [{$toStatus}]. This transition is rejected by policy lifecycle rules."
            );
        }

        return DB::transaction(function () use ($policyRequest, $fromStatus, $toStatus, $reason, $metadata) {
            $policyRequest->status = $toStatus;
            $policyRequest->save();

            PolicyStateHistory::create([
                'policy_request_id' => $policyRequest->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'metadata' => $metadata ?: null,
            ]);

            return $policyRequest;
        });
    }
}

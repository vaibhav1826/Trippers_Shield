<?php

namespace Tests\Feature;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Services\Policy\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIssuanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createValidatedPolicyRequest(): PolicyRequest
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'REQ-WORKFLOW-01',
            'agent_id' => 'AGENT-01',
            'customer_name' => 'Alice Smith',
            'date_of_birth' => '1995-05-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-07-01',
            'trip_end' => '2026-07-07',
            'plan_code' => 'PREMIUM',
            'sum_insured' => 500000,
        ]);

        return PolicyRequest::findOrFail($response->json('data.id'));
    }

    public function test_successful_provider_response_produces_exactly_one_policy_number(): void
    {
        $policyRequest = $this->createValidatedPolicyRequest();

        $submitResponse = $this->postJson("/api/policy-requests/{$policyRequest->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('issuance_status', 'ISSUED')
            ->assertJsonPath('data.status', 'ISSUED');

        $policyNumber = $submitResponse->json('data.policy_number');
        $this->assertNotEmpty($policyNumber);
        $this->assertStringStartsWith('POL-', $policyNumber);

        // Verify policy request updated in database
        $fresh = $policyRequest->fresh();
        $this->assertSame('ISSUED', $fresh->status);
        $this->assertSame($policyNumber, $fresh->policy_number);
        $this->assertNotNull($fresh->provider_reference);
        $this->assertSame(1, $fresh->attempt_count);

        // Verify issuance attempts table logged exactly 1 attempt
        $this->assertDatabaseHas('issuance_attempts', [
            'policy_request_id' => $policyRequest->id,
            'attempt_number' => 1,
            'status' => 'SUCCESS',
        ]);

        // Verify state histories audit trail
        $this->assertDatabaseHas('policy_state_histories', [
            'policy_request_id' => $policyRequest->id,
            'from_status' => 'RECEIVED',
            'to_status' => 'VALIDATED',
        ]);

        $this->assertDatabaseHas('policy_state_histories', [
            'policy_request_id' => $policyRequest->id,
            'from_status' => 'VALIDATED',
            'to_status' => 'SUBMITTED',
        ]);

        $this->assertDatabaseHas('policy_state_histories', [
            'policy_request_id' => $policyRequest->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'ISSUED',
        ]);
    }

    public function test_repeated_submit_on_issued_policy_is_idempotent_and_does_not_reissue(): void
    {
        $policyRequest = $this->createValidatedPolicyRequest();

        // 1. Submit first time -> ISSUED
        $firstSubmit = $this->postJson("/api/policy-requests/{$policyRequest->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $firstSubmit->assertStatus(200);
        $originalPolicyNumber = $firstSubmit->json('data.policy_number');

        // 2. Submit second time -> Idempotent safe return
        $secondSubmit = $this->postJson("/api/policy-requests/{$policyRequest->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $secondSubmit->assertStatus(200)
            ->assertJsonPath('issuance_status', 'ALREADY_ISSUED')
            ->assertJsonPath('data.policy_number', $originalPolicyNumber);

        $fresh = $policyRequest->fresh();
        $this->assertSame($originalPolicyNumber, $fresh->policy_number);
        $this->assertSame(1, $fresh->attempt_count); // Attempts should not increase
    }

    public function test_state_machine_strictly_rejects_invalid_regressions(): void
    {
        $policyRequest = $this->createValidatedPolicyRequest();

        // Issue the policy
        $this->postJson("/api/policy-requests/{$policyRequest->id}/submit", ['scenario' => 'SUCCESS']);
        $issuedPolicy = $policyRequest->fresh();
        $this->assertSame('ISSUED', $issuedPolicy->status);

        $stateMachine = app(PolicyStateMachine::class);

        // Attempt invalid regression: ISSUED -> SUBMITTED
        $this->expectException(InvalidStateTransitionException::class);
        $stateMachine->transition($issuedPolicy, PolicyStatus::SUBMITTED);
    }
}

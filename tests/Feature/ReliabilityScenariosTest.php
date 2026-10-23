<?php

namespace Tests\Feature;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function createSamplePolicy(string $clientRequestId = 'AGT-REQ-REL-01', string $agentId = 'AGENT-99'): PolicyRequest
    {
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => $clientRequestId,
            'agent_id' => $agentId,
            'customer_name' => 'Reliability Test Subject',
            'date_of_birth' => '1992-03-12',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-10',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);

        return PolicyRequest::findOrFail($response->json('data.id'));
    }

    /**
     * RELIABILITY CASE 1: Duplicate Intake
     * Repeated submissions with the same agent_id + client_request_id must not create another logical request.
     */
    public function test_reliability_case_1_duplicate_intake_safety(): void
    {
        $payload = [
            'client_request_id' => 'AGT-REQ-CASE-1',
            'agent_id' => 'AGENT-88',
            'customer_name' => 'Concurrent User',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-08-01',
            'trip_end' => '2026-08-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // Submission 1
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201);
        $createdId = $res1->json('data.id');

        // Submissions 2, 3, 4 (simulating retried / duplicate intake calls)
        for ($i = 0; $i < 3; $i++) {
            $res = $this->postJson('/api/policy-requests', $payload);
            $res->assertStatus(200)
                ->assertJsonPath('data.id', $createdId)
                ->assertJsonPath('status', 'EXISTING')
                ->assertJsonPath('is_duplicate', true);
        }

        // Exact single record in database
        $this->assertSame(1, PolicyRequest::where('client_request_id', 'AGT-REQ-CASE-1')->count());
    }

    /**
     * RELIABILITY CASE 2: Timeout After Success & Safe Reconciliation
     * The insurer issued a policy internally, but client call timed out.
     * System marks policy as NEEDS_RECONCILIATION instead of blindly re-issuing.
     * Reconcile queries provider and confirms the issued policy safely.
     */
    public function test_reliability_case_2_timeout_after_success_and_reconciliation(): void
    {
        $policy = $this->createSamplePolicy('AGT-REQ-CASE-2', 'AGENT-02');

        // 1. Submit with TIMEOUT_AFTER_ISSUE scenario
        $submitResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('issuance_status', 'NEEDS_RECONCILIATION')
            ->assertJsonPath('data.status', 'NEEDS_RECONCILIATION');

        $fresh = $policy->fresh();
        $this->assertSame('NEEDS_RECONCILIATION', $fresh->status);
        $this->assertNull($fresh->policy_number, 'Policy number must not be assumed without confirmation');
        $this->assertNotNull($fresh->provider_reference);

        // 2. Ensure re-submitting while in NEEDS_RECONCILIATION is safely blocked
        $blockedSubmit = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $blockedSubmit->assertStatus(200)
            ->assertJsonPath('issuance_status', 'NEEDS_RECONCILIATION');

        // 3. Reconcile with provider
        $reconcileResponse = $this->postJson("/api/policy-requests/{$policy->id}/reconcile");
        $reconcileResponse->assertStatus(200)
            ->assertJsonPath('reconciliation_status', 'ISSUED')
            ->assertJsonPath('data.status', 'ISSUED');

        $reconciledPolicy = $policy->fresh();
        $this->assertSame('ISSUED', $reconciledPolicy->status);
        $this->assertNotNull($reconciledPolicy->policy_number);
        $this->assertStringStartsWith('POL-TIMEOUT-', $reconciledPolicy->policy_number);

        // Verify the policy number matches what mock insurer stored internally
        $mockRecord = MockInsurerPolicy::where('provider_reference', $reconciledPolicy->provider_reference)->first();
        $this->assertNotNull($mockRecord);
        $this->assertSame($mockRecord->policy_number, $reconciledPolicy->policy_number);
    }

    /**
     * RELIABILITY CASE 3: Duplicate Webhook Callbacks
     * Provider delivers the exact same webhook callback event multiple times.
     * Processing must be idempotent: applied once, duplicates acknowledged without re-execution.
     */
    public function test_reliability_case_3_duplicate_callback_idempotency(): void
    {
        $policy = $this->createSamplePolicy('AGT-REQ-CASE-3', 'AGENT-03');

        // Submit with DELAYED_CALLBACK -> state moves to PENDING_CONFIRMATION
        $submitResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'DELAYED_CALLBACK',
        ]);
        $submitResponse->assertStatus(202)
            ->assertJsonPath('issuance_status', 'PENDING_CONFIRMATION');

        $providerRef = $submitResponse->json('data.provider_reference');
        $this->assertNotNull($providerRef);

        $callbackPayload = [
            'event_id' => 'EVT-WEBHOOK-999',
            'provider_reference' => $providerRef,
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-ASYNC-SUCCESS-777',
            'timestamp' => now()->toIso8601String(),
        ];

        // 1st callback delivery -> PROCESSED
        $cbResponse1 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cbResponse1->assertStatus(200)
            ->assertJsonPath('status', 'PROCESSED');

        $issuedPolicy = $policy->fresh();
        $this->assertSame('ISSUED', $issuedPolicy->status);
        $this->assertSame('POL-ASYNC-SUCCESS-777', $issuedPolicy->policy_number);

        // 2nd callback delivery (identical event_id) -> DUPLICATE_IGNORED
        $cbResponse2 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cbResponse2->assertStatus(200)
            ->assertJsonPath('status', 'DUPLICATE_IGNORED');

        // 3rd callback delivery (identical event_id) -> DUPLICATE_IGNORED
        $cbResponse3 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $cbResponse3->assertStatus(200)
            ->assertJsonPath('status', 'DUPLICATE_IGNORED');

        // Only 1 provider_events record created for this event_id
        $this->assertSame(1, ProviderEvent::where('event_id', 'EVT-WEBHOOK-999')->count());

        // Policy remains intact with the exact original policy number
        $this->assertSame('POL-ASYNC-SUCCESS-777', $policy->fresh()->policy_number);
    }

    /**
     * RELIABILITY CASE 4: Retry After Temporary Failure
     * Retries must not produce duplicate policies or lose the original accepted premium snapshot.
     */
    public function test_reliability_case_4_retry_after_temporary_failure(): void
    {
        $policy = $this->createSamplePolicy('AGT-REQ-CASE-4', 'AGENT-04');
        $originalPremium = $policy->premium_amount;
        $originalBreakdown = $policy->pricing_breakdown;

        // 1st submission: Provider returns TEMPORARY_FAILURE (HTTP 503)
        $failResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);
        $failResponse->assertStatus(503)
            ->assertJsonPath('issuance_status', 'TEMPORARY_FAILURE');

        $failedPolicy = $policy->fresh();
        // State safely rolled back to VALIDATED for clean retry
        $this->assertSame('VALIDATED', $failedPolicy->status);
        $this->assertSame(1, $failedPolicy->attempt_count);
        $this->assertSame($originalPremium, $failedPolicy->premium_amount, 'Original premium must remain immutable');

        // 2nd submission (Retry with SUCCESS scenario)
        $retryResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $retryResponse->assertStatus(200)
            ->assertJsonPath('issuance_status', 'ISSUED');

        $retriedPolicy = $policy->fresh();
        $this->assertSame('ISSUED', $retriedPolicy->status);
        $this->assertSame(2, $retriedPolicy->attempt_count);
        $this->assertNotNull($retriedPolicy->policy_number);
        $this->assertSame($originalPremium, $retriedPolicy->premium_amount, 'Premium must not change during retry');
        $this->assertSame($originalBreakdown, $retriedPolicy->pricing_breakdown);

        // Verify exactly 2 issuance attempts logged: 1 failed, 1 success
        $attempts = $retriedPolicy->issuanceAttempts()->get();
        $this->assertCount(2, $attempts);
        $this->assertSame('FAILED', $attempts[0]->status);
        $this->assertSame('SUCCESS', $attempts[1]->status);
    }

    /**
     * Requirement 8 & 3.4: A repeated callback or request cannot overwrite a confirmed ISSUED state.
     */
    public function test_confirmed_issued_state_is_immutable_against_stale_callbacks(): void
    {
        $policy = $this->createSamplePolicy('AGT-REQ-IMMUTABLE-01', 'AGENT-05');

        // Successfully issue policy
        $this->postJson("/api/policy-requests/{$policy->id}/submit", ['scenario' => 'SUCCESS']);
        $confirmed = $policy->fresh();
        $confirmedPolicyNumber = $confirmed->policy_number;

        // Attempt a conflicting callback with POLICY_REJECTED
        $conflictPayload = [
            'event_id' => 'EVT-CONFLICTING-001',
            'provider_reference' => $confirmed->provider_reference,
            'event_type' => 'POLICY_REJECTED',
            'timestamp' => now()->toIso8601String(),
        ];

        $cbResponse = $this->postJson('/api/mock-insurer/callback', $conflictPayload);
        $cbResponse->assertStatus(200)
            ->assertJsonPath('status', 'ALREADY_ISSUED');

        $stillConfirmed = $policy->fresh();
        $this->assertSame('ISSUED', $stillConfirmed->status);
        $this->assertSame($confirmedPolicyNumber, $stillConfirmed->policy_number);
    }
}

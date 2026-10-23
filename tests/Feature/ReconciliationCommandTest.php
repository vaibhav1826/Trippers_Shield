<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_command_resolves_timeout_policies(): void
    {
        // 1. Create a policy and trigger TIMEOUT_AFTER_ISSUE
        $response = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-CMD-RECON-01',
            'agent_id' => 'AGENT-CMD',
            'customer_name' => 'Command Test',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);
        $policyId = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        $policy = PolicyRequest::findOrFail($policyId);
        $this->assertSame('NEEDS_RECONCILIATION', $policy->status);

        // 2. Run Artisan reconciliation command
        $this->artisan('policy:reconcile')
            ->expectsOutputToContain('Found 1 policy/policies requiring reconciliation.')
            ->expectsOutputToContain('Successfully reconciled: policy confirmed as ISSUED.')
            ->assertSuccessful();

        // 3. Confirm policy status transitioned to ISSUED
        $this->assertSame('ISSUED', $policy->fresh()->status);
        $this->assertNotNull($policy->fresh()->policy_number);
    }
}

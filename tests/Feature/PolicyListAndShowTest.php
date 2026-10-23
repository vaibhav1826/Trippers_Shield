<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyListAndShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_read_policy_request_details_with_audit_trail(): void
    {
        // 1. Create request
        $createRes = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-SHOW-01',
            'agent_id' => 'AGENT-07',
            'customer_name' => 'Robert Bruce',
            'date_of_birth' => '1985-02-20',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-09-01',
            'trip_end' => '2026-09-10',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);
        $policyId = $createRes->json('data.id');

        // 2. Submit policy
        $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);

        // 3. Fetch single request details
        $showRes = $this->getJson("/api/policy-requests/{$policyId}");

        $showRes->assertStatus(200)
            ->assertJsonPath('data.id', $policyId)
            ->assertJsonPath('data.client_request_id', 'AGT-SHOW-01')
            ->assertJsonPath('data.agent_id', 'AGENT-07')
            ->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.travel_days', 10)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'client_request_id',
                    'agent_id',
                    'customer_name',
                    'date_of_birth',
                    'destination',
                    'trip_start',
                    'trip_end',
                    'travel_days',
                    'plan_code',
                    'sum_insured',
                    'premium_amount',
                    'pricing_breakdown',
                    'status',
                    'policy_number',
                    'provider_reference',
                    'attempt_count',
                    'state_history',
                    'issuance_attempts',
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($showRes->json('data.state_history')));
        $this->assertCount(1, $showRes->json('data.issuance_attempts'));
    }

    public function test_can_list_policy_requests_with_filter_and_pagination(): void
    {
        // Create 3 requests
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/policy-requests', [
                'client_request_id' => "AGT-LIST-{$i}",
                'agent_id' => $i === 1 ? 'AGENT-ALPHA' : 'AGENT-BETA',
                'customer_name' => "Customer {$i}",
                'date_of_birth' => '1990-01-01',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-10-01',
                'trip_end' => '2026-10-05',
                'plan_code' => 'BASIC',
                'sum_insured' => 100000,
            ]);
        }

        // List all with pagination
        $allRes = $this->getJson('/api/policy-requests?per_page=2');
        $allRes->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        // Filter by agent_id
        $agentRes = $this->getJson('/api/policy-requests?agent_id=AGENT-ALPHA');
        $agentRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.agent_id', 'AGENT-ALPHA');

        // Filter by status
        $statusRes = $this->getJson('/api/policy-requests?status=VALIDATED');
        $statusRes->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }
}

<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_policy_intake_creates_request_with_correct_premium_and_status(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.client_request_id', 'AGT-2026-0001')
            ->assertJsonPath('data.agent_id', 'AGENT-17')
            ->assertJsonPath('data.status', 'VALIDATED')
            ->assertJsonPath('data.travel_days', 5)
            ->assertJsonPath('data.premium_amount', 225)
            ->assertJsonPath('status', 'CREATED')
            ->assertJsonPath('is_duplicate', false);

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'status' => 'VALIDATED',
            'premium_amount' => 225.00,
            'travel_days' => 5,
        ]);

        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_duplicate_intake_returns_200_and_does_not_create_another_record(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-DUP-01',
            'agent_id' => 'AGENT-10',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // 1st request -> 201 Created
        $firstResponse = $this->postJson('/api/policy-requests', $payload);
        $firstResponse->assertStatus(201);
        $originalId = $firstResponse->json('data.id');

        // 2nd identical request -> 200 OK (idempotent duplicate intake)
        $secondResponse = $this->postJson('/api/policy-requests', $payload);
        $secondResponse->assertStatus(200)
            ->assertJsonPath('data.id', $originalId)
            ->assertJsonPath('status', 'EXISTING')
            ->assertJsonPath('is_duplicate', true);

        // Database should only contain exactly 1 logical request
        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_invalid_travel_dates_are_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-INVALID-DATE',
            'agent_id' => 'AGENT-10',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-10',
            'trip_end' => '2026-06-05', // Earlier than trip_start
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trip_end'])
            ->assertJsonPath('errors.trip_end.0', 'The trip_end date must be greater than or equal to trip_start.');
    }

    public function test_unsupported_plan_code_is_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-INVALID-PLAN',
            'agent_id' => 'AGENT-10',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
            'plan_code' => 'SUPER_PLATINUM', // Unsupported plan
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code']);
    }

    public function test_unsupported_sum_insured_is_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-INVALID-SUM',
            'agent_id' => 'AGENT-10',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 999999, // Unsupported sum_insured
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sum_insured']);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $response = $this->postJson('/api/policy-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_request_id',
                'agent_id',
                'customer_name',
                'date_of_birth',
                'destination',
                'trip_start',
                'trip_end',
                'plan_code',
                'sum_insured',
            ]);
    }
}

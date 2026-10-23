<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCsvIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_csv_intake_with_valid_and_invalid_and_duplicate_rows(): void
    {
        $csvContent = <<<CSV
client_request_id,agent_id,customer_name,date_of_birth,destination,trip_start,trip_end,plan_code,sum_insured
BULK-01,AGENT-01,Valid User,1995-01-01,DOMESTIC,2026-06-01,2026-06-05,BASIC,100000
BULK-02,AGENT-01,Invalid Dates,1995-01-01,DOMESTIC,2026-06-10,2026-06-05,BASIC,100000
BULK-01,AGENT-01,Valid User Dup,1995-01-01,DOMESTIC,2026-06-01,2026-06-05,BASIC,100000
BULK-03,AGENT-02,Bad Plan,1995-01-01,DOMESTIC,2026-06-01,2026-06-05,INVALID_PLAN,100000
CSV;

        $response = $this->postJson('/api/policy-requests/bulk-csv', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('total_rows', 4)
            ->assertJsonPath('successful', 1)
            ->assertJsonPath('duplicates', 1)
            ->assertJsonPath('failed', 2);

        $this->assertDatabaseCount('policy_requests', 1);
    }
}

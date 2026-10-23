<?php

namespace Database\Seeders;

use App\Enums\PolicyStatus;
use App\Models\IssuanceAttempt;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\Pricing\PremiumCalculator;
use Illuminate\Database\Seeder;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        $calculator = new PremiumCalculator();

        // 1. ISSUED Policy
        $pricing1 = $calculator->calculate('PLUS', 300000, 'INTERNATIONAL', '1998-04-10', '2026-11-10', '2026-11-14');
        $policy1 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'travel_days' => $pricing1->travelDays,
            'premium_amount' => $pricing1->premiumAmount,
            'pricing_breakdown' => $pricing1->breakdown,
            'status' => PolicyStatus::ISSUED->value,
            'policy_number' => 'POL-20261110-A9B8C7',
            'provider_reference' => 'PRV-DEMO-0001',
            'attempt_count' => 1,
            'last_response' => ['status' => 'ISSUED', 'policy_number' => 'POL-20261110-A9B8C7'],
        ]);

        MockInsurerPolicy::create([
            'policy_request_id' => (string) $policy1->id,
            'provider_reference' => 'PRV-DEMO-0001',
            'policy_number' => 'POL-20261110-A9B8C7',
            'status' => 'ISSUED',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => 'RECEIVED',
            'to_status' => 'VALIDATED',
            'reason' => 'Request validated and priced.',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => 'VALIDATED',
            'to_status' => 'SUBMITTED',
            'reason' => 'Submitted to provider.',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'ISSUED',
            'reason' => 'Provider confirmed policy issuance.',
        ]);
        IssuanceAttempt::create([
            'policy_request_id' => $policy1->id,
            'attempt_number' => 1,
            'scenario' => 'SUCCESS',
            'status' => 'SUCCESS',
            'response_payload' => ['policy_number' => 'POL-20261110-A9B8C7'],
        ]);

        // 2. VALIDATED Policy (Ready to submit)
        $pricing2 = $calculator->calculate('PREMIUM', 500000, 'INTERNATIONAL', '1985-06-20', '2026-12-01', '2026-12-15');
        $policy2 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-24',
            'customer_name' => 'Vikram Malhotra',
            'date_of_birth' => '1985-06-20',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-15',
            'plan_code' => 'PREMIUM',
            'sum_insured' => 500000,
            'travel_days' => $pricing2->travelDays,
            'premium_amount' => $pricing2->premiumAmount,
            'pricing_breakdown' => $pricing2->breakdown,
            'status' => PolicyStatus::VALIDATED->value,
            'attempt_count' => 0,
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy2->id,
            'from_status' => 'RECEIVED',
            'to_status' => 'VALIDATED',
            'reason' => 'Request validated and priced.',
        ]);

        // 3. NEEDS_RECONCILIATION Policy (Timed out call, safe recovery ready)
        $pricing3 = $calculator->calculate('BASIC', 100000, 'DOMESTIC', '1992-09-15', '2026-10-01', '2026-10-07');
        $providerRef3 = 'PRV-' . strtoupper(substr(md5('AGENT-05:AGT-2026-0003'), 0, 10));
        $policy3 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-05',
            'customer_name' => 'Ananya Sharma',
            'date_of_birth' => '1992-09-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-10-01',
            'trip_end' => '2026-10-07',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => $pricing3->travelDays,
            'premium_amount' => $pricing3->premiumAmount,
            'pricing_breakdown' => $pricing3->breakdown,
            'status' => PolicyStatus::NEEDS_RECONCILIATION->value,
            'provider_reference' => $providerRef3,
            'attempt_count' => 1,
            'last_error' => 'HTTP Connection Timeout: No response received from provider within 5000ms.',
        ]);
        MockInsurerPolicy::create([
            'policy_request_id' => (string) $policy3->id,
            'provider_reference' => $providerRef3,
            'policy_number' => 'POL-TIMEOUT-2026-RECON01',
            'status' => 'ISSUED',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => 'RECEIVED',
            'to_status' => 'VALIDATED',
            'reason' => 'Request validated.',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => 'VALIDATED',
            'to_status' => 'SUBMITTED',
            'reason' => 'Issuance attempt #1 initiated.',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'NEEDS_RECONCILIATION',
            'reason' => 'Timeout occurred during provider call. Reconcile required.',
        ]);
        IssuanceAttempt::create([
            'policy_request_id' => $policy3->id,
            'attempt_number' => 1,
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
            'status' => 'TIMEOUT',
            'error_message' => 'HTTP Connection Timeout: No response received within 5000ms.',
        ]);

        // 4. PENDING_CONFIRMATION Policy (Awaiting delayed callback)
        $pricing4 = $calculator->calculate('PLUS', 300000, 'DOMESTIC', '1970-03-10', '2026-11-01', '2026-11-05');
        $providerRef4 = 'PRV-' . strtoupper(substr(md5('AGENT-12:AGT-2026-0004'), 0, 10));
        $policy4 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0004',
            'agent_id' => 'AGENT-12',
            'customer_name' => 'Rajesh Gupta',
            'date_of_birth' => '1970-03-10',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'travel_days' => $pricing4->travelDays,
            'premium_amount' => $pricing4->premiumAmount,
            'pricing_breakdown' => $pricing4->breakdown,
            'status' => PolicyStatus::PENDING_CONFIRMATION->value,
            'provider_reference' => $providerRef4,
            'attempt_count' => 1,
        ]);
        MockInsurerPolicy::create([
            'policy_request_id' => (string) $policy4->id,
            'provider_reference' => $providerRef4,
            'policy_number' => 'POL-ASYNC-2026-PEND01',
            'status' => 'PENDING',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy4->id,
            'from_status' => 'RECEIVED',
            'to_status' => 'VALIDATED',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy4->id,
            'from_status' => 'VALIDATED',
            'to_status' => 'SUBMITTED',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy4->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'PENDING_CONFIRMATION',
            'reason' => 'Provider queued issuance asynchronously.',
        ]);
    }
}

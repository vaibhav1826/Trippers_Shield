<?php

namespace Tests\Unit;

use App\Enums\Coverage;
use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Exceptions\PricingException;
use App\Services\Pricing\PremiumCalculator;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorTest extends TestCase
{
    private PremiumCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PremiumCalculator();
    }

    public function test_it_calculates_example_from_specification_correctly(): void
    {
        // Specification example:
        // client_request_id: AGT-2026-0001
        // agent_id: AGENT-17
        // customer_name: Sample Customer
        // date_of_birth: 1998-04-10
        // destination: INTERNATIONAL
        // trip_start: 2026-11-10
        // trip_end: 2026-11-14
        // plan_code: PLUS
        // sum_insured: 300000
        // Expected: travel_days = 5, age multiplier = 1.00, premium = 20 * 5 * 1.00 * 1.80 * 1.25 = 225.00
        $result = $this->calculator->calculate(
            planCode: PlanCode::PLUS,
            sumInsured: Coverage::MEDIUM_300K,
            destination: Destination::INTERNATIONAL,
            dateOfBirth: '1998-04-10',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14'
        );

        $this->assertSame(5, $result->travelDays);
        $this->assertSame(28, $result->ageAtTripStart);
        $this->assertSame('1.00', $result->ageMultiplier);
        $this->assertSame('1.80', $result->destinationMultiplier);
        $this->assertSame('1.25', $result->coverageMultiplier);
        $this->assertSame('20.00', $result->baseDailyRate);
        $this->assertSame('225.00', $result->premiumAmount);
    }

    public function test_age_multiplier_brackets(): void
    {
        // 0-17: 1.10
        $this->assertSame('1.10', $this->calculator->determineAgeMultiplier(0));
        $this->assertSame('1.10', $this->calculator->determineAgeMultiplier(17));

        // 18-60: 1.00
        $this->assertSame('1.00', $this->calculator->determineAgeMultiplier(18));
        $this->assertSame('1.00', $this->calculator->determineAgeMultiplier(60));

        // 61-70: 1.50
        $this->assertSame('1.50', $this->calculator->determineAgeMultiplier(61));
        $this->assertSame('1.50', $this->calculator->determineAgeMultiplier(70));

        // 71+: 2.00
        $this->assertSame('2.00', $this->calculator->determineAgeMultiplier(71));
        $this->assertSame('2.00', $this->calculator->determineAgeMultiplier(99));
    }

    public function test_single_day_trip_minimum_travel_days(): void
    {
        // trip_start == trip_end -> 1 day inclusive
        $result = $this->calculator->calculate(
            planCode: 'BASIC',
            sumInsured: 100000,
            destination: 'DOMESTIC',
            dateOfBirth: '2000-01-01',
            tripStart: '2026-05-01',
            tripEnd: '2026-05-01'
        );

        $this->assertSame(1, $result->travelDays);
        // BASIC (12) * 1 day * 1.00 (age 26) * 1.00 (DOMESTIC) * 1.00 (100k) = 12.00
        $this->assertSame('12.00', $result->premiumAmount);
    }

    public function test_it_rejects_unsupported_plan(): void
    {
        $this->expectException(PricingException::class);
        $this->calculator->calculate(
            planCode: 'UNKNOWN_PLAN',
            sumInsured: 100000,
            destination: 'DOMESTIC',
            dateOfBirth: '2000-01-01',
            tripStart: '2026-05-01',
            tripEnd: '2026-05-05'
        );
    }

    public function test_it_rejects_unsupported_sum_insured(): void
    {
        $this->expectException(PricingException::class);
        $this->calculator->calculate(
            planCode: 'BASIC',
            sumInsured: 999999,
            destination: 'DOMESTIC',
            dateOfBirth: '2000-01-01',
            tripStart: '2026-05-01',
            tripEnd: '2026-05-05'
        );
    }

    public function test_it_rejects_inverted_dates(): void
    {
        $this->expectException(PricingException::class);
        $this->calculator->calculate(
            planCode: 'BASIC',
            sumInsured: 100000,
            destination: 'DOMESTIC',
            dateOfBirth: '2000-01-01',
            tripStart: '2026-05-10',
            tripEnd: '2026-05-05'
        );
    }
}

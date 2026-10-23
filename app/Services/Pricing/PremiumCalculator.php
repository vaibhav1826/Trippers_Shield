<?php

namespace App\Services\Pricing;

use App\Enums\Coverage;
use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Exceptions\PricingException;
use Carbon\Carbon;

class PremiumCalculator
{
    /**
     * Calculate premium deterministically according to assignment specification.
     *
     * Formula:
     * premium = base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
     *
     * @throws PricingException
     */
    public function calculate(
        string|PlanCode $planCode,
        int|float|string|Coverage $sumInsured,
        string|Destination $destination,
        string|Carbon $dateOfBirth,
        string|Carbon $tripStart,
        string|Carbon $tripEnd
    ): PricingResult {
        // 1. Resolve Plan
        $plan = $planCode instanceof PlanCode ? $planCode : PlanCode::tryFrom(strtoupper((string) $planCode));
        if (! $plan) {
            throw new PricingException("Unsupported plan code: [{$planCode}]. Supported plans: " . implode(', ', PlanCode::values()));
        }
        $baseDailyRate = $plan->baseDailyRate();

        // 2. Resolve Sum Insured / Coverage
        $coverage = $sumInsured instanceof Coverage ? $sumInsured : Coverage::tryFromInt($sumInsured);
        if (! $coverage) {
            throw new PricingException("Unsupported sum insured: [{$sumInsured}]. Supported amounts: " . implode(', ', Coverage::values()));
        }
        $coverageMultiplier = $coverage->multiplier();

        // 3. Resolve Destination
        $dest = $destination instanceof Destination ? $destination : Destination::tryFrom(strtoupper((string) $destination));
        if (! $dest) {
            throw new PricingException("Unsupported destination: [{$destination}]. Supported: " . implode(', ', Destination::values()));
        }
        $destinationMultiplier = $dest->multiplier();

        // 4. Resolve Dates & Travel Days
        $startDate = $tripStart instanceof Carbon ? $tripStart->copy()->startOfDay() : Carbon::parse($tripStart)->startOfDay();
        $endDate = $tripEnd instanceof Carbon ? $tripEnd->copy()->startOfDay() : Carbon::parse($tripEnd)->startOfDay();
        $dobDate = $dateOfBirth instanceof Carbon ? $dateOfBirth->copy()->startOfDay() : Carbon::parse($dateOfBirth)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new PricingException("trip_end ({$endDate->toDateString()}) cannot be earlier than trip_start ({$startDate->toDateString()}).");
        }

        // Inclusive travel days: trip_end - trip_start + 1; minimum 1
        $diffDays = $startDate->diffInDays($endDate);
        $travelDays = (int) max(1, $diffDays + 1);

        // 5. Age Multiplier (calculated on trip_start)
        if ($dobDate->gt($startDate)) {
            throw new PricingException("date_of_birth ({$dobDate->toDateString()}) cannot be in the future relative to trip_start.");
        }
        $ageAtTripStart = (int) $dobDate->diffInYears($startDate);
        $ageMultiplier = $this->determineAgeMultiplier($ageAtTripStart);

        // 6. Decimal-safe multiplication using BCMath
        // Formula: premium = base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
        $step1 = bcmul($baseDailyRate, (string) $travelDays, 6);
        $step2 = bcmul($step1, $ageMultiplier, 6);
        $step3 = bcmul($step2, $destinationMultiplier, 6);
        $step4 = bcmul($step3, $coverageMultiplier, 6);

        // Round to 2 decimal places using half-up arithmetic
        $roundedPremium = number_format((float) $step4, 2, '.', '');

        return new PricingResult(
            premiumAmount: $roundedPremium,
            travelDays: $travelDays,
            ageAtTripStart: $ageAtTripStart,
            baseDailyRate: $baseDailyRate,
            ageMultiplier: $ageMultiplier,
            destinationMultiplier: $destinationMultiplier,
            coverageMultiplier: $coverageMultiplier,
            breakdown: [
                'formula' => 'base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier',
                'plan_code' => $plan->value,
                'base_daily_rate' => (float) $baseDailyRate,
                'travel_days' => $travelDays,
                'trip_start' => $startDate->toDateString(),
                'trip_end' => $endDate->toDateString(),
                'age_at_trip_start' => $ageAtTripStart,
                'age_multiplier' => (float) $ageMultiplier,
                'destination' => $dest->value,
                'destination_multiplier' => (float) $destinationMultiplier,
                'sum_insured' => $coverage->value,
                'coverage_multiplier' => (float) $coverageMultiplier,
                'calculated_raw' => (float) $step4,
                'final_premium' => (float) $roundedPremium,
            ]
        );
    }

    /**
     * Rule:
     * 0–17: 1.10
     * 18–60: 1.00
     * 61–70: 1.50
     * 71+: 2.00
     */
    public function determineAgeMultiplier(int $age): string
    {
        if ($age < 0) {
            throw new PricingException("Age cannot be negative: {$age}");
        }

        if ($age <= 17) {
            return '1.10';
        }

        if ($age <= 60) {
            return '1.00';
        }

        if ($age <= 70) {
            return '1.50';
        }

        return '2.00';
    }
}

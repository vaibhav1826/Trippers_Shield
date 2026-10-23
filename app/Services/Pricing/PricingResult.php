<?php

namespace App\Services\Pricing;

class PricingResult
{
    public function __construct(
        public readonly string $premiumAmount,
        public readonly int $travelDays,
        public readonly int $ageAtTripStart,
        public readonly string $baseDailyRate,
        public readonly string $ageMultiplier,
        public readonly string $destinationMultiplier,
        public readonly string $coverageMultiplier,
        public readonly array $breakdown
    ) {}

    public function toArray(): array
    {
        return [
            'premium_amount' => $this->premiumAmount,
            'travel_days' => $this->travelDays,
            'age_at_trip_start' => $this->ageAtTripStart,
            'base_daily_rate' => $this->baseDailyRate,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'breakdown' => $this->breakdown,
        ];
    }
}

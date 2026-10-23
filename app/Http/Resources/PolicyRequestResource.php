<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_request_id' => $this->client_request_id,
            'agent_id' => $this->agent_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'travel_days' => $this->travel_days,
            'plan_code' => $this->plan_code,
            'sum_insured' => (float) $this->sum_insured,
            'premium_amount' => (float) $this->premium_amount,
            'pricing_breakdown' => $this->pricing_breakdown,
            'status' => $this->status,
            'policy_number' => $this->policy_number,
            'provider_reference' => $this->provider_reference,
            'attempt_count' => $this->attempt_count,
            'last_response' => $this->last_response,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'state_history' => $this->whenLoaded('stateHistories', function () {
                return $this->stateHistories->map(fn ($h) => [
                    'id' => $h->id,
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'reason' => $h->reason,
                    'metadata' => $h->metadata,
                    'timestamp' => $h->created_at?->toIso8601String(),
                ]);
            }),
            'issuance_attempts' => $this->whenLoaded('issuanceAttempts', function () {
                return $this->issuanceAttempts->map(fn ($a) => [
                    'attempt_number' => $a->attempt_number,
                    'scenario' => $a->scenario,
                    'status' => $a->status,
                    'error_message' => $a->error_message,
                    'timestamp' => $a->created_at?->toIso8601String(),
                ]);
            }),
        ];
    }
}

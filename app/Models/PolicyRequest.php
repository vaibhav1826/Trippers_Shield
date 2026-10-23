<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'premium_amount',
        'pricing_breakdown',
        'status',
        'policy_number',
        'provider_reference',
        'attempt_count',
        'last_response',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'sum_insured' => 'float',
        'premium_amount' => 'float',
        'pricing_breakdown' => 'array',
        'last_response' => 'array',
        'attempt_count' => 'integer',
        'travel_days' => 'integer',
    ];

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number', 'asc');
    }

    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at', 'asc');
    }

    public function isIssued(): bool
    {
        return $this->status === PolicyStatus::ISSUED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === PolicyStatus::FAILED->value;
    }

    public function isNeedsReconciliation(): bool
    {
        return $this->status === PolicyStatus::NEEDS_RECONCILIATION->value;
    }

    public function isPendingConfirmation(): bool
    {
        return $this->status === PolicyStatus::PENDING_CONFIRMATION->value;
    }
}

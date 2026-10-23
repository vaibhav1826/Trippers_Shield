<?php

namespace App\Http\Requests;

use App\Enums\Coverage;
use App\Enums\Destination;
use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePolicyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supportedPlans = implode(',', PlanCode::values());
        $supportedDestinations = implode(',', Destination::values());
        $supportedCoverages = implode(',', Coverage::values());

        return [
            'client_request_id' => ['required', 'string', 'max:100'],
            'agent_id' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d'],
            'destination' => ['required', 'string', "in:{$supportedDestinations}"],
            'trip_start' => ['required', 'date', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', "in:{$supportedPlans}"],
            'sum_insured' => ['required', 'numeric', "in:{$supportedCoverages}"],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip_end date must be greater than or equal to trip_start.',
            'plan_code.in' => 'Unsupported plan_code. Supported plans are: ' . implode(', ', PlanCode::values()),
            'destination.in' => 'Unsupported destination. Supported destinations are: ' . implode(', ', Destination::values()),
            'sum_insured.in' => 'Unsupported sum_insured. Supported amounts are: ' . implode(', ', Coverage::values()),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'VALIDATION_ERROR',
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}

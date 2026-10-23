<?php

namespace App\Http\Controllers;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Services\Insurer\MockInsurerService;
use App\Services\Policy\PolicyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockInsurerController extends Controller
{
    public function __construct(
        protected MockInsurerService $mockInsurer,
        protected PolicyRequestService $policyService
    ) {}

    /**
     * Webhook callback receiver: POST /api/mock-insurer/callback
     * Processes external provider events idempotently.
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();

        $result = $this->policyService->handleCallback($payload);

        return response()->json($result, 200);
    }

    /**
     * Provider lookup/reconciliation: GET /api/mock-insurer/status/{provider_reference}
     */
    public function status(string $providerReference): JsonResponse
    {
        $status = $this->mockInsurer->lookupStatus($providerReference);

        if (! $status) {
            return response()->json([
                'status' => 'NOT_FOUND',
                'message' => "Provider reference [{$providerReference}] not found in insurer records.",
            ], 404);
        }

        return response()->json($status, 200);
    }

    /**
     * Helper to simulate provider triggering a delayed callback:
     * POST /api/mock-insurer/trigger-callback/{provider_reference}
     */
    public function triggerCallback(Request $request, string $providerReference): JsonResponse
    {
        $policy = MockInsurerPolicy::where('provider_reference', $providerReference)->first();

        if (! $policy) {
            return response()->json([
                'status' => 'NOT_FOUND',
                'message' => "No mock policy found for reference [{$providerReference}].",
            ], 404);
        }

        // Transition mock insurer status to ISSUED
        $policy->status = 'ISSUED';
        $policy->save();

        // Dispatch callback event
        $eventId = 'EVT-' . strtoupper(substr(md5($providerReference . '-callback'), 0, 12));
        $eventPayload = [
            'event_id' => $eventId,
            'provider_reference' => $providerReference,
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => $policy->policy_number,
            'timestamp' => now()->toIso8601String(),
        ];

        $callbackResult = $this->policyService->handleCallback($eventPayload);

        return response()->json([
            'triggered' => true,
            'event_payload' => $eventPayload,
            'callback_result' => $callbackResult,
        ], 200);
    }
}

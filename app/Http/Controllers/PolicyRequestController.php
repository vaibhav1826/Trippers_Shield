<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePolicyRequestRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\Policy\PolicyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyRequestService $policyService
    ) {}

    /**
     * Intake endpoint: POST /api/policy-requests
     * Idempotent intake.
     * HTTP 201 for freshly created request.
     * HTTP 200 for safely returned existing request.
     */
    public function store(CreatePolicyRequestRequest $request): JsonResponse
    {
        $result = $this->policyService->createOrGet($request->validated());

        /** @var PolicyRequest $policyRequest */
        $policyRequest = $result['policy_request'];
        $wasCreated = $result['created'];

        $statusCode = $wasCreated ? 201 : 200;

        return (new PolicyRequestResource($policyRequest))
            ->additional([
                'status' => $wasCreated ? 'CREATED' : 'EXISTING',
                'message' => $wasCreated
                    ? 'Policy request successfully accepted and validated.'
                    : 'Duplicate intake detected: safely returning existing policy request.',
                'is_duplicate' => ! $wasCreated,
            ])
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * Read endpoint: GET /api/policy-requests/{id}
     * Returns full status, premium, references, and audit history.
     */
    public function show(int|string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->findOrFail($id);

        return (new PolicyRequestResource($policyRequest))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * List endpoint: GET /api/policy-requests
     * Supports status filter, agent_id filter, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()->with(['stateHistories']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginated = $query->latest('id')->paginate($perPage);

        return PolicyRequestResource::collection($paginated)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Submit endpoint: POST /api/policy-requests/{id}/submit
     * Dispatches request to provider (supports scenario header or body param for deterministic testing).
     */
    public function submit(Request $request, int|string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->findOrFail($id);

        // Allow scenario from header or request body
        $scenario = $request->header('X-Mock-Scenario', $request->input('scenario'));

        $result = $this->policyService->submitForIssuance($policyRequest, $scenario);

        $policyRequest = $result['policy_request'];
        $status = $result['status'];

        $httpCode = match ($status) {
            'ISSUED', 'ALREADY_ISSUED' => 200,
            'PENDING_CONFIRMATION' => 202,
            'NEEDS_RECONCILIATION' => 200,
            'TEMPORARY_FAILURE' => 503,
            default => 400,
        };

        return (new PolicyRequestResource($policyRequest))
            ->additional([
                'issuance_status' => $status,
                'message' => $result['message'],
            ])
            ->response()
            ->setStatusCode($httpCode);
    }

    /**
     * Reconcile endpoint: POST /api/policy-requests/{id}/reconcile
     * Queries external provider to resolve ambiguous timeout or pending states.
     */
    public function reconcile(Request $request, int|string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->findOrFail($id);

        $result = $this->policyService->reconcile($policyRequest);

        return (new PolicyRequestResource($result['policy_request']))
            ->additional([
                'reconciliation_status' => $result['status'],
                'message' => $result['message'],
            ])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Stretch Feature: Bulk CSV intake with row-level error reporting
     * POST /api/policy-requests/bulk-csv
     */
    public function bulkCsv(Request $request, \App\Services\Policy\BulkPolicyIntakeService $bulkService): JsonResponse
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $summary = $bulkService->processCsv($file);
        } elseif ($request->filled('csv_content')) {
            $summary = $bulkService->processCsv($request->input('csv_content'));
        } else {
            return response()->json([
                'status' => 'BAD_REQUEST',
                'message' => 'Please provide a CSV file in multipart field [file] or raw content in [csv_content].',
            ], 400);
        }

        return response()->json($summary, 200);
    }
}

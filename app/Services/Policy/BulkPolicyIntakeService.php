<?php

namespace App\Services\Policy;

use App\Http\Requests\CreatePolicyRequestRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class BulkPolicyIntakeService
{
    public function __construct(
        protected PolicyRequestService $policyService
    ) {}

    /**
     * Process bulk CSV file or raw CSV string with row-level reporting.
     */
    public function processCsv(UploadedFile|string $fileOrContent): array
    {
        $content = $fileOrContent instanceof UploadedFile
            ? file_get_contents($fileOrContent->getRealPath())
            : $fileOrContent;

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [
                'total_rows' => 0,
                'successful' => 0,
                'duplicates' => 0,
                'failed' => 0,
                'errors' => ['CSV file is empty.'],
                'rows' => [],
            ];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => trim(strtolower($h)), $header);

        $results = [];
        $successCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        $dummyRequest = new CreatePolicyRequestRequest();
        $rules = $dummyRequest->rules();
        $messages = $dummyRequest->messages();

        foreach ($lines as $index => $line) {
            $rowNum = $index + 2; // 1-indexed, header is row 1
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line);
            if (count($values) !== count($header)) {
                $failedCount++;
                $results[] = [
                    'row' => $rowNum,
                    'status' => 'FAILED',
                    'errors' => ["Column count mismatch. Expected " . count($header) . ", got " . count($values)],
                ];
                continue;
            }

            $rowRaw = array_combine($header, array_map('trim', $values));

            // Normalize fields
            $rowData = [
                'client_request_id' => $rowRaw['client_request_id'] ?? null,
                'agent_id' => $rowRaw['agent_id'] ?? null,
                'customer_name' => $rowRaw['customer_name'] ?? null,
                'date_of_birth' => $rowRaw['date_of_birth'] ?? null,
                'destination' => isset($rowRaw['destination']) ? strtoupper($rowRaw['destination']) : null,
                'trip_start' => $rowRaw['trip_start'] ?? null,
                'trip_end' => $rowRaw['trip_end'] ?? null,
                'plan_code' => isset($rowRaw['plan_code']) ? strtoupper($rowRaw['plan_code']) : null,
                'sum_insured' => isset($rowRaw['sum_insured']) ? (int) $rowRaw['sum_insured'] : null,
            ];

            $validator = Validator::make($rowData, $rules, $messages);
            if ($validator->fails()) {
                $failedCount++;
                $results[] = [
                    'row' => $rowNum,
                    'client_request_id' => $rowData['client_request_id'],
                    'status' => 'FAILED',
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            try {
                $intake = $this->policyService->createOrGet($rowData);
                $isNew = $intake['created'];
                $policy = $intake['policy_request'];

                if ($isNew) {
                    $successCount++;
                    $results[] = [
                        'row' => $rowNum,
                        'client_request_id' => $policy->client_request_id,
                        'policy_request_id' => $policy->id,
                        'status' => 'CREATED',
                        'premium_amount' => $policy->premium_amount,
                    ];
                } else {
                    $duplicateCount++;
                    $results[] = [
                        'row' => $rowNum,
                        'client_request_id' => $policy->client_request_id,
                        'policy_request_id' => $policy->id,
                        'status' => 'DUPLICATE_EXISTING',
                        'premium_amount' => $policy->premium_amount,
                    ];
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'row' => $rowNum,
                    'client_request_id' => $rowData['client_request_id'],
                    'status' => 'FAILED',
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return [
            'total_rows' => count($results),
            'successful' => $successCount,
            'duplicates' => $duplicateCount,
            'failed' => $failedCount,
            'rows' => $results,
        ];
    }
}

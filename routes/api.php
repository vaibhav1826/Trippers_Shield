<?php

use App\Http\Controllers\MockInsurerController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POLICYFLOW API Routes
| Fault-Tolerant Insurance Issuance & Reconciliation Engine
|--------------------------------------------------------------------------
*/

// Policy Request Intake & Management
Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::post('/policy-requests/bulk-csv', [PolicyRequestController::class, 'bulkCsv']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::get('/policy-requests/{id}', [PolicyRequestController::class, 'show']);
Route::post('/policy-requests/{id}/submit', [PolicyRequestController::class, 'submit']);
Route::post('/policy-requests/{id}/reconcile', [PolicyRequestController::class, 'reconcile']);

// Mock Insurer & Webhook Endpoints
Route::post('/mock-insurer/callback', [MockInsurerController::class, 'callback']);
Route::get('/mock-insurer/status/{provider_reference}', [MockInsurerController::class, 'status']);
Route::post('/mock-insurer/trigger-callback/{provider_reference}', [MockInsurerController::class, 'triggerCallback']);

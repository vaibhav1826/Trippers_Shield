#!/usr/bin/env bash

# ==============================================================================
# POLICYFLOW - Fault-Tolerant Insurance Issuance Engine
# End-to-End API Demonstration Script (cURL)
# ==============================================================================

BASE_URL="http://localhost:8000/api"

echo "----------------------------------------------------------------------"
echo "1. Policy Intake (Fresh Request -> Expect HTTP 201 Created)"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "2. Duplicate Intake (Same agent_id + client_request_id -> Expect HTTP 200 OK)"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "3. Validation Failure (trip_end < trip_start -> Expect HTTP 422)"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-BAD-DATE",
    "agent_id": "AGENT-17",
    "customer_name": "Bad Date User",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-14",
    "trip_end": "2026-11-10",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }' | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "4. Submit Policy (SUCCESS Scenario -> Expect HTTP 200, status: ISSUED)"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests/1/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{ "scenario": "SUCCESS" }' | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "5. Submit Policy (TIMEOUT_AFTER_ISSUE Scenario -> Expect NEEDS_RECONCILIATION)"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests/2/submit" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{ "scenario": "TIMEOUT_AFTER_ISSUE" }' | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "6. Reconcile Policy after Timeout -> Resolves to ISSUED safely"
echo "----------------------------------------------------------------------"
curl -s -X POST "${BASE_URL}/policy-requests/2/reconcile" \
  -H "Accept: application/json" | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "7. Read Policy Status & Full Audit Trail"
echo "----------------------------------------------------------------------"
curl -s -X GET "${BASE_URL}/policy-requests/1" \
  -H "Accept: application/json" | jq .

echo ""
echo "----------------------------------------------------------------------"
echo "8. List Policies with Pagination & Filter"
echo "----------------------------------------------------------------------"
curl -s -X GET "${BASE_URL}/policy-requests?status=ISSUED&per_page=5" \
  -H "Accept: application/json" | jq .

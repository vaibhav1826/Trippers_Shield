# POLICYFLOW
### Fault-Tolerant Insurance Issuance & Reconciliation Engine
**Campus Placement | Software Engineering Practical Assessment • TrippersShield**

---

## 1. System Overview & Architecture

**PolicyFlow** is a fault-tolerant insurance intake, deterministic pricing, external issuance, and reconciliation engine built with **PHP 8.2+ and Laravel 12**. 

When distributing travel insurance through external providers, network unreliability is an unavoidable reality: requests can drop, responses can time out, and webhook callbacks can be duplicated or delivered out of sequence. PolicyFlow guarantees **idempotency**, **preventing duplicate policy issuance**, and **enforcing strict lifecycle state machines** under all simulated failure conditions.

```mermaid
flowchart TD
    A[Agent Client / API Consumer] -->|POST /api/policy-requests| B[Policy Intake Service]
    B -->|Check Unique DB Constraint| C{Exists?}
    C -->|Yes: Duplicate Intake| D[Return 200 OK with Existing Request]
    C -->|No: New Request| E[Deterministic Pricing Calculator]
    E -->|Persist Immutable Snapshot| F[(Policy Request: VALIDATED)]
    
    F -->|POST /api/policy-requests/{id}/submit| G[Issuance Orchestrator]
    G -->|Transition to SUBMITTED| H[Simulated Mock Insurer]
    
    H -->|Scenario: SUCCESS| I[Transition to ISSUED<br>Unique Policy # Assigned]
    H -->|Scenario: TIMEOUT_AFTER_ISSUE| J[Transition to NEEDS_RECONCILIATION<br>Blind Re-issue Blocked]
    H -->|Scenario: DELAYED_CALLBACK| K[Transition to PENDING_CONFIRMATION<br>Awaiting Webhook]
    H -->|Scenario: TEMPORARY_FAILURE 503| L[Rollback to VALIDATED<br>Safe for Retry]
    
    J -->|POST /api/policy-requests/{id}/reconcile| M[Provider Status Lookup]
    M -->|Policy Confirmed| I
    
    K -->|POST /api/mock-insurer/callback| N[Idempotent Callback Handler]
    N -->|Duplicate Event ID?| O[200 OK: DUPLICATE_IGNORED]
    N -->|First Event| I
```

---

## 2. Implemented Reliability & Idempotency Scenarios

PolicyFlow implements **all four reliability scenarios** defined in the challenge specification, demonstrated with automated tests and interactive test lab controls:

### Case 1: Duplicate Intake Safety
- **Problem:** Agents retry or submit near-simultaneous duplicate requests with identical `agent_id` and `client_request_id`.
- **Solution:** 
  1. A composite database unique constraint `UNIQUE(agent_id, client_request_id)` is enforced at the schema level.
  2. The intake handler checks for existing records and returns `HTTP 200 OK` (with `'is_duplicate': true`) returning the existing request.
  3. In concurrent race conditions, database `QueryException` / unique violation is caught atomically, safely rolling back and returning the concurrency winner.

### Case 2: Timeout After Success & Safe Reconciliation
- **Problem:** The external insurer creates and commits the policy internally, but network dropped before client receives the HTTP response. Blindly re-issuing would charge the customer twice and create a duplicate policy.
- **Solution:** 
  1. Connection timeouts trigger an `InsurerTimeoutException`.
  2. The policy status moves to `NEEDS_RECONCILIATION` instead of `FAILED` or `ISSUED`.
  3. Re-submission is strictly blocked while in `NEEDS_RECONCILIATION`.
  4. The system provides an automated reconciliation mechanism (`POST /api/policy-requests/{id}/reconcile` and `php artisan policy:reconcile`) that queries the provider status endpoint (`GET /api/mock-insurer/status/{provider_reference}`), retrieves the already issued policy number, and safely transitions to `ISSUED`.

### Case 3: Duplicate Webhook Callbacks
- **Problem:** Insurer delivers identical webhook events 2–3 times over network retries.
- **Solution:** 
  1. Incoming callbacks require an `event_id`.
  2. All callbacks are recorded in `provider_events` with `UNIQUE(event_id)`.
  3. Subsequent deliveries return `HTTP 200 OK` with `status: DUPLICATE_IGNORED` without repeating state transitions.
  4. Confirmed `ISSUED` policies are terminal: conflicting or late callbacks cannot revert or overwrite an issued policy.

### Case 4: Safe Retry After Temporary Failure
- **Problem:** Insurer returns a transient failure (e.g., HTTP 503 Service Unavailable).
- **Solution:** 
  1. Transient errors are caught as `InsurerTemporaryFailureException`.
  2. The failed attempt is logged in `issuance_attempts` with error details and attempt counter.
  3. State safely rolls back to `VALIDATED` so it can be cleanly retried.
  4. The retry reuses the original, immutable accepted pricing snapshot without altering premium or generating duplicate records.

---

## 3. Technology Stack & Prerequisites

- **PHP:** 8.2 or newer (BCMath, PDO, SQLite / MySQL extensions enabled)
- **Framework:** Laravel 12 / 11
- **Database:** SQLite (default for zero-setup, immediate local execution) or MySQL 8+
- **Composer:** 2.x
- **No Paid Services Required**

---

## 4. Quickstart Setup (Zero Configuration)

### Step 1: Clone Repository & Navigate
```bash
git clone <repository_url>
cd Trippers_shield
```

### Step 2: Install Dependencies (if not already installed)
```bash
composer install
```

### Step 3: Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### Step 4: Run Migrations & Seed Sample Data
```bash
php artisan migrate --seed
```
*(Uses SQLite by default; database is seeded with sample policies across all lifecycle states).*

### Step 5: Start Local Development Server
```bash
php artisan serve
```
Open **[http://localhost:8000](http://localhost:8000)** in your browser to access the interactive web operations dashboard!

---

## 5. Running Automated Tests

Run the full automated test suite containing **26 tests and 192 assertions**:
```bash
php artisan test
```

Or run individual test suites:
```bash
# Pricing engine unit tests
php artisan test --filter=PremiumCalculatorTest

# Intake and validation feature tests
php artisan test --filter=PolicyIntakeTest

# Reliability & idempotency feature tests (all 4 cases)
php artisan test --filter=ReliabilityScenariosTest

# State machine and issuance workflow tests
php artisan test --filter=PolicyIssuanceWorkflowTest

# Bulk CSV intake tests
php artisan test --filter=BulkCsvIntakeTest

# Reconciliation artisan command tests
php artisan test --filter=ReconciliationCommandTest
```

---

## 6. Deterministic Pricing Logic

The pricing formula follows the assignment specification:
$$\text{Premium} = \text{base\_daily\_rate} \times \text{travel\_days} \times \text{age\_multiplier} \times \text{destination\_multiplier} \times \text{coverage\_multiplier}$$

| Factor | Rules |
| :--- | :--- |
| **Base Rate / Day** | `BASIC` = ₹12, `PLUS` = ₹20, `PREMIUM` = ₹30 |
| **Travel Days** | Inclusive: $\text{trip\_end} - \text{trip\_start} + 1$, minimum 1 |
| **Age Multiplier** | Calculated on `trip_start`:<br>• 0–17: **1.10**<br>• 18–60: **1.00**<br>• 61–70: **1.50**<br>• 71+: **2.00** |
| **Destination Multiplier** | `DOMESTIC` = **1.00**, `INTERNATIONAL` = **1.80** |
| **Coverage Multiplier** | ₹100,000 = **1.00**, ₹300,000 = **1.25**, ₹500,000 = **1.50** |
| **Arithmetic Safety** | Evaluated via **BCMath** arbitrary-precision math (`bcmul`) and rounded to 2 decimal places using half-up arithmetic. |

**Example from specification:**
- Plan: `PLUS` (₹20), Destination: `INTERNATIONAL` (1.80), Sum: ₹300,000 (1.25)
- Trip: `2026-11-10` to `2026-11-14` (5 travel days)
- DOB: `1998-04-10` (Age 28 on trip start $\rightarrow$ 1.00 multiplier)
- Calculation: $20 \times 5 \times 1.00 \times 1.80 \times 1.25 = \mathbf{₹225.00}$.

---

## 7. API Reference

### 7.1 Intake Policy Request
```http
POST /api/policy-requests
Content-Type: application/json
```
**Request Body:**
```json
{
  "client_request_id": "AGT-2026-0001",
  "agent_id": "AGENT-17",
  "customer_name": "Sample Customer",
  "date_of_birth": "1998-04-10",
  "destination": "INTERNATIONAL",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-14",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```
**Responses:**
- `201 Created`: Newly accepted policy request in `VALIDATED` state.
- `200 OK`: Duplicate intake safely detected; returns existing request.
- `422 Unprocessable Content`: Validation failure (e.g. `trip_end < trip_start` or invalid plan).

---

### 7.2 Submit Policy for Issuance
```http
POST /api/policy-requests/{id}/submit
Content-Type: application/json
X-Mock-Scenario: SUCCESS
```
*(Optional `scenario` parameter in header or body: `SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`)*

**Responses:**
- `200 OK`: Status `ISSUED` (contains unique `policy_number`).
- `202 Accepted`: Status `PENDING_CONFIRMATION` (awaiting async callback).
- `200 OK`: Status `NEEDS_RECONCILIATION` (timeout occurred; blind re-issue blocked).
- `503 Service Unavailable`: Temporary failure; state rolled back to `VALIDATED` for safe retry.

---

### 7.3 Reconcile Ambiguous / Timeout Policy
```http
POST /api/policy-requests/{id}/reconcile
```
Queries external provider lookup, confirms whether policy was issued, and transitions state safely to `ISSUED`.

---

### 7.4 Webhook Callback Intake
```http
POST /api/mock-insurer/callback
Content-Type: application/json
```
```json
{
  "event_id": "EVT-WEBHOOK-1001",
  "provider_reference": "PRV-DEMO-0001",
  "event_type": "POLICY_ISSUED",
  "policy_number": "POL-20261110-A9B8C7"
}
```
Idempotent webhook endpoint. Duplicate deliveries of identical `event_id` return `200 OK` with `status: DUPLICATE_IGNORED`.

---

### 7.5 List & Retrieve Policies
- `GET /api/policy-requests`: Supports `?status=ISSUED`, `?agent_id=...`, and `?per_page=...` pagination.
- `GET /api/policy-requests/{id}`: Returns complete request details, premium breakdown, external attempts, and full state history audit trail.

---

### 7.6 Bulk CSV Intake (Stretch Feature)
```http
POST /api/policy-requests/bulk-csv
Content-Type: application/json
```
Accepts multipart `file` or JSON `{"csv_content": "..."}` and provides row-by-row error reporting and duplicate identification.

---

## 8. State Machine & Transition Rules

Strict state machine implemented in `app/Services/Policy/PolicyStateMachine.php`:

| Current State | Allowed Next States |
| :--- | :--- |
| `RECEIVED` | `VALIDATED`, `FAILED` |
| `VALIDATED` | `SUBMITTED`, `FAILED` |
| `SUBMITTED` | `ISSUED`, `PENDING_CONFIRMATION`, `NEEDS_RECONCILIATION`, `FAILED`, `VALIDATED` (rollback) |
| `PENDING_CONFIRMATION` | `ISSUED`, `FAILED`, `NEEDS_RECONCILIATION` |
| `NEEDS_RECONCILIATION` | `ISSUED`, `FAILED`, `VALIDATED` |
| `FAILED` | `VALIDATED` (authorized manual retry) |
| `ISSUED` | **TERMINAL** (No further transitions permitted) |

Any invalid regression (such as attempting `ISSUED` $\rightarrow$ `SUBMITTED`) throws an `InvalidStateTransitionException` (HTTP 409) and is rejected.

---

## 9. Artisan Automation Commands

Run the batch reconciliation command to resolve any stuck or ambiguous requests:
```bash
php artisan policy:reconcile
# Or reconcile a specific ID:
php artisan policy:reconcile --id=3
```

---

## 10. Database Schema Overview

1. `policy_requests`: Core entity storing customer data, dates, calculated premium snapshot, current status, `policy_number` (unique), and `provider_reference`. Unique compound index on `(agent_id, client_request_id)`.
2. `issuance_attempts`: Audit trail of every external API invocation (attempt number, request payload, response payload, error message, status).
3. `policy_state_histories`: Immutable transition history with `from_status`, `to_status`, transition timestamp, reason, and contextual metadata.
4. `provider_events`: Webhook deduplication log enforcing `UNIQUE(event_id)`.
5. `mock_insurer_policies`: Local provider datastore simulating external insurer internal state.

---

## 11. Known Assumptions & Limitations

- **Currency:** Indian Rupee (₹) rounded to 2 decimal places.
- **Provider Mock:** Implemented as an internal service layer and HTTP controllers within the Laravel application; does not require active internet connection or paid services.
- **Database:** Defaults to SQLite for friction-free evaluator testing; fully compatible with MySQL 8+ and PostgreSQL by switching connection in `.env`.

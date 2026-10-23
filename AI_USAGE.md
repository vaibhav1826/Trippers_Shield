# AI Usage Disclosure (AI_USAGE.md)

**Project:** POLICYFLOW — Fault-Tolerant Insurance Issuance & Reconciliation Engine  
**Assessment:** Campus Engineering Placement Assessment  
**Policy Compliance:** Disclosed in accordance with Section 10 of the assignment specification.

---

### 1. Tools Used
- **Claude / Antigravity Assistant**: Used for architectural pair programming, structuring Laravel migrations, scaffolding boilerplate service classes, and generating automated PHPUnit test scenarios.
- **PHPStan & Laravel Artisan CLI**: Used for static analysis, syntax validation, and test harness execution.

---

### 2. Concrete Ways AI Assisted
1. **Mathematical Precision & Pricing Engine Boilerplate:**
   - AI helped write the decimal-safe BCMath computation pipeline (`bcmul`) to guarantee zero floating-point drift across the multi-factor pricing formula (`base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier`).
2. **State Machine Matrix Definition:**
   - AI helped scaffold the explicit transition lookup table (`PolicyStateMachine::ALLOWED_TRANSITIONS`) ensuring strict terminal protection for `ISSUED` policies and mapping safe rollbacks for temporary retriable states.
3. **Automated Test Scenarios Scaffolding:**
   - AI generated comprehensive test coverage for edge cases including leap-year date logic, single-day trip boundaries, duplicate intake concurrency, and webhook deduplication.

---

### 3. Incomplete / Incorrect Suggestion Encountered & Correction
- **Encountered Defect:**
  When generating assertions in `tests/Feature/ReliabilityScenariosTest.php` for checking the sequential history of external issuance attempts (`issuanceAttempts`), AI initially suggested:
  ```php
  $this->assertSame('SUCCESS', $retriedPolicy->issuanceAttempts()->latest('id')->first()->status);
  ```
  **Why it failed:**
  In Eloquent, the `issuanceAttempts()` relationship on `PolicyRequest` already had an explicit default ordering clause: `orderBy('attempt_number', 'asc')`. Calling `latest('id')` simply appended `ORDER BY attempt_number ASC, id DESC` to the query, causing SQL to prioritize the ascending attempt number and returning attempt #1 (`FAILED`) rather than attempt #2 (`SUCCESS`).
  
  **Resolution & Fix:**
  I identified the query ordering clash and refactored the verification to fetch the collection explicitly:
  ```php
  $attempts = $retriedPolicy->issuanceAttempts()->get();
  $this->assertCount(2, $attempts);
  $this->assertSame('FAILED', $attempts[0]->status);
  $this->assertSame('SUCCESS', $attempts[1]->status);
  ```
  This removed ambiguous SQL order clauses and ensured unambiguous, deterministic test verification.

---

### 4. Verification and Testing Methodology
- **Automated Test Suite:** Ran full test suites using `php artisan test` (26 passed tests with 192 assertions) verifying pricing calculations, schema constraints, idempotency, timeout reconciliation, and callback deduplication.
- **Database Level Verification:** Confirmed that compound unique constraints (`UNIQUE(agent_id, client_request_id)`) and policy uniqueness (`UNIQUE(policy_number)`) are enforced at the database migration level, not solely in application memory.
- **Interactive Visual Verification:** Tested the endpoints via the web dashboard at `http://localhost:8000/` and verified real-time pricing calculations, audit trails, and simulated provider failures.
- **Codebase Review:** Manually inspected every migration, model cast, controller response code, and exception handler for clean separation of concerns and adherence to Laravel 11/12 conventions.

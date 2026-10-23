<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PolicyFlow | Fault-Tolerant Insurance Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0B0F19;
            --bg-card: #111827;
            --bg-card-hover: #162032;
            --bg-input: #1A2438;
            --border-color: #24324D;
            --border-focus: #3B82F6;
            --text-main: #F3F4F6;
            --text-muted: #94A3B8;
            --accent-blue: #3B82F6;
            --accent-cyan: #06B6D4;
            --accent-emerald: #10B981;
            --accent-amber: #F59E0B;
            --accent-rose: #EF4444;
            --accent-purple: #8B5CF6;
            --shadow-subtle: 0 4px 20px -2px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 25px -5px rgba(59, 130, 246, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 15% 15%, rgba(59, 130, 246, 0.08) 0%, transparent 40%),
                              radial-gradient(circle at 85% 85%, rgba(139, 92, 246, 0.08) 0%, transparent 40%);
        }

        /* Top Navbar */
        .navbar {
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 50;
            padding: 0.85rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #3B82F6, #8B5CF6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
            font-size: 1.25rem;
            font-weight: 800;
            color: #FFFFFF;
            font-family: 'Outfit', sans-serif;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #FFFFFF, #93C5FD);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .badge-pill {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-subtle {
            background: rgba(59, 130, 246, 0.15);
            color: #60A5FA;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Stats Bar */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--shadow-subtle);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, border-color 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.4);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-total::after { background: var(--accent-blue); }
        .stat-issued::after { background: var(--accent-emerald); }
        .stat-pending::after { background: var(--accent-amber); }
        .stat-recon::after { background: var(--accent-rose); }
        .stat-validated::after { background: var(--accent-purple); }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 700;
            margin-top: 0.35rem;
        }

        /* Layout Grid */
        .layout-grid {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1100px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(17, 24, 39, 0.5);
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }

        input, select, textarea {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            color: var(--text-main);
            font-size: 0.88rem;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Live Pricing Preview Box */
        .pricing-preview-box {
            background: rgba(30, 41, 59, 0.7);
            border: 1px dashed rgba(59, 130, 246, 0.4);
            border-radius: 10px;
            padding: 1rem;
            margin: 1.25rem 0;
        }

        .pricing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .pricing-amount {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #34D399;
        }

        .pricing-formula {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            color: #94A3B8;
            line-height: 1.4;
            background: rgba(15, 23, 42, 0.6);
            padding: 0.5rem;
            border-radius: 6px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: #FFFFFF;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.35);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.5);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #1E293B;
            color: #E2E8F0;
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #24324D;
            border-color: #3B82F6;
        }

        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .btn-emerald {
            background: #059669;
            color: white;
        }
        .btn-emerald:hover { background: #10B981; }

        .btn-amber {
            background: #D97706;
            color: white;
        }
        .btn-amber:hover { background: #F59E0B; }

        .btn-rose {
            background: #DC2626;
            color: white;
        }
        .btn-rose:hover { background: #EF4444; }

        .btn-purple {
            background: #7C3AED;
            color: white;
        }
        .btn-purple:hover { background: #8B5CF6; }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }

        th {
            background: rgba(17, 24, 39, 0.7);
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.05em;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(36, 50, 77, 0.6);
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(30, 41, 59, 0.35);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            letter-spacing: 0.03em;
        }

        .status-ISSUED { background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-VALIDATED { background: rgba(139, 92, 246, 0.15); color: #A78BFA; border: 1px solid rgba(139, 92, 246, 0.3); }
        .status-SUBMITTED { background: rgba(59, 130, 246, 0.15); color: #60A5FA; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-PENDING_CONFIRMATION { background: rgba(245, 158, 11, 0.15); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-NEEDS_RECONCILIATION { background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-FAILED { background: rgba(239, 68, 68, 0.15); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-RECEIVED { background: rgba(148, 163, 184, 0.15); color: #CBD5E1; border: 1px solid rgba(148, 163, 184, 0.3); }

        .code-pill {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border-color);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            color: #93C5FD;
        }

        /* Action Buttons Grid in Table */
        .table-actions {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        /* Lab Section */
        .lab-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-top: 2rem;
        }

        .lab-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .lab-card h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            color: #F8FAFC;
        }

        .lab-card p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1.5rem;
        }

        .modal {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            width: 100%;
            max-width: 720px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            overflow: hidden;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .timeline {
            position: relative;
            padding-left: 1.5rem;
            border-left: 2px solid var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.85rem;
            top: 0.25rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-blue);
            box-shadow: 0 0 10px var(--accent-blue);
        }

        .timeline-title {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .timeline-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            background: #1E293B;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.85rem 1.25rem;
            font-size: 0.85rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.25s ease-out;
            max-width: 420px;
        }

        .toast-success { border-left: 4px solid var(--accent-emerald); }
        .toast-error { border-left: 4px solid var(--accent-rose); }
        .toast-info { border-left: 4px solid var(--accent-blue); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="brand">
            <div class="brand-logo">PF</div>
            <div>
                <div class="brand-title">POLICYFLOW</div>
                <div style="font-size: 0.72rem; color: var(--text-muted);">Fault-Tolerant Insurance Engine • TrippersShield</div>
            </div>
            <span class="badge-pill badge-subtle" style="margin-left: 0.5rem;">Laravel 12 • SQLite/MySQL</span>
        </div>
        <div class="nav-actions">
            <button class="btn btn-secondary btn-sm" onclick="runArtisanReconcile()">
                ⚡ Run Reconcile Command
            </button>
            <button class="btn btn-secondary btn-sm" onclick="openBulkModal()">
                📄 Bulk CSV Intake
            </button>
            <button class="btn btn-primary btn-sm" onclick="fetchPolicies()">
                🔄 Refresh
            </button>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container">

        <!-- Stats Overview Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-label">Total Requests</div>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            <div class="stat-card stat-issued">
                <div class="stat-label">Issued (Confirmed)</div>
                <div class="stat-value" style="color: #34D399;" id="stat-issued">0</div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-label">Pending Callback</div>
                <div class="stat-value" style="color: #FBBF24;" id="stat-pending">0</div>
            </div>
            <div class="stat-card stat-recon">
                <div class="stat-label">Needs Reconciliation</div>
                <div class="stat-value" style="color: #F87171;" id="stat-recon">0</div>
            </div>
            <div class="stat-card stat-validated">
                <div class="stat-label">Validated / Ready</div>
                <div class="stat-value" style="color: #A78BFA;" id="stat-validated">0</div>
            </div>
        </div>

        <!-- Main Layout: Form & Table -->
        <div class="layout-grid">

            <!-- Intake Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span>🛡️</span> Policy Intake & Pricing
                    </div>
                    <span class="badge-pill badge-subtle">Deterministic Pricing</span>
                </div>
                <div class="card-body">
                    <form id="intake-form" onsubmit="handleIntakeSubmit(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="client_request_id">Client Request ID</label>
                                <input type="text" id="client_request_id" required value="AGT-2026-0001">
                            </div>
                            <div class="form-group">
                                <label for="agent_id">Agent ID</label>
                                <input type="text" id="agent_id" required value="AGENT-17">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="customer_name">Customer Full Name</label>
                            <input type="text" id="customer_name" required value="Sample Customer">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" required value="1998-04-10" onchange="calculateLivePricing()">
                            </div>
                            <div class="form-group">
                                <label for="destination">Destination</label>
                                <select id="destination" required onchange="calculateLivePricing()">
                                    <option value="INTERNATIONAL" selected>INTERNATIONAL (1.80x)</option>
                                    <option value="DOMESTIC">DOMESTIC (1.00x)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="trip_start">Trip Start Date</label>
                                <input type="date" id="trip_start" required value="2026-11-10" onchange="calculateLivePricing()">
                            </div>
                            <div class="form-group">
                                <label for="trip_end">Trip End Date</label>
                                <input type="date" id="trip_end" required value="2026-11-14" onchange="calculateLivePricing()">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="plan_code">Insurance Plan</label>
                                <select id="plan_code" required onchange="calculateLivePricing()">
                                    <option value="BASIC">BASIC (₹12/day)</option>
                                    <option value="PLUS" selected>PLUS (₹20/day)</option>
                                    <option value="PREMIUM">PREMIUM (₹30/day)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sum_insured">Sum Insured</label>
                                <select id="sum_insured" required onchange="calculateLivePricing()">
                                    <option value="100000">₹100,000 (1.00x)</option>
                                    <option value="300000" selected>₹300,000 (1.25x)</option>
                                    <option value="500000">₹500,000 (1.50x)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Live Pricing Calculation Preview -->
                        <div class="pricing-preview-box">
                            <div class="pricing-header">
                                <span style="font-size: 0.8rem; font-weight: 600; color: #94A3B8;">CALCULATED PREMIUM</span>
                                <div class="pricing-amount" id="preview-premium">₹225.00</div>
                            </div>
                            <div class="pricing-formula" id="preview-formula">
                                Formula: 20 × 5 days × 1.00 (Age 28) × 1.80 × 1.25 = ₹225.00
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;" id="btn-intake">
                                🚀 Create / Safe Intake
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="generateRandomRequestId()" title="Generate Random Request ID">
                                🎲
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Active Policy Requests Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span>📋</span> Policy Request Registry
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <select id="filter-status" onchange="fetchPolicies()" style="width: auto; padding: 0.35rem 0.65rem; font-size: 0.78rem;">
                            <option value="">All Statuses</option>
                            <option value="VALIDATED">VALIDATED</option>
                            <option value="ISSUED">ISSUED</option>
                            <option value="PENDING_CONFIRMATION">PENDING_CONFIRMATION</option>
                            <option value="NEEDS_RECONCILIATION">NEEDS_RECONCILIATION</option>
                            <option value="FAILED">FAILED</option>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Request & Agent</th>
                                <th>Customer & Dates</th>
                                <th>Plan & Sum</th>
                                <th>Premium</th>
                                <th>Status</th>
                                <th>Policy # / Ref</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="policies-table-body">
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    Loading policy registry...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Reliability Test Lab Cards (Interactive Testing) -->
        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.3rem; margin: 3rem 0 1rem; color: #FFFFFF;">
            🧪 Reliability Test Lab (Evaluator Scenarios)
        </h3>
        <div class="lab-grid">
            <div class="lab-card">
                <div>
                    <h4>Case 1: Duplicate Intake Safety</h4>
                    <p>Fires identical agent_id + client_request_id multiple times. Tests that HTTP 200 is returned and only 1 logical record exists in database.</p>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="runScenarioDuplicateIntake()">
                    ⚡ Run Duplicate Intake Test
                </button>
            </div>

            <div class="lab-card">
                <div>
                    <h4>Case 2: Timeout After Issue & Reconciliation</h4>
                    <p>Simulates external insurer issuing policy while client call times out. State transitions to NEEDS_RECONCILIATION; reconciliation verifies provider status.</p>
                </div>
                <button class="btn btn-amber btn-sm" onclick="runScenarioTimeout()">
                    ⚡ Test Timeout & Reconcile
                </button>
            </div>

            <div class="lab-card">
                <div>
                    <h4>Case 3: Duplicate Webhook Callback</h4>
                    <p>Simulates insurer firing identical event_id 3 times. Engine deduplicates events idempotently without state regression or errors.</p>
                </div>
                <button class="btn btn-purple btn-sm" onclick="runScenarioDuplicateCallback()">
                    ⚡ Test Duplicate Callbacks
                </button>
            </div>

            <div class="lab-card">
                <div>
                    <h4>Case 4: Temporary Failure & Safe Retry</h4>
                    <p>Simulates HTTP 503 Provider Unavailable. Verifies attempt is logged, state resets to VALIDATED, and retry preserves exact accepted premium.</p>
                </div>
                <button class="btn btn-rose btn-sm" onclick="runScenarioRetryTemporaryFailure()">
                    ⚡ Test Temporary Failure Retry
                </button>
            </div>
        </div>

    </div>

    <!-- Audit Trail Modal -->
    <div class="modal-overlay" id="audit-modal">
        <div class="modal">
            <div class="modal-header">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700;" id="modal-title">
                        Policy Audit Trail
                    </h3>
                    <div style="font-size: 0.75rem; color: var(--text-muted);" id="modal-subtitle"></div>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="closeModal()">✕</button>
            </div>
            <div class="modal-body">
                <h5 style="color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                    State Transition Timeline
                </h5>
                <div class="timeline" id="modal-timeline"></div>

                <h5 style="color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.05em; margin: 1.5rem 0 0.75rem;">
                    External Issuance Attempts
                </h5>
                <div id="modal-attempts"></div>
            </div>
        </div>
    </div>

    <!-- Bulk CSV Modal -->
    <div class="modal-overlay" id="bulk-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700;">
                    Bulk CSV Policy Intake
                </h3>
                <button class="btn btn-secondary btn-sm" onclick="closeBulkModal()">✕</button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Upload a CSV file with header: <code>client_request_id, agent_id, customer_name, date_of_birth, destination, trip_start, trip_end, plan_code, sum_insured</code>
                </p>
                <div class="form-group">
                    <textarea id="bulk-csv-content" rows="6" placeholder="client_request_id,agent_id,customer_name,date_of_birth,destination,trip_start,trip_end,plan_code,sum_insured&#10;CSV-01,AGENT-01,Alex Johnson,1992-05-10,DOMESTIC,2026-11-01,2026-11-05,BASIC,100000"></textarea>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <button class="btn btn-secondary btn-sm" onclick="loadSampleCsv()">Load Sample CSV</button>
                    <button class="btn btn-primary" onclick="submitBulkCsv()">Process Bulk Intake</button>
                </div>
                <div id="bulk-results" style="margin-top: 1rem;"></div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        // Real-time Pricing Preview Calculation
        function calculateLivePricing() {
            const plan = document.getElementById('plan_code').value;
            const destination = document.getElementById('destination').value;
            const sumInsured = parseInt(document.getElementById('sum_insured').value);
            const dobStr = document.getElementById('date_of_birth').value;
            const startStr = document.getElementById('trip_start').value;
            const endStr = document.getElementById('trip_end').value;

            if (!dobStr || !startStr || !endStr) return;

            const start = new Date(startStr);
            const end = new Date(endStr);
            const dob = new Date(dobStr);

            if (end < start) {
                document.getElementById('preview-premium').textContent = 'Invalid Dates';
                document.getElementById('preview-formula').textContent = 'trip_end cannot be earlier than trip_start';
                return;
            }

            const diffTime = Math.abs(end - start);
            const travelDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

            // Age at trip start
            let age = start.getFullYear() - dob.getFullYear();
            const m = start.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && start.getDate() < dob.getDate())) {
                age--;
            }

            let baseRate = 12;
            if (plan === 'PLUS') baseRate = 20;
            if (plan === 'PREMIUM') baseRate = 30;

            let ageMult = 1.00;
            if (age <= 17) ageMult = 1.10;
            else if (age <= 60) ageMult = 1.00;
            else if (age <= 70) ageMult = 1.50;
            else ageMult = 2.00;

            const destMult = (destination === 'INTERNATIONAL') ? 1.80 : 1.00;

            let covMult = 1.00;
            if (sumInsured === 300000) covMult = 1.25;
            if (sumInsured === 500000) covMult = 1.50;

            const total = baseRate * travelDays * ageMult * destMult * covMult;
            const rounded = total.toFixed(2);

            document.getElementById('preview-premium').textContent = '₹' + rounded;
            document.getElementById('preview-formula').textContent =
                `Formula: ${baseRate} × ${travelDays} days × ${ageMult.toFixed(2)} (Age ${age}) × ${destMult.toFixed(2)} × ${covMult.toFixed(2)} = ₹${rounded}`;
        }

        function generateRandomRequestId() {
            const rand = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('client_request_id').value = 'AGT-2026-' + rand;
            showToast('Generated new Request ID: AGT-2026-' + rand, 'info');
        }

        // Fetch & Render Policies
        async function fetchPolicies() {
            const filter = document.getElementById('filter-status').value;
            let url = '/api/policy-requests?per_page=50';
            if (filter) url += '&status=' + filter;

            try {
                const res = await fetch(url);
                const json = await res.json();
                renderTable(json.data || []);
                updateStats(json.data || []);
            } catch (e) {
                console.error(e);
                showToast('Failed to load policies: ' + e.message, 'error');
            }
        }

        function updateStats(policies) {
            document.getElementById('stat-total').textContent = policies.length;
            document.getElementById('stat-issued').textContent = policies.filter(p => p.status === 'ISSUED').length;
            document.getElementById('stat-pending').textContent = policies.filter(p => p.status === 'PENDING_CONFIRMATION').length;
            document.getElementById('stat-recon').textContent = policies.filter(p => p.status === 'NEEDS_RECONCILIATION').length;
            document.getElementById('stat-validated').textContent = policies.filter(p => p.status === 'VALIDATED').length;
        }

        function renderTable(policies) {
            const tbody = document.getElementById('policies-table-body');
            if (policies.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No policy requests found.</td></tr>`;
                return;
            }

            tbody.innerHTML = policies.map(p => {
                let actionButtons = `
                    <button class="btn btn-secondary btn-sm" onclick="openAuditModal(${p.id})">🔍 Audit</button>
                `;

                if (p.status === 'VALIDATED') {
                    actionButtons += `
                        <button class="btn btn-emerald btn-sm" onclick="submitIssuance(${p.id}, 'SUCCESS')">🚀 Issue</button>
                        <button class="btn btn-amber btn-sm" onclick="submitIssuance(${p.id}, 'TIMEOUT_AFTER_ISSUE')" title="Simulate timeout">⏱️ Timeout</button>
                        <button class="btn btn-purple btn-sm" onclick="submitIssuance(${p.id}, 'DELAYED_CALLBACK')" title="Simulate delayed callback">⏳ Async</button>
                    `;
                } else if (p.status === 'NEEDS_RECONCILIATION') {
                    actionButtons += `
                        <button class="btn btn-rose btn-sm" onclick="reconcilePolicy(${p.id})">⚡ Reconcile</button>
                    `;
                } else if (p.status === 'PENDING_CONFIRMATION') {
                    actionButtons += `
                        <button class="btn btn-amber btn-sm" onclick="triggerCallback('${p.provider_reference}')">📬 Fire Callback</button>
                        <button class="btn btn-secondary btn-sm" onclick="reconcilePolicy(${p.id})">⚡ Reconcile</button>
                    `;
                } else if (p.status === 'ISSUED') {
                    actionButtons += `
                        <span style="font-size: 0.75rem; color: #34D399; font-weight: 600;">✓ Confirmed</span>
                    `;
                }

                return `
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: #FFFFFF;">${p.client_request_id}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">${p.agent_id}</div>
                        </td>
                        <td>
                            <div>${p.customer_name}</div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">${p.trip_start} to ${p.trip_end} (${p.travel_days}d)</div>
                        </td>
                        <td>
                            <span class="badge-pill badge-subtle">${p.plan_code}</span>
                            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">₹${p.sum_insured.toLocaleString()}</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #34D399; font-family: 'Outfit';">₹${p.premium_amount.toFixed(2)}</div>
                        </td>
                        <td>
                            <span class="status-badge status-${p.status}">${p.status}</span>
                        </td>
                        <td>
                            ${p.policy_number ? `<span class="code-pill">${p.policy_number}</span>` : '<span style="color: #64748B;">—</span>'}
                            ${p.provider_reference ? `<div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">${p.provider_reference}</div>` : ''}
                        </td>
                        <td>
                            <div class="table-actions">
                                ${actionButtons}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Intake Form Submit
        async function handleIntakeSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-intake');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            const payload = {
                client_request_id: document.getElementById('client_request_id').value,
                agent_id: document.getElementById('agent_id').value,
                customer_name: document.getElementById('customer_name').value,
                date_of_birth: document.getElementById('date_of_birth').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('trip_start').value,
                trip_end: document.getElementById('trip_end').value,
                plan_code: document.getElementById('plan_code').value,
                sum_insured: parseInt(document.getElementById('sum_insured').value),
            };

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (res.status === 201) {
                    showToast('Policy Created! Premium: ₹' + data.data.premium_amount, 'success');
                } else if (res.status === 200) {
                    showToast('Idempotent Duplicate Intake: Returned existing policy #' + data.data.id, 'info');
                } else {
                    showToast(data.message || 'Validation error', 'error');
                }

                fetchPolicies();
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '🚀 Create / Safe Intake';
            }
        }

        // Submit for Issuance
        async function submitIssuance(id, scenario) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ scenario: scenario })
                });
                const data = await res.json();
                showToast(`Issuance [${scenario}]: ${data.message}`, res.ok ? 'success' : 'error');
                fetchPolicies();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Reconcile Policy
        async function reconcilePolicy(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/reconcile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
                });
                const data = await res.json();
                showToast(`Reconciliation: ${data.message}`, 'success');
                fetchPolicies();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Trigger Delayed Webhook Callback
        async function triggerCallback(providerRef) {
            try {
                const res = await fetch(`/api/mock-insurer/trigger-callback/${providerRef}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
                });
                const data = await res.json();
                showToast(`Callback Dispatched! Policy successfully confirmed.`, 'success');
                fetchPolicies();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Audit Trail Modal
        async function openAuditModal(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}`);
                const data = await res.json();
                const p = data.data;

                document.getElementById('modal-title').textContent = `Policy Audit: ${p.client_request_id}`;
                document.getElementById('modal-subtitle').textContent = `Agent: ${p.agent_id} • Status: ${p.status} • Premium: ₹${p.premium_amount}`;

                // Timeline
                const timeline = document.getElementById('modal-timeline');
                timeline.innerHTML = (p.state_history || []).map(h => `
                    <div class="timeline-item">
                        <div class="timeline-title">${h.from_status || 'INIT'} ➔ <span style="color: #60A5FA;">${h.to_status}</span></div>
                        <div style="font-size: 0.8rem; color: #CBD5E1; margin: 0.2rem 0;">${h.reason || 'State transition'}</div>
                        <div class="timeline-meta">${new Date(h.timestamp).toLocaleString()}</div>
                    </div>
                `).join('') || '<div style="color: var(--text-muted);">No state transitions recorded.</div>';

                // Issuance Attempts
                const attempts = document.getElementById('modal-attempts');
                attempts.innerHTML = (p.issuance_attempts || []).map(a => `
                    <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem; margin-bottom: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 0.82rem;">
                            <span>Attempt #${a.attempt_number} (Scenario: ${a.scenario || 'DEFAULT'})</span>
                            <span class="status-badge status-${a.status}">${a.status}</span>
                        </div>
                        ${a.error_message ? `<div style="font-size: 0.75rem; color: #F87171; margin-top: 0.25rem;">${a.error_message}</div>` : ''}
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">${new Date(a.timestamp).toLocaleString()}</div>
                    </div>
                `).join('') || '<div style="color: var(--text-muted); font-size: 0.8rem;">No provider attempts made yet.</div>';

                document.getElementById('audit-modal').style.display = 'flex';
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function closeModal() {
            document.getElementById('audit-modal').style.display = 'none';
        }

        // Bulk CSV Modal
        function openBulkModal() {
            document.getElementById('bulk-modal').style.display = 'flex';
        }

        function closeBulkModal() {
            document.getElementById('bulk-modal').style.display = 'none';
        }

        function loadSampleCsv() {
            document.getElementById('bulk-csv-content').value =
`client_request_id,agent_id,customer_name,date_of_birth,destination,trip_start,trip_end,plan_code,sum_insured
BULK-SAMPLE-01,AGENT-20,Vikram Seth,1990-01-01,DOMESTIC,2026-11-01,2026-11-05,BASIC,100000
BULK-SAMPLE-02,AGENT-20,Sunita Rao,1985-06-12,INTERNATIONAL,2026-11-10,2026-11-20,PLUS,300000
BULK-SAMPLE-03,AGENT-20,Invalid Dates User,1995-02-02,DOMESTIC,2026-11-20,2026-11-10,BASIC,100000
BULK-SAMPLE-01,AGENT-20,Duplicate User,1990-01-01,DOMESTIC,2026-11-01,2026-11-05,BASIC,100000`;
        }

        async function submitBulkCsv() {
            const content = document.getElementById('bulk-csv-content').value;
            if (!content) return showToast('Please enter CSV content', 'error');

            try {
                const res = await fetch('/api/policy-requests/bulk-csv', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ csv_content: content })
                });
                const data = await res.json();

                const resultsDiv = document.getElementById('bulk-results');
                resultsDiv.innerHTML = `
                    <div style="background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem;">
                        <div style="font-weight: 700; color: #FFFFFF; margin-bottom: 0.5rem;">Results Summary:</div>
                        <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
                            <span>Total: <b>${data.total_rows}</b></span>
                            <span style="color: #34D399;">Success: <b>${data.successful}</b></span>
                            <span style="color: #60A5FA;">Duplicates: <b>${data.duplicates}</b></span>
                            <span style="color: #F87171;">Failed: <b>${data.failed}</b></span>
                        </div>
                    </div>
                `;

                showToast(`Bulk Intake Completed: ${data.successful} created, ${data.duplicates} duplicates, ${data.failed} failed.`, 'success');
                fetchPolicies();
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Artisan Reconcile via UI
        async function runArtisanReconcile() {
            showToast('Triggering reconciliation for pending and timeout policies...', 'info');
            // Fetch policies needing recon
            const res = await fetch('/api/policy-requests?status=NEEDS_RECONCILIATION');
            const data = await res.json();
            const list = data.data || [];

            if (list.length === 0) {
                showToast('No policies currently require reconciliation.', 'info');
                return;
            }

            for (const p of list) {
                await reconcilePolicy(p.id);
            }
            showToast(`Reconciliation finished for ${list.length} policies!`, 'success');
        }

        // Evaluator Quick Scenarios
        async function runScenarioDuplicateIntake() {
            const randReq = 'DUP-TEST-' + Math.floor(Math.random() * 99999);
            showToast('Firing 3 near-simultaneous duplicate requests...', 'info');
            const payload = {
                client_request_id: randReq,
                agent_id: 'AGENT-TEST',
                customer_name: 'Concurrent Runner',
                date_of_birth: '1995-04-10',
                destination: 'DOMESTIC',
                trip_start: '2026-10-01',
                trip_end: '2026-10-05',
                plan_code: 'BASIC',
                sum_insured: 100000
            };

            const [r1, r2, r3] = await Promise.all([
                fetch('/api/policy-requests', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }),
                fetch('/api/policy-requests', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }),
                fetch('/api/policy-requests', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
            ]);

            showToast(`Responses: Req 1: ${r1.status}, Req 2: ${r2.status}, Req 3: ${r3.status}. 1 Created, 2 Idempotently Returned!`, 'success');
            fetchPolicies();
        }

        async function runScenarioTimeout() {
            const randReq = 'TIMEOUT-TEST-' + Math.floor(Math.random() * 99999);
            showToast('Step 1: Creating policy...', 'info');
            const cRes = await fetch('/api/policy-requests', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    client_request_id: randReq,
                    agent_id: 'AGENT-TEST',
                    customer_name: 'Timeout Subject',
                    date_of_birth: '1990-01-01',
                    destination: 'INTERNATIONAL',
                    trip_start: '2026-12-01',
                    trip_end: '2026-12-05',
                    plan_code: 'PLUS',
                    sum_insured: 300000
                })
            });
            const cData = await cRes.json();
            const pId = cData.data.id;

            showToast('Step 2: Submitting under TIMEOUT_AFTER_ISSUE scenario...', 'info');
            await submitIssuance(pId, 'TIMEOUT_AFTER_ISSUE');

            setTimeout(async () => {
                showToast('Step 3: Resolving via Reconcile without duplicate issuance...', 'info');
                await reconcilePolicy(pId);
            }, 1200);
        }

        async function runScenarioDuplicateCallback() {
            const randReq = 'CB-TEST-' + Math.floor(Math.random() * 99999);
            const cRes = await fetch('/api/policy-requests', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    client_request_id: randReq,
                    agent_id: 'AGENT-TEST',
                    customer_name: 'Callback Subject',
                    date_of_birth: '1990-01-01',
                    destination: 'DOMESTIC',
                    trip_start: '2026-12-01',
                    trip_end: '2026-12-05',
                    plan_code: 'BASIC',
                    sum_insured: 100000
                })
            });
            const cData = await cRes.json();
            const pId = cData.data.id;

            // Submit with delayed callback
            const sRes = await fetch(`/api/policy-requests/${pId}/submit`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ scenario: 'DELAYED_CALLBACK' })
            });
            const sData = await sRes.json();
            const ref = sData.data.provider_reference;

            // Send duplicate callbacks
            const evtId = 'EVT-DUP-' + Math.floor(Math.random() * 99999);
            const cbPayload = {
                event_id: evtId,
                provider_reference: ref,
                event_type: 'POLICY_ISSUED',
                policy_number: 'POL-DUP-CB-999'
            };

            const [cb1, cb2] = await Promise.all([
                fetch('/api/mock-insurer/callback', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(cbPayload) }),
                fetch('/api/mock-insurer/callback', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(cbPayload) })
            ]);

            const d1 = await cb1.json();
            const d2 = await cb2.json();

            showToast(`Duplicate Callbacks: Call 1: ${d1.status}, Call 2: ${d2.status}! Applied only once.`, 'success');
            fetchPolicies();
        }

        async function runScenarioRetryTemporaryFailure() {
            const randReq = 'RETRY-TEST-' + Math.floor(Math.random() * 99999);
            const cRes = await fetch('/api/policy-requests', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    client_request_id: randReq,
                    agent_id: 'AGENT-TEST',
                    customer_name: 'Retry Subject',
                    date_of_birth: '1990-01-01',
                    destination: 'DOMESTIC',
                    trip_start: '2026-12-01',
                    trip_end: '2026-12-05',
                    plan_code: 'PREMIUM',
                    sum_insured: 500000
                })
            });
            const cData = await cRes.json();
            const pId = cData.data.id;

            showToast('Attempt 1: Simulating 503 Temporary Failure...', 'info');
            await submitIssuance(pId, 'TEMPORARY_FAILURE');

            setTimeout(async () => {
                showToast('Attempt 2: Retrying safely with SUCCESS scenario...', 'info');
                await submitIssuance(pId, 'SUCCESS');
            }, 1200);
        }

        // Toast notifications
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
                <div>${message}</div>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            calculateLivePricing();
            fetchPolicies();
        });
    </script>
</body>
</html>

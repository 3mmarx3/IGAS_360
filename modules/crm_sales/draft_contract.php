<?php
session_start();
require_once '../../config/db.php';

$active_page = 'client_contracts';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Draft Contract'];

$clientsStmt = $pdo->prepare("
    SELECT id, reference_id, company_name, entity_type, segment, payment_terms, status
    FROM partners
    WHERE partner_type = 'client'
    ORDER BY company_name ASC
");
$clientsStmt->execute();
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$nextStmt = $pdo->prepare("
    SELECT contract_number
    FROM client_contracts
    ORDER BY id DESC
    LIMIT 1
");
$nextStmt->execute();
$lastContract = $nextStmt->fetch(PDO::FETCH_ASSOC);

$nextNumber = 7046;
if ($lastContract && !empty($lastContract['contract_number'])) {
    if (preg_match('/CTR-(\d+)/', $lastContract['contract_number'], $m)) {
        $nextNumber = ((int)$m[1]) + 1;
    }
}
$autoContractRef = 'CTR-' . $nextNumber;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $contractNumber = trim($_POST['contract_number'] ?? $autoContractRef);
    $billingAddress = trim($_POST['billing_address'] ?? '');
    $gasType = trim($_POST['gas_type'] ?? '');
    $monthlyQuota = trim($_POST['monthly_quota'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $contractValue = (float)($_POST['contract_value'] ?? 0);
    $paymentTerms = trim($_POST['payment_terms'] ?? 'net30');
    $status = trim($_POST['status'] ?? 'active');

    if ($clientId > 0 && $contractNumber !== '' && $gasType !== '' && $startDate !== '' && $endDate !== '') {
        $insert = $pdo->prepare("
            INSERT INTO client_contracts
            (contract_number, client_id, monthly_quota, gas_type, start_date, end_date, contract_value, status, billing_address, unit, notes, payment_terms, created_at)
            VALUES
            (:contract_number, :client_id, :monthly_quota, :gas_type, :start_date, :end_date, :contract_value, :status, :billing_address, :unit, :notes, :payment_terms, NOW())
        ");
        $insert->execute([
            ':contract_number' => $contractNumber,
            ':client_id' => $clientId,
            ':monthly_quota' => $monthlyQuota,
            ':gas_type' => $gasType,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':contract_value' => $contractValue,
            ':status' => $status,
            ':billing_address' => $billingAddress,
            ':unit' => $unit,
            ':notes' => $notes,
            ':payment_terms' => $paymentTerms,
        ]);

        header('Location: client_contracts.php?success=1');
        exit;
    }
}

$clientIdSelected = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Contract | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A;
            --ink-soft: #2E2E2E;
            --paper: #FFFFFF;
            --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC;
            --line: #D8D6D1;
            --line-soft: #E7E5E1;
            --accent: #9A7B2E;
            --accent-soft: #FBF3DF;
            --mute: #767470;
            --mute-soft: #A6A39D;
            --sidebar: #1A1A1A;
            --sidebar-line: #2E2E2E;
            --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; }
        html { font-size: 16px; }
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: var(--paper-dim);
            color: var(--ink);
            font-feature-settings: "tnum" 1;
        }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        a, button { -webkit-tap-highlight-color: transparent; }
        .nav-row { position: relative; border-left: 2px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .btn-danger { background: var(--paper); color: #963B33; border: 1px solid var(--line); transition: all 0.15s ease; display: inline-flex; justify-content: center; align-items: center; }
        .btn-danger:hover { background: #F8E9E7; border-color: #963B33; }
        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 6px; }
        .form-input {
            width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 2px;
            padding: 8px 12px; font-size: 13.5px; color: var(--ink); transition: border-color 0.15s ease;
        }
        .form-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }
        .form-input:disabled { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0">

<?php include '../../includes/header.php'; ?>

<div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
    <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
        <span class="status-dot" style="background: #5C8A5C;"></span>System Nominal
    </span>
    <span class="w-px h-3" style="background: var(--line);"></span>
    <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
    <span class="w-px h-3" style="background: var(--line);"></span>
    <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
    <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
</div>

<div class="flex-1 overflow-auto px-8 py-7">

    <div class="flex justify-between items-end mb-7">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <a href="client_contracts.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration:none;">Contracts</a>
                <i data-lucide="chevron-right" class="w-3 h-3" style="color: var(--mute-soft);"></i>
                <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Draft Mode</span>
            </div>
            <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Draft New Contract</h2>
        </div>
        <div class="flex gap-3">
            <a href="client_contracts.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">Cancel</a>
            <button type="button" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>Save Draft
            </button>
            <button type="submit" form="contractForm" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>Activate Contract
            </button>
        </div>
    </div>

    <form id="contractForm" action="" method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Client & Master Reference</h3>
                    <div class="grid grid-cols-2 gap-5">
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Client Account</label>
                    <select name="client_id" class="form-input mono" required>
    <option value="" disabled selected>Select registered client...</option>
    <?php foreach ($clients as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $clientIdSelected === (int)$c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['company_name'] ?? 'Unknown Client') ?> 
            (<?= htmlspecialchars($c['reference_id'] ?? 'N/A') ?>)
        </option>
    <?php endforeach; ?>
</select>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="form-label">Contract Reference (Auto)</label>
                            <input type="text" name="contract_number" class="form-input mono num" value="<?= htmlspecialchars($autoContractRef) ?>" readonly>
                        </div>
                        <div class="col-span-2">
                            <label class="form-label">Billing Address / Plant Location</label>
                            <input type="text" name="billing_address" class="form-input" placeholder="e.g. Plot 42, Industrial Zone, Yanbu">
                        </div>
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Supply Specifications</h3>
                    <div class="grid grid-cols-3 gap-5">
                        <div class="col-span-3 md:col-span-1">
                            <label class="form-label">Gas Type / Mix</label>
                            <select name="gas_type" class="form-input mono">
                                <option value="LIQ. O₂">LIQ. O₂ (Liquid Oxygen)</option>
                                <option value="LIQ. N₂">LIQ. N₂ (Liquid Nitrogen)</option>
                                <option value="C₂H₂ 40L">C₂H₂ 40L (Acetylene)</option>
                                <option value="AR 50L">AR 50L (Argon)</option>
                                <option value="MIXED">Mixed Gas Custom</option>
                            </select>
                        </div>
                        <div class="col-span-3 md:col-span-1">
                            <label class="form-label">Monthly Quota</label>
                            <input type="text" name="monthly_quota" class="form-input mono num" placeholder="e.g. 200T / Month">
                        </div>
                        <div class="col-span-3 md:col-span-1">
                            <label class="form-label">Measurement Unit</label>
                            <select name="unit" class="form-input mono">
                                <option value="Cylinders">Cylinders</option>
                                <option value="MT">Metric Tons (MT)</option>
                                <option value="Liters">Liters</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Special Conditions & Remarks</h3>
                    <div>
                        <label class="form-label">Contract Terms & Logistics Notes</label>
                        <textarea name="notes" class="form-input" rows="4" placeholder="Enter specific delivery instructions, purity requirements, or penalty clauses..."></textarea>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1 flex flex-col gap-6">
                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Timeline</h3>
                    <div class="flex flex-col gap-5">
                        <div>
                            <label class="form-label">Effective Start Date</label>
                            <input type="date" name="start_date" class="form-input mono num">
                        </div>
                        <div>
                            <label class="form-label">Expiration Date</label>
                            <input type="date" name="end_date" class="form-input mono num">
                        </div>
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Financials</h3>
                    <div class="flex flex-col gap-5">
                        <div>
                            <label class="form-label">Total Contract Value (SAR)</label>
                            <input type="number" name="contract_value" class="form-input mono num text-[16px] font-semibold" placeholder="0.00" style="color: var(--ink);">
                        </div>
                        <div>
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" class="form-input mono">
                                <option value="net30">Net 30 Days</option>
                                <option value="net60">Net 60 Days</option>
                                <option value="cod">Cash on Delivery (COD)</option>
                                <option value="prepaid">Prepaid</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input mono">
                                <option value="active">Active</option>
                                <option value="expiring">Expiring Soon</option>
                                <option value="expired">Expired</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                    <h3 class="text-[13.5px] font-semibold tracking-tight mb-3" style="color: var(--ink);">Summary</h3>
                    <ul class="text-[12.5px] flex flex-col gap-2 mb-5" style="color: var(--mute);">
                        <li class="flex justify-between border-b pb-2" style="border-color: var(--line-soft);">
                            <span>Duration</span>
                            <span class="mono num" style="color: var(--ink);">--</span>
                        </li>
                        <li class="flex justify-between border-b pb-2" style="border-color: var(--line-soft);">
                            <span>Total Vol.</span>
                            <span class="mono num" style="color: var(--ink);">--</span>
                        </li>
                    </ul>
                    <button type="submit" class="w-full btn-primary py-2.5 rounded-sm text-[13.5px] font-medium">Validate Data</button>
                </div>
            </div>
        </div>
    </form>

</div>
</main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? null;
    $quotation_number = $_POST['quotation_number'] ?? '';
    $gas_type = $_POST['gas_type'] ?? '';
    $qty = (float)($_POST['qty'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $specs = $_POST['specs'] ?? '';
    $delivery_mechanism = $_POST['delivery_mechanism'] ?? '';
    $delivery_address = $_POST['delivery_address'] ?? '';
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $validity = (int)($_POST['validity'] ?? 30);
    $payment_terms = $_POST['payment_terms'] ?? '';

    $issue_date = date('Y-m-d');
    $expiry_date = date('Y-m-d', strtotime("+$validity days"));
    $status = isset($_POST['send_quotation']) ? 'sent' : 'draft';

    $subtotal = $qty * $unit_price;
    $vat_amount = $subtotal * 0.15;
    $total_value = $subtotal + $vat_amount;

    $notes = "Delivery: $delivery_mechanism | Address: $delivery_address | Terms: $payment_terms";

    $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, client_id, specs, issue_date, expiry_date, status, subtotal, vat_amount, total_value, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$quotation_number, $client_id, $specs, $issue_date, $expiry_date, $status, $subtotal, $vat_amount, $total_value, $notes]);
    $quotation_id = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO quotation_items (quotation_id, product_name, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtItem->execute([$quotation_id, $gas_type, $qty, $unit, $unit_price, $subtotal]);

    header("Location: quotations.php");
    exit;
}

$stmt_clients = $pdo->prepare("SELECT id, company_name FROM partners WHERE partner_type = 'client' AND status = 'approved'");
$stmt_clients->execute();
$clients = $stmt_clients->fetchAll();

$active_page = 'quotations';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Sales & Quotations', 'New Quotation'];
$new_quote_id = 'QT-' . rand(2300, 2450);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Quotation | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A; --ink-soft: #2E2E2E; --paper: #FFFFFF; --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC; --line: #D8D6D1; --line-soft: #E7E5E1; --accent: #9A7B2E;
            --accent-soft: #FBF3DF; --mute: #767470; --mute-soft: #A6A39D;
            --sidebar: #1A1A1A; --sidebar-line: #2E2E2E; --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; }
        html { font-size: 16px; }
        body { font-family: 'IBM Plex Sans', sans-serif; background-color: var(--paper-dim); color: var(--ink); font-feature-settings: "tnum" 1; }
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
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: none; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 6px; }
        .form-input { width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); transition: border-color 0.15s ease; }
        .form-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
        .form-input:disabled, .form-input.readonly { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }
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

            <form action="" method="POST" id="quotationForm">
                <input type="hidden" name="quotation_number" value="<?= htmlspecialchars($new_quote_id) ?>">
                
                <div class="flex justify-between items-end mb-7">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <a href="quotations.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Quotations</a>
                            <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">New Estimate</span>
                        </div>
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Quotation</h2>
                    </div>
                    <div class="flex gap-3">
                        <a href="quotations.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">Cancel</a>
                        <button type="submit" name="save_draft" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>Save Draft
                        </button>
                        <button type="submit" name="send_quotation" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>Send Quotation
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Account Selection</h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client Account</label>
                                    <select name="client_id" class="form-input mono" required>
                                        <option value="" selected disabled>Select registered client Account...</option>
                                        <?php foreach($clients as $client): ?>
                                            <option value="<?= htmlspecialchars($client['id']) ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Quotation Reference</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($new_quote_id) ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Line Items & Parameters</h3>
                            <div class="grid grid-cols-3 gap-5">
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Gas Type / Mix</label>
                                    <select name="gas_type" class="form-input mono" required>
                                        <option value="" selected disabled>Select gas type...</option>
                                        <option value="Liquid Oxygen">LIQ. O₂ (Liquid Oxygen)</option>
                                        <option value="Liquid Nitrogen">LIQ. N₂ (Liquid Nitrogen)</option>
                                        <option value="Acetylene">C₂H₂ 40L (Acetylene)</option>
                                        <option value="Argon">AR 50L (Argon)</option>
                                        <option value="Mixed Gas Custom">Mixed Gas Custom</option>
                                    </select>
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="qty" id="input_qty" class="form-input mono num" placeholder="0.00" step="0.01" required>
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Measurement Unit</label>
                                    <select name="unit" class="form-input mono" required>
                                        <option value="Cylinders">Cylinders</option>
                                        <option value="Tons">Metric Tons (MT)</option>
                                        <option value="Liters">Liters</option>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="form-label">Specifications & Purity Demands</label>
                                    <input type="text" name="specs" class="form-input" placeholder="e.g. High purity medical grade or specialized block pressure criteria">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Logistics & Terms</h3>
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Delivery Mechanism</label>
                                    <select name="delivery_mechanism" class="form-input mono">
                                        <option value="I-GAS Fleet Dispatch">I-GAS Fleet Dispatch</option>
                                        <option value="Customer Self-Pickup">Customer Self-Pickup</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Delivery Address / Destination</label>
                                    <input type="text" name="delivery_address" class="form-input" placeholder="Plant site details or coordinates">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Pricing Structure</h3>
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Unit Base Price (SAR)</label>
                                    <input type="number" name="unit_price" id="input_price" class="form-input mono num" placeholder="0.00" step="0.01" required>
                                </div>
                                <div>
                                    <label class="form-label">Validity Period</label>
                                    <select name="validity" class="form-input mono">
                                        <option value="14">14 Days</option>
                                        <option value="30" selected>30 Days</option>
                                        <option value="60">60 Days</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Payment Terms</label>
                                    <select name="payment_terms" class="form-input mono">
                                        <option value="Net 30 Days">Net 30 Days</option>
                                        <option value="Net 60 Days">Net 60 Days</option>
                                        <option value="Cash on Delivery (COD)">Cash on Delivery (COD)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[13.5px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Calculation Summary</h3>
                            <ul class="text-[12.5px] flex flex-col gap-3 mb-5" style="color: var(--mute);">
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>Subtotal</span>
                                    <span id="ui_subtotal" class="mono num" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>VAT (15%)</span>
                                    <span id="ui_vat" class="mono num" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                                <li class="flex justify-between items-center pt-1">
                                    <span class="font-semibold" style="color: var(--ink);">Total Value</span>
                                    <span id="ui_total" class="mono num text-[15px] font-semibold" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                            </ul>
                            
                            <button type="button" id="calc_btn" class="w-full btn-secondary py-2.5 rounded-sm text-[13.5px] font-medium border-dashed border-2 mb-3">
                                Calculate Totals
                            </button>
                        </div>

                    </div>
                </div>
            </form>

        </div>
    </main>

    <script>
        lucide.createIcons();

        const qtyInput = document.getElementById('input_qty');
        const priceInput = document.getElementById('input_price');
        const uiSubtotal = document.getElementById('ui_subtotal');
        const uiVat = document.getElementById('ui_vat');
        const uiTotal = document.getElementById('ui_total');
        const calcBtn = document.getElementById('calc_btn');

        function calculateTotals() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            
            const subtotal = qty * price;
            const vat = subtotal * 0.15;
            const total = subtotal + vat;

            uiSubtotal.textContent = subtotal.toFixed(2) + ' SAR';
            uiVat.textContent = vat.toFixed(2) + ' SAR';
            uiTotal.textContent = total.toFixed(2) + ' SAR';
        }

        qtyInput.addEventListener('input', calculateTotals);
        priceInput.addEventListener('input', calculateTotals);
        calcBtn.addEventListener('click', calculateTotals);
    </script>
</body>
</html>
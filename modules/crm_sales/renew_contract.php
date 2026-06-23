<?php
$active_page = 'client_contracts';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Renew Contract'];

$contract_id = isset($_GET['id']) ? $_GET['id'] : 'CTR-7044';

$current_contract = [
    'id' => $contract_id,
    'client' => 'Air Product Co.',
    'gas_type' => 'C₂H₂ 40L',
    'current_quota' => '500',
    'unit' => 'cylinders',
    'start_date' => '2024-06-01',
    'end_date' => '2026-07-15',
    'value' => 1250000,
    'payment_terms' => 'net30'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Contract | I-GAS Enterprise</title>
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

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--mute); margin-bottom: 6px; }
        .form-input {
            width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 2px;
            padding: 8px 12px; font-size: 13.5px; color: var(--ink); transition: border-color 0.15s ease;
        }
        .form-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
        .form-input:disabled, .form-input.readonly { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }
        
        .badge-warning { background: #FBF3DF; color: #7A5E1E; padding: 2px 6px; border-radius: 2px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
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
                        <a href="client_contracts.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); hover:color: var(--ink);">Contracts</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Renewal Operations</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none flex items-center gap-3" style="color: var(--ink);">
                        Renewing <?= htmlspecialchars($current_contract['id']) ?>
                        <span class="badge-warning">Expiring Soon</span>
                    </h2>
                </div>
                <div class="flex gap-3">
                    <a href="client_contracts.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>Confirm Renewal
                    </button>
                </div>
            </div>

            <form action="#" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex justify-between items-center" style="color: var(--ink); border-color: var(--line-soft);">
                                <span>1. Current Contract Snapshot</span>
                                <span class="text-[11px] font-normal uppercase tracking-wider" style="color: var(--mute);">Read Only</span>
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client Account</label>
                                    <input type="text" class="form-input readonly" value="<?= htmlspecialchars($current_contract['client']) ?>" disabled>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Gas Type</label>
                                    <input type="text" class="form-input readonly mono" value="<?= htmlspecialchars($current_contract['gas_type']) ?>" disabled>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Current Expiration</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($current_contract['end_date']) ?>" disabled>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Current Monthly Quota</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($current_contract['current_quota'] . ' ' . ucfirst($current_contract['unit'])) ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6 border-l-4" style="border-left-color: var(--ink);">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Renewal Parameters</h3>
                            
                            <div class="grid grid-cols-3 gap-5">
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">New Effective Date</label>
                                    <input type="date" class="form-input mono num" value="<?= htmlspecialchars($current_contract['end_date']) ?>">
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">New Expiration Date</label>
                                    <input type="date" class="form-input mono num" value="2027-07-15">
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Renewal Period</label>
                                    <select class="form-input mono">
                                        <option value="12">12 Months</option>
                                        <option value="24">24 Months</option>
                                        <option value="36">36 Months</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>

                                <div class="col-span-3 border-t mt-2 pt-5" style="border-color: var(--line-soft);"></div>

                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Adjusted Monthly Quota</label>
                                    <input type="number" class="form-input mono num" value="<?= htmlspecialchars($current_contract['current_quota']) ?>">
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Measurement Unit</label>
                                    <select class="form-input mono">
                                        <option value="cylinders" <?= $current_contract['unit'] == 'cylinders' ? 'selected' : '' ?>>Cylinders</option>
                                        <option value="tons" <?= $current_contract['unit'] == 'tons' ? 'selected' : '' ?>>Metric Tons (MT)</option>
                                    </select>
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">New Contract Value (SAR)</label>
                                    <input type="number" class="form-input mono num text-[15px] font-semibold" value="<?= htmlspecialchars($current_contract['value']) ?>" style="color: var(--ink);">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Updated Conditions</h3>
                            
                            <div>
                                <label class="form-label">Renewal Remarks & Adjustments</label>
                                <textarea class="form-input" rows="3" placeholder="Note any changes in pricing, delivery schedules, or special agreements for this renewal cycle..."></textarea>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Billing Updates</h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Payment Terms</label>
                                    <select class="form-input mono">
                                        <option value="net30" <?= $current_contract['payment_terms'] == 'net30' ? 'selected' : '' ?>>Net 30 Days</option>
                                        <option value="net60" <?= $current_contract['payment_terms'] == 'net60' ? 'selected' : '' ?>>Net 60 Days</option>
                                        <option value="cod" <?= $current_contract['payment_terms'] == 'cod' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Price Indexing</label>
                                    <select class="form-input mono">
                                        <option value="fixed">Fixed Price</option>
                                        <option value="variable">Market Variable</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[13.5px] font-semibold tracking-tight mb-3" style="color: var(--ink);">Impact Summary</h3>
                            <ul class="text-[12.5px] flex flex-col gap-3 mb-5" style="color: var(--mute);">
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>Quota Change</span>
                                    <span class="mono num" style="color: var(--ink);">0%</span>
                                </li>
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>Value Delta</span>
                                    <span class="mono num" style="color: var(--ink);">+0.00 SAR</span>
                                </li>
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>New End Date</span>
                                    <span class="mono num font-medium" style="color: var(--ink);">2027-07-15</span>
                                </li>
                            </ul>
                            <div class="flex items-center gap-2 mb-4">
                                <input type="checkbox" id="notify" class="w-4 h-4 border-gray-300 rounded" checked>
                                <label for="notify" class="text-[12px]" style="color: var(--mute);">Notify client billing department automatically</label>
                            </div>
                            <button class="w-full btn-secondary py-2.5 rounded-sm text-[13.5px] font-medium border-dashed border-2">
                                Recalculate Totals
                            </button>
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
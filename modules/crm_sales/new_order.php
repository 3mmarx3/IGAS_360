<?php
$active_page = 'clients';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'New Order'];

$client_id = isset($_GET['client_id']) ? $_GET['client_id'] : 'ACC-1042';
$client_name = 'SABIC Petrochemicals'; 

$order_ref = 'ORD-' . rand(7800, 7999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order | I-GAS Enterprise</title>
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
                        <a href="client_profile.php?id=<?= $client_id ?>" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Client Profile</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Order Creation</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Order</h2>
                </div>
                <div class="flex gap-3">
                    <a href="client_profile.php?id=<?= $client_id ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>Submit Order
                    </button>
                </div>
            </div>

            <form action="#" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Client & Reference</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client Account</label>
                                    <input type="text" class="form-input readonly" value="<?= htmlspecialchars($client_name) ?>" disabled>
                                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Order Reference</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $order_ref ?>" disabled>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Purchase Order (PO) Number</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. PO-998234">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Order Date</label>
                                    <input type="date" class="form-input mono num" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Order Specifications</h3>
                            
                            <div class="grid grid-cols-3 gap-5">
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Gas Type / Mix</label>
                                    <select class="form-input mono">
                                        <option value="">Select gas type...</option>
                                        <option value="liq_o2">LIQ. O₂ (Liquid Oxygen)</option>
                                        <option value="liq_n2">LIQ. N₂ (Liquid Nitrogen)</option>
                                        <option value="c2h2_40">C₂H₂ 40L (Acetylene)</option>
                                        <option value="ar_50">AR 50L (Argon)</option>
                                        <option value="mixed">Mixed Gas Custom</option>
                                    </select>
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-input mono num" placeholder="0.00">
                                </div>
                                <div class="col-span-3 md:col-span-1">
                                    <label class="form-label">Unit</label>
                                    <select class="form-input mono">
                                        <option value="cylinders">Cylinders</option>
                                        <option value="tons">Metric Tons (MT)</option>
                                        <option value="liters">Liters</option>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="form-label">Purity / Special Configuration Notes</label>
                                    <input type="text" class="form-input" placeholder="e.g. Industrial grade, min 99.5% purity">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Logistics & Delivery</h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Expected Delivery Date</label>
                                    <input type="date" class="form-input mono num">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Delivery Priority</label>
                                    <select class="form-input mono">
                                        <option value="standard">Standard (3-5 Days)</option>
                                        <option value="express">Express (Next Day)</option>
                                        <option value="urgent">Urgent / Emergency</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Delivery Address / Plant Location</label>
                                    <textarea class="form-input" rows="2" placeholder="Enter delivery coordinates, gate number, or contact person at site..."></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Financial Setup</h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Unit Price (SAR)</label>
                                    <input type="number" class="form-input mono num" placeholder="0.00">
                                </div>
                                <div>
                                    <label class="form-label">Discount (%)</label>
                                    <input type="number" class="form-input mono num" placeholder="0">
                                </div>
                                <div>
                                    <label class="form-label">Payment Terms</label>
                                    <select class="form-input mono">
                                        <option value="net30">Net 30 Days (Contract Default)</option>
                                        <option value="net60">Net 60 Days</option>
                                        <option value="cod">Cash on Delivery (COD)</option>
                                        <option value="prepaid">Prepaid</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[13.5px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Order Summary</h3>
                            <ul class="text-[12.5px] flex flex-col gap-3 mb-5" style="color: var(--mute);">
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>Subtotal</span>
                                    <span class="mono num" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                                <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                    <span>VAT (15%)</span>
                                    <span class="mono num" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                                <li class="flex justify-between items-center pt-1">
                                    <span class="font-semibold" style="color: var(--ink);">Grand Total</span>
                                    <span class="mono num text-[15px] font-semibold" style="color: var(--ink);">0.00 SAR</span>
                                </li>
                            </ul>
                            <div class="flex items-center gap-2 mb-5">
                                <input type="checkbox" id="email_receipt" class="w-4 h-4 border-gray-300 rounded" checked>
                                <label for="email_receipt" class="text-[12px]" style="color: var(--mute);">Send confirmation to client</label>
                            </div>
                            <button class="w-full btn-secondary py-2.5 rounded-sm text-[13.5px] font-medium border-dashed border-2 mb-3">
                                Calculate Totals
                            </button>
                            <button class="w-full btn-primary py-2.5 rounded-sm text-[13.5px] font-medium">
                                Confirm & Route to Production
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
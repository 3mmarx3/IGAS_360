<?php
$active_page = 'purchase_orders';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Create Order'];

$suppliers = [
    ['id' => 'SUP-5001', 'name' => 'Gulf Industrial Gases'],
    ['id' => 'SUP-5012', 'name' => 'Saudi Basic Industries (SABIC)'],
    ['id' => 'SUP-5033', 'name' => 'Air Products Qudra'],
];

$delivery_locations = [
    ['id' => 'LOC-1', 'name' => 'Jeddah Industrial — Main Plant (HQ)'],
    ['id' => 'LOC-2', 'name' => 'Riyadh Distribution Hub'],
    ['id' => 'LOC-3', 'name' => 'Yanbu Storage Facility'],
];

$order_id = 'PO-' . date('Ym') . '-' . rand(1000, 9999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order | I-GAS Enterprise</title>
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
        
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }

        .item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: start; margin-bottom: 12px; }
        
        .summary-sticky { position: sticky; top: 20px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

        <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
            <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
                <span class="status-dot" style="background: #2A6B8A;"></span>Procurement Active
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">System Date · <?= date('Y-m-d') ?></span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="purchase_orders.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Purchase Orders</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Create Order</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Purchase Request</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Draft a new B2B order, assign a verified supplier, and specify delivery logistics.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-7 items-start">
                
                <div class="xl:col-span-2 flex flex-col gap-6">
                    
                    <div class="card rounded-md p-6">
                        <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                            <i data-lucide="building" class="w-4 h-4" style="color: var(--mute);"></i>Supplier Selection
                        </h3>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Approved Supplier</label>
                                <select class="form-select" required>
                                    <option value="" selected disabled>Select from verified network...</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Generated P.O. Number</label>
                                <input type="text" class="form-input mono num bg-[var(--paper-deep)]" value="<?= $order_id ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md p-6">
                        <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2 justify-between" style="color: var(--ink); border-color: var(--line-soft);">
                            <div class="flex items-center gap-2"><i data-lucide="package-search" class="w-4 h-4" style="color: var(--mute);"></i>Product Catalog & Quantities</div>
                            <button type="button" class="text-[12px] font-medium flex items-center gap-1" style="color: var(--ink);"><i data-lucide="plus" class="w-3.5 h-3.5"></i>Add Line Item</button>
                        </h3>
                        
                        <div class="flex flex-col">
                            <div class="item-row mb-2">
                                <label class="form-label mb-0">Gas Product / Mix</label>
                                <label class="form-label mb-0">Qty (Liters/Cyl)</label>
                                <label class="form-label mb-0">Unit Price</label>
                                <div></div>
                            </div>
                            
                            <div class="item-row">
                                <select class="form-select">
                                    <option>Liquid Oxygen (LOX) - Bulk</option>
                                    <option selected>Argon/CO₂ Mix - 50L Cylinder</option>
                                    <option>High Purity Nitrogen - Bulk</option>
                                </select>
                                <input type="number" class="form-input num" placeholder="0" value="40">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] text-[var(--mute)]">SAR</span>
                                    <input type="number" class="form-input num pl-9" placeholder="0.00" value="250.00">
                                </div>
                                <button type="button" class="w-9 h-9 flex items-center justify-center border rounded-sm transition-colors hover:bg-[var(--paper-deep)]" style="border-color: var(--line-soft); color: var(--mute);">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <div class="item-row">
                                <select class="form-select">
                                    <option>Liquid Oxygen (LOX) - Bulk</option>
                                    <option>Argon/CO₂ Mix - 50L Cylinder</option>
                                    <option selected>Acetylene (C₂H₂) - 40L Cylinder</option>
                                </select>
                                <input type="number" class="form-input num" placeholder="0" value="15">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] text-[var(--mute)]">SAR</span>
                                    <input type="number" class="form-input num pl-9" placeholder="0.00" value="180.00">
                                </div>
                                <button type="button" class="w-9 h-9 flex items-center justify-center border rounded-sm transition-colors hover:bg-[var(--paper-deep)]" style="border-color: var(--line-soft); color: var(--mute);">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md p-6">
                        <h3 class="text-[14.5px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                            <i data-lucide="map-pin" class="w-4 h-4" style="color: var(--mute);"></i>Logistics & Delivery Details
                        </h3>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Delivery Location</label>
                                <select class="form-select">
                                    <option value="" disabled>Select registered facility...</option>
                                    <?php foreach ($delivery_locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="custom">-- Enter Custom Location --</option>
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Required Delivery Date</label>
                                <input type="date" class="form-input mono text-[13px]">
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Special Delivery Instructions / Gate Passes</label>
                                <textarea class="form-input" rows="2" placeholder="e.g. Requires forklift for unloading. Contact supervisor upon arrival."></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="xl:col-span-1">
                    <div class="card rounded-md p-6 summary-sticky" style="background: var(--paper-deep);">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Order Summary</h3>
                        
                        <div class="flex flex-col gap-4 mb-6 text-[13.5px]">
                            <div class="flex justify-between items-center">
                                <span style="color: var(--mute);">Subtotal</span>
                                <span class="font-medium num">SAR 12,700.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span style="color: var(--mute);">Logistics &amp; Transport</span>
                                <span class="font-medium num">SAR 500.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span style="color: var(--mute);">VAT (15%)</span>
                                <span class="font-medium num">SAR 1,980.00</span>
                            </div>
                            <div class="flex justify-between items-center pt-4 border-t" style="border-color: var(--line-soft);">
                                <span class="font-bold text-[15px]" style="color: var(--ink);">Total Amount</span>
                                <span class="font-bold text-[18px] num" style="color: var(--ink);">SAR 15,180.00</span>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Internal Reference / Cost Center</label>
                            <input type="text" class="form-input bg-white" placeholder="e.g. PRJ-2026-A">
                        </div>

                        <button type="submit" class="btn-primary w-full py-3 rounded-sm text-[13.5px] font-medium flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>Submit Order to Supplier
                        </button>
                        
                        <p class="text-[11px] text-center mt-4" style="color: var(--mute);">By submitting, you agree to the <a href="#" class="underline">procurement terms</a> of I-GAS Enterprise.</p>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
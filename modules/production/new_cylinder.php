<?php
$active_page = 'cylinders_inventory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Cylinders Inventory', 'New Batch Registration'];

$new_batch_id = 'BAT-' . rand(8000, 8999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Cylinder Batch | I-GAS Enterprise</title>
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
        .form-input:disabled { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }

        .input-group { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }
        .has-suffix { padding-right: 45px; }
        .input-suffix { position: absolute; right: 12px; font-size: 12px; color: var(--mute); pointer-events: none; font-family: 'IBM Plex Mono', monospace; }
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
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="cylinders_inventory.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Cylinders Inventory</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Batch Registration</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register New Batch</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Add a new batch of physical cylinders to the plant inventory and set compliance details.</p>
                </div>
                <div class="flex gap-3">
                    <a href="cylinders_inventory.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Register Assets
                    </button>
                </div>
            </div>

            <form action="cylinders_inventory.php" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="cylinder" class="w-4 h-4" style="color: var(--mute);"></i>Asset Specifications
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Gas Classification</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Select primary gas...</option>
                                        <option value="Oxygen">Oxygen (O₂)</option>
                                        <option value="Acetylene">Acetylene (C₂H₂)</option>
                                        <option value="Argon">Argon (Ar)</option>
                                        <option value="Nitrogen">Nitrogen (N₂)</option>
                                        <option value="Helium">Helium (He)</option>
                                        <option value="Carbon Dioxide">Carbon Dioxide (CO₂)</option>
                                        <option value="Mixed Gas">Mixed Gas</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Cylinder Volume / Size</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Select volume...</option>
                                        <option value="50L">50 Liters</option>
                                        <option value="40L">40 Liters</option>
                                        <option value="20L">20 Liters</option>
                                        <option value="10L">10 Liters</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Build Material</label>
                                    <input type="text" class="form-input" placeholder="e.g. Seamless Steel (34CrMo4)" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Valve Specification</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. CGA 540, BS341" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Color Coding Standard</label>
                                    <input type="text" class="form-input" placeholder="e.g. White Shoulder / Black Body" required>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="package-plus" class="w-4 h-4" style="color: var(--mute);"></i>Batch Details
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Quantity to Register</label>
                                    <div class="input-group">
                                        <input type="number" class="form-input has-suffix mono num" placeholder="0" min="1" required>
                                        <span class="input-suffix">UNITS</span>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Supplier / Manufacturer</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Select supplier...</option>
                                        <option value="SUP-5002">Modern Steel Fabricators</option>
                                        <option value="SUP-5004">Chemical Solutions Co.</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Purchase Order Ref</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. PO-8850">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Manufacturing Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono num" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="hash" class="w-4 h-4" style="color: var(--mute);"></i>System Allocation
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Batch ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $new_batch_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Target SKU (Auto-matched)</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. CYL-O2-50L" readonly style="background: var(--paper-dim); color: var(--mute);">
                                </div>
                                <div>
                                    <label class="form-label">Initial Location Status</label>
                                    <select class="form-select" disabled>
                                        <option value="plant" selected>In Plant (Ready/Empty)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="shield-check" class="w-4 h-4" style="color: var(--mute);"></i>Compliance &amp; Testing
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label">Working Pressure</label>
                                        <div class="input-group">
                                            <input type="number" class="form-input has-suffix mono num" placeholder="200" required>
                                            <span class="input-suffix">BAR</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Test Pressure</label>
                                        <div class="input-group">
                                            <input type="number" class="form-input has-suffix mono num" placeholder="300" required>
                                            <span class="input-suffix">BAR</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Initial Hydro-Test Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar-check" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Next Hydro-Test Due</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar-clock" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono num" value="<?= date('Y-m-d', strtotime('+5 years')) ?>" required>
                                    </div>
                                </div>
                            </div>
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
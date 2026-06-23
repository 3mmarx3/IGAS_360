<?php
$active_page = 'gases_mixtures';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Gases & Mixtures', 'Formulate Mix'];

$new_mix_id = 'GAS-' . rand(110, 999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulate Mix | I-GAS Enterprise</title>
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
        .has-suffix { padding-right: 45px; }
        .input-suffix { position: absolute; right: 12px; font-size: 12px; color: var(--mute); pointer-events: none; font-family: 'IBM Plex Mono', monospace; }

        .check-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; cursor: pointer; }
        .check-box { width: 16px; height: 16px; border: 1px solid var(--mute); border-radius: 2px; display: flex; align-items: center; justify-content: center; margin-top: 2px; transition: all 0.15s ease; }
        input[type="checkbox"] { display: none; }
        input[type="checkbox"]:checked + .check-box { background: var(--ink); border-color: var(--ink); color: white; }
        .check-text { font-size: 13px; color: var(--ink); }
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
                        <a href="gases_mixtures.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Gases &amp; Mixtures</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Formulation</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Formulate Product</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Define new gas mixtures, establish component ratios, and assign quality control parameters.</p>
                </div>
                <div class="flex gap-3">
                    <a href="gases_mixtures.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Formulation
                    </button>
                </div>
            </div>

            <form action="gases_mixtures.php" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="flask-conical" class="w-4 h-4" style="color: var(--mute);"></i>Product Definition
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Commercial Product Name</label>
                                    <input type="text" class="form-input" placeholder="e.g. Argon/CO2 Mix, High Purity Nitrogen" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Product Category</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Select classification...</option>
                                        <option value="Pure Gas">Pure Gas</option>
                                        <option value="Standard Mix">Standard Industrial Mix</option>
                                        <option value="Custom Mix">Custom / Calibration Mix</option>
                                        <option value="Medical">Medical Grade Gas</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Purity Grade Target</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. 99.999%, Industrial, Medical">
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Chemical Formula / Shorthand</label>
                                    <input type="text" class="form-input mono" placeholder="e.g. Ar 80% / CO₂ 20%">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="test-tubes" class="w-4 h-4" style="color: var(--mute);"></i>Composition Breakdown
                            </h3>
                            
                            <div class="flex flex-col gap-4">
                                <div class="grid grid-cols-3 gap-3 items-end">
                                    <div class="col-span-2">
                                        <label class="form-label">Base Gas / Component 1</label>
                                        <select class="form-select">
                                            <option value="" selected disabled>Select raw material...</option>
                                            <option value="Argon">Argon (Ar)</option>
                                            <option value="Nitrogen">Nitrogen (N₂)</option>
                                            <option value="Oxygen">Oxygen (O₂)</option>
                                            <option value="Helium">Helium (He)</option>
                                        </select>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="form-label">Ratio / Volume</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-input has-suffix num" placeholder="0.0">
                                            <span class="input-suffix">%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-3 items-end">
                                    <div class="col-span-2">
                                        <label class="form-label">Additive / Component 2</label>
                                        <select class="form-select">
                                            <option value="" selected>None (Pure Gas)</option>
                                            <option value="Carbon Dioxide">Carbon Dioxide (CO₂)</option>
                                            <option value="Oxygen">Oxygen (O₂)</option>
                                            <option value="Argon">Argon (Ar)</option>
                                            <option value="Carbon Monoxide">Carbon Monoxide (CO)</option>
                                        </select>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="form-label">Ratio / Volume</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-input has-suffix num" placeholder="0.0">
                                            <span class="input-suffix">%</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3 items-end">
                                    <div class="col-span-2">
                                        <label class="form-label">Additive / Component 3 (Optional)</label>
                                        <select class="form-select">
                                            <option value="" selected>None</option>
                                            <option value="Helium">Helium (He)</option>
                                            <option value="Nitrogen">Nitrogen (N₂)</option>
                                            <option value="Hydrogen">Hydrogen (H₂)</option>
                                        </select>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="form-label">Ratio / Volume</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-input has-suffix num" placeholder="0.0">
                                            <span class="input-suffix">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-2 text-right">
                                    <span class="text-[11px] font-medium" style="color: var(--mute);">Total Composition Must Equal 100%</span>
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
                                    <label class="form-label">Generated Product ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $new_mix_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Initial Production Status</label>
                                    <select class="form-select">
                                        <option value="testing" selected>In QC Lab / Testing</option>
                                        <option value="certified">QC Certified / Ready</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="microscope" class="w-4 h-4" style="color: var(--mute);"></i>QC Lab Requirements
                            </h3>
                            
                            <div class="flex flex-col">
                                <label class="check-item">
                                    <input type="checkbox" checked>
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Gas Chromatography Analysis</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" checked>
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Moisture / Dew Point Check</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Oxygen Deficiency Sensor Check</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Cylinder Valve Leak Test</span>
                                </label>
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
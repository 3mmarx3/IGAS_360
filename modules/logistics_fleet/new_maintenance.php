<?php
$active_page = 'maintenance_fuel';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel', 'New Service Ticket'];

$new_ticket_id = 'MNT-' . rand(4025, 4999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Service Ticket | I-GAS Enterprise</title>
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
        .currency-symbol { position: absolute; left: 12px; font-size: 12px; font-weight: 600; color: var(--mute); pointer-events: none; }
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

            <div >
                <div class="mb-7">
                    <div class="flex items-center gap-2 mb-2">
                        <a href="maintenance_fuel.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Maintenance Registry</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">New Ticket</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Service Ticket</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Schedule vehicle maintenance, log workshop repairs, and forecast service costs.</p>
                </div>

                <form action="maintenance_fuel.php" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <div class="p-8">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="wrench" class="w-4 h-4" style="color: var(--mute);"></i>Ticket Initialization
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Ticket ID</label>
                                <input type="text" class="form-input readonly mono num" value="<?= $new_ticket_id ?>" disabled>
                            </div>
                            <div>
                                <label class="form-label">Service Type</label>
                                <select class="form-select" name="service_type" required>
                                    <option value="" disabled selected>Select category...</option>
                                    <option value="preventive">Preventive Maintenance (PM)</option>
                                    <option value="repair">Corrective Repair</option>
                                    <option value="inspection">Safety / DOT Inspection</option>
                                    <option value="tires">Tire Replacement & Alignment</option>
                                    <option value="oil">Oil & Fluid Change</option>
                                </select>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>Asset Details
                        </h3>

                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Select Fleet Vehicle</label>
                                <select class="form-select mono" name="vehicle_id" required>
                                    <option value="" disabled selected>Identify unit...</option>
                                    <option value="FLT-001">FLT-001 (Mercedes Actros)</option>
                                    <option value="FLT-002">FLT-002 (Mercedes Actros)</option>
                                    <option value="FLT-004">FLT-004 (Volvo FH16)</option>
                                    <option value="FLT-005">FLT-005 (Isuzu NPR)</option>
                                    <option value="FLT-007">FLT-007 (Scania R500)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Current Odometer (KM)</label>
                                <div class="input-group">
                                    <i data-lucide="gauge" class="w-4 h-4 input-icon"></i>
                                    <input type="number" class="form-input has-icon num" placeholder="e.g. 145000" required>
                                </div>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="calendar-clock" class="w-4 h-4" style="color: var(--mute);"></i>Scheduling &amp; Financials
                        </h3>

                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Scheduled Date</label>
                                <div class="input-group">
                                    <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                    <input type="date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Estimated Cost</label>
                                <div class="input-group">
                                    <span class="currency-symbol">SAR</span>
                                    <input type="number" step="0.01" class="form-input has-icon num" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Assigned Workshop / Mechanic</label>
                                <input type="text" class="form-input" placeholder="e.g. In-house Garage or External Vendor Name" required>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="clipboard-list" class="w-4 h-4" style="color: var(--mute);"></i>Service Instructions
                        </h3>

                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="form-label">Reported Issues &amp; Instructions</label>
                                <textarea class="form-input" rows="4" placeholder="Detail the symptoms, required part replacements, or standard operating procedures for this service ticket..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="background: var(--paper-dim); border-color: var(--line-soft);">
                        <a href="maintenance_fuel.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel Ticket</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Authorize &amp; Schedule</button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
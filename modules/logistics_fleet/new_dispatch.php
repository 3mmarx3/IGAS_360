<?php
$active_page = 'dispatch_delivery';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery', 'New Dispatch'];

$new_manifest_id = 'DSP-' . rand(907, 999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Dispatch | I-GAS Enterprise</title>
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
                        <a href="dispatch_delivery.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Dispatch Registry</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Deployment</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Dispatch Manifest</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Authorize vehicle deployment, bind sales references, and establish logistics routing.</p>
                </div>

                <form action="dispatch_delivery.php" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <div class="p-8">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="file-text" class="w-4 h-4" style="color: var(--mute);"></i>Manifest Context
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Manifest ID</label>
                                <input type="text" class="form-input readonly mono num" value="<?= $new_manifest_id ?>" disabled>
                            </div>
                            <div>
                                <label class="form-label">Linked Order Reference</label>
                                <select class="form-select mono" name="order_ref" required>
                                    <option value="" disabled selected>Select active order...</option>
                                    <option value="ORD-7742">ORD-7742 (SABIC Petrochemicals)</option>
                                    <option value="ORD-7690">ORD-7690 (Air Product Co.)</option>
                                    <option value="ORD-7654">ORD-7654 (Red Sea Marine Services)</option>
                                    <option value="ORD-7521">ORD-7521 (National Contracting)</option>
                                </select>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>Resource Allocation
                        </h3>

                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Assign Fleet Asset</label>
                                <select class="form-select mono" name="vehicle_id" required>
                                    <option value="" disabled selected>Select available vehicle...</option>
                                    <option value="FLT-002">FLT-002 (Flatbed Truck — R N B 9876)</option>
                                    <option value="FLT-006">FLT-006 (Pickup Truck — X Y Z 1122)</option>
                                    <option value="FLT-007">FLT-007 (Flatbed Truck — A B C 9988)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Assign Driver</label>
                                <select class="form-select" name="driver_id" required>
                                    <option value="" disabled selected>Select available personnel...</option>
                                    <option value="1">Mohammed Saad</option>
                                    <option value="2">Khalid Hassan</option>
                                    <option value="3">Tariq Nabil</option>
                                </select>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="map-pin" class="w-4 h-4" style="color: var(--mute);"></i>Routing &amp; Schedule
                        </h3>

                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="form-label">Destination Address / Drop-off Coordinates</label>
                                <input type="text" class="form-input" placeholder="e.g. Industrial City 2, Gate 4, Jubail" required>
                            </div>
                            <div>
                                <label class="form-label">Dispatch Gate Out Date</label>
                                <div class="input-group">
                                    <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                    <input type="date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Expected Arrival (ETA)</label>
                                <div class="input-group">
                                    <i data-lucide="clock" class="w-4 h-4 input-icon"></i>
                                    <input type="time" class="form-input has-icon mono num" required>
                                </div>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Special Delivery / Safety Manifest Instructions</label>
                                <textarea class="form-input" rows="3" placeholder="Enter security clearance protocols, hazmat pressure validation notes, or site contact criteria..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="background: var(--paper-dim); border-color: var(--line-soft);">
                        <a href="dispatch_delivery.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel Manifest</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Authorize &amp; Dispatch Run</button>
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
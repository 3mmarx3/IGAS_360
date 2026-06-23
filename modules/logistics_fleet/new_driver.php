<?php
$active_page = 'drivers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Add New Driver'];

$new_driver_id = 'DRV-' . rand(110, 199);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Driver | I-GAS Enterprise</title>
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
        .input-group { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }
        .form-input.readonly { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }
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
                <div class="flex justify-between items-end mb-7">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <a href="drivers_directory.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Drivers Directory</a>
                            <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Registration</span>
                        </div>
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register New Driver</h2>
                    </div>
                </div>

                <form action="drivers_directory.php" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-3">
                        
                        <div class="lg:col-span-2 p-8 border-r" style="border-color: var(--line-soft);">
                            
                            <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                                <i data-lucide="user" class="w-4 h-4" style="color: var(--mute);"></i>Personal Information
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-6 mb-8">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Full Name (as per ID)</label>
                                    <input type="text" class="form-input" placeholder="Enter driver's full name" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">National ID / Iqama</label>
                                    <input type="text" class="form-input mono" placeholder="10-digit ID number" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-input mono" placeholder="+966 5X XXX XXXX" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Email Address (Optional)</label>
                                    <input type="email" class="form-input mono" placeholder="driver@example.com">
                                </div>
                            </div>

                            <hr class="mb-8" style="border-color: var(--line-soft);">

                            <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                                <i data-lucide="award" class="w-4 h-4" style="color: var(--mute);"></i>License & Compliance
                            </h3>

                            <div class="grid grid-cols-2 gap-6">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">License Class</label>
                                    <select class="form-select" required>
                                        <option value="" disabled selected>Select license type</option>
                                        <option value="heavy_hazmat">Heavy / Hazmat (Dangerous Goods)</option>
                                        <option value="heavy">Heavy Transport</option>
                                        <option value="light">Light Commercial</option>
                                        <option value="private">Private</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">License Number</label>
                                    <input type="text" class="form-input mono" placeholder="License serial number" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">License Expiry Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono" required>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Medical Check Expiry</label>
                                    <div class="input-group">
                                        <i data-lucide="activity" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 p-8" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                                <i data-lucide="shield-check" class="w-4 h-4" style="color: var(--mute);"></i>System Assignment
                            </h3>

                            <div class="flex flex-col gap-6">
                                <div>
                                    <label class="form-label">Generated Driver ID</label>
                                    <input type="text" class="form-input readonly mono" value="<?= $new_driver_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Employment Status</label>
                                    <select class="form-select">
                                        <option value="active" selected>Active / On Duty</option>
                                        <option value="on_leave">On Leave / Vacation</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Assign to Vehicle</label>
                                    <select class="form-select mono">
                                        <option value="unassigned" selected>-- Unassigned --</option>
                                        <option value="FLT-004">FLT-004 (Isuzu NPR)</option>
                                        <option value="FLT-008">FLT-008 (Volvo FH16)</option>
                                    </select>
                                    <p class="text-[11px] mt-1.5" style="color: var(--mute);">You can assign a vehicle later from the dispatch panel.</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="border-color: var(--line-soft);">
                        <a href="drivers_directory.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Register Driver</button>
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
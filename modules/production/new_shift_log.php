<?php
$active_page = 'daily_log_shifts';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Daily Shift Logs', 'New Shift Entry'];

$new_shift_id = 'SHF-' . date('md') . '-N'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start New Shift | I-GAS Enterprise</title>
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
                        <a href="daily_log_shifts.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Daily Shift Logs</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">New Entry</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Initialize Shift Log</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Record starting parameters, assign personnel, and complete pre-shift safety handovers.</p>
                </div>
                <div class="flex gap-3">
                    <a href="daily_log_shifts.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="play-circle" class="w-4 h-4"></i>Start Operational Shift
                    </button>
                </div>
            </div>

            <form action="daily_log_shifts.php" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="clock" class="w-4 h-4" style="color: var(--mute);"></i>Time &amp; Personnel Assignment
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Operational Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                        <input type="date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Shift Cycle</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Select assigned block...</option>
                                        <option value="Morning">Morning (06:00 - 14:00)</option>
                                        <option value="Evening">Evening (14:00 - 22:00)</option>
                                        <option value="Night">Night (22:00 - 06:00)</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Shift Supervisor / Foreperson</label>
                                    <select class="form-select" required>
                                        <option value="" selected disabled>Identify lead...</option>
                                        <option value="Faisal Omar">Faisal Omar</option>
                                        <option value="Tariq Nabil">Tariq Nabil</option>
                                        <option value="Yasser Abdullah">Yasser Abdullah</option>
                                        <option value="Ahmad Al-Sayed">Ahmad Al-Sayed</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Active Operators (Headcount)</label>
                                    <input type="number" class="form-input mono num" placeholder="0" min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="target" class="w-4 h-4" style="color: var(--mute);"></i>Production Targets
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Primary Gas Focus</label>
                                    <select class="form-select">
                                        <option value="Mixed Production" selected>Mixed Production Lines</option>
                                        <option value="Oxygen">Oxygen (O₂) Focus</option>
                                        <option value="Acetylene">Acetylene (C₂H₂) Focus</option>
                                        <option value="Argon">Argon (Ar) Focus</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Target Cylinder Output</label>
                                    <input type="number" class="form-input mono num" placeholder="Estimated fill count..." min="0">
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Shift Notes &amp; Handover Brief</label>
                                    <textarea class="form-input" rows="4" placeholder="Log any pending issues from the previous shift, pending maintenance tickets, or special production requests..."></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="fingerprint" class="w-4 h-4" style="color: var(--mute);"></i>System Context
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Shift ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= $new_shift_id ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Status Context</label>
                                    <select class="form-select" disabled>
                                        <option value="in_progress" selected>In Progress (Active)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="clipboard-check" class="w-4 h-4" style="color: var(--mute);"></i>Pre-Shift Safety Check
                            </h3>
                            
                            <div class="flex flex-col">
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Cryogenic tanks pressure verified</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Filling manifolds visually inspected</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Ventilation systems active and nominal</span>
                                </label>
                                <label class="check-item">
                                    <input type="checkbox">
                                    <div class="check-box"><i data-lucide="check" class="w-3 h-3"></i></div>
                                    <span class="check-text">Safety gear (PPE) accounted for</span>
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
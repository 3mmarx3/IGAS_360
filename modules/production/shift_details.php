<?php
$active_page = 'daily_log_shifts';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Daily Shift Logs', 'Shift Details'];

$shift_id = $_GET['id'] ?? 'SHF-0623-E';

$shift = [
    'id' => $shift_id,
    'date' => '2026-06-23',
    'cycle' => 'Evening',
    'hours' => '14:00 - 22:00',
    'supervisor' => 'Faisal Omar',
    'operators' => 14,
    'status' => 'in_progress',
    'target_output' => 1000,
    'current_output' => 840,
    'downtime_mins' => 15,
    'safety_alerts' => 0,
    'handover_notes' => 'Line 2 compressor required minor recalibration at 16:30. All other systems operating at nominal pressure. Stock levels for empty 50L O2 cylinders are running low, advise morning shift to pull from warehouse B.'
];

$production_breakdown = [
    ['gas' => 'Oxygen (O₂)', 'size' => '50L', 'qty' => 450, 'target' => 500, 'status' => 'on_track'],
    ['gas' => 'Acetylene (C₂H₂)', 'size' => '40L', 'qty' => 200, 'target' => 250, 'status' => 'on_track'],
    ['gas' => 'Argon (Ar)', 'size' => '50L', 'qty' => 190, 'target' => 250, 'status' => 'lagging'],
];

$event_logs = [
    ['time' => '14:00', 'type' => 'System', 'desc' => 'Shift initialized. Pre-shift safety checks completed.', 'user' => 'Faisal Omar'],
    ['time' => '15:30', 'type' => 'Production', 'desc' => 'Oxygen Batch #4480 filled and moved to dispatch zone.', 'user' => 'Line 1 Operator'],
    ['time' => '16:30', 'type' => 'Maintenance', 'desc' => 'Line 2 compressor recalibration. 15 mins downtime.', 'user' => 'Maint. Team'],
    ['time' => '18:45', 'type' => 'Production', 'desc' => 'Acetylene Batch #8812 completed safely.', 'user' => 'Line 3 Operator'],
];

$statusStyles = [
    'in_progress' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Active Shift'],
    'completed'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Signed Off'],
    'flagged'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Flagged / Issues'],
];

$typeStyles = [
    'System'      => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'Production'  => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
    'Maintenance' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E'],
    'Alert'       => ['bg' => '#F8E9E7', 'fg' => '#963B33'],
];

$ss = $statusStyles[$shift['status']];
$progress_pct = min(100, ($shift['current_output'] / $shift['target_output']) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shift['id']) ?> | I-GAS Enterprise</title>
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

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); transition: width 0.3s ease; }

        th, td { vertical-align: middle; }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .info-row { padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
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

            <a href="daily_log_shifts.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Shift Logs
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="clock-4" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none mono" style="color: var(--ink);"><?= htmlspecialchars($shift['id']) ?></h2>
                            <span class="pill" style="background: <?= $ss['bg'] ?>; color: <?= $ss['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $ss['dot'] ?>;"></span><?= $ss['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($shift['date']) ?> · <?= htmlspecialchars($shift['hours']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Handover
                    </button>
                    <?php if($shift['status'] === 'in_progress'): ?>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2" style="background: #45663F; border-color: #45663F;">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>Sign-off Shift
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Production Output</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($shift['current_output']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">/ <?= number_format($shift['target_output']) ?></span></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $progress_pct ?>%;"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Operators</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $shift['operators'] ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Clocked in for shift</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Downtime</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $shift['downtime_mins'] > 0 ? '#9A7B2E' : 'var(--ink)' ?>;"><?= $shift['downtime_mins'] ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Minutes</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Accumulated line stops</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Safety Alerts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $shift['safety_alerts'] > 0 ? '#963B33' : 'var(--ink)' ?>;"><?= $shift['safety_alerts'] ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <?php if($shift['safety_alerts'] > 0): ?>
                            <span class="pill" style="background: #F8E9E7; color: #963B33;">Review needed</span>
                        <?php else: ?>
                            <span class="pill" style="background: #EAF1E7; color: #45663F;">Zero incidents</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 flex flex-col gap-5">
                    
                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Production Breakdown</h3>
                            <button class="transition-colors" style="color: var(--mute);"><i data-lucide="plus" class="w-4 h-4"></i></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                        <th class="pl-6 py-3 font-medium">Gas Classification</th>
                                        <th class="px-3 py-3 font-medium">Cylinder Size</th>
                                        <th class="px-3 py-3 font-medium text-right">Filled Qty</th>
                                        <th class="px-3 py-3 font-medium text-right">Target</th>
                                        <th class="pr-6 py-3 font-medium text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php foreach ($production_breakdown as $p): ?>
                                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                        <td class="pl-6 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($p['gas']) ?></td>
                                        <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($p['size']) ?></td>
                                        <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($p['qty']) ?></td>
                                        <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--mute);"><?= number_format($p['target']) ?></td>
                                        <td class="pr-6 py-3.5 text-right">
                                            <?php if($p['status'] === 'on_track'): ?>
                                                <span class="text-[12px] font-medium" style="color: #45663F;">On Track</span>
                                            <?php else: ?>
                                                <span class="text-[12px] font-medium" style="color: #9A7B2E;">Lagging</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Operational Timeline &amp; Logs</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                        <th class="pl-6 py-3 font-medium">Time</th>
                                        <th class="px-3 py-3 font-medium">Category</th>
                                        <th class="px-3 py-3 font-medium">Description</th>
                                        <th class="pr-6 py-3 font-medium text-right">Logged By</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php foreach ($event_logs as $e): ?>
                                    <?php $ts = $typeStyles[$e['type']]; ?>
                                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                        <td class="pl-6 py-3.5 text-[12.5px] mono font-medium" style="color: var(--ink);"><?= htmlspecialchars($e['time']) ?></td>
                                        <td class="px-3 py-3.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background: <?= $ts['bg'] ?>; color: <?= $ts['fg'] ?>;">
                                                <?= htmlspecialchars($e['type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3.5" style="color: var(--ink);"><?= htmlspecialchars($e['desc']) ?></td>
                                        <td class="pr-6 py-3.5 text-right font-medium text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($e['user']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="flex flex-col gap-5">
                    
                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Shift Properties</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Shift Supervisor</p>
                                <p class="info-value flex items-center gap-2">
                                    <div class="w-5 h-5 rounded-full bg-slate-200 flex items-center justify-center text-[9px] font-bold inline-flex" style="color: var(--ink-soft);">
                                        <?= substr($shift['supervisor'], 0, 1) ?>
                                    </div>
                                    <?= htmlspecialchars($shift['supervisor']) ?>
                                </p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Operational Cycle</p>
                                <p class="info-value"><?= htmlspecialchars($shift['cycle']) ?> Shift</p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Scheduled Hours</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($shift['hours']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Handover Notes</h3>
                            <button class="transition-colors" style="color: var(--mute);"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                        </div>
                        <div class="px-6 py-4">
                            <p class="text-[13px]" style="color: var(--ink); line-height: 1.6;">
                                <?= htmlspecialchars($shift['handover_notes']) ?>
                            </p>
                        </div>
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
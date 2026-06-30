<?php
session_start();
require_once '../../config/db.php';

$active_page = 'daily_log_shifts';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Daily Shift Logs'];

$totalStmt = $pdo->query("SELECT COUNT(*) FROM shift_logs");
$total_shifts = $totalStmt->fetchColumn() ?: 0;

$activeStmt = $pdo->query("SELECT shift_cycle FROM shift_logs WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1");
$active_cycle = $activeStmt->fetchColumn();
$active_shift = $active_cycle ? $active_cycle . ' Shift' : 'None';

$outputStmt = $pdo->query("SELECT SUM(actual_output) FROM shift_logs WHERE DATE_FORMAT(operational_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')");
$total_output = $outputStmt->fetchColumn() ?: 0;

$alertsStmt = $pdo->query("SELECT SUM(safety_alerts) FROM shift_logs WHERE DATE_FORMAT(operational_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')");
$safety_alerts = $alertsStmt->fetchColumn() ?: 0;

$shiftsStmt = $pdo->query("SELECT * FROM shift_logs ORDER BY operational_date DESC, id DESC");
$shifts = $shiftsStmt->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
    'in_progress' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Active Shift'],
    'completed'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Signed Off'],
    'flagged'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Flagged / Issues'],
];

function getShiftTimeFormat($cycle) {
    if ($cycle === 'Morning') return 'Morning (06:00 - 14:00)';
    if ($cycle === 'Evening') return 'Evening (14:00 - 22:00)';
    if ($cycle === 'Night') return 'Night (22:00 - 06:00)';
    return $cycle;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Shift Logs | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../../assets/css/main.css">
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

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
        .checkbox-sq { width: 14px; height: 14px; border: 1px solid var(--line); border-radius: 2px; display: inline-block; background: var(--paper); }
        
        .tab-item { cursor: pointer; padding-bottom: 12px; border-bottom: 2px solid transparent; transition: all 0.15s ease; }
        .tab-item.active { border-bottom-color: var(--ink); color: var(--ink) !important; }
        .tab-item:not(.active):hover { color: var(--ink-soft); }
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Production Operations</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Daily Shift Logs</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor plant operational shifts, production outputs, and safety handovers.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Logs
                    </button>
                    <a href="new_shift_log.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Start New Shift
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Current Active Shift</p>
                    <h3 class="text-[24px] font-semibold tracking-tight" style="color: var(--ink);"><?= htmlspecialchars($active_shift) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <?php if($active_shift !== 'None'): ?>
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="clock" class="w-3 h-3"></i>Logging in progress
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Production Output (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_output) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Cylinders filled this month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Shifts Logged</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_shifts) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Historical records verified</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Safety Alerts (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $safety_alerts > 0 ? '#963B33' : 'var(--ink)' ?>;"><?= number_format($safety_alerts) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <?php if($safety_alerts > 0): ?>
                            <span class="pill" style="background: #F8E9E7; color: #963B33;">
                                <i data-lucide="alert-triangle" class="w-3 h-3"></i>Action required
                            </span>
                        <?php else: ?>
                            <span class="pill" style="background: #EAF1E7; color: #45663F;">
                                <i data-lucide="shield-check" class="w-3 h-3"></i>Zero incidents
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Shift Handover Logs</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search shift ID or supervisor..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Shifts</option>
                                <option>Morning Shift</option>
                                <option>Evening Shift</option>
                                <option>Night Shift</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All Logs <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($shifts) ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Shift Ref</th>
                                <th class="px-3 py-3 font-medium">Date & Cycle</th>
                                <th class="px-3 py-3 font-medium">Supervisor</th>
                                <th class="px-3 py-3 font-medium text-right">Cylinders Filled</th>
                                <th class="px-3 py-3 font-medium text-center">Alerts</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($shifts as $s): ?>
                            <?php
                                $statusKey = $s['status'] ?? 'in_progress';
                                $statusObj = $statusStyles[$statusKey];
                                $isFlagged = $statusKey === 'flagged';
                                $rowColor = $isFlagged ? '#963B33' : 'var(--ink)';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($s['shift_id']) ?></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium mono" style="color: var(--ink);"><?= htmlspecialchars($s['operational_date']) ?></span>
                                        <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars(getShiftTimeFormat($s['shift_cycle'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 font-medium" style="color: var(--ink);">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-slate-200 flex items-center justify-center text-[9px] font-bold" style="color: var(--ink-soft);">
                                            <?= htmlspecialchars(mb_substr($s['supervisor'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($s['supervisor']) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($s['actual_output']) ?></td>
                                <td class="px-3 py-3.5 text-center">
                                    <?php if($s['safety_alerts'] > 0): ?>
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-sm bg-red-50 text-red-700 text-[11px] font-bold num"><?= $s['safety_alerts'] ?></span>
                                    <?php else: ?>
                                        <span class="text-[12px] text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <a href="shift_details.php?id=<?= urlencode($s['id']) ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">View Log</a>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-6 text-[13px]" style="color: var(--mute);">لا توجد ورديات مسجلة حالياً.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($shifts) ?> Logs</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
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
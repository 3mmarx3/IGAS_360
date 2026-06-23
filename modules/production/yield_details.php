<?php
$active_page = 'yield_loss_reports';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Yield & Loss Reports', 'Batch Analysis'];

$batch_id = $_GET['batch'] ?? 'BAT-8092';

$batch = [
    'id' => $batch_id,
    'product' => 'Liquid Oxygen (LOX)',
    'date' => '2026-06-23',
    'shift' => 'Shift B (14:00 - 22:00)',
    'operator' => 'Eng. Ahmed Khaled',
    'machine_id' => 'ASU-Line-1',
    'input' => 5200,
    'output' => 5120,
    'loss' => 80,
    'yield_pct' => 98.4,
    'status' => 'optimal',
    'financial_loss' => 320.00,
    'qc_status' => 'Passed'
];

$loss_breakdown = [
    ['reason' => 'System Purging & Venting', 'val' => 45, 'pct' => 56.25],
    ['reason' => 'Pressure Stabilization', 'val' => 18, 'pct' => 22.50],
    ['reason' => 'Leakage Detection', 'val' => 12, 'pct' => 15.00],
    ['reason' => 'Calibration Waste', 'val' => 5, 'pct' => 6.25],
];

$process_logs = [
    ['time' => '14:15', 'event' => 'Batch Initialization', 'user' => 'System', 'status' => 'info'],
    ['time' => '14:45', 'event' => 'Pressure Stabilization Phase', 'user' => 'Auto-Controller', 'status' => 'warning'],
    ['time' => '16:20', 'event' => 'Mid-cycle QC Sample Taken', 'user' => 'Lab Team', 'status' => 'info'],
    ['time' => '18:10', 'event' => 'System Purging Executed', 'user' => 'Ahmed Khaled', 'status' => 'warning'],
    ['time' => '21:30', 'event' => 'Batch Completed & Logged', 'user' => 'Ahmed Khaled', 'status' => 'success'],
];

$statusStyles = [
    'optimal'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Optimal Yield'],
    'acceptable' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Acceptable Variance'],
    'high_loss'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'High Loss Alert'],
];

$logStyles = [
    'info'    => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'warning' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E'],
    'success' => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
];

$bs = $statusStyles[$batch['status']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch <?= htmlspecialchars($batch['id']) ?> | I-GAS Enterprise</title>
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

            <a href="yield_loss_reports.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Yield Reports
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="activity" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none mono" style="color: var(--ink);"><?= htmlspecialchars($batch['id']) ?></h2>
                            <span class="pill" style="background: <?= $bs['bg'] ?>; color: <?= $bs['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $bs['dot'] ?>;"></span><?= $bs['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px]" style="color: var(--mute-soft);"><?= htmlspecialchars($batch['product']) ?> · <?= htmlspecialchars($batch['date']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export PDF
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="flag" class="w-4 h-4"></i>Flag Batch
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Yield Efficiency</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $batch['yield_pct'] ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $batch['yield_pct'] ?>%;"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Input Volume</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($batch['input']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Liters</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Total raw material injected</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Net Output</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($batch['output']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Liters</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Final usable product volume</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Loss Volume</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($batch['loss']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Liters</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            Variance recorded
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 flex flex-col gap-5">
                    
                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Loss Categorization Breakdown</h3>
                        </div>
                        <div class="p-6 pb-2">
                            <?php foreach ($loss_breakdown as $item): ?>
                            <div class="mb-6">
                                <div class="flex justify-between items-end mb-2">
                                    <div>
                                        <p class="text-[14px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($item['reason']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[16px] font-semibold num" style="color: var(--ink);"><?= $item['val'] ?> L</span>
                                        <span class="text-[12px] mono ml-2" style="color: var(--mute);">(<?= $item['pct'] ?>%)</span>
                                    </div>
                                </div>
                                <div class="meter-bar h-2.5 w-full overflow-hidden rounded-sm">
                                    <div class="meter-fill h-full" style="width: <?= $item['pct'] ?>%; background: #9A7B2E;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Process Timeline</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                View Full Logs<i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                        <th class="pl-6 py-3 font-medium">Time</th>
                                        <th class="px-3 py-3 font-medium">Event Description</th>
                                        <th class="pr-6 py-3 font-medium text-right">Operator / System</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php foreach ($process_logs as $log): ?>
                                    <?php $ls = $logStyles[$log['status']]; ?>
                                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                        <td class="pl-6 py-3.5 text-[12.5px] mono font-medium" style="color: var(--ink);"><?= htmlspecialchars($log['time']) ?></td>
                                        <td class="px-3 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full" style="background: <?= $ls['fg'] ?>;"></span>
                                                <span style="color: var(--ink);"><?= htmlspecialchars($log['event']) ?></span>
                                            </div>
                                        </td>
                                        <td class="pr-6 py-3.5 text-right text-[12.5px]" style="color: var(--mute);"><?= htmlspecialchars($log['user']) ?></td>
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
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Batch Properties</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Product Gas</p>
                                <p class="info-value"><?= htmlspecialchars($batch['product']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Production Shift</p>
                                <p class="info-value text-[13px]"><?= htmlspecialchars($batch['shift']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Lead Operator</p>
                                <p class="info-value font-medium text-[13px]"><?= htmlspecialchars($batch['operator']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Machine / Line Unit</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($batch['machine_id']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Financial & QC Impact</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Est. Financial Loss</p>
                                <p class="info-value num font-semibold" style="color: #963B33;"><?= number_format($batch['financial_loss'], 2) ?> SAR</p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Quality Control Status</p>
                                <p class="info-value flex items-center gap-1.5" style="color: #45663F;">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i><?= htmlspecialchars($batch['qc_status']) ?>
                                </p>
                            </div>
                            <div class="info-row border-none pb-4">
                                <button class="w-full btn-secondary py-2 mt-2 rounded-sm text-[12.5px] font-medium bg-white">
                                    Send to Finance Dept.
                                </button>
                            </div>
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
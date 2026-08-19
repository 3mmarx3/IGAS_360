<?php
session_start();
require_once '../../config/db.php';

$active_page = 'production_reports';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Monthly & Yearly Production'];

// Dummy data for visualization to prevent DB missing table errors
$production_data = [
    ['month' => 'August 2026', 'gas' => 'Oxygen', 'cylinders' => 1250, 'bulk_liters' => 45000, 'target_pct' => 95, 'status' => 'on_track'],
    ['month' => 'August 2026', 'gas' => 'Nitrogen', 'cylinders' => 840, 'bulk_liters' => 22000, 'target_pct' => 88, 'status' => 'warning'],
    ['month' => 'July 2026', 'gas' => 'Oxygen', 'cylinders' => 1100, 'bulk_liters' => 41000, 'target_pct' => 102, 'status' => 'exceeded'],
    ['month' => 'July 2026', 'gas' => 'Nitrogen', 'cylinders' => 920, 'bulk_liters' => 25000, 'target_pct' => 97, 'status' => 'on_track'],
    ['month' => 'June 2026', 'gas' => 'Oxygen', 'cylinders' => 1050, 'bulk_liters' => 39000, 'target_pct' => 100, 'status' => 'on_track'],
    ['month' => 'June 2026', 'gas' => 'Argon', 'cylinders' => 450, 'bulk_liters' => 12000, 'target_pct' => 75, 'status' => 'underperforming'],
];

$kpi_stats = [
    'monthly_cylinders' => 2090,
    'monthly_bulk'      => 67000,
    'ytd_cylinders'     => 18540,
    'ytd_bulk'          => 542000
];

$statusStyles = [
    'on_track'        => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'On Track'],
    'exceeded'        => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'Exceeded Target'],
    'warning'         => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Below Target'],
    'underperforming' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Underperforming']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly & Yearly Production | I-GAS Enterprise</title>
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

        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .tab-item { cursor: pointer; padding-bottom: 12px; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-item.active { border-bottom-color: var(--ink); color: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Report Range: Year 2026</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Performance Analytics</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Monthly & Yearly Production</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Analyze plant output, cylinder filling metrics, and bulk gas processing volumes.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="filter" class="w-4 h-4"></i>Filters
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Report
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Monthly Cylinders Filled</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['monthly_cylinders']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="trending-up" class="w-3 h-3"></i>+5.2% vs Last Month
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Monthly Bulk (Liters)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['monthly_bulk']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="minus" class="w-3 h-3"></i>-1.5% vs Last Month
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">YTD Cylinders (2026)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['ytd_cylinders']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Total units processed this year</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">YTD Bulk Processed</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['ytd_bulk']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Total liters liquefied this year</span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Production Logs</h3>
                        <div class="flex items-center gap-3">
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>Year 2026</option>
                                <option>Year 2025</option>
                            </select>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Gas Types</option>
                                <option>Oxygen</option>
                                <option>Nitrogen</option>
                                <option>Argon</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Monthly Breakdown</span>
                        <span class="tab-item">Quarterly Summary</span>
                        <span class="tab-item">Gas Type Analysis</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">Period</th>
                                <th class="px-3 py-3 font-medium">Gas Classification</th>
                                <th class="px-3 py-3 font-medium text-right">Cylinders Filled</th>
                                <th class="px-3 py-3 font-medium text-right">Bulk Volume (L)</th>
                                <th class="px-3 py-3 font-medium">Target Achievement</th>
                                <th class="px-3 py-3 font-medium">Performance Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($production_data as $row): ?>
                            <?php
                                $statusObj = $statusStyles[$row['status']];
                                $barColor = '#45663F';
                                if ($row['target_pct'] < 80) $barColor = '#963B33';
                                elseif ($row['target_pct'] < 95) $barColor = '#9A7B2E';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($row['month']) ?></td>
                                <td class="px-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($row['gas']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($row['cylinders']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($row['bulk_liters']) ?></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 h-2 rounded-full overflow-hidden" style="background: var(--line-soft);">
                                            <div class="h-full rounded-full" style="width: <?= min($row['target_pct'], 100) ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                        <span class="text-[12px] mono num font-medium" style="color: var(--ink);"><?= $row['target_pct'] ?>%</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right">
                                    <button class="transition-colors bg-transparent border-none cursor-pointer" style="color: var(--mute);" title="View Logs" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing latest 6 production records</span>
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
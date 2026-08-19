<?php
session_start();
require_once '../../config/db.php';

$active_page = 'cost_profit';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Financials', 'Cost & Profit Analysis'];

$financial_data = [
    ['gas' => 'Liquid Oxygen (LOX)', 'volume' => '120,000 L', 'revenue' => 450000, 'cost' => 280000, 'profit' => 170000, 'margin' => 37.7, 'status' => 'high_profit'],
    ['gas' => 'Liquid Nitrogen (LIN)', 'volume' => '85,000 L', 'revenue' => 210000, 'cost' => 165000, 'profit' => 45000, 'margin' => 21.4, 'status' => 'acceptable'],
    ['gas' => 'Liquid Argon (LAR)', 'volume' => '30,000 L', 'revenue' => 180000, 'cost' => 95000, 'profit' => 85000, 'margin' => 47.2, 'status' => 'high_profit'],
    ['gas' => 'LPG Bulk', 'volume' => '200,000 L', 'revenue' => 600000, 'cost' => 520000, 'profit' => 80000, 'margin' => 13.3, 'status' => 'low_margin'],
    ['gas' => 'Acetylene Cylinders', 'volume' => '4,500 Units', 'revenue' => 135000, 'cost' => 90000, 'profit' => 45000, 'margin' => 33.3, 'status' => 'acceptable']
];

$kpi_stats = [
    'total_revenue' => 1575000,
    'total_cost'    => 1150000,
    'net_profit'    => 425000,
    'avg_margin'    => 26.9
];

$statusStyles = [
    'high_profit' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'High Profit'],
    'acceptable'  => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'Acceptable'],
    'low_margin'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Low Margin'],
    'loss'        => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Loss / Critical']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost & Profit Analysis | I-GAS Enterprise</title>
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
                <span class="status-dot" style="background: #5C8A5C;"></span>Financials Synced
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Reporting Currency — SAR</span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Period: Q3 2026</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Financial Intelligence</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Cost & Profit Analysis</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Evaluate production costs, revenue streams, and overall profitability margins per gas category.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4"></i>Select Period
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Export Financials
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #2A6B8A;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Gross Revenue</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['total_revenue']) ?> <span class="text-[14px] text-[var(--mute)]">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="trending-up" class="w-3 h-3"></i>+12.4% vs Q2
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #963B33;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Production Costs</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['total_cost']) ?> <span class="text-[14px] text-[var(--mute)]">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="trending-down" class="w-3 h-3"></i>+5.1% vs Q2
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5 border-l-4" style="border-left-color: #45663F;">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Net Profit</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['net_profit']) ?> <span class="text-[14px] text-[var(--mute)]">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="check-circle-2" class="w-3 h-3"></i>Stable Growth
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Average Profit Margin</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($kpi_stats['avg_margin'], 1) ?>%</h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Weighted average across all products</span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Profitability by Gas Classification</h3>
                        <div class="flex items-center gap-3">
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>Current Quarter (Q3)</option>
                                <option>Previous Quarter (Q2)</option>
                                <option>Year to Date (YTD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Category Overview</span>
                        <span class="tab-item">Cost Breakdown</span>
                        <span class="tab-item">Margin Trends</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">Product / Classification</th>
                                <th class="px-3 py-3 font-medium">Volume Sold</th>
                                <th class="px-3 py-3 font-medium text-right">Revenue (SAR)</th>
                                <th class="px-3 py-3 font-medium text-right">Total Cost (SAR)</th>
                                <th class="px-3 py-3 font-medium text-right">Net Profit (SAR)</th>
                                <th class="px-3 py-3 font-medium">Profit Margin</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($financial_data as $row): ?>
                            <?php
                                $statusObj = $statusStyles[$row['status']];
                                $barColor = '#45663F';
                                if ($row['margin'] < 20) $barColor = '#9A7B2E';
                                if ($row['margin'] < 10) $barColor = '#963B33';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-3 py-4 font-medium" style="color: var(--ink);"><?= htmlspecialchars($row['gas']) ?></td>
                                <td class="px-3 py-4 mono text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($row['volume']) ?></td>
                                <td class="px-3 py-4 text-right font-medium num" style="color: var(--ink);"><?= number_format($row['revenue']) ?></td>
                                <td class="px-3 py-4 text-right font-medium num" style="color: #963B33;"><?= number_format($row['cost']) ?></td>
                                <td class="px-3 py-4 text-right font-semibold num" style="color: #45663F;"><?= number_format($row['profit']) ?></td>
                                <td class="px-3 py-4 w-32">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="text-[12px] mono num font-medium" style="color: var(--ink);"><?= number_format($row['margin'], 1) ?>%</div>
                                        <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--line-soft);">
                                            <div class="h-full rounded-full" style="width: <?= min($row['margin'], 100) ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-4 text-right">
                                    <button class="transition-colors bg-transparent border-none cursor-pointer" style="color: var(--mute);" title="View Breakdown" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Displaying primary revenue streams</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors opacity-50 cursor-not-allowed" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors opacity-50 cursor-not-allowed" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
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
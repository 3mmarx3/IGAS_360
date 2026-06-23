<?php
$active_page = 'analytics';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Insights', 'Analytics & Performance'];

$metrics = [
    'revenue_mtd' => 1450200,
    'revenue_growth' => 12.5,
    'production_vol' => 84250,
    'production_growth' => 4.2,
    'avg_yield' => 97.8,
    'fleet_utilization' => 91,
];

$top_products = [
    ['rank' => 1, 'name' => 'Liquid Oxygen (LOX)', 'revenue' => 425000, 'growth' => 8.4, 'trend' => 'up'],
    ['rank' => 2, 'name' => 'Argon/CO₂ Mix', 'revenue' => 210000, 'growth' => 15.2, 'trend' => 'up'],
    ['rank' => 3, 'name' => 'High Purity Nitrogen', 'revenue' => 185500, 'growth' => -2.1, 'trend' => 'down'],
    ['rank' => 4, 'name' => 'Acetylene (C₂H₂)', 'revenue' => 150200, 'growth' => 5.5, 'trend' => 'up'],
    ['rank' => 5, 'name' => 'Medical Air', 'revenue' => 95000, 'growth' => 1.2, 'trend' => 'up'],
];

$regional_sales = [
    ['region' => 'Eastern Province (Jubail/Dammam)', 'pct' => 45, 'value' => 652590],
    ['region' => 'Western Province (Jeddah/Yanbu)', 'pct' => 35, 'value' => 507570],
    ['region' => 'Central Region (Riyadh)', 'pct' => 15, 'value' => 217530],
    ['region' => 'Northern/Southern Borders', 'pct' => 5, 'value' => 72510],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Performance | I-GAS Enterprise</title>
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Enterprise Insights</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Analytics &amp; Performance</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Macro-level data intelligence covering revenue streams, production output, and operational efficiency.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4"></i>This Month (MTD)
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Dashboard
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Gross Revenue (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($metrics['revenue_mtd']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="trending-up" class="w-3 h-3"></i>+<?= $metrics['revenue_growth'] ?>%
                        </span>
                        <span class="ml-2" style="color: var(--mute);">vs last month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Production Volume</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($metrics['production_vol']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Liters</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="trending-up" class="w-3 h-3"></i>+<?= $metrics['production_growth'] ?>%
                        </span>
                        <span class="ml-2" style="color: var(--mute);">vs last month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Plant Yield Efficiency</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $metrics['avg_yield'] ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $metrics['avg_yield'] ?>%; background: #45663F;"></div>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fleet Utilization</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $metrics['fleet_utilization'] ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $metrics['fleet_utilization'] ?>%; background: #2A6B8A;"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Top Performing Products</h3>
                        <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                    <th class="pl-6 py-3 font-medium">Rank</th>
                                    <th class="px-3 py-3 font-medium">Product / Gas Component</th>
                                    <th class="px-3 py-3 font-medium text-right">Generated Revenue</th>
                                    <th class="px-3 py-3 font-medium text-right">MoM Growth</th>
                                    <th class="pr-6 py-3 font-medium text-right">Trend</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($top_products as $p): ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 py-3.5 font-medium num" style="color: var(--mute);">#<?= $p['rank'] ?></td>
                                    <td class="px-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($p['name']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-semibold num" style="color: var(--ink);">SAR <?= number_format($p['revenue']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $p['trend'] === 'up' ? '#45663F' : '#963B33' ?>;">
                                        <?= $p['trend'] === 'up' ? '+' : '' ?><?= $p['growth'] ?>%
                                    </td>
                                    <td class="pr-6 py-3.5 text-right flex justify-end">
                                        <?php if($p['trend'] === 'up'): ?>
                                            <i data-lucide="trending-up" class="w-4 h-4" style="color: #45663F;"></i>
                                        <?php else: ?>
                                            <i data-lucide="trending-down" class="w-4 h-4" style="color: #963B33;"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 border-t" style="border-color: var(--line-soft); background: var(--paper-dim);">
                        <a href="#" class="text-[12px] font-medium" style="color: var(--ink); text-decoration: none;">View Full Product Ledger →</a>
                    </div>
                </div>

                <div class="card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Revenue Distribution by Region</h3>
                    </div>
                    <div class="p-6 flex-1 flex flex-col justify-center gap-6">
                        <?php foreach ($regional_sales as $rs): ?>
                        <div>
                            <div class="flex justify-between items-end mb-2">
                                <div>
                                    <p class="text-[13px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($rs['region']) ?></p>
                                    <p class="text-[11.5px] mono mt-0.5" style="color: var(--mute);">SAR <?= number_format($rs['value']) ?></p>
                                </div>
                                <span class="text-[14px] font-semibold num" style="color: var(--ink);"><?= $rs['pct'] ?>%</span>
                            </div>
                            <div class="meter-bar h-2 w-full overflow-hidden rounded-sm" style="background: var(--line-soft);">
                                <div class="meter-fill h-full" style="width: <?= $rs['pct'] ?>%; background: var(--ink);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
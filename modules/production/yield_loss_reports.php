<?php
$active_page = 'yield_loss_reports';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Yield & Loss Reports'];

$reports = [
    ['batch' => 'BAT-8092', 'date' => '2026-06-23', 'product' => 'Liquid Oxygen (LOX)', 'input' => 5200, 'output' => 5120, 'loss' => 80, 'yield_pct' => 98.4, 'status' => 'optimal'],
    ['batch' => 'BAT-8091', 'date' => '2026-06-23', 'product' => 'Argon/CO₂ Mix', 'input' => 1500, 'output' => 1485, 'loss' => 15, 'yield_pct' => 99.0, 'status' => 'optimal'],
    ['batch' => 'BAT-8090', 'date' => '2026-06-22', 'product' => 'High Purity Nitrogen', 'input' => 8000, 'output' => 7600, 'loss' => 400, 'yield_pct' => 95.0, 'status' => 'high_loss'],
    ['batch' => 'BAT-8089', 'date' => '2026-06-22', 'product' => 'Acetylene (C₂H₂)', 'input' => 1200, 'output' => 1170, 'loss' => 30, 'yield_pct' => 97.5, 'status' => 'acceptable'],
    ['batch' => 'BAT-8088', 'date' => '2026-06-21', 'product' => 'Helium (He)', 'input' => 400, 'output' => 395, 'loss' => 5, 'yield_pct' => 98.7, 'status' => 'optimal'],
    ['batch' => 'BAT-8087', 'date' => '2026-06-21', 'product' => 'Liquid Oxygen (LOX)', 'input' => 6000, 'output' => 5750, 'loss' => 250, 'yield_pct' => 95.8, 'status' => 'high_loss'],
];

$avg_yield       = 97.4;
$total_lost_vol  = 4520;
$financial_loss  = 18450;
$critical_alerts = 2;

$statusStyles = [
    'optimal'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Optimal Yield'],
    'acceptable' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Acceptable Variance'],
    'high_loss'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'High Loss Alert'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yield & Loss Reports | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    

<link rel="stylesheet" href="../../assets/css/main.css">
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Production Analytics</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Yield & Loss Reports</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Analyze production efficiency, track material loss, and monitor financial impact per batch.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4"></i>Date Filter
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Analysis
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Average Yield Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $avg_yield ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $avg_yield ?>%; background: #45663F;"></div>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Volume Lost (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_lost_vol) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">Liters</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="trending-down" class="w-3 h-3"></i>2.1% above target
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Financial Impact (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($financial_loss) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Estimated cost of waste</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Critical Loss Alerts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $critical_alerts > 0 ? '#963B33' : 'var(--ink)' ?>;"><?= $critical_alerts ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="alert-octagon" class="w-3 h-3"></i>Batches under 96% yield
                        </span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Batch Performance Registry</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search batch ID or product..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Statuses</option>
                                <option>Optimal</option>
                                <option>Acceptable</option>
                                <option>High Loss</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Recent Batches <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($reports) ?></span></span>
                        <span class="tab-item text-red-700">High Loss Alerts <span class="num text-[11px]" style="color: #963B33;"><?= $critical_alerts ?></span></span>
                        <span class="tab-item">Monthly Summary</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Batch ID</th>
                                <th class="px-3 py-3 font-medium">Product / Gas</th>
                                <th class="px-3 py-3 font-medium text-right">Input Vol.</th>
                                <th class="px-3 py-3 font-medium text-right">Output Vol.</th>
                                <th class="px-3 py-3 font-medium text-right">Lost Vol.</th>
                                <th class="px-3 py-3 font-medium text-center">Yield %</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($reports as $r): ?>
                            <?php
                                $statusObj = $statusStyles[$r['status']];
                                $isHighLoss = $r['status'] === 'high_loss';
                                $yieldColor = $isHighLoss ? '#963B33' : ($r['status'] === 'acceptable' ? '#7A5E1E' : '#45663F');
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium num" style="color: var(--ink);"><?= htmlspecialchars($r['batch']) ?></span>
                                        <span class="text-[11px] mono" style="color: var(--mute);"><?= htmlspecialchars($r['date']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($r['product']) ?></td>
                                <td class="px-3 py-3.5 text-right num" style="color: var(--mute);"><?= number_format($r['input']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($r['output']) ?></td>
                                <td class="px-3 py-3.5 text-right font-semibold num" style="color: <?= $isHighLoss ? '#963B33' : 'var(--mute)' ?>;">
                                    <?= number_format($r['loss']) ?>
                                </td>
                                <td class="px-3 py-3.5 text-center font-bold num" style="color: <?= $yieldColor ?>;">
                                    <?= number_format($r['yield_pct'], 1) ?>%
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <a href="yield_details.php?batch=<?= $r['batch'] ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">View Report</a>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($reports) ?> of 45 Batches</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">2</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">3</button>
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
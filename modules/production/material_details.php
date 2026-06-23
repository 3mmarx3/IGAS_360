<?php
$active_page = 'raw_materials';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Raw Materials', 'Material Details'];

$material_id = $_GET['id'] ?? 'RM-1001';

$material = [
    'id' => $material_id,
    'name' => 'Liquid Oxygen (LOX)',
    'category' => 'Bulk Gas',
    'unit' => 'Liters',
    'stock' => 45000,
    'capacity' => 60000,
    'threshold' => 10000,
    'unit_cost' => 2.50,
    'total_value' => 112500,
    'supplier_id' => 'SUP-5001',
    'supplier_name' => 'Gulf Industrial Gases',
    'lead_time' => '2 Days',
    'location' => 'Cryo Tank Farm - Tank A1',
    'status' => 'optimal',
    'last_updated' => '2026-06-23 08:15',
    'avg_consumption' => 1200 
];

$transactions = [
    ['id' => 'TRX-9921', 'date' => '2026-06-23 08:15', 'type' => 'out', 'qty' => 500, 'ref' => 'PRD-Batch-4480', 'user' => 'System'],
    ['id' => 'TRX-9905', 'date' => '2026-06-22 14:30', 'type' => 'out', 'qty' => 1200, 'ref' => 'PRD-Batch-4479', 'user' => 'Ahmad S.'],
    ['id' => 'TRX-9882', 'date' => '2026-06-20 09:00', 'type' => 'in', 'qty' => 20000, 'ref' => 'PO-8842', 'user' => 'Receiving Dept'],
    ['id' => 'TRX-9850', 'date' => '2026-06-19 11:45', 'type' => 'out', 'qty' => 850, 'ref' => 'PRD-Batch-4475', 'user' => 'System'],
    ['id' => 'TRX-9811', 'date' => '2026-06-18 16:20', 'type' => 'out', 'qty' => 1100, 'ref' => 'PRD-Batch-4471', 'user' => 'Faisal O.'],
];

$statusStyles = [
    'optimal' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'In Stock / Optimal'],
    'low'     => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Low Stock Alert'],
    'out'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Out of Stock'],
];

$typeStyles = [
    'in'  => ['color' => '#45663F', 'sign' => '+'],
    'out' => ['color' => '#963B33', 'sign' => '-'],
];

$ms = $statusStyles[$material['status']];
$stock_percentage = ($material['stock'] / $material['capacity']) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($material['name']) ?> | I-GAS Enterprise</title>
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

            <a href="raw_materials.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Inventory Registry
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="package" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($material['name']) ?></h2>
                            <span class="pill" style="background: <?= $ms['bg'] ?>; color: <?= $ms['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $ms['dot'] ?>;"></span><?= $ms['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($material['id']) ?> · <?= htmlspecialchars($material['category']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="arrow-down-to-line" class="w-4 h-4"></i>Adjust Stock
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>Reorder Material
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Current Stock Level</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($material['stock']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);"><?= htmlspecialchars($material['unit']) ?></span></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $stock_percentage ?>%;"></div>
                    </div>
                    <p class="text-[11px] mono mt-2 text-right" style="color: var(--mute);"><?= number_format($material['capacity']) ?> Max Cap</p>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Inventory Value</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($material['total_value']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Based on SAR <?= number_format($material['unit_cost'], 2) ?> / <?= htmlspecialchars($material['unit']) ?></span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Safety Threshold</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($material['threshold']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);"><?= htmlspecialchars($material['unit']) ?></span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="bell-ring" class="w-3 h-3"></i>Alert Active
                        </span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Est. Daily Consumption</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($material['avg_consumption']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);"><?= htmlspecialchars($material['unit']) ?>/d</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);"><?= round($material['stock'] / $material['avg_consumption']) ?> days of stock remaining</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Stock Movement History</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                Export Log<i data-lucide="download" class="w-3.5 h-3.5 ml-1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                    <th class="pl-6 pr-3 py-3 font-medium">Transaction ID</th>
                                    <th class="px-3 py-3 font-medium">Date & Time</th>
                                    <th class="px-3 py-3 font-medium text-right">Quantity</th>
                                    <th class="px-3 py-3 font-medium">Reference / Source</th>
                                    <th class="pr-6 py-3 font-medium text-right">Logged By</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($transactions as $t): ?>
                                <?php $ts = $typeStyles[$t['type']]; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($t['id']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($t['date']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $ts['color'] ?>;">
                                        <?= $ts['sign'] ?> <?= number_format($t['qty']) ?>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--ink);"><?= htmlspecialchars($t['ref']) ?></td>
                                    <td class="pr-6 py-3.5 text-right font-medium" style="color: var(--mute);"><?= htmlspecialchars($t['user']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                        <span class="text-[12px] mono" style="color: var(--mute);">Last updated: <?= htmlspecialchars($material['last_updated']) ?></span>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    
                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Material Properties</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Category Classification</p>
                                <p class="info-value"><?= htmlspecialchars($material['category']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Unit of Measure</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($material['unit']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Max Storage Capacity</p>
                                <p class="info-value num"><?= number_format($material['capacity']) ?> <span class="text-[11px] text-gray-500 font-normal"><?= htmlspecialchars($material['unit']) ?></span></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Storage Location / Zone</p>
                                <p class="info-value" style="font-size:13px;"><?= htmlspecialchars($material['location']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Procurement Data</h3>
                            <a href="supplier_profile.php?id=<?= $material['supplier_id'] ?>" class="text-[12px] font-medium" style="color: var(--ink); text-decoration: underline;">View Supplier</a>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Primary Vendor</p>
                                <p class="info-value font-medium text-[13px]"><?= htmlspecialchars($material['supplier_name']) ?></p>
                                <p class="text-[11px] mono mt-1" style="color: var(--mute);"><?= htmlspecialchars($material['supplier_id']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Standard Lead Time</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($material['lead_time']) ?></p>
                            </div>
                            <div class="info-row border-none pb-4">
                                <button class="w-full btn-secondary py-2 mt-2 rounded-sm text-[12.5px] font-medium bg-white">
                                    Generate Purchase Order
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
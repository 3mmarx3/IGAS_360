<?php
$active_page = 'gases_mixtures';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Gases & Mixtures', 'Product Details'];

$gas_id = $_GET['id'] ?? 'GAS-002';

$gas = [
    'id' => $gas_id,
    'name' => 'Argon/CO₂ Mix',
    'type' => 'Standard Mix',
    'formula' => 'Ar 80% / CO₂ 20%',
    'purity' => 'Industrial Standard',
    'stock' => 1200,
    'unit' => 'Cylinders',
    'capacity' => 1500,
    'status' => 'certified',
    'un_number' => 'UN 1956',
    'hazard_class' => '2.2 (Non-Flammable Gas)',
    'cas_number' => 'Mixture',
    'cylinder_color' => 'Green Body / Grey Shoulder',
    'shelf_life' => '36 Months'
];

$composition = [
    ['component' => 'Argon (Ar)', 'percentage' => 80, 'tolerance' => '±0.5%', 'grade' => '99.99%'],
    ['component' => 'Carbon Dioxide (CO₂)', 'percentage' => 20, 'tolerance' => '±0.5%', 'grade' => '99.9%'],
];

$recent_batches = [
    ['id' => 'BAT-8092', 'date' => '2026-06-23', 'qty' => 150, 'qc_status' => 'certified', 'inspector' => 'Tariq Nabil'],
    ['id' => 'BAT-8088', 'date' => '2026-06-22', 'qty' => 200, 'qc_status' => 'certified', 'inspector' => 'Faisal Omar'],
    ['id' => 'BAT-8075', 'date' => '2026-06-20', 'qty' => 180, 'qc_status' => 'testing', 'inspector' => 'Lab Team'],
    ['id' => 'BAT-8041', 'date' => '2026-06-18', 'qty' => 150, 'qc_status' => 'certified', 'inspector' => 'Tariq Nabil'],
    ['id' => 'BAT-8012', 'date' => '2026-06-15', 'qty' => 120, 'qc_status' => 'rejected', 'inspector' => 'Lab Team'],
];

$statusStyles = [
    'certified' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'QC Certified'],
    'testing'   => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In QC Lab'],
    'rejected'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'QC Rejected'],
    'low_stock' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Low Stock'],
];

$gs = $statusStyles[$gas['status']];
$stock_pct = ($gas['stock'] / $gas['capacity']) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($gas['name']) ?> | I-GAS Enterprise</title>
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

            <a href="gases_mixtures.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Formulation Registry
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="flask-conical" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($gas['name']) ?></h2>
                            <span class="pill" style="background: <?= $gs['bg'] ?>; color: <?= $gs['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $gs['dot'] ?>;"></span><?= $gs['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($gas['id']) ?> · <?= htmlspecialchars($gas['type']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Tech Sheet (SDS)
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>Edit Formulation
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Available Stock</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($gas['stock']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);"><?= htmlspecialchars($gas['unit']) ?></span></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $stock_pct ?>%;"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Purity / Grade</p>
                    <h3 class="text-[20px] font-semibold tracking-tight" style="color: var(--ink);"><?= htmlspecialchars($gas['purity']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Certified against ISO standards</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Chemical Formula</p>
                    <h3 class="text-[20px] font-semibold tracking-tight mono" style="color: var(--ink);"><?= htmlspecialchars($gas['formula']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="info" class="w-3 h-3"></i>Mixed Gas Profile
                        </span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">QC Compliance Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">99.2%</h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="check-circle" class="w-3 h-3"></i>High passing rate
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 flex flex-col gap-5">
                    
                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Mix Composition Breakdown</h3>
                        </div>
                        <div class="p-6 pb-2">
                            <?php foreach ($composition as $comp): ?>
                            <div class="mb-6">
                                <div class="flex justify-between items-end mb-2">
                                    <div>
                                        <p class="text-[14px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($comp['component']) ?></p>
                                        <p class="text-[12px] mono mt-0.5" style="color: var(--mute);">Grade: <?= htmlspecialchars($comp['grade']) ?> | Tol: <?= htmlspecialchars($comp['tolerance']) ?></p>
                                    </div>
                                    <span class="text-[16px] font-semibold num" style="color: var(--ink);"><?= $comp['percentage'] ?>%</span>
                                </div>
                                <div class="meter-bar h-2.5 w-full overflow-hidden rounded-sm">
                                    <div class="meter-fill h-full" style="width: <?= $comp['percentage'] ?>%;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card rounded-md flex flex-col overflow-hidden">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Production Batches</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                View All Batches<i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                        <th class="pl-6 py-3 font-medium">Batch ID</th>
                                        <th class="px-3 py-3 font-medium">Production Date</th>
                                        <th class="px-3 py-3 font-medium text-right">Volume / Qty</th>
                                        <th class="px-3 py-3 font-medium">QC Status</th>
                                        <th class="pr-6 py-3 font-medium text-right">QC Inspector</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php foreach ($recent_batches as $b): ?>
                                    <?php $bs = $statusStyles[$b['qc_status']]; ?>
                                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                        <td class="pl-6 py-3.5 font-medium num" style="color: var(--ink);"><?= htmlspecialchars($b['id']) ?></td>
                                        <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($b['date']) ?></td>
                                        <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= htmlspecialchars($b['qty']) ?></td>
                                        <td class="px-3 py-3.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background: <?= $bs['bg'] ?>; color: <?= $bs['fg'] ?>;">
                                                <?= htmlspecialchars($bs['label']) ?>
                                            </span>
                                        </td>
                                        <td class="pr-6 py-3.5 text-right text-[12.5px]" style="color: var(--mute);"><?= htmlspecialchars($b['inspector']) ?></td>
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
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Chemical Properties</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Product Category</p>
                                <p class="info-value"><?= htmlspecialchars($gas['type']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">CAS Number</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($gas['cas_number']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">UN Identification</p>
                                <p class="info-value mono" style="font-size:12.5px; font-weight: 600; color: var(--ink);"><?= htmlspecialchars($gas['un_number']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Hazard Classification</p>
                                <p class="info-value flex items-center gap-2" style="font-size:13px;">
                                    <i data-lucide="shield-alert" class="w-4 h-4" style="color: var(--mute);"></i>
                                    <?= htmlspecialchars($gas['hazard_class']) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Storage & Handling</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Cylinder Color Coding</p>
                                <p class="info-value" style="font-size:13px;"><?= htmlspecialchars($gas['cylinder_color']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Max Shelf Life</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($gas['shelf_life']) ?></p>
                            </div>
                            <div class="info-row border-none pb-4">
                                <button class="w-full btn-secondary py-2 mt-2 rounded-sm text-[12.5px] font-medium bg-white">
                                    Print GHS Labels
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
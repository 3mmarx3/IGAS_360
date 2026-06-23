<?php
$active_page = 'cylinders_inventory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Cylinders Inventory', 'Cylinder Specs'];

$sku = $_GET['sku'] ?? 'CYL-O2-50L';

$cylinder = [
    'sku' => $sku,
    'gas' => 'Oxygen (O₂)',
    'size' => '50L',
    'total' => 5400,
    'plant' => 1200,
    'clients' => 4150,
    'maint' => 50,
    'status' => 'optimal',
    'working_pressure' => '200 Bar',
    'test_pressure' => '300 Bar',
    'material' => 'Seamless Steel (34CrMo4)',
    'valve_type' => 'CGA 540',
    'color_code' => 'White Shoulder / Black Body',
    'last_hydro' => '2023-05-12',
    'next_hydro' => '2028-05-12'
];

$logs = [
    ['id' => 'TRX-8102', 'date' => '2026-06-23 09:15', 'event' => 'Dispatch', 'qty' => 150, 'ref' => 'SABIC Petrochemicals (ORD-7742)', 'user' => 'Dispatch Team'],
    ['id' => 'TRX-8095', 'date' => '2026-06-22 16:45', 'event' => 'Return (Empty)', 'qty' => 120, 'ref' => 'Air Product Co.', 'user' => 'Gate Security'],
    ['id' => 'TRX-8077', 'date' => '2026-06-21 11:30', 'event' => 'Refill', 'qty' => 300, 'ref' => 'Production Line 1', 'user' => 'System'],
    ['id' => 'TRX-8050', 'date' => '2026-06-20 08:00', 'event' => 'Maintenance', 'qty' => 15, 'ref' => 'Sent for Hydro-testing', 'user' => 'Maint. Dept'],
    ['id' => 'TRX-8012', 'date' => '2026-06-18 14:20', 'event' => 'Return (Empty)', 'qty' => 80, 'ref' => 'National Contracting', 'user' => 'Gate Security'],
];

$statusStyles = [
    'optimal' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Healthy Inventory'],
    'low'     => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Low at Plant'],
];

$eventStyles = [
    'Dispatch'       => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'Return (Empty)' => ['bg' => '#F2F1EF', 'fg' => '#5C5A56'],
    'Refill'         => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
    'Maintenance'    => ['bg' => '#F8E9E7', 'fg' => '#963B33'],
];

$cs = $statusStyles[$cylinder['status']];
$plant_pct = ($cylinder['plant'] / $cylinder['total']) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cylinder['sku']) ?> | I-GAS Enterprise</title>
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

            <a href="cylinders_inventory.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Cylinders Inventory
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="cylinder" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none mono" style="color: var(--ink);"><?= htmlspecialchars($cylinder['sku']) ?></h2>
                            <span class="pill" style="background: <?= $cs['bg'] ?>; color: <?= $cs['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $cs['dot'] ?>;"></span><?= $cs['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px]" style="color: var(--mute-soft);"><?= htmlspecialchars($cylinder['gas']) ?> · <?= htmlspecialchars($cylinder['size']) ?> Cylinders</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Data
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="barcode" class="w-4 h-4"></i>Scan Movement
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Assets Owned</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($cylinder['total']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Units in company registry</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">In Plant (Ready/Empty)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($cylinder['plant']) ?></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $plant_pct ?>%;"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">With Clients</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($cylinder['clients']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="users" class="w-3 h-3"></i>Active Circulation
                        </span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Maintenance / Scrapped</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($cylinder['maint']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="wrench" class="w-3 h-3"></i>Out of service
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Movement Logs</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                View Full History<i data-lucide="arrow-right" class="w-3.5 h-3.5 ml-1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                    <th class="pl-6 pr-3 py-3 font-medium">Log ID</th>
                                    <th class="px-3 py-3 font-medium">Date & Time</th>
                                    <th class="px-3 py-3 font-medium">Event Type</th>
                                    <th class="px-3 py-3 font-medium text-right">Qty</th>
                                    <th class="px-3 py-3 font-medium">Reference / Client</th>
                                    <th class="pr-6 py-3 font-medium text-right">Logged By</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($logs as $l): ?>
                                <?php $es = $eventStyles[$l['event']]; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($l['id']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($l['date']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background: <?= $es['bg'] ?>; color: <?= $es['fg'] ?>;">
                                            <?= htmlspecialchars($l['event']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($l['qty']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px]" style="color: var(--ink);"><?= htmlspecialchars($l['ref']) ?></td>
                                    <td class="pr-6 py-3.5 text-right font-medium" style="color: var(--mute);"><?= htmlspecialchars($l['user']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    
                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Technical Specifications</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Material Build</p>
                                <p class="info-value"><?= htmlspecialchars($cylinder['material']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Working / Test Pressure</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($cylinder['working_pressure']) ?> / <?= htmlspecialchars($cylinder['test_pressure']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Valve Specification</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($cylinder['valve_type']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Color Coding Standard</p>
                                <p class="info-value" style="font-size:13px;"><?= htmlspecialchars($cylinder['color_code']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Compliance & Testing</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Last Hydro-Test Batch</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($cylinder['last_hydro']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Next Hydro-Test Due</p>
                                <p class="info-value mono font-semibold" style="font-size:12.5px; color: var(--ink);"><?= htmlspecialchars($cylinder['next_hydro']) ?></p>
                            </div>
                            <div class="info-row border-none pb-4">
                                <button class="w-full btn-secondary py-2 mt-2 rounded-sm text-[12.5px] font-medium bg-white">
                                    Schedule Hydro-Test
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
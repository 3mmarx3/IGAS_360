<?php
session_start();
require_once '../../config/db.php';

$active_page = 'liquid_tracking';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Bulk Liquid Tracking'];

$plant_tanks = [
    [
        'id' => 'PLT-O2-01',
        'tank_name' => 'Main LOX Storage', 
        'gas' => 'Liquid Oxygen (LOX)', 
        'capacity' => 100000, 
        'current' => 78500, 
        'pressure' => 12.5, 
        'purity' => '99.99%', 
        'status' => 'optimal',
        'last_fill' => '2026-08-18 08:30:00'
    ],
    [
        'id' => 'PLT-N2-01',
        'tank_name' => 'Primary LIN Storage', 
        'gas' => 'Liquid Nitrogen (LIN)', 
        'capacity' => 80000, 
        'current' => 21000, 
        'pressure' => 8.2, 
        'purity' => '99.999%', 
        'status' => 'refuel_req',
        'last_fill' => '2026-08-10 14:15:00'
    ],
    [
        'id' => 'PLT-AR-01',
        'tank_name' => 'LAR Reserve Depot', 
        'gas' => 'Liquid Argon (LAR)', 
        'capacity' => 30000, 
        'current' => 28500, 
        'pressure' => 14.1, 
        'purity' => '99.999%', 
        'status' => 'optimal',
        'last_fill' => '2026-08-17 11:00:00'
    ],
    [
        'id' => 'PLT-LPG-01',
        'tank_name' => 'LPG Central Hub', 
        'gas' => 'Liquid Petroleum Gas', 
        'capacity' => 150000, 
        'current' => 18000, 
        'pressure' => 5.5, 
        'purity' => 'Standard', 
        'status' => 'critical',
        'last_fill' => '2026-07-28 09:45:00'
    ]
];

$total_capacity = array_sum(array_column($plant_tanks, 'capacity'));
$total_current  = array_sum(array_column($plant_tanks, 'current'));
$overall_pct    = $total_capacity > 0 ? round(($total_current / $total_capacity) * 100) : 0;

$statusStyles = [
    'optimal'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Optimal Level'],
    'refuel_req'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Delivery Scheduled'],
    'critical'    => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Critical / Low'],
    'maintenance' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Under Maintenance']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Liquid Tracking | I-GAS Enterprise</title>
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
                <span class="status-dot" style="background: #5C8A5C;"></span>Sensors Online
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Production & Inventory</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Bulk Liquid Tracking</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor main plant storage tanks, cryogenic liquid reserves, and raw material purity.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="arrow-down-to-line" class="w-4 h-4"></i>Receive Shipment
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="arrow-up-from-line" class="w-4 h-4"></i>Dispatch to Filling
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Storage Capacity</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_capacity) ?> <span class="text-[14px] text-[var(--mute)]">L</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Across all plant reservoirs</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Current Liquid Stock</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_current) ?> <span class="text-[14px] text-[var(--mute)]">L</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="droplet" class="w-3 h-3"></i><?= $overall_pct ?>% of total capacity
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Daily Vaporization Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">2,450 <span class="text-[14px] text-[var(--mute)]">L/day</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="activity" class="w-3 h-3"></i>Average processing draw
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Incoming Shipments</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">1</h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Scheduled for tomorrow (LPG)</span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Plant Tanks Overview</h3>
                        <div class="flex items-center gap-3">
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Tanks</option>
                                <option>Cryogenic (LOX/LIN/LAR)</option>
                                <option>Pressurized (LPG)</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Live Telemetry</span>
                        <span class="tab-item">Delivery History</span>
                        <span class="tab-item">Purity Logs</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">Tank ID & Name</th>
                                <th class="px-3 py-3 font-medium">Stored Material</th>
                                <th class="px-3 py-3 font-medium">Current Level</th>
                                <th class="px-3 py-3 font-medium text-right">Pressure / Purity</th>
                                <th class="px-3 py-3 font-medium">Last Received</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($plant_tanks as $tank): ?>
                            <?php
                                $level_pct = $tank['capacity'] > 0 ? round(($tank['current'] / $tank['capacity']) * 100) : 0;
                                $statusObj = $statusStyles[$tank['status']];
                                
                                $barColor = '#45663F';
                                if ($level_pct <= 15) $barColor = '#963B33';
                                elseif ($level_pct < 30) $barColor = '#9A7B2E';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-3 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($tank['tank_name']) ?></span>
                                        <span class="text-[11.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($tank['id']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 font-medium" style="color: var(--ink);"><?= htmlspecialchars($tank['gas']) ?></td>
                                <td class="px-3 py-4 w-48">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex justify-between items-end text-[12px] mono num">
                                            <span style="color: var(--ink);"><?= number_format($tank['current']) ?> L</span>
                                            <span style="color: var(--mute);"><?= $level_pct ?>%</span>
                                        </div>
                                        <div class="w-full h-1.5 rounded-full overflow-hidden" style="background: var(--line-soft);">
                                            <div class="h-full rounded-full" style="width: <?= $level_pct ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-right">
                                    <div class="flex flex-col">
                                        <span class="font-medium num" style="color: var(--ink);"><?= number_format($tank['pressure'], 1) ?> <span class="text-[11px] text-[var(--mute)]">BAR</span></span>
                                        <span class="text-[11.5px] mono" style="color: var(--mute);">Purity: <?= htmlspecialchars($tank['purity']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 num text-[12.5px]" style="color: var(--mute);">
                                    <?= date('M d, Y', strtotime($tank['last_fill'])) ?><br>
                                    <span class="text-[11px]"><?= date('H:i', strtotime($tank['last_fill'])) ?></span>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button class="transition-colors bg-transparent border-none cursor-pointer" style="color: var(--mute);" title="View Telemetry" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        <button class="transition-colors bg-transparent border-none cursor-pointer" style="color: var(--mute);" title="Log Drawing" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 2v20"/><path d="m17 17-5 5-5-5"/><path d="M2 12h20"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing all <?= count($plant_tanks) ?> registered plant tanks</span>
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
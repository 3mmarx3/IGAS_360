<?php
require_once '../../config/db.php';

$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Odometers & Tires'];

$stmt_total = $pdo->query("SELECT COUNT(*) FROM vehicles");
$total_fleet = $stmt_total->fetchColumn() ?: 0;

$stmt_vehicles = $pdo->query("
    SELECT v.fleet_id as id, v.make_model as make, 
           COALESCE((SELECT MAX(odometer) FROM vehicle_logs WHERE fleet_id = v.fleet_id), 0) as odo
    FROM vehicles v
    ORDER BY v.fleet_id ASC
");
$db_fleet = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

$fleet_data = [];
$service_due = 0;
$tires_critical = 0;
$total_health = 0;

foreach ($db_fleet as $row) {
    $odo = (int)$row['odo'];
    
    $service_interval = 10000;
    $next_service = (floor($odo / $service_interval) + 1) * $service_interval;
    if ($odo === 0) {
        $next_service = 10000;
    }
    
    $tire_lifespan = 60000;
    $km_on_tires = $odo % $tire_lifespan;
    $tires_health = max(0, 100 - round(($km_on_tires / $tire_lifespan) * 100));
    
    if ($tires_health < 20) {
        $status = 'critical';
        $tires_critical++;
    } elseif ($tires_health < 50 || ($next_service - $odo) <= 1000) {
        $status = 'warning';
    } else {
        $status = 'optimal';
    }

    if ($odo >= $next_service || ($next_service - $odo) <= 1000) {
        $service_due++;
    }

    $total_health += $tires_health;

    $fleet_data[] = [
        'id' => $row['id'],
        'make' => $row['make'],
        'odo' => $odo,
        'next_service' => $next_service,
        'tires_health' => $tires_health,
        'status' => $status
    ];
}

$avg_health = count($fleet_data) > 0 ? round($total_health / count($fleet_data)) : 0;

$statusStyles = [
    'optimal'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Healthy'],
    'warning'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Needs Review'],
    'critical' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Critical / Replace'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odometers & Tires | I-GAS Enterprise</title>
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

        input:focus, select:focus { outline: none; border-color: var(--ink) !important; }
        th, td { vertical-align: middle; }

        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }

        .checkbox-sq { width: 15px; height: 15px; border: 1.5px solid var(--mute-soft); border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer; transition: border-color 0.15s ease; }
        .checkbox-sq:hover { border-color: var(--ink); }

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
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics &amp; Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Odometers &amp; Tires</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor vehicle mileage, forecast service intervals, and track tire degradation.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Report
                    </button>
                    <button onclick="window.location.reload();" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>Sync Telematics
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Monitored Vehicles</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_fleet ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Active telemetry links</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Service Due / Overdue</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $service_due ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="alert-circle" class="w-3 h-3"></i>Exceeded mileage limit
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Critical Tire Health</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= $tires_critical ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="octagon-alert" class="w-3 h-3"></i>Requires replacement
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fleet Tire Average</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $avg_health ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $avg_health ?>%; background: #45663F;"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Odometers &amp; Tires Log</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search Fleet ID or Model..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Statuses</option>
                                <option>Optimal</option>
                                <option>Needs Review</option>
                                <option>Critical</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All Vehicles <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($fleet_data) ?></span></span>
                        <span class="tab-item">Service Overdue <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $service_due ?></span></span>
                        <span class="tab-item text-red-700">Tires Critical <span class="num text-[11px]" style="color: #963B33;"><?= $tires_critical ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Fleet Unit</th>
                                <th class="px-3 py-3 font-medium text-right">Current Odometer</th>
                                <th class="px-3 py-3 font-medium text-right">Next Service</th>
                                <th class="px-6 py-3 font-medium">Tires Health Indicator</th>
                                <th class="px-3 py-3 font-medium">System Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if(empty($fleet_data)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-[13px]" style="color: var(--mute);">
                                    No vehicles found in the fleet database.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($fleet_data as $row): ?>
                                <?php
                                    $s = $statusStyles[$row['status']];
                                    $isOdoCritical = $row['odo'] >= $row['next_service'];
                                    $odoColor = $isOdoCritical ? '#963B33' : 'var(--ink)';
                                    $tireColor = $row['tires_health'] < 20 ? '#963B33' : ($row['tires_health'] < 50 ? '#9A7B2E' : '#45663F');
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col">
                                            <span class="font-medium mono" style="color: var(--ink);"><?= htmlspecialchars($row['id']) ?></span>
                                            <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($row['make']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $odoColor ?>;">
                                        <?= number_format($row['odo']) ?> <span class="text-[11px] font-normal" style="color: var(--mute);">KM</span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--mute);">
                                        <?= number_format($row['next_service']) ?> <span class="text-[11px] font-normal" style="color: var(--mute-soft);">KM</span>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="meter-bar w-32">
                                                <div class="meter-fill h-full" style="width: <?= $row['tires_health'] ?>%; background: <?= $tireColor ?>;"></div>
                                            </div>
                                            <span class="num text-[12.5px] font-medium" style="color: <?= $tireColor ?>;"><?= $row['tires_health'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                        <a href="manual_entry.php?id=<?= urlencode($row['id']) ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">Log Entry</a>
                                        <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($fleet_data) ?> of <?= $total_fleet ?> Units</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">2</button>
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
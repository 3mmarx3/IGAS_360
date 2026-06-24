<?php
// 1. الاتصال بقاعدة البيانات
require_once '../../config/db.php'; 

$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet'];

$stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
$stats = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);

$available      = $stats['available'] ?? 0;
$in_transit     = ($stats['transit'] ?? 0) + ($stats['in_transit'] ?? 0); 
$in_maintenance = $stats['maintenance'] ?? 0;
$total_vehicles = array_sum($stats);

$utilization = $total_vehicles > 0 ? round((($in_transit + $available) / $total_vehicles) * 100, 1) : 0;

$stmt_fleet = $pdo->query("SELECT * FROM vehicles ORDER BY created_at DESC");
$fleet_data = $stmt_fleet->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
    'available'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Available'],
    'transit'     => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'In Transit'],
    'in_transit'  => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'In Transit'],
    'maintenance' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Maintenance'],
];

$vehicle_type_map = [
    'cryo'    => 'Cryogenic Tanker',
    'flatbed' => 'Flatbed Truck',
    'bobtail' => 'Bobtail Tanker',
    'pickup'  => 'Pickup Truck'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles Fleet | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A; --ink-soft: #2E2E2E; --paper: #FFFFFF; --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC; --line: #D8D6D1; --line-soft: #E7E5E1; --accent: #9A7B2E;
            --accent-soft: #FBF3DF; --mute: #767470; --mute-soft: #A6A39D; --sidebar: #1A1A1A;
            --sidebar-line: #2E2E2E; --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; } html { font-size: 16px; }
        body { font-family: 'IBM Plex Sans', sans-serif; background-color: var(--paper-dim); color: var(--ink); font-feature-settings: "tnum" 1; }
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
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }
        input:focus, select:focus { outline: none; border-color: var(--ink) !important; }
        th, td { vertical-align: middle; }
        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
        .tab-item.active { color: var(--ink); } .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); } .tab-item:not(.active):hover { color: var(--ink); }
        .avatar-sq { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px; }
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics & Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Vehicles Fleet</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage company vehicles, operational status, and driver assignments.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Registry
                    </button>
                    <a href="new_vehicle.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Add Vehicle
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Fleet Size</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_vehicles ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="ml-0" style="color: var(--mute);">registered units</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active / In Transit</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $in_transit ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="map-pin" class="w-3 h-3"></i>Currently on route
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">In Maintenance</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= $in_maintenance ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="wrench" class="w-3 h-3"></i>Requires attention
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fleet Utilization</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $utilization ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $utilization ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Fleet Database</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search ID or Plate No." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Types</option>
                                <option>Cryogenic Tanker</option>
                                <option>Bobtail Tanker</option>
                                <option>Flatbed Truck</option>
                                <option>Pickup Truck</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $total_vehicles ?></span></span>
                        <span class="tab-item">Available <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $available ?></span></span>
                        <span class="tab-item">In Transit <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $in_transit ?></span></span>
                        <span class="tab-item text-red-700">Maintenance <span class="num text-[11px]" style="color: #963B33;"><?= $in_maintenance ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Fleet ID</th>
                                <th class="px-3 py-3 font-medium">Plate Number</th>
                                <th class="px-3 py-3 font-medium">Vehicle Details</th>
                                <th class="px-3 py-3 font-medium">Max Capacity</th>
                                <th class="px-3 py-3 font-medium">Assigned Driver</th>
                                <th class="px-3 py-3 font-medium">Operational Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            
                            <?php foreach ($fleet_data as $v): ?>
                            <?php
                                // تحديد الستايل الخاص بالحالة
                                $v_status = strtolower($v['status']);
                                $s = $statusStyles[$v_status] ?? $statusStyles['available'];
                                $isMaintenance = $v_status === 'maintenance';
                                $rowColor = $isMaintenance ? 'var(--mute-soft)' : 'var(--ink)';

                                // منطق لعرض السعة (إذا كانت بالطن أو بالأسطوانة)
                                $capacity_display = '';
                                if ($v['load_capacity'] > 0) {
                                    // عرض الحمولة بالطن مع إزالة الأصفار الزائدة
                                    $capacity_display = (float)$v['load_capacity'] . 'T';
                                } else {
                                    // عرض سعة الأسطوانات
                                    $capacity_display = $v['cylinder_capacity'] . ' Cyl.';
                                }

                                // منطق لعرض اسم نوع المركبة بدلاً من الكود المختصر
                                $display_type = $vehicle_type_map[$v['vehicle_type']] ?? ucfirst($v['vehicle_type']);
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($v['fleet_id']) ?></td>
                                <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--ink); border: 1px solid var(--line-soft); display: inline-block; padding: 2px 8px; margin-top: 8px; background: white; border-radius: 2px;">
                                    <?= htmlspecialchars($v['plate_number']) ?>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($display_type) ?></span>
                                        <span class="text-[11px] mono" style="color: var(--mute);"><?= htmlspecialchars($v['make_model']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-[12.5px] mono font-medium" style="color: var(--mute);"><?= $capacity_display ?></td>
                                <td class="px-3 py-3.5">
                                    <?php if($v['driver_id'] === 'unassigned' || empty($v['driver_id'])): ?>
                                        <span class="text-[12px] italic" style="color: var(--mute-soft);">Unassigned</span>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-[9px] font-bold text-gray-600">
                                                D
                                            </span>
                                            <span style="color: <?= $rowColor ?>;">Driver #<?= htmlspecialchars($v['driver_id']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <a href="vehicle_logs.php?id=<?= $v['id'] ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">Logs</a>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($fleet_data) ?> of <?= $total_vehicles ?></span>
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
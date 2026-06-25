<?php
require_once '../../config/db.php';

$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet', 'Vehicle Logs'];

$vehicle_id = $_GET['id'] ?? '';

$stmt_vehicle = $pdo->prepare("
    SELECT v.*, d.full_name as driver_name 
    FROM vehicles v 
    LEFT JOIN drivers d ON v.driver_id = d.driver_id OR v.driver_id = d.id
    WHERE v.fleet_id = ?
");
$stmt_vehicle->execute([$vehicle_id]);
$db_vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);

if (!$db_vehicle) {
    header("Location: vehicles_fleet.php");
    exit;
}

$stmt_odo = $pdo->prepare("SELECT MAX(odometer) FROM vehicle_logs WHERE fleet_id = ?");
$stmt_odo->execute([$vehicle_id]);
$max_odo = $stmt_odo->fetchColumn();
$mileage = $max_odo ? number_format($max_odo) : '0';

$stmt_maint = $pdo->prepare("SELECT MAX(event_date) FROM vehicle_logs WHERE fleet_id = ? AND event_type = 'maintenance'");
$stmt_maint->execute([$vehicle_id]);
$last_maint_date = $stmt_maint->fetchColumn();

$last_maintenance = $last_maint_date ? date('d M Y', strtotime($last_maint_date)) : 'N/A';
$next_maintenance = $last_maint_date ? date('d M Y', strtotime('+6 months', strtotime($last_maint_date))) : 'N/A';

$vehicle = [
    'id' => $db_vehicle['fleet_id'],
    'plate' => $db_vehicle['plate_number'],
    'type' => ucfirst($db_vehicle['vehicle_type']),
    'make' => $db_vehicle['make_model'],
    'driver' => $db_vehicle['driver_name'] ?: 'Unassigned',
    'status' => $db_vehicle['status'],
    'mileage' => $mileage,
    'fuel_level' => 78,
    'last_maintenance' => $last_maintenance,
    'next_maintenance' => $next_maintenance
];

$stmt_logs = $pdo->prepare("SELECT * FROM vehicle_logs WHERE fleet_id = ? ORDER BY event_date DESC, event_time DESC");
$stmt_logs->execute([$vehicle_id]);
$db_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
foreach ($db_logs as $l) {
    $type = strtolower($l['event_type']);
    $icon = 'circle';
    $color = '#1A1A1A';
    $bg = '#F7F7F6';
    $display_type = ucfirst($type);

    if ($type === 'dispatch') {
        $icon = 'arrow-up-right';
        $color = '#2A6B8A';
        $bg = '#E8F1F5';
    } elseif ($type === 'arrival') {
        $icon = 'arrow-down-left';
        $color = '#45663F';
        $bg = '#EAF1E7';
    } elseif (in_array($type, ['refuel', 'refuel operations'])) {
        $icon = 'fuel';
        $color = '#9A7B2E';
        $bg = '#FBF3DF';
        $display_type = 'Refuel Operations';
    } elseif ($type === 'maintenance') {
        $icon = 'wrench';
        $color = '#963B33';
        $bg = '#F8E9E7';
    } elseif ($type === 'incident') {
        $icon = 'alert-triangle';
        $color = '#963B33';
        $bg = '#F8E9E7';
    }

    $formatted_date = date('d M Y', strtotime($l['event_date']));
    $formatted_time = date('H:i', strtotime($l['event_time']));
    $date_time_display = date('d M Y, H:i', strtotime($l['event_date'] . ' ' . $l['event_time']));

    $logs[] = [
        'date_time_display' => $date_time_display,
        'date' => $formatted_date,
        'time' => $formatted_time,
        'type' => $display_type,
        'desc' => $l['description'] ?: 'Log entry recorded',
        'operator' => $l['logged_by'] ?: 'System',
        'odometer' => $l['odometer'] ? number_format($l['odometer']) : '0',
        'fuel' => $l['fuel_liters'] > 0 ? $l['fuel_liters'] : '-',
        'cost' => $l['event_cost'] > 0 ? number_format($l['event_cost'], 2) : '-',
        'icon' => $icon,
        'color' => $color,
        'bg' => $bg
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Logs | I-GAS Enterprise</title>
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
        * { box-sizing: border-box; }
        html { font-size: 16px; }
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
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; text-decoration: none; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; text-decoration: none; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
        th, td { vertical-align: middle; }
        
        #log-modal-overlay { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(2px); }
        .info-block { display: flex; flex-direction: column; gap: 4px; }
        .info-lbl { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); font-weight: 600; }
        .info-val { font-size: 14px; font-weight: 500; color: var(--ink); }
        .info-val.mono { font-family: 'IBM Plex Mono', monospace; }
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
                    <a href="vehicles_fleet.php" class="inline-flex items-center gap-1.5 text-[12px] font-medium mb-3 transition-colors" style="color: var(--mute); text-decoration: none;"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Fleet</a>
                    <div class="flex items-center gap-3">
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Vehicle Logs</h2>
                        <span class="text-[14px] mono px-2 py-0.5 rounded-sm bg-white border" style="color: var(--ink); border-color: var(--line);"><?= htmlspecialchars($vehicle['id']) ?></span>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Log
                    </button>
                    <a href="manual_entry.php?id=<?= urlencode($vehicle['id']) ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Manual Entry
                    </a>
                </div>
            </div>

            <div class="card rounded-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Identification</p>
                        <p class="text-[15px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($vehicle['make']) ?></p>
                        <p class="text-[13px] mt-1" style="color: var(--mute);"><?= htmlspecialchars($vehicle['type']) ?></p>
                        <div class="mt-3 inline-block text-[12px] mono px-2 py-1 bg-white border rounded-sm" style="border-color: var(--line-soft); color: var(--ink);">
                            <?= htmlspecialchars($vehicle['plate']) ?>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Current Status</p>
                        <div class="mt-1">
                            <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                                <span class="status-dot" style="background:#2A6B8A;"></span><?= htmlspecialchars(ucfirst($vehicle['status'])) ?>
                            </span>
                        </div>
                        <p class="text-[12px] mt-3 flex items-center gap-1.5" style="color: var(--ink);">
                            <i data-lucide="user" class="w-3.5 h-3.5" style="color: var(--mute);"></i><?= htmlspecialchars($vehicle['driver']) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Odometer & Fuel</p>
                        <p class="text-[15px] font-medium num" style="color: var(--ink);"><?= htmlspecialchars($vehicle['mileage']) ?> <span class="text-[12px] font-normal" style="color: var(--mute);">KM</span></p>
                        <div class="mt-3">
                            <div class="flex justify-between text-[11px] font-medium mb-1">
                                <span style="color: var(--mute);">Fuel Level</span>
                                <span class="num" style="color: var(--ink);"><?= $vehicle['fuel_level'] ?>%</span>
                            </div>
                            <div class="meter-bar h-1.5 w-full overflow-hidden">
                                <div class="meter-fill h-full" style="width: <?= $vehicle['fuel_level'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Maintenance</p>
                        <p class="text-[13px]" style="color: var(--ink);">Last: <span class="mono text-[12px]"><?= htmlspecialchars($vehicle['last_maintenance']) ?></span></p>
                        <p class="text-[13px] mt-2" style="color: var(--ink);">Next: <span class="mono text-[12px]"><?= htmlspecialchars($vehicle['next_maintenance']) ?></span></p>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Activity Log</h3>
                    <div class="flex items-center gap-3">
                        <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                            <option>All Activities</option>
                            <option>Dispatches</option>
                            <option>Refuels</option>
                            <option>Maintenance</option>
                        </select>
                        <div class="relative">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                            <input type="text" value="<?= date('M Y') ?>" readonly class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-32 cursor-pointer" style="border-color: var(--line); color: var(--ink);">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    
                <table class="w-full text-left border-collapse">
    <thead>
        <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
            <th class="px-3 py-3 font-medium">Fleet ID</th>
            <th class="px-3 py-3 font-medium">Make & Model</th>
            <th class="px-3 py-3 font-medium">Plate Number</th>
            <th class="px-6 py-3 font-medium w-48">Date & Time</th>
            <th class="px-3 py-3 font-medium">Event Type</th>
            <th class="px-6 py-3 font-medium text-right w-24">Actions</th>
        </tr>
    </thead>
    <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
        <?php if (empty($logs)): ?>
        <tr>
            <td colspan="6" class="px-6 py-8 text-center text-[13px]" style="color: var(--mute);">
                No activity logs found for this vehicle.
            </td>
        </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                <td class="px-3 py-3.5 font-medium num" style="color: var(--ink);"><?= htmlspecialchars($vehicle['id']) ?></td>
                <td class="px-3 py-3.5" style="color: var(--ink);"><?= htmlspecialchars($vehicle['make']) ?></td>
                <td class="px-3 py-3.5 mono text-[12.5px]" style="color: var(--mute);"><?= htmlspecialchars($vehicle['plate']) ?></td>
                <td class="px-6 py-3.5 mono text-[12.5px]" style="color: var(--ink);"><?= htmlspecialchars($log['date_time_display']) ?></td>
                <td class="px-3 py-3.5">
                    <span class="inline-flex items-center gap-1.5 font-medium px-2 py-1 rounded-sm text-[12px]" style="background: <?= $log['bg'] ?>; color: <?= $log['color'] ?>;">
                        <i data-lucide="<?= $log['icon'] ?>" class="w-3 h-3"></i><?= htmlspecialchars($log['type']) ?>
                    </span>
                </td>
                <td class="px-6 py-3.5 text-right">
                    <button type="button" class="transition-colors bg-transparent border-none cursor-pointer p-1" style="color: var(--mute);" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'" onclick="openLogModal(this)" data-log="<?= htmlspecialchars(json_encode($log)) ?>">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
                </div>
            </div>

        </div>
    </main>

    <div id="log-modal-overlay" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md w-full max-w-2xl shadow-xl flex flex-col" style="border: 1px solid var(--line);">
            <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft); background: var(--paper-dim);">
                <h2 class="text-[16px] font-semibold tracking-tight" style="color: var(--ink);">Log Details</h2>
                <button type="button" onclick="closeLogModal()" class="text-gray-400 hover:text-gray-700 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="info-block mb-6">
                    <span class="info-lbl">Target Vehicle</span>
                    <span class="info-val"><?= htmlspecialchars($vehicle['id']) ?> (<?= htmlspecialchars($vehicle['make']) ?> — <?= htmlspecialchars($vehicle['plate']) ?>)</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6 pb-6 border-b" style="border-color: var(--line-soft);">
                    <div class="info-block">
                        <span class="info-lbl">Event Type</span>
                        <span class="info-val" id="mdl-type">-</span>
                    </div>
                    <div class="info-block">
                        <span class="info-lbl">Date of Event</span>
                        <span class="info-val mono" id="mdl-date">-</span>
                    </div>
                    <div class="info-block">
                        <span class="info-lbl">Time of Event</span>
                        <span class="info-val mono" id="mdl-time">-</span>
                    </div>
                    <div class="info-block">
                        <span class="info-lbl">Logged By</span>
                        <span class="info-val" id="mdl-operator">-</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-6 pb-6 border-b" style="border-color: var(--line-soft);">
                    <div class="info-block">
                        <span class="info-lbl">Current Odometer</span>
                        <span class="info-val mono num"><span id="mdl-odo">-</span> <span class="text-[12px] font-normal" style="color: var(--mute);">KM</span></span>
                    </div>
                    <div class="info-block">
                        <span class="info-lbl">Fuel Added</span>
                        <span class="info-val mono num"><span id="mdl-fuel">-</span> <span class="text-[12px] font-normal" style="color: var(--mute);">LTR</span></span>
                    </div>
                    <div class="info-block">
                        <span class="info-lbl">Associated Cost</span>
                        <span class="info-val mono num"><span id="mdl-cost">-</span> <span class="text-[12px] font-normal" style="color: var(--mute);">SAR</span></span>
                    </div>
                </div>

                <div class="info-block">
                    <span class="info-lbl">Log Description & Details</span>
                    <p class="text-[14px] leading-relaxed" style="color: var(--ink-soft);" id="mdl-desc">-</p>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t flex justify-end" style="border-color: var(--line-soft); background: var(--paper-dim);">
                <button type="button" onclick="closeLogModal()" class="btn-secondary px-5 py-2 rounded-sm text-[13.5px] font-medium">Close</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openLogModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-log'));
            
            document.getElementById('mdl-type').textContent = data.type;
            document.getElementById('mdl-date').textContent = data.date;
            document.getElementById('mdl-time').textContent = data.time;
            document.getElementById('mdl-operator').textContent = data.operator;
            document.getElementById('mdl-odo').textContent = data.odometer;
            
            document.getElementById('mdl-fuel').textContent = data.fuel !== '-' ? data.fuel : 'N/A';
            document.getElementById('mdl-cost').textContent = data.cost !== '-' ? data.cost : 'N/A';
            
            document.getElementById('mdl-desc').textContent = data.desc;

            document.getElementById('log-modal-overlay').classList.remove('hidden');
        }

        function closeLogModal() {
            document.getElementById('log-modal-overlay').classList.add('hidden');
        }

        document.getElementById('log-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogModal();
            }
        });
    </script>
</body>
</html>
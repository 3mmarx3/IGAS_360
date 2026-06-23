<?php
$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet', 'Vehicle Logs'];

$vehicle_id = $_GET['id'] ?? 'FLT-001';

$vehicle = [
    'id' => $vehicle_id,
    'plate' => 'T S A 1234',
    'type' => 'Cryogenic Tanker',
    'make' => 'Mercedes Actros',
    'driver' => 'Ahmed Ali',
    'status' => 'transit',
    'mileage' => '142,500',
    'fuel_level' => 78,
    'last_maintenance' => '10 May 2026',
    'next_maintenance' => '10 Nov 2026'
];

$logs = [
    ['date' => '21 Jun 2026, 14:30', 'type' => 'Dispatch', 'desc' => 'Dispatched to Jeddah Industrial City (Order #ORD-9921)', 'operator' => 'System', 'icon' => 'arrow-up-right', 'color' => '#2A6B8A', 'bg' => '#E8F1F5'],
    ['date' => '20 Jun 2026, 09:15', 'type' => 'Refuel', 'desc' => 'Refueled 120 Liters Diesel at Station A', 'operator' => 'Ahmed Ali', 'icon' => 'fuel', 'color' => '#9A7B2E', 'bg' => '#FBF3DF'],
    ['date' => '18 Jun 2026, 16:45', 'type' => 'Arrival', 'desc' => 'Returned to Base. Odometer: 142,450 km', 'operator' => 'Gate Security', 'icon' => 'arrow-down-left', 'color' => '#45663F', 'bg' => '#EAF1E7'],
    ['date' => '18 Jun 2026, 08:00', 'type' => 'Dispatch', 'desc' => 'Dispatched to Mecca Client Site (Order #ORD-9884)', 'operator' => 'System', 'icon' => 'arrow-up-right', 'color' => '#2A6B8A', 'bg' => '#E8F1F5'],
    ['date' => '15 Jun 2026, 10:00', 'type' => 'Maintenance', 'desc' => 'Scheduled tire rotation and oil check', 'operator' => 'Workshop', 'icon' => 'wrench', 'color' => '#963B33', 'bg' => '#F8E9E7'],
    ['date' => '12 Jun 2026, 18:20', 'type' => 'Arrival', 'desc' => 'Returned to Base. Odometer: 141,900 km', 'operator' => 'Gate Security', 'icon' => 'arrow-down-left', 'color' => '#45663F', 'bg' => '#EAF1E7'],
    ['date' => '12 Jun 2026, 07:30', 'type' => 'Dispatch', 'desc' => 'Dispatched to Yanbu Plant (Order #ORD-9810)', 'operator' => 'System', 'icon' => 'arrow-up-right', 'color' => '#2A6B8A', 'bg' => '#E8F1F5'],
];
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
        .meter-fill { background: var(--ink); }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
        th, td { vertical-align: middle; }
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
                    <a href="vehicles_fleet.php" class="inline-flex items-center gap-1.5 text-[12px] font-medium mb-3 transition-colors" style="color: var(--mute);"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Fleet</a>
                    <div class="flex items-center gap-3">
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Vehicle Logs</h2>
                        <span class="text-[14px] mono px-2 py-0.5 rounded-sm bg-white border" style="color: var(--ink); border-color: var(--line);"><?= htmlspecialchars($vehicle['id']) ?></span>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Log
                    </button>
                  <a href="manual_entry.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
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
                                <span class="status-dot" style="background:#2A6B8A;"></span>In Transit
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
                            <input type="text" value="Jun 2026" readonly class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-32 cursor-pointer" style="border-color: var(--line); color: var(--ink);">
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                <th class="px-6 py-3 font-medium w-48">Date & Time</th>
                                <th class="px-3 py-3 font-medium w-32">Event Type</th>
                                <th class="px-3 py-3 font-medium">Description / Notes</th>
                                <th class="px-6 py-3 font-medium text-right w-40">Logged By</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($logs as $log): ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="px-6 py-3.5 mono text-[12.5px]" style="color: var(--ink);"><?= htmlspecialchars($log['date']) ?></td>
                                <td class="px-3 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 font-medium px-2 py-1 rounded-sm text-[12px]" style="background: <?= $log['bg'] ?>; color: <?= $log['color'] ?>;">
                                        <i data-lucide="<?= $log['icon'] ?>" class="w-3 h-3"></i><?= htmlspecialchars($log['type']) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3.5" style="color: var(--ink);"><?= htmlspecialchars($log['desc']) ?></td>
                                <td class="px-6 py-3.5 text-right font-medium" style="color: var(--mute);"><?= htmlspecialchars($log['operator']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
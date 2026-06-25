<?php
require_once '../../config/db.php';

$active_page = 'maintenance_fuel';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Maintenance & Fuel'];

$stmt_active = $pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status IN ('scheduled', 'in_progress')");
$active_tickets = $stmt_active->fetchColumn() ?: 0;

$stmt_overdue = $pdo->query("SELECT COUNT(*) FROM maintenance_tickets WHERE status NOT IN ('completed', 'cancelled') AND scheduled_date < CURDATE()");
$overdue_tasks = $stmt_overdue->fetchColumn() ?: 0;

$stmt_fuel = $pdo->query("SELECT SUM(fuel_liters) FROM vehicle_logs WHERE event_type = 'refuel' AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())");
$fuel_mtd = $stmt_fuel->fetchColumn() ?: 0;

$stmt_cost = $pdo->query("SELECT SUM(estimated_cost) FROM maintenance_tickets WHERE MONTH(scheduled_date) = MONTH(CURDATE()) AND YEAR(scheduled_date) = YEAR(CURDATE())");
$cost_mtd = $stmt_cost->fetchColumn() ?: 0;

$stmt_records = $pdo->query("
    SELECT m.ticket_id as id, m.vehicle_id as vehicle, v.make_model as model, m.service_type as type, m.status as db_status, m.scheduled_date as date, m.estimated_cost as cost 
    FROM maintenance_tickets m 
    LEFT JOIN vehicles v ON m.vehicle_id = v.fleet_id 
    ORDER BY m.created_at DESC
");
$raw_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

$maintenance_records = [];
foreach ($raw_records as $r) {
    $status = $r['db_status'];
    if (in_array($status, ['scheduled', 'in_progress']) && strtotime($r['date']) < strtotime(date('Y-m-d'))) {
        $status = 'overdue';
    }
    if ($status === 'cancelled') {
        $status = 'overdue';
    }
    
    $type_map = [
        'preventive' => 'Preventive (PM)',
        'repair' => 'Repair',
        'inspection' => 'Inspection',
        'tires' => 'Tire Replacement',
        'oil' => 'Oil Change'
    ];
    
    $maintenance_records[] = [
        'id' => $r['id'],
        'vehicle' => $r['vehicle'],
        'model' => $r['model'] ?: 'Unknown Model',
        'type' => $type_map[$r['type']] ?? ucfirst($r['type']),
        'status' => $status,
        'date' => $r['date'],
        'cost' => number_format($r['cost'], 2)
    ];
}

$statusStyles = [
    'scheduled'   => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'Scheduled'],
    'in_progress' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In Workshop'],
    'completed'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Completed'],
    'overdue'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Overdue/Cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance &amp; Fuel | I-GAS Enterprise</title>
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
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics &amp; Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Maintenance &amp; Fuel</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage fleet service schedules, workshop tickets, and fuel consumption analytics.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="droplet" class="w-4 h-4"></i>Log Fuel Entry
                    </button>
                    <a href="new_maintenance.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4"></i>New Service Ticket
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Workshop Tickets</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $active_tickets ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="tool" class="w-3 h-3"></i>In progress / Scheduled
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Overdue Services</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $overdue_tasks ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="alert-circle" class="w-3 h-3"></i>Requires attention
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fuel Burned (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($fuel_mtd) ?> <span class="text-[14px] font-normal" style="color: var(--mute);">L</span></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: 65%; background: #2A6B8A;"></div>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Maintenance Cost (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">SAR <?= number_format($cost_mtd) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Budget tracking indicator</span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Service &amp; Repair Registry</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search ticket, asset, type" class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Active Maintenance <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $active_tickets ?></span></span>
                        <span class="tab-item">Service History</span>
                        <span class="tab-item">Fuel Logs</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Ticket ID</th>
                                <th class="px-3 py-3 font-medium">Asset Assign</th>
                                <th class="px-3 py-3 font-medium">Service Type</th>
                                <th class="px-3 py-3 font-medium">Date</th>
                                <th class="px-3 py-3 font-medium">Est. Cost (SAR)</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($maintenance_records)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-[13px]" style="color: var(--mute);">
                                    No maintenance records found.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($maintenance_records as $r): ?>
                                <?php
                                    $s = $statusStyles[$r['status']] ?? $statusStyles['scheduled'];
                                    $isCompleted = $r['status'] === 'completed';
                                    $isOverdue = $r['status'] === 'overdue';
                                    $rowColor = $isCompleted ? 'var(--mute-soft)' : 'var(--ink)';
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($r['id']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col">
                                            <span class="text-[12px] font-medium mono" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($r['vehicle']) ?></span>
                                            <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($r['model']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($r['type']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono font-medium" style="color: <?= $isOverdue ? '#963B33' : ($isCompleted ? 'var(--mute-soft)' : 'var(--ink)') ?>;"><?= htmlspecialchars(date('d M Y', strtotime($r['date']))) ?></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($r['cost']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                        <a href="maintenance_details.php?id=<?= urlencode($r['id']) ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">View Ticket</a>
                                        <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($maintenance_records) ?> of Total Tickets</span>
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
<?php
session_start();
require_once '../../config/db.php';

$active_page = 'customer_tanks';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Assets', 'Customer Tanks'];

$kpiStmt = $pdo->query("
    SELECT 
        COUNT(id) AS total_tanks,
        SUM(CASE WHEN current_level_pct >= 30 THEN 1 ELSE 0 END) AS operational,
        SUM(CASE WHEN current_level_pct < 30 AND current_level_pct > 0 THEN 1 ELSE 0 END) AS refuel_needed,
        SUM(CASE WHEN status = 'maintenance' OR current_level_pct = 0 THEN 1 ELSE 0 END) AS maintenance_alerts
    FROM customer_tanks
");
$kpiData = $kpiStmt ? $kpiStmt->fetch(PDO::FETCH_ASSOC) : [];

$total_tanks        = (int)($kpiData['total_tanks'] ?? 0);
$operational        = (int)($kpiData['operational'] ?? 0);
$refuel_needed      = (int)($kpiData['refuel_needed'] ?? 0);
$maintenance_alerts = (int)($kpiData['maintenance_alerts'] ?? 0);

$operational_pct   = $total_tanks > 0 ? round(($operational / $total_tanks) * 100) : 0;
$refuel_needed_pct = $total_tanks > 0 ? round(($refuel_needed / $total_tanks) * 100) : 0;

$tanksStmt = $pdo->query("
    SELECT 
        t.*, 
        p.company_name AS client_name,
        p.phone AS client_phone
    FROM customer_tanks t
    LEFT JOIN partners p ON t.client_id = p.id
    ORDER BY t.current_level_pct ASC, t.id DESC
");
$tanks_data = $tanksStmt ? $tanksStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$statusStyles = [
    'optimal'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Normal'],
    'refuel_req'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Refuel Needed'],
    'critical'    => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Critical Level'],
    'maintenance' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Maintenance']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Tanks | I-GAS Enterprise</title>
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Client Asset Management</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Customer Bulk Tanks</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor static client installations, telemetry gas levels, scheduled refuels, and technical inspections.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="radio" class="w-4 h-4"></i>Sync Telemetry
                    </button>
                    <a href="new_tank.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Register Tank Asset
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Deployed Tanks</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_tanks) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Active on-site units</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Nominal Levels (30%+)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($operational) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="check" class="w-3 h-3"></i><?= $operational_pct ?>% adequate capacity
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Refuel Required (&lt;30%)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #7A5E1E;"><?= number_format($refuel_needed) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                            <i data-lucide="truck" class="w-3 h-3"></i>Dispatch tanker trip
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Critical / Maintenance</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($maintenance_alerts) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>Action required
                        </span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Installed Assets Directory</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search Tank SN or Client..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Gas Types</option>
                                <option>Liquid Oxygen</option>
                                <option>Liquid Nitrogen</option>
                                <option>Liquid Argon</option>
                                <option>LPG Bulk</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Active Tanks <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($tanks_data) ?></span></span>
                        <span class="tab-item">Refueling Queue</span>
                        <span class="tab-item">Inspection & Compliance</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Tank Serial / ID</th>
                                <th class="px-3 py-3 font-medium">Client & Location</th>
                                <th class="px-3 py-3 font-medium">Gas & Capacity</th>
                                <th class="px-3 py-3 font-medium">Telemetry Level</th>
                                <th class="px-3 py-3 font-medium text-right">Pressure</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($tanks_data)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-[13px]" style="color: var(--mute);">No client tanks registered yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($tanks_data as $tank): ?>
                            <?php
                                $level = (int)($tank['current_level_pct'] ?? 0);
                                $tankStatusKey = 'optimal';
                                if (($tank['status'] ?? '') === 'maintenance') {
                                    $tankStatusKey = 'maintenance';
                                } elseif ($level <= 15) {
                                    $tankStatusKey = 'critical';
                                } elseif ($level < 30) {
                                    $tankStatusKey = 'refuel_req';
                                }
                                $statusObj = $statusStyles[$tankStatusKey];

                                $barColor = '#45663F';
                                if ($level <= 15) {
                                    $barColor = '#963B33';
                                } elseif ($level < 30) {
                                    $barColor = '#9A7B2E';
                                }
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($tank['serial_number'] ?? 'TNK-'.$tank['id']) ?></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($tank['client_name'] ?? 'Unassigned') ?></span>
                                        <span class="text-[11.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($tank['installation_location'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($tank['gas_type'] ?? 'N/A') ?></span>
                                        <span class="text-[11.5px] mono" style="color: var(--mute);"><?= number_format((int)($tank['capacity_liters'] ?? 0)) ?> L</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 h-2 rounded-full overflow-hidden" style="background: var(--line-soft);">
                                            <div class="h-full rounded-full" style="width: <?= $level ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                        <span class="text-[12px] mono num font-medium" style="color: var(--ink);"><?= $level ?>%</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);">
                                    <?= htmlspecialchars($tank['working_pressure_bar'] ?? '0') ?> <span class="text-[11px] mono font-normal" style="color: var(--mute);">BAR</span>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="tank_details.php?id=<?= $tank['id'] ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="View Tank" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>
                                        <a href="schedule_refuel.php?tank_id=<?= $tank['id'] ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="Schedule Refuel" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($tanks_data) ?> of <?= count($tanks_data) ?> Customer Tanks</span>
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
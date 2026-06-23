<?php
$active_page = 'drivers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Licenses & Alerts'];

$alerts = [
    ['id' => 'DRV-106', 'name' => 'Yasser Abdullah', 'type' => 'Driver License', 'expiry' => '2026-06-30', 'days' => 7, 'status' => 'critical'],
    ['id' => 'FLT-004', 'name' => 'Volvo FH16', 'type' => 'Vehicle Registration', 'expiry' => '2026-06-25', 'days' => 2, 'status' => 'critical'],
    ['id' => 'DRV-102', 'name' => 'Mohammed Saad', 'type' => 'Hazmat Permit', 'expiry' => '2026-07-20', 'days' => 27, 'status' => 'warning'],
    ['id' => 'FLT-006', 'name' => 'Toyota Hilux', 'type' => 'Commercial Insurance', 'expiry' => '2026-07-25', 'days' => 32, 'status' => 'warning'],
    ['id' => 'DRV-105', 'name' => 'Khalid Hassan', 'type' => 'Driver License', 'expiry' => '2026-08-10', 'days' => 48, 'status' => 'warning'],
    ['id' => 'FLT-002', 'name' => 'Isuzu NPR', 'type' => 'Civil Defense Permit', 'expiry' => '2026-06-20', 'days' => -3, 'status' => 'expired'],
];

$total_alerts  = 24;
$critical_docs = 4;
$warning_docs  = 20;
$compliance    = 94.5;

$statusStyles = [
    'expired'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Expired'],
    'critical' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Action Required'],
    'warning'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Expiring Soon'],
];

$iconMap = [
    'Driver License'       => 'id-card',
    'Hazmat Permit'        => 'flame',
    'Vehicle Registration' => 'file-text',
    'Commercial Insurance' => 'shield',
    'Civil Defense Permit' => 'building-2',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenses & Alerts | I-GAS Enterprise</title>
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics &amp; Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Licenses &amp; Alerts</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor regulatory compliance, track expiring driver licenses, and manage vehicle documentation renewals.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Alert Log
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4"></i>Update Renewals
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Monitored Docs</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);">342</h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Across personnel &amp; fleet</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Critical / Expired</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= $critical_docs ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="alert-octagon" class="w-3 h-3"></i>Immediate Action
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Expiring Soon (30 Days)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $warning_docs ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="clock" class="w-3 h-3"></i>Requires processing
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Fleet Compliance Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $compliance ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $compliance ?>%; background: #45663F;"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Compliance Alert Registry</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search entity or ID..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Documents</option>
                                <option>Driver Licenses</option>
                                <option>Hazmat Permits</option>
                                <option>Vehicle Registrations</option>
                                <option>Insurances</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All Alerts <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $total_alerts ?></span></span>
                        <span class="tab-item text-red-700">Critical / Expired <span class="num text-[11px]" style="color: #963B33;"><?= $critical_docs ?></span></span>
                        <span class="tab-item">Warnings <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $warning_docs ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Target Entity</th>
                                <th class="px-3 py-3 font-medium">Document Type</th>
                                <th class="px-3 py-3 font-medium text-right">Expiry Date</th>
                                <th class="px-3 py-3 font-medium text-right">Days Left</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($alerts as $a): ?>
                            <?php
                                $statusObj = $statusStyles[$a['status']];
                                $icon = $iconMap[$a['type']] ?? 'file';
                                $isExpired = $a['status'] === 'expired';
                                $daysColor = $isExpired ? '#963B33' : ($a['status'] === 'critical' ? '#963B33' : 'var(--ink)');
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($a['name']) ?></span>
                                        <span class="text-[11px] mono" style="color: var(--mute);"><?= htmlspecialchars($a['id']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="<?= $icon ?>" class="w-4 h-4" style="color: var(--mute);"></i>
                                        <span style="color: var(--ink);"><?= htmlspecialchars($a['type']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-right font-medium mono" style="color: <?= $isExpired ? '#963B33' : 'var(--ink)' ?>;">
                                    <?= htmlspecialchars(date('d M Y', strtotime($a['expiry']))) ?>
                                </td>
                                <td class="px-3 py-3.5 text-right font-semibold num" style="color: <?= $daysColor ?>;">
                                    <?= $isExpired ? 'Expired' : $a['days'] ?>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <button class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">Renew</button>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($alerts) ?> of <?= $total_alerts ?> Pending Alerts</span>
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
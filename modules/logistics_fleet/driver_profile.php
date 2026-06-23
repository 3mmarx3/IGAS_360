<?php
$active_page = 'drivers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Driver Profile'];

$driver_id = $_GET['id'] ?? 'DRV-101';

$driver = [
    'id' => $driver_id,
    'name' => 'Ahmed Ali',
    'initials' => 'AA',
    'license' => 'Heavy / Hazmat',
    'license_no' => 'SA-98273645',
    'expiry' => '2027-05-12',
    'blood_type' => 'O+',
    'phone' => '+966 50 111 2233',
    'emergency_contact' => '+966 55 999 8877 (Brother)',
    'status' => 'active',
    'rating' => 4.8,
    'total_trips' => 342,
    'on_time_rate' => 96.5,
    'joined' => '2022-03-15',
    'vehicle_id' => 'FLT-001',
    'vehicle_name' => 'Mercedes Actros'
];

$trips = [
    ['id' => 'TRP-8832', 'date' => '2026-06-21', 'destination' => 'Jeddah Industrial City', 'status' => 'completed', 'distance' => '45'],
    ['id' => 'TRP-8815', 'date' => '2026-06-19', 'destination' => 'Yanbu Plant', 'status' => 'completed', 'distance' => '320'],
    ['id' => 'TRP-8799', 'date' => '2026-06-18', 'destination' => 'Mecca Client Site', 'status' => 'completed', 'distance' => '95'],
    ['id' => 'TRP-8780', 'date' => '2026-06-15', 'destination' => 'Maintenance (Workshop)', 'status' => 'completed', 'distance' => '12'],
    ['id' => 'TRP-8755', 'date' => '2026-06-12', 'destination' => 'Rabigh Facility', 'status' => 'completed', 'distance' => '150'],
    ['id' => 'TRP-8712', 'date' => '2026-06-08', 'destination' => 'SABIC Petrochemicals', 'status' => 'completed', 'distance' => '340'],
];

$statusStyles = [
    'active'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'On Duty'],
    'on_leave'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'On Leave'],
    'suspended' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Suspended'],
];

$tripStyles = [
    'completed'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'label' => 'Completed'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'label' => 'In Transit'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'label' => 'Cancelled'],
];

$ds = $statusStyles[$driver['status']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($driver['name']) ?> | I-GAS Enterprise</title>
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

        th, td { vertical-align: middle; }

        .avatar-sq { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px; }
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

            <a href="drivers_directory.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Drivers Directory
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <span class="flex items-center justify-center font-semibold rounded-md flex-shrink-0" style="width:56px; height:56px; font-size:18px; background:#EFEEEC; color:#5C5A56; border:1px solid #DEDCD7;"><?= htmlspecialchars($driver['initials']) ?></span>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($driver['name']) ?></h2>
                            <span class="pill" style="background: <?= $ds['bg'] ?>; color: <?= $ds['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $ds['dot'] ?>;"></span><?= $ds['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($driver['id']) ?> · <?= htmlspecialchars($driver['license']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="file-check" class="w-4 h-4"></i>View License
                    </button>
                    <a href="edit_driver.php?id=<?= $driver['id'] ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="pencil" class="w-4 h-4"></i>Edit Profile
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Safety &amp; Rating</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $driver['rating'] ?></h3>
                        <i data-lucide="star" class="w-5 h-5 fill-current text-yellow-500"></i>
                    </div>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">based on 120 reviews</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Trips</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($driver['total_trips']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>+12 this month
                        </span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">On-Time Delivery</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $driver['on_time_rate'] ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $driver['on_time_rate'] ?>%"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">License Expiry</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars(date('d M Y', strtotime($driver['expiry']))) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Valid</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Trips</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                View All<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                    <th class="pl-6 pr-3 py-3 font-medium">Trip Ref</th>
                                    <th class="px-3 py-3 font-medium">Date</th>
                                    <th class="px-3 py-3 font-medium">Destination</th>
                                    <th class="px-3 py-3 font-medium text-right">Distance</th>
                                    <th class="pr-6 py-3 font-medium text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($trips as $t): ?>
                                <?php $ts = $tripStyles[$t['status']]; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($t['id']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($t['date']))) ?></td>
                                    <td class="px-3 py-3.5 text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($t['destination']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= htmlspecialchars($t['distance']) ?> <span class="text-[11px] font-normal" style="color: var(--mute);">KM</span></td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background: <?= $ts['bg'] ?>; color: <?= $ts['fg'] ?>;">
                                            <?= $ts['label'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    
                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Driver Information</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Mobile Number</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($driver['phone']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">License Number</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($driver['license_no']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Blood Type</p>
                                <p class="info-value mono text-red-700" style="font-size:13.5px; font-weight: 600;"><?= htmlspecialchars($driver['blood_type']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Emergency Contact</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($driver['emergency_contact']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Date Joined</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('d M Y', strtotime($driver['joined']))) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Current Assignment</h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-sm bg-white border flex items-center justify-center flex-shrink-0" style="border-color: var(--line);">
                                    <i data-lucide="truck" class="w-5 h-5" style="color: var(--mute);"></i>
                                </div>
                                <div>
                                    <p class="text-[14px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($driver['vehicle_name']) ?></p>
                                    <p class="text-[12px] mono mt-0.5" style="color: var(--mute);"><?= htmlspecialchars($driver['vehicle_id']) ?></p>
                                </div>
                            </div>
                            <a href="vehicle_logs.php?id=<?= $driver['vehicle_id'] ?>" class="w-full btn-secondary py-2 rounded-sm text-[12.5px] font-medium">View Vehicle Logs</a>
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
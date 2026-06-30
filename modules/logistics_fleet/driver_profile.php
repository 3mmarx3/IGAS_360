<?php
require_once '../../config/db.php';

$active_page = 'drivers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Drivers Directory', 'Driver Profile'];

$driver_id_param = $_GET['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_action'])) {
    $manifest_id_action = $_POST['manifest_id'] ?? null;
    $trip_action = $_POST['trip_action'];

    if ($manifest_id_action !== null && in_array($trip_action, ['accept', 'reject'])) {
        $new_status = $trip_action === 'accept' ? 'in_transit' : 'cancelled';
        $stmt_update = $pdo->prepare("UPDATE dispatches SET status = ? WHERE manifest_id = ?");
        $stmt_update->execute([$new_status, $manifest_id_action]);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . urlencode($driver_id_param));
    exit;
}

$stmt = $pdo->prepare("SELECT d.*, v.make_model as vehicle_name FROM drivers d LEFT JOIN vehicles v ON d.assigned_vehicle = v.fleet_id WHERE d.driver_id = ? OR d.id = ? LIMIT 1");
$stmt->execute([$driver_id_param, $driver_id_param]);
$db_driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_driver) {
    $db_driver = $pdo->query("SELECT d.*, v.make_model as vehicle_name FROM drivers d LEFT JOIN vehicles v ON d.assigned_vehicle = v.fleet_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$db_driver) {
    die("No drivers found in the database.");
}

$internal_id = $db_driver['id'];
$driver_code = $db_driver['driver_id'];
$stmt_trips_count = $pdo->prepare("SELECT COUNT(*) FROM dispatches WHERE driver_id = ?");
$stmt_trips_count->execute([$driver_code]);
$total_trips = $stmt_trips_count->fetchColumn();

$stmt_trips = $pdo->prepare("
    SELECT 
        manifest_id as id,
        order_ref,
        destination,
        dispatch_date as date,
        eta_time,
        status,
        distance,
        instructions,
        vehicle_id,
        created_at
    FROM dispatches 
    WHERE driver_id = ? 
    ORDER BY dispatch_date DESC 
    LIMIT 6
");
$stmt_trips->execute([$driver_code]);
$trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

$license_map = [
    'heavy_hazmat' => 'Heavy / Hazmat',
    'heavy'        => 'Heavy Transport',
    'light'        => 'Light Commercial',
    'private'      => 'Private'
];

$words = explode(' ', trim($db_driver['full_name']));
$initials = '';
foreach ($words as $w) {
    if (!empty($w)) {
        $initials .= strtoupper($w[0]);
    }
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) {
    $initials = 'U';
}

$driver = [
    'internal_id' => $db_driver['id'],
    'id' => $db_driver['driver_id'],
    'name' => $db_driver['full_name'],
    'initials' => $initials,
    'license' => $license_map[$db_driver['license_class']] ?? ucfirst($db_driver['license_class']),
    'license_no' => $db_driver['license_number'],
    'expiry' => $db_driver['license_expiry'],
    'blood_type' => $db_driver['blood_type'] ?? 'O+',
    'phone' => $db_driver['mobile_number'],
    'emergency_contact' => $db_driver['emergency_contact'] ?? 'Not Specified',
    'status' => strtolower($db_driver['status']),
    'rating' => $db_driver['rating'] ?? 5.0,
    'total_trips' => $total_trips,
    'on_time_rate' => $db_driver['on_time_rate'] ?? 100,
    'joined' => date('Y-m-d', strtotime($db_driver['created_at'])),
    'vehicle_id' => ($db_driver['assigned_vehicle'] !== 'unassigned' && !empty($db_driver['assigned_vehicle'])) ? $db_driver['assigned_vehicle'] : 'Unassigned',
    'vehicle_name' => $db_driver['vehicle_name'] ?? 'No Vehicle Assigned'
];

$statusStyles = [
    'active'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'On Duty'],
    'on_leave'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'On Leave'],
    'suspended' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Suspended'],
];

$tripStyles = [
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'label' => 'Completed'],
    'completed'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'label' => 'Completed'],
    'dispatched' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'label' => 'Pending'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'label' => 'In Transit'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'label' => 'Cancelled'],
];

$ds = $statusStyles[$driver['status']] ?? $statusStyles['active'];
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

        .btn-accept { background: #45663F; color: #fff; border: 1px solid #45663F; transition: background-color 0.15s ease; cursor: pointer; display: inline-flex; justify-content: center; align-items: center; }
        .btn-accept:hover { background: #38522F; }
        .btn-reject { background: #fff; color: #963B33; border: 1px solid #963B33; transition: background-color 0.15s ease; cursor: pointer; display: inline-flex; justify-content: center; align-items: center; }
        .btn-reject:hover { background: #F8E9E7; }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }

        th, td { vertical-align: middle; }

        .avatar-sq { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px; }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .info-row { padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(26,26,26,0.45);
            display: none; align-items: center; justify-content: center;
            z-index: 100; padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: var(--paper); border-radius: 6px; width: 100%; max-width: 480px;
            max-height: 90vh; overflow-y: auto; border: 1px solid var(--line-soft);
        }
        .modal-detail-row { padding: 10px 0; border-bottom: 1px solid var(--line-soft); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .modal-detail-row:last-child { border-bottom: none; }
        .modal-detail-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; flex-shrink: 0; }
        .modal-detail-value { font-size: 13px; color: var(--ink); font-weight: 500; text-align: right; }
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
                    <a href="edit_driver.php?id=<?= htmlspecialchars($driver['internal_id']) ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="pencil" class="w-4 h-4"></i>Edit Profile
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Safety &amp; Rating</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars($driver['rating']) ?></h3>
                        <i data-lucide="star" class="w-5 h-5 fill-current text-yellow-500"></i>
                    </div>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">System calculated</span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Trips</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($driver['total_trips']) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>Lifetime
                        </span>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">On-Time Delivery</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars($driver['on_time_rate']) ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= htmlspecialchars($driver['on_time_rate']) ?>%"></div>
                    </div>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">License Expiry</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars(date('d M Y', strtotime($driver['expiry']))) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <?php if (strtotime($driver['expiry']) > time()): ?>
                            <span style="color: var(--mute);">Valid</span>
                        <?php else: ?>
                            <span style="color: #963B33;">Expired</span>
                        <?php endif; ?>
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
                                    <th class="pl-6 pr-3 py-3 font-medium">Order Ref</th>
                                    <th class="px-3 py-3 font-medium">Destination</th>
                                    <th class="px-3 py-3 font-medium">Date &amp; Time</th>
                                    <th class="px-3 py-3 font-medium text-right">Status</th>
                                    <th class="pr-6 py-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($trips as $t): ?>
                                <?php
                                    $ts = $tripStyles[strtolower($t['status'])] ?? $tripStyles['dispatched'];
                                    $formatted_date = !empty($t['date']) ? date('d M Y', strtotime($t['date'])) : '--';
                                    $formatted_time = !empty($t['eta_time']) ? date('H:i', strtotime($t['eta_time'])) : '--:--';
                                    $is_pending = strtolower($t['status']) === 'dispatched';
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($t['order_ref']) ?></td>
                                    <td class="px-3 py-3.5 text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($t['destination']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($formatted_date) ?> · <?= htmlspecialchars($formatted_time) ?></td>
                                    <td class="px-3 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background: <?= $ts['bg'] ?>; color: <?= $ts['fg'] ?>;">
                                            <?= $ts['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button type="button"
                                                class="trip-view-btn transition-colors"
                                                style="color: var(--mute); background: none; border: none; padding: 0;"
                                                title="Manage Trip"
                                                onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'"
                                                data-manifest="<?= htmlspecialchars($t['id']) ?>"
                                                data-order="<?= htmlspecialchars($t['order_ref']) ?>"
                                                data-destination="<?= htmlspecialchars($t['destination']) ?>"
                                                data-date="<?= htmlspecialchars($formatted_date) ?>"
                                                data-time="<?= htmlspecialchars($formatted_time) ?>"
                                                data-vehicle="<?= htmlspecialchars($t['vehicle_id']) ?>"
                                                data-distance="<?= htmlspecialchars($t['distance']) ?>"
                                                data-instructions="<?= htmlspecialchars($t['instructions'] ?? '') ?>"
                                                data-status="<?= htmlspecialchars($t['status']) ?>"
                                                data-status-label="<?= htmlspecialchars($ts['label']) ?>"
                                                data-status-bg="<?= htmlspecialchars($ts['bg']) ?>"
                                                data-status-fg="<?= htmlspecialchars($ts['fg']) ?>"
                                                data-pending="<?= $is_pending ? '1' : '0' ?>"
                                            >
                                                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                                            </button>
                                            
                                            <a href="order_details.php?order_num=<?= urlencode($t['order_ref']) ?>" class="transition-colors" style="color: var(--mute); display: inline-flex;" title="View Full Order Details" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($trips)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-[13.5px]" style="color: var(--mute);">No recent trips recorded for this driver.</td>
                                </tr>
                                <?php endif; ?>
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
                            <?php if ($driver['vehicle_id'] !== 'Unassigned'): ?>
                            <a href="vehicle_logs.php?id=<?= htmlspecialchars($driver['vehicle_id']) ?>" class="w-full btn-secondary py-2 rounded-sm text-[12.5px] font-medium">View Vehicle Logs</a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <div class="modal-overlay" id="tripModalOverlay">
        <div class="modal-box">
            <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Trip Details</p>
                    <h3 class="text-[17px] font-semibold tracking-tight num" style="color: var(--ink);" id="modalManifestId">--</h3>
                </div>
                <button type="button" id="modalCloseBtn" style="color: var(--mute); background: none; border: none; cursor: pointer;">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="px-6 py-2">
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Order Ref</span>
                    <span class="modal-detail-value mono" id="modalOrderRef">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Destination</span>
                    <span class="modal-detail-value" id="modalDestination">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Date</span>
                    <span class="modal-detail-value mono" id="modalDate">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">ETA Time</span>
                    <span class="modal-detail-value mono" id="modalTime">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Vehicle</span>
                    <span class="modal-detail-value mono" id="modalVehicle">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Distance</span>
                    <span class="modal-detail-value mono" id="modalDistance">--</span>
                </div>
                <div class="modal-detail-row">
                    <span class="modal-detail-label">Status</span>
                    <span class="modal-detail-value" id="modalStatus">--</span>
                </div>
                <div class="modal-detail-row" style="flex-direction: column; align-items: flex-start;">
                    <span class="modal-detail-label mb-2">Instructions</span>
                    <span class="modal-detail-value" style="text-align: left; font-weight: 400;" id="modalInstructions">--</span>
                </div>
            </div>

            <form method="POST" action="" id="tripActionForm" class="px-6 py-5 border-t flex gap-3" style="border-color: var(--line-soft);" id="modalActionsWrap">
                <input type="hidden" name="manifest_id" id="modalActionManifestId" value="">
                <input type="hidden" name="trip_action" id="modalActionType" value="">
                <button type="submit" id="modalRejectBtn" class="btn-reject flex-1 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center justify-center gap-2">
                    <i data-lucide="x-circle" class="w-4 h-4"></i>Reject
                </button>
                <button type="submit" id="modalAcceptBtn" class="btn-accept flex-1 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>Accept
                </button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const modalOverlay = document.getElementById('tripModalOverlay');
        const modalCloseBtn = document.getElementById('modalCloseBtn');
        const modalActionsWrap = document.getElementById('tripActionForm');
        const modalAcceptBtn = document.getElementById('modalAcceptBtn');
        const modalRejectBtn = document.getElementById('modalRejectBtn');
        const modalActionManifestId = document.getElementById('modalActionManifestId');
        const modalActionType = document.getElementById('modalActionType');

        document.querySelectorAll('.trip-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('modalManifestId').textContent = btn.dataset.manifest;
                document.getElementById('modalOrderRef').textContent = btn.dataset.order;
                document.getElementById('modalDestination').textContent = btn.dataset.destination;
                document.getElementById('modalDate').textContent = btn.dataset.date;
                document.getElementById('modalTime').textContent = btn.dataset.time;
                document.getElementById('modalVehicle').textContent = btn.dataset.vehicle;
                document.getElementById('modalDistance').textContent = btn.dataset.distance + ' KM';
                document.getElementById('modalInstructions').textContent = btn.dataset.instructions && btn.dataset.instructions.trim() !== '' ? btn.dataset.instructions : 'No special instructions provided.';

                const statusBadge = document.getElementById('modalStatus');
                statusBadge.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium" style="background:' + btn.dataset.statusBg + '; color:' + btn.dataset.statusFg + ';">' + btn.dataset.statusLabel + '</span>';

                modalActionManifestId.value = btn.dataset.manifest;

                if (btn.dataset.pending === '1') {
                    modalActionsWrap.style.display = 'flex';
                } else {
                    modalActionsWrap.style.display = 'none';
                }

                modalOverlay.classList.add('show');
                lucide.createIcons();
            });
        });

        modalAcceptBtn.addEventListener('click', function () {
            modalActionType.value = 'accept';
        });
        modalRejectBtn.addEventListener('click', function () {
            modalActionType.value = 'reject';
        });

        modalCloseBtn.addEventListener('click', function () {
            modalOverlay.classList.remove('show');
        });
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('show');
            }
        });
    </script>
</body>
</html>
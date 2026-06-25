<?php
require_once '../../config/db.php';

$active_page = 'drivers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Licenses & Alerts'];

$renew_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
    $entity_id = $_POST['entity_id'] ?? '';
    $doc_type  = $_POST['doc_type'] ?? '';
    $new_date  = date('Y-m-d', strtotime('+1 year'));

    if ($doc_type === 'Driver License') {
        $stmt = $pdo->prepare("UPDATE drivers SET license_expiry = ? WHERE driver_id = ?");
        $stmt->execute([$new_date, $entity_id]);
    } elseif ($doc_type === 'Medical Certificate') {
        $stmt = $pdo->prepare("UPDATE drivers SET medical_expiry = ? WHERE driver_id = ?");
        $stmt->execute([$new_date, $entity_id]);
    } elseif ($doc_type === 'Vehicle Registration') {
        $stmt = $pdo->prepare("UPDATE vehicles SET registration_expiry = ? WHERE fleet_id = ?");
        $stmt->execute([$new_date, $entity_id]);
    } elseif ($doc_type === 'Commercial Insurance') {
        $stmt = $pdo->prepare("UPDATE vehicles SET insurance_expiry = ? WHERE fleet_id = ?");
        $stmt->execute([$new_date, $entity_id]);
    }
    
    $renew_message = "Document {$doc_type} for ID {$entity_id} successfully renewed until {$new_date}.";
}

$stmt = $pdo->query("
    SELECT driver_id as id, full_name as name, 'Driver License' as type, license_expiry as expiry FROM drivers WHERE license_expiry IS NOT NULL
    UNION ALL
    SELECT driver_id, full_name, 'Medical Certificate', medical_expiry FROM drivers WHERE medical_expiry IS NOT NULL
    UNION ALL
    SELECT fleet_id, make_model, 'Vehicle Registration', registration_expiry FROM vehicles WHERE registration_expiry IS NOT NULL
    UNION ALL
    SELECT fleet_id, make_model, 'Commercial Insurance', insurance_expiry FROM vehicles WHERE insurance_expiry IS NOT NULL
");
$all_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$alerts = [];
$total_monitored = count($all_docs);
$critical_docs = 0;
$warning_docs  = 0;
$valid_docs = 0;

$today = new DateTime();

foreach ($all_docs as $doc) {
    $exp = new DateTime($doc['expiry']);
    $diff = $today->diff($exp);
    $days = (int)$diff->format('%R%a');

    $status = 'active';
    if ($days < 0) {
        $status = 'expired';
        $critical_docs++;
    } elseif ($days <= 7) {
        $status = 'critical';
        $critical_docs++;
    } elseif ($days <= 30) {
        $status = 'warning';
        $warning_docs++;
    } else {
        $valid_docs++;
    }

    if ($status !== 'active') {
        $alerts[] = [
            'id'     => $doc['id'],
            'name'   => $doc['name'],
            'type'   => $doc['type'],
            'expiry' => $doc['expiry'],
            'days'   => $days,
            'status' => $status
        ];
    }
}

usort($alerts, fn($a, $b) => $a['days'] <=> $b['days']);

$total_alerts = count($alerts);
$compliance = $total_monitored > 0 ? round(($valid_docs / $total_monitored) * 100, 1) : 100;

$statusStyles = [
    'expired'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Expired'],
    'critical' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Action Required'],
    'warning'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Expiring Soon'],
];

$iconMap = [
    'Driver License'       => 'id-card',
    'Medical Certificate'  => 'activity',
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

            <?php if (!empty($renew_message)): ?>
            <div class="mb-6 px-4 py-3 rounded-md text-[13.5px] font-medium flex items-center gap-3" style="background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9;">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <?= htmlspecialchars($renew_message) ?>
            </div>
            <?php endif; ?>

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
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_monitored ?></h3>
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
                                    <form method="POST" action="" class="m-0 p-0 inline-block">
                                        <input type="hidden" name="action" value="renew">
                                        <input type="hidden" name="entity_id" value="<?= htmlspecialchars($a['id']) ?>">
                                        <input type="hidden" name="doc_type" value="<?= htmlspecialchars($a['type']) ?>">
                                        <button type="submit" class="text-[12px] font-medium bg-transparent cursor-pointer" style="color: var(--ink); border: none; border-bottom: 1px solid var(--ink); padding: 0;">Renew</button>
                                    </form>
                                    <button class="transition-colors bg-transparent border-none cursor-pointer" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($alerts)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-[13.5px]" style="color: var(--mute);">No compliance alerts at this moment. All documents are up to date.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($alerts) > 0 ? '1' : '0' ?>–<?= count($alerts) ?> of <?= $total_alerts ?> Pending Alerts</span>
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
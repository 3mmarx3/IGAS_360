<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $sku_to_delete = $_POST['sku'] ?? '';
    if (!empty($sku_to_delete)) {
        $delStmt = $pdo->prepare("DELETE FROM cylinder_batches WHERE sku = ?");
        $delStmt->execute([$sku_to_delete]);
        header("Location: cylinders_inventory.php");
        exit;
    }
}

$active_page = 'cylinders_inventory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Cylinders Inventory'];

$kpiStmt = $pdo->query("
    SELECT 
        COUNT(id) AS total_assets,
        SUM(CASE WHEN status = 'in_plant' THEN 1 ELSE 0 END) AS in_plant,
        SUM(CASE WHEN status = 'with_client' THEN 1 ELSE 0 END) AS with_clients,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM cylinders
");
$kpiData = $kpiStmt->fetch(PDO::FETCH_ASSOC);

$total_assets = (int)($kpiData['total_assets'] ?? 0);
$in_plant     = (int)($kpiData['in_plant'] ?? 0);
$with_clients = (int)($kpiData['with_clients'] ?? 0);
$maintenance  = (int)($kpiData['maintenance'] ?? 0);

$in_plant_pct = $total_assets > 0 ? round(($in_plant / $total_assets) * 100) : 0;
$with_clients_pct = $total_assets > 0 ? round(($with_clients / $total_assets) * 100) : 0;

$cylStmt = $pdo->query("
    SELECT 
        cb.sku, 
        MAX(cb.gas_classification) as gas, 
        MAX(cb.volume) as size,
        COUNT(c.id) as total,
        SUM(CASE WHEN c.status = 'in_plant' THEN 1 ELSE 0 END) as plant,
        SUM(CASE WHEN c.status = 'with_client' THEN 1 ELSE 0 END) as clients,
        SUM(CASE WHEN c.status = 'maintenance' THEN 1 ELSE 0 END) as maint
    FROM cylinder_batches cb
    LEFT JOIN cylinders c ON cb.id = c.batch_id
    GROUP BY cb.sku
    ORDER BY total DESC
");
$cylinders_data = $cylStmt->fetchAll(PDO::FETCH_ASSOC);

$cylinders = [];
foreach ($cylinders_data as $row) {
    $plant = (int)$row['plant'];
    $total = (int)$row['total'];
    
    $status = 'optimal';
    if ($total > 0 && ($plant / $total) <= 0.20) {
        $status = 'low';
    } elseif ($plant == 0 && $total > 0) {
        $status = 'low';
    }
    
    $cylinders[] = [
        'sku'     => $row['sku'],
        'gas'     => $row['gas'],
        'size'    => $row['size'],
        'total'   => $total,
        'plant'   => $plant,
        'clients' => (int)$row['clients'],
        'maint'   => (int)$row['maint'],
        'status'  => $status
    ];
}

$statusStyles = [
    'optimal' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Healthy'],
    'low'     => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Low at Plant'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cylinders Inventory | I-GAS Enterprise</title>
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Production & Inventory</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Cylinders Inventory</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Track physical cylinder assets, plant availability, client holding, and maintenance schedules.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="scan-line" class="w-4 h-4"></i>Scan Barcode
                    </button>
                    <a href="new_cylinder.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Register Batch
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Assets Owned</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_assets) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Registered company units</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">In Plant (Ready/Empty)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($in_plant) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="warehouse" class="w-3 h-3"></i><?= $in_plant_pct ?>% of total inventory
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">With Clients (Circulation)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($with_clients) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">
                            <i data-lucide="users" class="w-3 h-3"></i><?= $with_clients_pct ?>% of total inventory
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Maintenance & Scrapped</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($maintenance) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="wrench" class="w-3 h-3"></i>Hydro-testing required
                        </span>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Cylinders Database</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search SKU or gas type..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Sizes</option>
                                <option>50L</option>
                                <option>40L</option>
                                <option>10L</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Inventory Overview <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($cylinders) ?></span></span>
                        <span class="tab-item">Hydro-Testing</span>
                        <span class="tab-item">Scrapped Units</span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Type / SKU</th>
                                <th class="px-3 py-3 font-medium">Gas & Volume</th>
                                <th class="px-3 py-3 font-medium text-right">Total Owned</th>
                                <th class="px-3 py-3 font-medium text-right">In Plant</th>
                                <th class="px-3 py-3 font-medium text-right">With Clients</th>
                                <th class="px-3 py-3 font-medium text-right">Maint.</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($cylinders as $c): ?>
                            <?php
                                $statusObj = $statusStyles[$c['status']];
                                $plantColor = $c['status'] === 'low' ? '#963B33' : 'var(--ink)';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($c['sku']) ?></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($c['gas']) ?></span>
                                        <span class="text-[11.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($c['size']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($c['total']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $plantColor ?>;"><?= number_format($c['plant']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--mute);"><?= number_format($c['clients']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--mute);"><?= number_format($c['maint']) ?></td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="cylinder_details.php?sku=<?= urlencode($c['sku']) ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="View Details" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>
                                        <form method="POST" action="" class="m-0 p-0 inline-block" onsubmit="return confirm('Are you sure you want to delete this cylinder record?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="sku" value="<?= htmlspecialchars($c['sku']) ?>">
                                            <button type="submit" class="transition-colors bg-transparent border-none cursor-pointer flex items-center" style="color: #963B33; padding: 0;" title="Delete Cylinder" onmouseover="this.style.color='#7a2d26'" onmouseout="this.style.color='#963B33'">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($cylinders) ?> of <?= count($cylinders) ?> Asset Types</span>
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
<?php
session_start();
require_once '../../config/db.php';

$active_page = 'raw_materials';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Raw Materials'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;

    if ($delete_id > 0) {
        $del = $pdo->prepare("DELETE FROM raw_materials WHERE id = :id");
        $del->execute(['id' => $delete_id]);
    }

    header('Location: raw_materials.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        rm.id AS row_id,
        rm.material_sku,
        rm.material_name,
        rm.category,
        rm.current_stock,
        rm.unit,
        rm.safety_stock_threshold,
        rm.stock_status,
        rm.unit_cost,
        p.company_name AS supplier_name
    FROM raw_materials rm
    JOIN partners p ON rm.supplier_id = p.id
    ORDER BY rm.created_at DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$materials = [];
$total_items   = 0;
$low_stock     = 0;
$out_of_stock  = 0;
$total_value   = 0;

foreach ($rows as $row) {
    $status = $row['stock_status'];

    if ((float)$row['current_stock'] <= 0) {
        $status = 'out';
    } elseif ((float)$row['current_stock'] <= (float)$row['safety_stock_threshold']) {
        $status = 'low';
    } else {
        $status = 'optimal';
    }

    $total_items++;
    if ($status === 'low') { $low_stock++; }
    if ($status === 'out') { $out_of_stock++; }

    $total_value += (float)$row['current_stock'] * (float)$row['unit_cost'];

    $materials[] = [
        'row_id' => $row['row_id'],
        'id' => $row['material_sku'],
        'name' => $row['material_name'],
        'category' => $row['category'],
        'stock' => (float)$row['current_stock'],
        'unit' => $row['unit'],
        'threshold' => (float)$row['safety_stock_threshold'],
        'supplier' => $row['supplier_name'],
        'status' => $status,
    ];
}

$low_stock_list = array_values(array_filter($materials, fn($m) => $m['status'] === 'low'));
$out_of_stock_list = array_values(array_filter($materials, fn($m) => $m['status'] === 'out'));

$statusStyles = [
    'optimal' => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'In Stock'],
    'low'     => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Low Stock'],
    'out'     => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Out of Stock'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Materials | I-GAS Enterprise</title>
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

        <?php if (isset($_GET['created'])): ?>
        <div class="px-8 pt-5">
            <div class="rounded-sm px-4 py-3 text-[13px] flex items-center gap-2" style="background:#EAF1E7; color:#45663F; border:1px solid #D3E3CE;">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i>Material saved successfully.
            </div>
        </div>
        <?php endif; ?>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Production & Inventory</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Raw Materials</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Monitor bulk gases, chemicals, and physical components required for production.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Inventory
                    </button>
                    <a href="new_material.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Add Material
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Stocked Items</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_items) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Unique SKUs in catalog</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Low Stock Alerts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($low_stock) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>Requires Reorder
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Out of Stock</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($out_of_stock) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="x-circle" class="w-3 h-3"></i>Depleted inventory
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Inventory Value</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_value) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: 75%; background: var(--ink);"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Materials Master List</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search ID or name..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Categories</option>
                                <option>Bulk Gas</option>
                                <option>Chemicals</option>
                                <option>Containers</option>
                                <option>Spare Parts</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All Items <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($materials) ?></span></span>
                        <span class="tab-item">Low Stock <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($low_stock_list) ?></span></span>
                        <span class="tab-item text-red-700">Out of Stock <span class="num text-[11px]" style="color: #963B33;"><?= count($out_of_stock_list) ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Material ID</th>
                                <th class="px-3 py-3 font-medium">Description</th>
                                <th class="px-3 py-3 font-medium">Category</th>
                                <th class="px-3 py-3 font-medium text-right">Current Stock</th>
                                <th class="px-3 py-3 font-medium">Primary Supplier</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($materials as $m): ?>
                            <?php
                                $statusObj = $statusStyles[$m['status']];
                                $isOut = $m['status'] === 'out';
                                $rowColor = $isOut ? 'var(--mute-soft)' : 'var(--ink)';
                                $stockColor = $m['status'] === 'low' ? '#7A5E1E' : ($isOut ? '#963B33' : 'var(--ink)');
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($m['id']) ?></td>
                                <td class="px-3 py-3.5 font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($m['name']) ?></td>
                                <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($m['category']) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $stockColor ?>;">
                                    <?= number_format($m['stock']) ?> <span class="text-[11.5px] font-normal mono ml-1" style="color: var(--mute);"><?= htmlspecialchars($m['unit']) ?></span>
                                </td>
                                <td class="px-3 py-3.5 text-[12.5px]" style="color: var(--mute);"><?= htmlspecialchars($m['supplier']) ?></td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="material_details.php?id=<?= urlencode($m['row_id']) ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="View Details" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </a>
                                        <form method="POST" action="" class="m-0 p-0 inline-block" onsubmit="return confirm('Are you sure you want to delete this material?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="material_id" value="<?= htmlspecialchars($m['row_id']) ?>">
                                            <button type="submit" class="transition-colors bg-transparent border-none cursor-pointer flex items-center" style="color: #963B33; padding: 0;" title="Delete Material" onmouseover="this.style.color='#7a2d26'" onmouseout="this.style.color='#963B33'">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-6 text-sm" style="color: var(--mute);">لا توجد مواد خام مسجلة حالياً.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($materials) > 0 ? '1' : '0' ?>–<?= count($materials) ?> of <?= count($materials) ?> Items</span>
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
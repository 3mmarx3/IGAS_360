<?php
$active_page = 'raw_materials';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Raw Materials'];

$materials = [
    ['id' => 'RM-1001', 'name' => 'Liquid Oxygen (LOX)', 'category' => 'Bulk Gas', 'stock' => 45000, 'unit' => 'Liters', 'threshold' => 10000, 'supplier' => 'Gulf Industrial Gases', 'status' => 'optimal'],
    ['id' => 'RM-1002', 'name' => 'Calcium Carbide', 'category' => 'Chemicals', 'stock' => 1200, 'unit' => 'KG', 'threshold' => 2000, 'supplier' => 'Chemical Solutions Co.', 'status' => 'low'],
    ['id' => 'RM-1003', 'name' => 'Acetone', 'category' => 'Chemicals', 'stock' => 850, 'unit' => 'Liters', 'threshold' => 500, 'supplier' => 'Chemical Solutions Co.', 'status' => 'optimal'],
    ['id' => 'RM-1004', 'name' => 'Empty Cylinders (50L)', 'category' => 'Containers', 'stock' => 120, 'unit' => 'Units', 'threshold' => 150, 'supplier' => 'Modern Steel Fabricators', 'status' => 'low'],
    ['id' => 'RM-1005', 'name' => 'Brass Valves (Standard)', 'category' => 'Spare Parts', 'stock' => 0, 'unit' => 'Units', 'threshold' => 50, 'supplier' => 'Advanced Valve Systems', 'status' => 'out'],
    ['id' => 'RM-1006', 'name' => 'Liquid Nitrogen (LIN)', 'category' => 'Bulk Gas', 'stock' => 28000, 'unit' => 'Liters', 'threshold' => 8000, 'supplier' => 'Gulf Industrial Gases', 'status' => 'optimal'],
    ['id' => 'RM-1007', 'name' => 'Industrial Paint (Green)', 'category' => 'Consumables', 'stock' => 45, 'unit' => 'Gallons', 'threshold' => 20, 'supplier' => 'National Energy Resources', 'status' => 'optimal'],
];

$total_items = 142;
$low_stock   = 18;
$out_of_stock= 3;
$total_value = 845200;

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
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_items ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Unique SKUs in catalog</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Low Stock Alerts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $low_stock ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>Requires Reorder
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Out of Stock</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= $out_of_stock ?></h3>
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
                        <span class="tab-item">Low Stock <span class="num text-[11px]" style="color: var(--mute-soft);">2</span></span>
                        <span class="tab-item text-red-700">Out of Stock <span class="num text-[11px]" style="color: #963B33;">1</span></span>
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
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <a href="material_details.php?id=<?= $m['id'] ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">View</a>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($materials) ?> of <?= $total_items ?> Items</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">2</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm text-[12px] font-medium mono" style="border-color: var(--line); color: var(--ink);">3</button>
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
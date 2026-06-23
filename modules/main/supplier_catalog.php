<?php
$active_page = 'supplier_catalog';
$base_url    = '../../';
$breadcrumb  = ['Supplier Portal', 'Inventory', 'Catalog Management'];

$supplier_name = 'Gulf Industrial Gases';
$supplier_id   = 'SUP-5001';

$catalog_items = [
    ['code' => 'ITM-9021', 'name' => 'Liquid Argon (LAr)', 'type' => 'Bulk Liquid', 'purity' => '99.999%', 'price' => 120.00, 'unit' => 'Liter', 'status' => 'active'],
    ['code' => 'ITM-9022', 'name' => 'Helium (He)', 'type' => 'Compressed Gas', 'purity' => '99.99%', 'price' => 850.00, 'unit' => '50L Cylinder', 'status' => 'active'],
    ['code' => 'ITM-9025', 'name' => 'Calcium Carbide', 'type' => 'Raw Material', 'purity' => 'Industrial', 'price' => 4500.00, 'unit' => 'Ton', 'status' => 'out_of_stock'],
    ['code' => 'ITM-9028', 'name' => 'Medical Oxygen', 'type' => 'Compressed Gas', 'purity' => '99.5%', 'price' => 150.00, 'unit' => '40L Cylinder', 'status' => 'pending'],
];

$statusStyles = [
    'active'       => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active & Listed'],
    'out_of_stock' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Out of Stock'],
    'pending'      => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Awaiting Approval'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog Management | I-GAS Supplier Portal</title>
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
        
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

        <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
            <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
                <span class="status-dot" style="background: #5C8A5C;"></span>Supplier Link Active
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Entity · <?= htmlspecialchars($supplier_name) ?></span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">ID · <?= $supplier_id ?></span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Product Catalog Management</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage your supply items, update pricing, and list new materials for the I-GAS procurement network.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Price List
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-7 items-start">
                
                <div class="xl:col-span-1">
                    <form action="supplier_catalog.php" method="POST" class="card rounded-md p-6" style="background: var(--paper-deep);">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                            <i data-lucide="plus-circle" class="w-4 h-4" style="color: var(--mute);"></i>List New Supply Item
                        </h3>
                        
                        <div class="flex flex-col gap-5">
                            <div>
                                <label class="form-label">Material / Gas Name</label>
                                <input type="text" class="form-input bg-white" placeholder="e.g. High Purity Nitrogen" required>
                            </div>
                            
                            <div>
                                <label class="form-label">Supply Category</label>
                                <select class="form-select bg-white" required>
                                    <option value="" disabled selected>Select category...</option>
                                    <option>Bulk Liquid Gas</option>
                                    <option>Compressed Gas Cylinders</option>
                                    <option>Raw Industrial Materials</option>
                                    <option>Logistics & Equipment</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Purity / Grade</label>
                                    <input type="text" class="form-input bg-white" placeholder="e.g. 99.99%">
                                </div>
                                <div>
                                    <label class="form-label">Billing Unit</label>
                                    <select class="form-select bg-white">
                                        <option>Liter</option>
                                        <option>Ton</option>
                                        <option>50L Cylinder</option>
                                        <option>40L Cylinder</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Base Unit Price (SAR)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[12.5px] text-[var(--mute)]">SAR</span>
                                    <input type="number" step="0.01" class="form-input bg-white pl-10 num" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="btn-primary w-full py-2.5 rounded-sm text-[13.5px] font-medium flex items-center justify-center gap-2">
                                    Submit for Approval
                                </button>
                                <p class="text-[11px] text-center mt-3" style="color: var(--mute);">New items require verification from I-GAS Procurement before becoming active.</p>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="xl:col-span-2">
                    <div class="card rounded-md flex flex-col overflow-hidden h-full">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Active Catalog Ledger</h3>
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search by name or code..." class="pl-8 pr-3 py-1.5 bg-[var(--paper-deep)] border border-transparent rounded-sm text-[12px] w-64 outline-none focus:border-[var(--line)] transition-colors">
                            </div>
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                        <th class="pl-6 py-3 font-medium">Item Details</th>
                                        <th class="px-3 py-3 font-medium">Category / Grade</th>
                                        <th class="px-3 py-3 font-medium text-right">Unit Price</th>
                                        <th class="px-3 py-3 font-medium text-center">Listing Status</th>
                                        <th class="pr-6 py-3 font-medium text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php foreach ($catalog_items as $item): ?>
                                    <?php $style = $statusStyles[$item['status']]; ?>
                                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                        <td class="pl-6 py-3.5">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($item['name']) ?></span>
                                                <span class="text-[11px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($item['code']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[12.5px]" style="color: var(--ink);"><?= htmlspecialchars($item['type']) ?></span>
                                                <span class="text-[11.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($item['purity']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5 text-right">
                                            <span class="font-semibold num" style="color: var(--ink);"><?= number_format($item['price'], 2) ?></span>
                                            <span class="text-[11px] block mt-0.5" style="color: var(--mute);">SAR / <?= htmlspecialchars($item['unit']) ?></span>
                                        </td>
                                        <td class="px-3 py-3.5 text-center">
                                            <span class="pill" style="background: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                                <span class="status-dot" style="background:<?= $style['dot'] ?>;"></span><?= $style['label'] ?>
                                            </span>
                                        </td>
                                        <td class="pr-6 py-3.5 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button class="w-8 h-8 flex items-center justify-center rounded-sm transition-colors hover:bg-[var(--line-soft)]" style="color: var(--ink);">
                                                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <button class="w-8 h-8 flex items-center justify-center rounded-sm transition-colors hover:bg-[#F8E9E7]" style="color: #963B33;">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
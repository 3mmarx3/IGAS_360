<?php
session_start();
require_once '../../config/db.php';

$active_page = 'raw_materials';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Raw Materials', 'New Material'];

$errors = [];
$success_message = '';

$material_name        = '';
$category              = '';
$unit                  = '';
$handling_notes        = '';
$opening_stock         = '';
$safety_stock_threshold = '';
$warehouse_zone        = '';
$lead_time_days        = '';
$stock_status          = 'optimal';
$supplier_id           = '';
$unit_cost             = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $material_name          = trim($_POST['material_name'] ?? '');
    $category                = trim($_POST['category'] ?? '');
    $unit                    = trim($_POST['unit'] ?? '');
    $handling_notes          = trim($_POST['handling_notes'] ?? '');
    $opening_stock           = $_POST['opening_stock'] ?? '0';
    $safety_stock_threshold  = $_POST['safety_stock_threshold'] ?? '';
    $warehouse_zone          = trim($_POST['warehouse_zone'] ?? '');
    $lead_time_days          = $_POST['lead_time_days'] ?? '';
    $stock_status            = $_POST['stock_status'] ?? 'optimal';
    $supplier_id             = $_POST['supplier_id'] ?? '';
    $unit_cost               = $_POST['unit_cost'] ?? '';

    if ($material_name === '') {
        $errors[] = 'Material description / technical name is required.';
    }
    if ($category === '') {
        $errors[] = 'Primary supply category is required.';
    }
    if ($unit === '') {
        $errors[] = 'Unit of measure is required.';
    }
    if ($safety_stock_threshold === '' || !is_numeric($safety_stock_threshold) || (float)$safety_stock_threshold < 0) {
        $errors[] = 'Safety stock threshold must be a valid number.';
    }
    if ($opening_stock !== '' && (!is_numeric($opening_stock) || (float)$opening_stock < 0)) {
        $errors[] = 'Initial opening stock level must be a valid number.';
    }
    if ($supplier_id === '' || !ctype_digit((string)$supplier_id)) {
        $errors[] = 'Primary approved supplier is required.';
    }
    if ($unit_cost === '' || !is_numeric($unit_cost) || (float)$unit_cost < 0) {
        $errors[] = 'Standard purchase cost is required and must be a valid number.';
    }
    if (!in_array($stock_status, ['optimal', 'low', 'out'], true)) {
        $stock_status = 'optimal';
    }

    if ($supplier_id !== '' && ctype_digit((string)$supplier_id)) {
        $supplierCheck = $pdo->prepare("SELECT id FROM partners WHERE id = :id AND partner_type = 'supplier' LIMIT 1");
        $supplierCheck->execute(['id' => (int)$supplier_id]);
        if (!$supplierCheck->fetch()) {
            $errors[] = 'Selected supplier is invalid.';
        }
    }

    if (empty($errors)) {
        $opening_stock_val = $opening_stock !== '' ? (float)$opening_stock : 0;
        $material_sku = 'RM-' . random_int(1008, 1999);

        $skuCheck = $pdo->prepare("SELECT id FROM raw_materials WHERE material_sku = :sku LIMIT 1");
        $skuCheck->execute(['sku' => $material_sku]);
        while ($skuCheck->fetch()) {
            $material_sku = 'RM-' . random_int(1008, 9999);
            $skuCheck->execute(['sku' => $material_sku]);
        }

        $insert = $pdo->prepare("
            INSERT INTO raw_materials
                (material_sku, material_name, category, unit, handling_notes,
                 opening_stock, current_stock, safety_stock_threshold, warehouse_zone,
                 lead_time_days, stock_status, supplier_id, unit_cost)
            VALUES
                (:material_sku, :material_name, :category, :unit, :handling_notes,
                 :opening_stock, :current_stock, :safety_stock_threshold, :warehouse_zone,
                 :lead_time_days, :stock_status, :supplier_id, :unit_cost)
        ");

        $insert->execute([
            'material_sku'            => $material_sku,
            'material_name'           => $material_name,
            'category'                => $category,
            'unit'                    => $unit,
            'handling_notes'          => $handling_notes !== '' ? $handling_notes : null,
            'opening_stock'           => $opening_stock_val,
            'current_stock'           => $opening_stock_val,
            'safety_stock_threshold'  => (float)$safety_stock_threshold,
            'warehouse_zone'          => $warehouse_zone !== '' ? $warehouse_zone : null,
            'lead_time_days'          => $lead_time_days !== '' ? (int)$lead_time_days : null,
            'stock_status'            => $stock_status,
            'supplier_id'             => (int)$supplier_id,
            'unit_cost'               => (float)$unit_cost,
        ]);

        header('Location: raw_materials.php?created=1');
        exit;
    }
}

$new_material_id = 'RM-' . rand(1008, 1999);

$suppliers = $pdo->query("
    SELECT id, company_name, reference_id
    FROM partners
    WHERE partner_type = 'supplier' AND status = 'approved'
    ORDER BY company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Material Entry | I-GAS Enterprise</title>
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

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }
        .form-input:disabled { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }

        .input-group { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }

        .alert-box { border-radius: 4px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; }
        .alert-error { background: #F8E9E7; color: #963B33; border: 1px solid #E3C9C6; }
        .alert-error ul { margin: 4px 0 0 0; padding-left: 18px; }
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
                    <div class="flex items-center gap-2 mb-2">
                        <a href="raw_materials.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Raw Materials</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Inventory Entry</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Add Raw Material</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Introduce new bulk gas assets, production chemical components, or containment items to stock control.</p>
                </div>
                <div class="flex gap-3">
                    <a href="raw_materials.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button type="submit" form="new-material-form" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Material Item
                    </button>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert-box alert-error">
                <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                <div>
                    <strong>Couldn't save the material:</strong>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <form id="new-material-form" action="new_material.php" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="package" class="w-4 h-4" style="color: var(--mute);"></i>Item Specification
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label class="form-label">Material Description / Technical Name</label>
                                    <input type="text" name="material_name" class="form-input" placeholder="e.g. Liquid Oxygen (LOX), Brass Valves, Acetone" value="<?= htmlspecialchars($material_name) ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Primary Supply Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="" <?= $category === '' ? 'selected' : '' ?> disabled>Select primary classification...</option>
                                        <option value="Bulk Gas" <?= $category === 'Bulk Gas' ? 'selected' : '' ?>>Bulk Gas</option>
                                        <option value="Chemicals" <?= $category === 'Chemicals' ? 'selected' : '' ?>>Chemicals</option>
                                        <option value="Containers" <?= $category === 'Containers' ? 'selected' : '' ?>>Containers / Cylinders</option>
                                        <option value="Spare Parts" <?= $category === 'Spare Parts' ? 'selected' : '' ?>>Spare Parts &amp; Valves</option>
                                        <option value="Consumables" <?= $category === 'Consumables' ? 'selected' : '' ?>>Consumables</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Unit of Measure (UoM)</label>
                                    <select name="unit" class="form-select" required>
                                        <option value="" <?= $unit === '' ? 'selected' : '' ?> disabled>Select engineering metrics...</option>
                                        <option value="Liters" <?= $unit === 'Liters' ? 'selected' : '' ?>>Liters (L)</option>
                                        <option value="KG" <?= $unit === 'KG' ? 'selected' : '' ?>>Kilograms (KG)</option>
                                        <option value="Units" <?= $unit === 'Units' ? 'selected' : '' ?>>Units (Pcs)</option>
                                        <option value="Gallons" <?= $unit === 'Gallons' ? 'selected' : '' ?>>Gallons (Gal)</option>
                                        <option value="Metric Tons" <?= $unit === 'Metric Tons' ? 'selected' : '' ?>>Metric Tons (T)</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Internal Handling &amp; Storage Notes</label>
                                    <textarea name="handling_notes" class="form-input" rows="3" placeholder="Enter special temperature regulations, hazmat protocols, pressure limits or specialized zone criteria..."><?= htmlspecialchars($handling_notes) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="refresh-cw" class="w-4 h-4" style="color: var(--mute);"></i>Stock Management &amp; Reorder Logistics
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Initial Opening Stock Level</label>
                                    <input type="number" name="opening_stock" class="form-input mono num" placeholder="0" min="0" value="<?= htmlspecialchars((string)$opening_stock) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Safety Stock Threshold (Low Stock Limit)</label>
                                    <input type="number" name="safety_stock_threshold" class="form-input mono num" placeholder="Trigger alert below this value..." min="0" value="<?= htmlspecialchars((string)$safety_stock_threshold) ?>" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Assigned Warehouse / Tank Farm Zone</label>
                                    <input type="text" name="warehouse_zone" class="form-input" placeholder="e.g. Cryo Tank A, Shed 3, Zone B" value="<?= htmlspecialchars($warehouse_zone) ?>">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Estimated Lead Time (Days)</label>
                                    <input type="number" name="lead_time_days" class="form-input mono num" placeholder="Days required for replenishment..." min="0" value="<?= htmlspecialchars((string)$lead_time_days) ?>">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="hash" class="w-4 h-4" style="color: var(--mute);"></i>System Tracking
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Material SKU / ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($new_material_id) ?>" disabled>
                                    <p class="text-[11px] mt-1.5" style="color: var(--mute-soft);">Final SKU is assigned automatically on save.</p>
                                </div>
                                <div>
                                    <label class="form-label">Material Status Orientation</label>
                                    <select name="stock_status" class="form-select">
                                        <option value="optimal" <?= $stock_status === 'optimal' ? 'selected' : '' ?>>In Stock / Active</option>
                                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock / Reorder Initiated</option>
                                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="shield-alert" class="w-4 h-4" style="color: var(--mute);"></i>Procurement &amp; Finance
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Primary Approved Supplier</label>
                                    <select name="supplier_id" class="form-select" required>
                                        <option value="" <?= $supplier_id === '' ? 'selected' : '' ?> disabled>Bind reliable vendor...</option>
                                        <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?= (int)$sup['id'] ?>" <?= ((string)$supplier_id === (string)$sup['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sup['company_name']) ?> (<?= htmlspecialchars($sup['reference_id']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if (empty($suppliers)): ?>
                                        <option value="" disabled>No approved suppliers found</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Standard Purchase Cost (Per Unit)</label>
                                    <div class="input-group">
                                        <span class="absolute left-3 text-[12px] font-semibold mono" style="color: var(--mute);">SAR</span>
                                        <input type="number" step="0.01" name="unit_cost" class="form-input num pl-12" placeholder="0.00" value="<?= htmlspecialchars((string)$unit_cost) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
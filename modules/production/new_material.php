<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('materials.create');

$active_page = 'raw_materials';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Raw Materials', 'New Material'];

$errors = [];
$success_message = '';

$material_name    = '';
$category       = '';
$unit         = '';
$handling_notes    = '';
$opening_stock    = '';
$max_capacity     = '';
$safety_stock_threshold = '';
$warehouse_zone    = '';
$lead_time_days    = '';
$stock_status     = 'optimal';
$supplier_id     = '';
$unit_cost      = '';
$supplier_reference_id = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  require_csrf();

  $material_name     = trim($_POST['material_name'] ?? '');
  $category        = trim($_POST['category'] ?? '');
  $unit          = trim($_POST['unit'] ?? '');
  $handling_notes     = trim($_POST['handling_notes'] ?? '');
  $opening_stock     = $_POST['opening_stock'] ?? '0';
  $max_capacity      = $_POST['max_capacity'] ?? '0';
  $safety_stock_threshold = $_POST['safety_stock_threshold'] ?? '';
  $warehouse_zone     = trim($_POST['warehouse_zone'] ?? '');
  $lead_time_days     = $_POST['lead_time_days'] ?? '';
  $stock_status      = $_POST['stock_status'] ?? 'optimal';
  $supplier_id      = $_POST['supplier_id'] ?? '';
  $unit_cost       = $_POST['unit_cost'] ?? '';

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
  if ($max_capacity !== '' && (!is_numeric($max_capacity) || (float)$max_capacity < 0)) {
    $errors[] = 'Maximum storage capacity must be a valid number.';
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
    $supplierCheck = $pdo->prepare("SELECT id, reference_id FROM partners WHERE id = :id AND partner_type = 'supplier' LIMIT 1");
    $supplierCheck->execute(['id' => (int)$supplier_id]);
    $supplierData = $supplierCheck->fetch(PDO::FETCH_ASSOC);
    if (!$supplierData) {
      $errors[] = 'Selected supplier is invalid.';
    } else {
      $supplier_reference_id = $supplierData['reference_id'];
    }
  }

  if (empty($errors)) {
    $opening_stock_val = $opening_stock !== '' ? (float)$opening_stock : 0;
    $max_capacity_val = $max_capacity !== '' ? (float)$max_capacity : 0;
    $material_sku = 'RM-' . random_int(1008, 1999);

    $skuCheck = $pdo->prepare("SELECT id FROM raw_materials WHERE material_sku = :sku LIMIT 1");
    $skuCheck->execute(['sku' => $material_sku]);
    while ($skuCheck->fetch()) {
      $material_sku = 'RM-' . random_int(1008, 9999);
      $skuCheck->execute(['sku' => $material_sku]);
    }

    try {
      $pdo->beginTransaction();

      $insert = $pdo->prepare("
        INSERT INTO raw_materials
          (material_sku, material_name, category, unit, handling_notes,
           opening_stock, current_stock, max_capacity, safety_stock_threshold, warehouse_zone,
           lead_time_days, stock_status, supplier_id, unit_cost)
        VALUES
          (:material_sku, :material_name, :category, :unit, :handling_notes,
           :opening_stock, :current_stock, :max_capacity, :safety_stock_threshold, :warehouse_zone,
           :lead_time_days, :stock_status, :supplier_id, :unit_cost)
      ");

      $insert->execute([
        'material_sku'      => $material_sku,
        'material_name'      => $material_name,
        'category'        => $category,
        'unit'          => $unit,
        'handling_notes'     => $handling_notes !== '' ? $handling_notes : null,
        'opening_stock'      => $opening_stock_val,
        'current_stock'      => $opening_stock_val,
        'max_capacity'      => $max_capacity_val,
        'safety_stock_threshold' => (float)$safety_stock_threshold,
        'warehouse_zone'     => $warehouse_zone !== '' ? $warehouse_zone : null,
        'lead_time_days'     => $lead_time_days !== '' ? (int)$lead_time_days : null,
        'stock_status'      => $stock_status,
        'supplier_id'       => (int)$supplier_id,
        'unit_cost'        => (float)$unit_cost,
      ]);

      $insertSupplierProduct = $pdo->prepare("
        INSERT INTO supplier_products (
          supplier_reference, item_code, item_name, category, quantity, unit, unit_price, currency, lead_time_days
        ) VALUES (
          :supplier_reference, :item_code, :item_name, :category, 1.00, :unit, :unit_price, 'SAR', :lead_time_days
        )
      ");

      $insertSupplierProduct->execute([
        'supplier_reference' => $supplier_reference_id,
        'item_code'     => $material_sku,
        'item_name'     => $material_name,
        'category'      => $category,
        'unit'        => $unit,
        'unit_price'     => (float)$unit_cost,
        'lead_time_days'   => $lead_time_days !== '' ? (int)$lead_time_days : 0
      ]);

      $pdo->commit();
      redirect(app_url('modules/production/raw_materials.php?created=1'));

    } catch (Exception $e) {
      $pdo->rollBack();
      $errors[] = "Failed to save material and link it to the supplier: " . $e->getMessage();
    }
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
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Material Entry | I-GAS Enterprise" data-ar="إدخال مادة جديدة | I-GAS Enterprise">New Material Entry | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

  <?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400">
      <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
          <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
          <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="FEEDSTOCK ASSET CATALOG REGISTRATION" data-ar="تسجيل أصل مواد خام جديد">FEEDSTOCK ASSET CATALOG REGISTRATION</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">ASSIGNED SKU: <?= htmlspecialchars($new_material_id) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb Navigation & Top Action -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <div class="flex items-center gap-2 mb-1.5 text-xs font-mono text-neutral-500">
            <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors uppercase tracking-wider" data-en="Raw Materials" data-ar="المواد الخام">
              Raw Materials
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-neutral-400 rtl:rotate-180"></i>
            <span class="text-teal-600 dark:text-teal-400 uppercase tracking-wider font-semibold" data-en="Inventory Entry" data-ar="تسجيل عنصر جديد">Inventory Entry</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Add Raw Material" data-ar="إضافة مادة خام">
            Add Raw Material
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Register bulk gases, process chemical reagents, or cylinder valve hardware to inventory control." data-ar="تسجيل الغازات السائبة أو الكواشف الكيميائية أو صمامات الأسطوانات في مراقبة المخزون.">
            Register bulk gases, process chemical reagents, or cylinder valve hardware to inventory control.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <button type="submit" form="new-material-form" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="save" class="w-3.5 h-3.5"></i>
            <span data-en="Save Material Item" data-ar="حفظ عنصر المادة">Save Material Item</span>
          </button>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/40 text-rose-400 dark:text-rose-400 rounded-sm text-xs font-mono space-y-1">
        <div class="flex items-center gap-2 font-bold mb-1">
          <i data-lucide="alert-triangle" class="w-4 h-4"></i>
          <span data-en="Unable to register material:" data-ar="تعذر تسجيل المادة:">Unable to register material:</span>
        </div>
        <ul class="list-disc list-inside space-y-1 pl-2">
          <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form id="new-material-form" action="<?= app_url('modules/production/new_material.php') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left 2 Cols: Item Specification & Logistics -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Specification Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316] space-y-5">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="1. Item Specification & Physical Properties" data-ar="1. مواصفات العنصر والخصائص الفيزيائية">1. Item Specification & Physical Properties</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Material Description / Technical Name *" data-ar="اسم المادة الفني / الوصف *">
                    Material Description / Technical Name *
                  </label>
                  <input type="text" name="material_name" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Liquid Oxygen (LOX) 99.999% Grade 5.0" value="<?= htmlspecialchars($material_name) ?>" required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Primary Supply Category *" data-ar="فئة التوريد *">
                      Primary Supply Category *
                    </label>
                    <select name="category" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                      <option value="" <?= $category === '' ? 'selected' : '' ?> disabled data-en="Select category..." data-ar="اختر الفئة...">Select category...</option>
                      <option value="Bulk Gas" <?= $category === 'Bulk Gas' ? 'selected' : '' ?> data-en="Bulk Gas" data-ar="الغاز السائب">Bulk Gas</option>
                      <option value="Chemicals" <?= $category === 'Chemicals' ? 'selected' : '' ?> data-en="Chemicals" data-ar="المواد الكيميائية">Chemicals</option>
                      <option value="Containers" <?= $category === 'Containers' ? 'selected' : '' ?> data-en="Containers / Cylinders" data-ar="الحاويات / الأسطوانات">Containers / Cylinders</option>
                      <option value="Spare Parts" <?= $category === 'Spare Parts' ? 'selected' : '' ?> data-en="Spare Parts & Valves" data-ar="قطع الغيار والصمامات">Spare Parts & Valves</option>
                      <option value="Consumables" <?= $category === 'Consumables' ? 'selected' : '' ?> data-en="Consumables" data-ar="المواد الاستهلاكية">Consumables</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Unit of Measure (UoM) *" data-ar="وحدة القياس *">
                      Unit of Measure (UoM) *
                    </label>
                    <select name="unit" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                      <option value="" <?= $unit === '' ? 'selected' : '' ?> disabled data-en="Select unit..." data-ar="اختر الوحدة...">Select unit...</option>
                      <option value="Liters" <?= $unit === 'Liters' ? 'selected' : '' ?> data-en="Liters (L)" data-ar="لتر (L)">Liters (L)</option>
                      <option value="KG" <?= $unit === 'KG' ? 'selected' : '' ?> data-en="Kilograms (KG)" data-ar="كيلوجرام (KG)">Kilograms (KG)</option>
                      <option value="Units" <?= $unit === 'Units' ? 'selected' : '' ?> data-en="Units (Pcs)" data-ar="وحدات (قطع)">Units (Pcs)</option>
                      <option value="Gallons" <?= $unit === 'Gallons' ? 'selected' : '' ?> data-en="Gallons (Gal)" data-ar="جالون (Gal)">Gallons (Gal)</option>
                      <option value="Metric Tons" <?= $unit === 'Metric Tons' ? 'selected' : '' ?> data-en="Metric Tons (T)" data-ar="طن متري (T)">Metric Tons (T)</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Internal Handling & Storage Protocol" data-ar="بروتوكول المناولة والتخزين الداخلي">
                    Internal Handling & Storage Protocol
                  </label>
                  <textarea name="handling_notes" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 resize-none" rows="3" placeholder="Enter cryogenic temperatures, vacuum jacket requirements, hazmat classification, or tank pressure limits..."><?= htmlspecialchars($handling_notes) ?></textarea>
                </div>
              </div>
            </div>

            <!-- Capacity & Threshold Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-5 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="2. Capacity Limits & Safety Thresholds" data-ar="2. حدود السعة ومستويات الأمان">2. Capacity Limits & Safety Thresholds</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Initial Opening Stock" data-ar="المخزون الافتتاحي">
                    Initial Opening Stock
                  </label>
                  <input type="number" name="opening_stock" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="0" min="0" step="0.01" value="<?= htmlspecialchars((string)$opening_stock) ?>">
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Max Storage Capacity" data-ar="السعة القصوى">
                    Max Storage Capacity
                  </label>
                  <input type="number" name="max_capacity" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="20000" min="0" step="0.01" value="<?= htmlspecialchars((string)$max_capacity) ?>">
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Safety Alert Threshold *" data-ar="حد التنبيه الحرج *">
                    Safety Alert Threshold *
                  </label>
                  <input type="number" name="safety_stock_threshold" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="5000" min="0" step="0.01" value="<?= htmlspecialchars((string)$safety_stock_threshold) ?>" required>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Assigned Tank / Zone" data-ar="المنطقة أو الخزان المخصص">
                    Assigned Tank / Zone
                  </label>
                  <input type="text" name="warehouse_zone" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Tank Farm Vessel T-101" value="<?= htmlspecialchars($warehouse_zone) ?>">
                </div>
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Replenishment Lead Time (Days)" data-ar="مهلة التوريد (بالأيام)">
                    Replenishment Lead Time (Days)
                  </label>
                  <input type="number" name="lead_time_days" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. 5" min="0" value="<?= htmlspecialchars((string)$lead_time_days) ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Right 1 Col: Identification & Supplier Binding -->
          <div class="space-y-6">
            <!-- Tracking Card -->
            <div class="bg-[#102230]/60 bg-[#0c1924]/60 border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="hash" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Catalog Code" data-ar="رمز الكتالوج">Catalog Code</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Assigned SKU" data-ar="رمز الصنف">
                    Assigned SKU
                  </label>
                  <input type="text" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#112433] border border-[#1e3a50] rounded-sm text-slate-400 font-semibold cursor-not-allowed" value="<?= htmlspecialchars($new_material_id) ?>" disabled>
                  <span class="text-[10px] font-mono text-neutral-400 block mt-1" data-en="Auto-generated unique inventory identifier" data-ar="معرف مخزون فريد مُنشأ تلقائيًا">Auto-generated unique inventory identifier</span>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Initial Status Orientation" data-ar="الحالة الأولية للمادة">
                    Initial Status Orientation
                  </label>
                  <select name="stock_status" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                    <option value="optimal" <?= $stock_status === 'optimal' ? 'selected' : '' ?> data-en="In Stock / Active" data-ar="متوفر / نشط">In Stock / Active</option>
                    <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?> data-en="Low Stock / Reorder Triggered" data-ar="مخزون منخفض / تم بدء إعادة الطلب">Low Stock / Reorder Triggered</option>
                    <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?> data-en="Out of Stock" data-ar="نفد من المخزون">Out of Stock</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Procurement Binding Card -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 space-y-4 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-white pb-3 border-b border-[#1a3346] flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Vendor & Unit Cost" data-ar="المورد وتكلفة الوحدة">Vendor & Unit Cost</span>
              </h2>

              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Primary Approved Supplier *" data-ar="المورد المعتمد الأساسي *">
                    Primary Approved Supplier *
                  </label>
                  <select name="supplier_id" class="w-full px-3 py-2 text-xs font-mono bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                    <option value="" <?= $supplier_id === '' ? 'selected' : '' ?> disabled data-en="Bind approved vendor..." data-ar="ربط مورد معتمد...">Bind approved vendor...</option>
                    <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= (int)$sup['id'] ?>" <?= ((string)$supplier_id === (string)$sup['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($sup['company_name']) ?> (<?= htmlspecialchars($sup['reference_id']) ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono text-slate-400 uppercase tracking-wider mb-1.5" data-en="Standard Unit Cost (SAR) *" data-ar="تكلفة الوحدة القياسية (ر.س) *">
                    Standard Unit Cost (SAR) *
                  </label>
                  <input type="number" step="0.01" name="unit_cost" class="w-full px-3 py-2 text-xs font-mono tabular-nums bg-[#08131c] bg-[#0c1924] border border-[#1e3a50] rounded-sm text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="0.00" value="<?= htmlspecialchars((string)$unit_cost) ?>" required>
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
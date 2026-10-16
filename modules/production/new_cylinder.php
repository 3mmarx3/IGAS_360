<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('cylinders.manage');

$active_page = 'cylinders_inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Cylinders Inventory', 'New Batch Registration'];

$new_batch_id = 'BAT-' . rand(8000, 8999);

$rawMaterialsStmt = $pdo->query("SELECT id, material_name, current_stock, unit FROM raw_materials WHERE current_stock > 0 ORDER BY material_name ASC");
$rawMaterials = $rawMaterialsStmt ? $rawMaterialsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$poStmt = $pdo->query("SELECT order_number FROM purchase_orders WHERE status != 'cancelled' ORDER BY order_date DESC LIMIT 50");
$poList = $poStmt ? $poStmt->fetchAll(PDO::FETCH_COLUMN) : [];

$volStmt = $pdo->query("SELECT DISTINCT volume FROM cylinder_batches WHERE volume IS NOT NULL AND volume != ''");
$dbVolumes = $volStmt ? $volStmt->fetchAll(PDO::FETCH_COLUMN) : [];
$defaultVolumes = ['10L', '20L', '40L', '50L', '100L'];
$allVolumes = array_unique(array_merge($defaultVolumes, $dbVolumes));
sort($allVolumes, SORT_NATURAL);

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $volume       = $_POST['volume'] ?? '';
  $build_material   = $_POST['build_material'] ?? '';
  $valve_spec     = $_POST['valve_spec'] ?? '';
  $color_coding    = $_POST['color_coding'] ?? '';
  $quantity      = (int)($_POST['quantity'] ?? 0);
  $raw_material_id  = !empty($_POST['raw_material_id']) ? (int)$_POST['raw_material_id'] : null;
  $cost_price     = (float)($_POST['cost_price'] ?? 0);
  $selling_price   = (float)($_POST['selling_price'] ?? 0);
  $po_ref       = $_POST['po_ref'] ?? '';
  $mfg_date      = $_POST['mfg_date'] ?? '';
  $batch_number    = $_POST['batch_number'] ?? $new_batch_id;
  $sku        = $_POST['sku'] ?? '';
  $working_pressure  = (int)($_POST['working_pressure'] ?? 0);
  $test_pressure   = (int)($_POST['test_pressure'] ?? 0);
  $initial_test_date = $_POST['initial_test_date'] ?? '';
  $next_test_due   = $_POST['next_test_due'] ?? '';

  $gas_classification = 'Unknown';
  if ($raw_material_id) {
    $rmStmt = $pdo->prepare("SELECT material_name FROM raw_materials WHERE id = ?");
    $rmStmt->execute([$raw_material_id]);
    $gas_classification = $rmStmt->fetchColumn() ?: 'Unknown';
  }

  if (empty($sku) || $quantity <= 0 || empty($raw_material_id)) {
    $error_message = "يرجى تعبئة جميع الحقول الأساسية وتحديد كمية أكبر من صفر.";
  } else {
    try {
      $pdo->beginTransaction();

      $batchStmt = $pdo->prepare("
        INSERT INTO cylinder_batches 
        (batch_number, sku, gas_classification, volume, build_material, valve_spec, color_coding, quantity, raw_material_id, cost_price, selling_price, po_ref, mfg_date, working_pressure, test_pressure, initial_test_date, next_test_due) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $batchStmt->execute([
        $batch_number, $sku, $gas_classification, $volume, $build_material, $valve_spec, $color_coding, 
        $quantity, $raw_material_id, $cost_price, $selling_price, $po_ref, $mfg_date, $working_pressure, $test_pressure, $initial_test_date, $next_test_due
      ]);

      $batch_id = $pdo->lastInsertId();

      $cylStmt = $pdo->prepare("
        INSERT INTO cylinders (barcode, batch_id, sku, status) 
        VALUES (?, ?, ?, 'in_plant_filled')
      ");

      for ($i = 1; $i <= $quantity; $i++) {
        $barcode = $batch_number . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $cylStmt->execute([$barcode, $batch_id, $sku]);
      }

      $pdo->commit();
      $success_message = "تم تسجيل عدد {$quantity} أسطوانة بنجاح وتوليد الباركود الخاص بها.";
      $new_batch_id = 'BAT-' . rand(8000, 8999);
    } catch (Exception $e) {
      $pdo->rollBack();
      $error_message = "حدث خطأ أثناء التسجيل: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Register Cylinder Batch | I-GAS Enterprise" data-ar="تسجيل دفعة أسطوانات | I-GAS Enterprise">Register Cylinder Batch | I-GAS Enterprise</title>
  
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
        <span data-en="PLANT: JEDDAH MAIN FACILITY" data-ar="المحطة: منشأة جدة الرئيسية">PLANT: JEDDAH MAIN FACILITY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="COMMISSIONING MODE" data-ar="وضع إدخال الخدمة">COMMISSIONING MODE</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">BATCH ID:</span>
        <span class="text-slate-300 font-semibold font-mono"><?= htmlspecialchars($new_batch_id) ?></span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Breadcrumb Navigation -->
      <div>
        <a href="<?= app_url('modules/production/cylinders_inventory.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Cylinders Inventory" data-ar="العودة لمخزون الأسطوانات">Back to Cylinders Inventory</span>
        </a>
      </div>

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="PRODUCTION & INVENTORY" data-ar="الإنتاج والمخزون">PRODUCTION & INVENTORY</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="BATCH COMMISSIONING" data-ar="تسجيل الدفعة">BATCH COMMISSIONING</span>
          </div>
          <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Register New Cylinder Batch" data-ar="تسجيل دفعة أسطوانات جديدة">
            Register New Cylinder Batch
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Add physical cylinder assets into the plant inventory with technical stamping and hydrostatic validation." data-ar="إضافة دفعة جديدة من الأسطوانات وتحديد المواصفات، التكلفة، وأسعار البيع المستهدفة.">
            Add physical cylinder assets into the plant inventory with technical stamping and hydrostatic validation.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/production/cylinders_inventory.php') ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs transition-colors" data-en="Cancel" data-ar="إلغاء">
            Cancel
          </a>
          <button type="button" onclick="document.getElementById('batch-form').submit();" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs cursor-pointer">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span data-en="Register Assets" data-ar="تسجيل الأصول">Register Assets</span>
          </button>
        </div>
      </div>

      <?php if (!empty($error_message)): ?>
      <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-medium flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
      </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
      <div class="p-4 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
        <span><?= htmlspecialchars($success_message) ?></span>
      </div>
      <?php endif; ?>

      <form action="" method="POST" id="batch-form">
        <?= csrf_field() ?>
        <input type="hidden" name="batch_number" value="<?= htmlspecialchars($new_batch_id) ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Left Columns (Specifications & Pricing) -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Asset Specifications -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="cylinder" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Asset Specifications" data-ar="مواصفات الأصول (الأسطوانات)">Asset Specifications</h3>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Cylinder Volume / Size *" data-ar="حجم / سعة الأسطوانة *">Cylinder Volume / Size *</label>
                  <select name="volume" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required onchange="updateSKU()">
                    <option value="" selected disabled data-en="Select volume..." data-ar="اختر الحجم...">Select volume...</option>
                    <?php foreach ($allVolumes as $vol): ?>
                      <option value="<?= htmlspecialchars($vol) ?>"><?= htmlspecialchars($vol) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Build Material *" data-ar="مادة الصنع *">Build Material *</label>
                  <input type="text" name="build_material" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. Seamless Steel (34CrMo4)" data-en-placeholder="e.g. Seamless Steel (34CrMo4)" data-ar-placeholder="مثل: فولاذ غير ملحوم" required>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Valve Specification *" data-ar="مواصفات الصمام *">Valve Specification *</label>
                  <input type="text" name="valve_spec" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. CGA 540, BS341" required>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Color Coding Standard *" data-ar="معيار ترميز الألوان *">Color Coding Standard *</label>
                  <input type="text" name="color_coding" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. White Shoulder / Black Body" data-en-placeholder="e.g. White Shoulder / Black Body" data-ar-placeholder="مثل: كتف أبيض / جسم أسود" required>
                </div>
              </div>
            </div>

            <!-- Batch Details & Pricing -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="package-plus" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Batch Details & Pricing" data-ar="تفاصيل الدفعة والتسعير">Batch Details & Pricing</h3>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="col-span-1 md:col-span-2">
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Source Raw Gas / Material *" data-ar="المادة الخام (الغاز المستهدف) *">Source Raw Gas / Material *</label>
                  <select name="raw_material_id" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required onchange="updateSKU()">
                    <option value="" selected disabled data-en="Select material from stock..." data-ar="اختر المادة من المخزون...">Select material from stock...</option>
                    <?php foreach ($rawMaterials as $rm): ?>
                      <option value="<?= $rm['id'] ?>" data-name="<?= htmlspecialchars($rm['material_name']) ?>">
                        <?= htmlspecialchars($rm['material_name']) ?> (<?= $rm['current_stock'] . ' ' . $rm['unit'] ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Quantity to Register *" data-ar="عدد الأسطوانات المراد تسجيلها *">Quantity to Register *</label>
                  <div class="relative">
                    <input type="number" name="quantity" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-16 rtl:pl-16 rtl:pr-3" placeholder="0" min="1" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-[11px] font-mono text-neutral-400 pointer-events-none" data-en="UNITS" data-ar="وحدة">UNITS</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Cost Price (Per Cylinder) *" data-ar="التكلفة (للأسطوانة الواحدة) *">Cost Price (Per Cylinder) *</label>
                  <div class="relative">
                    <input type="number" step="0.01" min="0" name="cost_price" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-14 rtl:pl-14 rtl:pr-3" placeholder="0.00" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-[11px] font-mono text-neutral-400 pointer-events-none">SAR</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Selling Price (Per Cylinder) *" data-ar="سعر البيع (للأسطوانة الواحدة) *">Selling Price (Per Cylinder) *</label>
                  <div class="relative">
                    <input type="number" step="0.01" min="0" name="selling_price" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-14 rtl:pl-14 rtl:pr-3" placeholder="0.00" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-[11px] font-mono text-neutral-400 pointer-events-none">SAR</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Purchase Order Ref" data-ar="مرجع أمر الشراء (اختياري)">Purchase Order Ref</label>
                  <input list="po_list" name="po_ref" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. PO-8109" autocomplete="off">
                  <datalist id="po_list">
                    <?php foreach ($poList as $po): ?>
                      <option value="<?= htmlspecialchars($po) ?>"></option>
                    <?php endforeach; ?>
                  </datalist>
                </div>
                <div class="col-span-1 md:col-span-2">
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Manufacturing Date *" data-ar="تاريخ الإنتاج *">Manufacturing Date *</label>
                  <input type="date" name="mfg_date" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column (System Allocation & Testing) -->
          <div class="space-y-6">
            
            <!-- System Allocation -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-neutral-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="hash" class="w-4 h-4 text-slate-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="System Allocation" data-ar="تخصيص النظام">System Allocation</h3>
              </div>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-400 uppercase mb-1" data-en="Generated Batch ID" data-ar="معرف الدفعة المُنشأ">Generated Batch ID</label>
                  <input type="text" class="w-full px-3 py-2 border border-[#1a3346] bg-[#09151f] rounded-xs text-xs font-mono text-slate-400 cursor-not-allowed" value="<?= htmlspecialchars($new_batch_id) ?>" disabled>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1" data-en="Target SKU (Auto-matched)" data-ar="رمز SKU المستهدف (تلقائي)">Target SKU (Auto-matched)</label>
                  <input type="text" name="sku" id="target_sku" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#09151f] rounded-xs text-xs font-mono font-semibold text-teal-400 dark:text-teal-400" placeholder="e.g. CYL-O2-50L" readonly>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-400 uppercase mb-1" data-en="Initial Location Status" data-ar="حالة الموقع الأولية">Initial Location Status</label>
                  <input type="text" class="w-full px-3 py-2 border border-[#1a3346] bg-[#09151f] rounded-xs text-xs font-mono text-slate-400 cursor-not-allowed" value="In Plant (Ready/Filled)" disabled>
                </div>
              </div>
            </div>

            <!-- Compliance & Testing -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-amber-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="shield-check" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Compliance & Testing" data-ar="الضغط والاختبار">Compliance & Testing</h3>
              </div>
              
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1" data-en="Working Pressure *" data-ar="ضغط التشغيل *">Working Pressure *</label>
                    <div class="relative">
                      <input type="number" name="working_pressure" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-12 rtl:pl-12 rtl:pr-3" placeholder="200" value="200" required>
                      <span class="absolute right-2.5 rtl:left-2.5 rtl:right-auto top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 pointer-events-none">BAR</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1" data-en="Test Pressure *" data-ar="ضغط الاختبار *">Test Pressure *</label>
                    <div class="relative">
                      <input type="number" name="test_pressure" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-12 rtl:pl-12 rtl:pr-3" placeholder="300" value="300" required>
                      <span class="absolute right-2.5 rtl:left-2.5 rtl:right-auto top-1/2 -translate-y-1/2 text-[10px] font-mono text-neutral-400 pointer-events-none">BAR</span>
                    </div>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1" data-en="Initial Hydro-Test Date *" data-ar="تاريخ الاختبار الأول *">Initial Hydro-Test Date *</label>
                  <input type="date" name="initial_test_date" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1" data-en="Next Hydro-Test Due *" data-ar="تاريخ الفحص القادم *">Next Hydro-Test Due *</label>
                  <input type="date" name="next_test_due" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" value="<?= date('Y-m-d', strtotime('+5 years')) ?>" required>
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

    function updateSKU() {
      const rmSelect = document.querySelector('select[name="raw_material_id"]');
      const volSelect = document.querySelector('select[name="volume"]');
      const skuInput = document.getElementById('target_sku');
      
      if (rmSelect && volSelect && rmSelect.value && volSelect.value) {
        const selectedOption = rmSelect.options[rmSelect.selectedIndex];
        const rawName = selectedOption.getAttribute('data-name') || '';
        const vol = volSelect.value;
        
        let prefix = rawName.substring(0, 3).toUpperCase();
        if (rawName.toLowerCase().includes('oxy') || rawName.includes('اكسجين') || rawName.includes('أكسجين')) prefix = 'O2';
        if (rawName.toLowerCase().includes('nit') || rawName.includes('نيتروجين')) prefix = 'N2';
        if (rawName.toLowerCase().includes('arg') || rawName.includes('ارجون') || rawName.includes('أرغون')) prefix = 'AR';
        if (rawName.toLowerCase().includes('car') || rawName.includes('كربون')) prefix = 'CO2';
        if (rawName.toLowerCase().includes('hel') || rawName.includes('هيليوم')) prefix = 'HE';
        
        skuInput.value = 'CYL-' + prefix + '-' + vol;
      } else {
        skuInput.value = '';
      }
    }
  </script>
</body>
</html>
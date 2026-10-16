<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'warehouse.manage');

$active_page = 'inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Warehouse & Inventory', 'New Inventory Item'];

$errors = [];
$success_message = '';

$item_name   = '';
$item_category = '';
$warehouse_id = $_GET['warehouse_id'] ?? '';
$quantity   = '';

$warehouses = $pdo->query("
  SELECT w.id, w.warehouse_name, w.warehouse_type, p.company_name 
  FROM warehouses w 
  LEFT JOIN partners p ON w.client_id = p.id 
  ORDER BY w.warehouse_type ASC, w.warehouse_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $item_name   = trim($_POST['item_name'] ?? '');
  $item_category = trim($_POST['item_category'] ?? '');
  $warehouse_id = $_POST['warehouse_id'] ?? '';
  $quantity   = $_POST['quantity'] ?? '0';

  if ($item_name === '') {
    $errors[] = 'Item name is required.';
  }
  if ($item_category === '') {
    $errors[] = 'Item category is required.';
  }
  if ($warehouse_id === '' || !ctype_digit((string)$warehouse_id)) {
    $errors[] = 'Please select a valid warehouse or custody location.';
  }
  if ($quantity !== '' && (!is_numeric($quantity) || (float)$quantity < 0)) {
    $errors[] = 'Initial quantity must be a valid positive number.';
  }

  if (empty($errors)) {
    $quantity_val = $quantity !== '' ? (float)$quantity : 0;
    
    $sku = 'WH-' . random_int(1008, 9999);
    $skuCheck = $pdo->prepare("SELECT id FROM warehouse_inventory WHERE sku = :sku LIMIT 1");
    $skuCheck->execute(['sku' => $sku]);
    while ($skuCheck->fetch()) {
      $sku = 'WH-' . random_int(1008, 9999);
      $skuCheck->execute(['sku' => $sku]);
    }

    try {
      $pdo->beginTransaction();

      $insert = $pdo->prepare("
        INSERT INTO warehouse_inventory (warehouse_id, item_category, item_name, sku, quantity_on_hand)
        VALUES (:warehouse_id, :item_category, :item_name, :sku, :quantity_on_hand)
      ");
      $insert->execute([
        'warehouse_id'   => (int)$warehouse_id,
        'item_category'  => $item_category,
        'item_name'    => $item_name,
        'sku'       => $sku,
        'quantity_on_hand' => $quantity_val
      ]);

      $inventory_id = $pdo->lastInsertId();

      if ($quantity_val > 0) {
        $insertTrans = $pdo->prepare("
          INSERT INTO warehouse_transactions (warehouse_id, inventory_id, transaction_type, quantity, notes, logged_by)
          VALUES (:warehouse_id, :inventory_id, 'in', :quantity, 'Initial Stock Setup', :logged_by)
        ");
        $insertTrans->execute([
          'warehouse_id' => (int)$warehouse_id,
          'inventory_id' => $inventory_id,
          'quantity'   => $quantity_val,
          'logged_by'  => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System'
        ]);
      }

      $pdo->commit();
      redirect(app_url('modules/warehouse/warehouse_profile.php?id=' . (int)$warehouse_id));

    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = "Failed to save inventory item: " . $e->getMessage();
    }
  }
}

$generated_sku_preview = 'WH-' . random_int(1008, 9999);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Inventory Item | I-GAS Control Room" data-ar="إضافة صنف للمخزون | غرفة التحكم I-GAS">New Inventory Item | I-GAS Control Room</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script>
    (function() {
      document.documentElement.classList.add('dark');
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
    })();
  </script>
  <style>
    .font-oxanium { font-family: 'Oxanium', 'Reem Kufi', sans-serif; }
    .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="STOCK INTAKE & SKU REGISTRATION PROTOCOL" data-ar="بروتوكول إضافة صنف ورمز تخزين جديد">STOCK INTAKE & SKU REGISTRATION PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="PROVISIONAL SKU:" data-ar="رمز التخزين المقترح:">PROVISIONAL SKU:</span> <strong class="text-white font-mono"><?= htmlspecialchars($generated_sku_preview) ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: WH-CATALOG</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 w-full mx-auto space-y-6">

    <!-- Breadcrumbs & Title -->
    <div>
      <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
        <a href="warehouses.php" class="hover:text-white transition-colors" data-en="Warehouse Inventory" data-ar="مستودعات الغاز">Warehouse Inventory</a>
        <span>/</span>
        <span class="text-white font-semibold" data-en="Add Stock Item" data-ar="إضافة صنف">Add Stock Item</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
            <i data-lucide="package-plus" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Register Inventory Item / SKU" data-ar="تسجيل صنف مخزون أو أسطوانات">Register Inventory Item / SKU</span>
          </h1>
          <p class="text-sm text-slate-400 mt-1" data-en="Register cylinders, gas manifolds, brass valves, or equipment spare parts to a specific plant depot." data-ar="إضافة أسطوانات، صمامات نحاسية، أو قطع غيار شبكات الغاز إلى مستودع محدد أو عهدة عميل.">
            Register cylinders, gas manifolds, brass valves, or equipment spare parts to a specific plant depot.
          </p>
        </div>
        <div>
          <a href="warehouses.php" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span data-en="Back to Directory" data-ar="العودة للدليل">Back to Directory</span>
          </a>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <div>
          <strong class="font-mono text-xs uppercase" data-en="Submission Errors:" data-ar="أخطاء الإدخال:">Submission Errors:</strong>
          <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="<?= app_url('modules/warehouse/new_warehouse_item.php') ?>" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      <?= csrf_field() ?>

      <!-- Section 1: Item Identification -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Item Identification & Classification" data-ar="بيانات الصنف والتصنيف الفني">Item Identification & Classification</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Item Name / Technical Specification *" data-ar="اسم الصنف / المواصفة الفنية *">
              Item Name / Technical Specification *
            </label>
            <input type="text" name="item_name" value="<?= htmlspecialchars($item_name) ?>" required placeholder="e.g. Empty 50L Seamless Steel Cylinder (200 Bar), CGA580 Brass Valve" data-en-placeholder="e.g. Empty 50L Seamless Steel Cylinder (200 Bar), CGA580 Brass Valve" data-ar-placeholder="مثال: أسطوانة فولاذية 50 لتر (200 بار)، صمام نحاسي CGA580" class="w-full h-10 px-3 text-xs font-sans bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Item Category *" data-ar="تصنيف الصنف *">
              Item Category *
            </label>
            <select name="item_category" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" <?= $item_category === '' ? 'selected' : '' ?> disabled data-en="-- Select Category Classification --" data-ar="-- اختر التصنيف --">-- Select Category Classification --</option>
              <option value="Cylinders" <?= $item_category === 'Cylinders' ? 'selected' : '' ?> data-en="Cylinders (Empty / Pre-Filled)" data-ar="أسطوانات غاز (فارغة / معبأة)">Cylinders (Empty / Pre-Filled)</option>
              <option value="Valves" <?= $item_category === 'Valves' ? 'selected' : '' ?> data-en="Valves & Manifold Regulators" data-ar="صمامات ومنظمات شبكات">Valves & Manifold Regulators</option>
              <option value="Spare Parts" <?= $item_category === 'Spare Parts' ? 'selected' : '' ?> data-en="Plant & Compressor Spare Parts" data-ar="قطع غيار ضواغط ومعدات المصنع">Plant & Compressor Spare Parts</option>
              <option value="Consumables" <?= $item_category === 'Consumables' ? 'selected' : '' ?> data-en="Safety Seals & Gaskets" data-ar="أختام وجوانات سلامة">Safety Seals & Gaskets</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section 2: Depot Location & Opening Quantity -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Target Facility & Initial Inventory" data-ar="موقع المستودع والكمية الافتتاحية">Target Facility & Initial Inventory</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Warehouse / Custody Depot *" data-ar="المستودع / موقع العهدة *">
              Warehouse / Custody Depot *
            </label>
            <select name="warehouse_id" required class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" <?= $warehouse_id === '' ? 'selected' : '' ?> disabled data-en="-- Select Location --" data-ar="-- اختر موقع المستودع --">-- Select Location --</option>
              
              <optgroup label="Internal Plant Depots (مستودعات داخلية)">
                <?php foreach ($warehouses as $wh): ?>
                  <?php if ($wh['warehouse_type'] === 'internal'): ?>
                    <option value="<?= $wh['id'] ?>" <?= ((string)$warehouse_id === (string)$wh['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($wh['warehouse_name']) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </optgroup>
              
              <optgroup label="Client Custody Yards (عهدة عملاء)">
                <?php foreach ($warehouses as $wh): ?>
                  <?php if ($wh['warehouse_type'] === 'client'): ?>
                    <?php $displayName = $wh['company_name'] ? $wh['company_name'] . ' (' . $wh['warehouse_name'] . ')' : $wh['warehouse_name']; ?>
                    <option value="<?= $wh['id'] ?>" <?= ((string)$warehouse_id === (string)$wh['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($displayName) ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Initial Opening Quantity *" data-ar="الكمية الافتتاحية *">
              Initial Opening Quantity *
            </label>
            <div class="relative">
              <input type="number" name="quantity" min="0" step="0.01" value="<?= htmlspecialchars((string)$quantity) ?>" placeholder="0.00" required class="w-full h-10 ps-3 pe-14 text-xs font-mono bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
              <span class="absolute end-3 top-2.5 text-xs font-mono text-neutral-400 pointer-events-none">UNITS</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: SKU Telemetry Code -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="hash" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="SKU Code Assignment" data-ar="تعيين رمز التخزين (SKU)">SKU Code Assignment</span>
          </h2>
        </div>

        <div class="max-w-md">
          <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="System Assigned SKU Code" data-ar="رمز التخزين المُنشأ تلقائياً">
            System Assigned SKU Code
          </label>
          <input type="text" class="w-full h-10 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-neutral-500 cursor-not-allowed" value="<?= htmlspecialchars($generated_sku_preview) ?>" readonly>
          <p class="text-[11px] font-mono text-neutral-400 mt-1" data-en="Collision checked upon submission." data-ar="يتم التحقق من تفرد الرمز عند الاعتماد.">
            Collision checked upon submission.
          </p>
        </div>
      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 bg-[#09151f] flex items-center justify-between">
        <a href="warehouses.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Commit Stock Entry" data-ar="حفظ الصنف في المخزون">Commit Stock Entry</span>
        </button>
      </div>
    </form>

  </div>
</main>

<script>
  lucide.createIcons();

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
  });
</script>
</body>
</html>
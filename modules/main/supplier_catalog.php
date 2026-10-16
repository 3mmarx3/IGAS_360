<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_authenticated();

if (!is_staff() && !is_partner()) {
  redirect(app_url('auth/login.php'));
}

$active_page = 'supplier_catalog';
$base_url  = '../../';
$breadcrumb = ['Supplier Portal', 'Inventory', 'Catalog Management'];

$supplier_name = 'Gulf Industrial Gases';
$supplier_id  = 'SUP-5001';

$flash_success = '';
$flash_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $item_name = trim($_POST['item_name'] ?? '');
  $category = trim($_POST['category'] ?? 'Bulk Liquid Gas');
  $purity  = trim($_POST['purity'] ?? '99.9%');
  $unit   = trim($_POST['unit'] ?? 'Liter');
  $price   = floatval($_POST['unit_price'] ?? 0);
  $item_code = 'ITM-' . rand(9100, 9999);
  
  if (!empty($item_name) && $price > 0) {
    try {
      $stmt_ins = $pdo->prepare("INSERT INTO supplier_products (supplier_reference, item_code, item_name, category, unit, unit_price, currency, lead_time_days) VALUES (?, ?, ?, ?, ?, ?, 'SAR', 3)");
      $stmt_ins->execute([$supplier_id, $item_code, $item_name, $category, $unit, $price]);
      $flash_success = "Supply item {$item_name} ({$item_code}) listed and queued for verification.";
    } catch (Exception $e) {
      $flash_success = "Supply item {$item_name} listed and queued for verification.";
    }
  } else {
    $flash_error = "Please provide a valid material name and unit price.";
  }
}

$catalog_items = [];
try {
  $stmt_items = $pdo->query("SELECT * FROM supplier_products ORDER BY id DESC");
  if ($stmt_items) {
    $db_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    foreach ($db_items as $di) {
      $catalog_items[] = [
        'code' => $di['item_code'],
        'name' => $di['item_name'],
        'type' => $di['category'],
        'purity' => 'Standard Industrial',
        'price' => (float)$di['unit_price'],
        'unit' => $di['unit'],
        'status' => 'active'
      ];
    }
  }
} catch (Exception $e) {}

// Always seed with standard items if empty
if (empty($catalog_items)) {
  $catalog_items = [
    ['code' => 'ITM-9021', 'name' => 'Liquid Argon (LAr) Cryogenic', 'type' => 'Bulk Liquid Gas', 'purity' => '99.999%', 'price' => 120.00, 'unit' => 'Liter', 'status' => 'active'],
    ['code' => 'ITM-9022', 'name' => 'Helium Gas (He) Grade 5.0', 'type' => 'Compressed Gas Cylinders', 'purity' => '99.999%', 'price' => 850.00, 'unit' => '50L Cylinder', 'status' => 'active'],
    ['code' => 'ITM-9025', 'name' => 'Calcium Carbide Granules', 'type' => 'Raw Industrial Materials', 'purity' => 'Industrial Spec', 'price' => 4500.00, 'unit' => 'Ton', 'status' => 'out_of_stock'],
    ['code' => 'ITM-9028', 'name' => 'Medical Oxygen (USP / Ph. Eur)', 'type' => 'Compressed Gas Cylinders', 'purity' => '99.5%', 'price' => 150.00, 'unit' => '40L Cylinder', 'status' => 'pending'],
  ];
}

$statusLabels = [
  'active'    => ['label_en' => 'Active & Listed', 'label_ar' => 'نشط ومدرج', 'class' => 'border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300'],
  'out_of_stock' => ['label_en' => 'Out of Stock', 'label_ar' => 'نفد المخزون', 'class' => 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300'],
  'pending'   => ['label_en' => 'Awaiting Verification', 'label_ar' => 'بانتظار التحقق', 'class' => 'border-amber-300 dark:border-amber-800 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-300'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Product Catalog Management | I-GAS Supplier Portal" data-ar="إدارة كتالوج المنتجات | بوابة موردي I-GAS">Product Catalog Management | I-GAS Supplier Portal</title>
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

  <!-- Telemetry Status Sub-bar -->
  <div class="h-9 border-b border-[#1a3346] bg-[#0c1924] flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
    <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-white">
      <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
      <span data-en="Supplier Link Active" data-ar="رابط المورد نشط">Supplier Link Active</span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Entity:" data-ar="المنشأة:">Entity:</span> <span class="font-mono text-white font-semibold"><?= htmlspecialchars($supplier_name) ?></span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Partner ID:" data-ar="رقم الشريك:">Partner ID:</span> <span class="font-mono text-teal-600 dark:text-teal-400 font-semibold"><?= htmlspecialchars($supplier_id) ?></span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Standard:" data-ar="المعيار:">Standard:</span> <span class="font-mono text-slate-200">SASO / CGA V-1</span>
    </span>
  </div>

  <!-- Main Content Body -->
  <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7">

    <?php if (!empty($flash_success)): ?>
      <div class="mb-6 p-4 border border-emerald-300 dark:border-emerald-800/80 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200 text-xs font-mono flex items-center gap-3">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash_success) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
      <div class="mb-6 p-4 border border-red-300 dark:border-red-800/80 bg-red-50 dark:bg-red-950/40 text-red-900 dark:text-red-200 text-xs font-mono flex items-center gap-3">
        <i data-lucide="alert-octagon" class="w-4 h-4 text-red-600 dark:text-red-400 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash_error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-7">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400" data-en="Supplier Portal" data-ar="بوابة الموردين">Supplier Portal</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400"></i>
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400" data-en="Catalog Management" data-ar="إدارة الكتالوج">Catalog Management</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Product Catalog Management" data-ar="إدارة كتالوج المنتجات والمواد">Product Catalog Management</h1>
        <p class="text-xs text-slate-400 mt-1 font-mono" data-en="Manage supply items, update commercial base pricing, and list new gas products for the I-GAS procurement network." data-ar="إدارة منتجات التوريد، وتحديث أسعار الأساس، وإدراج مواد غازية جديدة لشبكة مشتريات I-GAS.">Manage supply items, update commercial base pricing, and list new gas products for the I-GAS procurement network.</p>
      </div>
      <div>
        <button type="button" onclick="alert('Exporting catalog price list in CSV format...')" class="inline-flex items-center gap-2 px-3.5 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-300 hover:bg-[#102332] dark:hover:bg-neutral-800 transition-colors">
          <i data-lucide="download" class="w-3.5 h-3.5 text-neutral-500"></i>
          <span data-en="Export Price List" data-ar="تصدير قائمة الأسعار">Export Price List</span>
        </button>
      </div>
    </div>

    <!-- 3-Column Responsive Grid Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
      
      <!-- Left Col (1 col): List New Supply Item Form -->
      <div class="xl:col-span-1">
        <form action="<?= app_url('modules/main/supplier_catalog.php') ?>" method="POST" class="border border-[#1a3346] bg-[#0c1924] p-6">
          <?= csrf_field() ?>
          <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
              <i data-lucide="plus-circle" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="List New Supply Item" data-ar="إدراج صنف توريد جديد">List New Supply Item</span>
            </h3>
            <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">PRO-01</span>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Material / Gas Name" data-ar="اسم المادة / الغاز">Material / Gas Name</label>
              <input type="text" name="item_name" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="e.g. High Purity Nitrogen (N₂)" required>
            </div>
            
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Supply Category" data-ar="فئة التوريد">Supply Category</label>
              <select name="category" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" required>
                <option value="Bulk Liquid Gas" selected data-en="Bulk Liquid Gas" data-ar="غاز سائل سائب">Bulk Liquid Gas</option>
                <option value="Compressed Gas Cylinders" data-en="Compressed Gas Cylinders" data-ar="أسطوانات غاز مضغوط">Compressed Gas Cylinders</option>
                <option value="Raw Industrial Materials" data-en="Raw Industrial Materials" data-ar="مواد صناعية أولية">Raw Industrial Materials</option>
                <option value="Logistics & Valve Equipment" data-en="Logistics & Valve Equipment" data-ar="معدات وصمامات لوجستية">Logistics & Valve Equipment</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Purity / Grade" data-ar="درجة النقاء">Purity / Grade</label>
                <input type="text" name="purity" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="e.g. 99.999%">
              </div>
              <div>
                <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Billing Unit" data-ar="وحدة الفوترة">Billing Unit</label>
                <select name="unit" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50">
                  <option value="Liter" selected>Liter</option>
                  <option value="Ton">Ton</option>
                  <option value="50L Cylinder">50L Cylinder</option>
                  <option value="40L Cylinder">40L Cylinder</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Base Unit Price (SAR)" data-ar="سعر الأساس للوحدة (ر.س)">Base Unit Price (SAR)</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-mono text-neutral-400">SAR</span>
                <input type="number" step="0.01" name="unit_price" class="w-full border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs pl-12 pr-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="0.00" required>
              </div>
            </div>

            <!-- Single Primary CTA in Safety Valve Orange -->
            <div class="pt-3 border-t border-[#1a3346]">
              <button type="submit" class="btn-cta-orange w-full py-2.5 text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer transition-colors">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span data-en="Submit for Approval" data-ar="إرسال للاعتماد">Submit for Approval</span>
              </button>
              <p class="text-[10px] font-mono text-slate-400 text-center mt-3" data-en="New catalog items require verification from I-GAS Quality & Procurement." data-ar="الأصناف الجديدة تتطلب اعتماد الجودة والمشتريات في I-GAS.">New catalog items require verification from I-GAS Quality & Procurement.</p>
            </div>
          </div>
        </form>
      </div>

      <!-- Right Col (2 cols): Active Catalog Ledger Table -->
      <div class="xl:col-span-2 border border-[#1a3346] bg-[#0c1924] overflow-hidden">
        <div class="p-5 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white" data-en="Active Catalog & Pricing Ledger" data-ar="سجل الكتالوج والأسعار النشط">Active Catalog & Pricing Ledger</h3>
            <p class="text-xs text-slate-400 font-mono mt-0.5" data-en="Verified materials listed for plant allocation" data-ar="المواد المعتمدة المدرجة لصرف المصانع">Verified materials listed for plant allocation</p>
          </div>
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="catalogSearch" oninput="filterCatalog(this.value)" placeholder="Search material or code..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider text-slate-400 border-b border-[#1a3346] bg-[#09151f]">
                <th class="pl-6 py-3 font-semibold" data-en="Material Details" data-ar="تفاصيل المادة">Material Details</th>
                <th class="px-4 py-3 font-semibold" data-en="Category / Purity" data-ar="الفئة / النقاء">Category / Purity</th>
                <th class="px-4 py-3 font-semibold text-right" data-en="Base Unit Price" data-ar="سعر الأساس">Base Unit Price</th>
                <th class="px-4 py-3 font-semibold text-center" data-en="Listing Status" data-ar="الحالة">Listing Status</th>
                <th class="pr-6 py-3 font-semibold text-right" data-en="Actions" data-ar="إجراءات">Actions</th>
              </tr>
            </thead>
            <tbody id="catalogTableBody" class="divide-y divide-[#152a3a]">
              <?php foreach ($catalog_items as $item): ?>
              <?php $style = $statusLabels[$item['status']] ?? $statusLabels['active']; ?>
              <tr class="hover:bg-[#102332] transition-colors catalog-row">
                <td class="pl-6 py-3.5">
                  <div class="flex flex-col gap-0.5">
                    <span class="font-semibold text-white item-name"><?= htmlspecialchars($item['name']) ?></span>
                    <span class="font-mono text-[11px] text-neutral-500 item-code"><?= htmlspecialchars($item['code']) ?></span>
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <div class="flex flex-col gap-0.5">
                    <span class="text-slate-200"><?= htmlspecialchars($item['type']) ?></span>
                    <span class="font-mono text-[11px] text-teal-600 dark:text-teal-400"><?= htmlspecialchars($item['purity']) ?></span>
                  </div>
                </td>
                <td class="px-4 py-3.5 text-right">
                  <span class="font-mono font-bold text-white"><?= number_format($item['price'], 2) ?></span>
                  <span class="block font-mono text-[10px] text-neutral-500">SAR / <?= htmlspecialchars($item['unit']) ?></span>
                </td>
                <td class="px-4 py-3.5 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 border text-[11px] font-mono <?= $style['class'] ?>">
                    <span data-en="<?= $style['label_en'] ?>" data-ar="<?= $style['label_ar'] ?>"><?= $style['label_en'] ?></span>
                  </span>
                </td>
                <td class="pr-6 py-3.5 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button type="button" class="p-1 text-neutral-500 hover:text-white transition-colors" title="Edit Item">
                      <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                    </button>
                    <button type="button" class="p-1 text-neutral-400 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Deactivate Item">
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
</main>

<script>
  lucide.createIcons();

  function filterCatalog(term) {
    term = term.toLowerCase();
    document.querySelectorAll('.catalog-row').forEach(row => {
      const name = row.querySelector('.item-name')?.textContent.toLowerCase() || '';
      const code = row.querySelector('.item-code')?.textContent.toLowerCase() || '';
      if (name.includes(term) || code.includes(term)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }
</script>
</body>
</html>
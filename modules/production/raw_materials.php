<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('materials.view');

$active_page = 'raw_materials';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Raw Materials'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_permission('materials.delete');
  require_csrf();
  $delete_id = isset($_POST['material_id']) ? (int)$_POST['material_id'] : 0;

  if ($delete_id > 0) {
    $del = $pdo->prepare("DELETE FROM raw_materials WHERE id = :id");
    $del->execute(['id' => $delete_id]);
  }

  redirect(app_url('modules/production/raw_materials.php'));
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
$total_items  = 0;
$low_stock   = 0;
$out_of_stock = 0;
$total_value  = 0;

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
  'optimal' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'In Stock', 'label_ar' => 'متوفر'],
  'low'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Low Stock', 'label_ar' => 'مخزون منخفض'],
  'out'   => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Out of Stock', 'label_ar' => 'نفد المخزون'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Raw Materials | I-GAS Enterprise" data-ar="المواد الخام | I-GAS Enterprise">Raw Materials | I-GAS Enterprise</title>
  
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
        <span data-en="RAW FEEDSTOCK & TANK FARM INVENTORY" data-ar="مخزون المواد الخام وخزانات التبريد">RAW FEEDSTOCK & TANK FARM INVENTORY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">CATALOG: <?= $total_items ?> SKUs</span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Page Title & Header Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="PRODUCTION INVENTORY MASTER" data-ar="سجل مخزون الإنتاج">PRODUCTION INVENTORY MASTER</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Raw Materials" data-ar="المواد الخام">
            Raw Materials
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Monitor bulk liquid feedstock, calibration gases, cylinder fittings, and process additives." data-ar="مراقبة الغازات السائلة السائبة وغازات المعايرة وتجهيزات الأسطوانات وإضافات العمليات.">
            Monitor bulk liquid feedstock, calibration gases, cylinder fittings, and process additives.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= app_url('modules/production/new_purchase_order.php') ?>" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-slate-400"></i>
            <span data-en="Procurement Order" data-ar="أمر توريد">Procurement Order</span>
          </a>
          <?php if (has_permission('materials.create')): ?>
          <a href="<?= app_url('modules/production/new_material.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span data-en="Add Material" data-ar="إضافة مادة">Add Material</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Stocked Items -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Total Stocked Items" data-ar="إجمالي العناصر">Total Stocked Items</span>
            <i data-lucide="boxes" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-white tabular-nums">
            <?= number_format($total_items) ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span data-en="Catalog SKUs" data-ar="أصناف الكتالوج">Catalog SKUs</span>
            <span class="text-teal-600 dark:text-teal-400 font-semibold">Active Matrix</span>
          </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-amber-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Low Stock Alerts" data-ar="تنبيهات نقص المخزون">Low Stock Alerts</span>
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-amber-400 dark:text-amber-400 tabular-nums">
            <?= number_format($low_stock) ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span data-en="Below Safety Stock" data-ar="أقل من حد الأمان">Below Safety Stock</span>
            </span>
            <span>Reorder Now</span>
          </div>
        </div>

        <!-- Out of Stock -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-rose-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Out of Stock" data-ar="نفد من المخزون">Out of Stock</span>
            <i data-lucide="x-circle" class="w-4 h-4 text-rose-600 dark:text-rose-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-rose-400 dark:text-rose-400 tabular-nums">
            <?= number_format($out_of_stock) ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400 whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              <span data-en="Depleted Feedstock" data-ar="مخزون مستنفد">Depleted Feedstock</span>
            </span>
            <span>Critical</span>
          </div>
        </div>

        <!-- Total Valuation -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-emerald-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Total Inventory Value" data-ar="إجمالي قيمة المخزون">Total Inventory Value</span>
            <i data-lucide="badge-dollar-sign" class="w-4 h-4 text-emerald-600 dark:text-emerald-500"></i>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-2xl font-mono font-bold text-white tabular-nums"><?= number_format($total_value, 2) ?></span>
            <span class="text-xs font-mono text-neutral-500">SAR</span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span data-en="Unit Cost Basis" data-ar="أساس تكلفة الوحدة">Unit Cost Basis</span>
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Audited</span>
          </div>
        </div>
      </div>

      <!-- Materials Master List Table Container -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm flex flex-col overflow-hidden">
        <!-- Filters Bar -->
        <div class="p-4 sm:p-5 border-b border-[#1a3346] flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#071018]">
          <div class="flex flex-wrap items-center gap-4">
            <h2 class="text-base font-mono font-semibold text-white" data-en="Materials Master Registry" data-ar="سجل المواد الرئيسي">
              Materials Master Registry
            </h2>
            <div class="flex items-center gap-2 text-xs font-mono">
              <button type="button" class="tab-pill px-3 py-1 border border-teal-600 bg-teal-950/30 dark:bg-teal-950 text-teal-300 dark:text-teal-300 rounded-sm font-semibold filter-btn active whitespace-nowrap" data-filter="all">
                <span data-en="All" data-ar="الكل">All</span> (<?= count($materials) ?>)
              </button>
              <button type="button" class="tab-pill px-3 py-1 border border-[#1e3a50] bg-[#0c1924] text-slate-300 rounded-sm hover:border-neutral-400 filter-btn whitespace-nowrap" data-filter="low">
                <span data-en="Low Stock" data-ar="مخزون منخفض">Low Stock</span> (<?= count($low_stock_list) ?>)
              </button>
              <button type="button" class="tab-pill px-3 py-1 border border-[#1e3a50] bg-[#0c1924] text-slate-300 rounded-sm hover:border-neutral-400 filter-btn whitespace-nowrap" data-filter="out">
                <span data-en="Out of Stock" data-ar="نفد">Out of Stock</span> (<?= count($out_of_stock_list) ?>)
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search SKU, name, supplier..." data-en="Search SKU, name, supplier..." data-ar="البحث برقم الصنف أو الاسم..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
            <select id="categoryFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
              <option value="all" data-en="All Categories" data-ar="جميع الفئات">All Categories</option>
              <option value="Bulk Gas" data-en="Bulk Gas" data-ar="الغاز السائب">Bulk Gas</option>
              <option value="Chemicals" data-en="Chemicals" data-ar="المواد الكيميائية">Chemicals</option>
              <option value="Containers" data-en="Containers" data-ar="الحاويات">Containers</option>
              <option value="Spare Parts" data-en="Spare Parts" data-ar="قطع الغيار">Spare Parts</option>
            </select>
          </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                <th class="px-4 py-3 font-semibold w-36">Material SKU</th>
                <th class="px-4 py-3 font-semibold" data-en="Description / Grade" data-ar="الوصف / الدرجة">Description / Grade</th>
                <th class="px-4 py-3 font-semibold" data-en="Category" data-ar="الفئة">Category</th>
                <th class="px-4 py-3 font-semibold text-right" data-en="Current Stock" data-ar="المخزون الحالي">Current Stock</th>
                <th class="px-4 py-3 font-semibold" data-en="Primary Supplier" data-ar="المورد الأساسي">Primary Supplier</th>
                <th class="px-4 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left w-28" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody id="tableBody" class="text-xs divide-y divide-[#152a3a]">
              <?php if (empty($materials)): ?>
              <tr>
                <td colspan="7" class="text-center py-12 text-neutral-500 font-mono" data-en="No raw materials registered currently." data-ar="لا توجد مواد خام مسجلة حالياً.">
                  No raw materials registered currently.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($materials as $m): ?>
                <?php
                  $statusObj = $statusStyles[$m['status']];
                  $searchString = strtolower($m['id'] . ' ' . $m['name'] . ' ' . $m['category'] . ' ' . $m['supplier']);
                ?>
                <tr class="hover:bg-[#102332] transition-colors table-row-item" data-status="<?= htmlspecialchars($m['status']) ?>" data-category="<?= htmlspecialchars($m['category']) ?>" data-search="<?= htmlspecialchars($searchString) ?>">
                  <td class="px-4 py-3 font-mono font-semibold tabular-nums text-teal-400 dark:text-teal-400 whitespace-nowrap">
                    <?= htmlspecialchars($m['id']) ?>
                  </td>
                  <td class="px-4 py-3 font-medium text-white">
                    <a href="<?= app_url('modules/production/material_details.php?id=' . urlencode($m['row_id'])) ?>" class="hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                      <?= htmlspecialchars($m['name']) ?>
                    </a>
                  </td>
                  <td class="px-4 py-3 text-slate-400 font-mono whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-sm bg-[#09151f] border border-[#1e3a50] text-[11px]">
                      <?= htmlspecialchars($m['category']) ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right font-mono font-semibold tabular-nums whitespace-nowrap <?= $m['status'] === 'low' ? 'text-amber-600 dark:text-amber-400' : ($m['status'] === 'out' ? 'text-rose-600 dark:text-rose-400' : 'text-white') ?>">
                    <?= number_format($m['stock']) ?> <span class="text-[11px] font-normal text-neutral-500"><?= htmlspecialchars($m['unit']) ?></span>
                  </td>
                  <td class="px-4 py-3 text-slate-400 font-mono whitespace-nowrap">
                    <?= htmlspecialchars($m['supplier']) ?>
                  </td>
                  <td class="px-4 py-3 text-center whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-sm text-[11px] font-mono border <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                      <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right rtl:text-left whitespace-nowrap">
                    <div class="flex items-center justify-end gap-2">
                      <a href="<?= app_url('modules/production/material_details.php?id=' . urlencode($m['row_id'])) ?>" class="px-2 py-1 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm transition-colors" data-en="View" data-ar="عرض">
                        View
                      </a>
                      <?php if (has_permission('materials.delete')): ?>
                      <form method="POST" action="" class="m-0 p-0 inline" onsubmit="return confirm('Are you sure you want to delete this material? / هل أنت متأكد من حذف هذه المادة؟');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="material_id" value="<?= htmlspecialchars($m['row_id']) ?>">
                        <button type="submit" class="p-1 text-neutral-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors rounded-sm" title="Delete Material">
                          <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Footer Summary Pagination -->
        <div class="p-3.5 border-t border-[#1a3346] flex justify-between items-center bg-[#071018] text-xs font-mono text-neutral-500">
          <span id="showingCount" data-en="Showing <?= count($materials) > 0 ? '1' : '0' ?>–<?= count($materials) ?> of <?= count($materials) ?> Items" data-ar="عرض <?= count($materials) > 0 ? '1' : '0' ?>–<?= count($materials) ?> من أصل <?= count($materials) ?> من العناصر">
            Showing <?= count($materials) > 0 ? '1' : '0' ?>–<?= count($materials) ?> of <?= count($materials) ?> Items
          </span>
          <div class="text-[11px] text-neutral-400">
            <span data-en="Industrial Feedstock & Raw Gas Tank Farm Inventory" data-ar="مخزون المواد الخام وخزانات الغاز الصناعي">Industrial Feedstock & Raw Gas Tank Farm Inventory</span>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    document.addEventListener("DOMContentLoaded", function() {
      const searchInput = document.getElementById('searchInput');
      const categoryFilter = document.getElementById('categoryFilter');
      const filterButtons = document.querySelectorAll('.filter-btn');
      const rows = document.querySelectorAll('.table-row-item');
      let currentStatusFilter = 'all';

      function filterTable() {
        const query = searchInput ? searchInput.value.toLowerCase() : '';
        const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
        let visibleCount = 0;

        rows.forEach(row => {
          const status = row.getAttribute('data-status');
          const category = row.getAttribute('data-category');
          const searchData = row.getAttribute('data-search');

          const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);
          const matchesCategory = (selectedCategory === 'all' || category === selectedCategory);
          const matchesSearch = searchData.includes(query);

          if (matchesStatus && matchesCategory && matchesSearch) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });
        
        const showingCountEl = document.getElementById('showingCount');
        if (showingCountEl) {
          showingCountEl.innerText = `Showing ${visibleCount} item(s)`;
        }
      }

      if (searchInput) searchInput.addEventListener('input', filterTable);
      if (categoryFilter) categoryFilter.addEventListener('change', filterTable);

      filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          filterButtons.forEach(b => {
            b.classList.remove('active', 'border-teal-600', 'bg-teal-950/30', 'dark:bg-teal-950', 'text-teal-300', 'dark:text-teal-300', 'font-semibold');
            b.classList.add('border-neutral-300', 'border-[#1e3a50]', 'bg-[#112433]', 'bg-[#0c1924]', 'text-slate-300', 'text-slate-300');
          });
          btn.classList.add('active', 'border-teal-600', 'bg-teal-950/30', 'dark:bg-teal-950', 'text-teal-300', 'dark:text-teal-300', 'font-semibold');
          btn.classList.remove('border-neutral-300', 'border-[#1e3a50]', 'bg-[#112433]', 'bg-[#0c1924]', 'text-slate-300', 'text-slate-300');
          currentStatusFilter = btn.getAttribute('data-filter');
          filterTable();
        });
      });
    });
  </script>
</body>
</html>
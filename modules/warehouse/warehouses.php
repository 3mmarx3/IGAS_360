<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'warehouse.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_permission($pdo, 'warehouse.manage');
  require_csrf();
  $delete_id = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
  if ($delete_id) {
    try {
      $pdo->beginTransaction();
      $stmt_trans = $pdo->prepare("DELETE FROM warehouse_transactions WHERE warehouse_id = ?");
      $stmt_trans->execute([$delete_id]);

      $stmt_inv = $pdo->prepare("DELETE FROM warehouse_inventory WHERE warehouse_id = ?");
      $stmt_inv->execute([$delete_id]);

      $stmt_del = $pdo->prepare("DELETE FROM warehouses WHERE id = ?");
      $stmt_del->execute([$delete_id]);

      $pdo->commit();
      redirect(app_url('modules/warehouse/warehouses.php'));
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
    }
  }
}

$active_page = 'inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Warehouse & Inventory', 'All Warehouses'];

$stmt = $pdo->query("
  SELECT w.*, p.id as client_partner_id, p.company_name as client_name,
  (SELECT COUNT(*) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) as sku_count,
  (SELECT COALESCE(SUM(quantity_on_hand), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) as total_qty
  FROM warehouses w 
  LEFT JOIN partners p ON w.client_id = p.id 
  ORDER BY w.created_at DESC
  LIMIT 150
");
$records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$warehouses = [];
$total_warehouses = count($records);
$internal_count = 0;
$client_count = 0;
$total_items_count = 0;

foreach ($records as $row) {
  $type = $row['warehouse_type'] ?? 'internal';
  if ($type === 'internal') {
    $internal_count++;
  } else {
    $client_count++;
  }
  $total_items_count += (int)($row['total_qty'] ?? 0);

  $warehouses[] = [
    'id'    => $row['id'],
    'code'   => $row['warehouse_code'] ?? ($row['code'] ?? ('WH-' . $row['id'])),
    'name'   => $row['warehouse_name'] ?? ($row['name'] ?? 'Main Warehouse'),
    'type'   => $type,
    'client_id' => $row['client_partner_id'] ?? ($row['client_id'] ?? null),
    'client'  => $row['client_name'] ?? 'N/A',
    'location' => $row['location'] ?? 'Jeddah Industrial Hub',
    'sku_count' => (int)($row['sku_count'] ?? 0),
    'total_qty' => (float)($row['total_qty'] ?? 0),
    'status'  => $row['status'] ?? 'active'
  ];
}

$typeStyles = [
  'internal' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'border' => 'border-emerald-300 dark:border-emerald-800', 'dot' => 'bg-emerald-500', 'label_en' => 'Internal Plant Depot', 'label_ar' => 'مستودع مصنع داخلي'],
  'client'  => ['bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'border' => 'border-amber-300 dark:border-amber-800', 'dot' => 'bg-amber-500', 'label_en' => 'Client Custody Yard', 'label_ar' => 'عهدة عميل / موقع خارجي'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Warehouses Directory | I-GAS Control Room" data-ar="دليل المستودعات وساحات الأسطوانات | غرفة التحكم I-GAS">Warehouses Directory | I-GAS Control Room</title>
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
        <span data-en="DEPOT TELEMETRY & YARD MANIFOLD REPOSITORY" data-ar="مستودعات الغاز وساحات التخزين الميداني">DEPOT TELEMETRY & YARD MANIFOLD REPOSITORY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="FACILITIES:" data-ar="المواقع:">FACILITIES:</span> <strong class="text-white font-mono"><?= $total_warehouses ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="TOTAL CYLINDERS/ITEMS:" data-ar="إجمالي المخزون:">TOTAL CYLINDERS/ITEMS:</span> <strong class="text-white font-mono"><?= number_format($total_items_count) ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: WH-INVENTORY</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
          <span data-en="Warehouse & Inventory" data-ar="المستودعات والمخزون">Warehouse & Inventory</span>
          <span>/</span>
          <span class="text-white font-semibold" data-en="Facilities" data-ar="المواقع والمستودعات">Facilities</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
          <i data-lucide="warehouse" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
          <span data-en="Warehouses & Depots Directory" data-ar="دليل المستودعات وساحات التخزين">Warehouses & Depots Directory</span>
        </h1>
        <p class="text-sm text-slate-400 mt-1" data-en="Manage centralized plant facilities, satellite stock points, and client on-site custody stores." data-ar="إدارة المستودعات المركزية بالمصنع، ونقاط التوزيع الفرعية، وخزانات وعهد الغاز لدى العملاء.">
          Manage centralized plant facilities, satellite stock points, and client on-site custody stores.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <a href="withdraw.php" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-colors">
          <i data-lucide="arrow-right-left" class="w-4 h-4 text-slate-400"></i>
          <span data-en="Stock Transfer" data-ar="تحويل مخزون">Stock Transfer</span>
        </a>
        <?php if (has_permission($pdo, 'warehouse.manage')): ?>
        <a href="new_warehouse.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="New Warehouse Depot" data-ar="مستودع جديد">New Warehouse Depot</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4 KPI Telemetry Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      <!-- KPI 1: Total Facilities -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Total Facilities" data-ar="إجمالي المواقع">
          Total Facilities
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_warehouses) ?>
          </span>
          <span class="text-[10px] font-mono text-neutral-500">STORAGE NODES</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Active depots & custody sites" data-ar="المستودعات النشطة ومواقع العهد">
          Active depots & custody sites
        </div>
      </div>

      <!-- KPI 2: Internal Depots -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Internal Depots" data-ar="المستودعات الداخلية">
          Internal Depots
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($internal_count) ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
            <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
            <span data-en="Plant Owned" data-ar="تابع للمصنع">Plant Owned</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Company owned filling yards" data-ar="ساحات التعبئة والتخزين المركزية">
          Company owned filling yards
        </div>
      </div>

      <!-- KPI 3: Client Custodies -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Client Custody Yards" data-ar="عهد العملاء الخارجية">
          Client Custody Yards
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($client_count) ?>
          </span>
          <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase rounded-xs bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-300 dark:border-amber-800">
            <span class="w-1 h-1 rounded-full bg-amber-500"></span>
            <span data-en="Custody" data-ar="عهدة">Custody</span>
          </span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="On-site customer tanks & manifolds" data-ar="خزانات وأجهزة التخفيض لدى العملاء">
          On-site customer tanks & manifolds
        </div>
      </div>

      <!-- KPI 4: Total Stored Cylinders -->
      <div class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs border-s-4 border-s-[#f97316]">
        <div class="text-[11px] font-mono uppercase tracking-wider text-neutral-500 mb-1" data-en="Total Stored Units" data-ar="إجمالي الوحدات المخزنة">
          Total Stored Units
        </div>
        <div class="flex items-baseline justify-between">
          <span class="text-2xl font-bold font-mono text-white">
            <?= number_format($total_items_count) ?>
          </span>
          <span class="text-[10px] font-mono text-teal-600 dark:text-teal-400">CYLINDERS / VALVES</span>
        </div>
        <div class="mt-2 text-[11px] font-sans text-neutral-500" data-en="Across all bays and yards" data-ar="في جميع الأقسام والساحات">
          Across all bays and yards
        </div>
      </div>

    </div>

    <!-- Registry & Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Controls Header: Title + Search -->
      <div class="p-4 border-b border-[#1a3346] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-2">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Facility Locations & Manifolds" data-ar="مواقع المستودعات والساحات">
            Facility Locations & Manifolds
          </h2>
        </div>

        <!-- Search Box -->
        <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="whSearch" placeholder="Search code, depot, client..." data-en-placeholder="Search code, depot, client..." data-ar-placeholder="ابحث عن الكود، المستودع، العميل..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-start text-xs border-collapse" id="whTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#09151f] text-slate-400 font-mono uppercase text-[10px] tracking-wider">
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Facility Code" data-ar="كود المستودع">Facility Code</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Depot / Warehouse Name" data-ar="اسم المستودع">Depot / Warehouse Name</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Facility Type" data-ar="نوع المنشأة">Facility Type</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Location / Client" data-ar="الموقع / العميل المرتبط">Location / Client</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Stock SKUs" data-ar="الأصناف">Stock SKUs</th>
              <th class="py-2.5 px-3 text-start font-semibold" data-en="Status" data-ar="الحالة">Status</th>
              <th class="py-2.5 px-3 text-end font-semibold" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($warehouses)): ?>
              <tr class="empty-state">
                <td colspan="7" class="py-8 text-center text-slate-400 font-mono text-xs" data-en="No warehouses registered in the system." data-ar="لا توجد مستودعات مسجلة في النظام.">
                  No warehouses registered in the system.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($warehouses as $wh): ?>
                <?php 
                  $t = $typeStyles[$wh['type']] ?? $typeStyles['internal'];
                  $isActive = strtolower($wh['status']) === 'active';
                ?>
                <tr class="wh-row hover:bg-[#102332] transition-colors">
                  <td class="py-2.5 px-3 font-mono font-bold text-white">
                    <a href="<?= app_url('modules/warehouse/warehouse_profile.php?id=' . $wh['id']) ?>" class="hover:text-cyan-400 text-teal-400">
                      <?= htmlspecialchars($wh['code']) ?>
                    </a>
                  </td>
                  <td class="py-2.5 px-3 font-medium text-white">
                    <a href="<?= app_url('modules/warehouse/warehouse_profile.php?id=' . $wh['id']) ?>" class="hover:text-cyan-400">
                      <?= htmlspecialchars($wh['name']) ?>
                    </a>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-mono uppercase tracking-wider rounded-xs border <?= $t['bg'] ?> <?= $t['text'] ?> <?= $t['border'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $t['dot'] ?>"></span>
                      <span data-en="<?= $t['label_en'] ?>" data-ar="<?= $t['label_ar'] ?>"><?= $t['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-slate-300">
                    <?php if (!empty($wh['client_id']) && $wh['client'] !== 'N/A'): ?>
                      <div class="flex flex-col">
                        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . (int)$wh['client_id']) ?>" class="font-medium text-cyan-400 hover:underline">
                          <?= htmlspecialchars($wh['client']) ?> &rarr;
                        </a>
                        <span class="text-[11px] text-slate-400"><?= htmlspecialchars($wh['location']) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="text-slate-300"><?= htmlspecialchars($wh['location']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3 text-end font-mono font-semibold text-white">
                    <?= number_format($wh['sku_count']) ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-mono uppercase rounded-xs <?= $isActive ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-800' : 'bg-[#112433] text-slate-300 border border-[#1e3a50]' ?>">
                      <span class="w-1 h-1 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-neutral-400' ?>"></span>
                      <span><?= ucfirst($wh['status']) ?></span>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-end">
                    <div class="inline-flex items-center justify-end gap-1.5">
                      <a href="<?= app_url('modules/warehouse/warehouse_profile.php?id=' . $wh['id']) ?>" class="btn-table-action whitespace-nowrap" title="View Yard Manifest">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                      </a>
                      <?php if (has_permission($pdo, 'warehouse.manage')): ?>
                      <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this warehouse depot? / هل أنت متأكد من حذف هذا المخزن؟');" class="inline-block m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="warehouse_id" value="<?= $wh['id'] ?>">
                        <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Delete Depot">
                          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <tr id="noResultsRow" style="display: none;">
              <td colspan="7" class="py-8 text-center text-neutral-500 font-mono text-xs" data-en="No warehouses matched your search query." data-ar="لا توجد مستودعات مطابقة للبحث.">
                No warehouses matched your search query.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer -->
      <div class="p-4 border-t border-[#1a3346] bg-[#071018] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs font-mono text-neutral-500">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <span id="visibleCount" class="font-semibold text-white"><?= count($warehouses) ?></span> <span data-en="storage facilities" data-ar="مواقع تخزين">storage facilities</span>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] transition-colors">
            <i data-lucide="chevron-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
          <span class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] text-white text-white rounded-xs font-mono">1</span>
          <button type="button" class="px-2 py-1 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] transition-colors">
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          </button>
        </div>
      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
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

    const search = document.getElementById('whSearch');
    const rows = document.querySelectorAll('.wh-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');

    if (search) {
      search.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
          const matches = !q || row.innerText.toLowerCase().includes(q);
          row.style.display = matches ? '' : 'none';
          if (matches) visibleCount++;
        });

        if (visibleCountElem) visibleCountElem.innerText = visibleCount;
        if (noResultsRow) {
          noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }
      });
    }
  });
</script>
</body>
</html>
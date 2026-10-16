<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('suppliers.view');

$active_page = 'suppliers_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Suppliers Directory'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission('suppliers.delete');
  $supplier_id = $_POST['supplier_id'] ?? '';
  if (!empty($supplier_id)) {
    try {
      $del_stmt = $pdo->prepare("DELETE FROM partners WHERE reference_id = ? AND partner_type = 'supplier'");
      $del_stmt->execute([$supplier_id]);
      redirect('modules/production/suppliers_directory.php?deleted=1');
    } catch (PDOException $e) {
    }
  }
}

$stmt_stats = $pdo->prepare("
  SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    AVG(rating) as avg_rating
  FROM partners 
  WHERE partner_type = 'supplier'
");
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$total_suppliers = (int)($stats['total_count'] ?? 0);
$active_count  = (int)($stats['active_count'] ?? 0);
$pending_count  = (int)($stats['pending_count'] ?? 0);
$avg_rating   = number_format((float)($stats['avg_rating'] ?? 0), 1);

$stmt_suppliers = $pdo->prepare("
  SELECT 
    id as db_id,
    reference_id as id, 
    company_name as name, 
    segment as category, 
    CONCAT_WS(' ', contact_first_name, contact_last_name) as contact, 
    phone, 
    status, 
    rating 
  FROM partners 
  WHERE partner_type = 'supplier' 
  ORDER BY created_at DESC
");
$stmt_suppliers->execute();
$suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'approved' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Active', 'label_ar' => 'نشط'],
  'pending'  => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Pending', 'label_ar' => 'قيد الانتظار'],
  'suspended' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Restricted', 'label_ar' => 'مقيد'],
  'rejected' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Rejected', 'label_ar' => 'مرفوض'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Suppliers Directory | I-GAS Enterprise" data-ar="دليل الموردين | I-GAS Enterprise">Suppliers Directory | I-GAS Enterprise</title>
  
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
        <span data-en="PRODUCTION PROCUREMENT & FEEDSTOCK LOGISTICS" data-ar="مشتريات الإنتاج وسلاسل توريد المواد الخام">PRODUCTION PROCUREMENT & FEEDSTOCK LOGISTICS</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REGISTRY: <?= $total_suppliers ?> VENDORS</span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400 font-mono">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Page Title & Header Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="FEEDSTOCK & EQUIPMENT PROCUREMENT" data-ar="توريد المواد الخام والمعدات">FEEDSTOCK & EQUIPMENT PROCUREMENT</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Suppliers Directory" data-ar="دليل الموردين">
            Suppliers Directory
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Manage bulk gas suppliers, cryogenic equipment manufacturers, valves, and manifold vendors." data-ar="إدارة موردي الغاز السائب ومصنعي معدات التبريد العميق والصمامات وخطوط التوزيع.">
            Manage bulk gas suppliers, cryogenic equipment manufacturers, valves, and manifold vendors.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button onclick="window.print()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="download" class="w-3.5 h-3.5 text-slate-400"></i>
            <span data-en="Export Directory" data-ar="تصدير الدليل">Export Directory</span>
          </button>
          <?php if (has_permission('suppliers.create')): ?>
          <a href="<?= app_url('modules/production/new_supplier.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span data-en="Add Supplier" data-ar="إضافة مورد">Add Supplier</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Suppliers -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Total Suppliers" data-ar="إجمالي الموردين">Total Suppliers</span>
            <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-white tabular-nums">
            <?= $total_suppliers ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span data-en="Registered vendors" data-ar="موردين مسجلين">Registered vendors</span>
            <span class="text-teal-600 dark:text-teal-400 font-semibold">Active Base</span>
          </div>
        </div>

        <!-- Active Partners -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-emerald-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Active Partners" data-ar="الشركاء النشطون">Active Partners</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">
            <?= $active_count ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span data-en="Verified & Compliant" data-ar="موثق ومعتمد">Verified & Compliant</span>
            </span>
            <span class="font-semibold"><?= $total_suppliers > 0 ? round(($active_count / $total_suppliers) * 100) : 0 ?>%</span>
          </div>
        </div>

        <!-- Pending Verification -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-amber-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Pending Verification" data-ar="قيد التحقق">Pending Verification</span>
            <i data-lucide="clock" class="w-4 h-4 text-amber-600 dark:text-amber-500"></i>
          </div>
          <div class="text-3xl font-mono font-bold text-amber-400 dark:text-amber-400 tabular-nums">
            <?= $pending_count ?>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346] flex items-center justify-between text-[11px] font-mono text-neutral-500">
            <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span data-en="Awaiting Approval" data-ar="بانتظار الموافقة">Awaiting Approval</span>
            </span>
            <span>Auditing</span>
          </div>
        </div>

        <!-- Average Vendor Rating -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-4 border-s-4 border-s-[#f97316] dark:border-s-sky-500">
          <div class="flex items-center justify-between text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
            <span data-en="Avg Vendor Rating" data-ar="متوسط تقييم الموردين">Avg Vendor Rating</span>
            <i data-lucide="star" class="w-4 h-4 text-amber-500 fill-amber-500"></i>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-mono font-bold text-white tabular-nums"><?= $avg_rating ?></span>
            <span class="text-xs font-mono text-neutral-400">/ 5.0</span>
          </div>
          <div class="mt-3 pt-2.5 border-t border-neutral-100 border-[#1a3346]">
            <div class="h-1.5 w-full bg-[#09151f] rounded-full overflow-hidden border border-[#1e3a50]">
              <div class="h-full bg-teal-600 dark:bg-teal-500" style="width: <?= ($avg_rating > 0 ? ($avg_rating / 5) * 100 : 0) ?>%;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Supplier Database Table Container -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm flex flex-col overflow-hidden">
        <!-- Filters Bar -->
        <div class="p-4 sm:p-5 border-b border-[#1a3346] flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-[#071018]">
          <div class="flex flex-wrap items-center gap-4">
            <h2 class="text-base font-mono font-semibold text-white" data-en="Vendor Database" data-ar="قاعدة بيانات الموردين">
              Vendor Database
            </h2>
            <div class="flex items-center gap-2 text-xs font-mono">
              <button type="button" class="tab-pill px-3 py-1 border border-teal-600 bg-teal-950/30 dark:bg-teal-950 text-teal-300 dark:text-teal-300 rounded-sm font-semibold filter-btn active whitespace-nowrap" data-filter="all">
                <span data-en="All" data-ar="الكل">All</span> (<?= $total_suppliers ?>)
              </button>
              <button type="button" class="tab-pill px-3 py-1 border border-[#1e3a50] bg-[#0c1924] text-slate-300 rounded-sm hover:border-neutral-400 filter-btn whitespace-nowrap" data-filter="approved">
                <span data-en="Active" data-ar="نشط">Active</span> (<?= $active_count ?>)
              </button>
              <button type="button" class="tab-pill px-3 py-1 border border-[#1e3a50] bg-[#0c1924] text-slate-300 rounded-sm hover:border-neutral-400 filter-btn whitespace-nowrap" data-filter="pending">
                <span data-en="Pending" data-ar="قيد الانتظار">Pending</span> (<?= $pending_count ?>)
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search suppliers..." data-en="Search suppliers..." data-ar="البحث عن موردين..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
            <select id="categoryFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
              <option value="all" data-en="All Categories" data-ar="جميع الفئات">All Categories</option>
              <option value="Raw Materials" data-en="Raw Materials" data-ar="مواد خام">Raw Materials</option>
              <option value="Cylinders" data-en="Cylinders" data-ar="أسطوانات">Cylinders</option>
              <option value="Chemicals" data-en="Chemicals" data-ar="مواد كيميائية">Chemicals</option>
            </select>
          </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider border-b border-[#1a3346] bg-[#102230]/60 bg-[#0c1924] text-slate-400">
                <th class="px-4 py-3 font-semibold" data-en="Supplier ID" data-ar="رقم المورد">Supplier ID</th>
                <th class="px-4 py-3 font-semibold" data-en="Company Name" data-ar="اسم الشركة">Company Name</th>
                <th class="px-4 py-3 font-semibold" data-en="Category" data-ar="الفئة">Category</th>
                <th class="px-4 py-3 font-semibold" data-en="Contact Details" data-ar="بيانات الاتصال">Contact Details</th>
                <th class="px-4 py-3 font-semibold text-center" data-en="Rating" data-ar="التقييم">Rating</th>
                <th class="px-4 py-3 font-semibold text-center" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left w-28" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody id="tableBody" class="text-xs divide-y divide-[#152a3a]">
              <?php if (empty($suppliers)): ?>
                <tr>
                  <td colspan="7" class="text-center py-12 text-neutral-500 font-mono" data-en="No suppliers found in directory." data-ar="لا يوجد موردون في الدليل.">
                    No suppliers found in directory.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($suppliers as $s): ?>
                <?php
                  $statusObj = $statusStyles[$s['status']] ?? $statusStyles['pending'];
                  $searchString = strtolower($s['id'] . ' ' . $s['name'] . ' ' . $s['category'] . ' ' . $s['contact'] . ' ' . $s['phone']);
                  
                  $words = explode(' ', trim($s['name'] ?? ''));
                  $initials = '';
                  if (count($words) >= 2) {
                    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
                  } elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
                    $initials = strtoupper(mb_substr($words[0], 0, 2));
                  }
                ?>
                <tr class="hover:bg-[#102332] transition-colors table-row-item" data-status="<?= htmlspecialchars($s['status']) ?>" data-category="<?= htmlspecialchars($s['category'] ?? 'General') ?>" data-search="<?= htmlspecialchars($searchString) ?>">
                  <td class="px-4 py-3 font-mono font-semibold tabular-nums text-teal-400 dark:text-teal-400 whitespace-nowrap">
                    <?= htmlspecialchars($s['id']) ?>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-sm bg-[#112433] text-slate-200 flex items-center justify-center font-mono font-semibold text-[11px] flex-shrink-0 border border-[#1e3a50]">
                        <?= htmlspecialchars($initials) ?>
                      </div>
                      <div>
                        <a href="<?= app_url('modules/production/supplier_profile.php?id=' . urlencode($s['id'])) ?>" class="font-medium text-white hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                          <?= htmlspecialchars($s['name']) ?>
                        </a>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-400 font-mono whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-sm bg-[#09151f] border border-[#1e3a50] text-[11px]">
                      <?= htmlspecialchars($s['category'] ?? 'General') ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="font-medium text-white"><?= htmlspecialchars($s['contact'] ?: '—') ?></div>
                    <?php if (!empty($s['phone'])): ?>
                      <div class="text-[11px] font-mono text-neutral-500 tabular-nums"><?= htmlspecialchars($s['phone']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center whitespace-nowrap">
                    <div class="inline-flex items-center gap-1 font-mono font-semibold text-slate-200 tabular-nums">
                      <?= htmlspecialchars($s['rating']) ?>
                      <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-center whitespace-nowrap">
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-sm text-[11px] font-mono border <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                      <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right rtl:text-left whitespace-nowrap">
                    <div class="flex items-center justify-end gap-2">
                      <a href="<?= app_url('modules/production/supplier_profile.php?id=' . urlencode($s['id'])) ?>" class="px-2 py-1 text-xs font-mono uppercase tracking-wider bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-750 text-slate-200 rounded-sm transition-colors" data-en="Details" data-ar="التفاصيل">
                        Details
                      </a>
                      <?php if (has_permission('suppliers.delete')): ?>
                      <form method="POST" action="" class="m-0 p-0 inline" onsubmit="return confirm('Are you sure you want to remove this supplier? / هل أنت متأكد من حذف هذا المورد؟');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($s['id']) ?>">
                        <button type="submit" class="p-1 text-neutral-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors rounded-sm" title="Delete Supplier">
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
          <span id="showingCount" data-en="Showing 1–<?= count($suppliers) ?> of <?= $total_suppliers ?>" data-ar="عرض 1–<?= count($suppliers) ?> من أصل <?= $total_suppliers ?>">
            Showing 1–<?= count($suppliers) ?> of <?= $total_suppliers ?>
          </span>
          <div class="text-[11px] text-neutral-400">
            <span data-en="Industrial Feedstock & Cryogenic Supply Chain" data-ar="سلسلة إمداد المواد الخام والتبريد الصناعي">Industrial Feedstock & Cryogenic Supply Chain</span>
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
        const query = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
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
          showingCountEl.innerText = `Showing ${visibleCount} supplier(s)`;
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
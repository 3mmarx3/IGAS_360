<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'gases_mixtures';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Gases & Mixtures'];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mix_id'])) {
  require_permission('production.manage');
  require_csrf();
  try {
    $del_id = (int)$_POST['delete_mix_id'];
    $pdo->beginTransaction();

    $stmt_get = $pdo->prepare("SELECT mix_id, volume, components_data FROM gas_mixtures WHERE id = ?");
    $stmt_get->execute([$del_id]);
    $mix = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if ($mix) {
      $components = json_decode($mix['components_data'] ?? '[]', true);
      $volume = floatval($mix['volume'] ?? 0);

      if (is_array($components) && $volume > 0) {
        foreach ($components as $comp) {
          $refund_qty = (($comp['ratio'] ?? 0) / 100) * $volume;
          
          if ($refund_qty > 0 && !empty($comp['name'])) {
            $stmt_rm = $pdo->prepare("SELECT id, current_stock FROM raw_materials WHERE material_name = ? LIMIT 1");
            $stmt_rm->execute([$comp['name']]);
            $rm = $stmt_rm->fetch(PDO::FETCH_ASSOC);

            if ($rm) {
              $new_stock = (float)$rm['current_stock'] + $refund_qty;
              
              $stmt_upd = $pdo->prepare("UPDATE raw_materials SET current_stock = ? WHERE id = ?");
              $stmt_upd->execute([$new_stock, $rm['id']]);

              $tx_ref = 'RT-' . time() . '-' . rand(100, 999);
              $operator = $_SESSION['user_name'] ?? 'System';
              $stmt_tx = $pdo->prepare("INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by) VALUES (?, ?, 'in', ?, ?, ?)");
              $stmt_tx->execute([$rm['id'], $tx_ref, $refund_qty, ($mix['mix_id'] ?? 'MIX') . ' (Restored)', $operator]);
            }
          }
        }
      }

      $stmt_del = $pdo->prepare("DELETE FROM gas_mixtures WHERE id = ?");
      $stmt_del->execute([$del_id]);
      
      $pdo->commit();
      $success_message = "تم حذف تركيبة الغاز بنجاح وإعادة المواد الخام المستخدمة للمخزون.";
    } else {
      $pdo->rollBack();
      $error_message = "خطأ: تركيبة الغاز غير موجودة بالنظام.";
    }
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error_message = "خطأ بقاعدة البيانات: " . $e->getMessage();
  }
}

try {
  $total_gases = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures")->fetchColumn();
  $custom_mixes = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures WHERE category = 'Custom Mix'")->fetchColumn();
  $qc_pending  = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures WHERE status = 'testing'")->fetchColumn();
  $pure_gases_count = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures WHERE category = 'Pure Gas'")->fetchColumn();
  $mixes_count = (int)$pdo->query("SELECT COUNT(*) FROM gas_mixtures WHERE category != 'Pure Gas'")->fetchColumn();
  
  $daily_output = 5400; 

  $stmt = $pdo->query("SELECT * FROM gas_mixtures ORDER BY id DESC");
  $gases_db = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

  $gases = [];
  foreach ($gases_db as $row) {
    $gases[] = [
      'raw_id' => $row['id'],
      'id'   => $row['code'] ?? ($row['mix_id'] ?? ('MIX-' . $row['id'])),
      'name'  => $row['product_name'] ?? ($row['mixture_name'] ?? 'Gas Mixture'),
      'type'  => $row['category'] ?? 'Specialty Gas',
      'formula' => !empty($row['formula']) ? $row['formula'] : 'N/A',
      'purity' => !empty($row['purity']) ? $row['purity'] : 'Standard',
      'stock'  => floatval($row['stock_quantity'] ?? ($row['volume'] ?? 0)),
      'status' => $row['status'] ?? 'active'
    ];
  }
} catch (PDOException $e) {
  $gases = [];
  $total_gases = 0;
  $custom_mixes = 0;
  $qc_pending = 0;
  $pure_gases_count = 0;
  $mixes_count = 0;
  $daily_output = 0;
}

$statusStyles = [
  'certified' => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label' => 'QC Certified', 'label_ar' => 'معتمد من الجودة'],
  'active'  => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label' => 'Active', 'label_ar' => 'نشط'],
  'testing'  => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'In QC Lab', 'label_ar' => 'في مختبر الجودة'],
  'low_stock' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Low Stock', 'label_ar' => 'مخزون منخفض'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Gases & Mixtures | I-GAS Enterprise" data-ar="الغازات والمخاليط | I-GAS Enterprise">Gases & Mixtures | I-GAS Enterprise</title>
  
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
        <span data-en="GAS BLENDING & QC LAB" data-ar="خلط الغازات ومختبر الجودة">GAS BLENDING & QC LAB</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">PRODUCTS:</span>
        <span class="text-slate-300 font-semibold font-mono"><?= $total_gases ?></span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Flash Alerts -->
      <?php if ($success_message): ?>
        <div class="p-4 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($error_message): ?>
        <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-medium flex items-center gap-2">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="PRODUCTION & QUALITY" data-ar="الإنتاج والجودة">PRODUCTION & QUALITY</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="GAS SPECIFICATIONS" data-ar="مواصفات الغاز">GAS SPECIFICATIONS</span>
          </div>
          <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Gases & Mixtures" data-ar="الغازات والمخاليط">
            Gases & Mixtures
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Manage pure industrial gases, standard mixtures, and specialized calibration gas formulations." data-ar="إدارة الغازات الصناعية النقية، المخاليط القياسية، وتركيبات غازات المعايرة المتخصصة.">
            Manage pure industrial gases, standard mixtures, and specialized calibration gas formulations.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" onclick="exportGasesData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
          </button>
          <?php if (has_permission('production.create')): ?>
          <a href="<?= app_url('modules/production/new_mixture.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="Formulate Mix" data-ar="تركيب خليط جديد">Formulate Mix</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Total Gas Types" data-ar="إجمالي أنواع الغازات">Total Gas Types</p>
            <i data-lucide="flask-conical" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= htmlspecialchars($total_gases) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Pure gases & standard mixes" data-ar="غازات نقية ومخاليط قياسية">Pure gases & standard mixes</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Active Custom Mixes" data-ar="المخاليط المخصصة النشطة">Active Custom Mixes</p>
            <i data-lucide="test-tubes" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= htmlspecialchars($custom_mixes) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Client-specific formulations" data-ar="تركيبات مخصصة لعملاء">Client-specific formulations</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-emerald-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Daily Output (Cylinders)" data-ar="الإنتاج اليومي (أسطوانات)">Daily Output (Cylinders)</p>
            <i data-lucide="activity" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($daily_output) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">+5.2%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Plant filling line rate" data-ar="معدل خط تعبئة المصنع">Plant filling line rate</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-amber-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Pending QC Approval" data-ar="بانتظار موافقة الجودة">Pending QC Approval</p>
            <i data-lucide="microscope" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold tabular-nums <?= $qc_pending > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-white' ?>"><?= htmlspecialchars($qc_pending) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Awaiting lab certificate (CoA)" data-ar="بانتظار شهادة التحليل">Awaiting lab certificate (CoA)</p>
        </div>
      </div>

      <!-- Product Formulation Registry Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f]">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            <!-- Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar" id="gasTabs">
              <button type="button" onclick="filterTable('all', this)" class="gas-tab active px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-teal-600 text-teal-400 dark:text-teal-400 font-semibold cursor-pointer transition-colors" data-tab="all">
                <span data-en="All Products" data-ar="جميع المنتجات">All Products</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $total_gases ?></span>
              </button>
              <button type="button" onclick="filterTable('pure', this)" class="gas-tab px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="pure">
                <span data-en="Pure Gases" data-ar="غازات نقية">Pure Gases</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $pure_gases_count ?></span>
              </button>
              <button type="button" onclick="filterTable('mixes', this)" class="gas-tab px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="mixes">
                <span data-en="Mixtures" data-ar="المخاليط">Mixtures</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $mixes_count ?></span>
              </button>
              <button type="button" onclick="filterTable('qc', this)" class="gas-tab px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="qc">
                <span data-en="Quality Control" data-ar="مراقبة الجودة">Quality Control</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $qc_pending ?></span>
              </button>
            </div>

            <!-- Search & Category Filter -->
            <div class="flex items-center gap-3 w-full lg:w-auto">
              <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search gas name or formula..." data-en-placeholder="Search gas name or formula..." data-ar-placeholder="البحث باسم الغاز أو الصيغة..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
              <select id="categorySelectFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
                <option value="" data-en="All Categories" data-ar="جميع الفئات">All Categories</option>
                <option value="Pure Gas" data-en="Pure Gases" data-ar="غازات نقية">Pure Gases</option>
                <option value="Standard Mix" data-en="Standard Mixes" data-ar="مخاليط قياسية">Standard Mixes</option>
                <option value="Custom Mix" data-en="Custom Mixes" data-ar="مخاليط مخصصة">Custom Mixes</option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse text-xs" id="gasesTable" style="min-width: 850px;">
            <thead>
              <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="SKU / ID" data-ar="SKU / المعرف">SKU / ID</th>
                <th class="px-4 py-3 font-semibold" data-en="Gas Product" data-ar="منتج الغاز">Gas Product</th>
                <th class="px-4 py-3 font-semibold" data-en="Formula / Ratio" data-ar="التركيبة / الصيغة">Formula / Ratio</th>
                <th class="px-4 py-3 font-semibold" data-en="Purity Grade" data-ar="درجة النقاء">Purity Grade</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Volume / Stock" data-ar="الحجم / الرصيد">Volume / Stock</th>
                <th class="px-4 py-3 font-semibold" data-en="QC Status" data-ar="حالة الجودة">QC Status</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" style="width: 120px;" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]" id="productsTableBody">
              <?php foreach ($gases as $g): ?>
              <?php
                $statusObj = $statusStyles[$g['status']] ?? $statusStyles['testing'];
              ?>
              <tr class="product-row hover:bg-[#102332] transition-colors" data-cat="<?= htmlspecialchars($g['type']) ?>" data-status="<?= htmlspecialchars($g['status']) ?>">
                <td class="px-5 py-3 font-mono font-semibold">
                  <a href="<?= app_url('modules/production/gas_details.php?id=' . urlencode($g['raw_id'])) ?>" class="text-teal-400 dark:text-teal-400 hover:underline sku-text">
                    <?= htmlspecialchars($g['id']) ?>
                  </a>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div><?= gas_chip($g['name'] ?? 'O2', 'compact') ?></div>
                    <div class="flex flex-col">
                      <span class="font-medium text-white name-text"><?= htmlspecialchars($g['name']) ?></span>
                      <span class="text-[11px] font-mono text-slate-400 type-text"><?= htmlspecialchars($g['type']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 font-mono text-slate-200 formula-text">
                  <?= htmlspecialchars($g['formula']) ?>
                </td>
                <td class="px-4 py-3 font-mono text-slate-400">
                  <?= htmlspecialchars($g['purity']) ?>
                </td>
                <td class="px-4 py-3 text-right rtl:text-left font-mono font-bold text-white tabular-nums">
                  <?= number_format($g['stock']) ?> <span class="text-[11px] font-normal text-neutral-500">Cyl</span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                    <span data-en="<?= $statusObj['label'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label'] ?></span>
                  </span>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="<?= app_url('modules/production/gas_details.php?id=' . urlencode($g['raw_id'])) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 transition-colors inline-flex items-center" title="View Details">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                    <?php if (has_permission('production.manage')): ?>
                    <form method="POST" class="inline m-0 p-0" onsubmit="return confirmDelete();">
                      <?= csrf_field() ?>
                      <input type="hidden" name="delete_mix_id" value="<?= htmlspecialchars($g['raw_id']) ?>">
                      <button type="submit" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-rose-950/30 dark:hover:bg-rose-950/40 text-rose-600 dark:text-rose-400 transition-colors cursor-pointer" title="Delete & Restore">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              
              <tr id="emptyStateRow" style="display: none;">
                <td colspan="7" class="text-center py-8 text-xs text-slate-400" data-en="No formulations found matching filters." data-ar="لا توجد تركيبات تطابق معايير البحث.">
                  No formulations found matching filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex justify-between items-center text-xs font-mono text-slate-400">
          <span>Showing <span id="visibleCount"><?= count($gases) ?></span> of <?= htmlspecialchars($total_gases) ?> Products</span>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
    let currentFilterType = 'all';

    function filterTable(filterType, tabElement) {
      if (tabElement) {
        document.querySelectorAll('#gasTabs .gas-tab').forEach(el => {
          el.classList.remove('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
          el.classList.add('border-transparent', 'text-neutral-600', 'text-slate-400');
        });
        tabElement.classList.add('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
        tabElement.classList.remove('border-transparent', 'text-neutral-600', 'text-slate-400');
      }
      currentFilterType = filterType;
      applyFilters();
    }

    function applyFilters() {
      const query = (document.getElementById('searchInput').value || '').toLowerCase();
      const selectedCategory = document.getElementById('categorySelectFilter').value;
      const rows = document.querySelectorAll('.product-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const category = row.getAttribute('data-cat') || '';
        const status = row.getAttribute('data-status') || '';
        const skuText = (row.querySelector('.sku-text') ? row.querySelector('.sku-text').innerText : '').toLowerCase();
        const nameText = (row.querySelector('.name-text') ? row.querySelector('.name-text').innerText : '').toLowerCase();
        const formulaText = (row.querySelector('.formula-text') ? row.querySelector('.formula-text').innerText : '').toLowerCase();

        const matchesSearch = !query || skuText.includes(query) || nameText.includes(query) || formulaText.includes(query);
        
        let matchesTab = false;
        if (currentFilterType === 'all') {
          matchesTab = true;
        } else if (currentFilterType === 'pure') {
          matchesTab = (category === 'Pure Gas');
        } else if (currentFilterType === 'mixes') {
          matchesTab = (category !== 'Pure Gas');
        } else if (currentFilterType === 'qc') {
          matchesTab = (status === 'testing');
        }

        let matchesSelect = !selectedCategory || (category === selectedCategory);

        if (matchesSearch && matchesTab && matchesSelect) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      document.getElementById('visibleCount').textContent = visibleCount;
      const emptyStateRow = document.getElementById('emptyStateRow');
      if (emptyStateRow) {
        emptyStateRow.style.display = (visibleCount === 0) ? '' : 'none';
      }
    }

    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('categorySelectFilter').addEventListener('change', applyFilters);

    function exportGasesData() {
      let csv = [];
      let rows = document.querySelectorAll("#gasesTable tr");
      
      rows.forEach(row => {
        if (row.style.display === 'none') return;
        let cols = row.querySelectorAll("td, th");
        let data = [];
        for(let i = 0; i < cols.length - 1; i++) {
          data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
        }
        if (data.length > 0) {
          csv.push(data.join(","));
        }
      });

      let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      let downloadLink = document.createElement("a");
      downloadLink.download = "gases_mixtures_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }

    function confirmDelete() {
      const currentLang = document.documentElement.lang || 'en';
      return confirm(currentLang === 'ar' ? 'هل أنت متأكد أنك تريد حذف تركيبة هذا المنتج؟ سيتم إعادة المواد الخام المخصومة إلى المخزون.' : 'Are you sure you want to delete this product mix? Deducted materials will be restored to raw inventory.');
    }
  </script>
</body>
</html>
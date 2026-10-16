<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.delete');

$active_page = 'dispatch_delivery';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'logistics.delete');
  $manifest_id_to_delete = $_POST['manifest_id'] ?? null;
  if ($manifest_id_to_delete !== null) {
    $stmt_delete = $pdo->prepare("DELETE FROM dispatches WHERE manifest_id = ?");
    $stmt_delete->execute([$manifest_id_to_delete]);
  }
  redirect(app_url('modules/logistics_fleet/dispatch_delivery.php'));
}

$stmt_total = $pdo->query("SELECT COUNT(*) FROM dispatches");
$total_dispatches = (int)$stmt_total->fetchColumn();

$stmt_active = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status IN ('dispatched', 'in_transit')");
$active_routes = (int)$stmt_active->fetchColumn();

$stmt_del_today = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'delivered' AND dispatch_date = CURDATE()");
$delivered_today = (int)$stmt_del_today->fetchColumn();

$stmt_loading = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'dispatched'");
$loading_count = (int)$stmt_loading->fetchColumn();

$stmt_transit = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'in_transit'");
$transit_count = (int)$stmt_transit->fetchColumn();

$stmt_delayed = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'cancelled'"); 
$delayed_count = (int)$stmt_delayed->fetchColumn();

$stmt_rate = $pdo->query("SELECT status, COUNT(*) as cnt FROM dispatches WHERE status IN ('delivered', 'cancelled') GROUP BY status");
$rate_data = $stmt_rate->fetchAll(PDO::FETCH_KEY_PAIR);
$delivered_count = (int)($rate_data['delivered'] ?? 0);
$cancelled_count = (int)($rate_data['cancelled'] ?? 0);
$total_completed = $delivered_count + $cancelled_count;
$on_time_rate = $total_completed > 0 ? round(($delivered_count / $total_completed) * 100, 1) : 100;

$query = "
  SELECT 
    d.manifest_id as id,
    d.order_ref,
    d.destination,
    d.vehicle_id as vehicle,
    d.eta_time as eta,
    d.status,
    dr.full_name as driver,
    p.company_name as client
  FROM dispatches d
  LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
  LEFT JOIN purchase_orders po ON d.order_ref = po.order_number
  LEFT JOIN partners p ON po.client_id = p.id
  ORDER BY d.created_at DESC
";
$stmt_dispatches = $pdo->query($query);
$dispatches = $stmt_dispatches->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'dispatched' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Loading & Staging', 'label_ar' => 'جاري التحميل', 'tab' => 'loading'],
  'in_transit' => ['badge' => 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400', 'dot' => 'bg-cyan-500', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق', 'tab' => 'in_transit'],
  'delivered' => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Delivered / Signed', 'label_ar' => 'تم التوصيل', 'tab' => 'delivered'],
  'cancelled' => ['badge' => 'border border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400', 'dot' => 'bg-red-500', 'label_en' => 'Delayed / Hold', 'label_ar' => 'متأخر / معلق', 'tab' => 'delayed'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Scheduled Gas Delivery Trips | I-GAS Control Room" data-ar="رحلات وإرساليات توزيع الغاز | غرفة التحكم I-GAS">Scheduled Gas Delivery Trips | I-GAS Control Room</title>
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
    .tab-btn { position: relative; padding-bottom: 10px; font-size: 13px; font-weight: 500; color: #64748b; transition: all 0.15s ease; border-bottom: 2px solid transparent; }
    .tab-btn:hover { color: #0f172a; }
    .dark .tab-btn:hover { color: #f8fafc; }
    .tab-btn.active { color: #0b6e8a; border-bottom-color: #0b6e8a; font-weight: 600; }
    .dark .tab-btn.active { color: #19b5c9; border-bottom-color: #19b5c9; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Control Room Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
        <span data-en="LIVE FLEET DISPATCH CONTROL & MANIFESTS" data-ar="مراقبة رحلات التوزيع ومنافيست الشحن الحية">LIVE FLEET DISPATCH CONTROL & MANIFESTS</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ACTIVE DEPLOYMENTS:" data-ar="الرحلات النشطة:">ACTIVE DEPLOYMENTS:</span> <strong class="text-cyan-700 dark:text-cyan-400"><?= $active_routes ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ON-TIME RATE:" data-ar="الالتزام بالمواعيد:">ON-TIME RATE:</span> <strong class="text-emerald-700 dark:text-emerald-400"><?= $on_time_rate ?>%</strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="radio" class="w-3.5 h-3.5 text-cyan-600 dark:text-cyan-400"></i>
      <span data-en="TELEMETRY SATELLITE LINK ACTIVE" data-ar="تتبع الأقمار الصناعية متصل">TELEMETRY SATELLITE LINK ACTIVE</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-teal-500/30 bg-teal-500/10 text-teal-400 dark:text-teal-400 rounded-xs">
            LOGISTICS // DISPATCH & DELIVERY
          </span>
          <span class="text-xs text-slate-400 font-mono">SYS-DSP-01</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Scheduled Gas Delivery Trips" data-ar="رحلات وإرساليات توزيع الغاز">
          Scheduled Gas Delivery Trips
        </h1>
        <p class="text-xs md:text-sm text-slate-400 mt-1 max-w-2xl" data-en="Real-time monitoring of route dispatches, customer tank bulk refills, cylinder drop-offs, and driver waybills." data-ar="مراقبة حية لمسارات التوزيع، تعبئة خزانات العملاء بالغاز المسال، تسليم أسطوانات الغاز، وإيصالات الشحن.">
          Real-time monitoring of route dispatches, customer tank bulk refills, cylinder drop-offs, and driver waybills.
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button id="exportBtn" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
          <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
          <span data-en="Export Manifests" data-ar="تصدير الإرساليات">Export Manifests</span>
        </button>
        <?php if (has_permission($pdo, 'logistics.create')): ?>
        <a href="new_dispatch.php" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span data-en="Schedule Dispatch" data-ar="جدولة إرسالية جديدة">Schedule Dispatch</span>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Metric KPI Bento Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Active Routes -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-cyan-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Active Route Runs" data-ar="المسارات النشطة">Active Route Runs</span>
          <i data-lucide="navigation" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400"><?= $active_routes ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-ping"></span>
          <span data-en="Live in-transit & staging" data-ar="شاحنات في الطريق وقيد التحميل">Live in-transit & staging</span>
        </div>
      </div>

      <!-- Delivered Today -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-emerald-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Delivered Today" data-ar="تم التسليم اليوم">Delivered Today</span>
          <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400"><?= $delivered_today ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Signed delivery notes" data-ar="إشعارات تسليم موقعة">
          Signed delivery notes
        </div>
      </div>

      <!-- Total Manifests -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="Total Dispatches" data-ar="إجمالي الإرساليات">Total Dispatches</span>
          <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= number_format($total_dispatches) ?></div>
        <div class="text-[11px] text-slate-400 mt-2 font-mono" data-en="Generated trip manifests" data-ar="منافيست شحن مسجلة">
          Generated trip manifests
        </div>
      </div>

      <!-- On-Time Delivery Rate -->
      <div class="p-4 bg-[#0c1924] border border-[#1a3346] rounded-xs border-s-4 border-s-[#f97316] dark:border-s-amber-400">
        <div class="flex items-center justify-between text-slate-400 text-xs font-mono uppercase mb-2">
          <span data-en="On-Time Rate" data-ar="الالتزام بالجدول">On-Time Rate</span>
          <i data-lucide="clock" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
        </div>
        <div class="text-2xl font-bold font-mono text-white"><?= $on_time_rate ?>%</div>
        <div class="mt-2 w-full bg-[#09151f] h-1.5 rounded-full overflow-hidden">
          <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $on_time_rate ?>%"></div>
        </div>
      </div>
    </div>

    <!-- Filter & Search Toolbar + Data Table Card -->
    <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      
      <!-- Toolbar -->
      <div class="p-4 border-b border-[#1a3346] flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Status Tabs -->
        <div class="flex items-center gap-6 overflow-x-auto pb-1 md:pb-0" id="filterTabs">
          <button class="tab-btn active cursor-pointer" data-tab="active_run">
            <span data-en="Active Runs" data-ar="قيد التشغيل">Active Runs</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-cyan-500/10 text-cyan-700 dark:text-cyan-400" id="countActive"><?= $active_routes ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="loading">
            <span data-en="Loading" data-ar="جاري التحميل">Loading</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-amber-500/10 text-amber-400 dark:text-amber-400" id="countLoading"><?= $loading_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="in_transit">
            <span data-en="In Transit" data-ar="في الطريق">In Transit</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-cyan-500/10 text-cyan-700 dark:text-cyan-400" id="countTransit"><?= $transit_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="delivered">
            <span data-en="Delivered" data-ar="تم التوصيل">Delivered</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"><?= $delivered_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="delayed">
            <span data-en="Delayed" data-ar="متأخر">Delayed</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-red-500/10 text-red-700 dark:text-red-400" id="countDelayed"><?= $delayed_count ?></span>
          </button>
          <button class="tab-btn cursor-pointer" data-tab="all">
            <span data-en="All" data-ar="الكل">All</span>
            <span class="ml-1.5 px-1.5 py-0.5 rounded-xs text-[10px] font-mono bg-[#09151f] text-slate-400"><?= count($dispatches) ?></span>
          </button>
        </div>

        <!-- Search and Status Filter -->
        <div class="flex items-center gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search manifest, order, client..." data-en-placeholder="Search manifest, order, client..." data-ar-placeholder="بحث عن إرسالية، طلب، عميل..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="statusFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="all" data-en="All Statuses" data-ar="جميع الحالات">All Statuses</option>
            <option value="dispatched" data-en="Loading / Staging" data-ar="جاري التحميل">Loading / Staging</option>
            <option value="in_transit" data-en="In Transit" data-ar="في الطريق">In Transit</option>
            <option value="delivered" data-en="Delivered" data-ar="تم التوصيل">Delivered</option>
            <option value="cancelled" data-en="Delayed / Hold" data-ar="متأخر / معلق">Delayed / Hold</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left rtl:text-right border-collapse" id="dispatchTable">
          <thead>
            <tr class="bg-[#09151f] border-b border-[#1a3346] text-[11px] font-mono text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4" data-en="Manifest ID" data-ar="معرف البيان">Manifest ID</th>
              <th class="py-3 px-4" data-en="Order Ref" data-ar="مرجع الطلب">Order Ref</th>
              <th class="py-3 px-4" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
              <th class="py-3 px-4" data-en="Route Destination" data-ar="وجهة المسار">Route Destination</th>
              <th class="py-3 px-4" data-en="Assigned Unit & Driver" data-ar="المركبة والسائق">Assigned Unit & Driver</th>
              <th class="py-3 px-4" data-en="ETA Window" data-ar="موعد الوصول">ETA Window</th>
              <th class="py-3 px-4" data-en="Status" data-ar="الحالة">Status</th>
              <th class="py-3 px-4 text-right rtl:text-left no-export" data-en="Actions" data-ar="الإجراءات">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] text-xs font-mono">
            <?php if (empty($dispatches)): ?>
            <tr class="empty-state">
              <td colspan="8" class="py-12 text-center text-slate-400 font-sans" data-en="No delivery dispatches currently registered." data-ar="لا توجد إرساليات توزيع مسجلة حالياً.">
                No delivery dispatches currently registered.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($dispatches as $d): ?>
              <?php
                $raw_status = strtolower($d['status'] ?? '');
                $s = $statusStyles[$raw_status] ?? $statusStyles['dispatched'];
                $formatted_eta = !empty($d['eta']) ? date('H:i', strtotime($d['eta'])) : '--:--';
                $tabCategory = $s['tab']; 
              ?>
              <tr class="hover:bg-[#102332] transition-colors filterable-row" data-status="<?= $raw_status ?>" data-tab="<?= $tabCategory ?>" data-search="<?= strtolower(htmlspecialchars($d['id'] . ' ' . $d['order_ref'] . ' ' . $d['client'] . ' ' . $d['destination'] . ' ' . $d['vehicle'] . ' ' . $d['driver'])) ?>">
                <td class="py-3 px-4">
                  <a href="dispatch_details.php?id=<?= urlencode($d['id']) ?>" class="font-bold text-teal-600 dark:text-teal-400 hover:underline inline-flex items-center gap-1.5">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                    <?= htmlspecialchars($d['id']) ?>
                  </a>
                </td>

                <td class="py-3 px-4 text-slate-300">
                  <?= htmlspecialchars($d['order_ref']) ?>
                </td>

                <td class="py-3 px-4 font-sans font-medium text-white">
                  <?= htmlspecialchars($d['client'] ?? 'Industrial Client') ?>
                </td>

                <td class="py-3 px-4 font-sans text-slate-300">
                  <?= htmlspecialchars($d['destination']) ?>
                </td>

                <td class="py-3 px-4">
                  <div class="flex flex-col">
                    <span class="font-bold text-teal-400 dark:text-teal-400 inline-flex items-center gap-1">
                      <i data-lucide="truck" class="w-3 h-3 text-neutral-400"></i>
                      <?= htmlspecialchars($d['vehicle'] ?? 'Carrier') ?>
                    </span>
                    <span class="text-[11px] font-sans text-slate-400 mt-0.5">
                      <?= htmlspecialchars($d['driver'] ?? 'Unassigned Driver') ?>
                    </span>
                  </div>
                </td>

                <td class="py-3 px-4 text-slate-200 font-bold">
                  <?= $formatted_eta ?>
                </td>

                <td class="py-3 px-4">
                  <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium <?= $s['badge'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                    <span data-en="<?= $s['label_en'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label_en'] ?></span>
                  </span>
                </td>

                <td class="py-3 px-4 text-right rtl:text-left no-export font-sans">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="dispatch_details.php?id=<?= urlencode($d['id']) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 cursor-pointer transition" title="Track Dispatch">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </a>

                    <?php if ($is_admin): ?>
                    <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Are you sure you want to cancel and delete this dispatch manifest? / هل أنت متأكد من حذف هذه الإرسالية؟');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($d['id']) ?>">
                      <button type="submit" class="p-1 rounded-xs border border-red-500/30 bg-red-500/10 text-red-600 hover:bg-red-500/20 cursor-pointer transition" title="Delete Dispatch">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <tr id="noResultsRow" style="display: none;">
                <td colspan="8" class="py-8 text-center text-slate-400 font-sans" data-en="No delivery runs match your filter criteria." data-ar="لا توجد إرساليات تطابق معايير البحث.">
                  No delivery runs match your filter criteria.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination / Counts -->
      <div class="p-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-slate-400">
        <div>
          <span data-en="Showing" data-ar="عرض">Showing</span> <strong id="visibleCount" class="text-white"><?= count($dispatches) ?></strong> <span data-en="of" data-ar="من">of</span> <strong class="text-white"><?= count($dispatches) ?></strong> <span data-en="manifest records" data-ar="إرسالية">manifest records</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-cyan-500"></span>
          <span data-en="Road telemetry synced" data-ar="التتبع متزامن مع الطريق">Road telemetry synced</span>
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

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tabButtons = document.querySelectorAll('#filterTabs .tab-btn');
    const rows = document.querySelectorAll('.filterable-row');
    const visibleCountElem = document.getElementById('visibleCount');
    const noResultsRow = document.getElementById('noResultsRow');
    let currentTab = 'active_run';

    function filterTable() {
      const query = (searchInput?.value || '').toLowerCase().trim();
      const filterVal = statusFilter ? statusFilter.value : 'all';
      let visibleCount = 0;

      rows.forEach(row => {
        const rowTab = row.getAttribute('data-tab');
        const rowStatus = row.getAttribute('data-status');
        const searchData = row.getAttribute('data-search') || '';
        
        const matchesSearch = !query || searchData.includes(query);
        const matchesDropdown = (filterVal === 'all' || rowStatus === filterVal);
        
        let matchesTab = false;
        if (currentTab === 'all') matchesTab = true;
        else if (currentTab === 'active_run') matchesTab = (rowTab === 'loading' || rowTab === 'in_transit');
        else matchesTab = (rowTab === currentTab);

        if (matchesSearch && matchesDropdown && matchesTab) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      if (visibleCountElem) visibleCountElem.innerText = visibleCount;
      if (noResultsRow && rows.length > 0) {
        noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
      }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);

    tabButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        tabButtons.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentTab = this.getAttribute('data-tab');
        filterTable();
      });
    });

    filterTable();

    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function() {
        let csvContent = "\uFEFF"; 
        const table = document.getElementById("dispatchTable");
        const trs = table.querySelectorAll("tr");

        trs.forEach(function(row) {
          if (row.style.display === 'none' && !row.classList.contains('empty-state') && row.id !== 'noResultsRow') return;
          if (row.classList.contains('empty-state') || row.id === 'noResultsRow') return;
          
          let rowData = [];
          const cols = row.querySelectorAll("th, td");
          
          cols.forEach(function(col) {
            if (col.classList.contains('no-export')) return;
            let text = col.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""').trim();
            rowData.push('"' + text + '"');
          });
          
          if (rowData.length > 0) {
            csvContent += rowData.join(",") + "\r\n";
          }
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "Dispatch_Manifests_Export_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }
  });
</script>
</body>
</html>
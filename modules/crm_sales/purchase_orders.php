<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'orders.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_permission($pdo, 'orders.approve');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
  require_csrf();
  $action = $_POST['action'];
  $order_id = (int)($_POST['order_id'] ?? 0);
  
  if ($action === 'delete') {
    require_permission($pdo, 'orders.delete');
    $stmt_del = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
    $stmt_del->execute([$order_id]);
  } elseif ($action === 'approve') {
    require_permission($pdo, 'orders.approve');
    $stmt_upd = $pdo->prepare("UPDATE purchase_orders SET status = 'delivered', production_status = 'ready_for_dispatch' WHERE id = ?");
    $stmt_upd->execute([$order_id]);
  } elseif ($action === 'reject') {
    require_permission($pdo, 'orders.edit');
    $stmt_upd = $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?");
    $stmt_upd->execute([$order_id]);
  }
  
  redirect(app_url('modules/crm_sales/purchase_orders.php'));
}

$active_page = 'purchase_orders';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders'];

$query = "
  SELECT po.*, po.id AS po_id, p.id AS client_partner_id, p.company_name, p.entity_type 
  FROM purchase_orders po 
  LEFT JOIN partners p ON po.client_id = p.id 
";
$params = [];

$isRep = has_role('sales_rep') && !is_admin();
if (!$is_admin && isset($_SESSION['client_id'])) {
  $query .= " WHERE po.client_id = ? ";
  $params[] = $_SESSION['client_id'];
} elseif ($isRep) {
  $uid = get_current_user_id();
  $query .= " WHERE (po.sales_rep_id = ? OR (',' || po.sales_rep_id || ',') LIKE ?) ";
  $params[] = (string)$uid;
  $params[] = "%,{$uid},%";
}

$query .= " ORDER BY po.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

$orders = [];
$total_orders = count($records);
$active_count = 0;
$transit_count = 0;
$delivered_count = 0;
$mtd_volume = 0;

$count_processing = 0;
$count_transit = 0;
$count_delivered = 0;
$count_cancelled = 0;

foreach ($records as $row) {
  $st = strtolower($row['status'] ?? 'draft');
  if ($st === 'processing') {
    $active_count++;
    $count_processing++;
  } elseif ($st === 'in_transit') {
    $transit_count++;
    $active_count++;
    $count_transit++;
  } elseif ($st === 'delivered') {
    $delivered_count++;
    $count_delivered++;
  } elseif ($st === 'cancelled') {
    $count_cancelled++;
  }

  if (in_array($st, ['processing', 'in_transit', 'delivered'])) {
    $mtd_volume += (float)($row['total_value'] ?? 0);
  }

  $words = preg_split('/\s+/', trim((string)($row['company_name'] ?? '')));
  $initials = '';
  if (count($words) >= 2) {
    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
  } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
    $initials = strtoupper(mb_substr($words[0], 0, 2));
  }
  $initials = $initials ?: 'PO';

  $orders[] = [
    'db_id'    => $row['po_id'],
    'id'     => $row['order_number'],
    'client_id'  => $row['client_partner_id'] ?? $row['client_id'],
    'client'   => $row['company_name'] ?? 'Unknown Client',
    'initials'  => $initials,
    'corp'    => (($row['entity_type'] ?? '') === 'Corporate'),
    'specs'    => $row['specs'] ?? 'Standard Gas Mixture',
    'order_date' => $row['order_date'],
    'target_date' => $row['delivery_date'] ?? $row['order_date'],
    'value'    => (float)($row['total_value'] ?? 0),
    'status'   => $st
  ];
}

$fulfillment_rate = $total_orders > 0 ? round(($delivered_count / $total_orders) * 100, 1) : 0;

$statusConfigs = [
  'draft'   => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'processing' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Processing', 'label_ar' => 'قيد المعالجة'],
  'in_transit' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
  'delivered' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Cancelled', 'label_ar' => 'ملغي'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Purchase Orders | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
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
        <span data-en="ORDER FULFILLMENT PIPELINE" data-ar="مسار إنجاز أوامر الشراء">ORDER FULFILLMENT PIPELINE</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= $total_orders ?> <span data-en="ORDERS LOGGED" data-ar="أمر شراء مسجل">ORDERS LOGGED</span></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Header Row -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-xs font-mono text-neutral-500 uppercase tracking-wider" data-en="Sales & Supply Chain Operations" data-ar="المبيعات وسلاسل الإمداد">Sales &amp; Supply Chain Operations</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Purchase Orders & Dispatch Releases" data-ar="أوامر الشراء وإذون الصرف">
            Purchase Orders &amp; Dispatch Releases
          </h1>
          <p class="text-xs text-neutral-500 mt-1" data-en="Track customer purchase orders, production filling schedules, and fleet delivery dispatches." data-ar="تتبع أوامر الشراء وجداول التعبئة في المصنع وإذون التسليم عبر الأسطول.">
            Track customer purchase orders, production filling schedules, and fleet delivery dispatches.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button onclick="exportOrdersData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span data-en="Export CSV" data-ar="تصدير CSV">Export CSV</span>
          </button>

          <?php if (has_permission($pdo, 'orders.create')): ?>
          <!-- Single Primary CTA: Safety Valve Orange -->
          <a href="<?= app_url('modules/crm_sales/new_order.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="Create New Order" data-ar="إنشاء طلب جديد">Create New Order</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento KPI Grid (4 cards with 3px start accents) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Total Orders" data-ar="إجمالي الطلبات">Total Orders</span>
            <i data-lucide="package" class="w-4 h-4 text-teal-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($total_orders) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="POs" data-ar="أمر">POs</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Recognized blanket & spot orders" data-ar="أوامر العقود والطلبات الفورية">Recognized blanket &amp; spot orders</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Processing & Transit" data-ar="قيد المعالجة والنقل">Processing &amp; Transit</span>
            <i data-lucide="truck" class="w-4 h-4 text-amber-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-amber-400 dark:text-amber-400">
            <?= number_format($active_count) ?>
            <span class="text-xs font-sans font-normal text-neutral-500" data-en="Active" data-ar="نشط">Active</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1">
            <?= $count_processing ?> <span data-en="filling" data-ar="تعبئة">filling</span> • <?= $count_transit ?> <span data-en="on road" data-ar="بالطريق">on road</span>
          </p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Delivered Volume" data-ar="قيمة الطلبات المنجزة">Delivered Volume</span>
            <i data-lucide="activity" class="w-4 h-4 text-blue-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-white">
            <?= number_format($mtd_volume) ?>
            <span class="text-xs font-sans font-normal text-neutral-500">SAR</span>
          </div>
          <p class="text-xs text-neutral-500 mt-1" data-en="Recognized supply volume" data-ar="إجمالي حجم التوريدات">Recognized supply volume</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-5 border-s-4 border-s-[#f97316]">
          <div class="flex items-center justify-between text-neutral-500 mb-2">
            <span class="text-xs font-mono uppercase tracking-wider" data-en="Fulfillment Rate" data-ar="معدل الإنجاز">Fulfillment Rate</span>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
          </div>
          <div class="text-2xl font-mono font-bold text-emerald-700 dark:text-emerald-400">
            <?= $fulfillment_rate ?><span class="text-xs font-sans font-normal text-neutral-500">%</span>
          </div>
          <div class="w-full bg-[#09151f] h-1.5 rounded-none mt-2 overflow-hidden border border-[#1e3a50]">
            <div class="bg-emerald-600 h-full" style="width: <?= min(100, $fulfillment_rate) ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Table Card with Filter Tabs -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md overflow-hidden">
        <!-- Filter Tabs & Search Header -->
        <div class="px-6 py-4 border-b border-[#1a3346] flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-2 overflow-x-auto no-scrollbar" id="filterTabs">
            <button onclick="filterByTab('all')" class="tab-btn px-3 py-1.5 text-xs font-mono rounded border border-neutral-900 dark:border-white bg-[#112433] text-slate-200 border-[#234257] text-white dark:bg-[#112433] text-slate-200 border-[#234257] text-white transition-colors" data-tab="all">
              <span data-en="All" data-ar="الكل">All</span> (<?= $total_orders ?>)
            </button>
            <button onclick="filterByTab('processing')" class="tab-btn px-3 py-1.5 text-xs font-mono rounded border border-[#1e3a50] text-slate-300 hover:border-neutral-400 transition-colors" data-tab="processing">
              <span data-en="Processing" data-ar="قيد المعالجة">Processing</span> (<?= $count_processing ?>)
            </button>
            <button onclick="filterByTab('in_transit')" class="tab-btn px-3 py-1.5 text-xs font-mono rounded border border-[#1e3a50] text-slate-300 hover:border-neutral-400 transition-colors" data-tab="in_transit">
              <span data-en="In Transit" data-ar="قيد الشحن">In Transit</span> (<?= $count_transit ?>)
            </button>
            <button onclick="filterByTab('delivered')" class="tab-btn px-3 py-1.5 text-xs font-mono rounded border border-[#1e3a50] text-slate-300 hover:border-neutral-400 transition-colors" data-tab="delivered">
              <span data-en="Delivered" data-ar="تم التوصيل">Delivered</span> (<?= $count_delivered ?>)
            </button>
            <button onclick="filterByTab('cancelled')" class="tab-btn px-3 py-1.5 text-xs font-mono rounded border border-[#1e3a50] text-slate-300 hover:border-neutral-400 transition-colors" data-tab="cancelled">
              <span data-en="Cancelled" data-ar="ملغي">Cancelled</span> (<?= $count_cancelled ?>)
            </button>
          </div>

          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search orders, client..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-start text-xs border-collapse" id="ordersTable">
            <thead>
              <tr class="bg-[#09151f] border-b border-[#1a3346] text-slate-400 font-mono uppercase text-[10px] tracking-wider">
                <th class="py-2.5 px-3 text-start font-semibold" data-en="PO Reference" data-ar="رقم أمر الشراء">PO Reference</th>
                <th class="py-2.5 px-3 text-start font-semibold" data-en="Client Commercial Account" data-ar="حساب العميل">Client Commercial Account</th>
                <th class="py-2.5 px-3 text-start font-semibold" data-en="Gas Specifications" data-ar="مواصفات الغاز">Gas Specifications</th>
                <th class="py-2.5 px-3 text-start font-semibold" data-en="Order Date" data-ar="تاريخ الطلب">Order Date</th>
                <th class="py-2.5 px-3 text-start font-semibold" data-en="Target Delivery" data-ar="التسليم المستهدف">Target Delivery</th>
                <th class="py-2.5 px-3 text-end font-semibold" data-en="Value (SAR)" data-ar="القيمة (ر.س)">Value (SAR)</th>
                <th class="py-2.5 px-3 text-center font-semibold" data-en="Status" data-ar="الحالة">Status</th>
                <th class="py-2.5 px-3 text-end font-semibold" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] font-mono">
              <?php if (empty($orders)): ?>
                <tr>
                  <td colspan="8" class="py-12 text-center text-slate-400 font-sans text-xs" data-en="No purchase orders registered in database." data-ar="لا توجد أوامر شراء مسجلة في قاعدة البيانات.">
                    No purchase orders registered in database.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($orders as $o): ?>
                  <?php $sc = $statusConfigs[$o['status']] ?? $statusConfigs['draft']; ?>
                  <tr class="hover:bg-[#102332] transition-colors order-row" data-status="<?= htmlspecialchars($o['status']) ?>">
                    <td class="py-2.5 px-3 font-bold text-white">
                      <a href="<?= app_url('modules/crm_sales/order_details.php?id=' . urlencode((string)$o['db_id'])) ?>" class="hover:text-cyan-400 text-teal-400">
                        <?= htmlspecialchars($o['id']) ?>
                      </a>
                    </td>
                    <td class="py-2.5 px-3 font-sans">
                      <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono font-bold text-[10px] text-slate-300 shrink-0">
                          <?= htmlspecialchars($o['initials']) ?>
                        </div>
                        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$o['client_id'])) ?>" class="font-medium text-white client-name truncate max-w-xs hover:text-cyan-400">
                          <?= htmlspecialchars($o['client']) ?>
                        </a>
                      </div>
                    </td>
                    <td class="py-2.5 px-3 font-sans text-slate-400">
                      <div class="flex items-center gap-1.5">
                        <span><?= htmlspecialchars($o['specs']) ?></span>
                        <?= gas_chip($o['specs']) ?>
                      </div>
                    </td>
                    <td class="py-2.5 px-3 text-slate-400 font-mono">
                      <?= !empty($o['order_date']) ? htmlspecialchars(date('d M Y', strtotime($o['order_date']))) : '—' ?>
                    </td>
                    <td class="py-2.5 px-3 text-slate-400 font-mono">
                      <?= !empty($o['target_date']) ? htmlspecialchars(date('d M Y', strtotime($o['target_date']))) : '—' ?>
                    </td>
                    <td class="py-2.5 px-3 text-end font-bold text-white font-mono">
                      <?= number_format($o['value'], 2) ?>
                    </td>
                    <td class="py-2.5 px-3 text-center">
                      <span class="px-2 py-0.5 text-[10px] font-mono rounded-full border <?= $sc['border'] ?> <?= $sc['bg'] ?> <?= $sc['text'] ?> inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                        <span data-en="<?= $sc['label'] ?>" data-ar="<?= $sc['label_ar'] ?>"><?= $sc['label'] ?></span>
                      </span>
                    </td>
                    <td class="py-2.5 px-3 text-end">
                      <div class="flex items-center justify-end gap-1.5">
                        <?php if ($is_admin && !in_array($o['status'], ['cancelled', 'delivered'])): ?>
                          <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Approve & Mark Delivered?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$o['db_id']) ?>">
                            <button type="submit" class="btn-table-action hover:!border-emerald-500 hover:!text-emerald-400 whitespace-nowrap" title="Approve & Mark Delivered">
                              <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            </button>
                          </form>
                          <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Cancel this order?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$o['db_id']) ?>">
                            <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Cancel Order">
                              <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                          </form>
                        <?php endif; ?>

                        <a href="<?= app_url('modules/crm_sales/order_details.php?id=' . urlencode((string)$o['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="Order Details">
                          <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>

                        <?php if (!empty($o['client_id'])): ?>
                          <a href="<?= app_url('modules/crm_sales/new_calloff.php?client_id=' . urlencode((string)$o['client_id'])) ?>" class="btn-table-action whitespace-nowrap" title="Issue Call-off Release">
                            <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                          </a>
                        <?php endif; ?>

                        <?php if ($is_admin): ?>
                          <form method="POST" action="" class="m-0 p-0 inline-flex" onsubmit="return confirm('Delete this order permanently?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$o['db_id']) ?>">
                            <button type="submit" class="btn-table-action hover:!border-rose-500 hover:!text-rose-400 whitespace-nowrap" title="Delete">
                              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
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

        <div class="px-6 py-4 border-t border-[#1a3346] flex flex-col sm:flex-row justify-between items-center gap-3 bg-[#09151f]">
          <span class="text-xs font-mono text-neutral-500">Showing <?= count($orders) > 0 ? '1' : '0' ?>–<?= count($orders) ?> of <?= number_format($total_orders) ?> orders</span>
          <span class="text-xs font-mono text-neutral-400">PAGE 1 OF 1</span>
        </div>
      </div>
    </div>
  </main>

  <script>
    lucide.createIcons();

    let currentTab = 'all';

    function filterByTab(tab) {
      currentTab = tab;
      document.querySelectorAll('#filterTabs .tab-btn').forEach(btn => {
        if (btn.getAttribute('data-tab') === tab) {
          btn.classList.remove('border-neutral-200', 'border-[#1e3a50]', 'text-slate-300', 'text-slate-300');
          btn.classList.add('border-[#234257]', 'dark:border-white', 'bg-[#112433]', 'text-white', 'dark:bg-[#112433]', 'text-white');
        } else {
          btn.classList.remove('border-[#234257]', 'dark:border-white', 'bg-[#112433]', 'text-white', 'dark:bg-[#112433]', 'text-white');
          btn.classList.add('border-neutral-200', 'border-[#1e3a50]', 'text-slate-300', 'text-slate-300');
        }
      });
      applyFilters();
    }

    function applyFilters() {
      const query = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
      const rows = document.querySelectorAll('.order-row');
      
      rows.forEach(row => {
        const status = (row.getAttribute('data-status') || '').toLowerCase();
        const text = row.innerText.toLowerCase();
        const matchesTab = (currentTab === 'all') || (status === currentTab);
        const matchesSearch = !query || text.includes(query);

        if (matchesTab && matchesSearch) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    document.getElementById('searchInput')?.addEventListener('input', applyFilters);

    function exportOrdersData() {
      let csv = [];
      let rows = document.querySelectorAll("#ordersTable tr");
      rows.forEach(row => {
        let cols = row.querySelectorAll("td, th");
        let data = [];
        for(let i = 0; i < cols.length - 1; i++) {
          data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
        }
        csv.push(data.join(","));
      });
      let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      let downloadLink = document.createElement("a");
      downloadLink.download = "purchase_orders_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }
  </script>
</body>
</html>
<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'orders.view');

$active_page = 'purchase_orders';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Order Detail'];

// Accept by DB id or by order_number
$order = null;
if (!empty($_GET['id'])) {
  $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
  $stmt->execute([(int)$_GET['id']]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($_GET['order_num'])) {
  $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE order_number = ?");
  $stmt->execute([$_GET['order_num']]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Graceful fallback to most recent order if not specified
if (!$order) {
  $stmt = $pdo->query("SELECT * FROM purchase_orders ORDER BY id DESC LIMIT 1");
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
  redirect(app_url('modules/crm_sales/purchase_orders.php'));
}

// Ownership / IDOR check for sales reps
if (!check_record_ownership($pdo, 'partners', (int)$order['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this order.");
}

// Client
$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt_client->execute([$order['client_id']]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

// Supplier
$supplier = null;
if (!empty($order['supplier_reference'])) {
  $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ?");
  $stmt_supp->execute([$order['supplier_reference']]);
  $supplier = $stmt_supp->fetch(PDO::FETCH_ASSOC);
}

// Order items
$stmt_items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Latest dispatch
$stmt_dispatch = $pdo->prepare("SELECT * FROM dispatches WHERE order_ref = ? ORDER BY id DESC LIMIT 1");
$stmt_dispatch->execute([$order['order_number']]);
$dispatch = $stmt_dispatch->fetch(PDO::FETCH_ASSOC);

// Driver info (if dispatch exists)
$driver = null;
if ($dispatch && !empty($dispatch['driver_id'])) {
  $stmt_drv = $pdo->prepare("SELECT * FROM drivers WHERE driver_id = ?");
  $stmt_drv->execute([$dispatch['driver_id']]);
  $driver = $stmt_drv->fetch(PDO::FETCH_ASSOC);
}

// Vehicle info (if dispatch exists)
$vehicle = null;
if ($dispatch && !empty($dispatch['vehicle_id'])) {
  $stmt_veh = $pdo->prepare("SELECT * FROM vehicles WHERE fleet_id = ?");
  $stmt_veh->execute([$dispatch['vehicle_id']]);
  $vehicle = $stmt_veh->fetch(PDO::FETCH_ASSOC);
}

// Client activity log for this order
$stmt_acts = $pdo->prepare("
  SELECT * FROM client_activities 
  WHERE client_id = ? AND activity_text LIKE ? 
  ORDER BY activity_time DESC LIMIT 5
");
$stmt_acts->execute([$order['client_id'], '%' . $order['order_number'] . '%']);
$activities = $stmt_acts->fetchAll(PDO::FETCH_ASSOC);

// Compute totals
$subtotal  = array_sum(array_column($items, 'line_total'));
$total_val  = (float)($order['total_value'] ?? 0);
$vat_amount = $total_val - ($total_val / 1.15);
$subtotal_ex = $total_val / 1.15;

$statusConfigs = [
  'draft'   => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'processing' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Processing', 'label_ar' => 'قيد المعالجة'],
  'in_transit' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
  'delivered' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Cancelled', 'label_ar' => 'ملغي'],
];

$dispatchConfigs = [
  'dispatched' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'label' => 'Dispatched', 'label_ar' => 'تم الإرسال'],
  'in_transit' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
  'delivered' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'label' => 'Cancelled', 'label_ar' => 'ملغي'],
];

$priorityBadge = [
  'standard' => 'bg-[#09151f] text-slate-300 border-[#1e3a50]',
  'urgent'  => 'bg-amber-950/30 dark:bg-amber-950/30 text-amber-400 dark:text-amber-400 border-amber-300 dark:border-amber-800',
  'critical' => 'bg-rose-950/30 dark:bg-rose-950/30 text-rose-400 dark:text-rose-400 border-rose-800 dark:border-rose-800',
];

$st = $statusConfigs[$order['status']] ?? $statusConfigs['draft'];
$dsp = $dispatch ? ($dispatchConfigs[$dispatch['status']] ?? $dispatchConfigs['dispatched']) : null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= htmlspecialchars($order['order_number']) ?> | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #FFFFFF !important; color: #000000 !important; }
      .print-border { border: 1px solid #CCCCCC !important; }
    }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

  <?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400 no-print">
      <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
          <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
          <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="ORDER OPERATIONS" data-ar="عمليات الطلبات">ORDER OPERATIONS</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($order['order_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH INDUSTRIAL</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Breadcrumb & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#1a3346] no-print">
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-400 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-teal-500 dark:hover:border-teal-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Orders" data-ar="العودة للطلبات">Back to Orders</span>
          </a>
          <span class="text-neutral-400 dark:text-neutral-600">/</span>
          <span class="text-xs font-mono text-slate-400 uppercase tracking-wider"><?= htmlspecialchars($order['order_number']) ?></span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-teal-500 dark:hover:border-teal-400 transition-colors">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            <span data-en="Print Invoice" data-ar="طباعة الفاتورة">Print Invoice</span>
          </button>
          <?php if (!empty($order['client_id'])): ?>
          <a href="<?= app_url('modules/crm_sales/new_calloff.php?client_id=' . (int)$order['client_id']) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm">
            <i data-lucide="truck" class="w-3.5 h-3.5"></i>
            <span data-en="Issue Call-off Release" data-ar="طلب سحب وتوريد جديد">Issue Call-off Release</span>
          </a>
          <?php endif; ?>
          <?php if (has_permission($pdo, 'orders.delete')): ?>
          <form method="POST" action="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline m-0" onsubmit="return confirm('Delete this order permanently?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-rose-400 dark:text-rose-400 bg-rose-950/30 dark:bg-rose-950/20 border border-rose-800 dark:border-rose-900/50 rounded-sm hover:bg-rose-100 dark:hover:bg-rose-950/40 transition-colors">
              <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
              <span data-en="Delete" data-ar="حذف">Delete</span>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Order Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
          <div class="flex items-center gap-2 mb-2 text-xs font-mono uppercase tracking-wider text-slate-400">
            <span data-en="PURCHASE ORDER RECORD" data-ar="سجل أمر الشراء">PURCHASE ORDER RECORD</span>
            <span>·</span>
            <span><?= htmlspecialchars(date('d M Y', strtotime($order['order_date']))) ?></span>
          </div>
          <div class="flex flex-wrap items-center gap-3 mb-2">
            <h1 class="text-2xl lg:text-3xl font-bold font-mono tracking-tight text-white">
              <?= htmlspecialchars($order['order_number']) ?>
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono uppercase tracking-wider border <?= $st['border'] ?> <?= $st['bg'] ?> <?= $st['text'] ?> whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>"></span>
              <span data-en="<?= $st['label'] ?>" data-ar="<?= $st['label_ar'] ?>"><?= $st['label'] ?></span>
            </span>
          </div>
          <p class="text-xs font-mono text-slate-400">
            <span data-en="PAYMENT TERMS:" data-ar="شروط السداد:">PAYMENT TERMS:</span>
            <span class="font-semibold text-slate-200"><?= htmlspecialchars(strtoupper($order['payment_terms'] ?? 'COD')) ?></span>
            &nbsp;·&nbsp;
            <span data-en="TOTAL VALUATION:" data-ar="إجمالي القيمة:">TOTAL VALUATION:</span>
            <span class="font-bold text-teal-400 tabular-nums whitespace-nowrap"><?= number_format($order['total_value'], 2) ?> SAR</span>
          </p>
        </div>

        <?php if (!empty($client)): ?>
        <div class="flex items-center gap-4 p-4 rounded-sm bg-[#112433]/60 border border-[#1e3a50]/60 w-full md:w-auto">
          <div class="w-10 h-10 rounded-sm bg-[#102230] border border-cyan-800 flex items-center justify-center text-cyan-400 flex-shrink-0">
            <i data-lucide="building" class="w-5 h-5"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[10px] font-mono uppercase tracking-wider text-slate-400" data-en="CLIENT ACCOUNT" data-ar="حساب العميل">CLIENT ACCOUNT</p>
            <a href="client_profile.php?id=<?= (int)$client['id'] ?>" class="text-sm font-bold text-white hover:text-cyan-400 truncate block transition-colors">
              <?= htmlspecialchars($client['company_name'] ?? 'N/A') ?> &rarr;
            </a>
            <p class="text-xs font-mono text-slate-400 truncate"><?= htmlspecialchars($client['reference_id'] ?? '') ?></p>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Fulfillment Lifecycle Progress Tracker -->
      <?php
      $stages = ['draft', 'processing', 'in_transit', 'delivered'];
      $stage_labels = [
        'draft'   => ['en' => 'Draft Created', 'ar' => 'مسودة منشأة'],
        'processing' => ['en' => 'Processing & Filling', 'ar' => 'قيد المعالجة والتعبئة'],
        'in_transit' => ['en' => 'In Transit / Logistics', 'ar' => 'قيد الشحن والتوصيل'],
        'delivered' => ['en' => 'Delivered & Completed', 'ar' => 'تم التوصيل بنجاح'],
      ];
      $current_stage = array_search($order['status'], $stages);
      if ($order['status'] === 'cancelled') $current_stage = -1;
      ?>
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 no-print border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between pb-4 mb-6 border-b border-[#1a3346]">
          <div class="flex items-center gap-2">
            <i data-lucide="activity" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="FULFILLMENT LIFECYCLE" data-ar="مراحل دورة تنفيذ الطلب">FULFILLMENT LIFECYCLE</h3>
          </div>
          <?php if ($order['status'] === 'cancelled'): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono text-rose-400 dark:text-rose-400 bg-rose-950/30 dark:bg-rose-950/20 border border-rose-800 dark:border-rose-900/50">
            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
            <span data-en="ORDER CANCELLED" data-ar="تم إلغاء الطلب">ORDER CANCELLED</span>
          </span>
          <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <?php
          $stage_icons = ['file-text', 'cog', 'truck', 'check-circle-2'];
          foreach ($stages as $i => $stg):
            $is_done  = ($current_stage !== false && $current_stage !== -1 && $i < $current_stage);
            $is_current = ($current_stage !== false && $i === $current_stage);
          ?>
          <div class="p-4 rounded-sm border <?= $is_current ? 'border-teal-500 bg-teal-950/30/30 dark:bg-teal-950/20' : ($is_done ? 'border-[#1e3a50] bg-[#09151f] bg-[#112433]/40' : 'border-[#1a3346] bg-[#0c1924] opacity-60') ?>">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-8 h-8 rounded-sm flex items-center justify-center font-mono text-xs font-bold <?= $is_done ? 'bg-[#112433] text-teal-400 border border-teal-800/60' : ($is_current ? 'bg-[#f97316] text-white' : 'bg-[#09151f] text-slate-500 border border-[#1a3346]') ?>">
                <i data-lucide="<?= $stage_icons[$i] ?>" class="w-4 h-4"></i>
              </div>
              <div>
                <span class="text-[10px] font-mono uppercase text-neutral-400 block">STAGE 0<?= $i + 1 ?></span>
                <span class="text-xs font-semibold <?= $is_current ? 'text-teal-400 dark:text-teal-400' : 'text-slate-200' ?>" data-en="<?= $stage_labels[$stg]['en'] ?>" data-ar="<?= $stage_labels[$stg]['ar'] ?>">
                  <?= $stage_labels[$stg]['en'] ?>
                </span>
              </div>
            </div>
            <div class="text-[11px] font-mono <?= $is_done ? 'text-teal-400 dark:text-teal-400' : ($is_current ? 'text-teal-600 font-bold' : 'text-neutral-400') ?>">
              <?= $is_done ? '✓ COMPLETED' : ($is_current ? '▶ IN PROGRESS' : '— PENDING') ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 3-Column Info Cards -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Client Details -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i data-lucide="user" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="CLIENT DETAILS" data-ar="بيانات العميل">CLIENT DETAILS</h3>
            </div>
            <?php if (!empty($client)): ?>
            <a href="client_profile.php?id=<?= (int)$client['id'] ?>" class="text-xs font-mono text-teal-600 dark:text-teal-400 hover:underline">
              <span data-en="PROFILE" data-ar="الملف">PROFILE</span> &rarr;
            </a>
            <?php endif; ?>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="COMPANY NAME" data-ar="اسم المنشأة">COMPANY NAME</span>
              <span class="text-sm font-bold text-white font-sans block"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></span>
              <span class="text-neutral-500 text-[11px]"><?= htmlspecialchars($client['reference_id'] ?? '') ?></span>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="CONTACT" data-ar="المسؤول">CONTACT</span>
                <span class="text-slate-200 font-sans font-medium"><?= htmlspecialchars(trim(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? ''))) ?: 'N/A' ?></span>
                <span class="text-neutral-500 text-[11px] block"><?= htmlspecialchars($client['job_title'] ?? '') ?></span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PHONE" data-ar="الهاتف">PHONE</span>
                <span class="text-slate-200 tabular-nums"><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="SEGMENT" data-ar="القطاع">SEGMENT</span>
                <span class="text-slate-200 capitalize font-sans"><?= htmlspecialchars($client['segment'] ?? 'General') ?></span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="ENTITY TYPE" data-ar="الكيان">ENTITY TYPE</span>
                <span class="text-slate-200 capitalize font-sans"><?= htmlspecialchars($client['entity_type'] ?? 'Corporate') ?></span>
              </div>
            </div>
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="BILLING ADDRESS" data-ar="عنوان الفوترة">BILLING ADDRESS</span>
              <span class="text-slate-200 font-sans block">
                <?= htmlspecialchars(implode(', ', array_filter([
                  $client['address'] ?? '',
                  $client['city']  ?? '',
                  $client['country'] ?? '',
                ]))) ?: '—' ?>
              </span>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-[#1a3346]">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="CREDIT LIMIT" data-ar="الحد الائتماني">CREDIT LIMIT</span>
                <span class="font-bold tabular-nums text-white"><?= number_format($client['credit_limit'] ?? 0, 2) ?> SAR</span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="BALANCE DUE" data-ar="الرصيد المستحق">BALANCE DUE</span>
                <span class="font-bold tabular-nums <?= ($client['balance_due'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>">
                  <?= number_format($client['balance_due'] ?? 0, 2) ?> SAR
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Fulfillment Source -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
            <i data-lucide="building-2" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="FULFILLMENT SOURCE" data-ar="مصدر التوريد">FULFILLMENT SOURCE</h3>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <?php if ($supplier): ?>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="SUPPLIER NAME" data-ar="اسم المورد">SUPPLIER NAME</span>
                <span class="text-sm font-bold text-white font-sans block"><?= htmlspecialchars($supplier['company_name']) ?></span>
                <span class="text-neutral-500 text-[11px]"><?= htmlspecialchars($supplier['reference_id']) ?></span>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-[10px] uppercase text-neutral-400 block" data-en="CR NUMBER" data-ar="السجل التجاري">CR NUMBER</span>
                  <span class="text-slate-200 tabular-nums"><?= htmlspecialchars($supplier['cr_number'] ?? 'N/A') ?></span>
                </div>
                <div>
                  <span class="text-[10px] uppercase text-neutral-400 block" data-en="TAX ID" data-ar="الرقم الضريبي">TAX ID</span>
                  <span class="text-slate-200 tabular-nums"><?= htmlspecialchars($supplier['tax_id'] ?? 'N/A') ?></span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-[10px] uppercase text-neutral-400 block" data-en="PHONE" data-ar="الهاتف">PHONE</span>
                  <span class="text-slate-200 tabular-nums"><?= htmlspecialchars($supplier['phone'] ?? 'N/A') ?></span>
                </div>
                <div>
                  <span class="text-[10px] uppercase text-neutral-400 block" data-en="TERMS" data-ar="الشروط">TERMS</span>
                  <span class="text-slate-200 uppercase"><?= htmlspecialchars($supplier['payment_terms'] ?? 'COD') ?></span>
                </div>
              </div>
            <?php else: ?>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PRODUCTION FACILITY" data-ar="المنشأة الإنتاجية">PRODUCTION FACILITY</span>
                <span class="text-sm font-bold text-white font-sans block" data-en="I-GAS Main Gas Plant (Jeddah)" data-ar="مصنع آي غاز الرئيسي (جدة)">I-GAS Main Gas Plant (Jeddah)</span>
                <span class="text-neutral-500 text-[11px] block mt-1" data-en="Fulfilled directly from primary cryogenic / compressed storage" data-ar="يتم التوريد مباشرة من صهاريج الغاز المبردة والمضغوطة">Fulfilled directly from primary cryogenic / compressed storage</span>
              </div>
              <div class="pt-3 border-t border-[#1a3346]">
                <span class="text-[10px] uppercase text-slate-400 block mb-1" data-en="TECHNICAL SPECIFICATIONS" data-ar="المواصفات الفنية">TECHNICAL SPECIFICATIONS</span>
                <div class="p-3 rounded-sm bg-[#112433]/50 border border-[#1e3a50]/50 text-slate-300 leading-relaxed font-mono text-[11px]">
                  <?= htmlspecialchars($order['specs'] ?: 'Standard Industrial Quality Specifications - Grade 5.0') ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Logistics & Delivery -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
            <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="LOGISTICS & DELIVERY" data-ar="اللوجستيات والتسليم">LOGISTICS & DELIVERY</h3>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="DELIVERY ADDRESS" data-ar="عنوان التوصيل">DELIVERY ADDRESS</span>
              <span class="text-slate-200 font-sans block leading-relaxed">
                <?= !empty($order['delivery_address']) ? nl2br(htmlspecialchars($order['delivery_address'])) : '<span class="text-neutral-400 italic">No delivery address specified</span>' ?>
              </span>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="TARGET DATE" data-ar="تاريخ التسليم">TARGET DATE</span>
                <span class="text-slate-200 tabular-nums">
                  <?= !empty($order['delivery_date']) ? htmlspecialchars(date('d M Y', strtotime($order['delivery_date']))) : '—' ?>
                </span>
                <span class="text-neutral-400 text-[10px] block tabular-nums">
                  <?= !empty($order['delivery_time']) ? htmlspecialchars(date('h:i A', strtotime($order['delivery_time']))) : 'Time unassigned' ?>
                </span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PRIORITY" data-ar="الأولوية">PRIORITY</span>
                <?php $pr = strtolower($order['delivery_priority'] ?? 'standard'); ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[10px] font-mono uppercase border <?= $priorityBadge[$pr] ?? $priorityBadge['standard'] ?>">
                  <?= htmlspecialchars($pr) ?>
                </span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-[#1a3346]">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PAYMENT TERMS" data-ar="شروط الدفع">PAYMENT TERMS</span>
                <span class="text-white font-bold uppercase"><?= htmlspecialchars($order['payment_terms'] ?? 'COD') ?></span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PRODUCTION STATUS" data-ar="حالة الإنتاج">PRODUCTION STATUS</span>
                <span class="text-slate-200 capitalize font-sans"><?= htmlspecialchars(str_replace('_', ' ', $order['production_status'] ?? 'Scheduled')) ?></span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Active Dispatch Card -->
      <?php if ($dispatch): ?>
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
        <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i data-lucide="navigation" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="ACTIVE DISPATCH MANIFEST" data-ar="بيان الإرسالية النشطة">ACTIVE DISPATCH MANIFEST</h3>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-mono text-xs text-slate-400 whitespace-nowrap"><?= htmlspecialchars($dispatch['manifest_id']) ?></span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-mono uppercase border <?= $dsp['border'] ?> <?= $dsp['bg'] ?> <?= $dsp['text'] ?> whitespace-nowrap">
              <span data-en="<?= $dsp['label'] ?>" data-ar="<?= $dsp['label_ar'] ?>"><?= $dsp['label'] ?></span>
            </span>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs font-mono">
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="DESTINATION" data-ar="الوجهة">DESTINATION</span>
            <span class="text-slate-200 font-sans font-medium"><?= htmlspecialchars($dispatch['destination']) ?></span>
          </div>
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="DISPATCH DATE" data-ar="تاريخ الإرسال">DISPATCH DATE</span>
            <span class="text-slate-200 tabular-nums whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($dispatch['dispatch_date']))) ?></span>
          </div>
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="ESTIMATED ARRIVAL" data-ar="الوقت المتوقع للوصول">ESTIMATED ARRIVAL</span>
            <span class="text-slate-200 tabular-nums whitespace-nowrap"><?= htmlspecialchars(date('H:i', strtotime($dispatch['eta_time']))) ?></span>
          </div>
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="ROUTE DISTANCE" data-ar="مسافة المسار">ROUTE DISTANCE</span>
            <span class="text-slate-200 tabular-nums whitespace-nowrap"><?= htmlspecialchars($dispatch['distance'] ?? 0) ?> KM</span>
          </div>

          <!-- Vehicle info -->
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="FLEET VEHICLE" data-ar="المركبة">FLEET VEHICLE</span>
            <?php if ($vehicle): ?>
              <span class="text-white font-bold"><?= htmlspecialchars($vehicle['fleet_id']) ?></span>
              <span class="text-slate-400 text-[11px] block"><?= htmlspecialchars($vehicle['make_model']) ?> · <?= htmlspecialchars($vehicle['plate_number']) ?></span>
            <?php else: ?>
              <span class="text-slate-200"><?= htmlspecialchars($dispatch['vehicle_id']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Driver info -->
          <div>
            <span class="text-[10px] uppercase text-slate-400 block" data-en="ASSIGNED DRIVER" data-ar="السائق">ASSIGNED DRIVER</span>
            <?php if ($driver): ?>
              <span class="text-white font-sans font-bold block"><?= htmlspecialchars($driver['full_name']) ?></span>
              <span class="text-slate-400 tabular-nums"><?= htmlspecialchars($driver['mobile_number']) ?></span>
            <?php else: ?>
              <span class="text-slate-200"><?= htmlspecialchars($dispatch['driver_id']) ?></span>
            <?php endif; ?>
          </div>

          <div class="col-span-1 sm:col-span-2">
            <span class="text-[10px] uppercase text-slate-400 block" data-en="INSTRUCTIONS" data-ar="التعليمات">INSTRUCTIONS</span>
            <span class="text-slate-300 font-sans">
              <?= !empty($dispatch['instructions']) ? nl2br(htmlspecialchars($dispatch['instructions'])) : '<span class="text-slate-400 italic">No special instructions recorded.</span>' ?>
            </span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Order Items Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="ORDER ITEMS & QUANTITIES" data-ar="أصناف وكميات الطلب">ORDER ITEMS & QUANTITIES</h3>
          </div>
          <span class="text-xs font-mono text-slate-400"><?= count($items) ?> <span data-en="LINE ITEMS" data-ar="صنف">LINE ITEMS</span></span>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold w-12 whitespace-nowrap">#</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Item Code" data-ar="رمز الصنف">Item Code</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Description" data-ar="الوصف">Description</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Qty" data-ar="الكمية">Qty</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Unit Price" data-ar="سعر الوحدة">Unit Price</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Disc %" data-ar="الخصم %">Disc %</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Line Total (SAR)" data-ar="الإجمالي (ريال)">Line Total (SAR)</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="7" class="px-6 py-12 text-center text-xs font-mono text-slate-400" data-en="No specific line items recorded. Valuation is calculated from global order specifications." data-ar="لم يتم تسجيل بنود تفصيلية، يتم احتساب القيمة من مواصفات الطلب الإجمالية.">
                    No specific line items recorded. Valuation is calculated from global order specifications.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($items as $idx => $item): ?>
                <tr class="hover:bg-[#102332]/50 transition-colors">
                  <td class="px-6 py-4 font-mono text-slate-400 whitespace-nowrap"><?= $idx + 1 ?></td>
                  <td class="px-6 py-4 font-mono font-semibold text-slate-300 whitespace-nowrap"><?= htmlspecialchars($item['item_code'] ?? '—') ?></td>
                  <td class="px-6 py-4 font-sans font-medium text-white">
                    <?= htmlspecialchars($item['item_name']) ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-medium text-white tabular-nums whitespace-nowrap">
                    <?= number_format($item['qty'], 2) ?>
                    <span class="text-[10px] text-slate-400"><?= htmlspecialchars($item['unit']) ?></span>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono text-slate-300 tabular-nums whitespace-nowrap"><?= number_format($item['unit_price'], 2) ?></td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono text-slate-400 tabular-nums whitespace-nowrap">
                    <?= $item['discount_pct'] > 0 ? number_format($item['discount_pct'], 1) . '%' : '—' ?>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums whitespace-nowrap"><?= number_format($item['line_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="px-6 py-6 border-t border-[#1a3346] bg-[#09151f] flex justify-end">
          <div class="w-full sm:w-80 space-y-3 font-mono text-xs">
            <div class="flex justify-between items-center">
              <span class="text-slate-300 font-medium" data-en="Subtotal (ex. VAT)" data-ar="المجموع الفرعي (غير شامل الضريبة)">Subtotal (ex. VAT)</span>
              <span class="font-semibold text-white tabular-nums whitespace-nowrap"><?= number_format($subtotal_ex, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-300 font-medium" data-en="VAT (15%)" data-ar="ضريبة القيمة المضافة (15%)">VAT (15%)</span>
              <span class="font-semibold text-white tabular-nums whitespace-nowrap"><?= number_format($vat_amount, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-[#1a3346]">
              <span class="text-sm font-bold text-white uppercase tracking-wider" data-en="Total Amount" data-ar="المبلغ الإجمالي">Total Amount</span>
              <span class="text-xl font-bold text-cyan-400 tabular-nums whitespace-nowrap"><?= number_format($order['total_value'], 2) ?> <span class="text-xs font-normal text-slate-400">SAR</span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Log -->
      <?php if (!empty($activities)): ?>
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 no-print border-s-4 border-s-[#f97316]">
        <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
          <i data-lucide="clock" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="ORDER ACTIVITY AUDIT" data-ar="سجل تدقيق نشاط الطلب">ORDER ACTIVITY AUDIT</h3>
        </div>
        <div class="space-y-4 font-mono text-xs">
          <?php foreach ($activities as $act): ?>
          <div class="flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 bg-teal-500"></div>
            <div>
              <p class="text-slate-200 font-sans"><?= htmlspecialchars($act['activity_text']) ?></p>
              <p class="text-[11px] text-neutral-400 mt-0.5 tabular-nums">
                <?= htmlspecialchars(date('d M Y · H:i', strtotime($act['activity_time']))) ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($act['author']) ?>
              </p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        el.innerText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    }

    document.addEventListener('DOMContentLoaded', () => {
      applyLanguage(localStorage.getItem('app_lang') || 'en');
    });
  </script>
</body>
</html>
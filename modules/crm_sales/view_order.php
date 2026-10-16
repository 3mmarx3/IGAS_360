<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'orders.view');

$active_page = 'purchase_orders';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Order Manifest'];

$order_num = $_GET['order_num'] ?? $_GET['id'] ?? '';

if (empty($order_num)) {
  $firstOrder = $pdo->query("SELECT order_number FROM purchase_orders ORDER BY id DESC LIMIT 1")->fetchColumn();
  if ($firstOrder) {
    $order_num = $firstOrder;
  } else {
    redirect(app_url('modules/crm_sales/purchase_orders.php'));
  }
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE (order_number = ? OR CAST(id AS CHAR) = ?)");
$stmt->execute([$order_num, $order_num]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  $order = $pdo->query("SELECT * FROM purchase_orders ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
  redirect(app_url('modules/crm_sales/purchase_orders.php'));
}

// Ownership / IDOR check for sales reps
if (!check_record_ownership($pdo, 'partners', (int)$order['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this order.");
}

$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt_client->execute([$order['client_id']]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

$reps = [];
if (!empty($order['sales_rep_id'])) {
  $stmt_reps = $pdo->prepare("SELECT full_name, emp_id FROM users WHERE FIND_IN_SET(id, ?)");
  $stmt_reps->execute([$order['sales_rep_id']]);
  $reps = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);
}

$stmt_items = $pdo->prepare("
  SELECT poi.*, COALESCE(poi.item_code, rm.material_sku, 'N/A') AS resolved_item_code 
  FROM purchase_order_items poi 
  LEFT JOIN raw_materials rm ON poi.item_name COLLATE utf8mb4_unicode_ci = rm.material_name COLLATE utf8mb4_unicode_ci 
  WHERE poi.purchase_order_id = ?
");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$custom_mix_msg = '';
if (!empty($order['specs']) && strpos($order['specs'], 'Custom Mix:') !== false) {
  $parts = explode('Custom Mix:', $order['specs']);
  if (isset($parts[1])) {
    $custom_mix_msg = trim($parts[1]);
  }
}

$statusConfigs = [
  'draft'   => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'processing' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Processing', 'label_ar' => 'قيد المعالجة'],
  'in_transit' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'In Transit', 'label_ar' => 'قيد الشحن'],
  'delivered' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Cancelled', 'label_ar' => 'ملغي'],
];

$st = $statusConfigs[$order['status']] ?? $statusConfigs['draft'];
$total_val = (float)($order['total_value'] ?? 0);
$subtotal_ex = $total_val / 1.15;
$vat_amount = $total_val - $subtotal_ex;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= htmlspecialchars($order['order_number']) ?> Manifest | I-GAS Enterprise</title>
  
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
        <span data-en="DELIVERY MANIFEST SHEET" data-ar="بيان تسليم الطلب">DELIVERY MANIFEST SHEET</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= htmlspecialchars($order['order_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <!-- Back Link & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#1a3346] no-print">
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-400 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-teal-500 dark:hover:border-teal-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Orders" data-ar="العودة للطلبات">Back to Orders</span>
          </a>
          <span class="text-neutral-400 dark:text-neutral-600">/</span>
          <span class="text-xs font-mono text-slate-400 uppercase tracking-wider"><?= htmlspecialchars($order['order_number']) ?></span>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/order_details.php?id=' . (int)$order['id']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-neutral-400 transition-colors">
            <i data-lucide="sliders" class="w-3.5 h-3.5"></i>
            <span data-en="Full Details View" data-ar="عرض التفاصيل الكاملة">Full Details View</span>
          </a>
          <button type="button" onclick="window.print()" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm cursor-pointer">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            <span data-en="Print Manifest Document" data-ar="طباعة بيان التسليم">Print Manifest Document</span>
          </button>
        </div>
      </div>

      <!-- Manifest Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316] flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
          <div class="flex items-center gap-2 mb-2 text-xs font-mono uppercase tracking-wider text-neutral-500">
            <span data-en="ORDER MANIFEST DOCUMENT" data-ar="وثيقة بيان الطلب">ORDER MANIFEST DOCUMENT</span>
            <span>·</span>
            <span><?= htmlspecialchars(date('d M Y', strtotime($order['order_date']))) ?></span>
          </div>
          <div class="flex flex-wrap items-center gap-3 mb-2">
            <h1 class="text-2xl lg:text-3xl font-bold font-mono tracking-tight text-white">
              <?= htmlspecialchars($order['order_number']) ?>
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-sm text-xs font-mono uppercase tracking-wider border <?= $st['border'] ?> <?= $st['bg'] ?> <?= $st['text'] ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>"></span>
              <span data-en="<?= $st['label'] ?>" data-ar="<?= $st['label_ar'] ?>"><?= $st['label'] ?></span>
            </span>
          </div>
          <p class="text-xs font-mono text-slate-400">
            <span data-en="CLIENT REF:" data-ar="مرجع العميل:">CLIENT REF:</span>
            <span class="font-semibold text-slate-200"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></span>
            &nbsp;·&nbsp;
            <span data-en="VALUATION:" data-ar="القيمة:">VALUATION:</span>
            <span class="font-bold text-teal-400 dark:text-teal-400 tabular-nums"><?= number_format($order['total_value'], 2) ?> SAR</span>
          </p>
        </div>

        <div class="flex items-center gap-3 p-3 rounded-sm bg-[#09151f] border border-[#1e3a50]/60 font-mono text-xs text-right rtl:text-left">
          <div>
            <span class="text-[10px] text-neutral-400 uppercase block" data-en="PAYMENT TERMS" data-ar="شروط الدفع">PAYMENT TERMS</span>
            <span class="font-bold text-white uppercase"><?= htmlspecialchars($order['payment_terms'] ?? 'COD') ?></span>
          </div>
          <div class="w-px h-8 bg-neutral-300 dark:bg-neutral-700"></div>
          <div>
            <span class="text-[10px] text-neutral-400 uppercase block" data-en="PRIORITY" data-ar="الأولوية">PRIORITY</span>
            <span class="font-bold text-teal-600 dark:text-teal-400 uppercase"><?= htmlspecialchars($order['delivery_priority'] ?? 'Standard') ?></span>
          </div>
        </div>
      </div>

      <!-- 3-Column Info Bento Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Client Account Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
            <i data-lucide="user" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="CLIENT ACCOUNT" data-ar="حساب العميل">CLIENT ACCOUNT</h3>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="COMPANY NAME" data-ar="اسم الشركة">COMPANY NAME</span>
              <span class="text-sm font-bold text-white font-sans block"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></span>
              <span class="text-neutral-500 text-[11px]"><?= htmlspecialchars($client['reference_id'] ?? '') ?></span>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="CONTACT PERSON" data-ar="مسؤول الاتصال">CONTACT PERSON</span>
                <span class="text-slate-200 font-sans font-medium">
                  <?= htmlspecialchars(trim(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? ''))) ?: 'N/A' ?>
                </span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PHONE" data-ar="الهاتف">PHONE</span>
                <span class="text-slate-200 tabular-nums"><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></span>
              </div>
            </div>
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="BILLING ADDRESS" data-ar="عنوان الفوترة">BILLING ADDRESS</span>
              <span class="text-slate-200 font-sans block leading-relaxed">
                <?= htmlspecialchars(implode(', ', array_filter([
                  $client['address'] ?? '',
                  $client['city'] ?? '',
                  $client['country'] ?? ''
                ]))) ?: '—' ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Sales Reps & Specs -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
            <i data-lucide="contact" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="COMMERCIAL REPS & SPECS" data-ar="المناديب والمواصفات">COMMERCIAL REPS & SPECS</h3>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block mb-1" data-en="ASSIGNED REPRESENTATIVE(S)" data-ar="المناديب المعينون">ASSIGNED REPRESENTATIVE(S)</span>
              <?php if (!empty($reps)): ?>
                <div class="space-y-2">
                  <?php foreach($reps as $rep): ?>
                    <div class="p-2 rounded-sm bg-[#09151f] border border-[#1e3a50]/50">
                      <span class="font-bold text-white font-sans block"><?= htmlspecialchars($rep['full_name']) ?></span>
                      <span class="text-[11px] text-neutral-500">ID: <?= htmlspecialchars($rep['emp_id'] ?? 'N/A') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="text-neutral-400 italic" data-en="No representative assigned to this order." data-ar="لم يتم تعيين ممثل مبيعات لهذا الطلب.">No representative assigned to this order.</span>
              <?php endif; ?>
            </div>
            <div class="pt-3 border-t border-[#1a3346]">
              <span class="text-[10px] uppercase text-neutral-400 block mb-1" data-en="ORDER SPECIFICATIONS" data-ar="مواصفات الطلب">ORDER SPECIFICATIONS</span>
              <p class="text-slate-300 font-mono text-[11px] leading-relaxed p-2.5 rounded-sm bg-[#09151f] border border-[#1e3a50]/40">
                <?= htmlspecialchars($order['specs'] ?: 'Standard Gas & Cryogenic Purity Specifications') ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Logistics & Delivery -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-6 border-s-4 border-s-[#f97316]">
          <div class="pb-3 mb-4 border-b border-[#1a3346] flex items-center gap-2">
            <i data-lucide="map-pin" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="LOGISTICS & SITE DELIVERY" data-ar="اللوجستيات وتوصيل الموقع">LOGISTICS & SITE DELIVERY</h3>
          </div>
          <div class="space-y-4 text-xs font-mono">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="DELIVERY SITE / PLANT LOCATION" data-ar="موقع التسليم / المصنع">DELIVERY SITE / PLANT LOCATION</span>
              <span class="text-slate-200 font-sans block leading-relaxed">
                <?= !empty($order['delivery_address']) ? nl2br(htmlspecialchars($order['delivery_address'])) : '<span class="text-neutral-400 italic">No specific delivery coordinates provided.</span>' ?>
              </span>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="TARGET DELIVERY" data-ar="موعد التسليم">TARGET DELIVERY</span>
                <span class="text-white font-bold tabular-nums">
                  <?= !empty($order['delivery_date']) ? htmlspecialchars(date('d M Y', strtotime($order['delivery_date']))) : '—' ?>
                </span>
                <span class="text-neutral-400 text-[10px] block tabular-nums">
                  <?= !empty($order['delivery_time']) ? htmlspecialchars(date('h:i A', strtotime($order['delivery_time']))) : 'Time unassigned' ?>
                </span>
              </div>
              <div>
                <span class="text-[10px] uppercase text-neutral-400 block" data-en="PRODUCTION STATUS" data-ar="حالة الإنتاج">PRODUCTION STATUS</span>
                <span class="text-slate-200 capitalize font-sans">
                  <?= htmlspecialchars(str_replace('_', ' ', $order['production_status'] ?? 'Scheduled')) ?>
                </span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Items Table Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i data-lucide="package" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="ORDER ITEMS & PRODUCT SPECIFICATIONS" data-ar="عناصر الطلب ومواصفات المنتجات">ORDER ITEMS & PRODUCT SPECIFICATIONS</h3>
          </div>
          <span class="text-xs font-mono text-neutral-500"><?= count($items) ?> <span data-en="ITEMS" data-ar="أصناف">ITEMS</span></span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold" data-en="Item Code" data-ar="رمز الصنف">Item Code</th>
                <th class="px-6 py-3.5 font-semibold" data-en="Description" data-ar="الوصف">Description</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Quantity" data-ar="الكمية">Quantity</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Unit Price (SAR)" data-ar="سعر الوحدة">Unit Price (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left" data-en="Line Total (SAR)" data-ar="إجمالي السطر">Line Total (SAR)</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="5" class="px-6 py-10 text-center font-mono text-neutral-400" data-en="No specific line items recorded for this order. Total value derived from specs." data-ar="لا توجد عناصر محددة مسجلة لهذا الطلب. القيمة الإجمالية مستمدة من المواصفات.">
                    No specific line items recorded for this order. Total value derived from specs.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr class="hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
                  <td class="px-6 py-4 font-mono font-semibold text-slate-300"><?= htmlspecialchars($item['resolved_item_code']) ?></td>
                  <td class="px-6 py-4 font-sans font-medium text-white"><?= htmlspecialchars($item['item_name']) ?></td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums">
                    <?= htmlspecialchars($item['qty']) ?>
                    <span class="text-[10px] text-neutral-400 font-normal"><?= htmlspecialchars($item['unit']) ?></span>
                  </td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono text-slate-300 tabular-nums"><?= number_format($item['unit_price'], 2) ?></td>
                  <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums"><?= number_format($item['line_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($custom_mix_msg)): ?>
        <div class="px-6 py-4 border-t border-[#1a3346] bg-[#09151f]">
          <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-teal-400 dark:text-teal-400 block mb-1" data-en="CUSTOM MIX BLEND INSTRUCTIONS" data-ar="تعليمات خلطة الغاز المخصصة">CUSTOM MIX BLEND INSTRUCTIONS</span>
          <p class="text-xs font-mono text-slate-200"><?= htmlspecialchars($custom_mix_msg) ?></p>
        </div>
        <?php endif; ?>

        <!-- Totals -->
        <div class="px-6 py-6 border-t border-[#1a3346] bg-[#09151f] bg-[#112433]/40 flex justify-end">
          <div class="w-full sm:w-80 space-y-3 font-mono text-xs">
            <div class="flex justify-between items-center">
              <span class="text-neutral-500" data-en="Subtotal (ex. VAT)" data-ar="المجموع الفرعي">Subtotal (ex. VAT)</span>
              <span class="font-semibold text-white tabular-nums"><?= number_format($subtotal_ex, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-neutral-500" data-en="VAT (15%)" data-ar="ضريبة القيمة المضافة">VAT (15%)</span>
              <span class="font-semibold text-white tabular-nums"><?= number_format($vat_amount, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-[#1e3a50]">
              <span class="text-sm font-bold text-white" data-en="Total Amount" data-ar="المبلغ الإجمالي">Total Amount</span>
              <span class="text-lg font-bold text-teal-400 dark:text-teal-400 tabular-nums"><?= number_format($total_val, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span></span>
            </div>
          </div>
        </div>
      </div>

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
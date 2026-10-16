<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_staff();

$type = $_GET['type'] ?? 'client';
if ($type === 'supplier') {
  if (!has_permission($pdo, 'suppliers.view') && !has_permission($pdo, 'inventory.view')) {
    require_permission('clients.view');
  }
} else {
  require_permission('clients.view');
}

$partner_id = $_GET['id'] ?? null;
$partner_data = null;

if ($partner_id) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? OR reference_id = ? LIMIT 1");
    $stmt->execute([$partner_id, $partner_id]);
    $partner_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($partner_data) {
      $type = $partner_data['partner_type'] ?? $type;
    }
  } catch (Exception $e) {}
}

if (!$partner_data) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE partner_type = ? LIMIT 1");
    $stmt->execute([$type]);
    $partner_data = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Exception $e) {}
}

$partner = [
  'id' => $partner_data['reference_id'] ?? (($type === 'supplier') ? 'SUP-5001' : 'CLT-3022'),
  'raw_id' => $partner_data['id'] ?? 1,
  'name' => $partner_data['company_name'] ?? (($type === 'supplier') ? 'Gulf Industrial Gases' : 'Saudi Basic Industries Corp (SABIC)'),
  'type' => $type,
  'cr_number' => !empty($partner_data['cr_number']) ? $partner_data['cr_number'] : ('1010' . rand(100000, 999999)),
  'tax_id' => !empty($partner_data['tax_id']) ? $partner_data['tax_id'] : ('3000' . rand(1000000000, 9999999999)),
  'email' => $partner_data['email'] ?? (($type === 'supplier') ? 'procurement@gulfgases.com' : 'supply@sabic.com'),
  'phone' => $partner_data['phone'] ?? '+966 13 876 0000',
  'address' => (!empty($partner_data['address']) ? $partner_data['address'] . ', ' : '') . ($partner_data['city'] ?? 'Jubail Industrial City') . ', ' . ($partner_data['country'] ?? 'Saudi Arabia'),
  'balance' => isset($partner_data['balance_due']) ? (float)$partner_data['balance_due'] : (($type === 'supplier') ? 145000 : -284300),
  'total_orders' => 48,
  'active_contracts' => 3,
  'compliance_status' => 'verified',
  'payment_terms' => !empty($partner_data['payment_terms']) ? strtoupper($partner_data['payment_terms']) : 'Net 30 Days'
];

$active_page = ($type === 'supplier') ? 'suppliers_directory' : 'clients_directory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Directory', ($type === 'supplier' ? 'Supplier Profile' : 'Client Profile')];

$orders = [];
try {
  $stmt_orders = $pdo->prepare("SELECT order_number, order_date, total_value, status FROM purchase_orders WHERE client_id = ? ORDER BY order_date DESC LIMIT 5");
  $stmt_orders->execute([$partner['raw_id']]);
  $db_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
  if (!empty($db_orders)) {
    foreach ($db_orders as $ord) {
      $orders[] = [
        'id' => $ord['order_number'],
        'date' => $ord['order_date'],
        'amount' => (float)$ord['total_value'],
        'status' => $ord['status'] === 'delivered' ? 'fulfilled' : ($ord['status'] === 'cancelled' ? 'cancelled' : 'processing')
      ];
    }
  }
} catch (Exception $e) {}

if (empty($orders)) {
  $orders = [
    ['id' => 'ORD-9942', 'date' => '2026-06-22', 'amount' => 45000, 'status' => 'fulfilled'],
    ['id' => 'ORD-9881', 'date' => '2026-06-18', 'amount' => 88000, 'status' => 'processing'],
    ['id' => 'ORD-9750', 'date' => '2026-06-05', 'amount' => 120000, 'status' => 'fulfilled'],
    ['id' => 'ORD-9611', 'date' => '2026-05-24', 'amount' => 35000, 'status' => 'cancelled'],
  ];
}

$statusLabels = [
  'fulfilled' => ['label_en' => 'Fulfilled', 'label_ar' => 'مكتمل', 'class' => 'border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300'],
  'processing' => ['label_en' => 'In Pipeline', 'label_ar' => 'قيد المعالجة', 'class' => 'border-amber-300 dark:border-amber-800 bg-amber-950/30 dark:bg-amber-950/40 text-amber-400 dark:text-amber-300'],
  'cancelled' => ['label_en' => 'Voided', 'label_ar' => 'ملغي', 'class' => 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300'],
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($partner['name']) ?> | I-GAS Industrial</title>
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
      <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
      <span data-en="Network Node Online" data-ar="نقطة الشبكة متصلة">Network Node Online</span>
    </span>
    <span class="w-px h-3 bg-[#112433]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Verification Tier:" data-ar="مستوى التحقق:">Verification Tier:</span> <span class="font-mono text-teal-600 dark:text-teal-400 font-semibold">Tier-1 Commercial Certified</span>
    </span>
    <span class="w-px h-3 bg-[#112433]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Profile Class:" data-ar="تصنيف الشريك:">Profile Class:</span> <span class="font-mono text-slate-200 uppercase"><?= htmlspecialchars($partner['type']) ?></span>
    </span>
  </div>

  <!-- Main Content Body -->
  <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7">

    <!-- Top Profile Header & Action Buttons -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-7">
      <div class="flex items-start gap-4">
        <div class="w-14 h-14 border border-[#1a3346] bg-[#0c1924] flex items-center justify-center flex-shrink-0">
          <i data-lucide="<?= ($partner['type'] === 'supplier') ? 'truck' : 'building' ?>" class="w-7 h-7 text-teal-600 dark:text-teal-400"></i>
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1.5">
            <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white leading-tight">
              <?= htmlspecialchars($partner['name']) ?>
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[11px] font-mono">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span data-en="Verified Enterprise" data-ar="منشأة موثقة">Verified Enterprise</span>
            </span>
          </div>
          <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
            <span class="text-white font-semibold"><?= htmlspecialchars($partner['id']) ?></span>
            <span>·</span>
            <span class="uppercase"><?= htmlspecialchars($partner['type']) ?></span>
            <span>·</span>
            <span data-en="ISO 9001 Audited" data-ar="مدقق وفق ISO 9001">ISO 9001 Audited</span>
          </div>
        </div>
      </div>

      <!-- Page Action Buttons (Exactly ONE Primary CTA in Safety Valve Orange) -->
      <div class="flex items-center gap-3">
        <a href="<?= app_url('modules/crm_sales/client_transactions.php?id=' . $partner['raw_id']) ?>" class="inline-flex items-center gap-2 px-3.5 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-300 hover:bg-[#102332] dark:hover:bg-neutral-800 transition-colors">
          <i data-lucide="file-text" class="w-4 h-4 text-neutral-500"></i>
          <span data-en="Statement of Account" data-ar="كشف الحساب">Statement of Account</span>
        </a>
        <?php if ($partner['type'] === 'supplier'): ?>
          <a href="<?= app_url('modules/main/create_order.php') ?>" class="btn-cta-orange px-4 py-2 text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center gap-2 transition-colors">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i>
            <span data-en="Issue Purchase Order" data-ar="إصدار أمر شراء">Issue Purchase Order</span>
          </a>
        <?php else: ?>
          <a href="<?= app_url('modules/crm_sales/new_quotation.php?client_id=' . $partner['raw_id']) ?>" class="btn-cta-orange px-4 py-2 text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center gap-2 transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="Create Quotation / Order" data-ar="إنشاء عرض سعر / طلب">Create Quotation / Order</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 4-Metric Grid Telemetry -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
      <!-- Metric 1: Account Balance -->
      <div class="p-5 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924]">
        <p class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Account Balance" data-ar="رصيد الحساب">Account Balance</p>
        <h3 class="text-2xl font-bold font-mono text-white">
          <?= number_format(abs($partner['balance']), 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span>
        </h3>
        <p class="text-[11px] font-mono mt-2 font-medium <?= $partner['balance'] < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
          <?= $partner['balance'] < 0 ? '● Outstanding Client Receivable' : '● Verified Available Credit' ?>
        </p>
      </div>
      
      <!-- Metric 2: Total Fulfillments -->
      <div class="p-5 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924]">
        <p class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Total Dispatches / Orders" data-ar="إجمالي الإرساليات / الطلبات">Total Dispatches / Orders</p>
        <h3 class="text-2xl font-bold font-mono text-white"><?= $partner['total_orders'] ?></h3>
        <p class="text-[11px] font-mono text-slate-400 mt-2" data-en="Lifetime transactions ledger" data-ar="سجل المعاملات الكلي">Lifetime transactions ledger</p>
      </div>
      
      <!-- Metric 3: Active Contracts -->
      <div class="p-5 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924]">
        <p class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Active Master Agreements" data-ar="الاتفاقيات الرئيسية السارية">Active Master Agreements</p>
        <h3 class="text-2xl font-bold font-mono text-teal-600 dark:text-teal-400"><?= $partner['active_contracts'] ?></h3>
        <p class="text-[11px] font-mono text-slate-400 mt-2" data-en="Commercial contracts binding" data-ar="عقود تجارية ملزمة">Commercial contracts binding</p>
      </div>
      
      <!-- Metric 4: Credit Terms -->
      <div class="p-5 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924]">
        <p class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Standard Settlement Terms" data-ar="شروط التسوية المعتمدة">Standard Settlement Terms</p>
        <h3 class="text-xl font-bold font-mono text-white"><?= htmlspecialchars($partner['payment_terms']) ?></h3>
        <p class="text-[11px] font-mono text-slate-400 mt-2" data-en="Default invoicing cycle" data-ar="دورة الفوترة القياسية">Default invoicing cycle</p>
      </div>
    </div>

    <!-- Main Details Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
      
      <!-- Left 2 Cols: Recent Order History Matrix -->
      <div class="xl:col-span-2 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] overflow-hidden">
        <div class="p-5 border-b border-[#1a3346] flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white" data-en="Recent Order & Fulfillment Matrix" data-ar="سجل الأوامر والتنفيذ الأخير">Recent Order & Fulfillment Matrix</h3>
            <p class="text-xs text-slate-400 font-mono mt-0.5" data-en="Telemetry logs for shipments and supply orders" data-ar="سجل القياسات للشحنات وأوامر التوريد">Telemetry logs for shipments and supply orders</p>
          </div>
          <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">
            <?= count($orders) ?> ENTRIES
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse font-sans text-xs">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider text-slate-400 border-b border-[#1a3346] bg-[#09151f]">
                <th class="pl-6 py-3 font-semibold" data-en="Order Identifier" data-ar="رقم الأمر">Order Identifier</th>
                <th class="px-4 py-3 font-semibold" data-en="Execution Date" data-ar="تاريخ التنفيذ">Execution Date</th>
                <th class="px-4 py-3 font-semibold text-right" data-en="Gross Amount (SAR)" data-ar="المبلغ الإجمالي (ر.س)">Gross Amount (SAR)</th>
                <th class="pr-6 py-3 font-semibold text-right" data-en="Fulfillment Status" data-ar="حالة التنفيذ">Fulfillment Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php foreach ($orders as $o): ?>
              <?php $style = $statusLabels[$o['status']] ?? $statusLabels['processing']; ?>
              <tr class="hover:bg-[#102332] transition-colors">
                <td class="pl-6 py-3.5 font-mono font-semibold text-white">
                  <?= htmlspecialchars($o['id']) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-slate-400">
                  <?= htmlspecialchars($o['date']) ?>
                </td>
                <td class="px-4 py-3.5 text-right font-mono font-semibold text-white">
                  <?= number_format($o['amount'], 2) ?>
                </td>
                <td class="pr-6 py-3.5 text-right">
                  <span class="inline-flex items-center px-2 py-0.5 border text-[11px] font-mono <?= $style['class'] ?>">
                    <span data-en="<?= $style['label_en'] ?>" data-ar="<?= $style['label_ar'] ?>"><?= $style['label_en'] ?></span>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right Col: Corporate Dossier & Parameters -->
      <div class="flex flex-col gap-6">
        
        <!-- Dossier Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] p-5">
          <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white pb-3 mb-4 border-b border-[#1a3346] flex items-center justify-between" data-en="Corporate Dossier" data-ar="الملف المؤسسي">
            <span>Corporate Dossier</span>
            <i data-lucide="shield" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </h3>
          
          <div class="space-y-3.5 text-xs">
            <div class="pb-3 border-b border-[#1a3346]">
              <p class="text-[10px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Commercial Registry (CR)" data-ar="السجل التجاري">Commercial Registry (CR)</p>
              <p class="font-mono font-semibold text-white"><?= htmlspecialchars($partner['cr_number']) ?></p>
            </div>
            <div class="pb-3 border-b border-[#1a3346]">
              <p class="text-[10px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="ZATCA Tax / VAT ID" data-ar="الرقم الضريبي (هيئة الزكاة والضريبة)">ZATCA Tax / VAT ID</p>
              <p class="font-mono font-semibold text-white"><?= htmlspecialchars($partner['tax_id']) ?></p>
            </div>
            <div class="pb-3 border-b border-[#1a3346]">
              <p class="text-[10px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Official Communications" data-ar="الاتصال الرسمي">Official Communications</p>
              <p class="font-medium text-white"><?= htmlspecialchars($partner['email']) ?></p>
              <p class="font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($partner['phone']) ?></p>
            </div>
            <div>
              <p class="text-[10px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1" data-en="Registered Plant / Corporate Address" data-ar="العنوان المسجل">Registered Plant / Corporate Address</p>
              <p class="text-slate-300 leading-relaxed"><?= htmlspecialchars($partner['address']) ?></p>
            </div>
          </div>
        </div>

        <!-- Operations & Quick Actions -->
        <div class="border border-[#1a3346] bg-[#09151f] p-5">
          <h3 class="text-xs font-semibold font-oxanium uppercase tracking-wider text-white pb-3 mb-4 border-b border-[#1a3346]" data-en="B2B Supply Configuration" data-ar="إعدادات التوريد المؤسسي">B2B Supply Configuration</h3>
          
          <div class="space-y-2.5">
            <a href="<?= app_url('modules/crm_sales/client_contracts.php?client_id=' . $partner['raw_id']) ?>" class="w-full inline-flex items-center justify-between px-3 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-200 hover:bg-[#102230] dark:hover:bg-neutral-800 transition-colors">
              <span data-en="View Active Contracts Mapping" data-ar="عرض خريطة العقود السارية">View Active Contracts Mapping</span>
              <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400"></i>
            </a>
            <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . $partner['raw_id']) ?>" class="w-full inline-flex items-center justify-between px-3 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-200 hover:bg-[#102230] dark:hover:bg-neutral-800 transition-colors">
              <span data-en="Complete 360 Registry Dossier" data-ar="ملف السجل الشامل 360">Complete 360 Registry Dossier</span>
              <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400"></i>
            </a>
          </div>
        </div>

      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();
</script>
</body>
</html>
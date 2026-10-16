<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'invoices.view');

$active_page = 'collections_invoices';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Collections & Invoices'];

$success_msg = '';
$error_msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'record_payment') {
  require_csrf();
  require_permission($pdo, 'invoices.manage');

  $inv_id = $_POST['invoice_id'] ?? '';
  
  try {
    $pdo->beginTransaction();

    $stmt_inv = $pdo->prepare("SELECT client_id, amount FROM invoices WHERE id = ?");
    $stmt_inv->execute([$inv_id]);
    $inv_data = $stmt_inv->fetch(PDO::FETCH_ASSOC);

    if (!$inv_data) {
      throw new Exception("Invoice not found.");
    }

    $client_id = $inv_data['client_id'];
    $inv_amount = $inv_data['amount'];

    $stmt_upd = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
    $stmt_upd->execute([$inv_id]);

    $stmt_client = $pdo->prepare("UPDATE partners SET balance_due = GREATEST(0, balance_due - ?) WHERE id = ?");
    $stmt_client->execute([$inv_amount, $client_id]);

    $act_text = "Recorded payment for invoice ID {$inv_id} amounting to " . number_format($inv_amount, 2) . " SAR";
    $author_name = $_SESSION['user_name'] ?? 'System';
    $stmt_act = $pdo->prepare("INSERT INTO client_activities (client_id, activity_text, activity_time, author) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
    $stmt_act->execute([$client_id, $act_text, $author_name]);

    $pdo->commit();
    redirect(app_url('modules/crm_sales/collections_invoices.php?success=paid'));
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error_msg = "Payment recording failed.";
  }
}

$isRep = has_role('sales_rep') && !is_admin();
$sqlInvoices = "
  SELECT 
    i.id as db_id,
    i.invoice_number as invoice_id, 
    p.company_name as client_name, 
    p.entity_type, 
    i.po_reference as po_ref, 
    i.issue_date, 
    i.due_date, 
    i.amount, 
    i.status 
  FROM invoices i 
  JOIN partners p ON i.client_id = p.id 
";
$paramsInvoices = [];
if ($isRep) {
  $uid = get_current_user_id();
  $sqlInvoices .= " WHERE (i.sales_rep_id = ? OR p.sales_rep_id = ? OR FIND_IN_SET(?, p.sales_rep_id)) ";
  $paramsInvoices = [$uid, (string)$uid, (string)$uid];
}
$sqlInvoices .= " ORDER BY i.issue_date DESC";

$stmt = $pdo->prepare($sqlInvoices);
$stmt->execute($paramsInvoices);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$invoices = [];
$total_receivables = 0;
$overdue_amount = 0;
$collected_mtd = 0;
$current_month = date('Y-m');

foreach ($records as $row) {
  $words = preg_split('/\s+/', trim($row['client_name']));
  $initials = '';
  if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'), 'UTF-8');
  } elseif (count($words) == 1 && mb_strlen($words[0], 'UTF-8') > 0) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
  }
  
  if ($row['status'] == 'paid') {
    if (strpos($row['issue_date'], $current_month) === 0) {
      $collected_mtd += $row['amount'];
    }
  } elseif ($row['status'] == 'overdue') {
    $overdue_amount += $row['amount'];
    $total_receivables += $row['amount'];
  } elseif ($row['status'] == 'pending' || $row['status'] == 'partial') {
    $total_receivables += $row['amount'];
  }

  $invoices[] = [
    'db_id'   => $row['db_id'],
    'id'     => $row['invoice_id'],
    'client'   => $row['client_name'],
    'initials'  => $initials ?: 'CL',
    'corp'    => ($row['entity_type'] == 'Corporate'),
    'po_ref'   => $row['po_ref'],
    'issue_date' => $row['issue_date'],
    'due_date'  => $row['due_date'],
    'amount'   => (float)$row['amount'],
    'status'   => $row['status']
  ];
}

$total_expected = $collected_mtd + $total_receivables;
$collection_rate = $total_expected > 0 ? round(($collected_mtd / $total_expected) * 100, 1) : 0;

$stmt_q = $pdo->prepare("
  SELECT 
    q.id as db_id,
    q.quotation_number as quote_id, 
    p.company_name as client_name, 
    p.entity_type, 
    q.issue_date, 
    q.expiry_date as due_date, 
    q.total_value as amount, 
    q.status 
  FROM quotations q 
  JOIN partners p ON q.client_id = p.id 
  WHERE p.partner_type = 'client'
  ORDER BY q.issue_date DESC
");
$stmt_q->execute();
$quotes_records = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

$quotations = [];
foreach ($quotes_records as $row) {
  $words = preg_split('/\s+/', trim($row['client_name']));
  $initials = '';
  if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'), 'UTF-8');
  } elseif (count($words) == 1 && mb_strlen($words[0], 'UTF-8') > 0) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
  }
  
  $quotations[] = [
    'db_id'   => $row['db_id'],
    'id'     => $row['quote_id'],
    'client'   => $row['client_name'],
    'initials'  => $initials ?: 'CL',
    'corp'    => ($row['entity_type'] == 'Corporate'),
    'issue_date' => $row['issue_date'],
    'due_date'  => $row['due_date'],
    'amount'   => (float)$row['amount'],
    'status'   => $row['status']
  ];
}

$stmt_po = $pdo->prepare("
  SELECT 
    q.id as db_id,
    q.quotation_number as quote_id, 
    p.company_name as supplier_name, 
    p.entity_type, 
    q.issue_date, 
    q.expiry_date as due_date, 
    q.total_value as amount, 
    q.status 
  FROM quotations q 
  JOIN partners p ON q.client_id = p.id 
  WHERE p.partner_type = 'supplier'
  ORDER BY q.issue_date DESC
");
$stmt_po->execute();
$pos_records = $stmt_po->fetchAll(PDO::FETCH_ASSOC);

$purchase_orders = [];
foreach ($pos_records as $row) {
  $words = preg_split('/\s+/', trim($row['supplier_name']));
  $initials = '';
  if (count($words) >= 2) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'), 'UTF-8');
  } elseif (count($words) == 1 && mb_strlen($words[0], 'UTF-8') > 0) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 2, 'UTF-8'), 'UTF-8');
  }
  
  $purchase_orders[] = [
    'db_id'   => $row['db_id'],
    'id'     => $row['quote_id'],
    'client'   => $row['supplier_name'],
    'initials'  => $initials ?: 'SP',
    'corp'    => ($row['entity_type'] == 'Corporate'),
    'issue_date' => $row['issue_date'],
    'due_date'  => $row['due_date'],
    'amount'   => (float)$row['amount'],
    'status'   => $row['status']
  ];
}

$statusConfigs = [
  'paid'  => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Paid in Full', 'label_ar' => 'مدفوع بالكامل'],
  'partial' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'Partially Paid', 'label_ar' => 'مدفوع جزئياً'],
  'pending' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Awaiting Payment', 'label_ar' => 'بانتظار الدفع'],
  'overdue' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Overdue', 'label_ar' => 'متأخر'],
];

$quoteConfigs = [
  'draft'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'sent'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Sent / Pending', 'label_ar' => 'مرسل / معلق'],
  'accepted' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Accepted', 'label_ar' => 'مقبول'],
  'declined' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Declined', 'label_ar' => 'مرفوض'],
  'expired' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Expired', 'label_ar' => 'منتهي الصلاحية'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Collections & Invoices | I-GAS Enterprise</title>
  
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
        <span data-en="ACCOUNTS RECEIVABLE & BILLING LEDGER" data-ar="سجل الذمم المدينة والفوترة">ACCOUNTS RECEIVABLE & BILLING LEDGER</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= count($invoices) ?> <span data-en="INVOICES LOGGED" data-ar="فاتورة مسجلة">INVOICES LOGGED</span></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">

      <?php if (isset($_GET['success']) && $_GET['success'] === 'paid'): ?>
        <div class="p-3 bg-teal-950/30 dark:bg-teal-950/30 text-teal-300 dark:text-teal-300 border border-teal-800 dark:border-teal-800 rounded-sm text-xs font-mono flex items-center gap-2">
          <i data-lucide="check-circle-2" class="w-4 h-4"></i>
          <span data-en="Payment recorded successfully and client balance updated." data-ar="تم تسجيل الدفعة بنجاح وتحديث رصيد العميل.">Payment recorded successfully and client balance updated.</span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_msg)): ?>
        <div class="p-3 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 border border-rose-800 dark:border-rose-800 rounded-sm text-xs font-mono flex items-center gap-2">
          <i data-lucide="alert-circle" class="w-4 h-4"></i>
          <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
      <?php endif; ?>

      <!-- Page Header Card -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-4 border-b border-[#1a3346]">
        <div>
          <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-semibold block mb-1" data-en="FINANCIAL SETTLEMENTS & CASHFLOW" data-ar="التسويات المالية والتدفق النقدي">FINANCIAL SETTLEMENTS & CASHFLOW</span>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Collections & Invoices" data-ar="التحصيلات والفواتير">
            Collections & Invoices
          </h1>
          <p class="text-xs font-mono text-neutral-500 mt-1" data-en="Track invoice aging, record client remittance payments, and export ledger audits." data-ar="تتبع أعمار الفواتير، تسجيل مدفوعات العملاء، وتصدير سجل التدقيق المالي.">
            Track invoice aging, record client remittance payments, and export ledger audits.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" onclick="exportLedgerData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="download" class="w-3.5 h-3.5"></i>
            <span data-en="Export Ledger" data-ar="تصدير السجل">Export Ledger</span>
          </button>
          <a href="<?= app_url('modules/crm_sales/new_order.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span data-en="Book Order / Invoice" data-ar="حجز طلب / إصدار فاتورة">Book Order / Invoice</span>
          </a>
        </div>
      </div>

      <!-- Bento KPI Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block mb-2" data-en="TOTAL RECEIVABLES" data-ar="إجمالي المستحقات">TOTAL RECEIVABLES</span>
          <div class="text-2xl font-bold font-mono text-white tabular-nums whitespace-nowrap">
            <?= number_format($total_receivables, 2) ?> <span class="text-xs font-normal text-slate-400">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-slate-400 block mt-2" data-en="Across all open client accounts" data-ar="عبر كافة حسابات العملاء المفتوحة">Across all open client accounts</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block mb-2" data-en="OVERDUE AMOUNT" data-ar="المبالغ المتأخرة">OVERDUE AMOUNT</span>
          <div class="text-2xl font-bold font-mono text-rose-400 tabular-nums whitespace-nowrap">
            <?= number_format($overdue_amount, 2) ?> <span class="text-xs font-normal text-slate-400">SAR</span>
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-[11px] font-mono text-rose-400 font-semibold whitespace-nowrap">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
            <span data-en="Immediate Follow-up Required" data-ar="يتطلب متابعة عاجلة">Immediate Follow-up Required</span>
          </div>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block mb-2" data-en="COLLECTED (MTD)" data-ar="المحصل هذا الشهر">COLLECTED (MTD)</span>
          <div class="text-2xl font-bold font-mono text-teal-400 tabular-nums whitespace-nowrap">
            <?= number_format($collected_mtd, 2) ?> <span class="text-xs font-normal text-slate-400">SAR</span>
          </div>
          <span class="text-[11px] font-mono text-slate-400 block mt-2" data-en="Cleared payments this month" data-ar="دفعات مكتملة خلال الشهر">Cleared payments this month</span>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-5 border-s-4 border-s-[#f97316]">
          <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block mb-2" data-en="COLLECTION RATE" data-ar="معدل التحصيل">COLLECTION RATE</span>
          <div class="text-2xl font-bold font-mono text-white tabular-nums whitespace-nowrap">
            <?= $collection_rate ?>%
          </div>
          <div class="w-full bg-[#132636] h-1.5 rounded-full mt-3 overflow-hidden">
            <div class="bg-[#f97316] h-full rounded-full" style="width: <?= min(100, $collection_rate) ?>%;"></div>
          </div>
        </div>
      </div>

      <!-- Ledger Table Container -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-[#1a3346]">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div class="flex items-center gap-2">
              <i data-lucide="receipt" class="w-4 h-4 text-teal-400"></i>
              <h3 class="text-xs font-mono uppercase tracking-wider font-bold text-slate-200" data-en="COMMERCIAL LEDGER DOCUMENTS" data-ar="مستندات السجل التجاري">COMMERCIAL LEDGER DOCUMENTS</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search reference, client..." data-en-placeholder="Search reference, client..." data-ar-placeholder="البحث بالمرجع أو العميل..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
              <select id="statusFilter" class="h-9 px-3 py-1.5 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
                <option value="" data-en="All Statuses" data-ar="جميع الحالات">All Statuses</option>
                <option value="pending" data-en="Pending / Awaiting" data-ar="معلق / بانتظار الدفع">Pending / Awaiting</option>
                <option value="overdue" data-en="Overdue" data-ar="متأخر">Overdue</option>
                <option value="paid" data-en="Paid / Cleared" data-ar="مدفوع / خالص">Paid / Cleared</option>
              </select>
            </div>
          </div>

          <!-- Ledger Tabs -->
          <div class="flex items-center gap-4 border-b border-[#1a3346] -mb-4 pt-2">
            <button type="button" class="tab-btn pb-3 text-xs font-mono uppercase tracking-wider font-semibold border-b-2 border-teal-600 text-teal-400 cursor-pointer" onclick="switchTab('invoices', this)">
              <span data-en="Tax Invoices" data-ar="فواتير الضريبة">Tax Invoices</span>
              <span class="ml-1.5 px-1.5 py-0.5 rounded-sm bg-[#09151f] text-[10px]"><?= count($invoices) ?></span>
            </button>
            <button type="button" class="tab-btn pb-3 text-xs font-mono uppercase tracking-wider font-semibold border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer" onclick="switchTab('quotations', this)">
              <span data-en="Client Quotations" data-ar="عروض أسعار العملاء">Client Quotations</span>
              <span class="ml-1.5 px-1.5 py-0.5 rounded-sm bg-[#09151f] text-[10px]"><?= count($quotations) ?></span>
            </button>
            <button type="button" class="tab-btn pb-3 text-xs font-mono uppercase tracking-wider font-semibold border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer" onclick="switchTab('purchase_orders', this)">
              <span data-en="Supplier POs" data-ar="أوامر شراء الموردين">Supplier POs</span>
              <span class="ml-1.5 px-1.5 py-0.5 rounded-sm bg-[#09151f] text-[10px]"><?= count($purchase_orders) ?></span>
            </button>
          </div>
        </div>

        <!-- TAX INVOICES TABLE -->
        <div id="invoices_section" class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Invoice #" data-ar="رقم الفاتورة">Invoice #</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="PO Ref" data-ar="مرجع الطلب">PO Ref</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Issue Date" data-ar="تاريخ الإصدار">Issue Date</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Due Date" data-ar="تاريخ الاستحقاق">Due Date</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Amount (SAR)" data-ar="المبلغ (ريال)">Amount (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-center whitespace-nowrap" data-en="Payment Status" data-ar="حالة الدفع">Payment Status</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php foreach ($invoices as $inv): ?>
              <?php
                $s = $statusConfigs[$inv['status']] ?? $statusConfigs['pending'];
                $isOverdue = $inv['status'] === 'overdue';
              ?>
              <tr class="ledger-row hover:bg-[#102332]/50 transition-colors" data-status="<?= htmlspecialchars($inv['status']) ?>">
                <td class="px-6 py-4 font-mono font-bold whitespace-nowrap">
                  <a href="<?= app_url('modules/crm_sales/view_invoice.php?id=' . urlencode($inv['db_id'])) ?>" class="text-teal-400 hover:underline">
                    <?= htmlspecialchars($inv['id']) ?>
                  </a>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-sm bg-[#09151f] border border-[#1e3a50] flex items-center justify-center text-[10px] font-mono font-bold text-slate-300 shrink-0">
                      <?= htmlspecialchars($inv['initials']) ?>
                    </span>
                    <span class="font-sans font-medium text-white client-name truncate max-w-[200px]">
                      <?= htmlspecialchars($inv['client']) ?>
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 font-mono text-slate-400 ref-text whitespace-nowrap"><?= htmlspecialchars($inv['po_ref'] ?: '—') ?></td>
                <td class="px-6 py-4 font-mono tabular-nums text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($inv['issue_date']))) ?></td>
                <td class="px-6 py-4 font-mono tabular-nums whitespace-nowrap <?= $isOverdue ? 'text-rose-400 font-bold' : 'text-slate-400' ?>">
                  <?= htmlspecialchars(date('d M Y', strtotime($inv['due_date']))) ?>
                </td>
                <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums whitespace-nowrap">
                  <?= number_format($inv['amount'], 2) ?>
                </td>
                <td class="px-6 py-4 text-center whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm text-[11px] font-mono font-medium uppercase tracking-wider border <?= $s['border'] ?> <?= $s['bg'] ?> <?= $s['text'] ?> whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full <?= $s['dot'] ?>"></span>
                    <span data-en="<?= $s['label'] ?>" data-ar="<?= $s['label_ar'] ?>"><?= $s['label'] ?></span>
                  </span>
                </td>
                <td class="px-6 py-4 text-right rtl:text-left whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <?php if($inv['status'] !== 'paid' && has_permission($pdo, 'invoices.manage')): ?>
                    <form method="POST" class="inline m-0" onsubmit="return confirmPayment();">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action_type" value="record_payment">
                      <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($inv['db_id']) ?>">
                      <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 text-[11px] font-mono font-semibold uppercase bg-teal-950/40 text-teal-300 border border-teal-800 rounded-sm hover:bg-teal-900/60 transition-colors cursor-pointer whitespace-nowrap" data-en="Record Pay" data-ar="تسجيل دفع">
                        Record Pay
                      </button>
                    </form>
                    <?php endif; ?>
                    <a href="<?= app_url('modules/crm_sales/view_invoice.php?id=' . urlencode($inv['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="View Invoice">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($invoices)): ?>
              <tr>
                <td colspan="8" class="px-6 py-12 text-center text-xs font-mono text-slate-400" data-en="No invoices recorded currently." data-ar="لا توجد فواتير مسجلة حالياً.">
                  No invoices recorded currently.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- CLIENT QUOTATIONS TABLE -->
        <div id="quotations_section" class="overflow-x-auto custom-scrollbar flex-1 w-full" style="display: none;">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Ref #" data-ar="المرجع #">Ref #</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Client Account" data-ar="حساب العميل">Client Account</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Issue Date" data-ar="تاريخ الإصدار">Issue Date</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Valid Until" data-ar="صالح حتى">Valid Until</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Total Value (SAR)" data-ar="القيمة الإجمالية (ريال)">Total Value (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-center whitespace-nowrap" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php foreach ($quotations as $q): ?>
              <?php
                $qs = $quoteConfigs[$q['status']] ?? $quoteConfigs['draft'];
              ?>
              <tr class="ledger-row hover:bg-[#102332]/50 transition-colors" data-status="<?= htmlspecialchars($q['status']) ?>">
                <td class="px-6 py-4 font-mono font-bold whitespace-nowrap">
                  <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($q['db_id'])) ?>" class="text-teal-400 hover:underline">
                    <?= htmlspecialchars($q['id']) ?>
                  </a>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-sm bg-[#09151f] border border-[#1e3a50] flex items-center justify-center text-[10px] font-mono font-bold text-slate-300 shrink-0">
                      <?= htmlspecialchars($q['initials']) ?>
                    </span>
                    <span class="font-sans font-medium text-white client-name truncate max-w-[200px]">
                      <?= htmlspecialchars($q['client']) ?>
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 font-mono tabular-nums text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($q['issue_date']))) ?></td>
                <td class="px-6 py-4 font-mono tabular-nums text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($q['due_date']))) ?></td>
                <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums whitespace-nowrap"><?= number_format($q['amount'], 2) ?></td>
                <td class="px-6 py-4 text-center whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm text-[11px] font-mono uppercase tracking-wider border <?= $qs['border'] ?> <?= $qs['bg'] ?> <?= $qs['text'] ?> whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full <?= $qs['dot'] ?>"></span>
                    <span data-en="<?= $qs['label'] ?>" data-ar="<?= $qs['label_ar'] ?>"><?= $qs['label'] ?></span>
                  </span>
                </td>
                <td class="px-6 py-4 text-right rtl:text-left whitespace-nowrap">
                  <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($q['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="View Quotation">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($quotations)): ?>
              <tr>
                <td colspan="7" class="px-6 py-12 text-center text-xs font-mono text-slate-400" data-en="No quotations found for clients." data-ar="لم يتم العثور على عروض أسعار للعملاء.">
                  No quotations found for clients.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- SUPPLIER POS TABLE -->
        <div id="purchase_orders_section" class="overflow-x-auto custom-scrollbar flex-1 w-full" style="display: none;">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="PO Ref #" data-ar="مرجع أمر الشراء #">PO Ref #</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Supplier Account" data-ar="حساب المورد">Supplier Account</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Issue Date" data-ar="تاريخ الإصدار">Issue Date</th>
                <th class="px-6 py-3.5 font-semibold whitespace-nowrap" data-en="Valid Until" data-ar="صالح حتى">Valid Until</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Total Value (SAR)" data-ar="القيمة الإجمالية (ريال)">Total Value (SAR)</th>
                <th class="px-6 py-3.5 font-semibold text-center whitespace-nowrap" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-6 py-3.5 font-semibold text-right rtl:text-left whitespace-nowrap" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="text-xs divide-y divide-[#152a3a]">
              <?php foreach ($purchase_orders as $po): ?>
              <?php
                $qs = $quoteConfigs[$po['status']] ?? $quoteConfigs['draft'];
              ?>
              <tr class="ledger-row hover:bg-[#102332]/50 transition-colors" data-status="<?= htmlspecialchars($po['status']) ?>">
                <td class="px-6 py-4 font-mono font-bold whitespace-nowrap">
                  <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($po['db_id'])) ?>" class="text-teal-400 hover:underline">
                    <?= htmlspecialchars($po['id']) ?>
                  </a>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-sm bg-[#09151f] border border-[#1e3a50] flex items-center justify-center text-[10px] font-mono font-bold text-slate-300 shrink-0">
                      <?= htmlspecialchars($po['initials']) ?>
                    </span>
                    <span class="font-sans font-medium text-white client-name truncate max-w-[200px]">
                      <?= htmlspecialchars($po['client']) ?>
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 font-mono tabular-nums text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($po['issue_date']))) ?></td>
                <td class="px-6 py-4 font-mono tabular-nums text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($po['due_date']))) ?></td>
                <td class="px-6 py-4 text-right rtl:text-left font-mono font-bold text-white tabular-nums whitespace-nowrap"><?= number_format($po['amount'], 2) ?></td>
                <td class="px-6 py-4 text-center whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm text-[11px] font-mono uppercase tracking-wider border <?= $qs['border'] ?> <?= $qs['bg'] ?> <?= $qs['text'] ?> whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full <?= $qs['dot'] ?>"></span>
                    <span data-en="<?= $qs['label'] ?>" data-ar="<?= $qs['label_ar'] ?>"><?= $qs['label'] ?></span>
                  </span>
                </td>
                <td class="px-6 py-4 text-right rtl:text-left whitespace-nowrap">
                  <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($po['db_id'])) ?>" class="btn-table-action whitespace-nowrap" title="View PO">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($purchase_orders)): ?>
              <tr>
                <td colspan="7" class="px-6 py-12 text-center text-xs font-mono text-slate-400" data-en="No purchase orders found for suppliers." data-ar="لم يتم العثور على أوامر شراء للموردين.">
                  No purchase orders found for suppliers.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
    let activeCurrentTab = 'invoices';

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        el.innerText = transText;
      });
      document.querySelectorAll('input[data-en-placeholder]').forEach(el => {
        const ph = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
        el.placeholder = ph;
      });
    }

    function switchTab(tabName, element) {
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-teal-600', 'text-teal-600', 'dark:text-teal-400');
        btn.classList.add('border-transparent', 'text-neutral-500');
      });
      element.classList.remove('border-transparent', 'text-neutral-500');
      element.classList.add('border-teal-600', 'text-teal-600', 'dark:text-teal-400');
      
      activeCurrentTab = tabName;
      
      document.getElementById('invoices_section').style.display = 'none';
      document.getElementById('quotations_section').style.display = 'none';
      document.getElementById('purchase_orders_section').style.display = 'none';

      if (tabName === 'invoices') {
        document.getElementById('invoices_section').style.display = 'block';
      } else if (tabName === 'quotations') {
        document.getElementById('quotations_section').style.display = 'block';
      } else if (tabName === 'purchase_orders') {
        document.getElementById('purchase_orders_section').style.display = 'block';
      }
      filterRows();
    }

    function filterRows() {
      const query = (document.getElementById('searchInput')?.value || '').toLowerCase();
      const selectedStatus = document.getElementById('statusFilter')?.value || '';
      const activeSection = document.getElementById(activeCurrentTab + '_section');
      if(!activeSection) return;

      const rows = activeSection.querySelectorAll('.ledger-row');
      rows.forEach(row => {
        const textContent = row.innerText.toLowerCase();
        const rowStatus = row.getAttribute('data-status');

        const matchesSearch = textContent.includes(query);
        
        let matchesStatus = true;
        if (selectedStatus) {
          if (selectedStatus === 'pending') {
            matchesStatus = ['pending', 'partial', 'sent', 'draft'].includes(rowStatus);
          } else if (selectedStatus === 'paid') {
            matchesStatus = ['paid', 'accepted'].includes(rowStatus);
          } else {
            matchesStatus = (rowStatus === selectedStatus);
          }
        }

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);

      const searchInput = document.getElementById('searchInput');
      const statusFilter = document.getElementById('statusFilter');

      if (searchInput) searchInput.addEventListener('input', filterRows);
      if (statusFilter) statusFilter.addEventListener('change', filterRows);
    });

    function exportLedgerData() {
      const activeSection = document.getElementById(activeCurrentTab + '_section');
      const table = activeSection.querySelector('table');
      const csv = [];
      const rows = table.querySelectorAll("tr");
      
      rows.forEach(row => {
        if (row.style.display === 'none') return;
        const cols = row.querySelectorAll("td, th");
        const data = [];
        for(let i = 0; i < cols.length - 1; i++) {
          data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
        }
        csv.push(data.join(","));
      });

      const csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      const downloadLink = document.createElement("a");
      downloadLink.download = activeCurrentTab + "_ledger_export_" + new Date().toISOString().slice(0,10) + ".csv";
      downloadLink.href = window.URL.createObjectURL(csvFile);
      downloadLink.style.display = "none";
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }

    function confirmPayment() {
      const currentLang = localStorage.getItem('app_lang') || 'en';
      return confirm(currentLang === 'ar' ? 'هل أنت متأكد من تسجيل دفع هذه الفاتورة؟' : 'Are you sure you want to mark this invoice as paid?');
    }
  </script>
</body>
</html>
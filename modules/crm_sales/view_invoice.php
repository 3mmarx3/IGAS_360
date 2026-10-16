<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'invoices.view');

$invoice_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$invoice_id) {
  $invoice_id = (int)$pdo->query("SELECT id FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
}

if (!$invoice_id) {
  redirect(app_url('modules/crm_sales/collections_invoices.php'));
}

$stmt = $pdo->prepare("
  SELECT i.*, 
      p.company_name, p.address, p.city, p.country, p.tax_id, p.email, p.phone, p.contact_first_name, p.contact_last_name, p.reference_id
  FROM invoices i
  JOIN partners p ON i.client_id = p.id
  WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
  $invoice_id = (int)$pdo->query("SELECT id FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
  if ($invoice_id) {
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}

if (!$invoice) {
  redirect(app_url('modules/crm_sales/collections_invoices.php'));
}

// Ownership / IDOR check for sales reps
if (!check_record_ownership($pdo, 'partners', (int)$invoice['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this invoice.");
}

$po = null;
$po_items = [];
if (!empty($invoice['po_reference'])) {
  $stmt_po = $pdo->prepare("SELECT * FROM purchase_orders WHERE order_number = ?");
  $stmt_po->execute([$invoice['po_reference']]);
  $po = $stmt_po->fetch(PDO::FETCH_ASSOC);

  if ($po) {
    $stmt_items = $pdo->prepare("
      SELECT poi.*, p.company_name as supplier_name 
      FROM purchase_order_items poi 
      LEFT JOIN partners p ON poi.supplier_reference = p.reference_id 
      WHERE poi.purchase_order_id = ?
    ");
    $stmt_items->execute([$po['id']]);
    $po_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt_quote = $pdo->prepare("SELECT * FROM quotations WHERE quotation_number = ?");
    $stmt_quote->execute([$invoice['po_reference']]);
    $quote = $stmt_quote->fetch(PDO::FETCH_ASSOC);

    if ($quote) {
      $stmt_items = $pdo->prepare("SELECT product_name as item_name, qty, unit, unit_price, line_total FROM quotation_items WHERE quotation_id = ?");
      $stmt_items->execute([$quote['id']]);
      $po_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'record_payment') {
  require_csrf();
  require_permission($pdo, 'invoices.manage');

  try {
    $pdo->beginTransaction();

    $stmt_upd = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
    $stmt_upd->execute([$invoice_id]);

    $stmt_client = $pdo->prepare("UPDATE partners SET balance_due = GREATEST(0, balance_due - ?) WHERE id = ?");
    $stmt_client->execute([$invoice['amount'], $invoice['client_id']]);

    $act_text = "Recorded payment for invoice " . $invoice['invoice_number'] . " amounting to " . number_format($invoice['amount'], 2) . " SAR";
    $author_name = $_SESSION['user_name'] ?? 'System';
    $stmt_act = $pdo->prepare("INSERT INTO client_activities (client_id, activity_text, activity_time, author) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
    $stmt_act->execute([$invoice['client_id'], $act_text, $author_name]);

    $pdo->commit();
    redirect(app_url('modules/crm_sales/view_invoice.php?id=' . urlencode($invoice_id)));
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error_msg = "Payment recording failed.";
  }
}

$active_page = 'collections_invoices';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Collections & Invoices', $invoice['invoice_number']];

$invoiceStatusStyles = [
  'paid'  => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Paid in Full', 'label_ar' => 'مدفوع بالكامل'],
  'partial' => ['border' => 'border-blue-500/30', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500', 'label' => 'Partially Paid', 'label_ar' => 'مدفوع جزئياً'],
  'pending' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Awaiting Payment', 'label_ar' => 'بانتظار الدفع'],
  'overdue' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Overdue', 'label_ar' => 'متأخر'],
];

$st = $invoiceStatusStyles[$invoice['status']] ?? $invoiceStatusStyles['pending'];
$inv_amount = (float)$invoice['amount'];
$subtotal_ex = $inv_amount / 1.15;
$vat_amount = $inv_amount - $subtotal_ex;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($invoice['invoice_number']) ?> | I-GAS Enterprise</title>
  
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
      .print-clean { border: 1px solid #CCCCCC !important; background: transparent !important; }
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
        <span data-en="ZATCA TAX INVOICE COMPLIANT" data-ar="فاتورة ضريبية متوافقة مع هيئة الزكاة">ZATCA TAX INVOICE COMPLIANT</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 w-full mx-auto">

      <!-- Back Link & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#1a3346] no-print">
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/collections_invoices.php') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-400 bg-[#0c1924] border border-[#1e3a50] rounded-sm hover:border-teal-500 dark:hover:border-teal-400 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Invoices" data-ar="العودة للفواتير">Back to Invoices</span>
          </a>
          <span class="text-neutral-400 dark:text-neutral-600">/</span>
          <span class="text-xs font-mono text-slate-400 uppercase tracking-wider"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
        </div>

        <div class="flex items-center gap-3">
          <?php if ($invoice['status'] !== 'paid' && has_permission($pdo, 'invoices.manage')): ?>
            <form method="POST" class="inline m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action_type" value="record_payment">
              <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-mono uppercase tracking-wider text-teal-400 dark:text-teal-300 bg-teal-950/30 dark:bg-teal-950/40 border border-teal-800 dark:border-teal-800 rounded-sm hover:bg-teal-100 transition-colors cursor-pointer">
                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                <span data-en="Record Full Payment" data-ar="تسجيل السداد الكامل">Record Full Payment</span>
              </button>
            </form>
          <?php endif; ?>
          <button type="button" onclick="window.print()" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-mono uppercase tracking-wider rounded-sm cursor-pointer">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            <span data-en="Print Tax Invoice" data-ar="طباعة الفاتورة الضريبية">Print Tax Invoice</span>
          </button>
        </div>
      </div>

      <!-- Tax Invoice Document Canvas -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-sm p-8 sm:p-12 print-clean border-s-4 border-s-[#f97316]">
        
        <!-- Document Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start border-b border-[#1a3346] pb-8 mb-8 gap-6">
          <div>
            <span class="text-xs font-mono uppercase tracking-wider text-teal-600 dark:text-teal-400 font-bold block mb-1" data-en="KINGDOM OF SAUDI ARABIA" data-ar="المملكة العربية السعودية">KINGDOM OF SAUDI ARABIA</span>
            <h1 class="text-3xl font-bold font-mono tracking-tight text-white mb-1" data-en="TAX INVOICE" data-ar="فاتورة ضريبية">
              TAX INVOICE
            </h1>
            <p class="text-xs font-mono text-neutral-500">
              <span data-en="INVOICE NUMBER:" data-ar="رقم الفاتورة:">INVOICE NUMBER:</span>
              <span class="font-bold text-white"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
            </p>
            <?php if(!empty($invoice['po_reference'])): ?>
              <p class="text-xs font-mono text-neutral-500 mt-0.5">
                <span data-en="PO REFERENCE:" data-ar="مرجع أمر الشراء:">PO REFERENCE:</span>
                <span class="font-semibold text-slate-200"><?= htmlspecialchars($invoice['po_reference']) ?></span>
              </p>
            <?php endif; ?>
          </div>

          <div class="text-left sm:text-right rtl:sm:text-left font-mono text-xs text-slate-400">
            <h2 class="text-base font-bold text-white font-sans mb-1">I-GAS Industrial Gas Plant Co.</h2>
            <p>Jeddah Industrial City, Phase 2</p>
            <p>PO Box 41209, Jeddah, KSA</p>
            <p class="mt-1 font-bold text-teal-400 dark:text-teal-400">VAT ID: 300123456700003</p>
            <p class="text-[11px] text-neutral-400 mt-1">CR: 4030198821</p>
          </div>
        </div>

        <!-- Billed To & Dates Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 pb-8 border-b border-[#1a3346]">
          <div class="space-y-2 text-xs font-mono">
            <span class="text-[10px] uppercase text-neutral-400 tracking-wider block font-bold" data-en="BILLED TO CUSTOMER" data-ar="فاتورة إلى العميل">BILLED TO CUSTOMER</span>
            <h3 class="text-base font-bold font-sans text-white"><?= htmlspecialchars($invoice['company_name']) ?></h3>
            <p class="text-slate-400 font-sans">
              Attn: <?= htmlspecialchars(trim($invoice['contact_first_name'] . ' ' . $invoice['contact_last_name'])) ?: 'Accounts Dept' ?>
            </p>
            <p class="text-slate-400 font-sans">
              <?= htmlspecialchars(implode(', ', array_filter([$invoice['address'], $invoice['city'], $invoice['country']]))) ?>
            </p>
            <?php if(!empty($invoice['tax_id'])): ?>
              <p class="font-bold text-slate-200 pt-1">
                <span data-en="Customer VAT:" data-ar="الرقم الضريبي:">Customer VAT:</span> <?= htmlspecialchars($invoice['tax_id']) ?>
              </p>
            <?php endif; ?>
            <p class="text-neutral-500">
              <span data-en="Client Ref:" data-ar="رمز العميل:">Client Ref:</span> <?= htmlspecialchars($invoice['reference_id'] ?? 'N/A') ?>
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4 text-xs font-mono bg-[#09151f] p-4 rounded-sm border border-[#1e3a50]/50">
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="ISSUE DATE" data-ar="تاريخ الإصدار">ISSUE DATE</span>
              <span class="font-bold text-white tabular-nums">
                <?= date('d M Y', strtotime($invoice['issue_date'])) ?>
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="PAYMENT DUE" data-ar="تاريخ الاستحقاق">PAYMENT DUE</span>
              <span class="font-bold tabular-nums <?= ($invoice['status'] === 'overdue') ? 'text-rose-600 dark:text-rose-400' : 'text-white' ?>">
                <?= date('d M Y', strtotime($invoice['due_date'])) ?>
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="PAYMENT STATUS" data-ar="حالة الدفع">PAYMENT STATUS</span>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-[10px] uppercase border <?= $st['border'] ?> <?= $st['bg'] ?> <?= $st['text'] ?> font-bold">
                <?= $st['label'] ?>
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase text-neutral-400 block" data-en="CURRENCY" data-ar="العملة">CURRENCY</span>
              <span class="font-bold text-white">SAR (Saudi Riyal)</span>
            </div>
          </div>
        </div>

        <!-- Logistics & Delivery Information (if applicable) -->
        <?php if ($po): ?>
        <div class="mb-8 p-4 rounded-sm border border-[#1a3346] bg-[#09151f] bg-[#112433]/40 text-xs font-mono">
          <span class="text-[10px] uppercase font-bold tracking-wider text-teal-400 dark:text-teal-400 block mb-3" data-en="LOGISTICS & SITE DELIVERY DETAILS" data-ar="بيانات الشحن وموقع التسليم">LOGISTICS & SITE DELIVERY DETAILS</span>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <span class="text-[10px] text-neutral-400 uppercase block" data-en="Target Date" data-ar="موعد التسليم">Target Date</span>
              <span class="font-medium text-slate-200 tabular-nums">
                <?= !empty($po['delivery_date']) ? date('d M Y', strtotime($po['delivery_date'])) : 'Standard Schedule' ?>
              </span>
            </div>
            <div>
              <span class="text-[10px] text-neutral-400 uppercase block" data-en="Target Time" data-ar="وقت التسليم">Target Time</span>
              <span class="font-medium text-slate-200 tabular-nums">
                <?= !empty($po['delivery_time']) ? htmlspecialchars($po['delivery_time']) : 'Standard Window' ?>
              </span>
            </div>
            <div class="sm:col-span-3">
              <span class="text-[10px] text-neutral-400 uppercase block" data-en="Delivery Destination Bay" data-ar="رصيف وموقع التفريغ">Delivery Destination Bay</span>
              <span class="font-sans text-slate-200">
                <?= !empty($po['delivery_address']) ? nl2br(htmlspecialchars($po['delivery_address'])) : 'Registered Plant Site Location' ?>
              </span>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Line Items Table -->
        <div class="mb-8 overflow-x-auto border border-[#1a3346] rounded-sm">
          <table class="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr class="text-[11px] font-mono uppercase tracking-wider bg-[#09151f] border-b border-[#1a3346] text-slate-400">
                <th class="px-4 py-3 font-semibold w-12">#</th>
                <th class="px-4 py-3 font-semibold" data-en="Item Description" data-ar="وصف الصنف">Item Description</th>
                <th class="px-4 py-3 font-semibold" data-en="Supplier Source" data-ar="المصدر">Supplier Source</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Qty" data-ar="الكمية">Qty</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Unit Price (SAR)" data-ar="سعر الوحدة">Unit Price (SAR)</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Total (SAR)" data-ar="الإجمالي">Total (SAR)</th>
              </tr>
            </thead>
            <tbody class="text-xs font-mono divide-y divide-[#152a3a]">
              <?php if(!empty($po_items)): ?>
                <?php foreach($po_items as $index => $item): ?>
                <tr class="hover:bg-[#102332]/50 dark:hover:bg-neutral-800/40 transition-colors">
                  <td class="px-4 py-3.5 text-neutral-400"><?= $index + 1 ?></td>
                  <td class="px-4 py-3.5 font-sans font-medium text-white">
                    <?= htmlspecialchars($item['item_name']) ?>
                    <?php if(!empty($item['item_code'])): ?>
                      <span class="block text-[10px] font-mono text-neutral-400">Code: <?= htmlspecialchars($item['item_code']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3.5 text-neutral-500 font-sans">
                    <?= htmlspecialchars($item['supplier_name'] ?? 'I-GAS Cryogenic Facility') ?>
                  </td>
                  <td class="px-4 py-3.5 text-right rtl:text-left tabular-nums text-white font-bold">
                    <?= htmlspecialchars($item['qty']) ?> <span class="text-[10px] text-neutral-400 font-normal"><?= htmlspecialchars($item['unit'] ?? 'CYL') ?></span>
                  </td>
                  <td class="px-4 py-3.5 text-right rtl:text-left tabular-nums text-slate-300">
                    <?= number_format($item['unit_price'], 2) ?>
                  </td>
                  <td class="px-4 py-3.5 text-right rtl:text-left tabular-nums font-bold text-white">
                    <?= number_format($item['line_total'], 2) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td class="px-4 py-4 text-neutral-400">1</td>
                  <td class="px-4 py-4 font-sans font-medium text-white" colspan="2">
                    Industrial Gas Supply & Cryogenic Filling Quota
                    <?php if(!empty($invoice['po_reference'])): ?>
                      <span class="block text-[10px] font-mono text-neutral-400">Reference: <?= htmlspecialchars($invoice['po_reference']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 text-right rtl:text-left tabular-nums font-bold">1 Lot</td>
                  <td class="px-4 py-4 text-right rtl:text-left tabular-nums"><?= number_format($subtotal_ex, 2) ?></td>
                  <td class="px-4 py-4 text-right rtl:text-left tabular-nums font-bold"><?= number_format($subtotal_ex, 2) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Financial Calculation & Payment Terms -->
        <div class="flex flex-col md:flex-row justify-between items-start gap-8">
          <div class="flex-1 text-xs font-mono space-y-2 text-neutral-500">
            <span class="text-[10px] uppercase font-bold tracking-wider text-slate-300 block" data-en="BANK TRANSFER REMITTANCE INSTRUCTIONS" data-ar="تعليمات التحويل المصرفي">BANK TRANSFER REMITTANCE INSTRUCTIONS</span>
            <p>Bank: Saudi National Bank (SNB) · IBAN: SA0310000001234567890123</p>
            <p>Account Name: I-GAS Industrial Gas Plant Co.</p>
            <p data-en="Please quote invoice reference upon electronic wire transfer." data-ar="يرجى تضمين رقم الفاتورة المرجعي عند التحويل البنكي.">Please quote invoice reference upon electronic wire transfer.</p>
          </div>

          <div class="w-full md:w-80 p-5 rounded-sm bg-[#09151f] border border-[#1e3a50]/60 font-mono text-xs space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-neutral-500" data-en="Subtotal (ex. VAT)" data-ar="المجموع (غير شامل الضريبة)">Subtotal (ex. VAT)</span>
              <span class="font-semibold text-white tabular-nums"><?= number_format($subtotal_ex, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-neutral-500" data-en="VAT (15%)" data-ar="ضريبة القيمة المضافة (15%)">VAT (15%)</span>
              <span class="font-semibold text-white tabular-nums"><?= number_format($vat_amount, 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-[#1e3a50]">
              <span class="text-sm font-bold text-white" data-en="Total Amount Due" data-ar="إجمالي المبلغ المستحق">Total Amount Due</span>
              <span class="text-lg font-bold text-teal-400 dark:text-teal-400 tabular-nums"><?= number_format($inv_amount, 2) ?> <span class="text-xs font-normal text-neutral-500">SAR</span></span>
            </div>
          </div>
        </div>

        <!-- Print Footer Sign-off Block -->
        <div class="hidden print:flex justify-between mt-16 pt-8 border-t border-neutral-300 text-xs font-mono">
          <div class="w-1/3 text-center">
            <div class="h-12 border-b border-dashed border-neutral-400 mb-2"></div>
            <p class="font-bold uppercase">Finance Officer Signature</p>
            <p class="text-neutral-500 text-[10px]">I-GAS Plant Operations</p>
          </div>
          <div class="w-1/3 text-center">
            <div class="h-12 border-b border-dashed border-neutral-400 mb-2"></div>
            <p class="font-bold uppercase">Customer Acceptance Stamp</p>
            <p class="text-neutral-500 text-[10px]">Received in Good Order</p>
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
        el.innerText = transText;
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      applyLanguage(localStorage.getItem('app_lang') || 'en');
    });
  </script>
</body>
</html>
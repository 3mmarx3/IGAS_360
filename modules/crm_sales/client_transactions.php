<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission($pdo, 'invoices.view');

$active_page = 'clients'; 
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Client Transactions & Stats'];

$ref_id = $_GET['id'] ?? null;

if (!$ref_id) {
  $firstRef = $pdo->query("SELECT reference_id FROM partners WHERE partner_type = 'client' ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstRef) {
    $ref_id = $firstRef;
  } else {
    redirect(app_url('modules/crm_sales/clients_directory.php'));
  }
}

$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE (reference_id = ? OR CAST(id AS CHAR) = ?) AND partner_type = 'client'");
$stmt_client->execute([$ref_id, $ref_id]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

if (!$client) {
  redirect(app_url('modules/crm_sales/clients_directory.php'));
}

// Ownership / IDOR check for sales reps
if (!check_record_ownership($pdo, 'partners', (int)$client['id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view transactions for this client.");
}

$client_id = $client['id'];

// ------------------------------------------------------------------
// Handle Actions (Toggle Invoice Payment & Quick Pay)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  require_csrf();
  require_permission($pdo, 'invoices.manage');

  $action = $_POST['action'];

  if ($action === 'toggle_invoice') {
    $inv_id = (int)$_POST['invoice_id'];
    $new_status = $_POST['new_status'];
    
    $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?")->execute([$new_status, $inv_id]);
    
    if ($new_status === 'paid') {
      $inv = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
      $inv->execute([$inv_id]);
      $invData = $inv->fetch(PDO::FETCH_ASSOC);
      
      if ($invData) {
        $rv = $pdo->prepare("INSERT OR IGNORE INTO receipt_vouchers (receipt_number, invoice_id, client_id, amount, payment_date, notes) VALUES (?, ?, ?, ?, CURRENT_DATE, 'Auto-generated upon status toggle')");
        $rv->execute(['RV-AUTO-'.$inv_id, $inv_id, $invData['client_id'], $invData['amount']]);
      }
    } else {
      $pdo->prepare("DELETE FROM receipt_vouchers WHERE invoice_id = ? AND receipt_number LIKE 'RV-AUTO-%'")->execute([$inv_id]);
    }
    redirect(app_url('modules/crm_sales/client_transactions.php?id=' . urlencode($ref_id)));
  }

  if ($action === 'quick_pay_po') {
    $po_number = $_POST['po_number'];
    $po_total = (float)$_POST['po_total'];
    $c_db_id = (int)$_POST['client_id'];

    $inv_num = 'INV-' . rand(10000, 99999);
    $ins_inv = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, po_reference, issue_date, due_date, amount, status) VALUES (?, ?, ?, CURRENT_DATE, CURRENT_DATE, ?, 'paid')");
    $ins_inv->execute([$inv_num, $c_db_id, $po_number, $po_total]);
    $inv_id = $pdo->lastInsertId();

    $rv_num = 'RV-AUTO-' . $inv_id;
    $ins_rv = $pdo->prepare("INSERT INTO receipt_vouchers (receipt_number, invoice_id, client_id, amount, payment_date, notes) VALUES (?, ?, ?, ?, CURRENT_DATE, 'Auto Quick Pay')");
    $ins_rv->execute([$rv_num, $inv_id, $c_db_id, $po_total]);

    redirect(app_url('modules/crm_sales/client_transactions.php?id=' . urlencode($ref_id)));
  }
}

// ------------------------------------------------------------------
// Fetch KPIs & Data
// ------------------------------------------------------------------
$total_sales = $pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM purchase_orders WHERE client_id = ? AND status NOT IN ('cancelled', 'draft')");
$total_sales->execute([$client_id]);
$m_sales = (float)$total_sales->fetchColumn();

$total_collections = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM receipt_vouchers WHERE client_id = ?");
$total_collections->execute([$client_id]);
$m_collections = (float)$total_collections->fetchColumn();

$m_due = $m_sales - $m_collections;
if ($m_due < 0) $m_due = 0.0;

$stmt_tanks = $pdo->prepare("SELECT * FROM customer_tanks WHERE client_id = ?");
$stmt_tanks->execute([$client_id]);
$client_tanks = $stmt_tanks->fetchAll(PDO::FETCH_ASSOC);

$stmt_quotations = $pdo->prepare("
  SELECT q.quotation_number, qi.product_name, qi.qty as total_qty, qi.consumed_qty, qi.unit
  FROM quotations q
  JOIN quotation_items qi ON q.id = qi.quotation_id
  WHERE q.client_id = ? AND q.status = 'accepted' AND qi.qty > qi.consumed_qty
");
$stmt_quotations->execute([$client_id]);
$active_quotations = $stmt_quotations->fetchAll(PDO::FETCH_ASSOC);

$sep = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') ? ", ''" : " SEPARATOR ''";
$stmt_orders = $pdo->prepare("
  SELECT 
    po.id, po.order_number, po.order_date, po.delivery_date, 
    po.total_value, po.status as delivery_status, po.production_status, po.payment_terms,
    (
      SELECT GROUP_CONCAT(
        CONCAT(
          '<li class=\"py-1.5 px-3 flex justify-between items-center text-xs border-b border-[var(--line-soft)] last:border-0\">',
          '<span class=\"font-medium text-[var(--ink)]\">', item_name, '</span>',
          '<span class=\"font-mono font-bold text-[var(--ink)]\">', CAST(qty AS INT), ' <span class=\"text-[10px] text-[var(--ink-muted)] font-normal\">', unit, '</span></span>',
          '</li>'
        ){$sep}
      ) 
      FROM purchase_order_items 
      WHERE purchase_order_id = po.id
    ) as requested_items,
    (
      SELECT COALESCE(SUM(rv.amount), 0) 
      FROM receipt_vouchers rv 
      JOIN invoices i ON rv.invoice_id = i.id 
      WHERE i.po_reference = po.order_number
    ) as amount_paid
  FROM purchase_orders po 
  WHERE po.client_id = ? 
  ORDER BY po.order_date DESC, po.id DESC
");
$stmt_orders->execute([$client_id]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

$stmt_invoices = $pdo->prepare("
  SELECT 
    i.id, i.invoice_number, i.po_reference, i.issue_date, i.due_date, i.amount, i.status,
    (SELECT COALESCE(SUM(amount), 0) FROM receipt_vouchers WHERE invoice_id = i.id) as paid_amount
  FROM invoices i
  WHERE i.client_id = ?
  ORDER BY i.due_date ASC
");
$stmt_invoices->execute([$client_id]);
$all_invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);

$invoices_by_po = [];
foreach($all_invoices as $inv) {
  $invoices_by_po[$inv['po_reference']][] = $inv;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($client['company_name']) ?> | Transactions & Statement | I-GAS Enterprise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= app_url('assets/css/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    (function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
      if (savedLang === 'ar') {
        document.documentElement.classList.add('arabic-mode');
      } else {
        document.documentElement.classList.remove('arabic-mode');
      }
      void document.documentElement.offsetHeight;
      requestAnimationFrame(function() {
        document.documentElement.classList.add('layout-ready');
      });
    })();
  </script>
  <style>
    *, *::before, *::after {
      box-shadow: none !important;
      text-shadow: none !important;
      filter: none !important;
    }
    @media print {
      body { background: white !important; }
      aside, header, .no-print { display: none !important; }
      main { padding: 0 !important; margin: 0 !important; }
    }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[var(--surface-0)] text-[var(--ink)] font-sans">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[var(--surface-0)] overflow-hidden">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[var(--line)] bg-[var(--surface-1)] flex items-center px-4 md:px-8 gap-3 sm:gap-4 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono no-print">
      <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-[var(--ink)]">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>Financial Ledger Active</span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Account: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($client['company_name']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Ref ID: <span class="text-[var(--cryo-teal)] font-semibold"><?= htmlspecialchars($client['reference_id']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">Reconciliation: Daily AST</span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid print:p-0">

      <!-- Breadcrumb Navigation Back Link -->
      <div class="mb-4 no-print">
        <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode($client['reference_id'])) ?>" class="inline-flex items-center gap-1.5 text-xs font-mono text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors whitespace-nowrap">
          <?= icon('arrow-left', 'w-3.5 h-3.5 rtl:rotate-180') ?>
          <span data-en="Back to Client Profile" data-ar="العودة إلى ملف العميل">Back to Client Profile</span>
        </a>
      </div>

      <!-- Page Title Bar -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <?= tag_plate($client['reference_id'], 'teal') ?>
            <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Financial Ledger & Statement of Account" data-ar="كشف الحساب وسجل المعاملات">Financial Ledger & Statement of Account</span>
          </div>
          <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]"><?= htmlspecialchars($client['company_name']) ?></h1>
          <p class="text-xs text-[var(--ink-muted)] mt-1 font-mono">Real-time procurement transactions, VAT invoices, payment vouchers, and cylinder tank quotas.</p>
        </div>
        <div class="flex items-center gap-3 no-print">
          <button type="button" onclick="window.print()" class="px-4 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center gap-2 transition-colors cursor-pointer">
            <?= icon('printer', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
            <span data-en="Print Statement" data-ar="طباعة الكشف">Print Statement</span>
          </button>
          <?php if (has_permission($pdo, 'orders.create')): ?>
          <a href="<?= app_url('modules/crm_sales/new_order.php?client_id=' . urlencode($client['reference_id'])) ?>" class="btn-cta-orange px-4 py-2 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-2 transition-colors">
            <?= icon('plus', 'w-4 h-4') ?>
            <span data-en="Book Gas Order" data-ar="طلب توريد غاز">Book Gas Order</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- KPI Metric Strip (4 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-4">
          <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] block mb-1" data-en="Total Orders" data-ar="إجمالي الطلبات">Total Orders</span>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]"><?= count($orders) ?></div>
          <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1">Fulfilled & active lots</p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--frost-cyan)] rounded-sm p-4">
          <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] block mb-1" data-en="Total Invoiced" data-ar="إجمالي الفواتير">Total Invoiced</span>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($m_sales, 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1">Cumulative sales billing</p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-4 border-s-[#f97316] rounded-sm p-4">
          <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] block mb-1" data-en="Total Collected" data-ar="إجمالي المدفوع">Total Collected</span>
          <div class="text-2xl font-bold font-mono tabular-nums text-emerald-600">
            <?= number_format($m_collections, 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono text-emerald-600 mt-1">Receipt vouchers settled</p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] <?= $m_due > 0 ? 'border-s-[var(--safety-orange)]' : 'border-s-emerald-600' ?> rounded-sm p-4">
          <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] block mb-1" data-en="Outstanding Balance" data-ar="الرصيد المستحق">Outstanding Balance</span>
          <div class="text-2xl font-bold font-mono tabular-nums <?= $m_due > 0 ? 'text-[var(--safety-orange)]' : 'text-[var(--ink)]' ?>">
            <?= number_format($m_due, 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono <?= $m_due > 0 ? 'text-[var(--safety-orange)]' : 'text-emerald-600' ?> mt-1">
            <?= $m_due > 0 ? 'Payment collection pending' : 'Zero balance · Fully settled' ?>
          </p>
        </div>
      </div>

      <!-- Active Contracts Quotas & Tanks Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        
        <!-- Active Quotas -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm overflow-hidden flex flex-col">
          <div class="px-5 py-3 border-b border-[var(--line)] flex items-center justify-between bg-[var(--surface-1)]">
            <div class="flex items-center gap-2">
              <?= icon('file-text', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
              <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Active Supply Quotas" data-ar="حصص التوريد النشطة">Active Supply Quotas</h2>
            </div>
            <span class="text-[10px] font-mono text-[var(--ink-muted)]"><?= count($active_quotations) ?> ALLOCATIONS</span>
          </div>
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left rtl:text-right border-collapse">
              <thead>
                <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                  <th class="px-4 py-2 font-semibold">Contract Ref</th>
                  <th class="px-4 py-2 font-semibold">Product Grade</th>
                  <th class="px-4 py-2 font-semibold text-right rtl:text-left">Unused Quota</th>
                </tr>
              </thead>
              <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                <?php if(empty($active_quotations)): ?>
                  <tr><td colspan="3" class="text-center py-6 text-[var(--ink-muted)] font-mono text-xs">No active supply contracts on file.</td></tr>
                <?php else: ?>
                  <?php foreach($active_quotations as $q): ?>
                    <?php $rem = $q['total_qty'] - $q['consumed_qty']; ?>
                    <tr class="hover:bg-[var(--surface-2)] transition-colors">
                      <td class="px-4 py-2.5 font-medium text-[var(--ink)]"><?= htmlspecialchars($q['quotation_number']) ?></td>
                      <td class="px-4 py-2.5 text-[var(--ink-muted)]"><?= htmlspecialchars($q['product_name']) ?></td>
                      <td class="px-4 py-2.5 text-right rtl:text-left font-bold tabular-nums text-[var(--cryo-teal)]">
                        <?= number_format($rem, 2) ?> <span class="text-[10px] text-[var(--ink-muted)] font-normal"><?= htmlspecialchars($q['unit']) ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Customer Tanks -->
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--frost-cyan)] rounded-sm overflow-hidden flex flex-col">
          <div class="px-5 py-3 border-b border-[var(--line)] flex items-center justify-between bg-[var(--surface-1)]">
            <div class="flex items-center gap-2">
              <?= icon('database', 'w-4 h-4 text-[var(--frost-cyan)]') ?>
              <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="On-Site Gas Storage Tanks" data-ar="خزانات الغاز الميدانية">On-Site Gas Storage Tanks</h2>
            </div>
            <span class="text-[10px] font-mono text-[var(--ink-muted)]"><?= count($client_tanks) ?> UNITS</span>
          </div>
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left rtl:text-right border-collapse">
              <thead>
                <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                  <th class="px-4 py-2 font-semibold">Tank Serial</th>
                  <th class="px-4 py-2 font-semibold">Gas Medium</th>
                  <th class="px-4 py-2 font-semibold">Level</th>
                  <th class="px-4 py-2 font-semibold text-right rtl:text-left">Volume</th>
                </tr>
              </thead>
              <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                <?php if(empty($client_tanks)): ?>
                  <tr><td colspan="4" class="text-center py-6 text-[var(--ink-muted)] font-mono text-xs">No cryogenic tanks registered.</td></tr>
                <?php else: ?>
                  <?php foreach($client_tanks as $tank): ?>
                    <?php 
                      $remaining_gas = ($tank['capacity_liters'] * $tank['current_level_pct']) / 100;
                      $status_color = $tank['current_level_pct'] <= 20 ? 'text-[var(--safety-orange)]' : ($tank['current_level_pct'] <= 50 ? 'text-amber-500' : 'text-emerald-500');
                      $bar_bg = $tank['current_level_pct'] <= 20 ? 'bg-[var(--safety-orange)]' : ($tank['current_level_pct'] <= 50 ? 'bg-amber-500' : 'bg-emerald-500');
                    ?>
                    <tr class="hover:bg-[var(--surface-2)] transition-colors">
                      <td class="px-4 py-2.5 font-medium text-[var(--ink)]"><?= htmlspecialchars($tank['serial_number']) ?></td>
                      <td class="px-4 py-2.5 text-[var(--ink-muted)]"><?= htmlspecialchars($tank['gas_type'] ?? ($tank['tank_type'] ?? 'Cryo Tank')) ?></td>
                      <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                          <div class="w-16 bg-[var(--surface-2)] border border-[var(--line)] h-1.5 rounded-xs overflow-hidden">
                            <div class="h-full <?= $bar_bg ?>" style="width: <?= $tank['current_level_pct'] ?>%;"></div>
                          </div>
                          <span class="text-[11px] font-bold <?= $status_color ?>"><?= $tank['current_level_pct'] ?>%</span>
                        </div>
                      </td>
                      <td class="px-4 py-2.5 text-right rtl:text-left font-semibold text-[var(--ink)] tabular-nums">
                        <?= number_format($remaining_gas, 2) ?> L
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Orders & Invoicing Register -->
      <div class="mb-4">
        <h2 class="text-sm font-bold font-heading uppercase tracking-tight text-[var(--ink)]" data-en="Procurement Orders & Invoicing Breakdown" data-ar="تفاصيل أوامر التوريد والفوترة">
          Procurement Orders & Invoicing Breakdown
        </h2>
        <p class="text-xs font-mono text-[var(--ink-muted)] mt-0.5">Itemized fulfillment breakdown linked with VAT tax invoices and settlement status.</p>
      </div>

      <div class="flex flex-col gap-4">
        <?php if(empty($orders)): ?>
          <div class="p-8 text-center text-xs font-mono text-[var(--ink-muted)] border border-[var(--line)] bg-[var(--surface-1)] rounded-sm">
            No purchase orders recorded for this account.
          </div>
        <?php else: ?>
          <?php foreach($orders as $o): ?>
            <?php 
              $po_total = (float)$o['total_value'];
              $po_paid = (float)$o['amount_paid'];
              $po_due = $po_total - $po_paid;
              if($po_due < 0) $po_due = 0.0;
              
              $po_invoices = $invoices_by_po[$o['order_number']] ?? [];
              $is_cancelled = ($o['delivery_status'] === 'cancelled');
            ?>
            <div class="border border-[var(--line)] bg-[var(--surface-1)] rounded-sm overflow-hidden">
              <!-- Card Header Bar -->
              <div class="px-5 py-3 border-b border-[var(--line)] flex flex-wrap justify-between items-center gap-3 bg-[var(--surface-2)]">
                <div class="flex items-center gap-3">
                  <span class="font-heading font-bold text-sm text-[var(--ink)]"><?= htmlspecialchars($o['order_number']) ?></span>
                  <?php if ($o['delivery_status'] === 'delivered'): ?>
                    <?= tag_plate('DELIVERED', 'teal') ?>
                  <?php elseif ($o['delivery_status'] === 'in_transit'): ?>
                    <?= tag_plate('IN TRANSIT', 'orange') ?>
                  <?php elseif ($is_cancelled): ?>
                    <?= tag_plate('CANCELLED', 'zinc') ?>
                  <?php else: ?>
                    <?= tag_plate(strtoupper($o['delivery_status']), 'zinc') ?>
                  <?php endif; ?>
                </div>
                <div class="flex items-center gap-5 text-xs font-mono tabular-nums">
                  <div><span class="text-[var(--ink-muted)]">Total:</span> <span class="font-bold text-[var(--ink)]"><?= number_format($po_total, 2) ?> SAR</span></div>
                  <div><span class="text-[var(--ink-muted)]">Settled:</span> <span class="font-bold text-emerald-600"><?= number_format($po_paid, 2) ?> SAR</span></div>
                  <div><span class="text-[var(--ink-muted)]">Balance:</span> <span class="font-bold <?= $po_due > 0 ? 'text-[var(--safety-orange)]' : 'text-[var(--ink-muted)]' ?>"><?= number_format($po_due, 2) ?> SAR</span></div>
                </div>
              </div>

              <!-- Card Body Grid -->
              <div class="p-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                  <!-- Items -->
                  <div>
                    <h3 class="text-[10px] font-mono font-semibold uppercase tracking-wider text-[var(--ink-muted)] mb-2">Requested Gas & Equipment Items</h3>
                    <ul class="list-none m-0 p-0 border border-[var(--line)] rounded-sm bg-[var(--surface-0)] divide-y divide-[var(--line-soft)]">
                      <?= $o['requested_items'] ?: '<li class="p-3 text-xs font-mono text-[var(--ink-muted)]">No itemized lines</li>' ?>
                    </ul>
                  </div>
                  
                  <!-- Invoices -->
                  <div>
                    <div class="flex justify-between items-center mb-2">
                      <h3 class="text-[10px] font-mono font-semibold uppercase tracking-wider text-[var(--ink-muted)]">VAT Invoices & Status</h3>
                      <span class="text-[10px] font-mono uppercase bg-[var(--surface-0)] border border-[var(--line)] px-2 py-0.5 rounded-xs text-[var(--ink)]">
                        <?= htmlspecialchars($o['payment_terms'] ?? 'Standard Terms') ?>
                      </span>
                    </div>
                    
                    <?php if(empty($po_invoices)): ?>
                      <div class="text-xs font-mono p-4 border border-dashed border-[var(--line)] rounded-sm bg-[var(--surface-0)] text-center flex flex-col items-center justify-center gap-2 text-[var(--ink-muted)]">
                        <span>No tax invoice generated for this PO yet.</span>
                        <?php if($po_total > 0 && has_permission($pdo, 'invoices.manage')): ?>
                        <form method="POST" class="m-0 no-print">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="quick_pay_po">
                          <input type="hidden" name="po_number" value="<?= htmlspecialchars($o['order_number']) ?>">
                          <input type="hidden" name="po_total" value="<?= $po_total ?>">
                          <input type="hidden" name="client_id" value="<?= $client_id ?>">
                          <button type="submit" class="btn-cta-orange px-3 py-1.5 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer" onclick="return confirm('Generate invoice and mark PO as paid?');">
                            <?= icon('zap', 'w-3.5 h-3.5') ?>
                            <span>Quick Pay & Settle</span>
                          </button>
                        </form>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div class="border border-[var(--line)] rounded-sm overflow-x-auto bg-[var(--surface-0)]">
                        <table class="w-full text-left rtl:text-right border-collapse">
                          <thead>
                            <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                              <th class="px-3 py-2 font-semibold">Invoice #</th>
                              <th class="px-3 py-2 font-semibold">Due Date</th>
                              <th class="px-3 py-2 font-semibold text-right rtl:text-left">Amount</th>
                              <th class="px-3 py-2 font-semibold text-center">Status</th>
                            </tr>
                          </thead>
                          <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                            <?php foreach($po_invoices as $inv): ?>
                              <?php 
                                $inv_status = $inv['status'];
                                if($inv['due_date'] < date('Y-m-d') && $inv_status !== 'paid') { $inv_status = 'overdue'; }
                                $new_status_to_toggle = ($inv_status === 'paid') ? 'pending' : 'paid';
                              ?>
                              <tr class="hover:bg-[var(--surface-2)] transition-colors">
                                <td class="px-3 py-2 font-medium text-[var(--ink)] whitespace-nowrap">
                                  <a href="<?= app_url('modules/crm_sales/view_invoice.php?id=' . urlencode($inv['id'])) ?>" class="text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)]">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                  </a>
                                </td>
                                <td class="px-3 py-2 text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($inv['due_date']))) ?></td>
                                <td class="px-3 py-2 text-right rtl:text-left tabular-nums text-[var(--ink)] font-semibold whitespace-nowrap"><?= number_format($inv['amount'], 2) ?></td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                  <?php if(has_permission($pdo, 'invoices.manage')): ?>
                                  <form method="POST" class="m-0 p-0 inline-block no-print">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_invoice">
                                    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $new_status_to_toggle ?>">
                                    <button type="submit" class="px-2 py-0.5 rounded-xs border text-[10px] font-mono font-semibold uppercase flex items-center gap-1 cursor-pointer transition-colors whitespace-nowrap <?= $inv_status === 'paid' ? 'border-emerald-500/60 bg-[#0d2a38] text-emerald-400' : 'border-amber-500/60 bg-[#1e231b] text-amber-400' ?>" title="Click to toggle status">
                                      <span><?= htmlspecialchars(strtoupper($inv_status)) ?></span>
                                      <?= icon('refresh-cw', 'w-2.5 h-2.5') ?>
                                    </button>
                                  </form>
                                  <?php else: ?>
                                    <?= tag_plate(strtoupper($inv_status), $inv_status === 'paid' ? 'teal' : 'orange') ?>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    function applyLanguage(lang) {
      document.querySelectorAll('[data-en]').forEach(el => {
        const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
        if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
          el.placeholder = transText;
        } else {
          el.innerText = transText; 
        }
      });
      document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
      if (lang === 'ar') {
        document.body.classList.add('arabic-mode');
      } else {
        document.body.classList.remove('arabic-mode');
      }
    }

    document.addEventListener("DOMContentLoaded", function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>
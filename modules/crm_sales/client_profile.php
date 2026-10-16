<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission($pdo, 'clients.view');

$active_page = 'clients';
$base_url  = '../../'; 

$reference_id = $_GET['id'] ?? $_SESSION['reference_id'] ?? '';
if (empty($reference_id)) {
  $firstRef = $pdo->query("SELECT reference_id FROM partners WHERE partner_type = 'client' ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstRef) {
    $reference_id = $firstRef;
  }
}

$stmt = $pdo->prepare("
  SELECT p.*, u.full_name AS sales_rep_name, u.email AS sales_rep_email, u.phone AS sales_rep_phone 
  FROM partners p 
  LEFT JOIN users u ON p.sales_rep_id = u.id 
  WHERE (p.reference_id = ? OR CAST(p.id AS CHAR) = ?) AND p.partner_type = 'client'
");
$stmt->execute([$reference_id, $reference_id]);
$db_client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_client) {
  redirect(app_url('modules/crm_sales/clients_directory.php'));
}

// Ownership / IDOR check for sales reps
if (!check_record_ownership($pdo, 'partners', (int)$db_client['id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this client profile.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_quote_id'], $_POST['quote_action'])) {
  require_csrf();
  require_permission($pdo, 'quotations.edit');

  $action_quote_id = (int)$_POST['action_quote_id'];
  $quote_action = $_POST['quote_action']; 
  
  $new_status = ($quote_action === 'accept') ? 'accepted' : 'declined';
  
  $update_stmt = $pdo->prepare("UPDATE quotations SET status = ? WHERE id = ? AND client_id = ?");
  if ($update_stmt->execute([$new_status, $action_quote_id, $db_client['id']])) {
    
    $action_text = ($quote_action === 'accept') ? "accepted" : "declined";
    $author_name = $_SESSION['user_name'] ?? 'System';
    $activity_msg = "Quotation ID-$action_quote_id was $action_text.";
    $act_stmt = $pdo->prepare("INSERT INTO client_activities (client_id, activity_text, activity_time, author) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
    $act_stmt->execute([$db_client['id'], $activity_msg, $author_name]);
    
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }
}

$words = explode(" ", $db_client['company_name'] ?? 'Client');
$initials = "";
foreach ($words as $w) {
  $initials .= mb_substr($w, 0, 1, 'UTF-8');
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'));

$stmt_quotes = $pdo->prepare("SELECT id AS db_id, quotation_number AS quote, specs, issue_date AS date, status, total_value AS value FROM quotations WHERE client_id = ? ORDER BY issue_date DESC LIMIT 5");
$stmt_quotes->execute([$db_client['id']]);
$quotations = $stmt_quotes->fetchAll(PDO::FETCH_ASSOC);

$stmt_orders = $pdo->prepare("SELECT order_number AS `order`, specs, order_date AS `date`, status, total_value AS `value` FROM purchase_orders WHERE client_id = ? ORDER BY order_date DESC LIMIT 10");
$stmt_orders->execute([$db_client['id']]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

$stmt_stats = $pdo->prepare("SELECT COUNT(*) as o_count, AVG(total_value) as o_avg FROM purchase_orders WHERE client_id = ?");
$stmt_stats->execute([$db_client['id']]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$stmt_act = $pdo->prepare("SELECT activity_text, activity_time, author FROM client_activities WHERE client_id = ? ORDER BY activity_time DESC LIMIT 10");
$stmt_act->execute([$db_client['id']]);
$activities = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

$client = [
  'id'       => $db_client['reference_id'] ?? ('CLI-' . $db_client['id']),
  'name'      => $db_client['company_name'] ?? 'Unnamed Account',
  'initials'    => $initials,
  'type'      => $db_client['entity_type'] ?? 'Corporate',
  'segment'    => $db_client['segment'] ?? 'General',
  'status'     => $db_client['status'] ?? 'active',
  'since'     => $db_client['created_at'] ?? date('Y-m-d'),
  'contact_name'  => trim(($db_client['contact_first_name'] ?? '') . ' ' . ($db_client['contact_last_name'] ?? '')) ?: 'N/A',
  'contact_role'  => $db_client['job_title'] ?? 'Procurement Officer',
  'email'     => $db_client['email'] ?? 'info@client.com',
  'phone'     => $db_client['phone'] ?? 'N/A',
  'address'    => trim(($db_client['address'] ?? '') . ', ' . ($db_client['city'] ?? '') . ', ' . ($db_client['country'] ?? 'Saudi Arabia'), ', '),
  'tax_id'     => $db_client['tax_id'] ?? '300000000000003',
  'lifetime'    => (float)($db_client['lifetime_value'] ?? 0),
  'balance'    => (float)($db_client['balance_due'] ?? 0),
  'orders_count'  => (int)($stats['o_count'] ?? 0),
  'avg_order'   => (float)($stats['o_avg'] ?? 0),
  'sales_rep_name' => $db_client['sales_rep_name'] ?? 'Not Assigned',
  'sales_rep_email'=> $db_client['sales_rep_email'] ?? '',
  'sales_rep_phone'=> $db_client['sales_rep_phone'] ?? '',
];

$breadcrumb = ['I-GAS', 'CRM & Accounts', 'Clients Directory', $client['name']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($client['name']) ?> | Customer 360 | I-GAS Enterprise</title>
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
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[var(--surface-0)] text-[var(--ink)] font-sans">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 bg-[var(--surface-0)] overflow-hidden">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar -->
    <div class="h-9 border-b border-[var(--line)] bg-[var(--surface-1)] flex items-center px-4 md:px-8 gap-3 sm:gap-4 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
      <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-[var(--ink)]">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>Account Status: <?= htmlspecialchars(strtoupper($client['status'])) ?></span>
      </span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Account ID: <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($client['id']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="text-[var(--ink-muted)] uppercase tracking-wide">Sales Officer: <span class="text-[var(--cryo-teal)] font-semibold"><?= htmlspecialchars($client['sales_rep_name']) ?></span></span>
      <?= pipe_divider() ?>
      <span class="ms-auto text-[var(--ink-muted)] uppercase tracking-wide">
        VAT ID: <?= htmlspecialchars($client['tax_id']) ?>
      </span>
    </div>

    <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 bg-grid">
      
      <!-- Breadcrumb Navigation Back Link -->
      <div class="mb-4">
        <a href="<?= app_url('modules/crm_sales/clients_directory.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-mono text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors whitespace-nowrap">
          <?= icon('arrow-left', 'w-3.5 h-3.5 rtl:rotate-180') ?>
          <span data-en="Back to Clients Directory" data-ar="العودة إلى دليل العملاء">Back to Clients Directory</span>
        </a>
      </div>

      <!-- Page Title Bar & Action Center -->
      <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-sm border border-[var(--line)] bg-[var(--surface-1)] flex items-center justify-center font-heading font-bold text-xl text-[var(--cryo-teal)] flex-shrink-0">
            <?= htmlspecialchars($client['initials']) ?>
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <?= tag_plate($client['id'], 'teal') ?>
              <span class="text-[11px] font-mono uppercase tracking-wider text-[var(--ink-muted)]"><?= htmlspecialchars($client['type']) ?> · <?= htmlspecialchars($client['segment']) ?></span>
            </div>
            <h1 class="text-2xl font-bold font-heading tracking-tight text-[var(--ink)]"><?= htmlspecialchars($client['name']) ?></h1>
          </div>
        </div>

        <!-- Primary and Secondary Actions -->
        <div class="flex flex-wrap items-center gap-2.5">
          <a href="<?= app_url('modules/crm_sales/new_calloff.php?client_id=' . urlencode($client['id'])) ?>" class="px-3.5 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center gap-1.5 transition-colors">
            <?= icon('truck', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
            <span data-en="Issue Call-off" data-ar="طلب سحب">Issue Call-off</span>
          </a>
          <?php if (has_permission($pdo, 'clients.edit')): ?>
          <a href="<?= app_url('modules/crm_sales/edit_client.php?id=' . urlencode($client['id'])) ?>" class="px-3.5 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center gap-1.5 transition-colors">
            <?= icon('edit-3', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
            <span data-en="Edit Account" data-ar="تعديل الحساب">Edit Account</span>
          </a>
          <?php endif; ?>
          <?php if (has_permission($pdo, 'quotations.create')): ?>
          <a href="<?= app_url('modules/crm_sales/new_quotation.php?client_id=' . urlencode($client['id'])) ?>" class="px-3.5 py-2 rounded-sm border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink)] text-xs font-mono flex items-center gap-1.5 transition-colors">
            <?= icon('file-text', 'w-3.5 h-3.5 text-[var(--ink-muted)]') ?>
            <span data-en="New Quote" data-ar="عرض سعر جديد">New Quote</span>
          </a>
          <?php endif; ?>
          <?php if (has_permission($pdo, 'orders.create')): ?>
          <a href="<?= app_url('modules/crm_sales/new_order.php?client_id=' . urlencode($client['id'])) ?>" class="btn-cta-orange px-4 py-2 rounded-sm text-xs font-heading font-semibold uppercase tracking-wider flex items-center gap-2 transition-colors">
            <?= icon('plus', 'w-4 h-4') ?>
            <span data-en="New Gas Order" data-ar="طلب غاز جديد">New Gas Order</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- KPI Metric Strip (4 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Lifetime Value" data-ar="القيمة الإجمالية">Lifetime Value</span>
            <?= icon('dollar-sign', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($client['lifetime'], 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1.5">Total historical revenue</p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] <?= $client['balance'] > 0 ? 'border-s-[var(--safety-orange)]' : 'border-s-emerald-600' ?> rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Balance Due" data-ar="الرصيد المستحق">Balance Due</span>
            <?= icon('clock', 'w-4 h-4 ' . ($client['balance'] > 0 ? 'text-[var(--safety-orange)]' : 'text-emerald-600')) ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums <?= $client['balance'] > 0 ? 'text-[var(--safety-orange)]' : 'text-[var(--ink)]' ?>">
            <?= number_format($client['balance'], 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono <?= $client['balance'] > 0 ? 'text-[var(--safety-orange)]' : 'text-emerald-600' ?> mt-1.5">
            <?= $client['balance'] > 0 ? 'Active receivable due' : 'Ledger cleared · Zero balance' ?>
          </p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--frost-cyan)] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Total Dispatches" data-ar="إجمالي الطلبات">Total Dispatches</span>
            <?= icon('package', 'w-4 h-4 text-[var(--frost-cyan)]') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($client['orders_count']) ?>
          </div>
          <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1.5">Purchase orders fulfilled</p>
        </div>

        <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-4 border-s-[#f97316] rounded-sm p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)]" data-en="Avg Dispatch Value" data-ar="متوسط قيمة الطلب">Avg Dispatch Value</span>
            <?= icon('trending-up', 'w-4 h-4 text-purple-600') ?>
          </div>
          <div class="text-2xl font-bold font-mono tabular-nums text-[var(--ink)]">
            <?= number_format($client['avg_order'], 2) ?>
            <span class="text-xs font-normal text-[var(--ink-muted)] font-mono">SAR</span>
          </div>
          <p class="text-[10px] font-mono text-[var(--ink-muted)] mt-1.5">Per-order procurement avg</p>
        </div>

      </div>

      <!-- Main Layout Grid: Left (Tables) & Right (Account Specs) -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        
        <!-- Left: Quotations & Orders -->
        <div class="xl:col-span-2 flex flex-col gap-6">
          
          <!-- Recent Commercial Quotations Card -->
          <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--cryo-teal)] rounded-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--line)] flex items-center justify-between">
              <div class="flex items-center gap-2">
                <?= icon('file-text', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
                <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Recent Commercial Quotations" data-ar="عروض الأسعار الأخيرة">Recent Commercial Quotations</h2>
              </div>
              <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="text-[11px] font-mono text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)] transition-colors flex items-center gap-1">
                <span data-en="View All" data-ar="عرض الكل">View All</span>
                <?= icon('arrow-right', 'w-3 h-3 rtl:rotate-180') ?>
              </a>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-left rtl:text-right border-collapse">
                <thead>
                  <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                    <th class="px-4 py-2.5 font-semibold">Quote Reference</th>
                    <th class="px-4 py-2.5 font-semibold">Gas Specification</th>
                    <th class="px-4 py-2.5 font-semibold">Issue Date</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left">Value (SAR)</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left">Actions</th>
                  </tr>
                </thead>
                <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                  <?php if (empty($quotations)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-6 text-[var(--ink-muted)] font-mono text-xs">
                      No commercial quotations on file for this client.
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                    <tr class="hover:bg-[var(--surface-2)] transition-colors">
                      <td class="px-4 py-3 font-semibold text-[var(--ink)] whitespace-nowrap">
                        <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($q['db_id'])) ?>" class="text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)]">
                          <?= htmlspecialchars($q['quote']) ?>
                        </a>
                      </td>
                      <td class="px-4 py-3 text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars($q['specs'] ?? 'Industrial Standard') ?></td>
                      <td class="px-4 py-3 text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($q['date']))) ?></td>
                      <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($q['status'] === 'accepted'): ?>
                          <?= tag_plate('ACCEPTED', 'teal') ?>
                        <?php elseif ($q['status'] === 'declined'): ?>
                          <?= tag_plate('DECLINED', 'orange') ?>
                        <?php else: ?>
                          <?= tag_plate(strtoupper($q['status']), 'zinc') ?>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-3 text-right rtl:text-left font-semibold text-[var(--ink)] tabular-nums">
                        <?= number_format((float)$q['value'], 2) ?>
                      </td>
                      <td class="px-4 py-3 text-right rtl:text-left">
                        <div class="flex items-center justify-end gap-1.5">
                          <?php if (in_array($q['status'], ['draft', 'sent']) && has_permission($pdo, 'quotations.edit')): ?>
                          <form method="POST" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_quote_id" value="<?= htmlspecialchars($q['db_id']) ?>">
                            <button type="submit" name="quote_action" value="accept" class="p-1 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] hover:bg-emerald-50 text-emerald-600 transition-colors cursor-pointer" title="Accept Quote">
                              <?= icon('check', 'w-3.5 h-3.5') ?>
                            </button>
                          </form>
                          <form method="POST" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_quote_id" value="<?= htmlspecialchars($q['db_id']) ?>">
                            <button type="submit" name="quote_action" value="decline" class="p-1 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] hover:bg-rose-950/30 text-rose-600 transition-colors cursor-pointer" title="Decline Quote">
                              <?= icon('x', 'w-3.5 h-3.5') ?>
                            </button>
                          </form>
                          <?php endif; ?>
                          <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode($q['db_id'])) ?>" class="p-1 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors" title="View Document">
                            <?= icon('eye', 'w-3.5 h-3.5') ?>
                          </a>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Order History Register Card -->
          <div class="card-accent-rule bg-[var(--surface-1)] border border-[var(--line)] border-s-[3px] border-s-[var(--frost-cyan)] rounded-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--line)] flex items-center justify-between">
              <div class="flex items-center gap-2">
                <?= icon('truck', 'w-4 h-4 text-[var(--frost-cyan)]') ?>
                <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Purchase & Dispatch Orders" data-ar="سجل أوامر التوريد والتوصيل">Purchase & Dispatch Orders</h2>
              </div>
              <a href="<?= app_url('modules/crm_sales/purchase_orders.php') ?>" class="text-[11px] font-mono text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)] transition-colors flex items-center gap-1">
                <span data-en="View All" data-ar="عرض الكل">View All</span>
                <?= icon('arrow-right', 'w-3 h-3 rtl:rotate-180') ?>
              </a>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-left rtl:text-right border-collapse">
                <thead>
                  <tr class="text-[10px] font-mono uppercase tracking-wider text-[var(--ink-muted)] bg-[var(--surface-2)] border-b border-[var(--line)]">
                    <th class="px-4 py-2.5 font-semibold">Order Number</th>
                    <th class="px-4 py-2.5 font-semibold">Gas Specs / Volume</th>
                    <th class="px-4 py-2.5 font-semibold">Dispatch Date</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left">Value (SAR)</th>
                    <th class="px-4 py-2.5 font-semibold text-right rtl:text-left">View</th>
                  </tr>
                </thead>
                <tbody class="text-xs font-mono divide-y divide-[var(--line-soft)]">
                  <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-6 text-[var(--ink-muted)] font-mono text-xs">
                      No purchase orders recorded for this account.
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <tr class="hover:bg-[var(--surface-2)] transition-colors">
                      <td class="px-4 py-3 font-semibold text-[var(--ink)] whitespace-nowrap">
                        <a href="<?= app_url('modules/crm_sales/view_order.php?order_num=' . urlencode($o['order'])) ?>" class="text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)]">
                          <?= htmlspecialchars($o['order']) ?>
                        </a>
                      </td>
                      <td class="px-4 py-3 text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars($o['specs'] ?? 'Industrial Cylinder Lot') ?></td>
                      <td class="px-4 py-3 text-[var(--ink-muted)] whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($o['date']))) ?></td>
                      <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($o['status'] === 'delivered'): ?>
                          <?= tag_plate('DELIVERED', 'teal') ?>
                        <?php elseif ($o['status'] === 'in_transit'): ?>
                          <?= tag_plate('IN TRANSIT', 'orange') ?>
                        <?php else: ?>
                          <?= tag_plate(strtoupper($o['status']), 'zinc') ?>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-3 text-right rtl:text-left font-semibold text-[var(--ink)] tabular-nums">
                        <?= number_format((float)$o['value'], 2) ?>
                      </td>
                      <td class="px-4 py-3 text-right rtl:text-left">
                        <a href="<?= app_url('modules/crm_sales/view_order.php?order_num=' . urlencode($o['order'])) ?>" class="p-1 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] hover:bg-[var(--surface-2)] text-[var(--ink-muted)] hover:text-[var(--ink)] transition-colors inline-block" title="Manifest">
                          <?= icon('eye', 'w-3.5 h-3.5') ?>
                        </a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- Right: Account Details & Telemetry Timeline -->
        <div class="flex flex-col gap-6">
          
          <!-- Account Specification Sheet -->
          <div class="card-notched bg-[var(--surface-1)] border border-[var(--line)] rounded-sm p-5">
            <div class="flex items-center justify-between pb-3 border-b border-[var(--line)] mb-4">
              <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Account Specifications" data-ar="مواصفات الحساب">Account Specifications</h2>
              <?= icon('building-2', 'w-4 h-4 text-[var(--cryo-teal)]') ?>
            </div>

            <div class="flex flex-col gap-3 text-xs font-mono">
              <div class="pb-2 border-b border-[var(--line-soft)]">
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">Assigned Sales Officer</span>
                <span class="text-[var(--ink)] font-semibold text-xs"><?= htmlspecialchars($client['sales_rep_name']) ?></span>
                <?php if (!empty($client['sales_rep_email'])): ?>
                  <p class="text-[11px] text-[var(--ink-muted)] mt-0.5"><?= htmlspecialchars($client['sales_rep_email']) ?></p>
                <?php endif; ?>
              </div>

              <div class="pb-2 border-b border-[var(--line-soft)]">
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">Primary Technical Contact</span>
                <span class="text-[var(--ink)] font-semibold text-xs"><?= htmlspecialchars($client['contact_name']) ?></span>
                <p class="text-[11px] text-[var(--ink-muted)] mt-0.5"><?= htmlspecialchars($client['contact_role']) ?></p>
              </div>

              <div class="pb-2 border-b border-[var(--line-soft)]">
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">Communications</span>
                <span class="text-[var(--ink)] font-medium block"><?= htmlspecialchars($client['email']) ?></span>
                <span class="text-[var(--ink)] font-medium block mt-0.5"><?= htmlspecialchars($client['phone']) ?></span>
              </div>

              <div class="pb-2 border-b border-[var(--line-soft)]">
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">Delivery Facility Address</span>
                <span class="text-[var(--ink)]"><?= htmlspecialchars($client['address']) ?></span>
              </div>

              <div class="pb-2 border-b border-[var(--line-soft)]">
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">ZATCA Tax Identification</span>
                <span class="text-[var(--ink)] font-semibold"><?= htmlspecialchars($client['tax_id']) ?></span>
              </div>

              <div>
                <span class="text-[10px] text-[var(--ink-muted)] uppercase block">Customer Inception Date</span>
                <span class="text-[var(--ink)]"><?= htmlspecialchars(date('d F Y', strtotime($client['since']))) ?></span>
              </div>
            </div>
          </div>

          <!-- Activity & Telemetry Timeline -->
          <div class="bg-[var(--surface-1)] border border-[var(--line)] rounded-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-[var(--line)] flex items-center justify-between">
              <h2 class="text-xs font-mono font-semibold uppercase tracking-wider text-[var(--ink)]" data-en="Activity Log" data-ar="سجل الأنشطة">Activity Log</h2>
              <span class="text-[10px] font-mono text-[var(--ink-muted)]"><?= count($activities) ?> ENTRIES</span>
            </div>

            <div class="p-4 flex flex-col gap-3">
              <?php if (empty($activities)): ?>
                <p class="text-center py-4 text-[11px] font-mono text-[var(--ink-muted)]" data-en="No recent interaction telemetry." data-ar="لا توجد أنشطة مسجلة مؤخراً.">No recent interaction telemetry.</p>
              <?php else: ?>
                <?php foreach ($activities as $act): ?>
                <div class="p-2.5 rounded-xs border border-[var(--line)] bg-[var(--surface-0)] text-xs font-mono">
                  <p class="text-[var(--ink)] font-medium leading-snug"><?= htmlspecialchars($act['activity_text']) ?></p>
                  <div class="flex items-center justify-between mt-1 text-[10px] text-[var(--ink-muted)]">
                    <span><?= htmlspecialchars($act['author']) ?></span>
                    <span><?= date('d M · H:i', strtotime($act['activity_time'])) ?></span>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="px-4 py-2.5 border-t border-[var(--line)] bg-[var(--surface-2)] text-center">
              <a href="<?= app_url('modules/crm_sales/client_activity.php?client_id=' . urlencode($client['id'])) ?>" class="text-[11px] font-mono text-[var(--cryo-teal)] hover:text-[var(--frost-cyan)] transition-colors" data-en="Open Full Interaction Log" data-ar="فتح سجل الأنشطة الكامل">
                Open Full Interaction Log →
              </a>
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

    document.addEventListener('DOMContentLoaded', function() {
      const savedLang = localStorage.getItem('app_lang') || 'en';
      applyLanguage(savedLang);
    });
  </script>
</body>
</html>
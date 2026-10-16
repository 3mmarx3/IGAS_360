<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'quotations.view');

$quotation_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$quotation_id || $quotation_id <= 0) {
  $firstId = (int)$pdo->query("SELECT id FROM quotations ORDER BY id DESC LIMIT 1")->fetchColumn();
  if ($firstId > 0) {
    $quotation_id = $firstId;
  } else {
    redirect(app_url('modules/crm_sales/quotations.php'));
  }
}

$stmt = $pdo->prepare("
  SELECT q.*, p.company_name, p.email, p.phone, p.address, p.city, p.country, p.cr_number, p.tax_id,
      p.contact_first_name, p.contact_last_name,
      u.full_name AS sales_rep_name, u.email AS sales_rep_email, u.phone AS sales_rep_phone, u.role AS sales_rep_role
  FROM quotations q 
  LEFT JOIN partners p ON q.client_id = p.id 
  LEFT JOIN users u ON q.sales_rep_id = u.id
  WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
  redirect(app_url('modules/crm_sales/quotations.php'));
}

// Ownership / IDOR check for sales reps
if (!is_admin() && !check_record_ownership($pdo, 'partners', (int)$quote['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this quotation.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  require_csrf();
  require_permission($pdo, 'quotations.edit');

  $action = $_POST['action'];
  if (in_array($action, ['accepted', 'declined'])) {
    $stmt_update = $pdo->prepare("UPDATE quotations SET status = ? WHERE id = ?");
    $stmt_update->execute([$action, $quotation_id]);
    redirect(app_url('modules/crm_sales/view_quotation.php?id=' . urlencode((string)$quotation_id)));
  }
}

$stmt_items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt_items->execute([$quotation_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$logistics = ['Delivery' => 'I-GAS Fleet Dispatch', 'Address' => $quote['address'] ?? 'Customer Site', 'Terms' => 'Net 30 Days'];
$raw_notes = '';

if (!empty($quote['notes'])) {
  $parts = explode('|', (string)$quote['notes']);
  foreach ($parts as $part) {
    $split = explode(':', trim($part), 2);
    if (count($split) === 2) {
      $key = trim($split[0]);
      $val = trim($split[1]);
      if (array_key_exists($key, $logistics)) {
        $logistics[$key] = $val;
      } else {
        $raw_notes .= $part . "\n";
      }
    } else {
      $raw_notes .= $part . "\n";
    }
  }
}

$active_page = 'quotations';
$breadcrumb = ['I-GAS', 'Sales & Quotations', 'A4 Document View'];

$st = strtolower($quote['status'] ?? 'draft');
$statusConfigs = [
  'draft'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'label' => 'DRAFT / ESTIMATE'],
  'sent'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'label' => 'PENDING CLIENT APPROVAL'],
  'accepted' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'label' => 'FORMALLY ACCEPTED'],
  'declined' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'label' => 'PROPOSAL DECLINED'],
  'expired' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'label' => 'PROPOSAL EXPIRED'],
];
$sc = $statusConfigs[$st] ?? $statusConfigs['draft'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($quote['quotation_number']) ?> | Formal Quotation | I-GAS</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="<?= app_url('assets/css/theme.css') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    @media print {
      body { background: white !important; color: black !important; }
      aside, header, .no-print { display: none !important; }
      main { padding: 0 !important; margin: 0 !important; height: auto !important; overflow: visible !important; }
      .a4-document { border: none !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

  <?php include __DIR__ . '/../../includes/aside.php'; ?>

  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <!-- Telemetry Status Bar (no-print) -->
    <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 sm:px-6 flex items-center justify-between text-xs font-mono text-slate-400 no-print">
      <div class="flex items-center gap-4 overflow-x-auto no-scrollbar">
        <span class="flex items-center gap-1.5 font-medium text-teal-400 dark:text-teal-400">
          <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
          <span data-en="DOCUMENT VIEWER" data-ar="عارض المستندات">DOCUMENT VIEWER</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="FORMAL A4 PROPOSAL" data-ar="عرض سعر رسمي A4">FORMAL A4 PROPOSAL</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($quote['quotation_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 w-full mx-auto">
      <!-- Action Toolbar (no-print) -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="p-2 bg-[#0c1924] border border-[#1e3a50] rounded-md text-slate-300 hover:border-neutral-900 dark:hover:border-white transition-colors" title="Back to Register">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          </a>
          <div>
            <h2 class="text-lg font-bold font-mono tracking-tight text-white">
              <?= htmlspecialchars($quote['quotation_number']) ?>
            </h2>
            <p class="text-xs text-neutral-500 font-mono">Formal Commercial Proposal Specification Sheet</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/quotation_details.php?id=' . urlencode((string)$quote['id'])) ?>" class="px-3.5 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors inline-flex items-center gap-1.5">
            <i data-lucide="sliders" class="w-3.5 h-3.5"></i>
            <span data-en="Specs &amp; Edit" data-ar="المواصفات والتعديل">Specs &amp; Edit</span>
          </a>

          <!-- Single Primary CTA: Safety Valve Orange -->
          <button onclick="window.print()" class="btn-cta-orange px-4 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all cursor-pointer">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span data-en="Print / Export PDF" data-ar="طباعة / تصدير PDF">Print / Export PDF</span>
          </button>

          <?php if (in_array($st, ['draft', 'sent']) && has_permission($pdo, 'quotations.edit')): ?>
            <form method="POST" action="" class="m-0 p-0 inline-block">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="accepted">
              <button type="submit" class="px-3.5 py-2 text-xs font-semibold rounded-md border border-teal-600 bg-teal-950/30 dark:bg-teal-950/40 text-teal-400 dark:text-teal-300 hover:bg-teal-100 transition-colors inline-flex items-center gap-1.5 cursor-pointer" onclick="return confirm('Accept this quotation?');">
                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                <span data-en="Accept" data-ar="قبول">Accept</span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Formal A4 Industrial Document -->
      <div class="a4-document bg-[#0c1924] border border-[#1e3a50] rounded-md overflow-hidden">
        <!-- Document Header Section -->
        <div class="p-8 sm:p-10 border-b border-[#1a3346] bg-[#09151f] flex flex-col md:flex-row md:items-start justify-between gap-6">
          <!-- Brand & Plant Data -->
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 bg-[#102230] border border-cyan-500/40 text-cyan-400 flex items-center justify-center font-mono font-bold text-xl rounded">
                IG
              </div>
              <div>
                <h1 class="text-xl font-bold font-mono tracking-tight text-white uppercase">
                  <?= !empty($quote['company_name_doc']) ? htmlspecialchars($quote['company_name_doc']) : 'I-GAS Enterprise' ?>
                </h1>
                <p class="text-xs font-mono text-neutral-500 uppercase tracking-widest">Industrial &amp; Medical Gases Plant</p>
              </div>
            </div>

            <div class="text-xs font-mono text-slate-400 space-y-0.5 leading-relaxed">
              <p>Phase 2, Heavy Industrial District, Jeddah, KSA</p>
              <p>CR No: 4030129881 • Plant License: IG-JED-04</p>
              <p>VAT Registration: 300123456700003</p>
              <p>Support: commercial@i-gas.sa • +966 12 345 6789</p>
            </div>
          </div>

          <!-- Proposal Metadata Block -->
          <div class="flex flex-col items-start md:items-end space-y-2 text-start md:text-end">
            <span class="px-3 py-1 text-xs font-mono font-bold uppercase rounded border <?= $sc['border'] ?> <?= $sc['bg'] ?> <?= $sc['text'] ?>">
              <?= $sc['label'] ?>
            </span>

            <div class="pt-2">
              <span class="text-xs font-mono text-neutral-400 uppercase tracking-wider block">Commercial Proposal Ref:</span>
              <span class="text-2xl font-bold font-mono text-white"><?= htmlspecialchars($quote['quotation_number']) ?></span>
            </div>

            <div class="text-xs font-mono text-slate-400 space-y-1 pt-1">
              <p><span class="text-neutral-400 uppercase">Issue Date:</span> <span class="font-bold text-slate-200"><?= date('d M Y', strtotime($quote['issue_date'])) ?></span></p>
              <p><span class="text-neutral-400 uppercase">Valid Until:</span> <span class="font-bold text-slate-200"><?= date('d M Y', strtotime($quote['expiry_date'])) ?></span></p>
              <?php if (!empty($quote['sales_rep_name'])): ?>
                <p><span class="text-neutral-400 uppercase">Account Rep:</span> <?= htmlspecialchars($quote['sales_rep_name']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Document Body -->
        <div class="p-8 sm:p-10 space-y-8">
          <!-- Client & Specification Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8 border-b border-[#1a3346] pb-8">
            <div>
              <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-neutral-400 block mb-2 border-b border-neutral-100 border-[#1a3346] pb-1" data-en="Client Commercial Account" data-ar="حساب العميل التجاري">
                Client Commercial Account
              </span>
              <h3 class="text-base font-bold text-white mb-1">
                <?= htmlspecialchars($quote['company_name'] ?? 'Client') ?>
              </h3>
              <div class="text-xs font-mono text-slate-400 space-y-1">
                <p><span class="text-neutral-400">Attn:</span> <?= htmlspecialchars(trim(($quote['contact_first_name'] ?? '') . ' ' . ($quote['contact_last_name'] ?? ''))) ?></p>
                <p><?= htmlspecialchars($quote['address'] ?? '') ?>, <?= htmlspecialchars($quote['city'] ?? '') ?></p>
                <p>CR: <?= htmlspecialchars($quote['cr_number'] ?? '—') ?> • VAT: <?= htmlspecialchars($quote['tax_id'] ?? '—') ?></p>
                <p>Email: <?= htmlspecialchars($quote['email'] ?? '—') ?> • Phone: <?= htmlspecialchars($quote['phone'] ?? '—') ?></p>
              </div>
            </div>

            <div>
              <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-neutral-400 block mb-2 border-b border-neutral-100 border-[#1a3346] pb-1" data-en="Technical & Delivery Parameters" data-ar="المعايير الفنية والتسليم">
                Technical &amp; Delivery Parameters
              </span>
              <div class="text-xs font-mono text-slate-400 space-y-1.5">
                <p><span class="text-neutral-400 uppercase">Mechanism:</span> <span class="font-medium text-slate-200"><?= htmlspecialchars($logistics['Delivery']) ?></span></p>
                <p><span class="text-neutral-400 uppercase">Destination:</span> <span class="font-medium text-slate-200"><?= htmlspecialchars($logistics['Address']) ?></span></p>
                <p><span class="text-neutral-400 uppercase">Payment SLA:</span> <span class="font-medium text-slate-200"><?= htmlspecialchars($logistics['Terms']) ?></span></p>
                <p><span class="text-neutral-400 uppercase">Quality Spec:</span> <span class="font-medium text-slate-200"><?= htmlspecialchars($quote['specs'] ?: 'EN 10204 3.1 Certified Industrial Purity') ?></span></p>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="overflow-x-auto">
            <table class="w-full text-start text-xs border-collapse">
              <thead>
                <tr class="bg-[#09151f] border-b border-[#1e3a50] text-neutral-600 text-slate-300 font-mono uppercase">
                  <th class="py-3 px-4 text-center w-12">#</th>
                  <th class="py-3 px-6 text-start" data-en="Gas Product / Specification" data-ar="مواصفة الغاز / المنتج">Gas Product / Specification</th>
                  <th class="py-3 px-4 text-end" data-en="Qty" data-ar="الكمية">Qty</th>
                  <th class="py-3 px-4 text-start" data-en="Unit" data-ar="الوحدة">Unit</th>
                  <th class="py-3 px-4 text-end" data-en="Unit Rate (SAR)" data-ar="سعر الوحدة">Unit Rate (SAR)</th>
                  <th class="py-3 px-6 text-end" data-en="Amount (SAR)" data-ar="المبلغ الإجمالي">Amount (SAR)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a] font-mono">
                <?php if (empty($items)): ?>
                  <tr>
                    <td colspan="6" class="py-6 text-center text-neutral-400 font-sans">
                      No line items specified for this quotation.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($items as $idx => $it): ?>
                    <tr>
                      <td class="py-3.5 px-4 text-center text-neutral-400"><?= $idx + 1 ?></td>
                      <td class="py-3.5 px-6 font-sans">
                        <div class="font-bold text-white flex items-center gap-2">
                          <span><?= htmlspecialchars($it['product_name']) ?></span>
                          <?= gas_chip($it['product_name']) ?>
                        </div>
                      </td>
                      <td class="py-3.5 px-4 text-end font-bold text-white"><?= number_format((float)($it['qty'] ?? 0), 2) ?></td>
                      <td class="py-3.5 px-4 text-neutral-500"><?= htmlspecialchars($it['unit'] ?? 'Cylinders') ?></td>
                      <td class="py-3.5 px-4 text-end text-slate-200"><?= number_format((float)($it['unit_price'] ?? 0), 2) ?></td>
                      <td class="py-3.5 px-6 text-end font-bold text-white"><?= number_format((float)($it['line_total'] ?? 0), 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Commercial Totals & Signature Block -->
          <div class="grid grid-cols-1 md:grid-cols-12 gap-8 pt-4">
            <!-- Terms & Conditions Text -->
            <div class="md:col-span-7 space-y-4">
              <span class="text-[11px] font-mono font-bold uppercase tracking-wider text-neutral-400 block border-b border-neutral-100 border-[#1a3346] pb-1" data-en="Standard Plant Terms of Supply" data-ar="الشروط القياسية لتوريد الغازات">
                Standard Plant Terms of Supply
              </span>
              <ul class="text-[11px] font-mono text-neutral-500 space-y-1 list-disc list-inside leading-relaxed">
                <li>Prices are quoted in Saudi Riyals (SAR) and valid through designated expiry date.</li>
                <li>All cylinders remain property of I-GAS under standard deposit/demurrage rules.</li>
                <li>Pressure test certificate ISO-9809 provided upon shipment dispatch.</li>
                <li>Gas analysis Certificate of Analysis (CoA) included with each lot.</li>
              </ul>

              <!-- Official Authorization Stamp Box -->
              <div class="pt-8 flex items-center gap-8">
                <div class="border border-[#1e3a50] rounded p-4 text-center w-52">
                  <div class="h-14 flex items-center justify-center font-mono text-xs text-neutral-400 italic">
                    [ Digital Plant Seal ]
                  </div>
                  <div class="border-t border-[#1a3346] pt-1 text-[10px] font-mono text-neutral-500 uppercase">
                    Authorized Signatory
                  </div>
                </div>

                <div class="text-xs font-mono text-neutral-400 space-y-0.5">
                  <p>I-GAS Plant Commercial Operations</p>
                  <p>Document Hash: <?= substr(hash('sha256', (string)$quote['id'] . $quote['quotation_number']), 0, 16) ?></p>
                  <p>Verification Code: VER-<?= (int)$quote['id'] ?>-98</p>
                </div>
              </div>
            </div>

            <!-- Calculation Box -->
            <div class="md:col-span-5 bg-[#09151f] border border-[#1a3346] rounded p-5 space-y-3 text-xs font-mono">
              <div class="flex justify-between items-center py-1 border-b border-[#1e3a50]">
                <span class="text-neutral-500" data-en="Taxable Subtotal:" data-ar="المجموع الفرعي:">Taxable Subtotal:</span>
                <span class="font-medium text-white"><?= number_format((float)($quote['subtotal'] ?? 0), 2) ?> SAR</span>
              </div>
              <div class="flex justify-between items-center py-1 border-b border-[#1e3a50]">
                <span class="text-neutral-500" data-en="Transport / Freight Fee:" data-ar="أجور النقل:">Transport / Freight Fee:</span>
                <span class="font-medium text-white"><?= number_format((float)($quote['delivery_fee'] ?? 0), 2) ?> SAR</span>
              </div>
              <div class="flex justify-between items-center py-1 border-b border-[#1e3a50]">
                <span class="text-neutral-500" data-en="Value Added Tax (15% VAT):" data-ar="ضريبة القيمة المضافة (15%):">Value Added Tax (15% VAT):</span>
                <span class="font-medium text-white"><?= number_format((float)($quote['vat_amount'] ?? 0), 2) ?> SAR</span>
              </div>
              <div class="flex justify-between items-center pt-3 border-t-2 border-neutral-900 dark:border-neutral-100">
                <span class="font-bold text-sm text-white uppercase font-sans" data-en="Grand Total:" data-ar="الإجمالي النهائي:">Grand Total:</span>
                <span class="text-xl font-bold font-mono text-white"><?= number_format((float)($quote['total_value'] ?? 0), 2) ?> SAR</span>
              </div>
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
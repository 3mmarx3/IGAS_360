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
  SELECT q.*, p.company_name, p.email, p.phone, p.address, p.city, p.country, p.cr_number, p.tax_id 
  FROM quotations q 
  LEFT JOIN partners p ON q.client_id = p.id 
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
    redirect(app_url('modules/crm_sales/quotation_details.php?id=' . urlencode((string)$quotation_id)));
  }
}

$stmt_items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt_items->execute([$quotation_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$sales_reps_info = [];
if (!empty($quote['sales_rep_id'])) {
  $rep_ids = explode(',', (string)$quote['sales_rep_id']);
  $rep_ids = array_filter(array_map('intval', $rep_ids));
  if (!empty($rep_ids)) {
    $placeholders = implode(',', array_fill(0, count($rep_ids), '?'));
    $stmt_reps = $pdo->prepare("SELECT full_name, role FROM users WHERE id IN ($placeholders)");
    $stmt_reps->execute($rep_ids);
    $sales_reps_info = $stmt_reps->fetchAll(PDO::FETCH_ASSOC);
  }
}

$active_page = 'quotations';
$breadcrumb = ['I-GAS', 'Sales & Quotations', 'Document Details'];

$st = strtolower($quote['status'] ?? 'draft');
$statusConfigs = [
  'draft'  => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'dot' => 'bg-neutral-400', 'label' => 'Draft', 'label_ar' => 'مسودة'],
  'sent'   => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label' => 'Sent / Pending', 'label_ar' => 'مرسل / معلق'],
  'accepted' => ['border' => 'border-teal-500/30', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-400 dark:text-teal-400', 'dot' => 'bg-teal-500', 'label' => 'Accepted', 'label_ar' => 'مقبول'],
  'declined' => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label' => 'Declined', 'label_ar' => 'مرفوض'],
  'expired' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-neutral-500', 'dot' => 'bg-neutral-400', 'label' => 'Expired', 'label_ar' => 'منتهي'],
];
$sc = $statusConfigs[$st] ?? $statusConfigs['draft'];

$words = preg_split('/\s+/', trim((string)($quote['company_name'] ?? '')));
$initials = '';
if (count($words) >= 2) {
  $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
} elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
  $initials = strtoupper(mb_substr($words[0], 0, 2));
}
$initials = $initials ?: 'QT';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($quote['quotation_number']) ?> Details | I-GAS Enterprise</title>
  
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
        <span data-en="SPECIFICATION AUDIT" data-ar="تدقيق المواصفات">SPECIFICATION AUDIT</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($quote['quotation_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Navigation Back & Quotation ID -->
      <div class="flex items-center justify-between">
        <a href="<?= app_url('modules/crm_sales/quotations.php') ?>" class="inline-flex items-center gap-2 text-xs font-mono text-neutral-500 hover:text-teal-400 dark:hover:text-teal-400 transition-colors">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Quotations Register" data-ar="العودة لسجل عروض الأسعار">Back to Quotations Register</span>
        </a>
        <span class="text-xs font-mono text-neutral-400">DOC ID #<?= (int)$quote['id'] ?></span>
      </div>

      <!-- Page Header Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 border-s-4 border-s-[#f97316]">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-md border border-[#1e3a50] bg-[#09151f] flex items-center justify-center font-mono font-bold text-lg text-slate-200 shrink-0">
            <?= htmlspecialchars($initials) ?>
          </div>
          <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="font-mono text-2xl font-bold tracking-tight text-white">
                <?= htmlspecialchars($quote['quotation_number']) ?>
              </h1>
              <span class="px-2.5 py-0.5 text-xs font-mono font-medium rounded-full border <?= $sc['border'] ?> <?= $sc['bg'] ?> <?= $sc['text'] ?> inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                <span data-en="<?= $sc['label'] ?>" data-ar="<?= $sc['label_ar'] ?>"><?= $sc['label'] ?></span>
              </span>
            </div>
            <p class="text-sm text-slate-400 flex items-center gap-2">
              <a href="<?= app_url('modules/crm_sales/client_profile.php?id=' . urlencode((string)$quote['client_id'])) ?>" class="font-medium text-white hover:text-teal-600 hover:underline">
                <?= htmlspecialchars($quote['company_name'] ?? 'Client') ?>
              </a>
              <span class="text-neutral-300 dark:text-neutral-700">•</span>
              <span class="font-mono text-xs text-neutral-500"><?= htmlspecialchars($quote['city'] ?? '') ?>, <?= htmlspecialchars($quote['country'] ?? 'KSA') ?></span>
            </p>
          </div>
        </div>

        <!-- Action Controls -->
        <div class="flex flex-wrap items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/view_quotation.php?id=' . urlencode((string)$quote['id'])) ?>" class="px-3.5 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors inline-flex items-center gap-2">
            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            <span data-en="Formal Print / PDF" data-ar="طباعة رسمية / PDF">Formal Print / PDF</span>
          </a>

          <?php if (in_array($st, ['draft', 'sent']) && has_permission($pdo, 'quotations.edit')): ?>
            <form method="POST" action="" class="m-0 p-0 inline-block">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="declined">
              <button type="submit" class="px-3.5 py-2 text-xs font-medium border border-rose-800 dark:border-rose-900 hover:border-rose-600 rounded-md text-rose-600 hover:bg-rose-950/30 dark:hover:bg-rose-950/40 transition-colors inline-flex items-center gap-1.5" onclick="return confirm('Decline this proposal?');">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                <span data-en="Decline" data-ar="رفض">Decline</span>
              </button>
            </form>

            <!-- Single Primary CTA: Safety Valve Orange -->
            <form method="POST" action="" class="m-0 p-0 inline-block">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="accepted">
              <button type="submit" class="btn-cta-orange px-4 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all cursor-pointer" onclick="return confirm('Mark this proposal as accepted by client?');">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span data-en="Mark Accepted" data-ar="قبول واعتماد العرض">Mark Accepted</span>
              </button>
            </form>
          <?php elseif ($st === 'accepted'): ?>
            <a href="<?= app_url('modules/crm_sales/new_order.php?quotation_id=' . urlencode((string)$quote['id'])) ?>" class="btn-cta-orange px-4 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
              <i data-lucide="shopping-cart" class="w-4 h-4"></i>
              <span data-en="Generate Sales Order" data-ar="إنشاء أمر بيع">Generate Sales Order</span>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Two-Column Specifications & Details -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Client Identification (2 cols) -->
        <div class="lg:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
          <h2 class="text-sm font-semibold text-white mb-1" data-en="Client Commercial & Tax Information" data-ar="البيانات التجارية والضريبية للعميل">
            Client Commercial & Tax Information
          </h2>
          <p class="text-xs text-neutral-500 mb-5" data-en="Registered commercial registration, corporate entity, and contact site." data-ar="السجل التجاري والكيان المؤسسي وموقع الاتصال.">
            Registered commercial registration, corporate entity, and contact site.
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
            <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
              <span class="text-neutral-400 block mb-1 uppercase" data-en="Company Name" data-ar="اسم الشركة">Company Name</span>
              <span class="font-sans font-bold text-white text-sm"><?= htmlspecialchars($quote['company_name'] ?? 'N/A') ?></span>
            </div>
            <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
              <span class="text-neutral-400 block mb-1 uppercase" data-en="CR Number" data-ar="السجل التجاري">CR Number</span>
              <span class="font-bold text-white"><?= htmlspecialchars($quote['cr_number'] ?? 'N/A') ?></span>
            </div>
            <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
              <span class="text-neutral-400 block mb-1 uppercase" data-en="Contact Email" data-ar="البريد الإلكتروني">Contact Email</span>
              <span class="text-slate-200"><?= htmlspecialchars($quote['email'] ?? 'N/A') ?></span>
            </div>
            <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
              <span class="text-neutral-400 block mb-1 uppercase" data-en="Phone" data-ar="رقم الهاتف">Phone</span>
              <span class="text-slate-200"><?= htmlspecialchars($quote['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="sm:col-span-2 p-3 rounded bg-[#09151f] border border-[#1a3346]">
              <span class="text-neutral-400 block mb-1 uppercase" data-en="Plant Address / Site Location" data-ar="عنوان المنشأة / الموقع">Plant Address / Site Location</span>
              <span class="text-slate-200 font-sans"><?= htmlspecialchars(trim(($quote['address'] ?? '') . ', ' . ($quote['city'] ?? '') . ', ' . ($quote['country'] ?? ''))) ?></span>
            </div>
          </div>
        </div>

        <!-- Right: Document Dates & Handlers (1 col) -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316] space-y-4 text-xs font-mono">
          <h3 class="text-sm font-semibold text-white font-sans" data-en="Document Parameters" data-ar="معايير الوثيقة">
            Document Parameters
          </h3>

          <div class="flex justify-between py-2 border-b border-neutral-100 border-[#1a3346]">
            <span class="text-neutral-500" data-en="Issue Date:" data-ar="تاريخ الإصدار:">Issue Date:</span>
            <span class="font-bold text-white"><?= !empty($quote['issue_date']) ? htmlspecialchars(date('d M Y', strtotime($quote['issue_date']))) : '—' ?></span>
          </div>

          <div class="flex justify-between py-2 border-b border-neutral-100 border-[#1a3346]">
            <span class="text-neutral-500" data-en="Validity Expiry:" data-ar="انتهاء الصلاحية:">Validity Expiry:</span>
            <span class="font-bold text-rose-600 dark:text-rose-400"><?= !empty($quote['expiry_date']) ? htmlspecialchars(date('d M Y', strtotime($quote['expiry_date']))) : '—' ?></span>
          </div>

          <div class="py-2 border-b border-neutral-100 border-[#1a3346]">
            <span class="text-neutral-500 block mb-1" data-en="Commercial Representatives:" data-ar="مناديب المبيعات:">Commercial Representatives:</span>
            <?php if (!empty($sales_reps_info)): ?>
              <?php foreach ($sales_reps_info as $r): ?>
                <div class="font-sans font-medium text-slate-200">
                  <?= htmlspecialchars($r['full_name']) ?> <span class="text-[11px] font-mono text-neutral-400">(<?= htmlspecialchars($r['role']) ?>)</span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-neutral-400">Direct House Account</span>
            <?php endif; ?>
          </div>

          <div class="py-2">
            <span class="text-neutral-500 block mb-1" data-en="General Specifications:" data-ar="المواصفات العامة:">General Specifications:</span>
            <span class="font-sans text-slate-300"><?= htmlspecialchars($quote['specs'] ?? 'Standard Industrial Supply') ?></span>
          </div>
        </div>
      </div>

      <!-- Line Items Table -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-md overflow-hidden">
        <div class="px-6 py-4 border-b border-[#1a3346] flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-white" data-en="Quoted Gas Products & Services" data-ar="منتجات الغاز والخدمات المسعرة">
              Quoted Gas Products & Services
            </h2>
            <p class="text-xs text-neutral-500" data-en="Industrial line items, cylinder volumes, and unit rate schedule." data-ar="بنود الغاز وأحجام الأسطوانات وجدول الأسعار الفردية.">
              Industrial line items, cylinder volumes, and unit rate schedule.
            </p>
          </div>
          <span class="px-2.5 py-0.5 text-xs font-mono rounded bg-[#09151f] text-slate-400 border border-[#1e3a50]">
            <?= count($items) ?> <span data-en="ITEMS" data-ar="بند">ITEMS</span>
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-start text-xs border-collapse">
            <thead>
              <tr class="bg-[#09151f] bg-[#112433]/75 border-b border-[#1a3346] text-neutral-500 font-mono uppercase">
                <th class="py-3 px-6 text-start" data-en="Product / Gas Specification" data-ar="مواصفة المنتج / الغاز">Product / Gas Specification</th>
                <th class="py-3 px-4 text-end" data-en="Quantity" data-ar="الكمية">Quantity</th>
                <th class="py-3 px-4 text-start" data-en="Unit" data-ar="الوحدة">Unit</th>
                <th class="py-3 px-4 text-end" data-en="Unit Price (SAR)" data-ar="سعر الوحدة (ر.س)">Unit Price (SAR)</th>
                <th class="py-3 px-6 text-end" data-en="Line Total (SAR)" data-ar="الإجمالي (ر.س)">Line Total (SAR)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a] dark:divide-neutral-800 font-mono">
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="5" class="py-8 text-center text-neutral-400 font-sans" data-en="No line items logged on this quotation." data-ar="لا توجد بنود مسجلة في هذا العرض.">
                    No line items logged on this quotation.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($items as $it): ?>
                  <tr class="hover:bg-[#102332] transition-colors">
                    <td class="py-3.5 px-6 font-sans">
                      <div class="font-semibold text-white flex items-center gap-2">
                        <span><?= htmlspecialchars($it['product_name']) ?></span>
                        <?= gas_chip($it['product_name']) ?>
                      </div>
                    </td>
                    <td class="py-3.5 px-4 text-end font-bold text-white">
                      <?= number_format((float)($it['qty'] ?? 0), 2) ?>
                    </td>
                    <td class="py-3.5 px-4 text-neutral-500">
                      <?= htmlspecialchars($it['unit'] ?? 'Cylinders') ?>
                    </td>
                    <td class="py-3.5 px-4 text-end text-slate-200">
                      <?= number_format((float)($it['unit_price'] ?? 0), 2) ?>
                    </td>
                    <td class="py-3.5 px-6 text-end font-bold text-white">
                      <?= number_format((float)($it['line_total'] ?? 0), 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Bottom: Logistics Terms & Calculation Breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Logistics & Delivery Clauses -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
          <h3 class="text-sm font-semibold text-white mb-3" data-en="Logistics, Handling & Delivery Terms" data-ar="شروط النقل والمناولة والتسليم">
            Logistics, Handling & Delivery Terms
          </h3>
          <div class="space-y-3 text-xs leading-relaxed font-mono text-slate-300">
            <?php 
              $notes_parts = explode(' | ', (string)($quote['notes'] ?? ''));
              $renderedNotes = false;
              foreach ($notes_parts as $part): 
                if (trim($part) === '') continue;
                $split = explode(': ', $part, 2);
                if (count($split) === 2):
                  $renderedNotes = true;
            ?>
              <div class="flex justify-between items-start py-1 border-b border-neutral-100 border-[#1a3346]">
                <span class="text-neutral-400 font-sans font-medium"><?= htmlspecialchars($split[0]) ?>:</span>
                <span class="text-end font-mono text-white"><?= htmlspecialchars($split[1]) ?></span>
              </div>
            <?php 
                else:
                  $renderedNotes = true;
                  echo "<p class='p-3 bg-[#09151f] rounded border border-[#1a3346]'>" . htmlspecialchars($part) . "</p>";
                endif;
              endforeach; 
              if (!$renderedNotes):
            ?>
              <p class="text-neutral-400 font-sans" data-en="Standard industrial handling terms apply. Delivery via ADR certified fleet." data-ar="تطبق الشروط القياسية للمناولة الصناعية. التسليم عبر أسطول مرخص ADR.">
                Standard industrial handling terms apply. Delivery via ADR certified fleet.
              </p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Financial Calculation Breakdown -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
          <h3 class="text-sm font-semibold text-white mb-4" data-en="Commercial Valuation Breakdown" data-ar="تفصيل التقييم المالي">
            Commercial Valuation Breakdown
          </h3>
          <div class="space-y-3 text-xs font-mono">
            <div class="flex justify-between items-center py-1 border-b border-neutral-100 border-[#1a3346]">
              <span class="text-neutral-500" data-en="Taxable Subtotal:" data-ar="المجموع الفرعي الخاضع للضريبة:">Taxable Subtotal:</span>
              <span class="font-medium text-white"><?= number_format((float)($quote['subtotal'] ?? 0), 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center py-1 border-b border-neutral-100 border-[#1a3346]">
              <span class="text-neutral-500" data-en="Value Added Tax (15% VAT):" data-ar="ضريبة القيمة المضافة (15%):">Value Added Tax (15% VAT):</span>
              <span class="font-medium text-white"><?= number_format((float)($quote['vat_amount'] ?? 0), 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center py-1 border-b border-neutral-100 border-[#1a3346]">
              <span class="text-neutral-500" data-en="Cryogenic / Transport Freight:" data-ar="أجور النقل والشحن:">Cryogenic / Transport Freight:</span>
              <span class="font-medium text-white"><?= number_format((float)($quote['delivery_fee'] ?? 0), 2) ?> SAR</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t-2 border-[#1e3a50]">
              <span class="font-bold text-sm text-white uppercase font-sans" data-en="Net Quotation Total:" data-ar="صافي الإجمالي:">Net Quotation Total:</span>
              <span class="text-xl font-bold font-mono text-teal-400 dark:text-teal-400"><?= number_format((float)($quote['total_value'] ?? 0), 2) ?> SAR</span>
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
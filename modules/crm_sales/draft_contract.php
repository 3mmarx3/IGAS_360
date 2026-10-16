<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('clients.edit');

$active_page = 'client_contracts';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Draft Contract'];

$is_rep = has_role('sales_rep') && !has_role('admin') && !has_role('super_admin');
if ($is_rep) {
  $clientsStmt = $pdo->prepare("
    SELECT id, reference_id, company_name, entity_type, segment, payment_terms, status
    FROM partners
    WHERE partner_type = 'client' AND (sales_rep_id = :uid OR assigned_to = :uid)
    ORDER BY company_name ASC
  ");
  $clientsStmt->execute([':uid' => $_SESSION['user_id']]);
} else {
  $clientsStmt = $pdo->prepare("
    SELECT id, reference_id, company_name, entity_type, segment, payment_terms, status
    FROM partners
    WHERE partner_type = 'client'
    ORDER BY company_name ASC
  ");
  $clientsStmt->execute();
}
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$nextStmt = $pdo->prepare("
  SELECT contract_number
  FROM client_contracts
  ORDER BY id DESC
  LIMIT 1
");
$nextStmt->execute();
$lastContract = $nextStmt->fetch(PDO::FETCH_ASSOC);

$nextNumber = 7046;
if ($lastContract && !empty($lastContract['contract_number'])) {
  if (preg_match('/CTR-(\d+)/', $lastContract['contract_number'], $m)) {
    $nextNumber = ((int)$m[1]) + 1;
  }
}
$autoContractRef = 'CTR-' . $nextNumber;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $clientId = (int)($_POST['client_id'] ?? 0);
  $contractNumber = trim($_POST['contract_number'] ?? $autoContractRef);
  $billingAddress = trim($_POST['billing_address'] ?? '');
  $gasType = trim($_POST['gas_type'] ?? '');
  $monthlyQuota = trim($_POST['monthly_quota'] ?? '');
  $unit = trim($_POST['unit'] ?? 'Cylinders');
  $notes = trim($_POST['notes'] ?? '');
  $startDate = trim($_POST['start_date'] ?? '');
  $endDate = trim($_POST['end_date'] ?? '');
  $contractValue = (float)($_POST['contract_value'] ?? 0);
  $paymentTerms = trim($_POST['payment_terms'] ?? 'net30');
  $status = trim($_POST['status'] ?? 'active');

  if ($clientId <= 0) {
    $errors[] = 'Please select a valid registered client.';
  }
  if ($contractNumber === '') {
    $errors[] = 'Contract reference number is required.';
  }
  if ($gasType === '') {
    $errors[] = 'Designated gas product is required.';
  }
  if ($startDate === '' || $endDate === '') {
    $errors[] = 'Contract commencement and expiration dates are required.';
  }

  if (empty($errors)) {
    if (!check_record_ownership($pdo, 'partners', $clientId, 'assigned_to') && !check_record_ownership($pdo, 'partners', $clientId, 'sales_rep_id')) {
      redirect('modules/crm_sales/client_contracts.php?error=unauthorized');
    }

    $insert = $pdo->prepare("
      INSERT INTO client_contracts
      (contract_number, client_id, monthly_quota, gas_type, start_date, end_date, contract_value, status, billing_address, unit, notes, payment_terms, created_at)
      VALUES
      (:contract_number, :client_id, :monthly_quota, :gas_type, :start_date, :end_date, :contract_value, :status, :billing_address, :unit, :notes, :payment_terms, CURRENT_TIMESTAMP)
    ");
    $insert->execute([
      ':contract_number' => $contractNumber,
      ':client_id' => $clientId,
      ':monthly_quota' => $monthlyQuota,
      ':gas_type' => $gasType,
      ':start_date' => $startDate,
      ':end_date' => $endDate,
      ':contract_value' => $contractValue,
      ':status' => $status,
      ':billing_address' => $billingAddress,
      ':unit' => $unit,
      ':notes' => $notes,
      ':payment_terms' => $paymentTerms,
    ]);

    redirect('modules/crm_sales/client_contracts.php?created=1');
  }
}

$clientIdSelected = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Draft Master Contract | I-GAS Enterprise</title>
  
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
          <span data-en="DRAFTING MODE" data-ar="وضع صياغة العقود">DRAFTING MODE</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="BLANKET SUPPLY AGREEMENTS" data-ar="اتفاقيات التوريد الشاملة">BLANKET SUPPLY AGREEMENTS</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">NEXT REF: <?= htmlspecialchars($autoContractRef) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">SLA: ISO-9001 / EN-10204</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Header & Action Row -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <a href="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-teal-600 dark:hover:text-teal-400 transition-colors inline-flex items-center gap-1">
              <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
              <span data-en="Master Contracts" data-ar="العقود الرئيسية">Master Contracts</span>
            </a>
            <span class="text-neutral-300 dark:text-neutral-700">/</span>
            <span class="text-xs font-mono text-neutral-400" data-en="New Agreement" data-ar="عقد جديد">New Agreement</span>
          </div>
          <h1 class="text-2xl font-bold font-mono tracking-tight text-white" data-en="Draft Master Supply Agreement" data-ar="صياغة اتفاقية توريد رئيسية">
            Draft Master Supply Agreement
          </h1>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="px-4 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <!-- Single Primary CTA: Safety Valve Orange -->
          <button type="submit" form="contractForm" class="btn-cta-orange px-5 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span data-en="Activate & Register Contract" data-ar="اعتماد وتسجيل العقد">Activate & Register Contract</span>
          </button>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="p-4 rounded-md border border-rose-800 dark:border-rose-900/60 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 text-xs space-y-1">
        <div class="font-bold flex items-center gap-1.5">
          <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600"></i>
          <span data-en="Submission Errors Detected:" data-ar="تم اكتشاف أخطاء أثناء الإدخال:">Submission Errors Detected:</span>
        </div>
        <ul class="list-disc list-inside ps-2 space-y-0.5 font-mono">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form id="contractForm" action="" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left: Form Sections (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Section 1: Customer & Identification -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="1. Client & Reference Identification" data-ar="1. هوية العميل والمرجع التعاقدي">
                1. Client & Reference Identification
              </h2>
              <p class="text-xs text-neutral-500 mb-5" data-en="Select commercial account and define contract reference identifier." data-ar="حدد الحساب التجاري وعرّف الرقم المرجعي للعقد.">
                Select commercial account and define contract reference identifier.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Client Account" data-ar="حساب العميل">Client Account</span> *
                  </label>
                  <select name="client_id" id="clientSelect" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="" disabled selected>-- Select registered partner --</option>
                    <?php foreach ($clients as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $clientIdSelected === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company_name'] ?? 'Unknown Client') ?> (<?= htmlspecialchars($c['reference_id'] ?? 'REF') ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Contract Reference" data-ar="الرقم المرجعي للعقد">Contract Reference</span> *
                  </label>
                  <input type="text" name="contract_number" value="<?= htmlspecialchars($autoContractRef) ?>" required class="w-full bg-[#09151f] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono font-bold text-white focus:outline-none">
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Delivery / Invoicing Plant Site" data-ar="موقع المنشأة للتسليم والفوترة">Delivery / Invoicing Plant Site</span>
                  </label>
                  <input type="text" name="billing_address" placeholder="e.g. Plot 42, Heavy Industrial Zone, Yanbu Industrial City" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs text-white focus:outline-none focus:border-teal-600">
                </div>
              </div>
            </div>

            <!-- Section 2: Gas Specifications & Supply Quota -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="2. Gas Product & Committed Quotas" data-ar="2. منتج الغاز والحصص الملتزم بها">
                2. Gas Product & Committed Quotas
              </h2>
              <p class="text-xs text-neutral-500 mb-5" data-en="Standard industrial gases, purity grades, and delivery batches." data-ar="الغازات الصناعية القياسية ودرجات النقاوة ودفعات التوريد.">
                Standard industrial gases, purity grades, and delivery batches.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Gas Product" data-ar="نوع الغاز">Gas Product</span> *
                  </label>
                  <select name="gas_type" id="gasTypeSelect" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="LIQ. O₂">LIQ. O₂ (Liquid Oxygen)</option>
                    <option value="LIQ. N₂">LIQ. N₂ (Liquid Nitrogen)</option>
                    <option value="AR 50L">AR 50L (Argon 99.999%)</option>
                    <option value="C₂H₂ 40L">C₂H₂ 40L (Dissolved Acetylene)</option>
                    <option value="CO₂ 45KG">CO₂ 45KG (Carbon Dioxide)</option>
                    <option value="N₂O">N₂O (Nitrous Oxide Medical)</option>
                    <option value="MIXED">Shielding Gas Custom Blend</option>
                  </select>
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Monthly Quota" data-ar="الحصة الشهرية">Monthly Quota</span> *
                  </label>
                  <input type="text" name="monthly_quota" id="monthlyQuotaInput" placeholder="e.g. 500" required class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Measurement Unit" data-ar="وحدة القياس">Measurement Unit</span>
                  </label>
                  <select name="unit" id="unitSelect" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="Cylinders">Cylinders (أسطوانات)</option>
                    <option value="MT">Metric Tons / MT (طن متري)</option>
                    <option value="m³">Cubic Meters / m³ (متر مكعب)</option>
                    <option value="Liters">Liters / Cryo (لتر سائل)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Section 3: SLA & Special Instructions -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="3. SLA & Operational Clauses" data-ar="3. شروط مستوى الخدمة والتعليمات التشغيلية">
                3. SLA & Operational Clauses
              </h2>
              <p class="text-xs text-neutral-500 mb-4" data-en="Purity certifications, emergency dispatch thresholds, demurrage & valve safety standards." data-ar="شهادات النقاوة وعتبات التوريد الطارئ ورسوم التأخير ومعايير سلامة الصمامات.">
                Purity certifications, emergency dispatch thresholds, demurrage & valve safety standards.
              </p>
              <textarea name="notes" rows="4" placeholder="Enter special handling conditions, tank pressure certifications, or safety escort requirements..." class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600"></textarea>
            </div>
          </div>

          <!-- Right: Timeline & Commercials (1 col) -->
          <div class="space-y-6">
            <!-- Validity Timeline -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Validity Timeline" data-ar="الجدول الزمني للسريان">
                Validity Timeline
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Effective Start Date" data-ar="تاريخ بدء السريان">Effective Start Date</span> *
                  </label>
                  <input type="date" name="start_date" id="startDateInput" value="<?= date('Y-m-d') ?>" required class="w-full bg-[#0c1924] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Expiration Date" data-ar="تاريخ انتهاء السريان">Expiration Date</span> *
                  </label>
                  <input type="date" name="end_date" id="endDateInput" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required class="w-full bg-[#0c1924] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>
              </div>
            </div>

            <!-- Financials & Payment Terms -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Financial Valuation" data-ar="التقييم المالي">
                Financial Valuation
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Total Contract Ceiling (SAR)" data-ar="السقف المالي الإجمالي (ر.س)">Total Contract Ceiling (SAR)</span>
                  </label>
                  <input type="number" step="0.01" name="contract_value" id="contractValueInput" placeholder="0.00" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-sm font-mono font-bold text-white focus:outline-none focus:border-teal-600">
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Payment Settlement Terms" data-ar="شروط السداد">Payment Settlement Terms</span>
                  </label>
                  <select name="payment_terms" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="net30">Net 30 Days (30 يوماً)</option>
                    <option value="net15">Net 15 Days (15 يوماً)</option>
                    <option value="net45">Net 45 Days (45 يوماً)</option>
                    <option value="net60">Net 60 Days (60 يوماً)</option>
                    <option value="cod">Cash On Delivery / COD (نقداً عند التسليم)</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Initial Lifecycle Status" data-ar="الحالة المبدئية للعقد">Initial Lifecycle Status</span>
                  </label>
                  <select name="status" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="active">Active (ساري ومفعل)</option>
                    <option value="expiring">Expiring Soon (قيد التجديد)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Live Agreement Summary -->
            <div class="bg-[#09151f]/80 border border-[#1e3a50] rounded-md p-5">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-500 mb-3" data-en="Agreement Summary" data-ar="ملخص الاتفاقية">
                Agreement Summary
              </h4>
              <div class="space-y-2 text-xs font-mono">
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="Duration:" data-ar="المدة:">Duration:</span>
                  <span id="summaryDuration" class="font-medium text-slate-200">365 Days</span>
                </div>
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="Annualized Vol:" data-ar="الحجم السنوي:">Annualized Vol:</span>
                  <span id="summaryAnnualVol" class="font-medium text-slate-200">—</span>
                </div>
                <div class="flex justify-between py-1">
                  <span class="text-neutral-500" data-en="Contract Rate:" data-ar="معدل العقد:">Contract Rate:</span>
                  <span id="summaryRate" class="font-bold text-teal-400 dark:text-teal-400">Fixed Ceiling</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <script>
    lucide.createIcons();

    function updateContractSummary() {
      const startVal = document.getElementById('startDateInput').value;
      const endVal = document.getElementById('endDateInput').value;
      const quota = parseFloat(document.getElementById('monthlyQuotaInput').value) || 0;
      const unit = document.getElementById('unitSelect').value;

      if (startVal && endVal) {
        const s = new Date(startVal);
        const e = new Date(endVal);
        const diffTime = e - s;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        document.getElementById('summaryDuration').innerText = diffDays > 0 ? (diffDays + ' Days') : 'Invalid dates';
      }

      if (quota > 0) {
        const annual = Math.round(quota * 12);
        document.getElementById('summaryAnnualVol').innerText = annual.toLocaleString() + ' ' + unit + '/yr';
      } else {
        document.getElementById('summaryAnnualVol').innerText = '—';
      }
    }

    document.getElementById('startDateInput')?.addEventListener('change', updateContractSummary);
    document.getElementById('endDateInput')?.addEventListener('change', updateContractSummary);
    document.getElementById('monthlyQuotaInput')?.addEventListener('input', updateContractSummary);
    document.getElementById('unitSelect')?.addEventListener('change', updateContractSummary);
    updateContractSummary();
  </script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('clients.edit');

$active_page = 'client_contracts';
$breadcrumb = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Renew Contract'];

$contract_param = $_GET['id'] ?? '';
if (empty($contract_param)) {
  $firstId = $pdo->query("SELECT id FROM client_contracts ORDER BY id ASC LIMIT 1")->fetchColumn();
  if (!empty($firstId)) {
    $contract_param = (string)$firstId;
  } else {
    redirect('modules/crm_sales/client_contracts.php');
  }
}

$stmt = $pdo->prepare("
  SELECT c.*, p.company_name, p.sales_rep_id, p.assigned_to
  FROM client_contracts c
  JOIN partners p ON c.client_id = p.id
  WHERE c.id = :id OR c.contract_number = :ref
  LIMIT 1
");
$stmt->execute(['id' => (int)$contract_param, 'ref' => $contract_param]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
  redirect('modules/crm_sales/client_contracts.php');
}

if (!check_record_ownership($pdo, 'partners', $contract['client_id'], 'assigned_to') && !check_record_ownership($pdo, 'partners', $contract['client_id'], 'sales_rep_id')) {
  redirect('modules/crm_sales/client_contracts.php?error=unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $new_end_date = trim($_POST['end_date'] ?? $contract['end_date']);
  $new_quota = trim($_POST['monthly_quota'] ?? $contract['monthly_quota']);
  $unit = trim($_POST['unit'] ?? $contract['unit']);
  $contract_value = (float)($_POST['contract_value'] ?? $contract['contract_value']);
  $payment_terms = trim($_POST['payment_terms'] ?? $contract['payment_terms']);
  $notes = trim($_POST['notes'] ?? $contract['notes']);

  $upd = $pdo->prepare("
    UPDATE client_contracts
    SET end_date = :end_date,
      monthly_quota = :monthly_quota,
      unit = :unit,
      contract_value = :contract_value,
      payment_terms = :payment_terms,
      notes = :notes,
      status = 'active'
    WHERE id = :id
  ");
  $upd->execute([
    ':end_date' => $new_end_date,
    ':monthly_quota' => $new_quota,
    ':unit' => $unit,
    ':contract_value' => $contract_value,
    ':payment_terms' => $payment_terms,
    ':notes' => $notes,
    ':id' => $contract['id']
  ]);

  redirect('modules/crm_sales/contract_details.php?id=' . $contract['id'] . '&renewed=1');
}

// Calculate proposed renewal date (+1 year from current end_date)
$current_end = new DateTime($contract['end_date'] ?? 'now');
$proposed_end = (clone $current_end)->modify('+1 year')->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Renew Contract <?= htmlspecialchars($contract['contract_number']) ?> | I-GAS Enterprise</title>
  
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
          <span data-en="RENEWAL ENGINE" data-ar="محرك تجديد العقود">RENEWAL ENGINE</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="CYCLE EXTENSION" data-ar="تمديد الدورة التعاقدية">CYCLE EXTENSION</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="font-mono text-neutral-500">REF: <?= htmlspecialchars($contract['contract_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-3">
        <span class="text-[11px] text-neutral-400">PLANT — JEDDAH HUB</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl w-full mx-auto">
      <!-- Header Row -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <a href="<?= app_url('modules/crm_sales/client_contracts.php') ?>" class="text-xs font-mono text-neutral-500 hover:text-teal-600 dark:hover:text-teal-400 transition-colors inline-flex items-center gap-1">
              <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
              <span data-en="Master Contracts" data-ar="العقود الرئيسية">Master Contracts</span>
            </a>
            <span class="text-neutral-300 dark:text-neutral-700">/</span>
            <a href="<?= app_url('modules/crm_sales/contract_details.php?id=' . urlencode((string)$contract['id'])) ?>" class="text-xs font-mono text-neutral-500 hover:underline">
              <?= htmlspecialchars($contract['contract_number']) ?>
            </a>
            <span class="text-neutral-300 dark:text-neutral-700">/</span>
            <span class="text-xs font-mono text-neutral-400" data-en="Renewal" data-ar="تجديد">Renewal</span>
          </div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold font-mono tracking-tight text-white">
              <?= htmlspecialchars($contract['contract_number']) ?>
            </h1>
            <span class="px-2.5 py-0.5 text-xs font-mono font-medium rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400 inline-flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span data-en="Renewal Pending" data-ar="قيد التجديد">Renewal Pending</span>
            </span>
            <?php if (!empty($contract['gas_type'])): ?>
              <?= gas_chip($contract['gas_type']) ?>
            <?php endif; ?>
          </div>
          <p class="text-xs text-neutral-500 mt-1">
            <span data-en="Client:" data-ar="العميل:">Client:</span> <span class="font-medium text-slate-200"><?= htmlspecialchars($contract['company_name']) ?></span>
          </p>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/crm_sales/contract_details.php?id=' . urlencode((string)$contract['id'])) ?>" class="px-4 py-2 text-xs font-medium border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors">
            <span data-en="Cancel" data-ar="إلغاء">Cancel</span>
          </a>
          <!-- Single Primary CTA: Safety Valve Orange -->
          <button type="submit" form="renewForm" class="btn-cta-orange px-5 py-2 text-xs font-semibold rounded-md inline-flex items-center gap-2 transition-all">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            <span data-en="Confirm & Execute Renewal" data-ar="تأكيد وتنفيذ التجديد">Confirm & Execute Renewal</span>
          </button>
        </div>
      </div>

      <form id="renewForm" action="" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left: Form Sections (2 cols) -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Current Baseline Snapshot -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-sm font-semibold text-white" data-en="1. Current Term Baseline Snapshot" data-ar="1. لقطة خط الأساس للمدة الحالية">
                    1. Current Term Baseline Snapshot
                  </h2>
                  <p class="text-xs text-neutral-500" data-en="Existing contract terms prior to extension." data-ar="شروط العقد الحالية قبل التمديد.">
                    Existing contract terms prior to extension.
                  </p>
                </div>
                <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-[#09151f] text-neutral-500 border border-[#1e3a50]">
                  READ ONLY
                </span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
                <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
                  <span class="text-neutral-500 block mb-1" data-en="Client Name" data-ar="اسم العميل">Client Name</span>
                  <span class="font-bold font-sans text-white text-sm"><?= htmlspecialchars($contract['company_name']) ?></span>
                </div>
                <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
                  <span class="text-neutral-500 block mb-1" data-en="Gas Product" data-ar="منتج الغاز">Gas Product</span>
                  <span class="font-bold text-white"><?= htmlspecialchars($contract['gas_type']) ?></span>
                </div>
                <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
                  <span class="text-neutral-500 block mb-1" data-en="Current Expiration" data-ar="تاريخ الانتهاء الحالي">Current Expiration</span>
                  <span class="font-bold text-rose-600 dark:text-rose-400"><?= htmlspecialchars($contract['end_date']) ?></span>
                </div>
                <div class="p-3 rounded bg-[#09151f] border border-[#1a3346]">
                  <span class="text-neutral-500 block mb-1" data-en="Monthly Quota & Volume" data-ar="الحصة والحجم الشهري">Monthly Quota & Volume</span>
                  <span class="font-bold text-white"><?= number_format((float)($contract['monthly_quota'] ?? 0)) ?> <?= htmlspecialchars($contract['unit'] ?? '') ?></span>
                </div>
              </div>
            </div>

            <!-- Renewal Parameters -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="2. Extension & Revised Quotas" data-ar="2. التمديد والحصص المعدلة">
                2. Extension & Revised Quotas
              </h2>
              <p class="text-xs text-neutral-500 mb-5" data-en="Update expiry date, adjust volume allocations, and set new financial threshold." data-ar="حدّث تاريخ الانتهاء واضبط مخصصات الحجم وحدد السقف المالي الجديد.">
                Update expiry date, adjust volume allocations, and set new financial threshold.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Current Expiration" data-ar="الانتهاء الحالي">Current Expiration</span>
                  </label>
                  <input type="date" value="<?= htmlspecialchars($contract['end_date']) ?>" disabled class="w-full bg-[#09151f] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-neutral-500 cursor-not-allowed">
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="New Expiration Date" data-ar="تاريخ الانتهاء الجديد">New Expiration Date</span> *
                  </label>
                  <input type="date" name="end_date" id="renewEndDate" value="<?= htmlspecialchars($proposed_end) ?>" required class="w-full bg-[#0c1924] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Measurement Unit" data-ar="وحدة القياس">Measurement Unit</span>
                  </label>
                  <select name="unit" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                    <option value="cylinders" <?= strtolower($contract['unit'] ?? '') == 'cylinders' ? 'selected' : '' ?>>Cylinders (أسطوانات)</option>
                    <option value="mt" <?= in_array(strtolower($contract['unit'] ?? ''), ['tons', 'mt']) ? 'selected' : '' ?>>Metric Tons / MT (طن متري)</option>
                    <option value="m³" <?= strtolower($contract['unit'] ?? '') == 'm³' ? 'selected' : '' ?>>Cubic Meters / m³ (متر مكعب)</option>
                    <option value="liters" <?= strtolower($contract['unit'] ?? '') == 'liters' ? 'selected' : '' ?>>Liters (لتر)</option>
                  </select>
                </div>

                <div class="sm:col-span-1">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="Adjusted Monthly Quota" data-ar="الحصة الشهرية المعدلة">Adjusted Monthly Quota</span> *
                  </label>
                  <input type="text" name="monthly_quota" value="<?= htmlspecialchars($contract['monthly_quota']) ?>" required class="w-full bg-[#0c1924] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                    <span data-en="New Total Contract Ceiling (SAR)" data-ar="السقف المالي الجديد للعقد (ر.س)">New Total Contract Ceiling (SAR)</span> *
                  </label>
                  <input type="number" step="0.01" name="contract_value" value="<?= htmlspecialchars($contract['contract_value']) ?>" required class="w-full bg-[#0c1924] border border-[#1e3a50] rounded px-3 py-2 text-sm font-mono font-bold text-white focus:outline-none focus:border-teal-600">
                </div>
              </div>
            </div>

            <!-- Special Remarks & Amendment Clauses -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h2 class="text-sm font-semibold text-white mb-1" data-en="3. Renewal Addendum & Contract Amendments" data-ar="3. ملحق التجديد وتعديلات البنود">
                3. Renewal Addendum & Contract Amendments
              </h2>
              <p class="text-xs text-neutral-500 mb-4" data-en="Note any pricing formula shifts, emergency dispatch amendments, or delivery location changes." data-ar="دوّن أي تغيير في صيغة التسعير أو تعديلات التوريد الطارئ أو مواقع التسليم.">
                Note any pricing formula shifts, emergency dispatch amendments, or delivery location changes.
              </p>
              <textarea name="notes" rows="4" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600" placeholder="Specify any amendments, tariff index adjustments, or revised SLA terms for this cycle..."><?= htmlspecialchars($contract['notes'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Right: Settlement & Review (1 col) -->
          <div class="space-y-6">
            <!-- Settlement Terms -->
            <div class="bg-[#0c1924] border border-[#1a3346] rounded-md p-6 border-s-4 border-s-[#f97316]">
              <h3 class="text-sm font-semibold text-white mb-4" data-en="Payment Settlement Terms" data-ar="شروط تسوية الفواتير">
                Payment Settlement Terms
              </h3>
              <div>
                <label class="block text-xs font-mono font-medium text-slate-300 mb-1.5">
                  <span data-en="Updated Credit Window" data-ar="فترة الائتمان المحدثة">Updated Credit Window</span>
                </label>
                <select name="payment_terms" class="w-full bg-[#08131c] border border-[#1e3a50] rounded px-3 py-2 text-xs font-mono text-white focus:outline-none focus:border-teal-600">
                  <option value="net30" <?= ($contract['payment_terms'] ?? '') == 'net30' ? 'selected' : '' ?>>Net 30 Days (30 يوماً)</option>
                  <option value="net15" <?= ($contract['payment_terms'] ?? '') == 'net15' ? 'selected' : '' ?>>Net 15 Days (15 يوماً)</option>
                  <option value="net45" <?= ($contract['payment_terms'] ?? '') == 'net45' ? 'selected' : '' ?>>Net 45 Days (45 يوماً)</option>
                  <option value="net60" <?= ($contract['payment_terms'] ?? '') == 'net60' ? 'selected' : '' ?>>Net 60 Days (60 يوماً)</option>
                  <option value="cod" <?= ($contract['payment_terms'] ?? '') == 'cod' ? 'selected' : '' ?>>Cash On Delivery / COD (نقداً)</option>
                  <option value="prepaid" <?= ($contract['payment_terms'] ?? '') == 'prepaid' ? 'selected' : '' ?>>Prepaid In Advance (دفع مسبق)</option>
                </select>
              </div>
            </div>

            <!-- Renewal Impact Preview -->
            <div class="bg-[#09151f]/80 border border-[#1e3a50] rounded-md p-5 space-y-4">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-500" data-en="Renewal Term Impact" data-ar="أثر تمديد العقد">
                Renewal Term Impact
              </h4>
              <div class="space-y-2 text-xs font-mono">
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="Contract Ref:" data-ar="الرقم المرجعي:">Contract Ref:</span>
                  <span class="font-bold text-white"><?= htmlspecialchars($contract['contract_number']) ?></span>
                </div>
                <div class="flex justify-between py-1 border-b border-[#1e3a50]">
                  <span class="text-neutral-500" data-en="New Expiration:" data-ar="الانتهاء الجديد:">New Expiration:</span>
                  <span id="summaryNewEnd" class="font-bold text-teal-400 dark:text-teal-400"><?= htmlspecialchars($proposed_end) ?></span>
                </div>
                <div class="flex justify-between py-1">
                  <span class="text-neutral-500" data-en="State on Save:" data-ar="الحالة بعد الحفظ:">State on Save:</span>
                  <span class="font-bold text-teal-600">ACTIVE / NOMINAL</span>
                </div>
              </div>

              <button type="submit" form="renewForm" class="w-full py-2 px-3 text-xs font-bold rounded bg-teal-800 hover:bg-teal-700 text-white transition-colors">
                <span data-en="Execute Cycle Extension" data-ar="تنفيذ تمديد الدورة">Execute Cycle Extension</span>
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <script>
    lucide.createIcons();

    document.getElementById('renewEndDate')?.addEventListener('change', function(e) {
      const el = document.getElementById('summaryNewEnd');
      if (el) el.innerText = e.target.value;
    });
  </script>
</body>
</html>
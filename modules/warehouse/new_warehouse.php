<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission($pdo, 'warehouse.manage');

$active_page = 'inventory';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Warehouse & Inventory', 'New Warehouse'];

$errors = [];
$success_message = '';

$warehouse_name = '';
$warehouse_type = 'internal';
$client_id   = '';
$location    = '';
$status     = 'active';

$clients = $pdo->query("
  SELECT id, company_name, reference_id 
  FROM partners 
  WHERE partner_type = 'client' AND status = 'approved'
  ORDER BY company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $warehouse_name = trim($_POST['warehouse_name'] ?? '');
  $warehouse_type = $_POST['warehouse_type'] ?? 'internal';
  $client_id   = $_POST['client_id'] ?? '';
  $location    = trim($_POST['location'] ?? '');
  $status     = $_POST['status'] ?? 'active';

  if ($warehouse_name === '') {
    $errors[] = 'Warehouse name is required.';
  }
  if (!in_array($warehouse_type, ['internal', 'client'])) {
    $errors[] = 'Invalid warehouse type.';
  }
  if ($warehouse_type === 'client' && ($client_id === '' || !ctype_digit((string)$client_id))) {
    $errors[] = 'Please select a client for this custody warehouse.';
  }
  if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
  }

  if (empty($errors)) {
    $warehouse_code = 'WHC-' . random_int(100, 999);
    $codeCheck = $pdo->prepare("SELECT id FROM warehouses WHERE warehouse_code = :code LIMIT 1");
    $codeCheck->execute(['code' => $warehouse_code]);
    while ($codeCheck->fetch()) {
      $warehouse_code = 'WHC-' . random_int(100, 999);
      $codeCheck->execute(['code' => $warehouse_code]);
    }

    $final_client_id = ($warehouse_type === 'client') ? (int)$client_id : null;

    try {
      $insert = $pdo->prepare("
        INSERT INTO warehouses (warehouse_code, warehouse_name, warehouse_type, client_id, location, status)
        VALUES (:warehouse_code, :warehouse_name, :warehouse_type, :client_id, :location, :status)
      ");
      $insert->execute([
        'warehouse_code' => $warehouse_code,
        'warehouse_name' => $warehouse_name,
        'warehouse_type' => $warehouse_type,
        'client_id'   => $final_client_id,
        'location'    => $location !== '' ? $location : null,
        'status'     => $status
      ]);

      redirect(app_url('modules/warehouse/warehouses.php?warehouse_created=1'));

    } catch (Exception $e) {
      $errors[] = "Failed to create warehouse: " . $e->getMessage();
    }
  }
}

$generated_code_preview = 'WHC-' . rand(100, 999);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="New Warehouse Depot | I-GAS Control Room" data-ar="إنشاء مخزن جديد | غرفة التحكم I-GAS">New Warehouse Depot | I-GAS Control Room</title>
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

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="DEPOT FACILITY COMMISSIONING PROTOCOL" data-ar="بروتوكول تدشين مستودع أو ساحة تخزين">DEPOT FACILITY COMMISSIONING PROTOCOL</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="PROVISIONAL CODE:" data-ar="الرمز المؤقت:">PROVISIONAL CODE:</span> <strong class="text-white font-mono"><?= htmlspecialchars($generated_code_preview) ?></strong>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: WH-ADMIN</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 w-full mx-auto space-y-6">

    <!-- Breadcrumbs & Title -->
    <div>
      <div class="flex items-center gap-2 text-xs font-mono text-neutral-500 uppercase tracking-wider mb-2">
        <a href="warehouses.php" class="hover:text-white transition-colors" data-en="Warehouse Inventory" data-ar="مستودعات الغاز">Warehouse Inventory</a>
        <span>/</span>
        <span class="text-white font-semibold" data-en="New Warehouse" data-ar="مستودع جديد">New Warehouse</span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white flex items-center gap-2">
            <i data-lucide="warehouse" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Commission New Warehouse Depot" data-ar="إنشاء وتدشين موقع مستودع جديد">Commission New Warehouse Depot</span>
          </h1>
          <p class="text-sm text-slate-400 mt-1" data-en="Define a new internal filling plant depot or register a client customer yard for gas cylinder custody." data-ar="تعريف منشأة تخزين وتعبئة داخلية جديدة، أو تسجيل ساحة عهدة خزانات وأسطوانات لدى عميل.">
            Define a new internal filling plant depot or register a client customer yard for gas cylinder custody.
          </p>
        </div>
        <div>
          <a href="warehouses.php" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700/50 rounded-xs transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span data-en="Back to Directory" data-ar="العودة للدليل">Back to Directory</span>
          </a>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <div>
          <strong class="font-mono text-xs uppercase" data-en="Validation Errors:" data-ar="أخطاء التحقق:">Validation Errors:</strong>
          <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <form action="<?= app_url('modules/warehouse/new_warehouse.php') ?>" method="POST" class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
      <?= csrf_field() ?>

      <!-- Section 1: Facility Details -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="box" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Facility Identification & Classification" data-ar="بيانات الموقع والتصنيف">Facility Identification & Classification</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Depot / Warehouse Name *" data-ar="اسم المستودع / الساحة *">
              Depot / Warehouse Name *
            </label>
            <input type="text" name="warehouse_name" value="<?= htmlspecialchars($warehouse_name) ?>" required placeholder="e.g. Dammam Central Depot, Custody - Al Safwa Hospital" data-en-placeholder="e.g. Dammam Central Depot, Custody - Al Safwa Hospital" data-ar-placeholder="مثال: مستودع الدمام المركزي، عهدة - مستشفى الصفوة" class="w-full h-10 px-3 text-xs font-sans bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Facility Type *" data-ar="نوع المنشأة والتخزين *">
              Facility Type *
            </label>
            <select name="warehouse_type" id="warehouse_type_select" required onchange="toggleClientSelect()" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="internal" <?= $warehouse_type === 'internal' ? 'selected' : '' ?> data-en="Internal Plant Depot (Company Owned)" data-ar="مستودع مصنع داخلي (مملوك للشركة)">Internal Plant Depot (Company Owned)</option>
              <option value="client" <?= $warehouse_type === 'client' ? 'selected' : '' ?> data-en="Client Custody Yard (External)" data-ar="ساحة عهدة عميل (خارجي)">Client Custody Yard (External)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Physical Location / Address" data-ar="العنوان أو الموقع الميداني">
              Physical Location / Address
            </label>
            <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="e.g. Industrial Area Phase 2, Gate 4" data-en-placeholder="e.g. Industrial Area Phase 2, Gate 4" data-ar-placeholder="مثال: المنطقة الصناعية المرحلة الثانية، بوابة 4" class="w-full h-10 px-3 text-xs font-sans bg-[#0c1924] border border-[#1e3a50] rounded-xs text-white focus:border-neutral-900 dark:focus:border-neutral-100 outline-none transition-colors">
          </div>

          <!-- Client Custody Link (Dynamic toggle) -->
          <div class="md:col-span-2" id="client_select_wrapper" style="<?= $warehouse_type === 'client' ? 'display:block;' : 'display:none;' ?>">
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Associated Client Account *" data-ar="حساب العميل المرتبط بالعهدة *">
              Associated Client Account *
            </label>
            <select name="client_id" id="client_id_select" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="" data-en="-- Select Approved Client Account --" data-ar="-- اختر حساب العميل المعتمد --">-- Select Approved Client Account --</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((string)$client_id === (string)$c['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['company_name']) ?> (<?= htmlspecialchars($c['reference_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-[11px] font-sans text-neutral-500 mt-1" data-en="Required for customer custody warehouses to track cylinder asset liability and billing." data-ar="مطلوب لمستودعات عهد العملاء لتتبع مسؤولية الأسطوانات وفواتير الإيجار والتعبئة.">
              Required for customer custody warehouses to track cylinder asset liability and billing.
            </p>
          </div>
        </div>
      </div>

      <!-- Section 2: Configuration & Operational State -->
      <div class="p-6 border-b border-[#1a3346]">
        <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
          <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
          <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
            <i data-lucide="settings" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="System Configuration & Operational Status" data-ar="إعدادات النظام وحالة التشغيل">System Configuration & Operational Status</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Generated Facility Code" data-ar="رمز المستودع المُنشأ">
              Generated Facility Code
            </label>
            <input type="text" class="w-full h-10 px-3 text-xs font-mono bg-[#09151f] border border-[#1e3a50] rounded-xs text-neutral-500 cursor-not-allowed" value="<?= htmlspecialchars($generated_code_preview) ?>" readonly>
            <p class="text-[11px] font-mono text-neutral-400 mt-1" data-en="Permanent code is registered upon saving." data-ar="يتم تثبيت الرمز الدائم عند الحفظ.">
              Permanent code is registered upon saving.
            </p>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Operational Status *" data-ar="حالة التشغيل *">
              Operational Status *
            </label>
            <select name="status" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
              <option value="active" <?= $status === 'active' ? 'selected' : '' ?> data-en="Active & Accepting Transfers" data-ar="نشط ويستقبل التحويلات والعهدة">Active & Accepting Transfers</option>
              <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?> data-en="Inactive / Standby" data-ar="غير نشط / في وضع الاستعداد">Inactive / Standby</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Form Actions Bar -->
      <div class="p-4 bg-[#09151f] flex items-center justify-between">
        <a href="warehouses.php" class="px-4 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 hover:text-white transition-colors" data-en="Discard" data-ar="إلغاء">
          Discard
        </a>
        <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-6 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span data-en="Save & Commission Depot" data-ar="حفظ وتدشين المستودع">Save & Commission Depot</span>
        </button>
      </div>
    </form>

  </div>
</main>

<script>
  lucide.createIcons();

  function toggleClientSelect() {
    const typeSelect = document.getElementById('warehouse_type_select');
    const clientWrapper = document.getElementById('client_select_wrapper');
    const clientSelect = document.getElementById('client_id_select');

    if (typeSelect && clientWrapper && clientSelect) {
      if (typeSelect.value === 'client') {
        clientWrapper.style.display = 'block';
        clientSelect.required = true;
      } else {
        clientWrapper.style.display = 'none';
        clientSelect.required = false;
        clientSelect.value = '';
      }
    }
  }

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", function() {
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
    toggleClientSelect();
  });
</script>
</body>
</html>
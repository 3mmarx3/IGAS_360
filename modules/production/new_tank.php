<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.create');

$active_page = 'customer_tanks';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Assets', 'Customer Tanks', 'Register Tank Asset'];

$clientsStmt = $pdo->query("SELECT id, company_name FROM partners WHERE status = 'approved' ORDER BY company_name ASC");
$clients = $clientsStmt ? $clientsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $serial_number     = trim($_POST['serial_number'] ?? '');
  $client_id       = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
  $installation_location = trim($_POST['installation_location'] ?? '');
  $gas_type       = trim($_POST['gas_type'] ?? '');
  $capacity_liters    = (float)($_POST['capacity_liters'] ?? 0);
  $current_level_pct   = (int)($_POST['current_level_pct'] ?? 0);
  $working_pressure_bar = (float)($_POST['working_pressure_bar'] ?? 0);
  $status        = $_POST['status'] ?? 'optimal';

  if (empty($serial_number) || empty($client_id) || empty($gas_type) || $capacity_liters <= 0) {
    $error_message = "يرجى تعبئة جميع الحقول الأساسية وتحديد سعة أكبر من صفر.";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO customer_tanks 
        (serial_number, client_id, installation_location, gas_type, capacity_liters, current_level_pct, working_pressure_bar, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([
        $serial_number, $client_id, $installation_location, $gas_type, $capacity_liters, $current_level_pct, $working_pressure_bar, $status
      ]);
      $success_message = "تم تسجيل الخزان وربطه بالعميل بنجاح.";
    } catch (Exception $e) {
      $error_message = "حدث خطأ أثناء التسجيل: " . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Register Tank Asset | I-GAS Enterprise" data-ar="تسجيل أصل صهريج | I-GAS Enterprise">Register Tank Asset | I-GAS Enterprise</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  
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
        <span data-en="PLANT: JEDDAH MAIN FACILITY" data-ar="المحطة: منشأة جدة الرئيسية">PLANT: JEDDAH MAIN FACILITY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="ASSET COMMISSIONING" data-ar="إدخال أصل جديد بالخدمة">ASSET COMMISSIONING</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">TYPE:</span>
        <span class="text-slate-300 font-semibold font-mono">MICROBULK</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <form action="<?= app_url('modules/production/new_tank.php') ?>" method="POST" id="tank-form">
        <?= csrf_field() ?>

        <!-- Back Link -->
        <div class="mb-2">
          <a href="<?= app_url('modules/production/customer_tanks.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Customer Tanks" data-ar="العودة لصهاريج العملاء">Back to Customer Tanks</span>
          </a>
        </div>

        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5 mb-6">
          <div>
            <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
              <span data-en="CLIENT ASSETS" data-ar="أصول العملاء">CLIENT ASSETS</span>
              <span>/</span>
              <span class="text-teal-400 dark:text-teal-400" data-en="COMMISSIONING" data-ar="تسجيل صهريج">COMMISSIONING</span>
            </div>
            <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Register Tank Asset" data-ar="تسجيل أصل صهريج">
              Register Tank Asset
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Commission stationary cryogenic vessel or bulk storage tank at an authorized customer facility." data-ar="إضافة صهريج تخزين ثابت وتعيينه لمنشأة عميل معتمدة.">
              Commission stationary cryogenic vessel or bulk storage tank at an authorized customer facility.
            </p>
          </div>

          <div class="flex items-center gap-3">
            <a href="<?= app_url('modules/production/customer_tanks.php') ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs transition-colors" data-en="Cancel" data-ar="إلغاء">
              Cancel
            </a>
            <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs cursor-pointer">
              <i data-lucide="save" class="w-4 h-4"></i>
              <span data-en="Save Tank Asset" data-ar="حفظ الأصل">Save Tank Asset</span>
            </button>
          </div>
        </div>

        <?php if (!empty($error_message)): ?>
        <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-medium flex items-center gap-2 mb-6">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="p-4 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2 mb-6">
          <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Left Column (Client & Specs) -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Client & Location Assignment -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="building-2" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Client & Location Assignment" data-ar="تعيين العميل والموقع">Client & Location Assignment</h3>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Client / Partner *" data-ar="العميل / الشريك *">Client / Partner *</label>
                  <select name="client_id" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                    <option value="" selected disabled data-en="Select assigned client..." data-ar="اختر العميل المعتمد...">Select assigned client...</option>
                    <?php foreach ($clients as $client): ?>
                      <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Installation Location *" data-ar="موقع التركيب *">Installation Location *</label>
                  <input type="text" name="installation_location" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. Main Hospital Block A" data-en-placeholder="e.g. Main Hospital Block A" data-ar-placeholder="مثل: مبنى المستشفى الرئيسي - جناح أ" required>
                </div>
              </div>
            </div>

            <!-- Tank Specifications -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="database" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Vessel Specifications" data-ar="مواصفات الصهريج">Vessel Specifications</h3>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Serial Number / Asset ID *" data-ar="الرقم التسلسلي / معرف الأصل *">Serial Number / Asset ID *</label>
                  <input type="text" name="serial_number" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. TNK-LOX-5001" required>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Gas Product Type *" data-ar="نوع منتج الغاز *">Gas Product Type *</label>
                  <select name="gas_type" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                    <option value="" selected disabled data-en="Select gas type..." data-ar="اختر نوع الغاز...">Select gas type...</option>
                    <option value="Liquid Oxygen">Liquid Oxygen (LOX)</option>
                    <option value="Liquid Nitrogen">Liquid Nitrogen (LIN)</option>
                    <option value="Liquid Argon">Liquid Argon (LAR)</option>
                    <option value="Liquid CO2">Liquid CO2 (LCO2)</option>
                    <option value="LPG Bulk">LPG Bulk</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Capacity (Liters) *" data-ar="السعة (باللتر) *">Capacity (Liters) *</label>
                  <div class="relative">
                    <input type="number" name="capacity_liters" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-16 rtl:pl-16 rtl:pr-3" placeholder="0.00" step="0.01" min="1" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-[11px] font-mono text-neutral-400 pointer-events-none">LITERS</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Working Pressure (BAR) *" data-ar="ضغط التشغيل (بار) *">Working Pressure (BAR) *</label>
                  <div class="relative">
                    <input type="number" name="working_pressure_bar" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-14 rtl:pl-14 rtl:pr-3" placeholder="16.00" step="0.01" min="0" value="16.00" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-[11px] font-mono text-neutral-400 pointer-events-none">BAR</span>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column (Telemetry & Status) -->
          <div class="space-y-6">
            
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-amber-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="activity" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Initial Telemetry & Status" data-ar="الحالة ومستوى الغاز الأولي">Initial Telemetry & Status</h3>
              </div>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Current Gas Level (%) *" data-ar="مستوى الغاز الحالي (%) *">Current Gas Level (%) *</label>
                  <div class="relative">
                    <input type="number" name="current_level_pct" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums pr-8 rtl:pl-8 rtl:pr-3" placeholder="0" min="0" max="100" value="0" required>
                    <span class="absolute right-3 rtl:left-3 rtl:right-auto top-1/2 -translate-y-1/2 text-xs font-mono text-neutral-400 pointer-events-none">%</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Operational Status *" data-ar="الحالة التشغيلية *">Operational Status *</label>
                  <select name="status" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                    <option value="optimal" selected data-en="Operational / Optimal" data-ar="تشغيلي / طبيعي">Operational / Optimal</option>
                    <option value="maintenance" data-en="Under Maintenance" data-ar="تحت الصيانة">Under Maintenance</option>
                  </select>
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
  </script>
</body>
</html>
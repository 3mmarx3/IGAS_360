<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$tank_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tank_id <= 0) {
  $firstTank = (int)$pdo->query("SELECT id FROM customer_tanks ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstTank > 0) {
    $tank_id = $firstTank;
  } else {
    redirect(app_url('modules/production/customer_tanks.php'));
  }
}

$stmt = $pdo->prepare("
  SELECT 
    t.*, 
    p.company_name AS client_name,
    p.phone AS client_phone,
    p.email AS client_email,
    p.address AS client_address
  FROM customer_tanks t
  LEFT JOIN partners p ON t.client_id = p.id
  WHERE t.id = ?
");
$stmt->execute([$tank_id]);
$tank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tank) {
  redirect(app_url('modules/production/customer_tanks.php'));
}

if (!empty($tank['client_id']) && !check_record_ownership($pdo, 'partners', (int)$tank['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this customer tank.");
}

$active_page = 'customer_tanks';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Assets', 'Customer Tanks', htmlspecialchars($tank['serial_number'])];

$level = (int)($tank['current_level_pct'] ?? 0);
$capacity = (float)($tank['capacity_liters'] ?? 0);
$current_liters = ($capacity * $level) / 100;

$tankStatusKey = 'optimal';
if (($tank['status'] ?? '') === 'maintenance') {
  $tankStatusKey = 'maintenance';
} elseif ($level <= 15) {
  $tankStatusKey = 'critical';
} elseif ($level < 30) {
  $tankStatusKey = 'refuel_req';
}

$statusStyles = [
  'optimal'   => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Operational / Normal', 'label_ar' => 'تشغيلي / طبيعي'],
  'refuel_req' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Refuel Needed', 'label_ar' => 'مطلوب تزويد'],
  'critical'  => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Critical Level', 'label_ar' => 'مستوى حرج'],
  'maintenance' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'dot' => 'bg-neutral-500', 'label_en' => 'Under Maintenance', 'label_ar' => 'تحت الصيانة']
];
$statusObj = $statusStyles[$tankStatusKey] ?? $statusStyles['optimal'];

$barColor = 'bg-emerald-500';
if ($level <= 15) {
  $barColor = 'bg-rose-500';
} elseif ($level < 30) {
  $barColor = 'bg-amber-500';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($tank['serial_number']) ?> Details | I-GAS Enterprise</title>
  
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
        <span class="font-semibold text-white font-mono"><?= htmlspecialchars($tank['serial_number']) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">LEVEL:</span>
        <span class="font-semibold font-mono text-teal-400 dark:text-teal-400"><?= $level ?>%</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Back Link -->
      <div>
        <a href="<?= app_url('modules/production/customer_tanks.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Customer Tanks" data-ar="العودة لصهاريج العملاء">Back to Customer Tanks</span>
        </a>
      </div>

      <!-- Page Header Card -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="CLIENT ASSETS" data-ar="أصول العملاء">CLIENT ASSETS</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="VESSEL TELEMETRY" data-ar="قياس الصهريج">VESSEL TELEMETRY</span>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight font-mono">
              <?= htmlspecialchars($tank['serial_number']) ?>
            </h1>
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
              <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Installed at:" data-ar="مثبت لدى:">Installed at:</span>
            <span class="font-medium text-white"><?= htmlspecialchars($tank['client_name'] ?? 'Unassigned') ?></span>
            <span>—</span>
            <span><?= htmlspecialchars($tank['installation_location'] ?? ($tank['location_address'] ?? 'Main Complex')) ?></span>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <a href="<?= app_url('modules/production/customer_tanks.php') ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs flex items-center gap-1.5 transition-colors">
            <i data-lucide="wrench" class="w-4 h-4 text-neutral-500"></i>
            <span data-en="Inspection History" data-ar="سجل الفحص">Inspection History</span>
          </a>
          <?php if (has_permission('production.manage')): ?>
          <a href="<?= app_url('modules/logistics_fleet/new_dispatch.php') ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs">
            <i data-lucide="truck" class="w-4 h-4"></i>
            <span data-en="Schedule Refuel" data-ar="جدولة تزويد بالغاز">Schedule Refuel</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Grid: Telemetry Level (2 cols) & Specs (1 col) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Current Telemetry Level -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] rounded-xs p-6 lg:col-span-2 flex flex-col justify-center">
          <div class="flex justify-between items-end mb-4">
            <div>
              <span class="block text-xs font-mono font-semibold text-slate-400 uppercase mb-1" data-en="Current Telemetry Level" data-ar="مستوى القياس الحالي">Current Telemetry Level</span>
              <div class="flex items-baseline gap-2">
                <h3 class="font-mono text-4xl font-bold text-white tabular-nums leading-none"><?= $level ?>%</h3>
                <span class="text-xs font-mono text-slate-400">/ 100% Full</span>
              </div>
            </div>
            <div class="text-right">
              <span class="block text-xs font-mono font-semibold text-slate-400 uppercase mb-1" data-en="Estimated Volume" data-ar="الحجم التقديري">Estimated Volume</span>
              <div class="font-mono text-xl font-bold text-white tabular-nums">
                <?= number_format($current_liters, 2) ?> <span class="text-xs font-normal text-slate-400">L</span>
              </div>
            </div>
          </div>
          
          <div class="w-full h-4 bg-[#112433] rounded-xs overflow-hidden mb-3 relative">
            <div class="h-full transition-all duration-700 ease-in-out <?= $barColor ?>" style="width: <?= min(100, $level) ?>%;"></div>
            <?php if($level < 30): ?>
            <div class="absolute top-0 bottom-0 border-r border-rose-500" style="left: 30%;"></div>
            <?php endif; ?>
          </div>
          
          <div class="flex justify-between items-center text-[11px] font-mono text-slate-400 uppercase">
            <span>Empty (0 L)</span>
            <span class="flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
              <i data-lucide="bell" class="w-3 h-3"></i> Refuel Threshold (30%)
            </span>
            <span>Full (<?= number_format($capacity) ?> L)</span>
          </div>
        </div>

        <!-- Asset Specifications -->
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 rounded-xs p-6">
          <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#1a3346]">
            <i data-lucide="gauge" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
            <h3 class="font-heading font-bold text-sm text-white" data-en="Asset Specifications" data-ar="مواصفات الأصل">Asset Specifications</h3>
          </div>
          
          <div class="space-y-3.5 text-xs">
            <div>
              <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Gas Product" data-ar="نوع الغاز">Gas Product</span>
              <div class="flex items-center gap-2">
                <div><?= gas_chip($tank['gas_type'] ?? 'O2', 'compact') ?></div>
                <span class="font-medium text-white"><?= htmlspecialchars($tank['gas_type'] ?? ($tank['tank_type'] ?? 'Industrial Gas')) ?></span>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-1 border-t border-neutral-100 border-[#1a3346]/80">
              <div>
                <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Max Capacity" data-ar="السعة القصوى">Max Capacity</span>
                <span class="font-mono font-medium text-white"><?= number_format($capacity) ?> L</span>
              </div>
              <div>
                <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Working Pressure" data-ar="ضغط التشغيل">Working Pressure</span>
                <span class="font-mono font-medium text-white"><?= number_format((float)($tank['working_pressure_bar'] ?? 16.0), 2) ?> BAR</span>
              </div>
            </div>
            <div class="pt-1 border-t border-neutral-100 border-[#1a3346]/80">
              <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Commissioning Date" data-ar="تاريخ الإدخال بالخدمة">Commissioning Date</span>
              <span class="font-mono text-slate-300"><?= !empty($tank['created_at']) ? date('M d, Y', strtotime($tank['created_at'])) : date('M d, Y') ?></span>
            </div>
          </div>
        </div>

      </div>

      <!-- Bottom Grid: Telemetry Logs (2 cols) & Client Info (1 col) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Telemetry Logs / History -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden lg:col-span-2">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
            <div class="flex items-center gap-2">
              <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Telemetry Transmission Logs" data-ar="سجل إشارات القياس">Telemetry Transmission Logs</h3>
            </div>
            <span class="text-xs font-mono text-slate-400 uppercase">Last 7 Days</span>
          </div>
          <div class="p-8 text-center text-xs text-slate-400">
            <i data-lucide="activity" class="w-8 h-8 mx-auto mb-3 text-neutral-400 dark:text-neutral-600"></i>
            <p data-en="Live IoT telemetry integration is streaming heartbeat pulses." data-ar="نظام القياس عن بُعد متصل ويرسل نبضات حية.">Live IoT telemetry integration is streaming heartbeat pulses.</p>
            <p class="font-mono text-[11px] text-neutral-400 dark:text-neutral-500 mt-1">Sensor ID: SEN-<?= htmlspecialchars($tank['serial_number']) ?> · Signal Strength: 98% (4G LTE-M)</p>
          </div>
        </div>

        <!-- Client Information Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs p-6 border-s-4 border-s-[#f97316]">
          <div class="flex items-center gap-2 pb-3 mb-4 border-b border-[#1a3346]">
            <i data-lucide="building-2" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <h3 class="font-heading font-bold text-sm text-white" data-en="Client Assignment" data-ar="بيانات العميل">Client Assignment</h3>
          </div>
          <div class="space-y-3.5 text-xs">
            <div>
              <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Company Name" data-ar="اسم المنشأة">Company Name</span>
              <span class="font-medium text-white text-sm"><?= htmlspecialchars($tank['client_name'] ?? 'Unassigned') ?></span>
            </div>
            <div class="grid grid-cols-1 gap-2 pt-1 border-t border-neutral-100 border-[#1a3346]/80">
              <div>
                <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-0.5" data-en="Phone" data-ar="الهاتف">Phone</span>
                <span class="font-mono text-slate-300"><?= htmlspecialchars($tank['client_phone'] ?? 'N/A') ?></span>
              </div>
              <div>
                <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-0.5" data-en="Email" data-ar="البريد الإلكتروني">Email</span>
                <span class="font-mono text-slate-300"><?= htmlspecialchars($tank['client_email'] ?? 'N/A') ?></span>
              </div>
            </div>
            <div class="pt-1 border-t border-neutral-100 border-[#1a3346]/80">
              <span class="block text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Installation Site Address" data-ar="عنوان موقع التركيب">Installation Site Address</span>
              <span class="text-slate-300 leading-relaxed"><?= htmlspecialchars($tank['client_address'] ?? 'N/A') ?></span>
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
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'customer_tanks';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Assets', 'Customer Tanks'];

$kpiStmt = $pdo->query("
  SELECT 
    COUNT(id) AS total_tanks,
    SUM(CASE WHEN current_level_pct >= 30 THEN 1 ELSE 0 END) AS operational,
    SUM(CASE WHEN current_level_pct < 30 AND current_level_pct > 0 THEN 1 ELSE 0 END) AS refuel_needed,
    SUM(CASE WHEN status = 'maintenance' OR current_level_pct = 0 THEN 1 ELSE 0 END) AS maintenance_alerts
  FROM customer_tanks
");
$kpiData = $kpiStmt ? $kpiStmt->fetch(PDO::FETCH_ASSOC) : [];

$total_tanks    = (int)($kpiData['total_tanks'] ?? 0);
$operational    = (int)($kpiData['operational'] ?? 0);
$refuel_needed   = (int)($kpiData['refuel_needed'] ?? 0);
$maintenance_alerts = (int)($kpiData['maintenance_alerts'] ?? 0);

$operational_pct  = $total_tanks > 0 ? round(($operational / $total_tanks) * 100) : 0;
$refuel_needed_pct = $total_tanks > 0 ? round(($refuel_needed / $total_tanks) * 100) : 0;

$tanksStmt = $pdo->query("
  SELECT 
    t.*, 
    p.company_name AS client_name,
    p.phone AS client_phone
  FROM customer_tanks t
  LEFT JOIN partners p ON t.client_id = p.id
  ORDER BY t.current_level_pct ASC, t.id DESC
");
$tanks_data = $tanksStmt ? $tanksStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$statusStyles = [
  'optimal'   => ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Normal', 'label_ar' => 'طبيعي'],
  'refuel_req' => ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Refuel Needed', 'label_ar' => 'بحاجة لتزويد'],
  'critical'  => ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Critical Level', 'label_ar' => 'مستوى حرج'],
  'maintenance' => ['border' => 'border-neutral-500/30', 'bg' => 'bg-neutral-500/10', 'text' => 'text-slate-400', 'dot' => 'bg-neutral-500', 'label_en' => 'Maintenance', 'label_ar' => 'صيانة']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Customer Tanks | I-GAS Enterprise" data-ar="صهاريج العملاء | I-GAS Enterprise">Customer Tanks | I-GAS Enterprise</title>
  
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
        <span data-en="CLIENT MICROBULK FLEET" data-ar="صهاريج العملاء">CLIENT MICROBULK FLEET</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">ACTIVE TANKS:</span>
        <span class="text-slate-300 font-semibold font-mono"><?= $total_tanks ?></span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            <span data-en="CLIENT ASSET MANAGEMENT" data-ar="إدارة أصول العملاء">CLIENT ASSET MANAGEMENT</span>
            <span>/</span>
            <span class="text-teal-400 dark:text-teal-400" data-en="MICROBULK TANKS" data-ar="صهاريج الغاز السائب">MICROBULK TANKS</span>
          </div>
          <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="Customer Bulk Tanks" data-ar="صهاريج العملاء">
            Customer Bulk Tanks
          </h1>
          <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Monitor static client installations, telemetry gas levels, scheduled refuels, and technical inspections." data-ar="مراقبة صهاريج العملاء، مستويات الغاز عن بُعد، التزويد المجدول، والفحص الفني.">
            Monitor static client installations, telemetry gas levels, scheduled refuels, and technical inspections.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" onclick="location.reload()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors cursor-pointer">
            <i data-lucide="radio" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
            <span data-en="Sync Telemetry" data-ar="مزامنة القياس">Sync Telemetry</span>
          </button>
          <?php if (has_permission('production.create')): ?>
          <a href="<?= app_url('modules/production/new_tank.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span data-en="Register Tank Asset" data-ar="تسجيل أصل صهريج">Register Tank Asset</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Total Deployed Tanks" data-ar="إجمالي الصهاريج المنشورة">Total Deployed Tanks</p>
            <i data-lucide="database" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($total_tanks) ?></h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Active on-site client units" data-ar="صهاريج نشطة بمواقع العملاء">Active on-site client units</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-emerald-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Nominal Levels (30%+)" data-ar="مستوى طبيعي (30%+)">Nominal Levels (30%+)</p>
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums"><?= number_format($operational) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"><?= $operational_pct ?>%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Adequate capacity" data-ar="سعة تشغيلية كافية">Adequate capacity</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-amber-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Refuel Required (&lt;30%)" data-ar="مطلوب تزويد (&lt;30%)">Refuel Required (&lt;30%)</p>
            <i data-lucide="truck" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums"><?= number_format($refuel_needed) ?></h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-amber-500/10 text-amber-400 dark:text-amber-400 border border-amber-500/20"><?= $refuel_needed_pct ?>%</span>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Dispatch tanker trip" data-ar="جدولة رحلة صهريج">Dispatch tanker trip</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-rose-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Critical / Maintenance" data-ar="حرج / صيانة">Critical / Maintenance</p>
            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums"><?= number_format($maintenance_alerts) ?></h3>
          </div>
          <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-2" data-en="Immediate action required" data-ar="يتطلب تدخلاً فورياً">Immediate action required</p>
        </div>
      </div>

      <!-- Installed Assets Directory Card -->
      <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f]">
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            <!-- Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar" id="tankTabs">
              <button type="button" onclick="filterTankTab('all', this)" class="tank-tab active px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-teal-600 text-teal-400 dark:text-teal-400 font-semibold cursor-pointer transition-colors" data-tab="all">
                <span data-en="Active Tanks" data-ar="الصهاريج النشطة">Active Tanks</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= count($tanks_data) ?></span>
              </button>
              <button type="button" onclick="filterTankTab('refuel', this)" class="tank-tab px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="refuel">
                <span data-en="Refueling Queue" data-ar="قائمة التزويد">Refueling Queue</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $refuel_needed ?></span>
              </button>
              <button type="button" onclick="filterTankTab('critical', this)" class="tank-tab px-3.5 py-2 text-xs font-mono uppercase tracking-wider border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer transition-colors" data-tab="critical">
                <span data-en="Critical & Inspection" data-ar="الحرج والصيانة">Critical & Inspection</span>
                <span class="ms-1.5 px-1.5 py-0.2 rounded-xs text-[10px] font-mono bg-[#112433] text-slate-300"><?= $maintenance_alerts ?></span>
              </button>
            </div>

            <!-- Search & Filter -->
            <div class="flex items-center gap-3 w-full lg:w-auto">
              <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="tankSearchInput" placeholder="Search Tank SN or Client..." data-en-placeholder="Search Tank SN or Client..." data-ar-placeholder="البحث بالرقم التسلسلي أو العميل..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
              <select id="gasFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
                <option value="" data-en="All Gas Types" data-ar="جميع أنواع الغاز">All Gas Types</option>
                <option value="Oxygen">Liquid Oxygen (LOX)</option>
                <option value="Nitrogen">Liquid Nitrogen (LIN)</option>
                <option value="Argon">Liquid Argon (LAR)</option>
                <option value="CO2">Liquid CO2 (LCO2)</option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
          <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 850px;">
            <thead>
              <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 font-semibold" data-en="Tank Serial / ID" data-ar="الرقم التسلسلي">Tank Serial / ID</th>
                <th class="px-4 py-3 font-semibold" data-en="Client & Location" data-ar="العميل والموقع">Client & Location</th>
                <th class="px-4 py-3 font-semibold" data-en="Gas & Capacity" data-ar="الغاز والسعة">Gas & Capacity</th>
                <th class="px-4 py-3 font-semibold" data-en="Telemetry Level" data-ar="مستوى الغاز">Telemetry Level</th>
                <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Pressure" data-ar="الضغط">Pressure</th>
                <th class="px-4 py-3 font-semibold" data-en="Status" data-ar="الحالة">Status</th>
                <th class="px-5 py-3 font-semibold text-right rtl:text-left" style="width: 120px;" data-en="Actions" data-ar="الإجراءات">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]" id="tankTableBody">
              <?php if (empty($tanks_data)): ?>
              <tr>
                <td colspan="7" class="text-center py-8 text-xs text-slate-400" data-en="No client tanks registered yet." data-ar="لا توجد صهاريج عملاء مسجلة حالياً.">
                  No client tanks registered yet.
                </td>
              </tr>
              <?php else: ?>
              <?php foreach ($tanks_data as $tank): ?>
              <?php
                $level = (int)($tank['current_level_pct'] ?? 0);
                $tankStatusKey = 'optimal';
                if (($tank['status'] ?? '') === 'maintenance') {
                  $tankStatusKey = 'maintenance';
                } elseif ($level <= 15) {
                  $tankStatusKey = 'critical';
                } elseif ($level < 30) {
                  $tankStatusKey = 'refuel_req';
                }
                $statusObj = $statusStyles[$tankStatusKey];

                $barColor = 'bg-emerald-500';
                if ($level <= 15) {
                  $barColor = 'bg-rose-500';
                } elseif ($level < 30) {
                  $barColor = 'bg-amber-500';
                }
              ?>
              <tr class="hover:bg-[#102332] transition-colors tank-row" 
                data-level="<?= $level ?>" 
                data-status="<?= $tankStatusKey ?>" 
                data-gas="<?= htmlspecialchars($tank['gas_type'] ?? '') ?>">
                <td class="px-5 py-3 font-mono font-semibold">
                  <a href="<?= app_url('modules/production/tank_details.php?id=' . (int)$tank['id']) ?>" class="text-teal-400 dark:text-teal-400 hover:underline tank-sn">
                    <?= htmlspecialchars($tank['serial_number'] ?? 'TNK-'.$tank['id']) ?>
                  </a>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col">
                    <span class="font-medium text-white tank-client"><?= htmlspecialchars($tank['client_name'] ?? 'Unassigned') ?></span>
                    <span class="text-[11px] font-mono text-slate-400"><?= htmlspecialchars($tank['installation_location'] ?? 'N/A') ?></span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div><?= gas_chip($tank['gas_type'] ?? 'O2', 'compact') ?></div>
                    <div class="flex flex-col">
                      <span class="font-medium text-white"><?= htmlspecialchars($tank['gas_type'] ?? 'N/A') ?></span>
                      <span class="text-[11px] font-mono text-slate-400"><?= number_format((int)($tank['capacity_liters'] ?? 0)) ?> L</span>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-20 h-1.5 bg-[#112433] rounded-xs overflow-hidden">
                      <div class="h-full <?= $barColor ?>" style="width: <?= min(100, $level) ?>%;"></div>
                    </div>
                    <span class="font-mono font-medium text-xs tabular-nums text-white"><?= $level ?>%</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-right rtl:text-left font-mono text-white tabular-nums">
                  <?= htmlspecialchars($tank['working_pressure_bar'] ?? '0') ?> <span class="text-[11px] text-slate-400">BAR</span>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                    <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
                  </span>
                </td>
                <td class="px-5 py-3 text-right rtl:text-left">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="<?= app_url('modules/production/tank_details.php?id=' . (int)$tank['id']) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-[#102230] dark:hover:bg-neutral-800 text-slate-300 transition-colors inline-flex items-center" title="View Tank Details">
                      <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    </a>
                    <a href="<?= app_url('modules/production/tank_details.php?id=' . (int)$tank['id']) ?>" class="p-1 rounded-xs border border-[#1e3a50] hover:bg-amber-950/30 dark:hover:bg-amber-950/40 text-amber-600 dark:text-amber-400 transition-colors inline-flex items-center" title="Schedule Refuel">
                      <i data-lucide="truck" class="w-3.5 h-3.5"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex justify-between items-center text-xs font-mono text-slate-400">
          <span>Showing <?= count($tanks_data) ?> Customer Tanks</span>
        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();

    let currentTabFilter = 'all';

    function filterTankTab(tabName, element) {
      document.querySelectorAll('#tankTabs .tank-tab').forEach(t => {
        t.classList.remove('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
        t.classList.add('border-transparent', 'text-neutral-600', 'text-slate-400');
      });
      element.classList.add('active', 'border-teal-600', 'text-teal-400', 'dark:text-teal-400', 'font-semibold');
      element.classList.remove('border-transparent', 'text-neutral-600', 'text-slate-400');
      currentTabFilter = tabName;
      filterTankRows();
    }

    function filterTankRows() {
      const query = (document.getElementById('tankSearchInput').value || '').toLowerCase();
      const selectedGas = (document.getElementById('gasFilter').value || '').toLowerCase();
      const rows = document.querySelectorAll('.tank-row');

      rows.forEach(row => {
        const sn = (row.querySelector('.tank-sn') ? row.querySelector('.tank-sn').innerText : '').toLowerCase();
        const client = (row.querySelector('.tank-client') ? row.querySelector('.tank-client').innerText : '').toLowerCase();
        const gas = (row.getAttribute('data-gas') || '').toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const level = parseInt(row.getAttribute('data-level') || '0');

        const matchesSearch = !query || sn.includes(query) || client.includes(query);
        const matchesGas = !selectedGas || gas.includes(selectedGas);

        let matchesTab = true;
        if (currentTabFilter === 'refuel') {
          matchesTab = (status === 'refuel_req' || (level < 30 && level > 0));
        } else if (currentTabFilter === 'critical') {
          matchesTab = (status === 'critical' || status === 'maintenance' || level <= 15);
        }

        if (matchesSearch && matchesGas && matchesTab) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    document.getElementById('tankSearchInput').addEventListener('input', filterTankRows);
    document.getElementById('gasFilter').addEventListener('change', filterTankRows);
  </script>
</body>
</html>
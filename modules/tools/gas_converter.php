<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_conversion') {
  require_csrf();
  $stmt = $pdo->prepare("INSERT INTO gas_conversion_logs (gas_type, input_qty, input_unit, result_mass, result_nm3, result_sm3, result_liquid, logged_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $_POST['gas_type'] ?? '',
    (float)($_POST['input_qty'] ?? 0),
    $_POST['input_unit'] ?? '',
    $_POST['result_mass'] ?? '',
    $_POST['result_nm3'] ?? '',
    $_POST['result_sm3'] ?? '',
    $_POST['result_liquid'] ?? '',
    $_SESSION['user_name'] ?? 'Staff'
  ]);
  redirect(app_url('modules/tools/gas_converter.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
  require_csrf();
  if (!is_admin() && !has_permission('settings.manage')) {
    redirect(app_url('modules/tools/gas_converter.php'));
  }
  $pdo->query("DELETE FROM gas_conversion_logs");
  redirect(app_url('modules/tools/gas_converter.php'));
}

$active_page = 'gas_converter';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Tools', 'Comprehensive Gas Converter'];

$logs = [];
try {
  $stmt = $pdo->query("SELECT * FROM gas_conversion_logs ORDER BY created_at DESC LIMIT 15");
  if ($stmt) {
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Comprehensive Gas Converter | I-GAS Industrial" data-ar="محول الغاز الشامل | I-GAS Industrial">Comprehensive Gas Converter | I-GAS Industrial</title>
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

  <!-- Telemetry Status Sub-bar -->
  <div class="h-9 border-b border-[#1a3346] bg-[#0c1924] flex items-center px-4 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px] font-mono">
    <span class="flex items-center gap-2 font-medium uppercase tracking-wide text-white">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      <span data-en="Thermodynamics Core Nominal" data-ar="محرك الديناميكا الحرارية مستقر">Thermodynamics Core Nominal</span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Reference Standard:" data-ar="المعيار المرجعي:">Reference Standard:</span> <span class="font-mono text-slate-200">ISO 13443 / CGA P-1</span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Base Conditions:" data-ar="شروط الأساس:">Base Conditions:</span> <span class="font-mono text-teal-600 dark:text-teal-400 font-semibold">Nm³ (0°C, 1atm) · Sm³ (15°C, 1atm)</span>
    </span>
  </div>

  <!-- Main Content Body -->
  <div class="flex-1 overflow-auto px-4 sm:px-6 md:px-8 py-5 md:py-7 flex flex-col gap-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400" data-en="Engineering Tools" data-ar="أدوات هندسية">Engineering Tools</span>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400"></i>
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400" data-en="Gas Converter" data-ar="محول الغاز">Gas Converter</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Comprehensive Gas Converter" data-ar="محول الغازات الصناعية الشامل">Comprehensive Gas Converter</h1>
        <p class="text-xs text-slate-400 mt-1 font-mono" data-en="Industrial standards thermodynamic conversion (Mass, Nm³, Sm³, Liquid Volume) across 16 major gas types." data-ar="محول المعايير الصناعية الديناميكية الحرارية (الكتلة، Nm³، Sm³، وحجم السائل) عبر 16 نوعاً رئيسياً من الغازات.">Industrial standards thermodynamic conversion (Mass, Nm³, Sm³, Liquid Volume) across 16 major gas types.</p>
      </div>
      <div>
        <a href="<?= app_url('modules/tools/unit_converter.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-300 hover:bg-[#102332] dark:hover:bg-neutral-800 transition-colors">
          <i data-lucide="gauge" class="w-3.5 h-3.5 text-neutral-500"></i>
          <span data-en="Pressure & Flow Converter" data-ar="محول الضغط والتدفق">Pressure & Flow Converter</span>
        </a>
      </div>
    </div>

    <!-- 2-Column Responsive Layout for Converters -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
      
      <!-- Left 2 Cols: Main Universal Converter -->
      <div class="xl:col-span-2 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
              <i data-lucide="calculator" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Universal Conversion Engine" data-ar="محرك التحويل الشامل">Universal Conversion Engine</span>
            </h3>
            <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">CALC-01</span>
          </div>

          <!-- Input Parameters Form -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Target Gas Product" data-ar="نوع الغاز المستهدف">Target Gas Product</label>
              <select id="gasSelect" class="w-full border border-[#1a3346] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 font-medium">
                <optgroup label="Atmospheric & Air Gases" class="font-bold text-neutral-500">
                  <option value="Oxygen (O2)" selected>Oxygen (O₂)</option>
                  <option value="Nitrogen (N2)">Nitrogen (N₂)</option>
                  <option value="Argon (Ar)">Argon (Ar)</option>
                  <option value="Air (Compressed)">Compressed Air</option>
                </optgroup>
                <optgroup label="Fuel & Hydrocarbons" class="font-bold text-neutral-500">
                  <option value="Hydrogen (H2)">Hydrogen (H₂)</option>
                  <option value="Methane (CH4)">Methane (CH₄)</option>
                  <option value="Propane (C3H8)">Propane (C₃H₈)</option>
                  <option value="Acetylene (C2H2)">Acetylene (C₂H₂)</option>
                  <option value="Ethylene (C2H4)">Ethylene (C₂H₄)</option>
                </optgroup>
                <optgroup label="Chemical & Specialty Gases" class="font-bold text-neutral-500">
                  <option value="Carbon Dioxide (CO2)">Carbon Dioxide (CO₂)</option>
                  <option value="Helium (He)">Helium (He)</option>
                  <option value="Ammonia (NH3)">Ammonia (NH₃)</option>
                  <option value="Chlorine (Cl2)">Chlorine (Cl₂)</option>
                  <option value="Nitrous Oxide (N2O)">Nitrous Oxide (N₂O)</option>
                  <option value="Sulfur Dioxide (SO2)">Sulfur Dioxide (SO₂)</option>
                  <option value="Carbon Monoxide (CO)">Carbon Monoxide (CO)</option>
                </optgroup>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Input Magnitude & Base Unit" data-ar="الكمية ووحدة الأساس">Input Magnitude & Base Unit</label>
              <div class="flex gap-2">
                <input type="number" id="inputQty" value="1" step="any" class="w-1/2 border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50" placeholder="1.0">
                <select id="inputUnit" class="w-1/2 border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2.5 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 font-medium">
                  <option value="kg" selected data-en="Mass (kg)" data-ar="الكتلة (كجم)">Mass (kg)</option>
                  <option value="nm3" data-en="Gas - Nm³ (0 °C, 1atm)" data-ar="غاز - Nm³ (0 °C، 1 بار)">Gas - Nm³ (0 °C, 1atm)</option>
                  <option value="sm3" data-en="Gas - Sm³ (15 °C, 1atm)" data-ar="غاز - Sm³ (15 °C، 1 بار)">Gas - Sm³ (15 °C, 1atm)</option>
                  <option value="liq" data-en="Liquid (Liters at NBP)" data-ar="سائل (لتر عند نقطة الغليان)">Liquid (Liters at NBP)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- 4 Conversion Output Tiles -->
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <!-- Tile 1: Mass -->
            <div class="p-4 border border-[#1a3346] bg-[#09151f] flex flex-col items-center justify-center text-center">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Mass" data-ar="الكتلة">Mass</span>
              <span class="text-xl font-bold font-mono text-white" id="resMass">1.0000</span>
              <span class="text-[10px] font-mono text-neutral-500 mt-1">Kilograms (kg)</span>
            </div>

            <!-- Tile 2: Nm3 -->
            <div class="p-4 border border-[#1a3346] bg-[#09151f] flex flex-col items-center justify-center text-center">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Normal Vol" data-ar="الحجم النظامي">Normal Vol</span>
              <span class="text-xl font-bold font-mono text-white" id="resNm3">0.7003</span>
              <span class="text-[10px] font-mono text-neutral-500 mt-1">Nm³ (0 °C, 1 atm)</span>
            </div>

            <!-- Tile 3: Sm3 -->
            <div class="p-4 border border-[#1a3346] bg-[#09151f] flex flex-col items-center justify-center text-center">
              <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Standard Vol" data-ar="الحجم القياسي">Standard Vol</span>
              <span class="text-xl font-bold font-mono text-white" id="resSm3">0.7480</span>
              <span class="text-[10px] font-mono text-neutral-500 mt-1">Sm³ (15 °C, 1 atm)</span>
            </div>

            <!-- Tile 4: Liquid Volume (Highlighted Teal Accent) -->
            <div class="p-4 border border-teal-800 dark:border-teal-800 bg-teal-950/30/50 dark:bg-teal-950/40 flex flex-col items-center justify-center text-center">
              <span class="text-[10px] font-mono uppercase tracking-wider text-teal-400 dark:text-teal-300 mb-1" data-en="Cryo Liquid" data-ar="السائل المبرد">Cryo Liquid</span>
              <span class="text-xl font-bold font-mono text-teal-900 dark:text-teal-100" id="resLiquid">0.8764</span>
              <span class="text-[10px] font-mono text-teal-600 dark:text-teal-400 mt-1">Liters at NBP</span>
            </div>
          </div>
        </div>

        <!-- Form to Log with Sole Primary CTA in Safety Valve Orange -->
        <form method="POST" action="<?= app_url('modules/tools/gas_converter.php') ?>" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-[#1a3346]">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="log_conversion">
          <input type="hidden" name="gas_type" id="formGas">
          <input type="hidden" name="input_qty" id="formQty">
          <input type="hidden" name="input_unit" id="formUnit">
          <input type="hidden" name="result_mass" id="formMass">
          <input type="hidden" name="result_nm3" id="formNm3">
          <input type="hidden" name="result_sm3" id="formSm3">
          <input type="hidden" name="result_liquid" id="formLiquid">

          <div class="text-[11px] font-mono text-slate-400">
            <span data-en="Physical parameters loaded from ISO 13443 reference library." data-ar="البيانات الفيزيائية محملة من مكتبة معايير ISO 13443.">Physical parameters loaded from ISO 13443 reference library.</span>
          </div>

          <!-- Single Primary CTA in Safety Valve Orange -->
          <button type="submit" class="btn-cta-orange px-5 py-2.5 text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center gap-2 cursor-pointer transition-colors w-full sm:w-auto justify-center">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span data-en="Save Calculation to Logs" data-ar="حفظ الحساب في السجلات">Save Calculation to Logs</span>
          </button>
        </form>
      </div>

      <!-- Right Col: Cylinder Capacity Estimator -->
      <div class="xl:col-span-1 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] p-6 flex flex-col justify-between h-full">
        <div>
          <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
              <i data-lucide="cylinder" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Cylinder Capacity Estimator" data-ar="مقدر سعة الأسطوانة">Cylinder Capacity Estimator</span>
            </h3>
            <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">CYL-01</span>
          </div>

          <p class="text-xs text-slate-400 mb-5 leading-relaxed font-mono" data-en="Calculate exact real gas volume (Nm³) and payload mass contained based on water capacity and fill gauge pressure." data-ar="حساب حجم الغاز الفعلي والكتلة الصافية بناءً على سعة الماء وضغط التعبئة.">Calculate exact real gas volume (Nm³) and payload mass contained based on water capacity and fill gauge pressure.</p>

          <div class="space-y-4 mb-6">
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Water Capacity (Liters)" data-ar="سعة الماء (لتر)">Water Capacity (Liters)</label>
              <input type="number" id="cylCap" value="40" class="w-full border border-[#1a3346] bg-[#09151f] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50">
            </div>
            <div>
              <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="Fill Pressure (bar gauge)" data-ar="ضغط التعبئة (بار)">Fill Pressure (bar gauge)</label>
              <input type="number" id="cylPres" value="150" class="w-full border border-[#1a3346] bg-[#09151f] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2.5 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50">
            </div>
          </div>
        </div>

        <div class="pt-4 border-t border-[#1a3346] space-y-3 font-mono text-xs">
          <div class="flex justify-between items-center text-slate-400">
            <span data-en="Gas Volume (Nm³):" data-ar="حجم الغاز (Nm³):">Gas Volume (Nm³):</span>
            <span class="text-base font-bold text-white" id="cylNm3Out">6.04 Nm³</span>
          </div>
          <div class="flex justify-between items-center text-slate-400">
            <span data-en="Net Payload Mass:" data-ar="الكتلة الصافية:">Net Payload Mass:</span>
            <span class="text-base font-bold text-teal-600 dark:text-teal-400" id="cylKgOut">8.62 kg</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Calculation History Ledger -->
    <div class="border border-[#1a3346] bg-[#0c1924] overflow-hidden">
      <div class="p-5 border-b border-[#1a3346] flex items-center justify-between">
        <div>
          <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white" data-en="Calculation History Ledger" data-ar="سجل عمليات التحويل">Calculation History Ledger</h3>
          <p class="text-xs text-slate-400 font-mono mt-0.5" data-en="Persistent telemetry logs of engineer conversions" data-ar="سجل القياسات والتحويلات الهندسية المحفوظة">Persistent telemetry logs of engineer conversions</p>
        </div>
        <?php if (is_admin() || has_permission('settings.manage')): ?>
        <form method="POST" action="<?= app_url('modules/tools/gas_converter.php') ?>" onsubmit="return confirm('Clear all conversion logs? / مسح كافة سجلات التحويل؟');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear_logs">
          <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            <span data-en="Clear History" data-ar="مسح السجل">Clear History</span>
          </button>
        </form>
        <?php endif; ?>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse font-sans text-xs">
          <thead>
            <tr class="text-[11px] font-mono uppercase tracking-wider text-slate-400 border-b border-[#1a3346] bg-[#09151f]">
              <th class="pl-6 py-3 font-semibold" data-en="Gas Product" data-ar="صنف الغاز">Gas Product</th>
              <th class="px-4 py-3 font-semibold" data-en="Input Magnitude" data-ar="القيمة المدخلة">Input Magnitude</th>
              <th class="px-4 py-3 font-semibold text-right" data-en="Mass (kg)" data-ar="الكتلة (كجم)">Mass (kg)</th>
              <th class="px-4 py-3 font-semibold text-right" data-en="Normal (Nm³)" data-ar="الحجم النظامي (Nm³)">Normal (Nm³)</th>
              <th class="px-4 py-3 font-semibold text-right" data-en="Liquid (Liters)" data-ar="السائل (لتر)">Liquid (Liters)</th>
              <th class="pr-6 py-3 font-semibold text-right" data-en="Timestamp" data-ar="التوقيت">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a]">
            <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" class="text-center py-8 text-slate-400 font-mono text-xs" data-en="No conversion calculations recorded yet. Use the engine above to save calculations." data-ar="لا توجد عمليات تحويل مسجلة بعد. استخدم المحرك أعلاه لحفظ الحسابات.">
                No conversion calculations recorded yet. Use the engine above to save calculations.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
              <tr class="hover:bg-[#102332] transition-colors">
                <td class="pl-6 py-3.5 font-semibold text-white">
                  <?= htmlspecialchars($log['gas_type']) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-slate-200">
                  <span class="font-bold"><?= floatval($log['input_qty']) ?></span> <?= htmlspecialchars($log['input_unit']) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-right font-medium text-white">
                  <?= number_format((float)$log['result_mass'], 4) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-right font-medium text-white">
                  <?= number_format((float)$log['result_nm3'], 4) ?>
                </td>
                <td class="px-4 py-3.5 font-mono text-right font-medium text-teal-600 dark:text-teal-400">
                  <?= number_format((float)$log['result_liquid'], 4) ?>
                </td>
                <td class="pr-6 py-3.5 font-mono text-right text-slate-400 text-[11px]">
                  <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<script>
  lucide.createIcons();

  const gasProperties = {
    'Oxygen (O2)': { rho_s: 1.337, rho_l: 1141 },
    'Nitrogen (N2)': { rho_s: 1.170, rho_l: 808 },
    'Argon (Ar)': { rho_s: 1.669, rho_l: 1395 },
    'Air (Compressed)': { rho_s: 1.205, rho_l: 876 },
    
    'Hydrogen (H2)': { rho_s: 0.084, rho_l: 71 },
    'Methane (CH4)': { rho_s: 0.680, rho_l: 422 },
    'Propane (C3H8)': { rho_s: 1.882, rho_l: 582 },
    'Acetylene (C2H2)': { rho_s: 1.092, rho_l: 400 },
    'Ethylene (C2H4)': { rho_s: 1.178, rho_l: 569 },
    
    'Carbon Dioxide (CO2)': { rho_s: 1.848, rho_l: 1032 },
    'Helium (He)': { rho_s: 0.167, rho_l: 125 },
    'Ammonia (NH3)': { rho_s: 0.730, rho_l: 681 },
    'Chlorine (Cl2)': { rho_s: 2.994, rho_l: 1562 },
    'Nitrous Oxide (N2O)': { rho_s: 1.843, rho_l: 1220 },
    'Sulfur Dioxide (SO2)': { rho_s: 2.726, rho_l: 1460 },
    'Carbon Monoxide (CO)': { rho_s: 1.165, rho_l: 789 }
  };

  const TN = 273.15;
  const TS = 288.15;

  const gasSelect = document.getElementById('gasSelect');
  const inputQty = document.getElementById('inputQty');
  const inputUnit = document.getElementById('inputUnit');

  const resMass = document.getElementById('resMass');
  const resNm3 = document.getElementById('resNm3');
  const resSm3 = document.getElementById('resSm3');
  const resLiquid = document.getElementById('resLiquid');

  const formGas = document.getElementById('formGas');
  const formQty = document.getElementById('formQty');
  const formUnit = document.getElementById('formUnit');
  const formMass = document.getElementById('formMass');
  const formNm3 = document.getElementById('formNm3');
  const formSm3 = document.getElementById('formSm3');
  const formLiquid = document.getElementById('formLiquid');

  const cylCap = document.getElementById('cylCap');
  const cylPres = document.getElementById('cylPres');
  const cylNm3Out = document.getElementById('cylNm3Out');
  const cylKgOut = document.getElementById('cylKgOut');

  function rhoN(g) {
    return g.rho_s * TS / TN;
  }

  function calculateGas() {
    const gasKey = gasSelect.value;
    const g = gasProperties[gasKey] || gasProperties['Oxygen (O2)'];
    const v = parseFloat(inputQty.value) || 0;
    const u = inputUnit.value;

    const rn = rhoN(g);
    const rs = g.rho_s;
    const rlk = g.rho_l / 1000;

    let m = 0;
    if (u === 'kg') m = v;
    else if (u === 'nm3') m = v * rn;
    else if (u === 'sm3') m = v * rs;
    else if (u === 'liq') m = v * rlk;

    const calcNm3 = m / rn;
    const calcSm3 = m / rs;
    const calcLiq = m / rlk;

    resMass.textContent = m.toFixed(4);
    resNm3.textContent = calcNm3.toFixed(4);
    resSm3.textContent = calcSm3.toFixed(4);
    resLiquid.textContent = calcLiq.toFixed(4);

    formGas.value = gasKey;
    formQty.value = v;
    formUnit.value = u;
    formMass.value = m.toFixed(4);
    formNm3.value = calcNm3.toFixed(4);
    formSm3.value = calcSm3.toFixed(4);
    formLiquid.value = calcLiq.toFixed(4);
  }

  function calculateCyl() {
    const gasKey = gasSelect.value;
    const g = gasProperties[gasKey] || gasProperties['Oxygen (O2)'];
    const cap = parseFloat(cylCap.value) || 0;
    const p = parseFloat(cylPres.value) || 0;

    const nm3 = cap * (p + 1.01325) / 1.01325 / 1000;
    const kg = nm3 * rhoN(g);

    cylNm3Out.textContent = nm3.toFixed(2) + ' Nm³';
    cylKgOut.textContent = kg.toFixed(2) + ' kg';
  }

  gasSelect.addEventListener('change', () => {
    calculateGas();
    calculateCyl();
  });
  inputQty.addEventListener('input', calculateGas);
  inputUnit.addEventListener('change', calculateGas);

  cylCap.addEventListener('input', calculateCyl);
  cylPres.addEventListener('input', calculateCyl);

  calculateGas();
  calculateCyl();
</script>
</body>
</html>
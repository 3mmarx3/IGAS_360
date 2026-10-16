<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_conversion') {
  require_csrf();
  $stmt = $pdo->prepare("INSERT INTO unit_conversion_logs (category, from_unit, to_unit, input_value, result_value, logged_by) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $_POST['category'] ?? '',
    $_POST['from_unit'] ?? '',
    $_POST['to_unit'] ?? '',
    (float)($_POST['input_value'] ?? 0),
    (float)($_POST['result_value'] ?? 0),
    $_SESSION['user_name'] ?? 'Staff'
  ]);
  redirect(app_url('modules/tools/unit_converter.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
  require_csrf();
  if (!is_admin() && !has_permission('settings.manage')) {
    redirect(app_url('modules/tools/unit_converter.php'));
  }
  $pdo->query("DELETE FROM unit_conversion_logs");
  redirect(app_url('modules/tools/unit_converter.php'));
}

$active_page = 'unit_converter';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Tools', 'Unit Converter'];

$logs = [];
try {
  $stmt = $pdo->query("SELECT * FROM unit_conversion_logs ORDER BY created_at DESC LIMIT 15");
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
  <title data-en="Engineering Unit Converter | I-GAS Industrial" data-ar="محول الوحدات الهندسية | I-GAS Industrial">Engineering Unit Converter | I-GAS Industrial</title>
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
      <span data-en="Units Calibration Core Online" data-ar="محرك معايرة الوحدات متصل">Units Calibration Core Online</span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Precision Calibration:" data-ar="دقة المعايرة:">Precision Calibration:</span> <span class="font-mono text-teal-600 dark:text-teal-400 font-semibold">NIST / SI Metric Standard</span>
    </span>
    <span class="w-px h-3 bg-[#1a3346]"></span>
    <span class="text-slate-400 uppercase tracking-wide">
      <span data-en="Engine Status:" data-ar="حالة المحرك:">Engine Status:</span> <span class="font-mono text-slate-200">ACTIVE</span>
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
          <span class="text-[11px] font-mono font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400" data-en="Unit Converter" data-ar="محول الوحدات">Unit Converter</span>
        </div>
        <h1 class="text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Industrial Unit Converter" data-ar="محول الوحدات الهندسية والصناعية">Industrial Unit Converter</h1>
        <p class="text-xs text-slate-400 mt-1 font-mono" data-en="High-precision conversion engine for pressure, volume, mass, temperature, and industrial volumetric flow." data-ar="محرك تحويل عالي الدقة لوحدات الضغط والحجم والكتلة ودرجة الحرارة والتدفق الحجمي الصناعي.">High-precision conversion engine for pressure, volume, mass, temperature, and industrial volumetric flow.</p>
      </div>
      <div>
        <a href="<?= app_url('modules/tools/gas_converter.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 border border-[#1a3346] bg-[#0c1924] text-xs font-mono text-slate-300 hover:bg-[#102332] dark:hover:bg-neutral-800 transition-colors">
          <i data-lucide="wind" class="w-3.5 h-3.5 text-neutral-500"></i>
          <span data-en="Gas Density & Mass Converter" data-ar="محول كثافة وكتلة الغاز">Gas Density & Mass Converter</span>
        </a>
      </div>
    </div>

    <!-- 2-Column Responsive Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      
      <!-- Left Col (1 col): Interactive Converter Panel -->
      <div class="lg:col-span-1 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] p-6 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between pb-4 mb-5 border-b border-[#1a3346]">
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white flex items-center gap-2">
              <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <span data-en="Conversion Parameters" data-ar="معايير التحويل">Conversion Parameters</span>
            </h3>
            <span class="text-[11px] font-mono uppercase px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-400">UNIT-01</span>
          </div>

          <!-- Category Selector Pills -->
          <div class="flex flex-wrap gap-1.5 mb-6" id="categoryContainer">
            <button type="button" class="cat-btn px-3 py-1.5 border border-cyan-800/80 bg-[#0d2a38] text-cyan-400 font-bold text-xs font-mono transition-colors" data-cat="Pressure" data-en="Pressure" data-ar="الضغط">Pressure</button>
            <button type="button" class="cat-btn px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-slate-300 text-xs font-mono font-medium hover:bg-[#102332] transition-colors" data-cat="Volume" data-en="Volume" data-ar="الحجم">Volume</button>
            <button type="button" class="cat-btn px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-slate-300 text-xs font-mono font-medium hover:bg-[#102332] transition-colors" data-cat="Mass" data-en="Mass" data-ar="الكتلة">Mass</button>
            <button type="button" class="cat-btn px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-slate-300 text-xs font-mono font-medium hover:bg-[#102332] transition-colors" data-cat="Temperature" data-en="Temperature" data-ar="الحرارة">Temperature</button>
            <button type="button" class="cat-btn px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-slate-300 text-xs font-mono font-medium hover:bg-[#102332] transition-colors" data-cat="Flow" data-en="Flow Rate" data-ar="التدفق">Flow Rate</button>
          </div>

          <!-- Input Magnitude & From Unit -->
          <div class="mb-4">
            <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="From (Source Magnitude)" data-ar="من (المقدار المصدر)">From (Source Magnitude)</label>
            <div class="flex gap-2">
              <input type="number" id="inputVal" value="1" step="any" class="w-2/3 border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 font-mono text-xs px-3 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 font-semibold" placeholder="1.0">
              <select id="fromUnit" class="w-1/3 border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 font-mono"></select>
            </div>
          </div>

          <!-- Swap Button -->
          <div class="flex justify-center my-3">
            <button type="button" id="swapBtn" class="w-8 h-8 border border-[#1a3346] bg-[#09151f] text-neutral-600 text-slate-300 flex items-center justify-center hover:bg-[#102230] dark:hover:bg-neutral-800 transition-colors" title="Swap Units">
              <i data-lucide="arrow-down-up" class="w-4 h-4"></i>
            </button>
          </div>

          <!-- Result Magnitude & To Unit -->
          <div class="mb-6">
            <label class="block text-[11px] font-mono font-semibold uppercase tracking-wider text-slate-400 mb-1.5" data-en="To (Converted Result)" data-ar="إلى (النتيجة المحولة)">To (Converted Result)</label>
            <div class="flex gap-2">
              <input type="text" id="resultVal" readonly class="w-2/3 border border-[#1a3346] bg-[#09151f] text-teal-600 dark:text-teal-400 font-mono text-xs px-3 py-2 outline-none font-bold" value="100000">
              <select id="toUnit" class="w-1/3 border border-[#1a3346] bg-[#08131c] bg-[#0c1924] text-slate-100 placeholder-slate-500 text-xs px-2 py-2 outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 font-mono"></select>
            </div>
          </div>
        </div>

        <!-- Sole Primary CTA in Safety Valve Orange -->
        <form method="POST" action="<?= app_url('modules/tools/unit_converter.php') ?>" class="pt-4 border-t border-[#1a3346]">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="log_conversion">
          <input type="hidden" name="category" id="formCat">
          <input type="hidden" name="from_unit" id="formFrom">
          <input type="hidden" name="to_unit" id="formTo">
          <input type="hidden" name="input_value" id="formInput">
          <input type="hidden" name="result_value" id="formResult">
          <button type="submit" class="btn-cta-orange w-full py-2.5 text-xs font-semibold font-oxanium uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer transition-colors">
            <i data-lucide="save" class="w-4 h-4"></i>
            <span data-en="Save to Logs" data-ar="حفظ في السجلات">Save to Logs</span>
          </button>
        </form>
      </div>

      <!-- Right Col (2 cols): Recent Conversions Ledger -->
      <div class="lg:col-span-2 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] overflow-hidden">
        <div class="p-5 border-b border-[#1a3346] flex items-center justify-between">
          <div>
            <h3 class="text-sm font-semibold font-oxanium tracking-tight text-white" data-en="Recent Conversions Matrix" data-ar="سجل التحويلات الأخيرة">Recent Conversions Matrix</h3>
            <p class="text-xs text-slate-400 font-mono mt-0.5" data-en="Engine audit log of engineering conversions" data-ar="سجل تدقيق المعايرات الهندسية">Engine audit log of engineering conversions</p>
          </div>
          <?php if (is_admin() || has_permission('settings.manage')): ?>
          <form method="POST" action="<?= app_url('modules/tools/unit_converter.php') ?>" onsubmit="return confirm('Clear all unit conversion logs? / مسح كافة سجلات التحويل؟');">
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
                <th class="pl-6 py-3 font-semibold" data-en="Category" data-ar="الفئة">Category</th>
                <th class="px-4 py-3 font-semibold" data-en="Input Magnitude" data-ar="القيمة المدخلة">Input Magnitude</th>
                <th class="px-4 py-3 font-semibold" data-en="Converted Result" data-ar="النتيجة المحولة">Converted Result</th>
                <th class="px-4 py-3 font-semibold" data-en="Operator" data-ar="المشغل">Operator</th>
                <th class="pr-6 py-3 font-semibold text-right" data-en="Timestamp" data-ar="التوقيت">Timestamp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#152a3a]">
              <?php if (empty($logs)): ?>
              <tr>
                <td colspan="5" class="text-center py-8 text-slate-400 font-mono text-xs" data-en="No unit conversion logs recorded yet. Use the converter above to save entries." data-ar="لا توجد سجلات تحويل مسجلة بعد. استخدم المحول أعلاه لحفظ المدخلات.">
                  No unit conversion logs recorded yet. Use the converter above to save entries.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-[#102332] transition-colors">
                  <td class="pl-6 py-3.5">
                    <span class="inline-flex items-center px-2 py-0.5 border border-[#1a3346] bg-[#09151f] text-slate-200 font-mono text-[10px] uppercase">
                      <?= htmlspecialchars($log['category']) ?>
                    </span>
                  </td>
                  <td class="px-4 py-3.5 font-mono text-slate-200">
                    <span class="font-bold text-white"><?= floatval($log['input_value']) ?></span> <?= htmlspecialchars($log['from_unit']) ?>
                  </td>
                  <td class="px-4 py-3.5 font-mono font-bold text-teal-600 dark:text-teal-400">
                    <?= floatval($log['result_value']) ?> <span class="font-normal text-neutral-500"><?= htmlspecialchars($log['to_unit']) ?></span>
                  </td>
                  <td class="px-4 py-3.5 text-slate-400 font-mono text-[11px]">
                    <?= htmlspecialchars($log['logged_by']) ?>
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

  </div>
</main>

<script>
  lucide.createIcons();

  const unitsData = {
    Pressure: { "bar": 100000, "psi": 6894.757, "Pa": 1, "kPa": 1000, "MPa": 1000000, "mbar": 100, "atm": 101325, "mmHg": 133.322 },
    Volume: { "m³": 1, "L": 0.001, "mL": 0.000001, "ft³": 0.0283168, "gal(US)": 0.00378541, "Nm³": 1 },
    Mass: { "kg": 1, "tonne": 1000, "lb": 0.4535924, "g": 0.001, "oz": 0.02834952, "ton(US)": 907.1847 },
    Temperature: { "°C": "C", "°F": "F", "K": "K" },
    Flow: { "m³/h": 1, "L/min": 0.06, "L/s": 3.6, "CFM": 1.699, "Nm³/h": 1, "Sm³/h": 0.948 }
  };

  const catBtns = document.querySelectorAll('.cat-btn');
  const fromUnit = document.getElementById('fromUnit');
  const toUnit = document.getElementById('toUnit');
  const inputVal = document.getElementById('inputVal');
  const resultVal = document.getElementById('resultVal');
  const swapBtn = document.getElementById('swapBtn');

  const formCat = document.getElementById('formCat');
  const formFrom = document.getElementById('formFrom');
  const formTo = document.getElementById('formTo');
  const formInput = document.getElementById('formInput');
  const formResult = document.getElementById('formResult');

  let currentCat = 'Pressure';

  function populateSelects() {
    const units = Object.keys(unitsData[currentCat]);
    fromUnit.innerHTML = '';
    toUnit.innerHTML = '';
    units.forEach(u => {
      fromUnit.add(new Option(u, u));
      toUnit.add(new Option(u, u));
    });
    if (units.length > 1) {
      toUnit.selectedIndex = 1;
    }
    calculate();
  }

  function convertTemp(val, from, to) {
    let c;
    if (from === '°F') c = (val - 32) * 5/9;
    else if (from === 'K') c = val - 273.15;
    else c = val;

    if (to === '°F') return (c * 9/5) + 32;
    else if (to === 'K') return c + 273.15;
    else return c;
  }

  function calculate() {
    const v = parseFloat(inputVal.value) || 0;
    const fu = fromUnit.value;
    const tu = toUnit.value;
    let res = 0;

    if (currentCat === 'Temperature') {
      res = convertTemp(v, fu, tu);
    } else {
      const map = unitsData[currentCat];
      const baseVal = v * (map[fu] || 1);
      res = baseVal / (map[tu] || 1);
    }

    let finalRes = Number.isInteger(res) ? res : parseFloat(res.toFixed(6));
    resultVal.value = finalRes;

    formCat.value = currentCat;
    formFrom.value = fu;
    formTo.value = tu;
    formInput.value = v;
    formResult.value = finalRes;
  }

  catBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      catBtns.forEach(b => {
        b.className = 'cat-btn px-3 py-1.5 border border-[#1a3346] bg-[#0c1924] text-slate-300 text-xs font-mono font-medium hover:bg-[#102332] transition-colors';
      });
      btn.className = 'cat-btn px-3 py-1.5 border border-cyan-800/80 bg-[#0d2a38] text-cyan-400 font-bold text-xs font-mono transition-colors';
      currentCat = btn.getAttribute('data-cat');
      populateSelects();
    });
  });

  fromUnit.addEventListener('change', calculate);
  toUnit.addEventListener('change', calculate);
  inputVal.addEventListener('input', calculate);

  swapBtn.addEventListener('click', () => {
    const temp = fromUnit.value;
    fromUnit.value = toUnit.value;
    toUnit.value = temp;
    calculate();
  });

  populateSelects();
</script>
</body>
</html>
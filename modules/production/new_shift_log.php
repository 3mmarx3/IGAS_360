<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.create');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $shift_id = $_POST['shift_id'] ?? ('SHF-' . date('md') . '-' . rand(100, 999));
  $operational_date = $_POST['operational_date'] ?? date('Y-m-d');
  $shift_cycle = $_POST['shift_cycle'] ?? '';
  $supervisor = $_POST['supervisor'] ?? '';
  $operators_count = (int)($_POST['operators_count'] ?? 0);
  $primary_focus = $_POST['primary_focus'] ?? 'Mixed Production';
  $target_output = (int)($_POST['target_output'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');

  $cryo_checked = isset($_POST['cryo_checked']) ? 1 : 0;
  $manifolds_checked = isset($_POST['manifolds_checked']) ? 1 : 0;
  $ventilation_checked = isset($_POST['ventilation_checked']) ? 1 : 0;
  $ppe_checked = isset($_POST['ppe_checked']) ? 1 : 0;

  if (empty($shift_cycle) || empty($supervisor) || $operators_count <= 0) {
    $error_message = "يرجى اختيار الوردية، المشرف، وعدد العمال بشكل صحيح. / Please select shift cycle, supervisor, and valid operator headcount.";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO shift_logs 
        (shift_id, operational_date, shift_cycle, supervisor, operators_count, primary_focus, target_output, notes, cryo_checked, manifolds_checked, ventilation_checked, ppe_checked, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_progress')
      ");
      $stmt->execute([
        $shift_id, $operational_date, $shift_cycle, $supervisor, $operators_count, 
        $primary_focus, $target_output, $notes, $cryo_checked, $manifolds_checked, 
        $ventilation_checked, $ppe_checked
      ]);
      
      redirect(app_url('modules/production/daily_log_shifts.php'));
    } catch (Exception $e) {
      $error_message = "حدث خطأ أثناء حفظ الوردية: " . $e->getMessage();
    }
  }
}

$active_page = 'daily_log_shifts';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Daily Shift Logs', 'New Shift Entry'];

$new_shift_id = 'SHF-' . date('md') . '-' . rand(100, 999); 

$supStmt = $pdo->query("SELECT full_name FROM users WHERE status = 'Active' ORDER BY full_name ASC");
$supervisors = $supStmt ? $supStmt->fetchAll(PDO::FETCH_COLUMN) : [];

try {
  $focusStmt = $pdo->query("
    SELECT category_name as focus_name FROM gas_categories 
    UNION 
    SELECT product_name as focus_name FROM gas_mixtures 
    ORDER BY focus_name ASC
  ");
  $focus_options = $focusStmt ? $focusStmt->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (Exception $e) {
  $focus_options = ['Liquid Oxygen (LOX)', 'Liquid Nitrogen (LIN)', 'Compressed Argon', 'CO2 Industrial', 'Acetylene High Pressure'];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Start New Shift | Production | I-GAS Control Room</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Oxanium:wght@400;500;600;700;800&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: {
              DEFAULT: '#0B6E8A',
              bright: '#19B5C9',
              dark: '#0A1B26',
            },
            safety: {
              orange: '#FF6B1A',
            },
          },
          fontFamily: {
            sans: ['IBM Plex Sans', 'Cairo', 'sans-serif'],
            heading: ['Oxanium', 'Reem Kufi', 'sans-serif'],
            mono: ['IBM Plex Mono', 'monospace'],
          },
        }
      }
    }
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">
  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Industrial Telemetry Sub-header -->
  <div class="h-9 border-b border-[#1a3346] bg-[#102230]/80 bg-[#0c1924]/80 flex items-center px-4 sm:px-6 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px]">
    <span class="flex items-center gap-1.5 font-mono uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      FILLING MANIFOLD DISPATCH // PRE-SHIFT INITIALIZATION
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">PLANT: JEDDAH BULK REFINERY #4</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">SAFETY INTERLOCK: NOMINAL</span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Error Notification Banner -->
    <?php if (!empty($error_message)): ?>
    <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-mono flex items-center gap-3">
      <?= icon('alert-circle', 'w-5 h-5 flex-shrink-0') ?>
      <span><?= htmlspecialchars($error_message) ?></span>
    </div>
    <?php endif; ?>

    <!-- Page Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono text-slate-400 mb-1">
            <a href="<?= app_url('modules/production/daily_log_shifts.php') ?>" class="text-teal-600 dark:text-teal-400 hover:underline">DAILY SHIFT LOGS</a>
            <span>/</span>
            <span>NEW SHIFT ENTRY</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
            <?= icon('play-circle', 'w-6 h-6 text-teal-600 dark:text-teal-400') ?>
            <span>Initialize Shift Log</span>
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            Record starting parameters, assign personnel, and complete pre-shift safety handovers.
          </p>
        </div>

        <!-- Action Controls: Cancel & Single Primary Orange CTA -->
        <div class="flex items-center gap-3">
          <a href="<?= app_url('modules/production/daily_log_shifts.php') ?>" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('x', 'w-4 h-4') ?>
            <span>Cancel</span>
          </a>
          <button type="submit" form="shift-form" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('play', 'w-4 h-4') ?>
            <span>Start Operational Shift</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Shift Form Grid -->
    <form action="" method="POST" id="shift-form">
      <?= csrf_field() ?>
      <input type="hidden" name="shift_id" value="<?= htmlspecialchars($new_shift_id) ?>">

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Time, Personnel, and Targets -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Time & Personnel Assignment Card -->
          <div class="border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('clock', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Time & Personnel Assignment
              </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Operational Date *</label>
                <input type="date" name="operational_date" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" value="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Shift Cycle *</label>
                <select name="shift_cycle" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                  <option value="" selected disabled>Select assigned block...</option>
                  <option value="Morning">Morning (06:00 - 14:00)</option>
                  <option value="Evening">Evening (14:00 - 22:00)</option>
                  <option value="Night">Night (22:00 - 06:00)</option>
                </select>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Shift Supervisor / Foreperson *</label>
                <select name="supervisor" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required>
                  <option value="" selected disabled>Identify lead supervisor...</option>
                  <?php foreach ($supervisors as $sup): ?>
                    <option value="<?= htmlspecialchars($sup) ?>"><?= htmlspecialchars($sup) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Active Operators (Headcount) *</label>
                <input type="number" name="operators_count" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 tabular-nums" placeholder="4" min="1" required>
              </div>
            </div>
          </div>

          <!-- Production Targets Card -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('target', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Production Targets & Brief
              </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Primary Gas Focus</label>
                <select name="primary_focus" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                  <option value="Mixed Production Lines" selected>Mixed Production Lines</option>
                  <?php foreach ($focus_options as $focus): ?>
                    <option value="<?= htmlspecialchars($focus) ?>"><?= htmlspecialchars($focus) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Target Cylinder Output (Qty)</label>
                <input type="number" name="target_output" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 tabular-nums" placeholder="e.g. 150" min="0">
              </div>

              <div class="col-span-2">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Shift Notes & Handover Brief</label>
                <textarea name="notes" rows="4" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="Log any pending issues from the previous shift, line pressure notes, maintenance tickets, or special fill requests..."></textarea>
              </div>
            </div>
          </div>

        </div>

        <!-- Right 1 Col: System Context & Pre-Shift Safety Checklist -->
        <div class="space-y-6">

          <!-- System Allocation Context -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('hash', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                System Context
              </h2>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Generated Shift ID</label>
                <input type="text" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-neutral-600 text-slate-300 cursor-not-allowed" value="<?= htmlspecialchars($new_shift_id) ?>" disabled>
              </div>
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Initial Status Context</label>
                <input type="text" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-amber-600 dark:text-amber-400 font-bold cursor-not-allowed" value="In Progress (Active)" disabled>
              </div>
            </div>
          </div>

          <!-- Pre-Shift Safety Checklist -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('shield-check', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Pre-Shift Safety Check
              </h2>
            </div>

            <div class="space-y-3 text-xs font-mono">
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="cryo_checked" value="1" class="mt-0.5 w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Cryogenic storage pressure verified within safe envelope</span>
              </label>
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="manifolds_checked" value="1" class="mt-0.5 w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Filling manifolds visually inspected for valve leaks</span>
              </label>
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="ventilation_checked" value="1" class="mt-0.5 w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Ventilation & exhaust blowers active and nominal</span>
              </label>
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="ppe_checked" value="1" class="mt-0.5 w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Crew cryogenic PPE gear inspected and equipped</span>
              </label>
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</main>

<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }
</script>
</body>
</html>
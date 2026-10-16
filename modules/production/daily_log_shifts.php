<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'daily_log_shifts';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Daily Shift Logs'];

$totalStmt = $pdo->query("SELECT COUNT(*) FROM shift_logs");
$total_shifts = $totalStmt->fetchColumn() ?: 0;

$activeStmt = $pdo->query("SELECT shift_cycle FROM shift_logs WHERE status = 'in_progress' ORDER BY id DESC LIMIT 1");
$active_cycle = $activeStmt->fetchColumn();
$active_shift = $active_cycle ? $active_cycle . ' Shift' : 'None';
$active_shift_ar = $active_cycle ? ($active_cycle === 'Morning' ? 'الوردية الصباحية' : ($active_cycle === 'Evening' ? 'الوردية المسائية' : 'وردية الليل')) : 'لا توجد';

$outputStmt = $pdo->query("SELECT SUM(actual_output) FROM shift_logs WHERE DATE_FORMAT(operational_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')");
$total_output = $outputStmt->fetchColumn() ?: 0;

$alertsStmt = $pdo->query("SELECT SUM(safety_alerts) FROM shift_logs WHERE DATE_FORMAT(operational_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')");
$safety_alerts = $alertsStmt->fetchColumn() ?: 0;

$shiftsStmt = $pdo->query("SELECT * FROM shift_logs ORDER BY operational_date DESC, id DESC");
$shifts = $shiftsStmt->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
  'in_progress' => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30', 'dot' => 'bg-amber-500', 'label' => 'Active Shift', 'label_ar' => 'وردية نشطة'],
  'completed'  => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'Signed Off', 'label_ar' => 'تم الاعتماد'],
  'flagged'   => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30', 'dot' => 'bg-rose-500', 'label' => 'Flagged / Issues', 'label_ar' => 'مبلغ / مشاكل'],
];

function getShiftTimeFormat($cycle) {
  if ($cycle === 'Morning') return 'Morning (06:00 - 14:00)';
  if ($cycle === 'Evening') return 'Evening (14:00 - 22:00)';
  if ($cycle === 'Night') return 'Night (22:00 - 06:00)';
  return $cycle;
}

function getShiftTimeFormatAr($cycle) {
  if ($cycle === 'Morning') return 'الصباحية (06:00 - 14:00)';
  if ($cycle === 'Evening') return 'المسائية (14:00 - 22:00)';
  if ($cycle === 'Night') return 'الليلية (22:00 - 06:00)';
  return $cycle;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daily Shift Logs | Production | I-GAS Control Room</title>
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
      PLANT OPERATIONS NOMINAL // SHIFT MONITOR ONLINE
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">PLANT: JEDDAH INDUSTRIAL BULK #4</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">CURRENT CYCLE: <?= htmlspecialchars($active_shift) ?></span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Page Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            PRODUCTION OPERATIONS // SHIFT MASTER REGISTRY
          </div>
          <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
            <?= icon('clock', 'w-6 h-6 text-teal-600 dark:text-teal-400') ?>
            <span>Daily Shift Logs</span>
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            Monitor plant operational shifts, production outputs, and safety handovers.
          </p>
        </div>

        <!-- Single Primary CTA in Safety Valve Orange -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="exportLogsData()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <?= icon('download', 'w-4 h-4') ?>
            <span>Export Logs</span>
          </button>
          <?php if (has_permission('production.create')): ?>
          <a href="<?= app_url('modules/production/new_shift_log.php') ?>" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <?= icon('plus', 'w-4 h-4') ?>
            <span>Start New Shift</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Current Active Shift -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">ACTIVE SHIFT</span>
          <?= icon('activity', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-xl font-bold font-heading uppercase text-white">
          <?= htmlspecialchars($active_shift) ?>
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs font-mono">
          <?php if ($active_shift !== 'None'): ?>
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs text-[11px] font-mono border bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30 whitespace-nowrap">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
              Logging in Progress
            </span>
          <?php else: ?>
            <span class="text-neutral-500">No shift currently active</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Production Output MTD -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">OUTPUT (MTD)</span>
          <?= icon('cylinder', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($total_output) ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Cylinders filled this month
        </div>
      </div>

      <!-- Total Shifts Logged -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">TOTAL SHIFTS</span>
          <?= icon('archive', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($total_shifts) ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Historical logs archived
        </div>
      </div>

      <!-- Safety Alerts MTD -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-[3px] <?= $safety_alerts > 0 ? 'border-s-rose-600' : 'border-s-emerald-600' ?>">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">SAFETY ALERTS (MTD)</span>
          <?= icon('shield-alert', 'w-4 h-4 ' . ($safety_alerts > 0 ? 'text-rose-600' : 'text-emerald-600')) ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums <?= $safety_alerts > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
          <?= number_format($safety_alerts) ?>
        </div>
        <div class="mt-3 text-xs font-mono">
          <?php if ($safety_alerts > 0): ?>
            <span class="text-rose-600 font-semibold flex items-center gap-1">
              <?= icon('alert-triangle', 'w-3.5 h-3.5') ?>
              Action required
            </span>
          <?php else: ?>
            <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
              <?= icon('check-circle', 'w-3.5 h-3.5') ?>
              Zero safety incidents
            </span>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Master Shift Registry Table Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
      
      <!-- Table Header & Controls -->
      <div class="p-4 border-b border-[#1a3346] bg-[#09151f] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white flex items-center gap-2">
            <?= icon('list', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <span>Shift Handover Logs</span>
          </h2>
          <span class="text-xs font-mono text-neutral-500">(<span id="visibleCount" class="tabular-nums font-semibold text-slate-200"><?= count($shifts) ?></span> records)</span>
        </div>

        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search Shift ID, Supervisor..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="shiftFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="">All Shifts</option>
            <option value="Morning">Morning Shift</option>
            <option value="Evening">Evening Shift</option>
            <option value="Night">Night Shift</option>
          </select>
        </div>
      </div>

      <!-- Table Responsive Container -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left border-collapse text-xs" id="shiftsTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
              <th class="py-3 px-4">SHIFT REF</th>
              <th class="py-3 px-4">DATE & CYCLE</th>
              <th class="py-3 px-4">SUPERVISOR</th>
              <th class="py-3 px-4 text-right">CYLINDERS FILLED</th>
              <th class="py-3 px-4 text-center">ALERTS</th>
              <th class="py-3 px-4">STATUS</th>
              <th class="py-3 px-4 text-right">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] font-mono">
            <?php foreach ($shifts as $s): ?>
            <?php
              $statusKey = $s['status'] ?? 'in_progress';
              $statusObj = $statusStyles[$statusKey] ?? $statusStyles['in_progress'];
            ?>
            <tr class="hover:bg-[#102332] transition shift-row" data-cycle="<?= htmlspecialchars($s['shift_cycle']) ?>">
              <td class="py-3 px-4 font-semibold text-white shift-id">
                <?= htmlspecialchars($s['shift_id']) ?>
              </td>
              <td class="py-3 px-4">
                <div class="font-medium text-slate-200 date-text">
                  <?= htmlspecialchars($s['operational_date']) ?>
                </div>
                <div class="text-[11px] text-neutral-500 cycle-text">
                  <?= htmlspecialchars(getShiftTimeFormat($s['shift_cycle'])) ?>
                </div>
              </td>
              <td class="py-3 px-4 font-medium text-slate-200 supervisor-text">
                <div class="flex items-center gap-2">
                  <div class="w-5 h-5 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center text-[10px] font-bold text-neutral-600 text-slate-300">
                    <?= htmlspecialchars(mb_substr($s['supervisor'] ?? 'U', 0, 1)) ?>
                  </div>
                  <span><?= htmlspecialchars($s['supervisor']) ?></span>
                </div>
              </td>
              <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                <?= number_format($s['actual_output']) ?>
              </td>
              <td class="py-3 px-4 text-center">
                <?php if ($s['safety_alerts'] > 0): ?>
                  <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-xs text-[11px] font-bold border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 tabular-nums">
                    <?= (int)$s['safety_alerts'] ?>
                  </span>
                <?php else: ?>
                  <span class="text-neutral-400">-</span>
                <?php endif; ?>
              </td>
              <td class="py-3 px-4">
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs text-[11px] font-mono border <?= $statusObj['bg'] ?>">
                  <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                  <?= $statusObj['label'] ?>
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <a href="<?= app_url('modules/production/shift_details.php?id=' . urlencode($s['shift_id'])) ?>" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline font-semibold">
                  <span>View Log</span>
                  <?= icon('chevron-right', 'w-3.5 h-3.5 rtl:rotate-180') ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($shifts)): ?>
            <tr id="emptyStateRow">
              <td colspan="7" class="py-8 text-center text-slate-400">
                No shift logs recorded yet.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Pagination / Summary -->
      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
        <span>Showing 1 to <span id="visibleSummary"><?= count($shifts) ?></span> of <?= count($shifts) ?> shift records</span>
        <span class="text-[11px] text-neutral-400">Real-time sync active</span>
      </div>
    </div>

  </div>
</main>

<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const shiftFilter = document.getElementById('shiftFilter');
    const rows = document.querySelectorAll('.shift-row');

    function filterRows() {
      const query = (searchInput.value || '').toLowerCase().trim();
      const selectedCycle = shiftFilter.value;
      let visibleCount = 0;

      rows.forEach(row => {
        const shiftIdEl = row.querySelector('.shift-id');
        const supervisorEl = row.querySelector('.supervisor-text');
        const dateEl = row.querySelector('.date-text');

        const shiftIdText = shiftIdEl ? shiftIdEl.innerText.toLowerCase() : '';
        const supervisorText = supervisorEl ? supervisorEl.innerText.toLowerCase() : '';
        const dateText = dateEl ? dateEl.innerText.toLowerCase() : '';
        const rowCycle = row.getAttribute('data-cycle');

        const matchesSearch = !query || shiftIdText.includes(query) || supervisorText.includes(query) || dateText.includes(query);
        const matchesCycle = !selectedCycle || rowCycle === selectedCycle;

        if (matchesSearch && matchesCycle) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      const visibleCountEl = document.getElementById('visibleCount');
      if (visibleCountEl) visibleCountEl.textContent = visibleCount;
      const visibleSummaryEl = document.getElementById('visibleSummary');
      if (visibleSummaryEl) visibleSummaryEl.textContent = visibleCount;

      const emptyRow = document.getElementById('emptyStateRow');
      if (emptyRow) {
        emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
      }
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (shiftFilter) shiftFilter.addEventListener('change', filterRows);
  });

  function exportLogsData() {
    let csv = [];
    let rows = document.querySelectorAll("#shiftsTable tr");
    
    rows.forEach(row => {
      if (row.style.display === 'none') return;
      let cols = row.querySelectorAll("td, th");
      let data = [];
      for (let i = 0; i < cols.length - 1; i++) {
        data.push('"' + cols[i].innerText.replace(/"/g, '""').trim() + '"');
      }
      if (data.length > 0) {
        csv.push(data.join(","));
      }
    });

    let csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "shift_logs_export_" + new Date().toISOString().slice(0, 10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>
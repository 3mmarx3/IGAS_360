<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('reports.view');

$active_page = 'production_reports';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Production Analytics Reports'];

$production_data = [];
$monthly_cylinders = 0;
$monthly_bulk = 0;
$ytd_cylinders = 0;
$ytd_bulk = 0;

try {
  $prodStmt = $pdo->query("
    SELECT 
      DATE_FORMAT(operational_date, '%Y-%m') as period_key,
      DATE_FORMAT(operational_date, '%M %Y') as period_month,
      primary_focus as gas_type,
      SUM(actual_output) as total_cylinders,
      SUM(target_output) as total_target
    FROM shift_logs
    GROUP BY DATE_FORMAT(operational_date, '%Y-%m'), primary_focus
    ORDER BY operational_date DESC
  ");
  $raw_prod = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($raw_prod as $row) {
    $cyls = (int)$row['total_cylinders'];
    $target = (int)$row['total_target'];
    $pct = $target > 0 ? round(($cyls / $target) * 100) : 100;
    
    $status = 'on_track';
    if ($pct > 105) $status = 'exceeded';
    elseif ($pct < 80) $status = 'underperforming';
    elseif ($pct < 95) $status = 'warning';

    $production_data[] = [
      'period_key'  => $row['period_key'],
      'gas'     => $row['gas_type'] ?: 'Mixed Production',
      'month'    => $row['period_month'],
      'cylinders'  => $cyls,
      'bulk_liters' => $cyls * 90,
      'target_pct'  => $pct,
      'status'    => $status
    ];
  }

  $currMonth = date('Y-m');
  $currYear = date('Y');

  $mCylStmt = $pdo->prepare("SELECT SUM(actual_output) FROM shift_logs WHERE DATE_FORMAT(operational_date, '%Y-%m') = ?");
  $mCylStmt->execute([$currMonth]);
  $monthly_cylinders = (int)$mCylStmt->fetchColumn();

  $yCylStmt = $pdo->prepare("SELECT SUM(actual_output) FROM shift_logs WHERE YEAR(operational_date) = ?");
  $yCylStmt->execute([$currYear]);
  $ytd_cylinders = (int)$yCylStmt->fetchColumn();

  $monthly_bulk = $monthly_cylinders * 90;
  $ytd_bulk = $ytd_cylinders * 90;

} catch (Exception $e) {
  $production_data = [];
}

$kpi_stats = [
  'monthly_cylinders' => $monthly_cylinders,
  'monthly_bulk'   => $monthly_bulk,
  'ytd_cylinders'   => $ytd_cylinders,
  'ytd_bulk'     => $ytd_bulk
];

$statusStyles = [
  'on_track'    => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'On Track'],
  'exceeded'    => ['bg' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/30',       'dot' => 'bg-cyan-500',  'label' => 'Exceeded Target'],
  'warning'     => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',    'dot' => 'bg-amber-500',  'label' => 'Below Target'],
  'underperforming' => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30',     'dot' => 'bg-rose-500',  'label' => 'Underperforming']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Reports | Analytics | I-GAS Control Room</title>
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
      PRODUCTION ANALYTICS // TELEMETRY DATABASE ONLINE
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">PLANT: JEDDAH INDUSTRIAL #4</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">ANNUAL RUN: <?= date('Y') ?></span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Page Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            PERFORMANCE ANALYTICS // FILLING RUN ARCHIVE
          </div>
          <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
            <?= icon('file-text', 'w-6 h-6 text-teal-600 dark:text-teal-400') ?>
            <span>Production Reports</span>
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            Analyze plant output, cylinder filling metrics, and bulk gas processing volumes.
          </p>
        </div>

        <!-- Action Controls: One Primary Orange CTA -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('printer', 'w-4 h-4') ?>
            <span>Print Dossier</span>
          </button>
          <?php if (has_permission('reports.export')): ?>
          <button type="button" onclick="exportProductionData()" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('download', 'w-4 h-4') ?>
            <span>Export CSV Dossier</span>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Monthly Cylinders Filled -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">MONTHLY CYLINDERS</span>
          <?= icon('cylinder', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($kpi_stats['monthly_cylinders']) ?>
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs font-mono text-emerald-600 dark:text-emerald-400">
          <?= icon('trending-up', 'w-3.5 h-3.5') ?>
          <span>Live Database Output</span>
        </div>
      </div>

      <!-- Monthly Bulk (Liters) -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">MONTHLY BULK (L)</span>
          <?= icon('droplet', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($kpi_stats['monthly_bulk']) ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Calculated bulk liquid volume
        </div>
      </div>

      <!-- YTD Cylinders -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">YTD CYLINDERS</span>
          <?= icon('archive', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($kpi_stats['ytd_cylinders']) ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Total filled units in <?= date('Y') ?>
        </div>
      </div>

      <!-- YTD Bulk Processed -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">YTD BULK PROCESSED</span>
          <?= icon('activity', 'w-4 h-4 text-emerald-600 dark:text-emerald-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-emerald-600 dark:text-emerald-400">
          <?= number_format($kpi_stats['ytd_bulk']) ?> <span class="text-xs font-normal text-neutral-500">L</span>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Annual liquefied gas output
        </div>
      </div>

    </div>

    <!-- Master Production Breakdown Table Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
      
      <!-- Table Header & Controls -->
      <div class="p-4 border-b border-[#1a3346] bg-[#09151f] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white flex items-center gap-2">
            <?= icon('layers', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <span>Production Logs Matrix</span>
          </h2>
          <span class="text-xs font-mono text-neutral-500">(<span id="visibleCount" class="tabular-nums font-semibold text-slate-200"><?= count($production_data) ?></span> entries)</span>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <select id="yearFilter" class="bg-[#08131c] border border-[#1e3a50] rounded-xs px-3 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
            <option value="2026">Year 2026</option>
            <option value="2025">Year 2025</option>
          </select>
          <select id="gasFilter" class="bg-[#08131c] border border-[#1e3a50] rounded-xs px-3 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
            <option value="">All Gas Types</option>
            <option value="Oxygen">Oxygen</option>
            <option value="Nitrogen">Nitrogen</option>
            <option value="Argon">Argon</option>
            <option value="CO2">CO2</option>
            <option value="Acetylene">Acetylene</option>
          </select>
        </div>
      </div>

      <!-- Table Responsive Container -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs" id="productionTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
              <th class="py-3 px-4">PERIOD</th>
              <th class="py-3 px-4">GAS CLASSIFICATION</th>
              <th class="py-3 px-4 text-right">CYLINDERS FILLED</th>
              <th class="py-3 px-4 text-right">BULK VOLUME (L)</th>
              <th class="py-3 px-4">TARGET ACHIEVEMENT</th>
              <th class="py-3 px-4">PERFORMANCE STATUS</th>
              <th class="py-3 px-4 text-right">DETAILS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] font-mono">
            <?php foreach ($production_data as $row): ?>
            <?php
              $statusObj = $statusStyles[$row['status']] ?? $statusStyles['on_track'];
            ?>
            <tr class="hover:bg-[#102332] transition production-row" data-gas="<?= htmlspecialchars($row['gas']) ?>" data-year="<?= substr($row['period_key'], 0, 4) ?>">
              <td class="py-3 px-4 font-semibold text-white month-text">
                <?= htmlspecialchars($row['month']) ?>
              </td>
              <td class="py-3 px-4 font-medium text-white gas-text">
                <div class="flex items-center gap-2">
                  <?= gas_chip($row['gas']) ?>
                  <span><?= htmlspecialchars($row['gas']) ?></span>
                </div>
              </td>
              <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                <?= number_format($row['cylinders']) ?>
              </td>
              <td class="py-3 px-4 text-right tabular-nums text-slate-400">
                <?= number_format($row['bulk_liters']) ?>
              </td>
              <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                  <div class="w-20">
                    <?= level_bar(min($row['target_pct'], 100), 100, 'h-1.5') ?>
                  </div>
                  <span class="tabular-nums font-bold text-slate-200"><?= $row['target_pct'] ?>%</span>
                </div>
              </td>
              <td class="py-3 px-4">
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs text-[11px] font-mono border <?= $statusObj['bg'] ?>">
                  <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                  <?= $statusObj['label'] ?>
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <a href="daily_log_shifts.php" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline font-semibold" title="View Shift Logs">
                  <span>Logs</span>
                  <?= icon('chevron-right', 'w-3.5 h-3.5 rtl:rotate-180') ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($production_data)): ?>
            <tr id="emptyStateRow">
              <td colspan="7" class="py-8 text-center text-slate-400">
                No production log entries recorded for this period.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Summary -->
      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
        <span>Showing verified production logs from telemetry database</span>
        <span class="text-[11px] text-neutral-400">Precision: Tabular Monospace</span>
      </div>
    </div>

  </div>
</main>

<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  document.addEventListener('DOMContentLoaded', function() {
    const yearFilter = document.getElementById('yearFilter');
    const gasFilter = document.getElementById('gasFilter');
    const rows = document.querySelectorAll('.production-row');

    function filterRows() {
      const selectedYear = yearFilter ? yearFilter.value : '';
      const selectedGas = gasFilter ? gasFilter.value.toLowerCase() : '';
      let visibleCount = 0;

      rows.forEach(row => {
        const rowYear = row.getAttribute('data-year');
        const rowGas = (row.getAttribute('data-gas') || '').toLowerCase();

        const matchesYear = !selectedYear || rowYear === selectedYear;
        const matchesGas = !selectedGas || rowGas.includes(selectedGas);

        if (matchesYear && matchesGas) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      const visibleCountEl = document.getElementById('visibleCount');
      if (visibleCountEl) visibleCountEl.textContent = visibleCount;

      const emptyRow = document.getElementById('emptyStateRow');
      if (emptyRow) {
        emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
      }
    }

    if (yearFilter) yearFilter.addEventListener('change', filterRows);
    if (gasFilter) gasFilter.addEventListener('change', filterRows);
  });

  function exportProductionData() {
    let csv = [];
    let rows = document.querySelectorAll("#productionTable tr");
    
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
    downloadLink.download = "production_analytics_export_" + new Date().toISOString().slice(0, 10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>
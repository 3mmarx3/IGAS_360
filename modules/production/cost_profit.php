<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

if (!has_permission('finance.view') && !has_permission('reports.view')) {
  redirect(app_url('index.php'));
}

$active_page = 'cost_profit';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Financials', 'Cost & Profit Analysis'];

try {
  $stmt = $pdo->query("
    SELECT 
      gm.id,
      gm.product_name as gas,
      gm.category,
      gm.standard_cost,
      gm.selling_price,
      COALESCE(SUM(poi.qty), 0) as volume_sold,
      COALESCE(SUM(poi.line_total), 0) as actual_revenue
    FROM gas_mixtures gm
    LEFT JOIN purchase_order_items poi ON LOWER(gm.product_name) = LOWER(poi.item_name)
    LEFT JOIN purchase_orders po ON poi.purchase_order_id = po.id AND po.status != 'cancelled'
    GROUP BY gm.id, gm.product_name, gm.category, gm.standard_cost, gm.selling_price
    ORDER BY actual_revenue DESC, volume_sold DESC
  ");
  $db_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $db_data = [];
}

$financial_data = [];
$total_revenue = 0;
$total_cost = 0;

foreach ($db_data as $row) {
  $volume = (float)($row['volume_sold'] ?? 0);
  $revenue = (float)($row['actual_revenue'] ?? 0);
  $unit_cost = (float)($row['standard_cost'] ?? 0);
  $unit_price = (float)($row['selling_price'] ?? 0);
  
  $cost = $volume * $unit_cost;
  $profit = $revenue - $cost;
  $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
  
  if ($margin >= 35) $status = 'high_profit';
  elseif ($margin >= 20) $status = 'acceptable';
  elseif ($margin > 0) $status = 'low_margin';
  else $status = 'loss';
  
  $financial_data[] = [
    'id'     => $row['id'],
    'gas'     => $row['gas'] ?: 'Industrial Gas',
    'category'  => $row['category'] ?: 'Standard Mix',
    'volume'   => number_format($volume) . ' Units',
    'volume_raw' => $volume,
    'revenue'   => $revenue,
    'cost'    => $cost,
    'profit'   => $profit,
    'margin'   => $margin,
    'status'   => $status,
    'unit_cost'  => $unit_cost,
    'unit_price' => $unit_price
  ];
  
  $total_revenue += $revenue;
  $total_cost += $cost;
}

$net_profit = $total_revenue - $total_cost;
$avg_margin = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;

$kpi_stats = [
  'total_revenue' => $total_revenue,
  'total_cost'  => $total_cost,
  'net_profit'  => $net_profit,
  'avg_margin'  => $avg_margin
];

$statusStyles = [
  'high_profit' => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'High Profit'],
  'acceptable' => ['bg' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/30',       'dot' => 'bg-cyan-500',  'label' => 'Acceptable'],
  'low_margin' => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',    'dot' => 'bg-amber-500',  'label' => 'Low Margin'],
  'loss'    => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30',     'dot' => 'bg-rose-500',  'label' => 'Loss / Critical']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cost & Profit Analysis | Financials | I-GAS Control Room</title>
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
      FINANCIAL INTELLIGENCE // MARGIN RECONCILIATION ACTIVE
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">REPORTING CURRENCY: SAR</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">NET MARGIN BENCHMARK: <?= number_format($avg_margin, 1) ?>%</span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Page Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
            FINANCIAL INTELLIGENCE // UNIT COST & PROFIT MARGINS
          </div>
          <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
            <?= icon('banknote', 'w-6 h-6 text-teal-600 dark:text-teal-400') ?>
            <span>Cost & Profit Analysis</span>
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            Evaluate production costs, revenue streams, and overall profitability margins per gas category.
          </p>
        </div>

        <!-- Action Controls: Exactly ONE Primary Orange CTA -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="btn-secondary h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-colors">
            <?= icon('printer', 'w-4 h-4') ?>
            <span>Print Dossier</span>
          </button>
          <?php if (has_permission('finance.view') || has_permission('reports.export')): ?>
          <button type="button" onclick="exportFinancialsData()" class="btn-cta-orange h-9 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider rounded-md inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition-all">
            <?= icon('download', 'w-4 h-4') ?>
            <span>Export Financials</span>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Gross Revenue -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">GROSS REVENUE</span>
          <?= icon('dollar-sign', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tabular-nums text-white">
            <?= number_format($kpi_stats['total_revenue'], 2) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500">SAR</span>
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs font-mono text-emerald-600 dark:text-emerald-400">
          <?= icon('trending-up', 'w-3.5 h-3.5') ?>
          <span>Total Income Recognized</span>
        </div>
      </div>

      <!-- Total Costs -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">TOTAL PRODUCTION COSTS</span>
          <?= icon('credit-card', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tabular-nums text-rose-600 dark:text-rose-400">
            <?= number_format($kpi_stats['total_cost'], 2) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500">SAR</span>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Direct raw material & operational cost
        </div>
      </div>

      <!-- Net Profit -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-[3px] <?= $kpi_stats['net_profit'] >= 0 ? 'border-s-emerald-600' : 'border-s-rose-600' ?>">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">NET PROFIT</span>
          <?= icon('activity', 'w-4 h-4 ' . ($kpi_stats['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600')) ?>
        </div>
        <div class="flex items-baseline gap-1.5">
          <span class="text-2xl font-bold font-mono tabular-nums <?= $kpi_stats['net_profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
            <?= number_format($kpi_stats['net_profit'], 2) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500">SAR</span>
        </div>
        <div class="mt-3 text-xs font-mono">
          <?php if ($kpi_stats['net_profit'] >= 0): ?>
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
              <?= icon('check-circle', 'w-3.5 h-3.5') ?>
              Positive Operating Return
            </span>
          <?php else: ?>
            <span class="text-rose-600 font-semibold flex items-center gap-1">
              <?= icon('alert-triangle', 'w-3.5 h-3.5') ?>
              Operational Deficit
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Avg Profit Margin -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">AVG PROFIT MARGIN</span>
          <?= icon('percent', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($kpi_stats['avg_margin'], 1) ?>%
        </div>
        <div class="mt-3">
          <div class="flex justify-between text-[11px] font-mono text-neutral-500 mb-1">
            <span>WEIGHTED AVERAGE</span>
            <span class="tabular-nums font-semibold"><?= round($kpi_stats['avg_margin'], 1) ?>%</span>
          </div>
          <?= level_bar(max(0, min($kpi_stats['avg_margin'], 100)), 100, 'h-1.5') ?>
        </div>
      </div>

    </div>

    <!-- Master Profitability Table Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
      
      <!-- Table Header & Controls -->
      <div class="p-4 border-b border-[#1a3346] bg-[#09151f] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white flex items-center gap-2">
            <?= icon('layers', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <span>Profitability by Gas Product</span>
          </h2>
          <span class="text-xs font-mono text-neutral-500">(<span id="visibleCount" class="tabular-nums font-semibold text-slate-200"><?= count($financial_data) ?></span> products)</span>
        </div>

        <!-- Search and Filters -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>
                <input type="text" id="searchInput" placeholder="Search product..." class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">
              </div>
          <select id="categoryFilter" class="h-9 px-3 text-xs font-mono bg-[#07111a] border border-[#1e3a50] rounded-md text-slate-100 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/40 transition-all">
            <option value="">All Categories</option>
            <option value="Pure Gas">Pure Gas</option>
            <option value="Standard Mix">Standard Mix</option>
            <option value="Custom Mix">Custom Mix</option>
            <option value="Medical">Medical</option>
          </select>
        </div>
      </div>

      <!-- Table Responsive Container -->
      <div class="overflow-x-auto custom-scrollbar flex-1 w-full">
        <table class="w-full text-left border-collapse text-xs" id="financialTable">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
              <th class="py-3 px-4">PRODUCT / CLASSIFICATION</th>
              <th class="py-3 px-4">VOLUME SOLD</th>
              <th class="py-3 px-4 text-right">REVENUE (SAR)</th>
              <th class="py-3 px-4 text-right">TOTAL COST (SAR)</th>
              <th class="py-3 px-4 text-right">NET PROFIT (SAR)</th>
              <th class="py-3 px-4">PROFIT MARGIN</th>
              <th class="py-3 px-4">STATUS</th>
              <th class="py-3 px-4 text-right">DETAILS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] font-mono">
            <?php foreach ($financial_data as $row): ?>
            <?php
              $statusObj = $statusStyles[$row['status']] ?? $statusStyles['acceptable'];
            ?>
            <tr class="hover:bg-[#102332] transition financial-row" data-category="<?= htmlspecialchars($row['category']) ?>">
              <td class="py-3 px-4">
                <div class="font-bold text-white gas-text flex items-center gap-2">
                  <?= gas_chip($row['gas']) ?>
                  <span><?= htmlspecialchars($row['gas']) ?></span>
                </div>
                <div class="text-[11px] text-neutral-500 category-text">
                  <?= htmlspecialchars($row['category']) ?>
                </div>
              </td>
              <td class="py-3 px-4 tabular-nums text-slate-400">
                <?= htmlspecialchars($row['volume']) ?>
              </td>
              <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                <?= number_format($row['revenue'], 2) ?>
              </td>
              <td class="py-3 px-4 text-right tabular-nums text-rose-600 dark:text-rose-400">
                <?= number_format($row['cost'], 2) ?>
              </td>
              <td class="py-3 px-4 text-right tabular-nums font-bold <?= $row['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                <?= number_format($row['profit'], 2) ?>
              </td>
              <td class="py-3 px-4 w-36">
                <div class="flex items-center gap-2">
                  <div class="w-16">
                    <?= level_bar(max(0, min($row['margin'], 100)), 100, 'h-1.5') ?>
                  </div>
                  <span class="tabular-nums font-bold text-white"><?= number_format($row['margin'], 1) ?>%</span>
                </div>
              </td>
              <td class="py-3 px-4">
                <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs text-[11px] font-mono border <?= $statusObj['bg'] ?>">
                  <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                  <?= $statusObj['label'] ?>
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <button type="button" onclick='openDetails(<?= json_encode($row) ?>)' class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline font-semibold focus:outline-teal-500" title="View Financial Breakdown">
                  <span>Audit</span>
                  <?= icon('chevron-right', 'w-3.5 h-3.5 rtl:rotate-180') ?>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($financial_data)): ?>
            <tr id="emptyStateRow">
              <td colspan="8" class="py-8 text-center text-slate-400">
                No financial data available to display.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer Summary -->
      <div class="px-5 py-3 border-t border-[#1a3346] bg-[#09151f] flex items-center justify-between text-xs font-mono text-neutral-500">
        <span>Displaying primary revenue streams from reconciled accounting</span>
        <span class="text-[11px] text-neutral-400">Currency: SAR</span>
      </div>
    </div>

  </div>
</main>

<!-- Details Modal (Flat 1px border, Hairline borders, Scrim backdrop) -->
<div id="detailsModal" class="fixed inset-0 bg-[#0c1924]/70 z-50 hidden items-center justify-center p-4">
  <div class="bg-[#0c1924] border border-[#1e3a50] rounded-sm w-full max-w-lg overflow-hidden flex flex-col">
    <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
      <h3 class="text-xs font-bold font-heading uppercase tracking-wider text-white flex items-center gap-2">
        <?= icon('file-text', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        <span>Financial Breakdown</span>
      </h3>
      <button type="button" onclick="closeModal('detailsModal')" class="text-neutral-400 hover:text-slate-300 dark:hover:text-neutral-200">
        <?= icon('x', 'w-4 h-4') ?>
      </button>
    </div>
    <div class="p-5 space-y-4">
      <div>
        <h4 id="m_gas_name" class="text-lg font-bold font-heading text-white"></h4>
        <span id="m_category" class="inline-block text-[11px] font-mono uppercase tracking-wider px-2 py-0.5 rounded-xs border border-[#1e3a50] text-slate-400 mt-1"></span>
      </div>
      
      <div class="grid grid-cols-2 gap-3 text-xs font-mono">
        <div class="p-3 border border-[#1a3346] bg-[#09151f] rounded-xs">
          <span class="text-neutral-500 uppercase tracking-wider block text-[10px]">Standard Cost / Unit</span>
          <span id="m_unit_cost" class="text-sm font-bold text-rose-600 dark:text-rose-400"></span>
        </div>
        <div class="p-3 border border-[#1a3346] bg-[#09151f] rounded-xs">
          <span class="text-neutral-500 uppercase tracking-wider block text-[10px]">Selling Price / Unit</span>
          <span id="m_unit_price" class="text-sm font-bold text-emerald-600 dark:text-emerald-400"></span>
        </div>
      </div>
      
      <div class="border border-[#1a3346] bg-[#09151f] bg-[#0c1924]/30 rounded-xs p-4 space-y-2.5 text-xs font-mono">
        <div class="flex justify-between border-b border-[#1a3346] pb-2">
          <span class="text-neutral-500">Total Volume Sold</span>
          <span id="m_volume" class="font-bold text-white"></span>
        </div>
        <div class="flex justify-between border-b border-[#1a3346] pb-2">
          <span class="text-neutral-500">Gross Revenue</span>
          <span id="m_revenue" class="font-bold text-white"></span>
        </div>
        <div class="flex justify-between border-b border-[#1a3346] pb-2">
          <span class="text-neutral-500">Total Production Cost</span>
          <span id="m_cost" class="font-bold text-rose-600 dark:text-rose-400"></span>
        </div>
        <div class="flex justify-between pt-1 text-sm">
          <span class="font-bold uppercase text-slate-200">Net Profit Generated</span>
          <span id="m_profit" class="font-bold text-emerald-600 dark:text-emerald-400"></span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }

  document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');

    function filterRows() {
      const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
      const selectedCategory = categoryFilter ? categoryFilter.value.trim() : '';
      const rows = document.querySelectorAll('.financial-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const gasEl = row.querySelector('.gas-text');
        const gasText = gasEl ? gasEl.innerText.toLowerCase() : '';
        const rowCategory = (row.getAttribute('data-category') || '').trim();

        const matchesSearch = !query || gasText.includes(query);
        const matchesCategory = !selectedCategory || rowCategory === selectedCategory;

        if (matchesSearch && matchesCategory) {
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

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (categoryFilter) categoryFilter.addEventListener('change', filterRows);
  });

  function openDetails(data) {
    document.getElementById('m_gas_name').innerText = data.gas;
    document.getElementById('m_category').innerText = data.category;
    
    document.getElementById('m_unit_cost').innerText = 'SAR ' + parseFloat(data.unit_cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('m_unit_price').innerText = 'SAR ' + parseFloat(data.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('m_volume').innerText = data.volume;
    document.getElementById('m_revenue').innerText = 'SAR ' + parseFloat(data.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('m_cost').innerText = 'SAR ' + parseFloat(data.cost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('m_profit').innerText = 'SAR ' + parseFloat(data.profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const modal = document.getElementById('detailsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('flex');
    modal.classList.add('hidden');
  }

  function exportFinancialsData() {
    let csv = [];
    let rows = document.querySelectorAll("#financialTable tr");
    
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
    downloadLink.download = "cost_profit_analysis_" + new Date().toISOString().slice(0, 10) + ".csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
  }
</script>
</body>
</html>
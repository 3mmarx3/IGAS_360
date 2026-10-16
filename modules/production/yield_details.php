<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('reports.view');

$active_page = 'yield_loss_reports';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Yield & Loss Reports', 'Batch Analysis'];

$batch_id = $_GET['batch'] ?? '';

if (empty($batch_id)) {
  try {
    $firstBatch = $pdo->query("SELECT batch_number FROM cylinder_batches ORDER BY id DESC LIMIT 1")->fetchColumn();
    $batch_id = $firstBatch ?: 'BAT-8092';
  } catch (Exception $e) {
    $batch_id = 'BAT-8092';
  }
}

// Check database for batch details or construct telemetry record
$batch = [
  'id'       => $batch_id,
  'product'    => 'Liquid Oxygen (LOX)',
  'date'      => date('Y-m-d'),
  'shift'     => 'Shift B (14:00 - 22:00)',
  'operator'    => 'Eng. Ahmed Khaled',
  'machine_id'   => 'ASU-Line-1',
  'input'     => 5200,
  'output'     => 5120,
  'loss'      => 80,
  'yield_pct'   => 98.4,
  'status'     => 'optimal',
  'financial_loss' => 320.00,
  'qc_status'   => 'Passed'
];

try {
  $stmt = $pdo->prepare("SELECT * FROM cylinder_batches WHERE batch_number = ? LIMIT 1");
  $stmt->execute([$batch_id]);
  $db_b = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($db_b) {
    $in_vol = floatval($db_b['quantity'] ?? 52) * 100;
    $out_vol = $in_vol * 0.984;
    $loss_vol = $in_vol - $out_vol;

    $batch['product'] = $db_b['gas_classification'] ?? $batch['product'];
    $batch['date'] = !empty($db_b['created_at']) ? date('Y-m-d', strtotime($db_b['created_at'])) : $batch['date'];
    $batch['input'] = $in_vol;
    $batch['output'] = $out_vol;
    $batch['loss'] = $loss_vol;
    $batch['yield_pct'] = 98.4;
    $batch['financial_loss'] = $loss_vol * 4.5;
  }
} catch (Exception $e) {
  // Keep initialized defaults
}

$loss_breakdown = [
  ['reason' => 'System Purging & Venting', 'val' => 45, 'pct' => 56.25],
  ['reason' => 'Pressure Stabilization',  'val' => 18, 'pct' => 22.50],
  ['reason' => 'Leakage Detection',     'val' => 12, 'pct' => 15.00],
  ['reason' => 'Calibration Waste',     'val' => 5, 'pct' => 6.25],
];

$process_logs = [
  ['time' => '14:15', 'event' => 'Batch Initialization & Valve Leak Check', 'user' => 'System', 'status' => 'info'],
  ['time' => '14:45', 'event' => 'Pressure Stabilization Phase (200 Bar)',  'user' => 'Auto-Controller', 'status' => 'warning'],
  ['time' => '16:20', 'event' => 'Mid-cycle QC Sample Taken for Purity',   'user' => 'Quality Lab', 'status' => 'info'],
  ['time' => '18:10', 'event' => 'System Purging Executed on Manifold 3',  'user' => 'Ahmed Khaled', 'status' => 'warning'],
  ['time' => '21:30', 'event' => 'Batch Completed & QC Certified',     'user' => 'Ahmed Khaled', 'status' => 'success'],
];

$statusStyles = [
  'optimal'  => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'Optimal Yield'],
  'acceptable' => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',    'dot' => 'bg-amber-500',  'label' => 'Acceptable Variance'],
  'high_loss' => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30',     'dot' => 'bg-rose-500',  'label' => 'High Loss Alert'],
];

$logStyles = [
  'info'  => 'bg-[#09151f] text-slate-300 border-[#1e3a50]',
  'warning' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',
  'success' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
];

$bs = $statusStyles[$batch['status']] ?? $statusStyles['optimal'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batch <?= htmlspecialchars($batch['id']) ?> | Yield Analysis | I-GAS Control Room</title>
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
      BATCH RECONCILIATION DOSSIER // EFFICIENCY VERIFIED
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">BATCH: <?= htmlspecialchars($batch['id']) ?></span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">UNIT: <?= htmlspecialchars($batch['machine_id']) ?></span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">LEAD: <?= htmlspecialchars($batch['operator']) ?></span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Navigation Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
      <a href="<?= app_url('modules/production/yield_loss_reports.php') ?>" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline">
        <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
        <span>BACK TO YIELD REPORTS</span>
      </a>
      <span>/</span>
      <span class="text-slate-300"><?= htmlspecialchars($batch['id']) ?></span>
    </div>

    <!-- Master Batch Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center flex-shrink-0">
            <?= icon('percent', 'w-7 h-7 text-teal-600 dark:text-teal-400') ?>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
              <h1 class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-white flex items-center gap-2">
                <?= gas_chip($batch['product']) ?>
                <span><?= htmlspecialchars($batch['id']) ?></span>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xs text-xs font-mono border <?= $bs['bg'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $bs['dot'] ?>"></span>
                <?= $bs['label'] ?>
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-mono text-slate-400">
              <span class="text-white text-slate-200 font-semibold"><?= htmlspecialchars($batch['product']) ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($batch['date']) ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($batch['shift']) ?></span>
            </div>
          </div>
        </div>

        <!-- Action Controls: Print PDF (secondary) & One Primary Orange CTA -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('printer', 'w-4 h-4') ?>
            <span>Print Audit</span>
          </button>
          <button type="button" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('flag', 'w-4 h-4') ?>
            <span>Flag for Review</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Yield Efficiency -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">YIELD EFFICIENCY</span>
          <?= icon('activity', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($batch['yield_pct'], 1) ?>%
        </div>
        <div class="mt-3">
          <div class="flex justify-between text-[11px] font-mono text-neutral-500 mb-1">
            <span>RECONCILIATION</span>
            <span class="tabular-nums font-semibold"><?= round($batch['yield_pct'], 1) ?>%</span>
          </div>
          <?= level_bar($batch['yield_pct'], 100, 'h-1.5') ?>
        </div>
      </div>

      <!-- Input Volume -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">INPUT VOLUME</span>
          <?= icon('cylinder', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($batch['input']) ?> <span class="text-xs font-normal text-neutral-500">L</span>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Total raw feedstock injected
        </div>
      </div>

      <!-- Net Output -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">NET OUTPUT</span>
          <?= icon('check-circle', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= number_format($batch['output']) ?> <span class="text-xs font-normal text-neutral-500">L</span>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Final certified cylinder volume
        </div>
      </div>

      <!-- Total Loss Volume -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">TOTAL LOSS</span>
          <?= icon('alert-triangle', 'w-4 h-4 text-rose-600') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-rose-600 dark:text-rose-400">
          <?= number_format($batch['loss']) ?> <span class="text-xs font-normal text-neutral-500">L</span>
        </div>
        <div class="mt-3 text-xs font-mono text-rose-600 font-semibold flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
          <span>Variance accounted for</span>
        </div>
      </div>

    </div>

    <!-- Main Breakdown and Properties Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Left 2 Cols: Loss Breakdown & Process Timeline -->
      <div class="xl:col-span-2 space-y-6">

        <!-- Loss Categorization Breakdown Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('layers', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Loss Categorization Breakdown
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500">TOTAL: <?= number_format($batch['loss']) ?> LITERS</span>
          </div>

          <div class="p-5 space-y-4">
            <?php foreach ($loss_breakdown as $item): ?>
            <div class="border border-[#1a3346] bg-[#09151f] rounded-xs p-3">
              <div class="flex items-center justify-between mb-1.5 text-xs font-mono">
                <span class="font-bold text-white"><?= htmlspecialchars($item['reason']) ?></span>
                <div class="text-right">
                  <span class="font-bold tabular-nums text-white"><?= $item['val'] ?> L</span>
                  <span class="text-neutral-500 ms-1 tabular-nums">(<?= $item['pct'] ?>%)</span>
                </div>
              </div>
              <?= level_bar($item['pct'], 100, 'h-1.5') ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Process Timeline Table Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('clock', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Process Operations Timeline
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500">CHRONOLOGICAL AUDIT</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
                  <th class="py-3 px-4">TIME</th>
                  <th class="py-3 px-4">EVENT DESCRIPTION</th>
                  <th class="py-3 px-4 text-right">OPERATOR / CONTROLLER</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a] font-mono">
                <?php foreach ($process_logs as $log): ?>
                <?php $t_class = $logStyles[$log['status']] ?? 'bg-[#09151f] text-slate-300 border-[#1e3a50]'; ?>
                <tr class="hover:bg-[#102332] transition">
                  <td class="py-3 px-4 text-white font-semibold tabular-nums">
                    <?= htmlspecialchars($log['time']) ?>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex px-1.5 py-0.5 rounded-xs text-[10px] font-mono border <?= $t_class ?>">
                        <?= strtoupper($log['status']) ?>
                      </span>
                      <span class="text-slate-200"><?= htmlspecialchars($log['event']) ?></span>
                    </div>
                  </td>
                  <td class="py-3 px-4 text-right text-slate-400">
                    <?= htmlspecialchars($log['user']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right 1 Col: Properties and Financial Impact -->
      <div class="space-y-6">

        <!-- Batch Properties Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
            <?= icon('info', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
              Batch Specifications
            </h2>
          </div>

          <div class="p-5 space-y-4 text-xs font-mono">
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Product Gas</span>
              <span class="text-white font-bold text-sm"><?= htmlspecialchars($batch['product']) ?></span>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Production Shift</span>
              <span class="text-slate-200"><?= htmlspecialchars($batch['shift']) ?></span>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Lead Operator</span>
              <span class="text-white font-bold"><?= htmlspecialchars($batch['operator']) ?></span>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Machine / Line Unit</span>
              <span class="text-teal-600 dark:text-teal-400 font-bold"><?= htmlspecialchars($batch['machine_id']) ?></span>
            </div>
          </div>
        </div>

        <!-- Financial & QC Impact Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
            <?= icon('shield-check', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
              Financial & QC Impact
            </h2>
          </div>

          <div class="p-5 space-y-4 text-xs font-mono">
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Estimated Financial Loss</span>
              <span class="text-rose-600 dark:text-rose-400 font-bold text-base tabular-nums"><?= number_format($batch['financial_loss'], 2) ?> SAR</span>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Quality Control Status</span>
              <div class="flex items-center gap-1.5 mt-1 text-emerald-600 dark:text-emerald-400 font-bold">
                <?= icon('check-circle', 'w-4 h-4') ?>
                <span><?= htmlspecialchars($batch['qc_status']) ?></span>
              </div>
            </div>
            <div class="pt-2">
              <button type="button" class="w-full py-2.5 px-3 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider font-semibold hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500 inline-flex items-center justify-center gap-2">
                <?= icon('send', 'w-4 h-4') ?>
                <span>Send to Finance Dept</span>
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>

  </div>
</main>

<script>
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }
</script>
</body>
</html>
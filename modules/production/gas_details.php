<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'gases_mixtures';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Gases & Mixtures', 'Product Details'];

$gas_id = $_GET['id'] ?? '';

if (empty($gas_id)) {
  $firstGas = $pdo->query("SELECT id FROM gas_mixtures ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstGas) {
    $gas_id = $firstGas;
  } else {
    redirect(app_url('modules/production/gases_mixtures.php'));
  }
}

try {
  $stmt = $pdo->prepare("SELECT * FROM gas_mixtures WHERE mix_id = ? OR id = ?");
  $stmt->execute([$gas_id, is_numeric($gas_id) ? (int)$gas_id : 0]);
  $gas_db = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$gas_db) {
    redirect(app_url('modules/production/gases_mixtures.php'));
  }

  $gas = [
    'id'       => $gas_db['mix_id'] ?? 'GAS-' . $gas_db['id'],
    'name'      => $gas_db['product_name'] ?? 'Industrial Gas',
    'type'      => $gas_db['category'] ?? 'Standard Gas',
    'formula'    => !empty($gas_db['formula']) ? $gas_db['formula'] : 'N/A',
    'purity'     => !empty($gas_db['purity']) ? $gas_db['purity'] : 'Standard Grade',
    'stock'     => floatval($gas_db['volume'] ?? 0),
    'unit'      => 'Liters',
    'capacity'    => 1500, // Maximum capacity for percentage calculation
    'status'     => $gas_db['status'] ?? 'certified',
    'un_number'   => ($gas_db['category'] ?? '') === 'Medical' ? 'UN 1072' : 'UN 1956',
    'hazard_class'  => '2.2 (Compressed / Non-Flammable)',
    'cas_number'   => ($gas_db['category'] ?? '') === 'Pure Gas' ? 'Single Element' : 'Mixture Blend',
    'cylinder_color' => 'Standard Industrial Code',
    'shelf_life'   => '36 Months'
  ];

  $components_data = !empty($gas_db['components_data']) ? json_decode((string)$gas_db['components_data'], true) : [];
  $composition = [];
  
  if (is_array($components_data) && !empty($components_data)) {
    foreach ($components_data as $comp) {
      $composition[] = [
        'component' => $comp['name'] ?? ($comp['material_name'] ?? 'Unknown Component'),
        'percentage' => floatval($comp['ratio'] ?? 0),
        'tolerance' => '±0.5%',
        'grade'   => 'Grade 4.8+'
      ];
    }
  } else {
    $composition[] = [
      'component' => $gas['name'],
      'percentage' => 100.0,
      'tolerance' => '±0.0%',
      'grade'   => $gas['purity']
    ];
  }

} catch (PDOException $e) {
  die("Database Error: " . $e->getMessage());
}

// Simulated recent batches for production audit log
$recent_batches = [
  ['id' => 'BAT-' . rand(8000, 8999), 'date' => date('Y-m-d', strtotime('-1 days')), 'qty' => 150, 'qc_status' => 'certified', 'inspector' => 'Tariq Nabil'],
  ['id' => 'BAT-' . rand(8000, 8999), 'date' => date('Y-m-d', strtotime('-2 days')), 'qty' => 200, 'qc_status' => 'certified', 'inspector' => 'Faisal Omar'],
  ['id' => 'BAT-' . rand(8000, 8999), 'date' => date('Y-m-d', strtotime('-4 days')), 'qty' => 180, 'qc_status' => 'testing',  'inspector' => 'Quality Lab Team'],
];

$statusStyles = [
  'certified' => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'QC Certified'],
  'testing'  => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30', 'dot' => 'bg-amber-500', 'label' => 'In QC Lab'],
  'rejected' => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30', 'dot' => 'bg-rose-500', 'label' => 'QC Rejected'],
  'low_stock' => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30', 'dot' => 'bg-rose-500', 'label' => 'Low Stock Alert'],
];

$gs = $statusStyles[$gas['status']] ?? $statusStyles['certified'];
$stock_pct = min(100, max(0, ($gas['stock'] / ($gas['capacity'] ?: 1)) * 100));
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($gas['name']) ?> | Gas Details | I-GAS Control Room</title>
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
      MANIFOLD ONLINE // PURITY SPEC VERIFIED
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">PLANT: JEDDAH BULK REFINERY #4</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">FILLING MATRIX: ISO 14175 COMPLIANT</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">SPEC REVISION: REV 2026.4</span>
  </div>

  <!-- Page Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">
    
    <!-- Back Navigation & Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
      <a href="<?= app_url('modules/production/gases_mixtures.php') ?>" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline">
        <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
        <span>BACK TO GAS REGISTRY</span>
      </a>
      <span>/</span>
      <span class="text-slate-300"><?= htmlspecialchars($gas['id']) ?></span>
    </div>

    <!-- Master Product Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center flex-shrink-0">
            <?= icon('flask-conical', 'w-7 h-7 text-teal-600 dark:text-teal-400') ?>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
              <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
                <?= gas_chip($gas['name']) ?>
                <span><?= htmlspecialchars($gas['name']) ?></span>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xs text-xs font-mono border <?= $gs['bg'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $gs['dot'] ?>"></span>
                <?= $gs['label'] ?>
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-mono text-slate-400">
              <span class="text-white text-slate-200 font-semibold"><?= htmlspecialchars($gas['id']) ?></span>
              <span>•</span>
              <span>CATEGORY: <?= htmlspecialchars($gas['type']) ?></span>
              <span>•</span>
              <span>FORMULA: <span class="text-slate-200 font-bold"><?= htmlspecialchars($gas['formula']) ?></span></span>
            </div>
          </div>
        </div>

        <!-- Action Controls -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('file-text', 'w-4 h-4') ?>
            SDS TECH SHEET
          </button>
          <?php if (has_permission('production.create')): ?>
          <a href="<?= app_url('modules/production/new_mixture.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('plus', 'w-4 h-4') ?>
            NEW BATCH MIX
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Available Stock -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">AVAILABLE STOCK</span>
          <?= icon('cylinder', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="text-2xl font-bold font-mono tabular-nums text-white">
            <?= number_format($gas['stock'], 2) ?>
          </span>
          <span class="text-xs font-mono text-slate-400"><?= htmlspecialchars($gas['unit']) ?></span>
        </div>
        <div class="mt-3">
          <div class="flex justify-between text-[11px] font-mono text-neutral-500 mb-1">
            <span>CAPACITY ALLOCATION</span>
            <span class="tabular-nums font-semibold"><?= round($stock_pct, 1) ?>%</span>
          </div>
          <?= level_bar($stock_pct, 100, 'h-1.5') ?>
        </div>
      </div>

      <!-- Purity / Grade -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">PURITY / GRADE</span>
          <?= icon('award', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-xl font-bold font-heading uppercase text-white">
          <?= htmlspecialchars($gas['purity']) ?>
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs font-mono text-emerald-600 dark:text-emerald-400">
          <?= icon('check-circle', 'w-3.5 h-3.5') ?>
          <span>ISO 14175 Standard Verified</span>
        </div>
      </div>

      <!-- Chemical Formula -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">CHEMICAL FORMULA</span>
          <?= icon('binary', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono text-teal-600 dark:text-teal-400">
          <?= htmlspecialchars($gas['formula']) ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          CAS Profile: <span class="text-slate-200"><?= htmlspecialchars($gas['cas_number']) ?></span>
        </div>
      </div>

      <!-- QC Compliance Rate -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">QC COMPLIANCE RATE</span>
          <?= icon('shield-check', 'w-4 h-4 text-emerald-600 dark:text-emerald-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-emerald-600 dark:text-emerald-400">
          100.0%
        </div>
        <div class="mt-3 flex items-center gap-1.5 text-xs font-mono text-slate-400">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          <span>Zero Chromatograph Deviations</span>
        </div>
      </div>
    </div>

    <!-- Main Breakdown & Specifications Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Left 2 Cols: Formulation Components & Batches -->
      <div class="xl:col-span-2 space-y-6">
        
        <!-- Mix Composition Breakdown -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('layers', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-sm font-bold font-heading uppercase tracking-wide text-white">
                Mix Composition Breakdown
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500 uppercase">Certified Recipe R-<?= substr(md5($gas['id']), 0, 6) ?></span>
          </div>

          <div class="p-5 space-y-5">
            <?php foreach ($composition as $comp): ?>
            <div class="border border-[#1a3346] bg-[#09151f] rounded-xs p-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-white font-mono">
                      <?= htmlspecialchars($comp['component']) ?>
                    </span>
                    <?= gas_chip($comp['component']) ?>
                  </div>
                  <div class="text-xs font-mono text-slate-400 mt-1">
                    Grade Spec: <span class="text-slate-300"><?= htmlspecialchars($comp['grade']) ?></span>
                    <span class="mx-1">•</span>
                    Tolerance: <span class="text-slate-300"><?= htmlspecialchars($comp['tolerance']) ?></span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-xl font-bold font-mono tabular-nums text-teal-600 dark:text-teal-400">
                    <?= number_format($comp['percentage'], 2) ?>%
                  </span>
                </div>
              </div>
              <?= level_bar($comp['percentage'], 100, 'h-2') ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Recent Production Batches -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('archive', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-sm font-bold font-heading uppercase tracking-wide text-white">
                Recent Production Batches
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500">LAST 30 DAYS</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
                  <th class="py-3 px-4">BATCH ID</th>
                  <th class="py-3 px-4">PRODUCTION DATE</th>
                  <th class="py-3 px-4 text-right">VOLUME (L)</th>
                  <th class="py-3 px-4">QC CERTIFICATION</th>
                  <th class="py-3 px-4 text-right">QC INSPECTOR</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a] font-mono">
                <?php foreach ($recent_batches as $b): ?>
                <?php $bs = $statusStyles[$b['qc_status']] ?? $statusStyles['certified']; ?>
                <tr class="hover:bg-[#102332] transition">
                  <td class="py-3 px-4 font-semibold text-white">
                    <?= htmlspecialchars($b['id']) ?>
                  </td>
                  <td class="py-3 px-4 text-slate-400 tabular-nums">
                    <?= htmlspecialchars($b['date']) ?>
                  </td>
                  <td class="py-3 px-4 text-right tabular-nums text-white font-semibold">
                    <?= number_format($b['qty'], 2) ?>
                  </td>
                  <td class="py-3 px-4">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono border <?= $bs['bg'] ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?= $bs['dot'] ?>"></span>
                      <?= $bs['label'] ?>
                    </span>
                  </td>
                  <td class="py-3 px-4 text-right text-slate-400">
                    <?= htmlspecialchars($b['inspector']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right 1 Col: Chemical Properties & Handling Guidelines -->
      <div class="space-y-6">
        
        <!-- Chemical & Hazard Properties -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
            <?= icon('shield-alert', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <h2 class="text-sm font-bold font-heading uppercase tracking-wide text-white">
              Chemical Properties
            </h2>
          </div>
          <div class="p-5 space-y-4 text-xs font-mono">
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Product Category</span>
              <span class="text-white font-bold text-sm"><?= htmlspecialchars($gas['type']) ?></span>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">CAS Identification</span>
              <span class="text-slate-200"><?= htmlspecialchars($gas['cas_number']) ?></span>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">UN Number (Transport)</span>
              <span class="text-teal-600 dark:text-teal-400 font-bold text-sm"><?= htmlspecialchars($gas['un_number']) ?></span>
            </div>
            <div class="pb-1">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px] mb-1.5">Hazard Classification</span>
              <div class="flex items-center gap-2">
                <?= hazard_badge('non_flammable', 'w-4 h-4') ?>
                <span class="text-white font-semibold"><?= htmlspecialchars($gas['hazard_class']) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Storage & Cylinder Handling -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
            <?= icon('tool', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <h2 class="text-sm font-bold font-heading uppercase tracking-wide text-white">
              Storage & Handling
            </h2>
          </div>
          <div class="p-5 space-y-4 text-xs font-mono">
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Cylinder Color Coding</span>
              <div class="flex items-center gap-2 mt-1">
                <span class="w-3 h-3 rounded-full border border-neutral-300 dark:border-neutral-600 bg-teal-800"></span>
                <span class="text-white font-medium"><?= htmlspecialchars($gas['cylinder_color']) ?></span>
              </div>
            </div>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Shelf Life Guarantee</span>
              <span class="text-white font-bold"><?= htmlspecialchars($gas['shelf_life']) ?></span>
            </div>
            <div class="pt-2">
              <button type="button" class="w-full py-2.5 px-3 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider font-semibold hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500 inline-flex items-center justify-center gap-2">
                <?= icon('printer', 'w-4 h-4') ?>
                PRINT GHS ADHERENCE LABELS
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
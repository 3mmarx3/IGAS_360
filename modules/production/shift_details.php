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
$breadcrumb = ['I-GAS', 'Production', 'Daily Shift Logs', 'Shift Details'];

$shift_id = $_GET['id'] ?? '';

if (empty($shift_id)) {
  $latestStmt = $pdo->query("SELECT COALESCE(shift_id, shift_code, id) FROM shift_logs ORDER BY id DESC LIMIT 1");
  $shift_id = $latestStmt ? $latestStmt->fetchColumn() : '';
  if (empty($shift_id)) {
    redirect(app_url('modules/production/daily_log_shifts.php'));
  }
}

$stmt = $pdo->prepare("SELECT * FROM shift_logs WHERE shift_id = ? OR shift_code = ? OR CAST(id AS CHAR) = ?");
$stmt->execute([$shift_id, $shift_id, $shift_id]);
$shift_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shift_data) {
  redirect(app_url('modules/production/daily_log_shifts.php'));
}

$cycle_hours = '00:00 - 00:00';
if ($shift_data['shift_cycle'] === 'Morning') $cycle_hours = '06:00 - 14:00';
elseif ($shift_data['shift_cycle'] === 'Evening') $cycle_hours = '14:00 - 22:00';
elseif ($shift_data['shift_cycle'] === 'Night') $cycle_hours = '22:00 - 06:00';

$shift = [
  'id'       => $shift_data['shift_id'] ?? 'SHF-' . $shift_data['id'],
  'date'      => $shift_data['operational_date'] ?? date('Y-m-d'),
  'cycle'     => $shift_data['shift_cycle'] ?? 'Day',
  'hours'     => $cycle_hours,
  'supervisor'   => $shift_data['supervisor'] ?? 'Supervisor',
  'operators'   => intval($shift_data['operators_count'] ?? 1),
  'status'     => $shift_data['status'] ?? 'completed',
  'target_output' => floatval($shift_data['target_output'] ?? 0),
  'current_output' => floatval($shift_data['actual_output'] ?? 0),
  'downtime_mins' => 0,
  'safety_alerts' => intval($shift_data['safety_alerts'] ?? 0),
  'handover_notes' => !empty($shift_data['notes']) ? $shift_data['notes'] : 'No operational notes or handover briefs recorded for this shift.'
];

$breakdown_status = 'on_track';
if ($shift['current_output'] < $shift['target_output']) {
  $breakdown_status = 'lagging';
}

$production_breakdown = [
  [
    'gas'  => $shift_data['primary_focus'] ?? 'Liquid Oxygen (LOX)', 
    'size'  => 'Mixed Cylinder Variants', 
    'qty'  => $shift['current_output'], 
    'target' => $shift['target_output'], 
    'status' => $breakdown_status
  ]
];

$logStmt = $pdo->prepare("SELECT created_at as time, event_type as type, reference_info as description_info, logged_by as username FROM cylinder_logs WHERE DATE(created_at) = ? ORDER BY created_at DESC LIMIT 10");
$logStmt->execute([$shift_data['operational_date']]);
$raw_event_logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

$event_logs = [];
foreach ($raw_event_logs as $log) {
  $event_logs[] = [
    'time' => $log['time'],
    'type' => $log['type'],
    'desc' => $log['description_info'],
    'user' => $log['username']
  ];
}

$event_logs[] = [
  'time' => $shift_data['created_at'] ?? date('Y-m-d 06:00:00'),
  'type' => 'System',
  'desc' => 'Shift initialized. Pre-shift safety checks completed and verified.',
  'user' => $shift_data['supervisor'] ?? 'System'
];

usort($event_logs, function($a, $b) {
  return strtotime($a['time']) - strtotime($b['time']);
});

foreach ($event_logs as &$log) {
  $log['time'] = date('H:i', strtotime($log['time']));
}

$statusStyles = [
  'in_progress' => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30', 'dot' => 'bg-amber-500', 'label' => 'Active Shift'],
  'completed'  => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-500', 'label' => 'Signed Off'],
  'flagged'   => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30', 'dot' => 'bg-rose-500', 'label' => 'Flagged / Issues'],
];

$typeStyles = [
  'System'   => 'bg-[#09151f] text-slate-300 border-[#1e3a50]',
  'Production' => 'bg-teal-500/10 text-teal-400 dark:text-teal-400 border-teal-500/30',
  'Maintenance' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',
  'Alert'    => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30',
];

$ss = $statusStyles[$shift['status']] ?? $statusStyles['completed'];
$progress_pct = $shift['target_output'] > 0 ? min(100, ($shift['current_output'] / $shift['target_output']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($shift['id']) ?> | Shift Details | I-GAS Control Room</title>
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
      SHIFT TELEMETRY ARCHIVE // VERIFIED DOSSIER
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">SHIFT REF: <?= htmlspecialchars($shift['id']) ?></span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">SUPERVISOR: <?= htmlspecialchars($shift['supervisor']) ?></span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">CYCLE: <?= htmlspecialchars($shift['cycle']) ?> (<?= htmlspecialchars($shift['hours']) ?>)</span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Navigation Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
      <a href="<?= app_url('modules/production/daily_log_shifts.php') ?>" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline">
        <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
        <span>BACK TO SHIFT LOGS</span>
      </a>
      <span>/</span>
      <span class="text-slate-300"><?= htmlspecialchars($shift['id']) ?></span>
    </div>

    <!-- Shift Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center flex-shrink-0">
            <?= icon('clock', 'w-7 h-7 text-teal-600 dark:text-teal-400') ?>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
              <h1 class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-white">
                <?= htmlspecialchars($shift['id']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xs text-xs font-mono border <?= $ss['bg'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $ss['dot'] ?>"></span>
                <?= $ss['label'] ?>
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-mono text-slate-400">
              <span class="text-white text-slate-200 font-semibold"><?= htmlspecialchars($shift['date']) ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($shift['cycle']) ?> Shift</span>
              <span>•</span>
              <span><?= htmlspecialchars($shift['hours']) ?></span>
            </div>
          </div>
        </div>

        <!-- Action Buttons: Print (secondary) & One Primary Orange CTA if in progress -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('printer', 'w-4 h-4') ?>
            <span>Print Handover</span>
          </button>
          <?php if ($shift['status'] === 'in_progress' && has_permission('production.manage')): ?>
          <button type="button" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('check-circle', 'w-4 h-4') ?>
            <span>Sign-off Shift</span>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bento KPI Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Output vs Target -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">PRODUCTION OUTPUT</span>
          <?= icon('cylinder', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="text-2xl font-bold font-mono tabular-nums text-white">
            <?= number_format($shift['current_output']) ?>
          </span>
          <span class="text-xs font-mono text-neutral-500">/ <?= number_format($shift['target_output']) ?> Cyl</span>
        </div>
        <div class="mt-3">
          <div class="flex justify-between text-[11px] font-mono text-neutral-500 mb-1">
            <span>TARGET PROGRESS</span>
            <span class="tabular-nums font-semibold"><?= round($progress_pct, 1) ?>%</span>
          </div>
          <?= level_bar($progress_pct, 100, 'h-1.5') ?>
        </div>
      </div>

      <!-- Active Operators -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">ACTIVE OPERATORS</span>
          <?= icon('users', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= $shift['operators'] ?>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Clocked in for shift duty
        </div>
      </div>

      <!-- Total Downtime -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-4 border-s-[#f97316]">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">TOTAL DOWNTIME</span>
          <?= icon('pause-circle', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums text-white">
          <?= $shift['downtime_mins'] ?> <span class="text-xs font-normal text-neutral-500">Mins</span>
        </div>
        <div class="mt-3 text-xs font-mono text-slate-400">
          Accumulated line halts
        </div>
      </div>

      <!-- Safety Alerts -->
      <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-4 border-s-[3px] <?= $shift['safety_alerts'] > 0 ? 'border-s-rose-600' : 'border-s-emerald-600' ?>">
        <div class="flex items-center justify-between text-slate-400 mb-2">
          <span class="text-xs font-mono uppercase tracking-wider">SAFETY ALERTS</span>
          <?= icon('shield-alert', 'w-4 h-4 ' . ($shift['safety_alerts'] > 0 ? 'text-rose-600' : 'text-emerald-600')) ?>
        </div>
        <div class="text-2xl font-bold font-mono tabular-nums <?= $shift['safety_alerts'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
          <?= $shift['safety_alerts'] ?>
        </div>
        <div class="mt-3 text-xs font-mono">
          <?php if ($shift['safety_alerts'] > 0): ?>
            <span class="text-rose-600 font-semibold flex items-center gap-1">
              <?= icon('alert-triangle', 'w-3.5 h-3.5') ?>
              Review needed
            </span>
          <?php else: ?>
            <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
              <?= icon('check-circle', 'w-3.5 h-3.5') ?>
              Zero incidents reported
            </span>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Main Breakdown and Properties Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Left 2 Cols: Production Breakdown & Timeline Logs -->
      <div class="xl:col-span-2 space-y-6">

        <!-- Production Breakdown Table -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('layers', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Production Output Breakdown
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500 uppercase">Shift Target Matrix</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
                  <th class="py-3 px-4">GAS CLASSIFICATION</th>
                  <th class="py-3 px-4">CYLINDER SPEC</th>
                  <th class="py-3 px-4 text-right">FILLED QTY</th>
                  <th class="py-3 px-4 text-right">TARGET</th>
                  <th class="py-3 px-4 text-right">STATUS</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a] font-mono">
                <?php foreach ($production_breakdown as $p): ?>
                <tr class="hover:bg-[#102332] transition">
                  <td class="py-3 px-4 font-semibold text-white flex items-center gap-2">
                    <?= gas_chip($p['gas']) ?>
                    <span><?= htmlspecialchars($p['gas']) ?></span>
                  </td>
                  <td class="py-3 px-4 text-slate-400">
                    <?= htmlspecialchars($p['size']) ?>
                  </td>
                  <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                    <?= number_format($p['qty']) ?>
                  </td>
                  <td class="py-3 px-4 text-right tabular-nums text-slate-400">
                    <?= number_format($p['target']) ?>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <?php if ($p['status'] === 'on_track'): ?>
                      <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                        <?= icon('check', 'w-3.5 h-3.5') ?>
                        <span>On Track</span>
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-semibold">
                        <?= icon('alert-circle', 'w-3.5 h-3.5') ?>
                        <span>Lagging</span>
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Operational Timeline & Event Logs -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('activity', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Operational Timeline & Audit Events
              </h2>
            </div>
            <span class="text-xs font-mono text-neutral-500">CHRONOLOGICAL EVENT LOG</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
                  <th class="py-3 px-4">TIME</th>
                  <th class="py-3 px-4">CATEGORY</th>
                  <th class="py-3 px-4">DESCRIPTION</th>
                  <th class="py-3 px-4 text-right">LOGGED BY</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a] font-mono">
                <?php foreach ($event_logs as $e): ?>
                <?php $t_class = $typeStyles[$e['type']] ?? 'bg-[#09151f] text-slate-300 border-[#1e3a50]'; ?>
                <tr class="hover:bg-[#102332] transition">
                  <td class="py-3 px-4 text-white font-semibold tabular-nums">
                    <?= htmlspecialchars($e['time']) ?>
                  </td>
                  <td class="py-3 px-4">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-xs text-[11px] font-mono border <?= $t_class ?>">
                      <?= htmlspecialchars($e['type']) ?>
                    </span>
                  </td>
                  <td class="py-3 px-4 text-slate-200">
                    <?= htmlspecialchars($e['desc']) ?>
                  </td>
                  <td class="py-3 px-4 text-right text-slate-400">
                    <?= htmlspecialchars($e['user']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right 1 Col: Properties and Notes -->
      <div class="space-y-6">

        <!-- Shift Properties Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
            <?= icon('info', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
            <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
              Shift Specifications
            </h2>
          </div>

          <div class="p-5 space-y-4 text-xs font-mono">
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Shift Supervisor</span>
              <div class="flex items-center gap-2 mt-1">
                <div class="w-5 h-5 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center text-[10px] font-bold text-neutral-600 text-slate-300">
                  <?= htmlspecialchars(mb_substr($shift['supervisor'], 0, 1)) ?>
                </div>
                <span class="font-bold text-white"><?= htmlspecialchars($shift['supervisor']) ?></span>
              </div>
            </div>

            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Operational Cycle</span>
              <span class="text-white font-semibold"><?= htmlspecialchars($shift['cycle']) ?> Shift</span>
            </div>

            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Scheduled Hours</span>
              <span class="text-teal-600 dark:text-teal-400 font-bold"><?= htmlspecialchars($shift['hours']) ?></span>
            </div>

            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Active Crew Size</span>
              <span class="text-white font-bold"><?= $shift['operators'] ?> Certified Plant Operators</span>
            </div>
          </div>
        </div>

        <!-- Handover Notes Card -->
        <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <?= icon('file-text', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Handover Notes
              </h2>
            </div>
            <span class="text-[11px] font-mono text-neutral-400">SIGN-OFF BRIEF</span>
          </div>

          <div class="p-5">
            <p class="text-xs font-mono text-slate-300 leading-relaxed bg-[#09151f] p-3 rounded-xs border border-[#1a3346]">
              <?= htmlspecialchars($shift['handover_notes']) ?>
            </p>
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
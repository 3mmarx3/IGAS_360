<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.view');

$active_page = 'liquid_tracking';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Bulk Liquid Tracking', 'Tank Profile'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  $firstId = $pdo->query("SELECT id FROM raw_materials WHERE category = 'Bulk Gas' ORDER BY id ASC LIMIT 1")->fetchColumn();
  if (!$firstId) {
    $firstId = $pdo->query("SELECT id FROM raw_materials ORDER BY id ASC LIMIT 1")->fetchColumn();
  }
  if ($firstId) {
    $id = (int)$firstId;
  } else {
    redirect(app_url('modules/production/liquid_tracking.php'));
  }
}

$stmt = $pdo->prepare("SELECT * FROM raw_materials WHERE id = ?");
$stmt->execute([$id]);
$tank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tank) {
  redirect(app_url('modules/production/liquid_tracking.php'));
}

$stmt_usage = $pdo->prepare("
  SELECT 
    SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END) as total_in,
    SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END) as total_out
  FROM material_transactions
  WHERE material_id = ?
");
$stmt_usage->execute([$id]);
$usage_data = $stmt_usage->fetch(PDO::FETCH_ASSOC);

$total_in = (float)($usage_data['total_in'] ?? 0);
$total_out = (float)($usage_data['total_out'] ?? 0);

$txStmt = $pdo->prepare("SELECT * FROM material_transactions WHERE material_id = ? ORDER BY transaction_date DESC LIMIT 50");
$txStmt->execute([$id]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

$max_cap = (float)($tank['max_capacity'] ?? 0);
$curr_stock = (float)($tank['current_stock'] ?? 0);
$threshold = (float)($tank['safety_stock_threshold'] ?? 0);

$level_pct = $max_cap > 0 ? round(($curr_stock / $max_cap) * 100) : 0;

if ($curr_stock <= 0) {
  $statusObj = ['border' => 'border-rose-500/30', 'bg' => 'bg-rose-500/10', 'text' => 'text-rose-400 dark:text-rose-400', 'dot' => 'bg-rose-500', 'label_en' => 'Empty / Out of Stock', 'label_ar' => 'فارغ / نفد المخزون'];
} elseif ($curr_stock <= $threshold) {
  $statusObj = ['border' => 'border-amber-500/30', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Low Stock Alert', 'label_ar' => 'تنبيه مخزون منخفض'];
} else {
  $statusObj = ['border' => 'border-emerald-500/30', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Optimal Level', 'label_ar' => 'مستوى مثالي'];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($tank['warehouse_zone'] ?? $tank['material_name']) ?> | I-GAS Enterprise</title>
  
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
          <span data-en="SENSORS: ONLINE" data-ar="المستشعرات: متصلة">SENSORS: ONLINE</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="PLANT: JEDDAH MAIN FACILITY" data-ar="المحطة: منشأة جدة الرئيسية">PLANT: JEDDAH MAIN FACILITY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="text-white font-semibold font-mono"><?= htmlspecialchars($tank['warehouse_zone'] ?? 'VESSEL-' . $id) ?></span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">LEVEL:</span>
        <span class="text-teal-400 dark:text-teal-400 font-semibold font-mono"><?= $level_pct ?>%</span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <!-- Back Link -->
      <div>
        <a href="<?= app_url('modules/production/liquid_tracking.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors whitespace-nowrap">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Liquid Reconciliation" data-ar="العودة لمطابقة السوائل">Back to Liquid Reconciliation</span>
        </a>
      </div>

      <!-- Page Header Card -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5">
        <div class="flex items-start sm:items-center gap-4">
          <div class="w-12 h-12 bg-[#112433] border border-[#1e3a50] rounded-xs flex items-center justify-center flex-shrink-0">
            <i data-lucide="database" class="w-6 h-6 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2.5 mb-1">
              <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight font-mono">
                <?= htmlspecialchars($tank['warehouse_zone'] ?? $tank['material_sku']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 whitespace-nowrap px-2 py-0.5 rounded-xs border text-[11px] font-medium <?= $statusObj['border'] ?> <?= $statusObj['bg'] ?> <?= $statusObj['text'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $statusObj['dot'] ?>"></span>
                <span data-en="<?= $statusObj['label_en'] ?>" data-ar="<?= $statusObj['label_ar'] ?>"><?= $statusObj['label_en'] ?></span>
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <div><?= gas_chip($tank['material_name'] ?? 'O2', 'compact') ?></div>
              <span class="font-mono text-slate-400"><?= htmlspecialchars($tank['material_sku']) ?></span>
              <span>·</span>
              <span class="font-medium text-white"><?= htmlspecialchars($tank['material_name']) ?></span>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <?php if (has_permission('production.create')): ?>
          <a href="<?= app_url('modules/production/liquid_transaction.php?type=in&id=' . $id) ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs flex items-center gap-1.5 transition-colors">
            <i data-lucide="arrow-down-to-line" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
            <span data-en="Receive (IN)" data-ar="استلام شحنة (وارد)">Receive (IN)</span>
          </a>
          <a href="<?= app_url('modules/production/liquid_transaction.php?type=out&id=' . $id) ?>" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs">
            <i data-lucide="arrow-up-from-line" class="w-4 h-4"></i>
            <span data-en="Dispatch (OUT)" data-ar="صرف / تفريغ (صادر)">Dispatch (OUT)</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bento Metric Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Current Volume" data-ar="الحجم الحالي">Current Volume</p>
            <i data-lucide="gauge" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
          </div>
          <div class="mt-2 flex items-baseline justify-between">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums">
              <?= number_format($curr_stock) ?> <span class="text-xs font-normal text-slate-400 font-sans"><?= htmlspecialchars($tank['unit']) ?></span>
            </h3>
            <span class="text-xs font-mono px-1.5 py-0.5 rounded-xs bg-teal-500/10 text-teal-400 dark:text-teal-400 border border-teal-500/20"><?= $level_pct ?>%</span>
          </div>
          <div class="mt-2.5 h-1.5 w-full bg-[#112433] rounded-xs overflow-hidden">
            <div class="h-full bg-teal-600 dark:bg-teal-500" style="width: <?= min(100, $level_pct) ?>%;"></div>
          </div>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Max Capacity" data-ar="السعة القصوى">Max Capacity</p>
            <i data-lucide="maximize-2" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-white tabular-nums">
              <?= number_format($max_cap) ?> <span class="text-xs font-normal text-slate-400 font-sans"><?= htmlspecialchars($tank['unit']) ?></span>
            </h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Safe filling limit" data-ar="حد التعبئة الآمن">Safe filling limit</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-emerald-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Lifetime Received" data-ar="إجمالي الوارد">Lifetime Received</p>
            <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
              +<?= number_format($total_in) ?> <span class="text-xs font-normal text-slate-400 font-sans"><?= htmlspecialchars($tank['unit']) ?></span>
            </h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Total replenishments" data-ar="إجمالي الشحنات المستلمة">Total replenishments</p>
        </div>

        <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-rose-400 p-4 rounded-xs">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400" data-en="Lifetime Dispatched" data-ar="إجمالي المنصرف">Lifetime Dispatched</p>
            <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
          </div>
          <div class="mt-2">
            <h3 class="font-mono text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums">
              -<?= number_format($total_out) ?> <span class="text-xs font-normal text-slate-400 font-sans"><?= htmlspecialchars($tank['unit']) ?></span>
            </h3>
          </div>
          <p class="text-[11px] text-slate-400 mt-2" data-en="Total drawn out" data-ar="إجمالي المسحوب للتعبئة">Total drawn out</p>
        </div>
      </div>

      <!-- Content Grid: Transactions Table (2 cols) & Specs (1 col) -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Transactions History Table -->
        <div class="xl:col-span-2 bg-[#0c1924] border border-[#1a3346] rounded-xs flex flex-col overflow-hidden">
          <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
            <div class="flex items-center gap-2">
              <i data-lucide="history" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Transaction Ledger" data-ar="سجل الحركات">Transaction Ledger</h3>
            </div>
            <span class="text-xs font-mono text-slate-400"><?= count($transactions) ?> <span data-en="entries" data-ar="سجل">entries</span></span>
          </div>

          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left rtl:text-right border-collapse text-xs" style="min-width: 600px;">
              <thead>
                <tr class="bg-[#102230]/75 bg-[#112433]/60 border-b border-[#1a3346] text-[11px] font-mono uppercase tracking-wider text-slate-400">
                  <th class="px-5 py-3 font-semibold" data-en="Date & Time" data-ar="التاريخ والوقت">Date & Time</th>
                  <th class="px-4 py-3 font-semibold text-center" data-en="Type" data-ar="النوع">Type</th>
                  <th class="px-4 py-3 font-semibold text-right rtl:text-left" data-en="Quantity" data-ar="الكمية">Quantity</th>
                  <th class="px-4 py-3 font-semibold" data-en="Reference" data-ar="المرجع">Reference</th>
                  <th class="px-5 py-3 font-semibold text-right rtl:text-left" data-en="Operator" data-ar="المشغل">Operator</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#152a3a]">
                <?php if (!empty($transactions)): ?>
                  <?php foreach ($transactions as $tx): ?>
                  <tr class="hover:bg-[#102332] transition-colors">
                    <td class="px-5 py-3 font-mono text-[11px] text-slate-400">
                      <?= htmlspecialchars(date('d M Y, H:i', strtotime($tx['transaction_date']))) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <?php if ($tx['type'] === 'in'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-xs border text-[10px] font-mono font-semibold uppercase tracking-wider border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                          Fill (IN)
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-xs border text-[10px] font-mono font-semibold uppercase tracking-wider border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400">
                          Draw (OUT)
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right rtl:text-left font-mono font-bold tabular-nums <?= $tx['type'] === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                      <?= $tx['type'] === 'in' ? '+' : '-' ?><?= number_format($tx['quantity']) ?> <?= htmlspecialchars($tank['unit']) ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-white">
                      <?= htmlspecialchars($tx['transaction_ref']) ?>
                      <?php if (!empty($tx['source_ref'])): ?>
                        <div class="text-[11px] text-slate-400 font-sans">
                          Src: <?= htmlspecialchars($tx['source_ref']) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right rtl:text-left text-slate-400 text-xs">
                      <?= htmlspecialchars($tx['logged_by']) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center py-8 text-xs text-slate-400" data-en="No transactions found for this vessel." data-ar="لا توجد حركات مسجلة لهذا الصهريج.">
                      No transactions found for this vessel.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right Column: Vessel Specs & Safety -->
        <div class="space-y-6">
          
          <!-- Storage Parameters Card -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
              <i data-lucide="sliders" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Storage Parameters" data-ar="معايير التخزين">Storage Parameters</h3>
            </div>
            <div class="p-5 space-y-4 text-xs">
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Material Name" data-ar="اسم المادة">Material Name</p>
                <p class="font-medium text-white text-sm"><?= htmlspecialchars($tank['material_name']) ?></p>
              </div>
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Material Category" data-ar="فئة المادة">Material Category</p>
                <p class="font-medium text-white"><?= htmlspecialchars($tank['category']) ?></p>
              </div>
              <div class="border-b border-neutral-100 border-[#1a3346]/80 pb-3">
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Safety Threshold Level" data-ar="حد الأمان الأدنى">Safety Threshold Level</p>
                <p class="font-mono font-bold text-rose-600 dark:text-rose-400"><?= number_format($threshold) ?> <?= htmlspecialchars($tank['unit']) ?></p>
              </div>
              <div>
                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400 mb-1" data-en="Unit Cost Reference" data-ar="سعر التكلفة المرجعي">Unit Cost Reference</p>
                <p class="font-mono font-medium text-white"><?= number_format($tank['unit_cost'], 2) ?> SAR / <?= htmlspecialchars($tank['unit']) ?></p>
              </div>
            </div>
          </div>

          <!-- Safety & Handling Guidelines -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
              <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
              <h3 class="font-heading font-bold text-sm text-white" data-en="Safety & Handling Guidelines" data-ar="إرشادات السلامة والتعامل">Safety & Handling Guidelines</h3>
            </div>
            <div class="p-5 text-xs text-slate-400 leading-relaxed font-sans">
              <?= nl2br(htmlspecialchars($tank['handling_notes'] ?? 'No specific handling guidelines recorded for this vessel.')) ?>
            </div>
          </div>

        </div>
      </div>

    </div>
  </main>

  <script>
    lucide.createIcons();
  </script>
</body>
</html>
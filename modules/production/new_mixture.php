<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.create');

$active_page = 'gases_mixtures';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Gases & Mixtures', 'Formulate Mix'];

$new_mix_id = 'GAS-' . rand(1100, 9999);
$success_msg = '';
$error_msg = '';

$stmt_rm = $pdo->query("SELECT id, material_sku, material_name, current_stock, unit, unit_cost FROM raw_materials ORDER BY material_name ASC");
$available_materials = $stmt_rm->fetchAll(PDO::FETCH_ASSOC);

$materials_pricing = [];
foreach ($available_materials as $mat) {
  $materials_pricing[$mat['id']] = [
    'name' => $mat['material_name'],
    'unit' => $mat['unit'],
    'cost' => floatval($mat['unit_cost']),
    'stock' => floatval($mat['current_stock']),
  ];
}

$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
$forUpdate = $isSqlite ? '' : ' FOR UPDATE';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  try {
    $mix_id = $_POST['mix_id'] ?? $new_mix_id;
    $product_name = trim($_POST['product_name'] ?? '');
    $category = $_POST['category'] ?? '';
    $purity = trim($_POST['purity'] ?? '');
    $volume = floatval($_POST['volume'] ?? 0);
    $container_capacity = floatval($_POST['container_capacity'] ?? 0);
    $working_pressure = intval($_POST['working_pressure'] ?? 0);
    $formula = trim($_POST['formula'] ?? '');
    $status = $_POST['status'] ?? 'testing';

    $standard_cost = floatval($_POST['standard_cost'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);

    $qc_gc = isset($_POST['qc_gc']) ? 1 : 0;
    $qc_moisture = isset($_POST['qc_moisture']) ? 1 : 0;
    $qc_o2 = isset($_POST['qc_o2']) ? 1 : 0;
    $qc_leak = isset($_POST['qc_leak']) ? 1 : 0;

    $comp_ids = $_POST['comp_id'] ?? [];
    $comp_ratios = $_POST['comp_ratio'] ?? [];
    $components = [];
    $total_ratio = 0;

    foreach ($comp_ids as $index => $mat_id) {
      $mat_id = intval($mat_id);
      $clean_ratio = floatval($comp_ratios[$index] ?? 0);

      if ($mat_id > 0) {
        $components[] = [
          'material_id' => $mat_id,
          'ratio' => $clean_ratio,
        ];
        $total_ratio += $clean_ratio;
      }
    }

    if (empty($product_name) || empty($category) || empty($components)) {
      $error_msg = "من فضلك املأ كل الحقول المطلوبة واختر مكوّن واحد على الأقل من القائمة. / Please fill in all required fields and add at least one component.";
    } elseif ($container_capacity <= 0) {
      $error_msg = "من فضلك أدخل (Container / Bottle Capacity) — هي الكمية الفعلية اللي هيتم خصمها من المخزون، لازم تكون أكبر من صفر. / Please enter the Container / Bottle Capacity — this is the actual quantity that will be deducted from stock.";
    } elseif (($category !== 'Pure Gas' && $category !== 'Medical') && abs($total_ratio - 100.0) > 0.01) {
      $error_msg = "إجمالي نسب التركيبة لازم يساوي 100% بالظبط. الإجمالي الحالي: {$total_ratio}% / Total composition ratio must equal exactly 100%. Current total: {$total_ratio}%";
    } else {
      if ($category === 'Pure Gas' || $category === 'Medical') {
        $components = [
          [
            'material_id' => $components[0]['material_id'],
            'ratio' => 100.0,
          ],
        ];
      }

      $pdo->beginTransaction();

      $resolved_components = [];
      $shortage_errors = [];

      foreach ($components as $comp) {
        $stmt_find = $pdo->prepare("SELECT id, material_name, current_stock, unit FROM raw_materials WHERE id = ?" . $forUpdate);
        $stmt_find->execute([$comp['material_id']]);
        $rm = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if (!$rm) {
          $shortage_errors[] = "مكوّن غير موجود في المخزون (ID: {$comp['material_id']}).";
          continue;
        }

        $deduct_qty = ($comp['ratio'] / 100) * $container_capacity;

        if ($deduct_qty > (float)$rm['current_stock']) {
          $shortage_errors[] = "المادة \"{$rm['material_name']}\" مش كفاية: متاح {$rm['current_stock']} {$rm['unit']}، والمطلوب {$deduct_qty} {$rm['unit']}.";
        }

        $resolved_components[] = [
          'id' => $rm['id'],
          'name' => $rm['material_name'],
          'unit' => $rm['unit'],
          'ratio' => $comp['ratio'],
          'deduct_qty' => $deduct_qty,
        ];
      }

      if (!empty($shortage_errors)) {
        $pdo->rollBack();
        $error_msg = "معنديش مخزون كفاية علشان أخصم الكمية دي: " . implode(' | ', $shortage_errors);
      } else {
        $components_json = json_encode(array_map(function ($c) {
          return ['name' => $c['name'], 'ratio' => $c['ratio']];
        }, $resolved_components), JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO gas_mixtures 
          (mix_id, product_name, category, purity, volume, container_capacity, working_pressure, formula,
          standard_cost, selling_price, components_data, status, qc_gc, qc_moisture, qc_o2, qc_leak)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
          $mix_id, $product_name, $category, $purity, $volume, $container_capacity, $working_pressure, $formula,
          $standard_cost, $selling_price, $components_json, $status, $qc_gc, $qc_moisture, $qc_o2, $qc_leak,
        ]);

        foreach ($resolved_components as $c) {
          if ($c['deduct_qty'] <= 0) {
            continue;
          }

          $stmt_stock = $pdo->prepare("SELECT current_stock FROM raw_materials WHERE id = ?" . $forUpdate);
          $stmt_stock->execute([$c['id']]);
          $current = (float)$stmt_stock->fetchColumn();
          $new_stock = $current - $c['deduct_qty'];

          $stmt_upd = $pdo->prepare("UPDATE raw_materials SET current_stock = ? WHERE id = ?");
          $stmt_upd->execute([$new_stock, $c['id']]);

          $tx_ref = 'TX-' . time() . '-' . rand(100, 999);
          $stmt_tx = $pdo->prepare("INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by) VALUES (?, ?, 'out', ?, ?, 'System')");
          $stmt_tx->execute([$c['id'], $tx_ref, $c['deduct_qty'], $mix_id]);
        }

        $pdo->commit();

        $success_msg = "تم حفظ التركيبة وخصم الكميات الصحيحة من المخزون بنجاح! / Product formulation saved and inventory deducted successfully!";
        $new_mix_id = 'GAS-' . rand(1100, 9999);

        $stmt_rm = $pdo->query("SELECT id, material_sku, material_name, current_stock, unit, unit_cost FROM raw_materials ORDER BY material_name ASC");
        $available_materials = $stmt_rm->fetchAll(PDO::FETCH_ASSOC);
        $materials_pricing = [];
        foreach ($available_materials as $mat) {
          $materials_pricing[$mat['id']] = [
            'name' => $mat['material_name'],
            'unit' => $mat['unit'],
            'cost' => floatval($mat['unit_cost']),
            'stock' => floatval($mat['current_stock']),
          ];
        }
      }
    }
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error_msg = "Database Error: " . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulate Mix | Gas Production | I-GAS Control Room</title>
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

  <!-- Telemetry status line -->
  <div class="h-9 border-b border-[#1a3346] bg-[#102230]/80 bg-[#0c1924]/80 flex items-center px-4 sm:px-6 md:px-8 gap-3 sm:gap-6 flex-shrink-0 overflow-x-auto whitespace-nowrap text-[11px]">
    <span class="flex items-center gap-1.5 font-mono uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      MANIFOLD BLENDING UNIT READY
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">PLANT: JEDDAH BULK REFINERY #4</span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">RAW MATERIAL VALVES: ACTIVE</span>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- System Alerts -->
    <?php if ($success_msg): ?>
      <div class="p-4 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-mono flex items-center gap-3">
        <?= icon('check-circle', 'w-4 h-4 flex-shrink-0') ?>
        <span><?= htmlspecialchars($success_msg) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
      <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-mono flex items-center gap-3">
        <?= icon('alert-circle', 'w-4 h-4 flex-shrink-0') ?>
        <span><?= htmlspecialchars($error_msg) ?></span>
      </div>
    <?php endif; ?>

    <!-- Page Header & Action Bar -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-mono text-slate-400 mb-1">
            <a href="gases_mixtures.php" class="text-teal-600 dark:text-teal-400 hover:underline">GASES & MIXTURES</a>
            <span>/</span>
            <span>FORMULATION</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-bold font-heading uppercase tracking-tight text-white flex items-center gap-2">
            <?= icon('flask-conical', 'w-6 h-6 text-teal-600 dark:text-teal-400') ?>
            <span>Formulate Product Mix</span>
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            Define new gas mixtures, establish component ratios, container capacities, and assign quality control parameters.
          </p>
        </div>

        <!-- One Primary CTA in Safety Valve Orange -->
        <div class="flex items-center gap-3">
          <a href="gases_mixtures.php" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('x', 'w-4 h-4') ?>
            <span>Cancel</span>
          </a>
          <button type="submit" form="gasForm" id="saveBtn" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500 disabled:opacity-50 disabled:cursor-not-allowed">
            <?= icon('save', 'w-4 h-4') ?>
            <span>Save Formulation</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Formulation Form -->
    <form id="gasForm" action="" method="POST">
      <?= csrf_field() ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Product Definition & Component Ratios -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Product Definition Card -->
          <div class="border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('sliders', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Product Definition & Specifications
              </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Commercial Product Name *</label>
                <input type="text" name="product_name" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Argon/CO2 Mix 80/20, High Purity Nitrogen" required>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Product Category *</label>
                <select name="category" id="productCategory" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" required onchange="toggleGasMode()">
                  <option value="" selected disabled>Select classification...</option>
                  <option value="Pure Gas">Pure Gas (Single Component)</option>
                  <option value="Standard Mix">Standard Industrial Mix</option>
                  <option value="Custom Mix">Custom / Calibration Mix</option>
                  <option value="Medical">Medical Grade Gas</option>
                </select>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Purity Grade Target</label>
                <input type="text" name="purity" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. 99.999% (Grade 5.0)">
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Standard Cylinder Volume (Spec) *</label>
                <div class="relative">
                  <input type="number" step="0.1" name="volume" id="volumeInput" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 pe-14 tabular-nums" placeholder="40.0" required>
                  <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-xs font-mono text-neutral-500">Liters</span>
                </div>
                <p class="text-[10px] text-neutral-500 font-mono mt-1">Descriptive catalog spec only — NOT used to deduct stock.</p>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Container Capacity (Actual Deduction) *</label>
                <div class="relative">
                  <input type="number" step="0.1" name="container_capacity" id="containerCapacityInput" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 pe-14 tabular-nums" placeholder="50.0" oninput="calculateCosts()" required>
                  <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-xs font-mono text-neutral-500">Liters</span>
                </div>
                <p class="text-[10px] text-teal-600 dark:text-teal-400 font-mono mt-1">Actual volume deducted from raw materials warehouse.</p>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Working Pressure</label>
                <div class="relative">
                  <input type="number" step="1" name="working_pressure" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 pe-14 tabular-nums" placeholder="150">
                  <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-xs font-mono text-neutral-500">Bar</span>
                </div>
              </div>

              <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Chemical Formula / Shorthand</label>
                <input type="text" name="formula" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500" placeholder="e.g. Ar 80% / CO2 20%">
              </div>
            </div>
          </div>

          <!-- Composition Breakdown Card -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-[#1a3346]">
              <div class="flex items-center gap-2">
                <?= icon('test-tubes', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
                <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                  Composition Breakdown & Stock Deduction
                </h2>
              </div>
              <span id="totalRatioDisplay" class="text-xs font-mono font-bold text-neutral-500">Total: 0.00%</span>
            </div>

            <!-- Shortage Alert Banner -->
            <div id="stockWarningBanner" class="p-3 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-mono hidden flex items-start gap-2">
              <?= icon('alert-triangle', 'w-4 h-4 flex-shrink-0 mt-0.5') ?>
              <span id="stockWarningText"></span>
            </div>

            <!-- Headers -->
            <div class="grid grid-cols-12 gap-3 px-1 text-[11px] font-mono uppercase text-slate-400">
              <span class="col-span-6">Component (Warehouse Raw Stock)</span>
              <span class="col-span-2">Ratio (%)</span>
              <span class="col-span-3 text-right">Deduction Qty</span>
              <span class="col-span-1"></span>
            </div>

            <!-- Dynamic Rows Container -->
            <div id="componentsContainer" class="space-y-3">
              <div class="component-row grid grid-cols-12 gap-3 items-start border border-[#1a3346] bg-[#09151f] p-3 rounded-xs">
                <div class="col-span-6">
                  <select name="comp_id[]" class="w-full bg-[#08131c] border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 comp-name-select" onchange="onComponentChange(this)" required>
                    <option value="" selected disabled>Select raw material...</option>
                    <?php foreach ($available_materials as $mat): ?>
                      <option value="<?= (int)$mat['id'] ?>">
                        <?= htmlspecialchars($mat['material_name']) ?> (<?= htmlspecialchars($mat['unit']) ?>) — <?= number_format($mat['unit_cost'], 2) ?> SAR/<?= htmlspecialchars($mat['unit']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <p class="text-[11px] font-mono text-neutral-500 mt-1 stock-hint-text">&nbsp;</p>
                </div>
                <div class="col-span-2">
                  <div class="relative">
                    <input type="number" step="0.01" name="comp_ratio[]" class="w-full bg-[#08131c] border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 pe-7 tabular-nums ratio-input" oninput="calculateTotal()" placeholder="100.0" required>
                    <span class="absolute inset-y-0 end-0 flex items-center pe-2 text-xs font-mono text-neutral-500">%</span>
                  </div>
                </div>
                <div class="col-span-3 text-right">
                  <div class="border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono font-bold text-white bg-[#0c1924] tabular-nums required-qty-display">
                    0.00
                  </div>
                </div>
                <div class="col-span-1 flex justify-end">
                  <button type="button" class="p-1.5 rounded-xs border border-[#1e3a50] text-neutral-400 opacity-40 cursor-not-allowed" disabled>
                    <?= icon('trash-2', 'w-4 h-4') ?>
                  </button>
                </div>
              </div>
            </div>

            <!-- Add Component Button -->
            <div id="addMixBtnContainer" class="pt-2">
              <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-dashed border-teal-600/50 dark:border-teal-400/50 text-teal-600 dark:text-teal-400 hover:bg-teal-500/10 transition text-xs font-mono rounded-xs focus:outline-teal-500" onclick="addComponentRow()">
                <?= icon('plus', 'w-3.5 h-3.5') ?>
                <span>Add Compound / Element</span>
              </button>
            </div>
          </div>

        </div>

        <!-- Right 1 Col: Allocation, Costing, and QC Specs -->
        <div class="space-y-6">

          <!-- System Allocation Card -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('hash', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                System Allocation
              </h2>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Generated Product ID</label>
                <input type="hidden" name="mix_id" value="<?= $new_mix_id ?>">
                <input type="text" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-neutral-600 text-slate-300 cursor-not-allowed" value="<?= $new_mix_id ?>" readonly>
              </div>
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Initial Production Status</label>
                <select name="status" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500">
                  <option value="testing" selected>In QC Lab / Testing</option>
                  <option value="certified">QC Certified / Ready</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Pricing & Costing Card -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('banknote', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                Pricing & Costing
              </h2>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Standard Production Cost</label>
                <div class="relative">
                  <input type="number" step="0.01" name="standard_cost" id="standardCostInput" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono font-bold text-slate-100 placeholder-slate-500 pe-14 tabular-nums" placeholder="0.00" readonly>
                  <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-xs font-mono text-neutral-500">SAR</span>
                </div>
                <p class="text-[10px] text-neutral-500 font-mono mt-1">Calculated from components x bottle capacity.</p>
              </div>

              <div class="pt-3 border-t border-[#1a3346]">
                <label class="block text-xs font-mono uppercase text-slate-400 mb-1">Base Selling Price (30% Markup)</label>
                <div class="relative">
                  <input type="number" step="0.01" name="selling_price" id="sellingPriceInput" class="w-full bg-[#09151f] border border-[#1e3a50] rounded-xs px-3 py-2 text-xs font-mono font-bold text-teal-600 dark:text-teal-400 pe-14 tabular-nums focus:outline-2 focus:outline-teal-500" placeholder="0.00">
                  <span class="absolute inset-y-0 end-0 flex items-center pe-3 text-xs font-mono text-neutral-500">SAR</span>
                </div>
                <p class="text-[10px] text-neutral-500 font-mono mt-1">Default suggested price, fully editable.</p>
              </div>
            </div>
          </div>

          <!-- QC Lab Requirements Card -->
          <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 space-y-4">
            <div class="flex items-center gap-2 pb-3 border-b border-[#1a3346]">
              <?= icon('shield-check', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
              <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
                QC Lab Requirements
              </h2>
            </div>

            <div class="space-y-2.5 text-xs font-mono">
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="qc_gc" value="1" checked class="w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Gas Chromatography (GC)</span>
              </label>
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="qc_moisture" value="1" checked class="w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Moisture / Dew Point Check</span>
              </label>
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="qc_o2" value="1" class="w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Oxygen Sensor Analysis</span>
              </label>
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="qc_leak" value="1" class="w-4 h-4 rounded-xs border-[#1e3a50] text-teal-600 focus:ring-0">
                <span class="text-slate-200">Cylinder Valve Leak Test</span>
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

  // Material Map: { id: {name, unit, cost, stock} }
  const materialsPricing = <?= json_encode($materials_pricing, JSON_UNESCAPED_UNICODE) ?>;

  function optionsHtml(selectedId) {
    let html = `<option value="" ${!selectedId ? 'selected disabled' : ''}>Select raw material...</option>`;
    for (const id in materialsPricing) {
      const m = materialsPricing[id];
      const sel = (String(id) === String(selectedId)) ? 'selected' : '';
      html += `<option value="${id}" ${sel}>${m.name} (${m.unit}) — ${m.cost.toFixed(2)} SAR/${m.unit}</option>`;
    }
    return html;
  }

  function addComponentRow() {
    const container = document.getElementById('componentsContainer');
    const rowHtml = `
      <div class="component-row grid grid-cols-12 gap-3 items-start border border-[#1a3346] bg-[#09151f] p-3 rounded-xs">
        <div class="col-span-6">
          <select name="comp_id[]" class="w-full bg-[#08131c] border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 comp-name-select" onchange="onComponentChange(this)" required>
            ${optionsHtml(null)}
          </select>
          <p class="text-[11px] font-mono text-neutral-500 mt-1 stock-hint-text">&nbsp;</p>
        </div>
        <div class="col-span-2">
          <div class="relative">
            <input type="number" step="0.01" name="comp_ratio[]" class="w-full bg-[#08131c] border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-2 focus:outline-teal-500 pe-7 tabular-nums ratio-input" oninput="calculateTotal()" placeholder="0.0" required>
            <span class="absolute inset-y-0 end-0 flex items-center pe-2 text-xs font-mono text-neutral-500">%</span>
          </div>
        </div>
        <div class="col-span-3 text-right">
          <div class="border border-[#1e3a50] rounded-xs px-2.5 py-1.5 text-xs font-mono font-bold text-white bg-[#0c1924] tabular-nums required-qty-display">0.00</div>
        </div>
        <div class="col-span-1 flex justify-end">
          <button type="button" class="p-1.5 rounded-xs border border-rose-800 dark:border-rose-800 text-rose-600 hover:bg-rose-950/30 dark:hover:bg-rose-950 transition" onclick="removeComponentRow(this)">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHtml);
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    calculateTotal();
  }

  function removeComponentRow(btn) {
    btn.closest('.component-row').remove();
    calculateTotal();
  }

  function onComponentChange(select) {
    calculateCosts();
  }

  function calculateCosts() {
    let totalCost = 0;
    let hasShortage = false;
    const shortageMessages = [];

    const containerVal = parseFloat(document.getElementById('containerCapacityInput').value) || 0;
    const usagePerMaterial = {};

    document.querySelectorAll('.component-row').forEach(row => {
      const select = row.querySelector('.comp-name-select');
      const ratioInput = row.querySelector('.ratio-input');
      const hintEl = row.querySelector('.stock-hint-text');
      const requiredBox = row.querySelector('.required-qty-display');

      const matId = select.value;
      const ratio = parseFloat(ratioInput.value) || 0;

      if (matId && materialsPricing.hasOwnProperty(matId)) {
        const mat = materialsPricing[matId];
        const requiredQty = (ratio / 100) * containerVal;

        requiredBox.textContent = requiredQty.toFixed(2) + ' ' + mat.unit;
        totalCost += requiredQty * mat.cost;

        usagePerMaterial[matId] = (usagePerMaterial[matId] || 0) + requiredQty;

        hintEl.textContent = `In stock: ${mat.stock.toFixed(2)} ${mat.unit}`;
        hintEl.classList.remove('text-rose-600', 'font-semibold');

        if (usagePerMaterial[matId] > mat.stock) {
          hasShortage = true;
          hintEl.classList.add('text-rose-600', 'font-semibold');
          shortageMessages.push(`"${mat.name}" short: available ${mat.stock.toFixed(2)} ${mat.unit}, requires ${usagePerMaterial[matId].toFixed(2)} ${mat.unit}`);
        }
      } else {
        requiredBox.textContent = '0.00';
        hintEl.textContent = '\u00A0';
      }
    });

    document.getElementById('standardCostInput').value = totalCost.toFixed(2);

    const suggestedPrice = totalCost * 1.30;
    const sellingInput = document.getElementById('sellingPriceInput');
    if (!sellingInput.dataset.manualEdited) {
      sellingInput.value = suggestedPrice.toFixed(2);
    }

    const banner = document.getElementById('stockWarningBanner');
    const bannerText = document.getElementById('stockWarningText');
    const saveBtn = document.getElementById('saveBtn');

    if (hasShortage) {
      banner.classList.remove('hidden');
      bannerText.textContent = shortageMessages.join(' | ');
      saveBtn.disabled = true;
    } else {
      banner.classList.add('hidden');
      saveBtn.disabled = false;
    }
  }

  document.getElementById('sellingPriceInput').addEventListener('input', function() {
    this.dataset.manualEdited = "true";
  });

  function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.ratio-input').forEach(input => {
      total += parseFloat(input.value) || 0;
    });
    const display = document.getElementById('totalRatioDisplay');
    display.textContent = 'Total: ' + total.toFixed(2) + '%';

    if (Math.abs(total - 100) < 0.01) {
      display.className = 'text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400';
    } else {
      display.className = 'text-xs font-mono font-bold text-rose-600 dark:text-rose-400';
    }

    calculateCosts();
  }

  function toggleGasMode() {
    const categorySelect = document.getElementById('productCategory');
    const addBtnContainer = document.getElementById('addMixBtnContainer');
    const firstRatioInput = document.querySelector('.ratio-input');
    const allRows = document.querySelectorAll('.component-row');

    if (categorySelect.value === 'Pure Gas' || categorySelect.value === 'Medical') {
      addBtnContainer.style.display = 'none';
      firstRatioInput.value = '100';
      firstRatioInput.readOnly = true;

      if (allRows.length > 1) {
        for (let i = 1; i < allRows.length; i++) {
          allRows[i].remove();
        }
      }
    } else {
      addBtnContainer.style.display = 'block';
      firstRatioInput.value = '';
      firstRatioInput.readOnly = false;
    }
    calculateTotal();
  }

  document.addEventListener('DOMContentLoaded', () => {
    toggleGasMode();
    calculateTotal();
  });
</script>
</body>
</html>
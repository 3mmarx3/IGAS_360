<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('production.create');

$type = isset($_GET['type']) && $_GET['type'] === 'out' ? 'out' : 'in';
$type_label_en = $type === 'in' ? 'Receive Liquid Shipment' : 'Decant / Dispatch Liquid';
$type_label_ar = $type === 'in' ? 'استلام شحنة سائل' : 'صرف / تفريغ سائل';

$preselected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$active_page = 'liquid_tracking';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Bulk Liquid Tracking', 'New Transaction'];
$transaction_ref = 'TX-' . rand(10000, 99999);

$tanksStmt = $pdo->query("SELECT id, material_sku, warehouse_zone, material_name, current_stock, max_capacity, unit FROM raw_materials WHERE category = 'Bulk Gas'");
$tanks = $tanksStmt ? $tanksStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $material_id = (int)($_POST['material_id'] ?? 0);
  $transaction_type = ($_POST['transaction_type'] ?? '') === 'out' ? 'out' : 'in';
  $quantity = (float)($_POST['quantity'] ?? 0);
  $source_ref = trim($_POST['source_ref'] ?? '');
  
  if ($quantity <= 0 || $material_id <= 0) {
    $error_message = 'يرجى تحديد الخزان والكمية بشكل صحيح (أكبر من صفر).';
  } else {
    try {
      $pdo->beginTransaction();
      
      $forUpdate = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') ? '' : ' FOR UPDATE';
      $stmt = $pdo->prepare("SELECT current_stock, max_capacity, unit FROM raw_materials WHERE id = ?" . $forUpdate);
      $stmt->execute([$material_id]);
      $tank = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$tank) {
        throw new Exception("الخزان المحدد غير موجود بالنظام.");
      }
      
      $new_stock = (float)$tank['current_stock'];
      if ($transaction_type === 'in') {
        $new_stock += $quantity;
      } else {
        $new_stock -= $quantity;
      }
      
      if ($new_stock < 0) {
        throw new Exception("الرصيد المتاح بالخزان لا يكفي لإتمام عملية الصرف.");
      }
      if ($tank['max_capacity'] > 0 && $new_stock > (float)$tank['max_capacity']) {
        throw new Exception("الكمية المدخلة تتجاوز السعة القصوى المسموح بها للخزان.");
      }
      
      $stock_status = 'optimal';
      if ($tank['max_capacity'] > 0) {
        $pct = ($new_stock / (float)$tank['max_capacity']) * 100;
        if ($pct <= 15) $stock_status = 'out';
        elseif ($pct <= 30) $stock_status = 'low';
      }
      
      $updateStmt = $pdo->prepare("UPDATE raw_materials SET current_stock = ?, stock_status = ? WHERE id = ?");
      $updateStmt->execute([$new_stock, $stock_status, $material_id]);
      
      $operator = $_SESSION['user_name'] ?? 'System Operator';
      $insertStmt = $pdo->prepare("INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by) VALUES (?, ?, ?, ?, ?, ?)");
      $insertStmt->execute([$material_id, $transaction_ref, $transaction_type, $quantity, $source_ref, $operator]);
      
      $pdo->commit();
      $success_message = "تمت العملية بنجاح بالمرجع {$transaction_ref} وتحديث رصيد الخزان.";
      $transaction_ref = 'TX-' . rand(10000, 99999);
    } catch (Exception $e) {
      $pdo->rollBack();
      $error_message = $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'en') ?>" dir="<?= ($_SESSION['lang'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="<?= $type_label_en ?> | I-GAS Enterprise" data-ar="<?= $type_label_ar ?> | I-GAS Enterprise"><?= $type_label_en ?> | I-GAS Enterprise</title>
  
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
          <span data-en="SYS: NOMINAL" data-ar="النظام: مستقر">SYS: NOMINAL</span>
        </span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span data-en="PLANT: JEDDAH MAIN FACILITY" data-ar="المحطة: منشأة جدة الرئيسية">PLANT: JEDDAH MAIN FACILITY</span>
        <span class="text-neutral-300 dark:text-neutral-700">|</span>
        <span class="text-teal-400 dark:text-teal-400 font-semibold font-mono uppercase"><?= $type ?> BOUND OPERATION</span>
      </div>
      <div class="hidden sm:flex items-center gap-2">
        <span class="text-neutral-400 dark:text-neutral-500">REF:</span>
        <span class="text-slate-300 font-semibold font-mono"><?= htmlspecialchars($transaction_ref) ?></span>
      </div>
    </div>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl">

      <form action="" method="POST" id="transactionForm">
        <?= csrf_field() ?>
        <input type="hidden" name="transaction_type" value="<?= $type ?>">

        <!-- Breadcrumb Navigation -->
        <div class="mb-2">
          <a href="<?= app_url('modules/production/liquid_tracking.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
            <span data-en="Back to Liquid Reconciliation" data-ar="العودة لمطابقة السوائل">Back to Liquid Reconciliation</span>
          </a>
        </div>

        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#1a3346] pb-5 mb-6">
          <div>
            <div class="flex items-center gap-2 text-xs font-mono uppercase tracking-wider text-slate-400 mb-1">
              <span data-en="PRODUCTION & INVENTORY" data-ar="الإنتاج والمخزون">PRODUCTION & INVENTORY</span>
              <span>/</span>
              <span class="text-teal-400 dark:text-teal-400 uppercase" data-en="<?= $type ?> BOUND RECORD" data-ar="تسجيل حركة"><?= $type ?> BOUND RECORD</span>
            </div>
            <h1 class="font-heading font-bold text-2xl sm:text-3xl text-white tracking-tight" data-en="<?= $type_label_en ?>" data-ar="<?= $type_label_ar ?>">
              <?= $type_label_en ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1" data-en="Record bulk tanker offloading into stationary cryogenic vessels or decanting run." data-ar="تسجيل تفريغ صهريج السائل في الأوعية الثابتة أو سحب السائل للتعبئة.">
              Record bulk tanker offloading into stationary cryogenic vessels or decanting run.
            </p>
          </div>

          <div class="flex items-center gap-3">
            <a href="<?= app_url('modules/production/liquid_tracking.php') ?>" class="px-3.5 py-2 text-xs font-medium text-slate-300 bg-[#112433] border border-[#1e3a50] hover:bg-[#102332] dark:hover:bg-neutral-700 rounded-xs transition-colors" data-en="Cancel" data-ar="إلغاء">
              Cancel
            </a>
            <button type="submit" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-amber-600 hover:bg-amber-500 active:bg-amber-700 transition-colors border border-amber-500 rounded-xs cursor-pointer">
              <i data-lucide="<?= $type === 'in' ? 'arrow-down-to-line' : 'arrow-up-from-line' ?>" class="w-4 h-4"></i>
              <span data-en="Confirm Transaction" data-ar="تأكيد الحركة">Confirm Transaction</span>
            </button>
          </div>
        </div>

        <?php if ($error_message): ?>
        <div class="p-4 rounded-xs border border-rose-500/30 bg-rose-500/10 text-rose-400 dark:text-rose-400 text-xs font-medium flex items-center gap-2 mb-6">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
        <div class="p-4 rounded-xs border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-medium flex items-center gap-2 mb-6">
          <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Left Column (Inputs) -->
          <div class="lg:col-span-2 space-y-6">
            
            <!-- Section 1: Tank Selection -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="database" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="1. Target Plant Vessel" data-ar="1. وعاء التخزين بالمصنع">1. Target Plant Vessel</h3>
              </div>
              
              <div>
                <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Select Active Storage Tank *" data-ar="اختر صهريج التخزين النشط *">Select Active Storage Tank *</label>
                <select name="material_id" id="tankSelect" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" required>
                  <option value="" disabled <?= $preselected_id === 0 ? 'selected' : '' ?> data-en="Select active vessel from tank farm..." data-ar="اختر الوعاء من مزرعة الصهاريج...">Select active vessel from tank farm...</option>
                  <?php foreach ($tanks as $tank): ?>
                    <option value="<?= $tank['id'] ?>" <?= $preselected_id === (int)$tank['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($tank['warehouse_zone'] ?: $tank['material_sku']) ?> — <?= htmlspecialchars($tank['material_name']) ?> (<?= number_format($tank['current_stock']) ?> / <?= number_format($tank['max_capacity']) ?> <?= htmlspecialchars($tank['unit']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Section 2: Parameters -->
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-cyan-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-5 border-b border-[#1a3346]">
                <i data-lucide="sliders" class="w-4 h-4 text-cyan-600 dark:text-cyan-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="2. Transaction Parameters" data-ar="2. معايير الحركة">2. Transaction Parameters</h3>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Quantity *" data-ar="الكمية *">Quantity *</label>
                  <input type="number" name="quantity" id="inputQty" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500 tabular-nums" placeholder="0.00" step="0.01" required>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Unit of Measure" data-ar="وحدة القياس">Unit of Measure</label>
                  <input type="text" id="uiUnitField" value="Liters" class="w-full px-3 py-2 border border-[#1a3346] bg-[#09151f] rounded-xs text-xs font-mono text-slate-400 cursor-not-allowed" disabled>
                </div>
                <div>
                  <label class="block text-xs font-mono font-semibold text-slate-300 uppercase mb-1.5" data-en="Waybill / PO Reference" data-ar="رقم البوليصة / أمر الشراء">Waybill / PO Reference</label>
                  <input type="text" name="source_ref" class="w-full px-3 py-2 border border-[#1e3a50] bg-[#08131c] rounded-xs text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-teal-500" placeholder="e.g. WB-88501 / PO-921">
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column (Telemetry & Validation) -->
          <div class="space-y-6">
            
            <div class="bg-[#0c1924] border border-[#1a3346] border-s-4 border-s-[#f97316] dark:border-s-amber-400 rounded-xs p-5 sm:p-6">
              <div class="flex items-center gap-2 pb-4 mb-4 border-b border-[#1a3346]">
                <i data-lucide="gauge" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                <h3 class="font-heading font-bold text-sm text-white" data-en="Tank Telemetry & Limits" data-ar="بيانات الخزان وحدود الأمان">Tank Telemetry & Limits</h3>
              </div>
              
              <ul class="text-xs space-y-3 mb-5 font-mono">
                <li class="flex justify-between items-center border-b border-neutral-100 border-[#1a3346]/80 pb-2">
                  <span class="text-slate-400 font-sans" data-en="Transaction ID" data-ar="رقم المعاملة">Transaction ID</span>
                  <span class="text-white font-semibold"><?= htmlspecialchars($transaction_ref) ?></span>
                </li>
                <li class="flex justify-between items-center border-b border-neutral-100 border-[#1a3346]/80 pb-2">
                  <span class="text-slate-400 font-sans" data-en="Operation Mode" data-ar="نوع العملية">Operation Mode</span>
                  <span class="uppercase font-semibold <?= $type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>"><?= $type ?> BOUND</span>
                </li>
                <li class="flex justify-between items-center border-b border-neutral-100 border-[#1a3346]/80 pb-2">
                  <span class="text-slate-400 font-sans" data-en="Current Tank Stock" data-ar="الرصيد الحالي">Current Tank Stock</span>
                  <span id="uiCurrentStock" class="text-white tabular-nums">0 L</span>
                </li>
                <li class="flex justify-between items-center border-b border-neutral-100 border-[#1a3346]/80 pb-2">
                  <span class="text-slate-400 font-sans" data-en="Safe Max Limit" data-ar="الحد الأقصى">Safe Max Limit</span>
                  <span id="uiMaxCapacity" class="text-white tabular-nums">0 L</span>
                </li>
                <li class="flex justify-between items-center pt-1 text-sm font-semibold">
                  <span class="font-sans" data-en="Projected Balance" data-ar="الرصيد المتوقع">Projected Balance</span>
                  <span id="uiNewStock" class="text-white tabular-nums font-mono">0 L</span>
                </li>
              </ul>
              
              <button type="button" id="calcBtn" class="w-full px-3 py-2 text-xs font-mono font-medium text-slate-300 bg-[#09151f] border border-[#1e3a50] hover:bg-[#112433] hover:text-white rounded-xs transition-colors cursor-pointer" data-en="Revalidate Limits" data-ar="إعادة التحقق من الحدود">
                Revalidate Limits
              </button>
            </div>

          </div>
        </div>
      </form>

    </div>
  </main>

  <script>
    lucide.createIcons();

    const tanksData = <?= json_encode($tanks) ?>;
    const txType = "<?= $type ?>";
    
    const tankSelect = document.getElementById('tankSelect');
    const qtyInput = document.getElementById('inputQty');
    const uiCurrentStock = document.getElementById('uiCurrentStock');
    const uiMaxCapacity = document.getElementById('uiMaxCapacity');
    const uiNewStock = document.getElementById('uiNewStock');
    const uiUnitField = document.getElementById('uiUnitField');
    const calcBtn = document.getElementById('calcBtn');

    function updateTelemetry() {
      const selectedId = parseInt(tankSelect.value);
      const tank = tanksData.find(t => parseInt(t.id) === selectedId);
      const qty = parseFloat(qtyInput.value) || 0;

      if (tank) {
        const current = parseFloat(tank.current_stock);
        const max = parseFloat(tank.max_capacity);
        const unit = tank.unit || 'L';
        
        uiUnitField.value = unit;
        uiCurrentStock.textContent = current.toLocaleString() + ' ' + unit;
        uiMaxCapacity.textContent = max.toLocaleString() + ' ' + unit;

        let newStock = current;
        if (txType === 'in') {
          newStock += qty;
        } else {
          newStock -= qty;
        }

        uiNewStock.textContent = newStock.toLocaleString() + ' ' + unit;
        
        if (newStock < 0 || (max > 0 && newStock > max)) {
          uiNewStock.className = 'text-rose-600 dark:text-rose-400 tabular-nums font-mono font-bold';
        } else {
          uiNewStock.className = 'text-teal-400 dark:text-teal-400 tabular-nums font-mono font-bold';
        }
      } else {
        uiCurrentStock.textContent = '0 L';
        uiMaxCapacity.textContent = '0 L';
        uiNewStock.textContent = '0 L';
      }
    }

    tankSelect.addEventListener('change', updateTelemetry);
    qtyInput.addEventListener('input', updateTelemetry);
    calcBtn.addEventListener('click', updateTelemetry);

    document.addEventListener('DOMContentLoaded', () => {
      if (tankSelect.value) {
        updateTelemetry();
      }
    });
  </script>
</body>
</html>
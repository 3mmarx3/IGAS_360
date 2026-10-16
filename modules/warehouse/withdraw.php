<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../../includes/auth.php';
require_permission('warehouse.manage');
require_once __DIR__ . '/../../config/db.php';

$active_page = 'withdraw';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Warehouse & Inventory', 'Stock Allocation & Transfer'];

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $action_type    = $_POST['action_type'] ?? 'withdraw';
  $inventory_id   = $_POST['inventory_id'] ?? '';
  $action_qty    = $_POST['action_qty'] ?? '';
  $transaction_type = $_POST['transaction_type'] ?? 'out';
  $reference_doc   = trim($_POST['reference_doc'] ?? '');
  $notes       = trim($_POST['notes'] ?? '');
  $destination_wh_id = $_POST['destination_warehouse_id'] ?? '';

  if ($inventory_id === '' || !ctype_digit((string)$inventory_id)) {
    $errors[] = 'Please select a valid item from the source warehouse. / يرجى اختيار صنف صحيح.';
  }
  if ($action_qty === '' || !is_numeric($action_qty) || (float)$action_qty <= 0) {
    $errors[] = 'Quantity must be a positive number. / الكمية يجب أن تكون رقماً موجباً.';
  }
  if ($action_type === 'transfer' && ($destination_wh_id === '' || !ctype_digit((string)$destination_wh_id))) {
    $errors[] = 'Please select a valid destination warehouse for transfer. / يرجى اختيار مخزن الوجهة.';
  }

  if (empty($errors)) {
    $qty_val = (float)$action_qty;

    try {
      $pdo->beginTransaction();

      $stmt_check = $pdo->prepare("SELECT * FROM warehouse_inventory WHERE id = ? FOR UPDATE");
      $stmt_check->execute([$inventory_id]);
      $item = $stmt_check->fetch(PDO::FETCH_ASSOC);

      if (!$item) {
        throw new Exception("Inventory item not found. / لم يتم العثور على الصنف.");
      }

      if ($qty_val > (float)$item['quantity_on_hand']) {
        throw new Exception("Requested quantity exceeds available stock. / الكمية المطلوبة تتجاوز المتاح.");
      }

      if ($action_type === 'transfer' && $item['warehouse_id'] == $destination_wh_id) {
        throw new Exception("Source and destination warehouses cannot be the same. / لا يمكن التحويل لنفس المخزن.");
      }

      $stmt_deduct = $pdo->prepare("UPDATE warehouse_inventory SET quantity_on_hand = quantity_on_hand - ? WHERE id = ?");
      $stmt_deduct->execute([$qty_val, $inventory_id]);

      $source_trans_type = $action_type === 'transfer' ? 'transfer' : (in_array($transaction_type, ['out', 'return', 'adjustment']) ? $transaction_type : 'out');
      
      $stmt_trans_out = $pdo->prepare("
        INSERT INTO warehouse_transactions (warehouse_id, inventory_id, transaction_type, quantity, reference_doc, notes, logged_by, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
      ");
      $stmt_trans_out->execute([
        $item['warehouse_id'],
        $inventory_id,
        $source_trans_type,
        $qty_val,
        $reference_doc !== '' ? $reference_doc : null,
        $notes !== '' ? $notes : null,
        $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'System'
      ]);

      if ($action_type === 'transfer') {
        $stmt_check_dest = $pdo->prepare("SELECT id FROM warehouse_inventory WHERE warehouse_id = ? AND sku = ? AND item_name = ? FOR UPDATE");
        $stmt_check_dest->execute([$destination_wh_id, $item['sku'], $item['item_name']]);
        $dest_item = $stmt_check_dest->fetch(PDO::FETCH_ASSOC);

        if ($dest_item) {
          $dest_inv_id = $dest_item['id'];
          $stmt_add = $pdo->prepare("UPDATE warehouse_inventory SET quantity_on_hand = quantity_on_hand + ? WHERE id = ?");
          $stmt_add->execute([$qty_val, $dest_inv_id]);
        } else {
          $stmt_insert_dest = $pdo->prepare("
            INSERT INTO warehouse_inventory (warehouse_id, item_category, item_name, sku, quantity_on_hand) 
            VALUES (?, ?, ?, ?, ?)
          ");
          $stmt_insert_dest->execute([
            $destination_wh_id,
            $item['item_category'],
            $item['item_name'],
            $item['sku'],
            $qty_val
          ]);
          $dest_inv_id = $pdo->lastInsertId();
        }

        $stmt_trans_in = $pdo->prepare("
          INSERT INTO warehouse_transactions (warehouse_id, inventory_id, transaction_type, quantity, reference_doc, notes, logged_by, transaction_date)
          VALUES (?, ?, 'in', ?, ?, ?, ?, NOW())
        ");
        $stmt_trans_in->execute([
          $destination_wh_id,
          $dest_inv_id,
          $qty_val,
          $reference_doc !== '' ? $reference_doc : null,
          $notes !== '' ? "Transferred from: " . $item['warehouse_id'] . " | " . $notes : "Transferred from Warehouse ID: " . $item['warehouse_id'],
          $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'System'
        ]);
      }

      $pdo->commit();
      redirect(app_url('modules/warehouse/withdraw.php?success=1'));

    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errors[] = $e->getMessage();
    }
  }
}

$inventory_query = $pdo->query("
  SELECT wi.id, wi.sku, wi.item_name, wi.quantity_on_hand, w.warehouse_name, w.warehouse_type 
  FROM warehouse_inventory wi 
  JOIN warehouses w ON wi.warehouse_id = w.id 
  WHERE wi.quantity_on_hand > 0 AND w.status = 'active'
  ORDER BY w.warehouse_name ASC, wi.item_name ASC
");
$inventory_list = $inventory_query ? $inventory_query->fetchAll(PDO::FETCH_ASSOC) : [];

$grouped_inventory = [];
foreach ($inventory_list as $row) {
  $wh_name = $row['warehouse_name'] . ($row['warehouse_type'] === 'client' ? ' (Custody)' : ' (Internal)');
  $grouped_inventory[$wh_name][] = $row;
}

$warehouses_query = $pdo->query("SELECT id, warehouse_name, warehouse_type FROM warehouses WHERE status = 'active' ORDER BY warehouse_name ASC");
$warehouses = $warehouses_query ? $warehouses_query->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Manage Stock Allocation & Transfer | I-GAS Industrial" data-ar="إدارة وصرف وتحويل المخزون | I-GAS Industrial">Manage Stock Allocation & Transfer | I-GAS Industrial</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=Oxanium:wght@500;600;700&family=Reem+Kufi:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../../assets/css/theme.css">
  <script>
    (function() {
      document.documentElement.classList.add('dark');
      const savedLang = localStorage.getItem('app_lang') || 'en';
      document.documentElement.lang = savedLang;
      document.documentElement.dir = savedLang === 'ar' ? 'rtl' : 'ltr';
    })();
  </script>
  <style>
    .font-oxanium { font-family: 'Oxanium', 'Reem Kufi', sans-serif; }
    .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="flex h-screen overflow-hidden antialiased bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400">

<?php include __DIR__ . '/../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-y-auto bg-[#071018]">

  <?php include __DIR__ . '/../../includes/header.php'; ?>

  <!-- Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-teal-500"></span>
        <span data-en="INVENTORY TRANSACTION & CUSTODY DISPATCH" data-ar="حركات المخزون وصرف العهد">INVENTORY TRANSACTION & CUSTODY DISPATCH</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="CONTROL MODE: DUAL-GATE VERIFIED" data-ar="وضع التحكم: معتمد ببوابة مزدوجة">CONTROL MODE: DUAL-GATE VERIFIED</span>
      </span>
    </div>
    <div class="flex items-center gap-3 text-neutral-500 text-xs">
      <span>SEC: WH-ALLOC</span>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="p-4 sm:p-6 md:p-8 w-full mx-auto space-y-6">

    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="<?= app_url('modules/warehouse/warehouses.php') ?>" class="text-xs font-mono uppercase tracking-wider text-neutral-500 hover:text-white transition-colors" data-en="Warehouse Inventory" data-ar="مستودعات المنشأة">Warehouse Inventory</a>
          <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-neutral-400 rtl:rotate-180"></i>
          <span class="text-xs font-mono uppercase tracking-wider text-white font-semibold" data-en="Stock Allocation & Transfer" data-ar="صرف وتحويل المخزون">Stock Allocation & Transfer</span>
        </div>
        <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white" data-en="Manage Stock Allocation & Transfer" data-ar="إدارة صرف وتحويل الأصول والمخزون">Manage Stock Allocation & Transfer</h1>
        <p class="text-xs text-slate-400 mt-1" data-en="Deduct assets or transfer quantities between depots, manifolds, and customer custody yards." data-ar="سحب الأصول أو تحويل الكميات بين المستودعات والمشعبات وساحات عهد العملاء.">Deduct assets or transfer quantities between depots, manifolds, and customer custody yards.</p>
      </div>
      <div class="flex items-center gap-2.5">
        <a href="<?= app_url('modules/warehouse/warehouses.php') ?>" class="border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 px-4 py-2 text-xs font-mono uppercase tracking-wider font-medium transition-colors flex items-center gap-2">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5 rtl:rotate-180"></i>
          <span data-en="Back to Directory" data-ar="العودة للدليل">Back to Directory</span>
        </a>
        <button type="submit" form="inventory-action-form" class="btn-cta-orange px-4 py-2 text-xs font-mono uppercase tracking-wider font-semibold transition-colors flex items-center gap-2">
          <i data-lucide="check-circle-2" class="w-4 h-4"></i>
          <span data-en="Execute Transaction" data-ar="تنفيذ الحركة">Execute Transaction</span>
        </button>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="p-4 border border-rose-800 dark:border-rose-900 bg-rose-950/30 dark:bg-rose-950/30 text-rose-300 dark:text-rose-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
        <div>
          <strong class="font-mono text-xs uppercase" data-en="Action Failed:" data-ar="فشل الإجراء:">Action Failed:</strong>
          <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs">
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
      <div class="p-4 border border-teal-800 dark:border-teal-900 bg-teal-950/30 dark:bg-teal-950/30 text-teal-300 dark:text-teal-300 rounded-xs flex items-center gap-3 text-sm">
        <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-teal-600 dark:text-teal-400"></i>
        <div>
          <strong class="font-mono text-xs uppercase" data-en="Transaction Completed" data-ar="تم تنفيذ الحركة">Transaction Completed</strong>
          <p class="text-xs mt-0.5" data-en="Stock deduction or transfer was recorded and inventory balances updated successfully." data-ar="تم تسجيل عملية الصرف أو التحويل وتحديث أرصدة المخزون بنجاح.">Stock deduction or transfer was recorded and inventory balances updated successfully.</p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Grid -->
    <form id="inventory-action-form" action="<?= app_url('modules/warehouse/withdraw.php') ?>" method="POST">
      <?= csrf_field() ?>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column (Main Form) -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Step 1: Action Protocol -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs p-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
              <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
              <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
                <i data-lucide="git-branch" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="1. Select Transaction Protocol" data-ar="١. اختيار نوع العملية">1. Select Transaction Protocol</span>
              </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label class="relative flex items-center gap-3.5 p-4 border border-[#1a3346] bg-[#0c1924] cursor-pointer hover:border-neutral-400 dark:hover:border-neutral-600 transition-colors">
                <input type="radio" name="action_type" value="withdraw" checked onchange="toggleActionFields()" class="w-4 h-4 text-white border-neutral-300 focus:ring-0">
                <div>
                  <div class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
                    <i data-lucide="package-minus" class="w-4 h-4 text-amber-500"></i>
                    <span data-en="Withdraw / Deduct" data-ar="سحب / صرف من المخزون">Withdraw / Deduct</span>
                  </div>
                  <p class="text-xs text-slate-400 mt-0.5" data-en="Direct consumption, damage write-off, or return" data-ar="استهلاك مباشر، إتلاف، أو إرجاع للمورد">Direct consumption, damage write-off, or return</p>
                </div>
              </label>
              <label class="relative flex items-center gap-3.5 p-4 border border-[#1a3346] bg-[#0c1924] cursor-pointer hover:border-neutral-400 dark:hover:border-neutral-600 transition-colors">
                <input type="radio" name="action_type" value="transfer" onchange="toggleActionFields()" class="w-4 h-4 text-white border-neutral-300 focus:ring-0">
                <div>
                  <div class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
                    <i data-lucide="arrow-right-left" class="w-4 h-4 text-teal-500"></i>
                    <span data-en="Inter-Depot Transfer" data-ar="تحويل بين المستودعات">Inter-Depot Transfer</span>
                  </div>
                  <p class="text-xs text-slate-400 mt-0.5" data-en="Move quantities to another depot or custody yard" data-ar="نقل الكميات إلى مستودع آخر أو ساحة عهدة">Move quantities to another depot or custody yard</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Step 2: Item & Allocation Specs -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs p-6 border-s-4 border-s-[#f97316]">
            <div class="flex items-center gap-2 mb-4 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
              <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
              <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
                <i data-lucide="box" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="2. Item & Quantity Allocation" data-ar="٢. تخصيص الصنف والكمية">2. Item & Quantity Allocation</span>
              </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div class="md:col-span-2">
                <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Source Inventory SKU & Depot *" data-ar="صنف المخزون والمستودع المصدر *">
                  Source Inventory SKU & Depot *
                </label>
                <select name="inventory_id" id="inventory_id" required onchange="updateMaxQty()" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
                  <option value="" data-en="-- Select Source Item & Depot --" data-ar="-- اختر الصنف ومستودع المصدر --">-- Select Source Item & Depot --</option>
                  <?php foreach ($grouped_inventory as $wh_name => $items): ?>
                    <optgroup label="<?= htmlspecialchars($wh_name) ?>">
                      <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>" data-max="<?= $item['quantity_on_hand'] ?>">
                          [<?= htmlspecialchars($item['sku']) ?>] <?= htmlspecialchars($item['item_name']) ?> — Available: <?= (float)$item['quantity_on_hand'] ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Action Quantity *" data-ar="الكمية المطلوبة *">
                  Action Quantity *
                </label>
                <input type="number" name="action_qty" id="action_qty" placeholder="0.00" min="0.01" step="0.01" required disabled class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors disabled:bg-[#102230] dark:disabled:bg-neutral-800 disabled:cursor-not-allowed">
                <p class="text-[11px] font-mono text-neutral-500 mt-1" id="qty_help_text" data-en="Select an item to view maximum stock limit." data-ar="اختر صنفاً لمعرفة الحد الأقصى للمخزون المتاح.">Select an item to view maximum stock limit.</p>
              </div>

              <div id="transaction_type_container">
                <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Deduction Reason *" data-ar="سبب الخصم والعملية *">
                  Deduction Reason *
                </label>
                <select name="transaction_type" id="transaction_type" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
                  <option value="out" data-en="Out / Operational Dispatch" data-ar="منصرف / صرف تشغيلي">Out / Operational Dispatch</option>
                  <option value="return" data-en="Return to Vendor / Supplier" data-ar="مرتجع للمورد الأصلي">Return to Vendor / Supplier</option>
                  <option value="adjustment" data-en="Damage / Technical Scrappage" data-ar="تسوية (تلف فني أو إهلاك)">Damage / Technical Scrappage</option>
                </select>
              </div>

              <div id="destination_warehouse_container" class="hidden">
                <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Destination Facility / Yard *" data-ar="المستودع أو الساحة الوجهة *">
                  Destination Facility / Yard *
                </label>
                <select name="destination_warehouse_id" id="destination_warehouse_id" class="w-full h-10 px-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
                  <option value="" data-en="-- Select Destination Facility --" data-ar="-- اختر المستودع أو الساحة الوجهة --">-- Select Destination Facility --</option>
                  <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>">
                      <?= htmlspecialchars($wh['warehouse_name']) ?> (<?= ucfirst($wh['warehouse_type']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Column (Audit Log Details) -->
        <div class="lg:col-span-1 space-y-6">

          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs p-6 space-y-4 border-s-4 border-s-[#f97316]">
            <div class="flex items-center gap-2 pb-2 border-b border-neutral-100 border-[#1a3346]/60">
              <span class="w-1.5 h-3 bg-teal-500 rounded-xs"></span>
              <h2 class="text-xs font-mono uppercase tracking-wider font-semibold text-white flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                <span data-en="Audit Documentation" data-ar="مستندات التدقيق والتوثيق">Audit Documentation</span>
              </h2>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Reference Doc Number" data-ar="رقم المستند المرجعي">
                Reference Doc Number
              </label>
              <input type="text" name="reference_doc" placeholder="e.g. TR-8831, PO-1029" data-en-placeholder="e.g. TR-8831, PO-1029" data-ar-placeholder="مثال: TR-8831، PO-1029" class="w-full h-10 px-3 text-xs font-mono bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors">
            </div>

            <div>
              <label class="block text-xs font-mono uppercase tracking-wider text-slate-400 mb-1.5" data-en="Audit Notes / Reason" data-ar="ملاحظات وسند الصرف">
                Audit Notes / Reason
              </label>
              <textarea name="notes" rows="4" placeholder="Provide operational reason or logistic details..." data-en-placeholder="Provide operational reason or logistic details..." data-ar-placeholder="أدخل سبب الصرف أو بيانات الشحن اللوجستية..." class="w-full p-3 text-xs font-sans bg-[#08131c] border border-[#1e3a50] rounded-xs text-slate-100 placeholder-slate-500 focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50 outline-none transition-colors"></textarea>
            </div>

            <div class="pt-3 border-t border-[#1a3346] text-[11px] font-mono text-neutral-500 space-y-1.5">
              <div class="flex justify-between">
                <span data-en="OPERATOR:" data-ar="المستخدم:">OPERATOR:</span>
                <span class="font-medium text-slate-200"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'System') ?></span>
              </div>
              <div class="flex justify-between">
                <span data-en="TIMESTAMP:" data-ar="التوقيت:">TIMESTAMP:</span>
                <span class="font-medium text-slate-200"><?= date('Y-m-d H:i') ?></span>
              </div>
              <div class="flex justify-between">
                <span data-en="SECURITY:" data-ar="الأمان:">SECURITY:</span>
                <span class="text-teal-600 dark:text-teal-400">CSRF PROTECTED</span>
              </div>
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</main>

<script>
  function updateMaxQty() {
    const select = document.getElementById('inventory_id');
    const qtyInput = document.getElementById('action_qty');
    const helpText = document.getElementById('qty_help_text');
    const currentLang = localStorage.getItem('app_lang') || 'en';

    if (select.value) {
      const selectedOption = select.options[select.selectedIndex];
      const maxQty = selectedOption.getAttribute('data-max');
      
      qtyInput.disabled = false;
      qtyInput.max = maxQty;
      
      if (currentLang === 'ar') {
        helpText.innerText = `الحد الأقصى المتاح للصرف: ${maxQty}`;
      } else {
        helpText.innerText = `Maximum stock available: ${maxQty}`;
      }
    } else {
      qtyInput.disabled = true;
      qtyInput.value = '';
      if (currentLang === 'ar') {
        helpText.innerText = 'اختر صنفاً لمعرفة الحد الأقصى للمخزون المتاح.';
      } else {
        helpText.innerText = 'Select an item to view maximum stock limit.';
      }
    }
  }

  function toggleActionFields() {
    const actionType = document.querySelector('input[name="action_type"]:checked').value;
    const destContainer = document.getElementById('destination_warehouse_container');
    const transTypeContainer = document.getElementById('transaction_type_container');
    const destSelect = document.getElementById('destination_warehouse_id');
    
    if (actionType === 'transfer') {
      destContainer.classList.remove('hidden');
      transTypeContainer.classList.add('hidden');
      destSelect.required = true;
    } else {
      destContainer.classList.add('hidden');
      transTypeContainer.classList.remove('hidden');
      destSelect.required = false;
      destSelect.value = '';
    }
  }

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION' && el.hasAttribute('data-en')) {
        el.innerText = transText;
      } else if (el.tagName !== 'OPTION') {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      const transPlaceholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
      el.placeholder = transPlaceholder;
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    updateMaxQty();
  }

  document.addEventListener("DOMContentLoaded", function() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    const savedLang = localStorage.getItem('app_lang') || 'en';
    applyLanguage(savedLang);
    toggleActionFields();
  });
</script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';
require_permission($pdo, 'logistics.view');

$is_admin = has_role($pdo, 'Admin') || has_role($pdo, 'Super Admin') || has_role($pdo, 'Logistics Manager') || has_permission($pdo, 'logistics.edit');

$active_page = 'dispatch_delivery';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery', 'Manifest Details'];

$dispatch_id = $_GET['id'] ?? '';
if (empty($dispatch_id)) {
  $firstDisp = $pdo->query("SELECT manifest_id FROM dispatches ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstDisp) {
    $dispatch_id = $firstDisp;
  } else {
    redirect(app_url('modules/logistics_fleet/dispatch_delivery.php'));
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  require_csrf();
  require_permission($pdo, 'logistics.delete');
  $manifest_id_to_delete = $_POST['manifest_id'] ?? null;
  if ($manifest_id_to_delete !== null) {
    $stmt_delete = $pdo->prepare("DELETE FROM dispatches WHERE manifest_id = ?");
    $stmt_delete->execute([$manifest_id_to_delete]);
  }
  redirect(app_url('modules/logistics_fleet/dispatch_delivery.php'));
}

$stmt = $pdo->prepare("
  SELECT 
    d.manifest_id as id,
    d.order_ref,
    p.company_name as client,
    d.destination,
    d.vehicle_id,
    v.make_model as vehicle_name,
    v.plate_number as plate,
    d.driver_id,
    dr.full_name as driver_name,
    dr.mobile_number as driver_phone,
    d.status,
    d.eta_time as eta,
    d.dispatch_date,
    d.created_at,
    d.instructions
  FROM dispatches d
  LEFT JOIN purchase_orders po ON d.order_ref = po.order_number
  LEFT JOIN partners p ON po.client_id = p.id
  LEFT JOIN vehicles v ON d.vehicle_id = v.fleet_id
  LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
  WHERE (d.manifest_id = ? OR CAST(d.id AS CHAR) = ?)
");
$stmt->execute([$dispatch_id, $dispatch_id]);
$dispatch_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispatch_data) {
  $firstDisp = $pdo->query("SELECT manifest_id FROM dispatches ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstDisp) {
    $stmt->execute([$firstDisp, $firstDisp]);
    $dispatch_data = $stmt->fetch(PDO::FETCH_ASSOC);
  }
}

if (!$dispatch_data) {
  redirect(app_url('modules/logistics_fleet/dispatch_delivery.php'));
}

$stmt_items = $pdo->prepare("
  SELECT pi.item_name, pi.qty, pi.unit
  FROM purchase_order_items pi
  JOIN purchase_orders po ON pi.purchase_order_id = po.id
  WHERE po.order_number = ?
  LIMIT 1
");
$stmt_items->execute([$dispatch_data['order_ref']]);
$item_data = $stmt_items->fetch(PDO::FETCH_ASSOC);

$gas_type = $item_data['item_name'] ?? 'Liquid Oxygen (LOX 99.99%)';
$quantity = $item_data['qty'] ?? '18.50';
$unit = $item_data['unit'] ?? 'TON';

$dispatch = [
  'id' => $dispatch_data['id'],
  'order_ref' => $dispatch_data['order_ref'],
  'client' => $dispatch_data['client'] ?? 'Industrial Gases Corp',
  'destination' => $dispatch_data['destination'],
  'vehicle_id' => $dispatch_data['vehicle_id'],
  'vehicle_name' => $dispatch_data['vehicle_name'] ?? 'Mercedes Actros Tanker',
  'plate' => $dispatch_data['plate'] ?? '---',
  'driver_id' => $dispatch_data['driver_id'],
  'driver_name' => $dispatch_data['driver_name'] ?? 'Unassigned',
  'driver_phone' => $dispatch_data['driver_phone'] ?? '---',
  'status' => strtolower($dispatch_data['status']),
  'eta' => !empty($dispatch_data['eta']) ? date('H:i', strtotime($dispatch_data['eta'])) : '--:--',
  'gas_type' => $gas_type,
  'quantity' => $quantity,
  'unit' => $unit,
  'created_at' => date('Y-m-d H:i', strtotime($dispatch_data['created_at'])),
  'instructions' => $dispatch_data['instructions'] ?: 'Ensure cryo grounding cable connected prior to liquid decanting.',
  'dispatch_date' => $dispatch_data['dispatch_date']
];

$creation_date = date('d M Y', strtotime($dispatch['created_at']));
$creation_time = date('H:i', strtotime($dispatch['created_at']));
$disp_date_fmt = date('d M Y', strtotime($dispatch['dispatch_date']));

$is_dispatched = $dispatch['status'] === 'dispatched';
$is_transit  = $dispatch['status'] === 'in_transit';
$is_delivered = $dispatch['status'] === 'delivered';
$is_cancelled = $dispatch['status'] === 'cancelled';

$timeline = [];
$timeline[] = [
  'time' => $creation_time, 
  'date' => $creation_date, 
  'title' => 'Manifest Generated', 
  'desc' => 'Dispatch manifest registered in system and assigned to transport unit.', 
  'status' => 'past'
];

if ($is_cancelled) {
  $timeline[] = [
    'time' => 'N/A', 
    'date' => $disp_date_fmt, 
    'title' => 'Dispatch Aborted', 
    'desc' => 'Delivery run cancelled due to logistics hold or customer notice.', 
    'status' => 'current'
  ];
} else {
  $timeline[] = [
    'time' => '08:30', 
    'date' => $disp_date_fmt, 
    'title' => 'Loading & Cryo Safety Check', 
    'desc' => 'Tanker filling completed, pressure verified, safety seals attached.', 
    'status' => ($is_dispatched ? 'current' : 'past')
  ];

  if ($is_transit || $is_delivered) {
    $timeline[] = [
      'time' => '09:15', 
      'date' => $disp_date_fmt, 
      'title' => 'Plant Gate Departure', 
      'desc' => 'Carrier cleared security gate, transit tracking telemetry active.', 
      'status' => 'past'
    ];
    $timeline[] = [
      'time' => 'Live GPS', 
      'date' => 'En Route', 
      'title' => 'In Transit to Site', 
      'desc' => 'Vehicle on highway towards ' . htmlspecialchars($dispatch['destination']) . '.', 
      'status' => ($is_transit ? 'current' : 'past')
    ];
  } else {
    $timeline[] = [
      'time' => 'Pending', 
      'date' => 'TBD', 
      'title' => 'Gate Departure & Highway Transit', 
      'desc' => 'Awaiting driver sign-off and gate clearance.', 
      'status' => 'future'
    ];
  }

  if ($is_delivered) {
    $timeline[] = [
      'time' => $dispatch['eta'], 
      'date' => $disp_date_fmt, 
      'title' => 'Customer Tank Delivery & Sign-Off', 
      'desc' => 'Liquid decanted into customer bulk tank; waybill signed.', 
      'status' => 'current'
    ];
  } else {
    $timeline[] = [
      'time' => $dispatch['eta'], 
      'date' => 'ETA Window', 
      'title' => 'Expected Customer Delivery', 
      'desc' => 'Pending arrival, decanting protocols, and delivery note sign-off.', 
      'status' => 'future'
    ];
  }
}

$statusStyles = [
  'dispatched' => ['badge' => 'border border-amber-500/30 bg-amber-500/10 text-amber-400 dark:text-amber-400', 'dot' => 'bg-amber-500', 'label_en' => 'Loading & Staging', 'label_ar' => 'جاري التحميل'],
  'in_transit' => ['badge' => 'border border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400', 'dot' => 'bg-cyan-500', 'label_en' => 'In Transit', 'label_ar' => 'في الطريق'],
  'delivered' => ['badge' => 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500', 'label_en' => 'Delivered', 'label_ar' => 'تم التوصيل'],
  'cancelled' => ['badge' => 'border border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400', 'dot' => 'bg-red-500', 'label_en' => 'Delayed / Aborted', 'label_ar' => 'ملغي / معلق'],
];

$ds = $statusStyles[$dispatch['status']] ?? $statusStyles['dispatched'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-en="Manifest <?= htmlspecialchars($dispatch['id']) ?> | I-GAS Control Room" data-ar="بيان شحنة <?= htmlspecialchars($dispatch['id']) ?> | غرفة التحكم I-GAS">Manifest <?= htmlspecialchars($dispatch['id']) ?> | I-GAS Control Room</title>
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

  <!-- Control Room Sub-Header / Telemetry Strip -->
  <div class="h-9 border-b border-[#152938] bg-[#060e14] text-slate-400 px-4 md:px-8 flex items-center justify-between text-xs font-mono shrink-0">
    <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
      <span class="inline-flex items-center gap-1.5 text-slate-400">
        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
        <span data-en="TRIP MANIFEST & ROUTE WAYBILL TELEMETRY" data-ar="بيان إرسالية ومسار الشحن المباشر">TRIP MANIFEST & ROUTE WAYBILL TELEMETRY</span>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="MANIFEST ID:" data-ar="معرف البيان:">MANIFEST ID:</span> <strong class="text-cyan-700 dark:text-cyan-400"><?= htmlspecialchars($dispatch['id']) ?></strong>
      </span>
      <span class="text-neutral-300 dark:text-neutral-700">|</span>
      <span class="text-slate-400">
        <span data-en="ORDER:" data-ar="الطلب:">ORDER:</span> <strong class="text-white"><?= htmlspecialchars($dispatch['order_ref']) ?></strong>
      </span>
    </div>
    <div class="hidden sm:flex items-center gap-2 text-slate-400 text-[11px]">
      <i data-lucide="shield-check" class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400"></i>
      <span data-en="HAZMAT TRANSIT PERMIT VALID" data-ar="تصريح نقل الغاز سارٍ">HAZMAT TRANSIT PERMIT VALID</span>
    </div>
  </div>

  <!-- Main Content Container -->
  <div class="flex-1 p-4 md:p-8 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-[#1a3346] pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="dispatch_delivery.php" class="px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-widest font-semibold border border-[#1e3a50] text-slate-400 hover:border-teal-500 hover:text-teal-600 rounded-xs transition-colors">
            ← DISPATCH REGISTRY
          </a>
          <span class="text-xs text-slate-400 font-mono">SYS-DSP-02</span>
        </div>
        <div class="flex items-center gap-3">
          <h1 class="text-xl md:text-2xl font-bold font-oxanium tracking-tight text-white">
            <?= htmlspecialchars($dispatch['id']) ?>
          </h1>
          <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-xs text-[11px] font-mono font-medium <?= $ds['badge'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $ds['dot'] ?>"></span>
            <span data-en="<?= $ds['label_en'] ?>" data-ar="<?= $ds['label_ar'] ?>"><?= $ds['label_en'] ?></span>
          </span>
        </div>
        <p class="text-xs md:text-sm text-slate-400 mt-1" data-en="Generated on <?= htmlspecialchars($dispatch['created_at']) ?> for client <?= htmlspecialchars($dispatch['client']) ?>." data-ar="تم إنشاء البيان في <?= htmlspecialchars($dispatch['created_at']) ?> لصالح العميل <?= htmlspecialchars($dispatch['client']) ?>.">
          Generated on <?= htmlspecialchars($dispatch['created_at']) ?> for client <?= htmlspecialchars($dispatch['client']) ?>.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <a href="dispatch_delivery.php" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-mono uppercase tracking-wider text-slate-300 bg-[#112433] text-slate-200 border-[#234257] bg-[#112433] border border-[#1e3a50] rounded-xs hover:bg-[#102332] dark:hover:bg-neutral-700 transition-colors">
          <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
          <span data-en="Back" data-ar="رجوع">Back</span>
        </a>
        <button onclick="window.print()" class="btn-cta-orange inline-flex items-center gap-2 px-4 py-2 text-xs font-medium font-mono uppercase tracking-wider text-white bg-[#FF6B1A] hover:bg-[#e05a10] rounded-xs cursor-pointer transition-colors">
          <i data-lucide="printer" class="w-4 h-4"></i>
          <span data-en="Print Waybill" data-ar="طباعة البوليصة">Print Waybill</span>
        </button>
      </div>
    </div>

    <!-- 2-Column Grid Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Left 2 Columns: Manifest Dossier -->
      <div class="xl:col-span-2 space-y-6">
        
        <!-- General Info Card -->
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden border-s-4 border-s-[#f97316]">
          <div class="p-4 border-b border-[#1a3346] bg-[#09151f]">
            <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="1. Route & Commercial Context" data-ar="1. بيانات المسار والطلب التجاري">
              1. Route & Commercial Context
            </h3>
          </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs font-mono">
            <div>
              <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Purchase Order Reference" data-ar="مرجع طلب الشراء">Purchase Order Reference</span>
              <div class="text-sm font-bold text-teal-400 dark:text-teal-400">
                <?= htmlspecialchars($dispatch['order_ref']) ?>
              </div>
            </div>

            <div>
              <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Client Commercial Account" data-ar="حساب العميل التجاري">Client Commercial Account</span>
              <div class="text-sm font-bold text-white font-sans">
                <?= htmlspecialchars($dispatch['client']) ?>
              </div>
            </div>

            <div class="col-span-1 md:col-span-2 pt-2 border-t border-[#1a3346]">
              <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Delivery Destination Address" data-ar="عنوان موقع التسليم">Delivery Destination Address</span>
              <div class="text-xs font-sans text-slate-200 font-medium">
                <?= htmlspecialchars($dispatch['destination']) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Logistics & Carrier Allocation Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <!-- Carrier & Driver Allocation -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="p-4 border-b border-[#1a3346] bg-[#09151f]">
              <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="2. Resource Allocation" data-ar="2. تخصيص الموارد">
                2. Resource Allocation
              </h3>
            </div>
            <div class="p-6 space-y-5 text-xs font-mono">
              <div>
                <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Assigned Carrier Unit" data-ar="الناقلة المعينة">Assigned Carrier Unit</span>
                <div class="flex items-center gap-2">
                  <i data-lucide="truck" class="w-4 h-4 text-teal-600 dark:text-teal-400"></i>
                  <span class="font-bold text-white"><?= htmlspecialchars($dispatch['vehicle_name']) ?></span>
                </div>
                <div class="text-[11px] text-neutral-500 mt-1">
                  <?= htmlspecialchars($dispatch['vehicle_id']) ?> · <?= htmlspecialchars($dispatch['plate']) ?>
                </div>
                <a href="vehicle_logs.php?id=<?= urlencode($dispatch['vehicle_id']) ?>" class="inline-block mt-2 text-[10px] text-teal-600 dark:text-teal-400 hover:underline">
                  → <span data-en="View Vehicle Logs" data-ar="عرض سجل المركبة">View Vehicle Logs</span>
                </a>
              </div>

              <div class="pt-4 border-t border-[#1a3346]">
                <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Designated Hazmat Driver" data-ar="السائق المعتمد">Designated Hazmat Driver</span>
                <div class="flex items-center gap-2">
                  <i data-lucide="user" class="w-4 h-4 text-neutral-400"></i>
                  <span class="font-bold text-white font-sans"><?= htmlspecialchars($dispatch['driver_name']) ?></span>
                </div>
                <div class="text-[11px] text-neutral-500 mt-1">
                  <?= htmlspecialchars($dispatch['driver_id']) ?> · <?= htmlspecialchars($dispatch['driver_phone']) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Freight Payload Specs -->
          <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
            <div class="p-4 border-b border-[#1a3346] bg-[#09151f]">
              <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="3. Freight & Payload" data-ar="3. مواصفات الحمولة">
                3. Freight & Payload
              </h3>
            </div>
            <div class="p-6 space-y-5 text-xs font-mono">
              <div>
                <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Gas Specification / Blend" data-ar="نوع الغاز / الخليط">Gas Specification / Blend</span>
                <div class="text-sm font-bold text-white">
                  <?= htmlspecialchars($dispatch['gas_type']) ?>
                </div>
              </div>

              <div>
                <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Payload Quantity" data-ar="كمية الحمولة">Payload Quantity</span>
                <div class="text-2xl font-bold font-mono text-white">
                  <?= htmlspecialchars($dispatch['quantity']) ?> <span class="text-xs text-neutral-500 font-normal"><?= htmlspecialchars($dispatch['unit']) ?></span>
                </div>
              </div>

              <div class="pt-4 border-t border-[#1a3346]">
                <span class="block text-[10px] uppercase text-neutral-500 mb-1" data-en="Safety & Decanting Notes" data-ar="تعليمات السلامة والتفريغ">Safety & Decanting Notes</span>
                <p class="text-slate-300 font-sans text-xs leading-relaxed">
                  <?= htmlspecialchars($dispatch['instructions']) ?>
                </p>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- Right Column: Timeline & Route Progress -->
      <div class="space-y-6">
        
        <div class="bg-[#0c1924] border border-[#1a3346] rounded-xs overflow-hidden">
          <div class="p-4 border-b border-[#1a3346] bg-[#09151f] flex justify-between items-center">
            <h3 class="text-xs font-mono uppercase tracking-wider font-semibold text-white" data-en="Route Execution Milestones" data-ar="مراحل تنفيذ مسار الرحلة">
              Route Execution Milestones
            </h3>
            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
          </div>

          <div class="p-6 space-y-6">
            <?php foreach ($timeline as $idx => $point): ?>
              <div class="flex gap-4 relative">
                <!-- Marker -->
                <div class="flex flex-col items-center">
                  <div class="w-3 h-3 rounded-full border-2 <?= $point['status'] === 'past' ? 'border-emerald-500 bg-emerald-500' : ($point['status'] === 'current' ? 'border-cyan-500 bg-cyan-500' : 'border-neutral-400 bg-transparent') ?> shrink-0"></div>
                  <?php if ($idx < count($timeline) - 1): ?>
                    <div class="w-px h-full bg-[#112433] my-1"></div>
                  <?php endif; ?>
                </div>

                <!-- Text -->
                <div class="flex-1 pb-4">
                  <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold font-oxanium text-white">
                      <?= htmlspecialchars($point['title']) ?>
                    </h4>
                    <span class="text-[10px] font-mono text-neutral-500">
                      <?= htmlspecialchars($point['time']) ?>
                    </span>
                  </div>
                  <p class="text-xs text-slate-400 mt-1 font-sans">
                    <?= htmlspecialchars($point['desc']) ?>
                  </p>
                  <div class="text-[10px] font-mono text-neutral-400 mt-1">
                    <?= htmlspecialchars($point['date']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

    </div>

  </div>
</main>

<script>
  lucide.createIcons();

  function applyLanguage(lang) {
    document.querySelectorAll('[data-en]').forEach(el => {
      const transText = el.getAttribute('data-' + lang) || el.getAttribute('data-en');
      if ((el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') && el.hasAttribute('placeholder')) {
        el.placeholder = transText;
      } else if (el.tagName === 'OPTION') {
        el.innerText = transText;
      } else {
        el.innerText = transText;
      }
    });
    document.querySelectorAll('[data-en-placeholder]').forEach(el => {
      el.placeholder = el.getAttribute('data-' + lang + '-placeholder') || el.getAttribute('data-en-placeholder');
    });
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }

  document.addEventListener("DOMContentLoaded", () => {
    applyLanguage(localStorage.getItem('app_lang') || 'en');
  });
</script>
</body>
</html>
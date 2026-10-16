<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ui_helpers.php';

require_permission('orders.view');

$active_page = 'orders';
$base_url  = '../../';
$breadcrumb = ['I-GAS', 'Production', 'Orders', 'Order Details'];

$order_num = $_GET['order_num'] ?? $_GET['id'] ?? '';

if (empty($order_num)) {
  $firstOrder = $pdo->query("SELECT order_number FROM purchase_orders ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($firstOrder) {
    $order_num = $firstOrder;
  } else {
    redirect(app_url('modules/production/raw_materials.php'));
  }
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE (order_number = ? OR CAST(id AS CHAR) = ?)");
$stmt->execute([$order_num, $order_num]);
$order = $stmt->fetch();

if (!$order) {
  redirect(app_url('modules/production/raw_materials.php'));
}

if (!empty($order['client_id']) && !check_record_ownership($pdo, 'partners', (int)$order['client_id'], 'sales_rep_id')) {
  http_response_code(403);
  die("Access denied: You do not have permission to view this order.");
}

$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt_client->execute([$order['client_id']]);
$client = $stmt_client->fetch();

$supplier = null;
if (!empty($order['supplier_reference'])) {
  $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ?");
  $stmt_supp->execute([$order['supplier_reference']]);
  $supplier = $stmt_supp->fetch();
}

$stmt_items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll();

$statusStyles = [
  'draft'   => ['bg' => 'bg-[#09151f] text-slate-400 border-[#1e3a50]', 'dot' => 'bg-neutral-400', 'label' => 'Draft'],
  'processing' => ['bg' => 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/30',                      'dot' => 'bg-cyan-500',  'label' => 'Processing'],
  'in_transit' => ['bg' => 'bg-amber-500/10 text-amber-400 dark:text-amber-400 border-amber-500/30',                    'dot' => 'bg-amber-500',  'label' => 'In Transit'],
  'delivered' => ['bg' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',                'dot' => 'bg-emerald-500', 'label' => 'Delivered'],
  'cancelled' => ['bg' => 'bg-rose-500/10 text-rose-400 dark:text-rose-400 border-rose-500/30',                      'dot' => 'bg-rose-500',  'label' => 'Cancelled']
];

$os = $statusStyles[$order['status']] ?? $statusStyles['draft'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= ($lang ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>" class="<?= ($theme ?? 'light') === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order <?= htmlspecialchars($order['order_number']) ?> | Orders | I-GAS Control Room</title>
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
      INTERNAL PRODUCTION PROCUREMENT // ORDER MANIFEST
    </span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">ORDER: <?= htmlspecialchars($order['order_number']) ?></span>
    <span class="w-px h-3 bg-neutral-300 dark:bg-neutral-700"></span>
    <span class="text-slate-400 font-mono">CLIENT: <?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></span>
  </div>

  <!-- Main Content Container -->
  <div class="p-4 sm:p-6 md:p-8 space-y-6">

    <!-- Navigation Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
      <a href="<?= app_url('modules/production/raw_materials.php') ?>" class="inline-flex items-center gap-1 text-teal-600 dark:text-teal-400 hover:underline">
        <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
        <span>BACK TO RAW MATERIALS</span>
      </a>
      <span>/</span>
      <span class="text-slate-300"><?= htmlspecialchars($order['order_number']) ?></span>
    </div>

    <!-- Master Order Header Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm p-5 sm:p-6 border-s-4 border-s-[#f97316]">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-xs border border-[#1e3a50] bg-[#09151f] flex items-center justify-center flex-shrink-0">
            <?= icon('shopping-cart', 'w-7 h-7 text-teal-600 dark:text-teal-400') ?>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
              <h1 class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-white">
                <?= htmlspecialchars($order['order_number']) ?>
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xs text-xs font-mono border <?= $os['bg'] ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $os['dot'] ?>"></span>
                <?= $os['label'] ?>
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs font-mono text-slate-400">
              <span>Date: <span class="text-slate-200 font-semibold"><?= htmlspecialchars(date('d M Y', strtotime($order['order_date']))) ?></span></span>
              <span>•</span>
              <span>Total Value: <span class="text-white font-bold tabular-nums"><?= number_format($order['total_value'], 2) ?> SAR</span></span>
            </div>
          </div>
        </div>

        <!-- Action Controls: Print (secondary) & Single Primary Orange CTA -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-3 py-2 border border-[#1e3a50] bg-[#09151f] text-slate-200 text-xs font-mono uppercase tracking-wider hover:bg-[#112433] hover:text-white transition rounded-xs focus:outline-teal-500">
            <?= icon('printer', 'w-4 h-4') ?>
            <span>Print Document</span>
          </button>
          <?php if (has_permission('orders.edit')): ?>
          <a href="<?= app_url('modules/crm_sales/order_details.php?id=' . (int)$order['id']) ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-transparent bg-safety-orange text-white text-xs font-mono uppercase tracking-wider font-semibold hover:bg-orange-600 transition rounded-xs focus:outline-teal-500">
            <?= icon('edit-3', 'w-4 h-4') ?>
            <span>Edit Order</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 3-Column Entity Cards: Client, Supplier, Delivery -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Client Details -->
      <div class="border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] rounded-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
          <?= icon('user', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
            Client Details
          </h2>
        </div>
        <div class="p-5 space-y-4 text-xs font-mono">
          <div class="border-b border-[#1a3346] pb-3">
            <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Company Name</span>
            <span class="text-white font-bold text-sm"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></span>
            <span class="text-neutral-400 block text-[11px] mt-0.5"><?= htmlspecialchars($client['reference_id'] ?? '') ?></span>
          </div>
          <div class="grid grid-cols-2 gap-3 border-b border-[#1a3346] pb-3">
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Contact Person</span>
              <span class="text-slate-200 font-medium"><?= htmlspecialchars(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? '')) ?></span>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Phone</span>
              <span class="text-slate-200"><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></span>
            </div>
          </div>
          <div>
            <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Billing Address</span>
            <span class="text-slate-300 block leading-relaxed mt-1">
              <?= htmlspecialchars($client['address'] ?? '') ?>, 
              <?= htmlspecialchars($client['city'] ?? '') ?>, 
              <?= htmlspecialchars($client['country'] ?? '') ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Merchant / Supplier -->
      <div class="border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] rounded-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
          <?= icon('truck', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
            Merchant / Supplier
          </h2>
        </div>
        <div class="p-5 space-y-4 text-xs font-mono">
          <?php if ($supplier): ?>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Supplier Name</span>
              <span class="text-white font-bold text-sm"><?= htmlspecialchars($supplier['company_name']) ?></span>
              <span class="text-neutral-400 block text-[11px] mt-0.5"><?= htmlspecialchars($supplier['reference_id']) ?></span>
            </div>
            <div class="grid grid-cols-2 gap-3 border-b border-[#1a3346] pb-3">
              <div>
                <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">CR / Tax ID</span>
                <span class="text-slate-200"><?= htmlspecialchars($supplier['cr_number'] ?? 'N/A') ?></span>
              </div>
              <div>
                <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Contact</span>
                <span class="text-slate-200"><?= htmlspecialchars($supplier['phone'] ?? 'N/A') ?></span>
              </div>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Origin Address</span>
              <span class="text-slate-300 block leading-relaxed mt-1">
                <?= htmlspecialchars($supplier['address'] ?? '') ?>, 
                <?= htmlspecialchars($supplier['city'] ?? '') ?>, 
                <?= htmlspecialchars($supplier['country'] ?? '') ?>
              </span>
            </div>
          <?php else: ?>
            <div class="border-b border-[#1a3346] pb-3">
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Order Specifications</span>
              <span class="text-slate-200 block mt-1"><?= htmlspecialchars($order['specs'] ?? 'Internal Direct Fulfillment') ?></span>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Supplier Fulfillment</span>
              <span class="text-neutral-400 block mt-1">Direct production manifold dispensing — no external vendor intermediary.</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Logistics & Delivery -->
      <div class="border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924] rounded-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center gap-2">
          <?= icon('map-pin', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
            Logistics & Delivery
          </h2>
        </div>
        <div class="p-5 space-y-4 text-xs font-mono">
          <div class="border-b border-[#1a3346] pb-3">
            <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Delivery Location</span>
            <span class="text-slate-200 block leading-relaxed mt-1">
              <?= !empty($order['delivery_address']) ? nl2br(htmlspecialchars($order['delivery_address'])) : 'Plant Yard Delivery Dock #3' ?>
            </span>
          </div>
          <div class="grid grid-cols-2 gap-3 border-b border-[#1a3346] pb-3">
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Delivery Date</span>
              <span class="text-slate-200 font-semibold">
                <?= !empty($order['delivery_date']) ? htmlspecialchars(date('d M Y', strtotime($order['delivery_date']))) : 'Scheduled' ?>
              </span>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Time Window</span>
              <span class="text-slate-200">
                <?= !empty($order['delivery_time']) ? htmlspecialchars(date('h:i A', strtotime($order['delivery_time']))) : 'Daytime Shift' ?>
              </span>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Priority</span>
              <span class="text-teal-600 dark:text-teal-400 font-bold uppercase"><?= htmlspecialchars($order['delivery_priority'] ?? 'Standard') ?></span>
            </div>
            <div>
              <span class="text-neutral-500 uppercase tracking-wider block text-[11px]">Payment Terms</span>
              <span class="text-white font-bold uppercase"><?= htmlspecialchars($order['payment_terms'] ?? 'COD') ?></span>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Order Items Table Card -->
    <div class="border border-[#1a3346] bg-[#0c1924] rounded-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-[#1a3346] bg-[#09151f] flex items-center justify-between">
        <div class="flex items-center gap-2">
          <?= icon('list', 'w-4 h-4 text-teal-600 dark:text-teal-400') ?>
          <h2 class="text-xs font-bold font-heading uppercase tracking-wider text-white">
            Order Manifest Items
          </h2>
        </div>
        <span class="text-xs font-mono text-neutral-500 uppercase">Itemized Manifest</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-[#1a3346] bg-[#102230]/60 dark:bg-[#060e14]/80 backdrop-blur-sm text-slate-400 font-mono uppercase tracking-wider">
              <th class="py-3 px-4">ITEM CODE</th>
              <th class="py-3 px-4">DESCRIPTION</th>
              <th class="py-3 px-4 text-right">QUANTITY</th>
              <th class="py-3 px-4 text-right">UNIT PRICE</th>
              <th class="py-3 px-4 text-right">DISCOUNT</th>
              <th class="py-3 px-4 text-right">LINE TOTAL</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#152a3a] font-mono">
            <?php if (empty($items)): ?>
              <tr>
                <td colspan="6" class="px-5 py-8 text-center text-neutral-500">
                  No specific line items recorded for this order. Total value derived from specs.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $item): ?>
              <tr class="hover:bg-[#102332] transition">
                <td class="py-3 px-4 text-neutral-500">
                  <?= htmlspecialchars($item['item_code'] ?? 'N/A') ?>
                </td>
                <td class="py-3 px-4 font-semibold text-white flex items-center gap-2">
                  <?= gas_chip($item['item_name']) ?>
                  <span><?= htmlspecialchars($item['item_name']) ?></span>
                </td>
                <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                  <?= htmlspecialchars($item['qty']) ?> <span class="text-[11px] font-normal text-neutral-500"><?= htmlspecialchars($item['unit'] ?? 'Units') ?></span>
                </td>
                <td class="py-3 px-4 text-right tabular-nums text-slate-200">
                  <?= number_format($item['unit_price'], 2) ?>
                </td>
                <td class="py-3 px-4 text-right tabular-nums text-neutral-500">
                  <?= number_format($item['discount_pct'] ?? 0, 2) ?>%
                </td>
                <td class="py-3 px-4 text-right tabular-nums text-white font-bold">
                  <?= number_format($item['line_total'], 2) ?> SAR
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Totals & VAT summary container -->
      <div class="p-5 border-t border-[#1a3346] bg-[#09151f] flex justify-end">
        <div class="w-72 space-y-2 text-xs font-mono">
          <div class="flex justify-between text-neutral-500">
            <span>Subtotal (Excl. VAT)</span>
            <span class="tabular-nums font-semibold text-slate-200"><?= number_format($order['total_value'] / 1.15, 2) ?> SAR</span>
          </div>
          <div class="flex justify-between text-neutral-500">
            <span>VAT (15%)</span>
            <span class="tabular-nums font-semibold text-slate-200"><?= number_format($order['total_value'] - ($order['total_value'] / 1.15), 2) ?> SAR</span>
          </div>
          <div class="flex justify-between pt-2 border-t border-[#1e3a50] text-sm font-bold text-white">
            <span>Total Amount</span>
            <span class="tabular-nums text-teal-600 dark:text-teal-400"><?= number_format($order['total_value'], 2) ?> SAR</span>
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
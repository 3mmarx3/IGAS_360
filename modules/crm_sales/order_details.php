<?php
session_start();
require_once '../../config/db.php';

$active_page = 'purchase_orders';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Purchase Orders', 'Order Detail'];

// Accept by DB id or by order_number
$order = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($_GET['order_num'])) {
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE order_number = ?");
    $stmt->execute([$_GET['order_num']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    http_response_code(404);
    die("Order not found.");
}

// Client
$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt_client->execute([$order['client_id']]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

// Supplier
$supplier = null;
if (!empty($order['supplier_reference'])) {
    $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ?");
    $stmt_supp->execute([$order['supplier_reference']]);
    $supplier = $stmt_supp->fetch(PDO::FETCH_ASSOC);
}

// Order items
$stmt_items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Latest dispatch
$stmt_dispatch = $pdo->prepare("SELECT * FROM dispatches WHERE order_ref = ? ORDER BY id DESC LIMIT 1");
$stmt_dispatch->execute([$order['order_number']]);
$dispatch = $stmt_dispatch->fetch(PDO::FETCH_ASSOC);

// Driver info (if dispatch exists)
$driver = null;
if ($dispatch && !empty($dispatch['driver_id'])) {
    $stmt_drv = $pdo->prepare("SELECT * FROM drivers WHERE driver_id = ?");
    $stmt_drv->execute([$dispatch['driver_id']]);
    $driver = $stmt_drv->fetch(PDO::FETCH_ASSOC);
}

// Vehicle info (if dispatch exists)
$vehicle = null;
if ($dispatch && !empty($dispatch['vehicle_id'])) {
    $stmt_veh = $pdo->prepare("SELECT * FROM vehicles WHERE fleet_id = ?");
    $stmt_veh->execute([$dispatch['vehicle_id']]);
    $vehicle = $stmt_veh->fetch(PDO::FETCH_ASSOC);
}

// Client activity log for this order
$stmt_acts = $pdo->prepare("
    SELECT * FROM client_activities 
    WHERE client_id = ? AND activity_text LIKE ? 
    ORDER BY activity_time DESC LIMIT 5
");
$stmt_acts->execute([$order['client_id'], '%' . $order['order_number'] . '%']);
$activities = $stmt_acts->fetchAll(PDO::FETCH_ASSOC);

// Compute totals
$subtotal    = array_sum(array_column($items, 'line_total'));
$vat_amount  = $order['total_value'] - ($order['total_value'] / 1.15);
$subtotal_ex = $order['total_value'] / 1.15;

$statusStyles = [
    'draft'      => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Draft'],
    'processing' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Processing'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Delivered'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Cancelled'],
];

$dispatchStyles = [
    'dispatched' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'label' => 'Dispatched'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'label' => 'Delivered'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'label' => 'Cancelled'],
];

$priorityLabel = [
    'standard' => 'Standard',
    'urgent'   => 'Urgent',
    'critical' => 'Critical',
];

$os  = $statusStyles[$order['status']]    ?? $statusStyles['draft'];
$dsp = $dispatch ? ($dispatchStyles[$dispatch['status']] ?? $dispatchStyles['dispatched']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= htmlspecialchars($order['order_number']) ?> | I-GAS Enterprise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --ink: #1A1A1A;
            --ink-soft: #2E2E2E;
            --paper: #FFFFFF;
            --paper-dim: #F7F7F6;
            --paper-deep: #EFEEEC;
            --line: #D8D6D1;
            --line-soft: #E7E5E1;
            --accent: #9A7B2E;
            --accent-soft: #FBF3DF;
            --mute: #767470;
            --mute-soft: #A6A39D;
            --sidebar: #1A1A1A;
            --sidebar-line: #2E2E2E;
            --sidebar-text: #B8B6B1;
        }
        * { box-sizing: border-box; }
        html { font-size: 16px; }
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: var(--paper-dim);
            color: var(--ink);
            font-feature-settings: "tnum" 1;
        }
        .mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0; }
        .num  { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        a, button { -webkit-tap-highlight-color: transparent; }

        .card { background: var(--paper); border: 1px solid var(--line-soft); }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: 1px solid var(--ink); }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .btn-danger { background: #F8E9E7; color: #963B33; border: 1px solid #EAC8C4; transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-danger:hover { background: #f1d5d1; }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; font-weight: 500; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
        .info-sub   { font-size: 12px; color: var(--mute); margin-top: 2px; }

        .nav-row { position: relative; border-left: 2px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }

        th, td { vertical-align: middle; }

        .step-line::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 20px;
            bottom: -12px;
            width: 1px;
            background: var(--line-soft);
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .card { border: 1px solid #ddd; box-shadow: none; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

<main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

    <!-- System status bar -->
    <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0 no-print" style="background: var(--paper-deep); border-color: var(--line-soft);">
        <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
            <span class="status-dot" style="background: #5C8A5C;"></span>System Nominal
        </span>
        <span class="w-px h-3" style="background: var(--line);"></span>
        <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Order Operations</span>
        <span class="w-px h-3" style="background: var(--line);"></span>
        <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
        <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
    </div>

    <div class="flex-1 overflow-auto px-8 py-7">

        <!-- Back -->
        <a href="purchase_orders.php" class="no-print inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;"
           onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Purchase Orders
        </a>

        <!-- Page header -->
        <div class="flex justify-between items-start mb-7">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Sales Operations</p>
                <div class="flex items-center gap-3 mb-1.5">
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none num" style="color: var(--ink);"><?= htmlspecialchars($order['order_number']) ?></h2>
                    <span class="pill" style="background: <?= $os['bg'] ?>; color: <?= $os['fg'] ?>;">
                        <span class="status-dot" style="background: <?= $os['dot'] ?>;"></span><?= $os['label'] ?>
                    </span>
                </div>
                <p class="text-[13px] mono" style="color: var(--mute-soft);">
                    Placed <?= htmlspecialchars(date('d M Y', strtotime($order['order_date']))) ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars(strtoupper($order['payment_terms'] ?? 'COD')) ?>
                    &nbsp;·&nbsp;
                    Total <?= number_format($order['total_value'], 2) ?> SAR
                </p>
            </div>
            <div class="flex gap-2.5 no-print">
                <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2" onclick="window.print()">
                    <i data-lucide="printer" class="w-4 h-4"></i>Print
                </button>
                <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Edit Order
                </a>
                <form method="POST" action="purchase_orders.php" class="inline" onsubmit="return confirm('Delete this order permanently?');">
                    <input type="hidden" name="action"   value="delete">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" class="btn-danger px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>Delete
                    </button>
                </form>
            </div>
        </div>

        <!-- Fulfillment progress tracker -->
        <?php
        $stages = ['draft', 'processing', 'in_transit', 'delivered'];
        $stage_labels = ['Draft', 'Processing', 'In Transit', 'Delivered'];
        $current_stage = array_search($order['status'], $stages);
        if ($order['status'] === 'cancelled') $current_stage = -1;
        ?>
        <div class="card rounded-md px-6 py-5 mb-5 no-print">
            <div class="flex items-center justify-between">
                <?php foreach ($stages as $i => $stage): ?>
                    <?php
                    $is_done    = ($current_stage !== false && $current_stage !== -1 && $i < $current_stage);
                    $is_current = ($current_stage !== false && $i === $current_stage);
                    $icons = ['file-text', 'settings', 'truck', 'check-circle'];
                    ?>
                    <div class="flex items-center <?= $i < count($stages) - 1 ? 'flex-1' : '' ?>">
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mb-1.5 transition-colors"
                                 style="background: <?= $is_done ? 'var(--ink)' : ($is_current ? 'var(--accent-soft)' : 'var(--paper-deep)') ?>;
                                        border: 1.5px solid <?= $is_done ? 'var(--ink)' : ($is_current ? 'var(--accent)' : 'var(--line)') ?>;">
                                <i data-lucide="<?= $icons[$i] ?>" class="w-3.5 h-3.5"
                                   style="color: <?= $is_done ? 'white' : ($is_current ? 'var(--accent)' : 'var(--mute-soft)') ?>;"></i>
                            </div>
                            <span class="text-[11px] font-medium" style="color: <?= $is_done || $is_current ? 'var(--ink)' : 'var(--mute-soft)' ?>;">
                                <?= $stage_labels[$i] ?>
                            </span>
                        </div>
                        <?php if ($i < count($stages) - 1): ?>
                        <div class="flex-1 h-px mx-3 mt-[-10px]"
                             style="background: <?= $is_done ? 'var(--ink)' : 'var(--line-soft)' ?>;"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($order['status'] === 'cancelled'): ?>
                    <div class="ml-4 pill" style="background: #F8E9E7; color: #963B33;">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>Order Cancelled
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3-column info cards -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

            <!-- Client Details -->
            <div class="card rounded-md">
                <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color: var(--line-soft);">
                    <i data-lucide="user" class="w-4 h-4" style="color: var(--mute);"></i>
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Client Details</h3>
                </div>
                <div class="p-5 grid grid-cols-2 gap-y-5 gap-x-4">
                    <div class="col-span-2">
                        <p class="info-label">Company Name</p>
                        <p class="info-value"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></p>
                        <p class="info-sub mono"><?= htmlspecialchars($client['reference_id'] ?? '') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Contact Person</p>
                        <p class="info-value"><?= htmlspecialchars(trim(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? ''))) ?: 'N/A' ?></p>
                        <p class="info-sub"><?= htmlspecialchars($client['job_title'] ?? '') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Phone & Email</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></p>
                        <p class="info-sub mono"><?= htmlspecialchars($client['email'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Segment</p>
                        <p class="info-value" style="font-size:13px;"><?= htmlspecialchars($client['segment'] ?? 'General') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Entity Type</p>
                        <p class="info-value" style="font-size:13px;"><?= htmlspecialchars($client['entity_type'] ?? 'Corporate') ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="info-label">Billing Address</p>
                        <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                            <?= htmlspecialchars(implode(', ', array_filter([
                                $client['address'] ?? '',
                                $client['city']    ?? '',
                                $client['country'] ?? '',
                            ]))) ?>
                        </p>
                    </div>
                    <div>
                        <p class="info-label">Credit Limit</p>
                        <p class="info-value num"><?= number_format($client['credit_limit'] ?? 0, 2) ?> <span style="color:var(--mute); font-size:11px;">SAR</span></p>
                    </div>
                    <div>
                        <p class="info-label">Balance Due</p>
                        <p class="info-value num" style="color: <?= ($client['balance_due'] ?? 0) > 0 ? '#963B33' : 'var(--ink)' ?>;">
                            <?= number_format($client['balance_due'] ?? 0, 2) ?> <span style="color:var(--mute); font-size:11px;">SAR</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Supplier -->
            <div class="card rounded-md">
                <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color: var(--line-soft);">
                    <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute);"></i>
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Supplier</h3>
                </div>
                <div class="p-5 grid grid-cols-2 gap-y-5 gap-x-4">
                    <?php if ($supplier): ?>
                        <div class="col-span-2">
                            <p class="info-label">Company Name</p>
                            <p class="info-value"><?= htmlspecialchars($supplier['company_name']) ?></p>
                            <p class="info-sub mono"><?= htmlspecialchars($supplier['reference_id']) ?></p>
                        </div>
                        <div>
                            <p class="info-label">CR Number</p>
                            <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['cr_number'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <p class="info-label">Tax ID</p>
                            <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['tax_id'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <p class="info-label">Contact</p>
                            <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['phone'] ?? 'N/A') ?></p>
                            <p class="info-sub mono"><?= htmlspecialchars($supplier['email'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <p class="info-label">Payment Terms</p>
                            <p class="info-value" style="font-size:13px; text-transform:uppercase;"><?= htmlspecialchars($supplier['payment_terms'] ?? 'COD') ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="info-label">Origin Address</p>
                            <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                                <?= htmlspecialchars(implode(', ', array_filter([
                                    $supplier['address'] ?? '',
                                    $supplier['city']    ?? '',
                                    $supplier['country'] ?? '',
                                ]))) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="col-span-2">
                            <p class="info-label">Order Specifications</p>
                            <p class="info-value" style="font-weight:400; font-size:13px;"><?= htmlspecialchars($order['specs']) ?></p>
                        </div>
                        <div class="col-span-2 pt-2">
                            <p class="info-sub" style="font-size: 12.5px;">No direct supplier is linked to this order.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Logistics & Delivery -->
            <div class="card rounded-md">
                <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color: var(--line-soft);">
                    <i data-lucide="map-pin" class="w-4 h-4" style="color: var(--mute);"></i>
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Logistics & Delivery</h3>
                </div>
                <div class="p-5 grid grid-cols-2 gap-y-5 gap-x-4">
                    <div class="col-span-2">
                        <p class="info-label">Delivery Address</p>
                        <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                            <?= !empty($order['delivery_address']) ? nl2br(htmlspecialchars($order['delivery_address'])) : '<span style="color:var(--mute-soft);">No address specified</span>' ?>
                        </p>
                    </div>
                    <div>
                        <p class="info-label">Delivery Date</p>
                        <p class="info-value mono" style="font-size:12.5px;">
                            <?= !empty($order['delivery_date']) ? htmlspecialchars(date('d M Y', strtotime($order['delivery_date']))) : 'N/A' ?>
                        </p>
                        <p class="info-sub mono">
                            <?= !empty($order['delivery_time']) ? htmlspecialchars(date('h:i A', strtotime($order['delivery_time']))) : 'Time not set' ?>
                        </p>
                    </div>
                    <div>
                        <p class="info-label">Priority</p>
                        <p class="info-value" style="font-size:13px; text-transform:capitalize;">
                            <?= htmlspecialchars($priorityLabel[$order['delivery_priority']] ?? ucfirst($order['delivery_priority'] ?? 'Standard')) ?>
                        </p>
                    </div>
                    <div>
                        <p class="info-label">Payment Terms</p>
                        <p class="info-value" style="font-size:13px; font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($order['payment_terms'] ?? 'COD') ?></p>
                    </div>
                    <div>
                        <p class="info-label">Order Specs</p>
                        <p class="info-sub" style="font-size:12px; line-height:1.5;"><?= htmlspecialchars($order['specs']) ?></p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Active Dispatch Card -->
        <?php if ($dispatch): ?>
        <div class="card rounded-md mb-5">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--line-soft);">
                <div class="flex items-center gap-2">
                    <i data-lucide="navigation" class="w-4 h-4" style="color: var(--mute);"></i>
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Active Dispatch</h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="mono text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($dispatch['manifest_id']) ?></span>
                    <span class="pill" style="background: <?= $dsp['bg'] ?>; color: <?= $dsp['fg'] ?>;">
                        <?= $dsp['label'] ?>
                    </span>
                </div>
            </div>
            <div class="p-5 grid grid-cols-2 lg:grid-cols-4 gap-y-5 gap-x-4">
                <div>
                    <p class="info-label">Destination</p>
                    <p class="info-value"><?= htmlspecialchars($dispatch['destination']) ?></p>
                </div>
                <div>
                    <p class="info-label">Dispatch Date</p>
                    <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('d M Y', strtotime($dispatch['dispatch_date']))) ?></p>
                </div>
                <div>
                    <p class="info-label">ETA</p>
                    <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('H:i', strtotime($dispatch['eta_time']))) ?></p>
                </div>
                <div>
                    <p class="info-label">Distance</p>
                    <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['distance'] ?? 0) ?> KM</p>
                </div>

                <!-- Vehicle info -->
                <div>
                    <p class="info-label">Vehicle</p>
                    <?php if ($vehicle): ?>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($vehicle['fleet_id']) ?></p>
                        <p class="info-sub"><?= htmlspecialchars($vehicle['make_model']) ?> · <?= htmlspecialchars($vehicle['plate_number']) ?></p>
                    <?php else: ?>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['vehicle_id']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Driver info -->
                <div>
                    <p class="info-label">Driver</p>
                    <?php if ($driver): ?>
                        <p class="info-value"><?= htmlspecialchars($driver['full_name']) ?></p>
                        <p class="info-sub mono"><?= htmlspecialchars($driver['mobile_number']) ?></p>
                    <?php else: ?>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['driver_id']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="col-span-2">
                    <p class="info-label">Instructions</p>
                    <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                        <?= !empty($dispatch['instructions']) ? nl2br(htmlspecialchars($dispatch['instructions'])) : '<span style="color:var(--mute-soft);">No special instructions.</span>' ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Items Table -->
        <div class="card rounded-md flex flex-col overflow-hidden mb-5">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color: var(--line-soft);">
                <div class="flex items-center gap-2">
                    <i data-lucide="package" class="w-4 h-4" style="color: var(--mute);"></i>
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Order Items</h3>
                </div>
                <span class="text-[12px] mono" style="color: var(--mute);"><?= count($items) ?> line items</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                            <th class="pl-5 pr-3 py-3 font-medium">#</th>
                            <th class="px-3 py-3 font-medium">Item Code</th>
                            <th class="px-3 py-3 font-medium">Description</th>
                            <th class="px-3 py-3 font-medium text-right">Qty</th>
                            <th class="px-3 py-3 font-medium text-right">Unit Price</th>
                            <th class="px-3 py-3 font-medium text-right">Disc %</th>
                            <th class="pr-5 py-3 font-medium text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-[13px]" style="color: var(--mute);">
                                    No line items recorded. Total value is derived from order specifications.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $idx => $item): ?>
                            <tr onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-5 pr-3 py-3.5 text-[12px] mono" style="color: var(--mute-soft);"><?= $idx + 1 ?></td>
                                <td class="px-3 py-3.5 mono text-[12px] font-medium" style="color: var(--mute);"><?= htmlspecialchars($item['item_code'] ?? '—') ?></td>
                                <td class="px-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td class="px-3 py-3.5 text-right num" style="color: var(--ink);">
                                    <?= number_format($item['qty'], 2) ?>
                                    <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($item['unit']) ?></span>
                                </td>
                                <td class="px-3 py-3.5 text-right num" style="color: var(--ink);"><?= number_format($item['unit_price'], 2) ?></td>
                                <td class="px-3 py-3.5 text-right num" style="color: var(--mute);">
                                    <?= $item['discount_pct'] > 0 ? number_format($item['discount_pct'], 1) . '%' : '—' ?>
                                </td>
                                <td class="pr-5 py-3.5 text-right font-semibold num" style="color: var(--ink);"><?= number_format($item['line_total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="px-5 py-5 border-t flex justify-end" style="border-color: var(--line-soft); background: var(--paper-dim);">
                <div class="w-72">
                    <div class="flex justify-between items-center mb-2.5">
                        <span class="text-[12.5px]" style="color: var(--mute);">Subtotal (ex. VAT)</span>
                        <span class="text-[13.5px] num font-medium" style="color: var(--ink);"><?= number_format($subtotal_ex, 2) ?> SAR</span>
                    </div>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-[12.5px]" style="color: var(--mute);">VAT (15%)</span>
                        <span class="text-[13.5px] num font-medium" style="color: var(--ink);"><?= number_format($vat_amount, 2) ?> SAR</span>
                    </div>
                    <div class="flex justify-between items-center pt-3.5 border-t" style="border-color: var(--line);">
                        <span class="text-[14px] font-semibold" style="color: var(--ink);">Total Amount</span>
                        <span class="text-[20px] num font-bold" style="color: var(--ink);"><?= number_format($order['total_value'], 2) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <?php if (!empty($activities)): ?>
        <div class="card rounded-md mb-5 no-print">
            <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color: var(--line-soft);">
                <i data-lucide="activity" class="w-4 h-4" style="color: var(--mute);"></i>
                <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Activity Log</h3>
            </div>
            <div class="px-5 py-4">
                <div class="space-y-4">
                    <?php foreach ($activities as $act): ?>
                    <div class="flex items-start gap-3">
                        <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" style="background: var(--mute-soft);"></div>
                        <div>
                            <p class="text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($act['activity_text']) ?></p>
                            <p class="text-[11.5px] mono mt-0.5" style="color: var(--mute-soft);">
                                <?= htmlspecialchars(date('d M Y · H:i', strtotime($act['activity_time']))) ?>
                                &nbsp;·&nbsp; <?= htmlspecialchars($act['author']) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- end .flex-1.overflow-auto -->
</main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
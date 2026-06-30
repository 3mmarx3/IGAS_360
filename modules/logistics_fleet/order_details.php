<?php
require_once '../../config/db.php';

$active_page = 'orders';
$base_url    = '../../';

$order_num = $_GET['order_num'] ?? '';

if (empty($order_num)) {
    die("Invalid order number.");
}

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE order_number = ?");
$stmt->execute([$order_num]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found in system.");
}

$stmt_client = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt_client->execute([$order['client_id']]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

$supplier = null;
if (!empty($order['supplier_reference'])) {
    $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ?");
    $stmt_supp->execute([$order['supplier_reference']]);
    $supplier = $stmt_supp->fetch(PDO::FETCH_ASSOC);
}

$stmt_items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = ?");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$stmt_dispatch = $pdo->prepare("SELECT * FROM dispatches WHERE order_ref = ? ORDER BY id DESC LIMIT 1");
$stmt_dispatch->execute([$order['order_number']]);
$dispatch = $stmt_dispatch->fetch(PDO::FETCH_ASSOC);

$statusStyles = [
    'draft'      => ['bg' => 'transparent', 'fg' => '#A6A39D', 'dot' => 'transparent', 'label' => 'Draft'],
    'processing' => ['bg' => '#EAEAE8', 'fg' => '#3D3C3A', 'dot' => '#3D3C3A', 'label' => 'Processing'],
    'in_transit' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Delivered'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Cancelled']
];

$dispatchStyles = [
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'label' => 'Delivered'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'label' => 'In Transit'],
    'dispatched' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'label' => 'Dispatched'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'label' => 'Cancelled'],
];

$os = $statusStyles[$order['status']] ?? $statusStyles['draft'];
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
        .num { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D4D2CC; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--mute); }
        a, button { -webkit-tap-highlight-color: transparent; }

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: 1px solid var(--ink); }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .pill {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
            padding: 3px 9px; border-radius: 3px; line-height: 1;
        }

        .info-block { padding: 20px; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
        .info-sub { font-size: 12px; color: var(--mute); margin-top: 2px; }

        th, td { vertical-align: middle; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

        <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
            <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
                <span class="status-dot" style="background: #5C8A5C;"></span>System Nominal
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Order Operations</span>
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <a href="javascript:history.back()" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
            </a>

            <div class="flex justify-between items-start mb-7">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-[24px] font-semibold tracking-tight leading-none num" style="color: var(--ink);"><?= htmlspecialchars($order['order_number']) ?></h2>
                        <span class="pill" style="background: <?= $os['bg'] ?>; color: <?= $os['fg'] ?>;">
                            <span class="status-dot" style="background:<?= $os['dot'] ?>;"></span><?= $os['label'] ?>
                        </span>
                    </div>
                    <p class="text-[13px] mono" style="color: var(--mute-soft);">Date: <?= htmlspecialchars(date('d M Y', strtotime($order['order_date']))) ?> · Total: <?= number_format($order['total_value'], 2) ?> SAR</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2" onclick="window.print()">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Document
                    </button>
                    <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="pencil" class="w-4 h-4"></i>Edit Order
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
                <div class="card rounded-md">
                    <div class="px-5 py-4 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[14px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);"><i data-lucide="user" class="w-4 h-4 text-gray-500"></i> Client Details</h3>
                    </div>
                    <div class="info-block grid grid-cols-2 gap-y-5 gap-x-4">
                        <div class="col-span-2">
                            <p class="info-label">Company Name</p>
                            <p class="info-value"><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></p>
                            <p class="info-sub mono"><?= htmlspecialchars($client['reference_id'] ?? '') ?></p>
                        </div>
                        <div>
                            <p class="info-label">Contact Person</p>
                            <p class="info-value"><?= htmlspecialchars(($client['contact_first_name'] ?? '') . ' ' . ($client['contact_last_name'] ?? '')) ?></p>
                        </div>
                        <div>
                            <p class="info-label">Phone & Email</p>
                            <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></p>
                            <p class="info-sub mono"><?= htmlspecialchars($client['email'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="info-label">Billing Address</p>
                            <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                                <?= htmlspecialchars($client['address'] ?? '') ?>, 
                                <?= htmlspecialchars($client['city'] ?? '') ?>, 
                                <?= htmlspecialchars($client['country'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card rounded-md">
                    <div class="px-5 py-4 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[14px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);"><i data-lucide="truck" class="w-4 h-4 text-gray-500"></i> Merchant / Supplier</h3>
                    </div>
                    <div class="info-block grid grid-cols-2 gap-y-5 gap-x-4">
                        <?php if ($supplier): ?>
                            <div class="col-span-2">
                                <p class="info-label">Supplier Name</p>
                                <p class="info-value"><?= htmlspecialchars($supplier['company_name']) ?></p>
                                <p class="info-sub mono"><?= htmlspecialchars($supplier['reference_id']) ?></p>
                            </div>
                            <div>
                                <p class="info-label">CR Number / Tax ID</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['cr_number'] ?? 'N/A') ?></p>
                            </div>
                            <div>
                                <p class="info-label">Contact</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['phone'] ?? 'N/A') ?></p>
                                <p class="info-sub mono"><?= htmlspecialchars($supplier['email'] ?? 'N/A') ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="info-label">Origin Address</p>
                                <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                                    <?= htmlspecialchars($supplier['address'] ?? '') ?>, 
                                    <?= htmlspecialchars($supplier['city'] ?? '') ?>, 
                                    <?= htmlspecialchars($supplier['country'] ?? '') ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="col-span-2">
                                <p class="info-label">Order Specifications</p>
                                <p class="info-value" style="font-weight:400; font-size:13px;"><?= htmlspecialchars($order['specs']) ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="info-label">Supplier Link</p>
                                <p class="info-sub">No direct supplier linked to this specific order entity.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card rounded-md">
                    <div class="px-5 py-4 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[14px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);"><i data-lucide="map-pin" class="w-4 h-4 text-gray-500"></i> Logistics & Delivery</h3>
                    </div>
                    <div class="info-block grid grid-cols-2 gap-y-5 gap-x-4">
                        <div class="col-span-2">
                            <p class="info-label">Delivery Address / Plant Location</p>
                            <p class="info-value" style="font-weight:400; font-size:13px; line-height: 1.5;">
                                <?= !empty($order['delivery_address']) ? nl2br(htmlspecialchars($order['delivery_address'])) : 'No specific delivery address recorded.' ?>
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Expected Delivery Date</p>
                            <p class="info-value mono" style="font-size:12.5px;"><?= !empty($order['delivery_date']) ? htmlspecialchars(date('d M Y', strtotime($order['delivery_date']))) : 'N/A' ?></p>
                            <p class="info-sub mono"><?= !empty($order['delivery_time']) ? htmlspecialchars(date('h:i A', strtotime($order['delivery_time']))) : 'Time not set' ?></p>
                        </div>
                        <div>
                            <p class="info-label">Priority & Terms</p>
                            <p class="info-value" style="font-size:13px; text-transform:capitalize;"><?= htmlspecialchars($order['delivery_priority'] ?? 'Standard') ?></p>
                            <p class="info-sub" style="text-transform:uppercase; font-weight: 500; color: var(--ink);"><?= htmlspecialchars($order['payment_terms'] ?? 'COD') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($dispatch): ?>
            <?php $dsp_s = $dispatchStyles[$dispatch['status']] ?? $dispatchStyles['dispatched']; ?>
            <div class="card rounded-md mb-5">
                <div class="px-5 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                    <h3 class="text-[14px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);">
                        <i data-lucide="navigation" class="w-4 h-4 text-gray-500"></i> Active Dispatch Details
                    </h3>
                    <span class="pill" style="background: <?= $dsp_s['bg'] ?>; color: <?= $dsp_s['fg'] ?>;">
                        <?= $dsp_s['label'] ?>
                    </span>
                </div>
                <div class="info-block grid grid-cols-2 lg:grid-cols-4 gap-y-5 gap-x-4">
                    <div>
                        <p class="info-label">Manifest ID</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['manifest_id']) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Destination</p>
                        <p class="info-value"><?= htmlspecialchars($dispatch['destination']) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Date</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('d M Y', strtotime($dispatch['dispatch_date']))) ?></p>
                    </div>
                    <div>
                        <p class="info-label">ETA Time</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('H:i', strtotime($dispatch['eta_time']))) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Vehicle</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['vehicle_id']) ?></p>
                    </div>
                    <div>
                        <p class="info-label">Distance</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($dispatch['distance']) ?> KM</p>
                    </div>
                    <div class="col-span-2">
                        <p class="info-label">Instructions</p>
                        <p class="info-value" style="font-weight:400; font-size:13px; line-height:1.5;">
                            <?= !empty($dispatch['instructions']) ? nl2br(htmlspecialchars($dispatch['instructions'])) : 'No special instructions provided.' ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card rounded-md flex flex-col overflow-hidden mb-7">
                <div class="px-5 py-4 border-b" style="border-color: var(--line-soft);">
                    <h3 class="text-[14px] font-semibold tracking-tight" style="color: var(--ink);">Order Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                <th class="pl-5 pr-3 py-3 font-medium">Item Code</th>
                                <th class="px-3 py-3 font-medium">Description</th>
                                <th class="px-3 py-3 font-medium text-right">Quantity</th>
                                <th class="px-3 py-3 font-medium text-right">Unit Price</th>
                                <th class="pr-5 py-3 font-medium text-right">Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-[13px]" style="color: var(--mute);">No specific line items recorded for this order. Total value derived from specs.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-5 pr-3 py-3.5 mono font-medium text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($item['item_code'] ?? 'N/A') ?></td>
                                    <td class="px-3 py-3.5" style="color: var(--ink); font-weight:500;"><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="px-3 py-3.5 text-right num" style="color: var(--ink);"><?= htmlspecialchars($item['qty']) ?> <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($item['unit']) ?></span></td>
                                    <td class="px-3 py-3.5 text-right num" style="color: var(--ink);"><?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="pr-5 py-3.5 text-right font-semibold num" style="color: var(--ink);"><?= number_format($item['line_total'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-5 py-5 border-t bg-gray-50 flex justify-end" style="border-color: var(--line-soft); background: var(--paper-dim);">
                    <div class="w-64">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[12.5px]" style="color: var(--mute);">Subtotal</span>
                            <span class="text-[13.5px] num font-medium" style="color: var(--ink);"><?= number_format($order['total_value'] / 1.15, 2) ?> SAR</span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[12.5px]" style="color: var(--mute);">Taxes (VAT 15%)</span>
                            <span class="text-[13.5px] num font-medium" style="color: var(--ink);"><?= number_format($order['total_value'] - ($order['total_value'] / 1.15), 2) ?> SAR</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t" style="border-color: var(--line);">
                            <span class="text-[14px] font-semibold" style="color: var(--ink);">Total Amount</span>
                            <span class="text-[18px] num font-bold" style="color: var(--ink);"><?= number_format($order['total_value'], 2) ?> SAR</span>
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
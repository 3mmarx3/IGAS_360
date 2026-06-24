<?php
require_once '../../config/db.php';

$active_page = 'suppliers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Suppliers Directory', 'Supplier Profile'];

$supplier_ref = $_GET['id'] ?? 'SUP-5001';

$stmt = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ? AND partner_type = 'supplier'");
$stmt->execute([$supplier_ref]);
$db_supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_supplier) {
    die("Supplier not found in database.");
}

$words = preg_split('/\s+/', trim($db_supplier['company_name'] ?? ''));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') {
        $initials .= mb_substr($w, 0, 1, 'UTF-8');
    }
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'));

$supplier = [
    'id' => $db_supplier['reference_id'] ?? '',
    'name' => $db_supplier['company_name'] ?? '',
    'initials' => $initials,
    'category' => $db_supplier['segment'] ?? '',
    'status' => $db_supplier['status'] ?? 'pending',
    'rating' => $db_supplier['rating'] ?? '0.0',
    'contact_person' => trim(($db_supplier['contact_first_name'] ?? '') . ' ' . ($db_supplier['contact_last_name'] ?? '')),
    'position' => $db_supplier['job_title'] ?? '',
    'phone' => $db_supplier['phone'] ?? '',
    'email' => $db_supplier['email'] ?? '',
    'address' => trim(implode(', ', array_filter([
        $db_supplier['address'] ?? '',
        $db_supplier['city'] ?? '',
        $db_supplier['country'] ?? ''
    ]))),
    'tax_id' => $db_supplier['tax_id'] ?? '',
    'cr_number' => $db_supplier['cr_number'] ?? '',
    'payment_terms' => $db_supplier['payment_terms'] ?? ''
];

$stmt_metrics = $pdo->prepare("SELECT COUNT(*) as total_orders, SUM(total_value) as total_spend FROM purchase_orders WHERE supplier_reference = ?");
$stmt_metrics->execute([$db_supplier['reference_id']]);
$db_metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);

$metrics = [
    'total_orders' => (int)($db_metrics['total_orders'] ?? 0),
    'total_spend' => (float)($db_metrics['total_spend'] ?? 0),
    'on_time_rate' => 98.5,
    'defect_rate' => 0.2
];

$stmt_orders = $pdo->prepare("SELECT order_number as po, order_date as date, specs as item, total_value as amount, status FROM purchase_orders WHERE supplier_reference = ? ORDER BY order_date DESC LIMIT 5");
$stmt_orders->execute([$db_supplier['reference_id']]);
$recent_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

$stmt_products = $pdo->prepare("SELECT * FROM supplier_products WHERE supplier_reference = ? ORDER BY item_name ASC");
$stmt_products->execute([$db_supplier['reference_id']]);
$supplier_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
    'active'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active Partner'],
    'pending'    => ['bg' => 'var(--accent-soft)', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Pending Verification'],
    'restricted' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Restricted'],
];

$orderStatusStyles = [
    'draft'      => ['bg' => 'transparent', 'fg' => '#A6A39D'],
    'processing' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33'],
];

$ss = $statusStyles[$supplier['status']] ?? $statusStyles['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['name']) ?> | I-GAS Enterprise</title>
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
        .nav-row { position: relative; border-left: 2px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease; }
        .nav-row.active { border-left-color: var(--accent); background-color: rgba(255,255,255,0.04); color: #FFFFFF; }
        .nav-row:not(.active):hover { background-color: rgba(255,255,255,0.03); color: #FFFFFF; }
        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }
        th, td { vertical-align: middle; }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
        .info-row { padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.45); }
        .modal-overlay.open { display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .modal-box { background: var(--paper); border: 1px solid var(--line-soft); border-radius: 6px; width: 100%; max-width: 580px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
        .modal-input { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); outline: none; transition: border-color 0.15s ease; font-family: inherit; }
        .modal-input:focus { border-color: var(--ink); }
        .modal-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); outline: none; transition: border-color 0.15s ease; font-family: inherit; cursor: pointer; }
        .modal-select:focus { border-color: var(--ink); }
        .modal-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .icon-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid var(--line); border-radius:4px; background:var(--paper); color:var(--ink); transition:.15s; text-decoration:none; }
        .icon-btn:hover { background:var(--paper-dim); border-color:var(--mute-soft); }
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
    <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Plant — Jeddah Industrial</span>
    <span class="w-px h-3" style="background: var(--line);"></span>
    <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
</div>

<div class="flex-1 overflow-auto px-8 py-7">
    <a href="suppliers_directory.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5" style="color: var(--mute); text-decoration: none;">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Suppliers Directory
    </a>

    <div class="flex justify-between items-start mb-7">
        <div class="flex items-center gap-4">
            <span class="flex items-center justify-center font-semibold rounded-md flex-shrink-0" style="width:56px; height:56px; font-size:18px; background:#1A1A1A; color:#FFFFFF;"><?= htmlspecialchars($supplier['initials']) ?></span>
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($supplier['name']) ?></h2>
                    <span class="pill" style="background: <?= $ss['bg'] ?>; color: <?= $ss['fg'] ?>;">
                        <span class="status-dot" style="background:<?= $ss['dot'] ?>;"></span><?= $ss['label'] ?>
                    </span>
                </div>
                <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($supplier['id']) ?> · <?= htmlspecialchars($supplier['category']) ?></p>
            </div>
        </div>
        <div class="flex gap-3">
            <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="mail" class="w-4 h-4"></i>Contact
            </button>
            <a href="edit_supplier.php?id=<?= htmlspecialchars($supplier['id']) ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="pencil" class="w-4 h-4"></i>Edit Details
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Supplier Rating</p>
            <div class="flex items-center gap-2">
                <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars($supplier['rating']) ?></h3>
                <i data-lucide="star" class="w-5 h-5 fill-current text-yellow-500"></i>
            </div>
            <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                <div class="meter-fill h-full" style="width: <?= ((float)$supplier['rating'] / 5) * 100 ?>%;"></div>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Orders</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($metrics['total_orders']) ?></h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span style="color: var(--mute);">Lifetime POs generated</span>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Spend</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($metrics['total_spend'], 2) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span style="color: var(--mute);">Lifetime financial volume</span>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">On-Time Delivery</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $metrics['on_time_rate'] ?>%</h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span class="pill" style="background: #EAF1E7; color: #45663F;">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>Highly Reliable
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 flex flex-col gap-5">
            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);">
                        <i data-lucide="package" class="w-4 h-4" style="color: var(--mute);"></i> Product Catalog & Pricing
                    </h3>
                    <button onclick="openAddProductModal()" class="btn-secondary px-3 py-1.5 rounded-sm text-[12px] font-medium flex items-center gap-1.5">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Product
                    </button>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">Item Code</th>
                                <th class="px-3 py-3 font-medium">Product Name</th>
                                <th class="px-3 py-3 font-medium">Unit Price</th>
                                <th class="pr-6 py-3 font-medium text-right">Lead Time</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if(empty($supplier_products)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center">
                                    <p class="text-[13px] text-gray-500 mb-2">No products registered for this supplier.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($supplier_products as $p): ?>
                                <tr class="transition-colors" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 mono num text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($p['item_code'] ?? 'N/A') ?></td>
                                    <td class="px-3 py-3.5 font-medium" style="color: var(--ink);">
                                        <?= htmlspecialchars($p['item_name'] ?? '') ?>
                                        <span class="block text-[11px] mono font-normal" style="color: var(--mute); mt-0.5"><?= htmlspecialchars($p['category'] ?? '') ?></span>
                                    </td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: var(--ink);">
                                        <?= number_format($p['unit_price'] ?? 0, 2) ?> <?= htmlspecialchars($p['currency'] ?? 'SAR') ?>
                                        <span class="text-[11px] font-normal" style="color: var(--mute);"> / <?= htmlspecialchars($p['unit'] ?? '') ?></span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right text-[12.5px]" style="color: var(--mute);">
                                        <?= htmlspecialchars($p['lead_time_days'] ?? 0) ?> Days
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="shopping-cart" class="w-4 h-4" style="color: var(--mute);"></i> Recent Procurement History
                        </h3>
                    </div>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-3 py-3 font-medium">PO Number</th>
                                <th class="px-3 py-3 font-medium">Date</th>
                                <th class="px-3 py-3 font-medium">Primary Item</th>
                                <th class="px-3 py-3 font-medium text-right">Value (SAR)</th>
                                <th class="px-3 py-3 font-medium text-center">Actions</th>
                                <th class="pr-6 py-3 font-medium text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if(empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-[13px] text-gray-500">No recent orders found.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $o): ?>
                                <?php $os = $orderStatusStyles[$o['status']] ?? $orderStatusStyles['draft']; ?>
                                <tr class="transition-colors" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($o['po'] ?? '') ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($o['date']))) ?></td>
                                    <td class="px-3 py-3.5 text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($o['item'] ?? '') ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($o['amount'] ?? 0, 2) ?></td>
                                    <td class="px-3 py-3.5 text-center">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="view_order.php?order_num=<?= urlencode($o['po'] ?? '') ?>" class="icon-btn" title="View Details">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                            <a href="invoice.php?po=<?= urlencode($o['po'] ?? '') ?>" class="icon-btn" title="Open Invoice">
                                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium capitalize" style="background: <?= $os['bg'] ?>; color: <?= $os['fg'] ?>;">
                                            <?= htmlspecialchars(str_replace('_', ' ', $o['status'] ?? '')) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <div class="card rounded-md overflow-hidden">
                <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Contact Information</h3>
                </div>
                <div class="px-6">
                    <div class="info-row">
                        <p class="info-label">Primary Contact</p>
                        <p class="info-value"><?= htmlspecialchars($supplier['contact_person']) ?></p>
                        <p class="text-[12px] mt-0.5" style="color: var(--mute);"><?= htmlspecialchars($supplier['position']) ?></p>
                    </div>
                    <div class="info-row">
                        <p class="info-label">Phone Number</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['phone']) ?></p>
                    </div>
                    <div class="info-row">
                        <p class="info-label">Email Address</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['email']) ?></p>
                    </div>
                    <div class="info-row">
                        <p class="info-label">Registered Address</p>
                        <p class="info-value" style="font-size:13px; line-height:1.4;"><?= htmlspecialchars($supplier['address']) ?></p>
                    </div>
                </div>
            </div>

            <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                    <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Business & Legal</h3>
                </div>
                <div class="px-6">
                    <div class="info-row">
                        <p class="info-label">Tax ID (VAT)</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['tax_id']) ?></p>
                    </div>
                    <div class="info-row">
                        <p class="info-label">Commercial Registry (CR)</p>
                        <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($supplier['cr_number']) ?></p>
                    </div>
                    <div class="info-row">
                        <p class="info-label">Payment Terms</p>
                        <p class="info-value font-medium" style="font-size:13px;"><?= htmlspecialchars($supplier['payment_terms']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="addProductModal" class="modal-overlay">
    <div class="modal-box">
        <div class="px-6 py-5 border-b flex justify-between items-center flex-shrink-0" style="border-color: var(--line-soft);">
            <div>
                <h3 class="text-[16px] font-semibold tracking-tight" style="color: var(--ink);">Add New Product</h3>
                <p class="text-[12px] mt-0.5" style="color: var(--mute);">Register a new supply item for <?= htmlspecialchars($supplier['name']) ?></p>
            </div>
            <button onclick="closeAddProductModal()" class="btn-secondary w-8 h-8 rounded-sm flex items-center justify-center flex-shrink-0">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="overflow-y-auto flex-1 px-6 py-5">
            <div id="modal-error" class="hidden mb-4 p-3 rounded-sm" style="background-color: #F8E9E7; border: 1px solid #963B33;">
                <p class="text-[12.5px] font-medium flex items-center gap-1.5" style="color: #963B33;">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <span id="modal-error-text"></span>
                </p>
            </div>

            <form id="modalAddProductForm">
                <input type="hidden" name="supplier_reference" value="<?= htmlspecialchars($supplier['id']) ?>">
                <div class="mb-5 pb-5 border-b" style="border-color: var(--line-soft);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-4 flex items-center gap-2" style="color: var(--mute);">
                        <i data-lucide="package" class="w-3.5 h-3.5"></i> Product Basic Details
                    </p>
                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="modal-label">Product Name / Description</label>
                            <input type="text" name="item_name" class="modal-input" placeholder="e.g. Bulk Liquid Oxygen" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="modal-label">Item Code / SKU</label>
                                <input type="text" name="item_code" class="modal-input mono" placeholder="e.g. RM-O2-LIQ" required>
                            </div>
                            <div>
                                <label class="modal-label">Category</label>
                                <select name="category" class="modal-select" required>
                                    <option value="Raw Materials">Raw Materials</option>
                                    <option value="Cylinders">Cylinders</option>
                                    <option value="Chemicals">Chemicals</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Spare Parts">Spare Parts</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] mb-4 flex items-center gap-2" style="color: var(--mute);">
                        <i data-lucide="scale" class="w-3.5 h-3.5"></i> Measurement & Pricing
                    </p>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="modal-label">Quantity</label>
                            <input type="number" name="quantity" class="modal-input mono" placeholder="e.g. 1" step="0.01" value="1" required>
                        </div>
                        <div>
                            <label class="modal-label">Unit</label>
                            <input type="text" name="unit" class="modal-input" placeholder="Liter, Ton..." required>
                        </div>
                        <div>
                            <label class="modal-label">Lead Time (Days)</label>
                            <input type="number" name="lead_time_days" class="modal-input mono" placeholder="e.g. 3" min="0">
                        </div>
                        <div>
                            <label class="modal-label">Unit Price</label>
                            <input type="number" name="unit_price" class="modal-input mono" placeholder="0.00" step="0.01" required>
                        </div>
                        <div>
                            <label class="modal-label">Currency</label>
                            <select name="currency" class="modal-select mono">
                                <option value="SAR">SAR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 border-t flex justify-end gap-3 flex-shrink-0" style="border-color: var(--line-soft);">
            <button onclick="closeAddProductModal()" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel</button>
            <button onclick="submitAddProduct()" id="modal-submit-btn" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span id="modal-submit-text">Save Product</span>
            </button>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    function openAddProductModal() {
        document.getElementById('addProductModal').classList.add('open');
        document.getElementById('modalAddProductForm').reset();
        document.getElementById('modal-error').classList.add('hidden');
        lucide.createIcons();
    }

    function closeAddProductModal() {
        document.getElementById('addProductModal').classList.remove('open');
    }

    document.getElementById('addProductModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddProductModal();
    });

    async function submitAddProduct() {
        const form = document.getElementById('modalAddProductForm');
        const btn = document.getElementById('modal-submit-btn');
        const btnText = document.getElementById('modal-submit-text');
        const errorDiv = document.getElementById('modal-error');
        const errorText = document.getElementById('modal-error-text');
        const data = new FormData(form);

        const required = ['item_name', 'item_code', 'quantity', 'unit', 'unit_price'];
        for (let field of required) {
            if (!data.get(field) || data.get(field).toString().trim() === '') {
                errorText.textContent = 'Please fill in all required fields.';
                errorDiv.classList.remove('hidden');
                lucide.createIcons();
                return;
            }
        }

        btn.disabled = true;
        btnText.textContent = 'Saving...';
        errorDiv.classList.add('hidden');

        try {
            const response = await fetch('add_supplier_product.php', {
                method: 'POST',
                body: data
            });

            if (response.ok) {
                closeAddProductModal();
                window.location.reload();
            } else {
                throw new Error('Server error');
            }
        } catch (err) {
            errorText.textContent = 'Failed to save. Please try again.';
            errorDiv.classList.remove('hidden');
            lucide.createIcons();
        } finally {
            btn.disabled = false;
            btnText.textContent = 'Save Product';
            lucide.createIcons();
        }
    }
</script>
</body>
</html>
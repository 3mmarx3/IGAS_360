<?php
$active_page = 'suppliers_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Suppliers Directory', 'Supplier Profile'];

$supplier_id = $_GET['id'] ?? 'SUP-5001';

$supplier = [
    'id' => $supplier_id,
    'name' => 'Gulf Industrial Gases',
    'initials' => 'GI',
    'category' => 'Raw Materials',
    'status' => 'active',
    'rating' => 4.9,
    'contact_person' => 'Ahmad Al-Sayed',
    'position' => 'Key Account Manager',
    'phone' => '+966 50 123 4567',
    'email' => 'sales@gulfgas.sa',
    'address' => 'Building 44, Industrial Area Phase 1, Dammam, KSA',
    'tax_id' => '300112233445566',
    'cr_number' => '2050112233',
    'payment_terms' => 'Net 30 Days'
];

$metrics = [
    'total_orders' => 142,
    'total_spend' => 1245000,
    'on_time_rate' => 98.5,
    'defect_rate' => 0.2
];

$recent_orders = [
    ['po' => 'PO-8842', 'date' => '2026-06-20', 'item' => 'Bulk Liquid Oxygen (20T)', 'amount' => 45000, 'status' => 'delivered'],
    ['po' => 'PO-8815', 'date' => '2026-06-12', 'item' => 'Bulk Liquid Nitrogen (15T)', 'amount' => 32000, 'status' => 'delivered'],
    ['po' => 'PO-8790', 'date' => '2026-05-28', 'item' => 'Argon Gas Cylinders (x100)', 'amount' => 18500, 'status' => 'delivered'],
    ['po' => 'PO-8755', 'date' => '2026-05-15', 'item' => 'Bulk Liquid Oxygen (25T)', 'amount' => 56250, 'status' => 'delivered'],
    ['po' => 'PO-8712', 'date' => '2026-04-30', 'item' => 'Specialty Gas Mix (x50)', 'amount' => 12400, 'status' => 'delivered'],
];

$statusStyles = [
    'active'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active Partner'],
    'pending'    => ['bg' => 'var(--accent-soft)', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Pending Verification'],
    'restricted' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Restricted'],
];

$orderStatusStyles = [
    'processing' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E'],
    'transit'    => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F'],
];

$ss = $statusStyles[$supplier['status']];
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

            <a href="suppliers_directory.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
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
                    <a href="edit_supplier.php?id=<?= $supplier['id'] ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="pencil" class="w-4 h-4"></i>Edit Details
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Supplier Rating</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $supplier['rating'] ?></h3>
                        <i data-lucide="star" class="w-5 h-5 fill-current text-yellow-500"></i>
                    </div>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= ($supplier['rating'] / 5) * 100 ?>%;"></div>
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
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($metrics['total_spend']) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
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
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Procurement History</h3>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                View All POs<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </button>
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
                                    <th class="pr-6 py-3 font-medium text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($recent_orders as $o): ?>
                                <?php $os = $orderStatusStyles[$o['status']]; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($o['po']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($o['date']))) ?></td>
                                    <td class="px-3 py-3.5 text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($o['item']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($o['amount']) ?></td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-medium capitalize" style="background: <?= $os['bg'] ?>; color: <?= $os['fg'] ?>;">
                                            <?= htmlspecialchars($o['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
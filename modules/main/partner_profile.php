<?php
$type = $_GET['type'] ?? 'client';
$active_page = ($type === 'supplier') ? 'suppliers_directory' : 'clients_directory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Directory', ($type === 'supplier' ? 'Supplier Profile' : 'Client Profile')];

$partner = [
    'id' => ($type === 'supplier') ? 'SUP-5001' : 'CLT-3022',
    'name' => ($type === 'supplier') ? 'Gulf Industrial Gases' : 'SABIC Petrochemicals',
    'type' => $type,
    'cr_number' => '1010' . rand(100000, 999999),
    'tax_id' => '3000' . rand(1000000000, 9999999999),
    'email' => ($type === 'supplier') ? 'procurement@gulfgases.com' : 'supply@sabic.com',
    'phone' => '+966 13 ' . rand(1000000, 9999999),
    'address' => ($type === 'supplier') ? 'Industrial Area Phase 2, Dammam, KSA' : 'SABIC HQ, Airport Road, Riyadh, KSA',
    'balance' => ($type === 'supplier') ? 145000 : -284300, 
    'total_orders' => rand(40, 120),
    'active_contracts' => rand(2, 5),
    'compliance_status' => 'verified',
    'payment_terms' => 'Net 30 Days'
];

$orders = [
    ['id' => 'ORD-9942', 'date' => '2026-06-22', 'amount' => 45000, 'status' => 'fulfilled'],
    ['id' => 'ORD-9881', 'date' => '2026-06-18', 'amount' => 88000, 'status' => 'processing'],
    ['id' => 'ORD-9750', 'date' => '2026-06-05', 'amount' => 120000, 'status' => 'fulfilled'],
    ['id' => 'ORD-9611', 'date' => '2026-05-24', 'amount' => 35000, 'status' => 'cancelled'],
];

$statusStyles = [
    'fulfilled'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Delivered'],
    'processing' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In Pipeline'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Voided'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($partner['name']) ?> | I-GAS Enterprise</title>
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

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }

        .info-row { padding: 11px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }
    </style>
</head>
<body class="flex h-screen overflow-hidden antialiased">

<?php include '../../includes/aside.php'; ?>

    <main class="flex-1 flex flex-col min-w-0">

    <?php include '../../includes/header.php'; ?>

        <div class="h-9 border-b flex items-center px-8 gap-6 flex-shrink-0" style="background: var(--paper-deep); border-color: var(--line-soft);">
            <span class="flex items-center gap-2 text-[11px] font-medium mono uppercase tracking-wide" style="color: var(--ink);">
                <span class="status-dot" style="background: #5C8A5C;"></span>Network Node Online
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Verification · G2 Commercial Grade</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="<?= ($partner['type'] === 'supplier') ? 'truck' : 'building' ?>" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($partner['name']) ?></h2>
                            <span class="pill" style="background: #EAF1E7; color: #45663F;">
                                <span class="status-dot" style="background:#45663F;"></span>Verified Partner
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($partner['id']) ?> · <?= ucfirst($partner['type']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4"></i>Statement of Account
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="<?= ($partner['type'] === 'supplier') ? 'shopping-cart' : 'plus' ?>" class="w-4 h-4"></i>
                        <?= ($partner['type'] === 'supplier') ? 'Issue Purchase Order' : 'Create Invoice' ?>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-2" style="color: var(--mute);">Account Balance</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $partner['balance'] < 0 ? '#963B33' : 'var(--ink)' ?>;">
                        <?= number_format(abs($partner['balance'])) ?> <span class="text-[13px] font-normal" style="color: var(--mute);">SAR</span>
                    </h3>
                    <p class="text-[11px] mt-2 font-medium" style="color: <?= $partner['balance'] < 0 ? '#963B33' : '#45663F' ?>;">
                        <?= $partner['balance'] < 0 ? 'Receivable from Client' : 'Payable to Supplier' ?>
                    </p>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-2" style="color: var(--mute);">Total Orders / Fulfillments</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $partner['total_orders'] ?></h3>
                    <p class="text-[11px] mt-2" style="color: var(--mute);">Lifetime transaction ledger</p>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-2" style="color: var(--mute);">Active Commercial Contracts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $partner['active_contracts'] ?></h3>
                    <p class="text-[11px] mt-2" style="color: var(--mute);">Active B2B agreements binding</p>
                </div>
                
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-2" style="color: var(--mute);">Standard Credit Terms</p>
                    <h3 class="text-[20px] font-semibold tracking-tight" style="color: var(--ink);"><?= htmlspecialchars($partner['payment_terms']) ?></h3>
                    <p class="text-[11px] mt-2" style="color: var(--mute);">Default clearing interval cycle</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Order History Matrix</h3>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft); background: var(--paper-dim);">
                                    <th class="pl-6 py-3 font-medium">Order ID</th>
                                    <th class="px-3 py-3 font-medium">Execution Date</th>
                                    <th class="px-3 py-3 font-medium text-right">Gross Amount</th>
                                    <th class="pr-6 py-3 font-medium text-right">Fulfillment Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($orders as $o): ?>
                                <?php $style = $statusStyles[$o['status']]; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 py-3.5 num font-medium text-[var(--ink)]"><?= htmlspecialchars($o['id']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($o['date']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-semibold num" style="color: var(--ink);">SAR <?= number_format($o['amount']) ?></td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <span class="pill" style="background: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $style['dot'] ?>;"></span><?= $style['label'] ?>
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
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Corporate Dossier</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Commercial Registry (CR)</p>
                                <p class="info-value mono num"><?= htmlspecialchars($partner['cr_number']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">GAZT VAT Number</p>
                                <p class="info-value mono num"><?= htmlspecialchars($partner['tax_id']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Communications Gateway</p>
                                <p class="info-value text-[13px] font-medium" style="color: var(--ink);"><?= htmlspecialchars($partner['email']) ?></p>
                                <p class="text-[11.5px] mono mt-0.5" style="color: var(--mute);"><?= htmlspecialchars($partner['phone']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Registered Corporate Address</p>
                                <p class="info-value text-[12.5px]" style="color: var(--ink); line-height: 1.4; font-weight: 400;"><?= htmlspecialchars($partner['address']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md overflow-hidden" style="background: var(--paper-deep);">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">B2B Integration Parameters</h3>
                        </div>
                        <div class="px-6 py-4 flex flex-col gap-3">
                            <button class="w-full btn-secondary py-2 rounded-sm text-[12.5px] font-medium bg-white">
                                View Active Contracts Mapping
                            </button>
                            <button class="w-full btn-secondary py-2 rounded-sm text-[12.5px] font-medium bg-white text-red-700 hover:border-red-300">
                                Restrict Transaction Pipeline
                            </button>
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
<?php
$active_page = 'collections_invoices';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Collections & Invoices'];

$invoices = [
    ['id' => 'INV-9021', 'client' => 'SABIC Petrochemicals',   'initials' => 'SP', 'corp' => true,  'po_ref' => 'PO-8842', 'issue_date' => '2026-06-20', 'due_date' => '2026-07-20', 'amount' => 45000, 'status' => 'pending'],
    ['id' => 'INV-9020', 'client' => 'Air Product Co.',        'initials' => 'AP', 'corp' => true,  'po_ref' => 'PO-8841', 'issue_date' => '2026-06-15', 'due_date' => '2026-06-30', 'amount' => 18400, 'status' => 'paid'],
    ['id' => 'INV-9019', 'client' => 'Red Sea Marine Services','initials' => 'RM', 'corp' => true,  'po_ref' => 'PO-8840', 'issue_date' => '2026-06-10', 'due_date' => '2026-06-25', 'amount' => 27600, 'status' => 'partial'],
    ['id' => 'INV-9018', 'client' => 'Tabuk Steel Works',      'initials' => 'TS', 'corp' => true,  'po_ref' => 'PO-8839', 'issue_date' => '2026-05-20', 'due_date' => '2026-06-05', 'amount' => 21300, 'status' => 'overdue'],
    ['id' => 'INV-9017', 'client' => 'National Contracting',   'initials' => 'NC', 'corp' => true,  'po_ref' => 'PO-8838', 'issue_date' => '2026-05-18', 'due_date' => '2026-06-02', 'amount' => 11000, 'status' => 'overdue'],
    ['id' => 'INV-9016', 'client' => 'Abdullah Al-Hashim',     'initials' => 'AH', 'corp' => false, 'po_ref' => 'PO-8837', 'issue_date' => '2026-06-12', 'due_date' => '2026-06-27', 'amount' => 7250,  'status' => 'paid'],
    ['id' => 'INV-9015', 'client' => 'Yanbu Fabrication LLC',  'initials' => 'YF', 'corp' => true,  'po_ref' => 'PO-8836', 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15', 'amount' => 9800,  'status' => 'paid'],
];

$total_receivables = 342500;
$overdue_amount    = 32300;
$collected_mtd     = 185400;
$collection_rate   = 78.5;

$statusStyles = [
    'paid'    => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Paid in Full', 'dotBorder' => ''],
    'partial' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'Partially Paid', 'dotBorder' => ''],
    'pending' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Awaiting Payment', 'dotBorder' => ''],
    'overdue' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Overdue', 'dotBorder' => ''],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections & Invoices | I-GAS Enterprise</title>
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
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }

        input:focus, select:focus { outline: none; border-color: var(--ink) !important; }
        th, td { vertical-align: middle; }

        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px;
            height: 2px; background: transparent; transition: background 0.15s ease;
        }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }

        .avatar-sq {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px;
        }

        .checkbox-sq {
            width: 15px; height: 15px; border: 1.5px solid var(--mute-soft); border-radius: 2px;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
            cursor: pointer; transition: border-color 0.15s ease;
        }
        .checkbox-sq:hover { border-color: var(--ink); }

        .pill {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
            padding: 3px 9px; border-radius: 3px; line-height: 1;
        }
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
            <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Accounting & Finance</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Collections & Invoices</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage billing, track client payments, and monitor overdue accounts.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Ledger
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>Issue Invoice
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Receivables</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_receivables) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="ml-0" style="color: var(--mute);">across all accounts</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Overdue Amount</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #963B33;"><?= number_format($overdue_amount) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F8E9E7; color: #963B33;">
                            <i data-lucide="alert-circle" class="w-3 h-3"></i>Requires Action
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Collected (MTD)</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($collected_mtd) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">cleared this month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Collection Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $collection_rate ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $collection_rate ?>%; background: #45663F;"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Invoice Ledger</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search INV or PO ref" class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All statuses</option>
                                <option>Pending</option>
                                <option>Overdue</option>
                                <option>Paid</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All <span class="num text-[11px]" style="color: var(--mute-soft);">1,402</span></span>
                        <span class="tab-item">Pending <span class="num text-[11px]" style="color: var(--mute-soft);">45</span></span>
                        <span class="tab-item text-red-700">Overdue <span class="num text-[11px]" style="color: #963B33;">12</span></span>
                        <span class="tab-item">Paid <span class="num text-[11px]" style="color: var(--mute-soft);">1,345</span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Invoice #</th>
                                <th class="px-3 py-3 font-medium">Client Account</th>
                                <th class="px-3 py-3 font-medium">PO Ref</th>
                                <th class="px-3 py-3 font-medium">Issue Date</th>
                                <th class="px-3 py-3 font-medium">Due Date</th>
                                <th class="px-3 py-3 font-medium text-right">Amount</th>
                                <th class="px-3 py-3 font-medium">Payment Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php foreach ($invoices as $inv): ?>
                            <?php
                                $s = $statusStyles[$inv['status']];
                                $avatarBg = $inv['corp'] ? '#1A1A1A' : '#EFEEEC';
                                $avatarFg = $inv['corp'] ? '#FFFFFF' : '#5C5A56';
                                $avatarBorder = $inv['corp'] ? '' : 'border:1px solid #DEDCD7;';
                                $isOverdue = $inv['status'] === 'overdue';
                                $rowColor = $isOverdue ? '#963B33' : 'var(--ink)';
                            ?>
                            <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($inv['id']) ?></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="avatar-sq" style="background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($inv['initials']) ?></span>
                                        <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($inv['client']) ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($inv['po_ref']) ?></td>
                                <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($inv['issue_date']))) ?></td>
                                <td class="px-3 py-3.5 text-[12.5px] mono font-medium" style="color: <?= $isOverdue ? '#963B33' : 'var(--mute)' ?>;"><?= htmlspecialchars(date('d M Y', strtotime($inv['due_date']))) ?></td>
                                <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($inv['amount']) ?></td>
                                <td class="px-3 py-3.5">
                                    <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                        <span class="status-dot" style="background:<?= $s['dot'] ?>;<?= $s['dotBorder'] ?>"></span><?= $s['label'] ?>
                                    </span>
                                </td>
                                <td class="pr-6 py-3.5 text-right flex items-center justify-end gap-3">
                                    <?php if($inv['status'] !== 'paid'): ?>
                                    <button class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink);">Record Pay</button>
                                    <?php endif; ?>
                                    <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($invoices) ?> of 1,402</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
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
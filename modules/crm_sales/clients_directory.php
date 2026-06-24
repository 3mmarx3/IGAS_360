<?php
session_start();
require_once '../../config/db.php';

$active_page = 'clients';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory'];

$stmt = $pdo->prepare("SELECT * FROM partners WHERE partner_type = 'client' ORDER BY created_at DESC");
$stmt->execute();
$records = $stmt->fetchAll();

$clients = [];
$total_clients = count($records);
$active_count = 0;
$total_receivable = 0;
$new_this_month = 0;
$total_lifetime = 0;

$current_month = date('m');
$current_year = date('Y');

foreach ($records as $row) {
    $ui_status = $row['status'];
    if ($ui_status === 'approved') $ui_status = 'active';
    if ($ui_status === 'suspended') $ui_status = 'inactive';
    if ($row['balance_due'] > 0 && $ui_status === 'active') {
        if (strtotime($row['last_order_date']) < strtotime('-30 days')) {
            $ui_status = 'overdue';
        }
    }

    if ($ui_status === 'active') {
        $active_count++;
    }
    
    $total_receivable += $row['balance_due'];
    $total_lifetime += $row['lifetime_value'];

    $created_month = date('m', strtotime($row['created_at']));
    $created_year = date('Y', strtotime($row['created_at']));
    if ($created_month == $current_month && $created_year == $current_year) {
        $new_this_month++;
    }

    $words = explode(' ', trim($row['company_name']));
    $initials = '';
    if (count($words) >= 2) {
        $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    } elseif (count($words) == 1 && mb_strlen($words[0]) > 0) {
        $initials = strtoupper(mb_substr($words[0], 0, 2));
    }

    $clients[] = [
        'id' => $row['reference_id'],
        'name' => $row['company_name'],
        'initials' => $initials,
        'type' => $row['entity_type'] ?? 'Corporate',
        'segment' => $row['segment'] ?? 'General',
        'last_order' => $row['last_order_date'] ? date('d M Y', strtotime($row['last_order_date'])) : 'Never',
        'lifetime' => $row['lifetime_value'],
        'balance' => $row['balance_due'],
        'status' => $ui_status
    ];
}

$avg_account_value = $total_clients > 0 ? round($total_lifetime / $total_clients) : 0;
$active_percentage = $total_clients > 0 ? round(($active_count / $total_clients) * 100) : 0;

$statusStyles = [
    'active'   => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active'],
    'pending'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Pending'],
    'overdue'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Overdue'],
    'inactive' => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Inactive'],
    'rejected' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Rejected'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-GAS Enterprise | Clients Directory</title>
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

        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }

        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }

        .avatar-sq { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 600; flex-shrink: 0; border-radius: 3px; }

        .checkbox-sq { width: 15px; height: 15px; border: 1.5px solid var(--mute-soft); border-radius: 2px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; cursor: pointer; transition: border-color 0.15s ease; }
        .checkbox-sq:hover { border-color: var(--ink); }

        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 3px; line-height: 1; }
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
            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">CRM &amp; Accounts</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Clients Directory</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">All registered accounts, balances, and account standing.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="upload" class="w-4 h-4"></i>Import
                    </button>
                    <a href="new_client.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>New Client
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Accounts</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_clients) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i><?= $new_this_month ?> new
                        </span>
                        <span class="ml-2" style="color: var(--mute);">this month</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Accounts</p>
                    <div class="flex items-baseline gap-1.5">
                        <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($active_count) ?></h3>
                        <span class="text-[12px] font-medium" style="color: var(--mute);">/ <?= number_format($total_clients) ?></span>
                    </div>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $active_percentage ?>%"></div>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Receivable</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_receivable) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Avg. Account Value</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($avg_account_value) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">All Accounts</h3>
                        <div class="flex items-center gap-3">
                            <button class="transition-colors" style="color: var(--mute);"><i data-lucide="filter" class="w-4 h-4"></i></button>
                            <button class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink);">
                                Export List<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $total_clients ?></span></span>
                    </div>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Account</th>
                                <th class="px-3 py-3 font-medium">Type</th>
                                <th class="px-3 py-3 font-medium">Segment</th>
                                <th class="px-3 py-3 font-medium">Last Order</th>
                                <th class="px-3 py-3 font-medium text-right">Lifetime Value</th>
                                <th class="px-3 py-3 font-medium text-right">Balance Due</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-[13px]" style="color: var(--mute);">No client records found.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $c): ?>
                                <?php
                                    $s = $statusStyles[$c['status']] ?? $statusStyles['pending'];
                                    $isCorp = $c['type'] === 'Corporate';
                                    $avatarBg = $isCorp ? '#1A1A1A' : '#EFEEEC';
                                    $avatarFg = $isCorp ? '#FFFFFF' : '#5C5A56';
                                    $avatarBorder = $isCorp ? '' : 'border:1px solid #DEDCD7;';
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="avatar-sq" style="background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($c['initials']) ?></span>
                                            <div class="min-w-0">
                                                <a href="./client_profile.php?type=client" class="font-medium hover:underline block truncate" style="color: var(--ink); text-decoration: none;"><?= htmlspecialchars($c['name']) ?></a>
                                                <span class="text-[11px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($c['id']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($c['type']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($c['segment']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($c['last_order']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($c['lifetime']) ?></td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $c['balance'] > 0 ? 'var(--ink)' : 'var(--mute-soft)' ?>;"><?= $c['balance'] > 0 ? number_format($c['balance']) : '—' ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($clients) > 0 ? '1' : '0' ?>–<?= count($clients) ?> of <?= number_format($total_clients) ?></span>
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
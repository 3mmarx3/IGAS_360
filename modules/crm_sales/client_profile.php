<?php
require_once '../../config/db.php';

$active_page = 'clients';
$base_url    = '../../'; 

$reference_id = $_GET['id'] ?? 'ACC-2984';

$stmt = $pdo->prepare("SELECT * FROM partners WHERE reference_id = ? AND partner_type = 'client'");
$stmt->execute([$reference_id]);
$db_client = $stmt->fetch();

if (!$db_client) {
    die("Client not found in system.");
}

$words = explode(" ", $db_client['company_name']);
$initials = "";
foreach ($words as $w) {
    $initials .= mb_substr($w, 0, 1, 'UTF-8');
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2, 'UTF-8'));

$stmt_orders = $pdo->prepare("SELECT order_number AS `order`, specs, order_date AS `date`, status, total_value AS `value` FROM purchase_orders WHERE client_id = ? ORDER BY order_date DESC LIMIT 10");
$stmt_orders->execute([$db_client['id']]);
$orders = $stmt_orders->fetchAll();

$stmt_stats = $pdo->prepare("SELECT COUNT(*) as o_count, AVG(total_value) as o_avg FROM purchase_orders WHERE client_id = ?");
$stmt_stats->execute([$db_client['id']]);
$stats = $stmt_stats->fetch();

$stmt_act = $pdo->prepare("SELECT activity_text, activity_time, author FROM client_activities WHERE client_id = ? ORDER BY activity_time DESC LIMIT 10");
$stmt_act->execute([$db_client['id']]);
$activities = $stmt_act->fetchAll();

$client = [
    'id'           => $db_client['reference_id'],
    'name'         => $db_client['company_name'],
    'initials'     => $initials,
    'type'         => $db_client['entity_type'],
    'segment'      => $db_client['segment'],
    'status'       => $db_client['status'],
    'since'        => $db_client['created_at'],
    'contact_name' => $db_client['contact_first_name'] . ' ' . $db_client['contact_last_name'],
    'contact_role' => $db_client['job_title'],
    'email'        => $db_client['email'],
    'phone'        => $db_client['phone'],
    'address'      => $db_client['address'] . ', ' . $db_client['city'] . ', ' . $db_client['country'],
    'tax_id'       => $db_client['tax_id'],
    'lifetime'     => $db_client['lifetime_value'],
    'balance'      => $db_client['balance_due'],
    'orders_count' => $stats['o_count'] ?? 0,
    'avg_order'    => $stats['o_avg'] ?? 0,
];

$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory', $client['name']];

$statusStyles = [
    'active'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active'],
    'pending'    => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Pending'],
    'overdue'    => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Overdue'],
    'inactive'   => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Inactive'],
    'processing' => ['bg' => '#EAEAE8', 'fg' => '#3D3C3A', 'dot' => '#3D3C3A', 'label' => 'Processing'],
    'in_transit' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Delivered'],
    'draft'      => ['bg' => 'transparent', 'fg' => '#A6A39D', 'dot' => 'transparent', 'label' => 'Draft'],
];

$cs = $statusStyles[$client['status']] ?? $statusStyles['inactive'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($client['name']) ?> | I-GAS Enterprise</title>
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

        .ticket {
            background: var(--paper); border: 1px solid var(--line-soft);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .ticket:hover { border-color: var(--mute-soft); box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .ticket:focus-visible { outline: 2px solid var(--ink); outline-offset: -2px; }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }

        input:focus { outline: none; border-color: var(--ink) !important; }
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

        .timeline-rail { position: relative; }
        .timeline-rail::before {
            content: ''; position: absolute; left: 19.5px; top: 4px; bottom: 4px;
            width: 1px; background: var(--line-soft);
        }
        .timeline-dot {
            width: 7px; height: 7px; border-radius: 50%; border: 2px solid var(--mute-soft); background: var(--paper);
            flex-shrink: 0; position: relative; z-index: 1;
        }
        .timeline-dot.current { border-color: var(--ink); background: var(--ink); }

        .checkbox-sq {
            width: 15px; height: 15px; border: 1.5px solid var(--mute-soft); border-radius: 2px;
            display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
            cursor: pointer; transition: border-color 0.15s ease;
        }
        .checkbox-sq:hover { border-color: var(--ink); }

        .crumb { color: var(--mute); }
        .crumb-sep { color: var(--mute-soft); }
        .crumb-current { color: var(--ink); font-weight: 500; }

        .pill {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
            padding: 3px 9px; border-radius: 3px; line-height: 1;
        }

        .info-row { padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
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

            <a href="clients_directory.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Clients Directory
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <span class="flex items-center justify-center font-semibold rounded-md flex-shrink-0" style="width:56px; height:56px; font-size:18px; background:#1A1A1A; color:#fff;"><?= htmlspecialchars($client['initials']) ?></span>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);"><?= htmlspecialchars($client['name']) ?></h2>
                            <span class="pill" style="background: <?= $cs['bg'] ?>; color: <?= $cs['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $cs['dot'] ?>;"></span><?= $cs['label'] ?>
                            </span>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($client['id']) ?> · <?= htmlspecialchars($client['type']) ?> · <?= htmlspecialchars($client['segment']) ?></p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="pencil" class="w-4 h-4"></i>Edit Profile
                    </a>
                    <a href="new_order.php?client_id=<?= $client['id'] ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>New Order
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Lifetime Value</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($client['lifetime']) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Balance Due</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: <?= $client['balance'] > 0 ? 'var(--ink)' : 'var(--mute-soft)' ?>;"><?= $client['balance'] > 0 ? number_format($client['balance']) : '0' ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                    <?php if ($client['balance'] > 0): ?>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: var(--accent-soft); color: #7A5E1E;">
                            <i data-lucide="clock" class="w-3 h-3"></i>Due in 14 days
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Orders</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($client['orders_count']) ?></h3>
                </div>
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Avg. Order Value</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($client['avg_order']) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <div class="xl:col-span-2 card rounded-md flex flex-col overflow-hidden">
                    <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Order History</h3>
                            <a href="client_orders.php?client_id=<?= $client['id'] ?>" class="text-[12.5px] font-medium flex items-center gap-1" style="color: var(--ink); text-decoration: none;">
                                View All<i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                    <th class="pl-6 pr-3 py-3 font-medium">Order</th>
                                    <th class="px-3 py-3 font-medium">Specs</th>
                                    <th class="px-3 py-3 font-medium">Date</th>
                                    <th class="px-3 py-3 font-medium">Status</th>
                                    <th class="px-3 py-3 font-medium text-right">Value</th>
                                    <th class="pr-6 py-3 font-medium text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                <?php foreach ($orders as $o): ?>
                                <?php $os = $statusStyles[$o['status']] ?? $statusStyles['draft']; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-3 py-3.5 num font-medium" style="color: var(--ink);"><?= htmlspecialchars($o['order']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($o['specs']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($o['date']))) ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $os['bg'] ?>; color: <?= $os['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $os['dot'] ?>;"></span><?= $os['label'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium num" style="color: var(--ink);"><?= number_format($o['value']) ?></td>
                                    <td class="pr-6 py-3.5 text-center">
                                        <a href="view_order.php?order_num=<?= urlencode($o['order']) ?>" class="inline-flex items-center justify-center transition-colors" style="color: var(--mute);" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'" title="View Details">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                        <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($orders) ?> of <?= $client['orders_count'] ?></span>
                        <div class="flex items-center gap-1.5">
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                            <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                            <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    <div class="card rounded-md overflow-hidden">
                        <div class="px-6 py-5 border-b" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Account Information</h3>
                        </div>
                        <div class="px-6">
                            <div class="info-row">
                                <p class="info-label">Primary Contact</p>
                                <p class="info-value"><?= htmlspecialchars($client['contact_name']) ?></p>
                                <p class="text-[12px] mt-0.5" style="color: var(--mute);"><?= htmlspecialchars($client['contact_role']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Email</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($client['email']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Phone</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($client['phone']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Address</p>
                                <p class="info-value" style="font-weight:400; font-size:13px;"><?= htmlspecialchars($client['address']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Tax / VAT ID</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars($client['tax_id']) ?></p>
                            </div>
                            <div class="info-row">
                                <p class="info-label">Client Since</p>
                                <p class="info-value mono" style="font-size:12.5px;"><?= htmlspecialchars(date('d M Y', strtotime($client['since']))) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md flex flex-col overflow-hidden flex-1">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Activity</h3>
                            <button class="transition-colors" style="color: var(--mute);"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                        </div>
                        <div class="p-5 timeline-rail flex-1 overflow-y-auto">
                            <?php foreach ($activities as $index => $act): ?>
                            <div class="flex gap-3 <?= $index === count($activities) - 1 ? '' : 'pb-5 relative' ?>">
                                <div class="timeline-dot <?= $index === 0 ? 'current' : '' ?> mt-1"></div>
                                <div>
                                    <p class="text-[13px]" style="color: var(--ink);"><?= htmlspecialchars($act['activity_text']) ?></p>
                                    <p class="text-[11px] mono mt-1" style="color: var(--mute-soft);"><?= date('d M · H:i', strtotime($act['activity_time'])) ?> — <?= htmlspecialchars($act['author']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="border-t p-3" style="border-color: var(--line-soft);">
                            <a href="client_activity.php?client_id=<?= $client['id'] ?>" class="block w-full text-center text-[12px] font-medium py-1.5 transition-colors" style="color: var(--mute); text-decoration: none;">View all activity</a>
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
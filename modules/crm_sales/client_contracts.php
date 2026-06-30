<?php
session_start();
require_once '../../config/db.php';

$active_page = 'client_contracts';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Client Contracts'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;

    if ($delete_id > 0) {
        $del = $pdo->prepare("DELETE FROM client_contracts WHERE id = :id");
        $del->execute(['id' => $delete_id]);
    }

    header('Location: client_contracts.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        c.id AS row_id,
        c.contract_number AS id,
        p.company_name AS client,
        p.entity_type,
        c.monthly_quota AS quota,
        c.gas_type,
        c.start_date,
        c.end_date,
        c.contract_value AS value,
        c.status
    FROM client_contracts c
    JOIN partners p ON c.client_id = p.id
    ORDER BY c.start_date DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contracts = [];
$total_active_contracts = 0;
$expiring_soon_count = 0;
$monthly_recurring = 0;
$total_contract_value = 0;

$today = new DateTime();
$expiringThreshold = (clone $today)->modify('+60 days');

foreach ($rows as $row) {
    $words = preg_split('/\s+/', trim($row['client']));
    $initials = '';

    if (count($words) >= 2) {
        $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
        $initials = strtoupper(mb_substr($words[0], 0, 2));
    }

    $start = !empty($row['start_date']) ? new DateTime($row['start_date']) : null;
    $end   = !empty($row['end_date']) ? new DateTime($row['end_date']) : null;

    $status = $row['status'];

    if ($status === 'active') {
        $total_active_contracts++;
        $monthly_recurring += (float)$row['value'];
    }

    if ($end && $end >= $today && $end <= $expiringThreshold) {
        $expiring_soon_count++;
        if ($status !== 'terminated' && $status !== 'expired') {
            $status = 'expiring';
        }
    }

    if ($status === 'active') {
        $total_contract_value += (float)$row['value'];
    }

    $contracts[] = [
        'row_id' => $row['row_id'],
        'id' => $row['id'],
        'client' => $row['client'],
        'initials' => $initials ?: 'CL',
        'corp' => ($row['entity_type'] === 'Corporate'),
        'quota' => $row['quota'],
        'gas_type' => $row['gas_type'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'value' => (float)$row['value'],
        'status' => $status
    ];
}

if ($total_contract_value <= 0) {
    $total_contract_value = array_sum(array_map(fn($c) => $c['value'], $contracts));
}

$statusStyles = [
    'active'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active', 'dotBorder' => ''],
    'expiring'   => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Expiring Soon', 'dotBorder' => ''],
    'expired'    => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Expired', 'dotBorder' => ''],
    'terminated' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Terminated', 'dotBorder' => ''],
];

if ($monthly_recurring <= 0) {
    $monthly_recurring = count(array_filter($contracts, fn($c) => $c['status'] === 'active'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Contracts | I-GAS Enterprise</title>
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
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; }
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
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Corporate Sales</p>
            <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Client Contracts</h2>
            <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manage long-term supply agreements, monthly quotas, and renewals.</p>
        </div>
        <div class="flex gap-3">
            <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i>Export Data
            </button>
            <a href="draft_contract.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>Draft Contract
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Contracts</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_active_contracts) ?></h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span class="ml-0" style="color: var(--mute);">currently active</span>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Expiring Soon</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #7A5E1E;"><?= number_format($expiring_soon_count) ?></h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span class="pill" style="background: #FBF3DF; color: #7A5E1E;">
                    <i data-lucide="clock" class="w-3 h-3"></i>Within 60 days
                </span>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Monthly Recurring Vol.</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($monthly_recurring) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
            <div class="mt-3 flex items-center text-[12px]">
                <span style="color: var(--mute);">guaranteed output</span>
            </div>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Contract Value</p>
            <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_contract_value / 1000000, 1) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">M SAR</span></h3>
            <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                <div class="meter-fill h-full" style="width: 85%; background: var(--ink);"></div>
            </div>
        </div>
    </div>

    <div class="card rounded-md flex flex-col overflow-hidden">
        <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Contract Registry</h3>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                        <input type="text" placeholder="Search contract or client" class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                    </div>
                    <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                        <option>All statuses</option>
                        <option>Active</option>
                        <option>Expiring</option>
                        <option>Expired</option>
                        <option>Terminated</option>
                    </select>
                    <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                </div>
            </div>
            <div class="flex items-center gap-6 text-[13px] font-medium">
                <span class="tab-item active">All <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($contracts) ?></span></span>
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                        <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                        <th class="px-3 py-3 font-medium">Contract Ref</th>
                        <th class="px-3 py-3 font-medium">Client Account</th>
                        <th class="px-3 py-3 font-medium">Monthly Quota</th>
                        <th class="px-3 py-3 font-medium">Start Date</th>
                        <th class="px-3 py-3 font-medium">End Date</th>
                        <th class="px-3 py-3 font-medium text-right">Value (SAR)</th>
                        <th class="px-3 py-3 font-medium">Status</th>
                        <th class="pr-6 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                    <?php foreach ($contracts as $c): ?>
                    <?php
                        $s = $statusStyles[$c['status']];
                        $avatarBg = $c['corp'] ? '#1A1A1A' : '#EFEEEC';
                        $avatarFg = $c['corp'] ? '#FFFFFF' : '#5C5A56';
                        $avatarBorder = $c['corp'] ? '' : 'border:1px solid #DEDCD7;';
                        $isInactive = in_array($c['status'], ['expired', 'terminated']);
                        $rowColor = $isInactive ? 'var(--mute-soft)' : 'var(--ink)';
                    ?>
                    <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                        <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                        <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($c['id']) ?></td>
                        <td class="px-3 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="avatar-sq" style="background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($c['initials']) ?></span>
                                <span class="font-medium" style="color: <?= $isInactive ? 'var(--mute)' : 'var(--ink)' ?>;"><?= htmlspecialchars($c['client']) ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-3.5">
                            <div class="flex flex-col">
                                <span class="text-[12.5px] font-medium" style="color: <?= $isInactive ? 'var(--mute-soft)' : 'var(--ink)' ?>;"><?= htmlspecialchars($c['quota']) ?></span>
                                <span class="text-[11px] mono" style="color: var(--mute);"><?= htmlspecialchars($c['gas_type']) ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars(date('d M Y', strtotime($c['start_date']))) ?></td>
                        <td class="px-3 py-3.5 text-[12.5px] mono font-medium" style="color: <?= $c['status'] === 'expiring' ? '#7A5E1E' : ($isInactive ? 'var(--mute-soft)' : 'var(--mute)') ?>;"><?= htmlspecialchars(date('d M Y', strtotime($c['end_date']))) ?></td>
                        <td class="px-3 py-3.5 text-right font-medium num" style="color: <?= $rowColor ?>;"><?= number_format($c['value']) ?></td>
                        <td class="px-3 py-3.5">
                            <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                <span class="status-dot" style="background:<?= $s['dot'] ?>;<?= $s['dotBorder'] ?>"></span><?= $s['label'] ?>
                            </span>
                        </td>
                        <td class="pr-6 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-4">
                                <?php if ($c['status'] === 'expiring'): ?>
                                <a href="renew_contract.php?id=<?= urlencode($c['id']) ?>" class="text-[12px] font-medium" style="color: var(--ink); border-bottom: 1px solid var(--ink); text-decoration: none;">Renew</a>
                                <?php endif; ?>
                                <a href="contract_details.php?id=<?= urlencode($c['row_id']) ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="View Details" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                                <form method="POST" action="" class="m-0 p-0 inline-block" onsubmit="return confirm('Are you sure you want to delete this contract?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contract_id" value="<?= htmlspecialchars($c['row_id']) ?>">
                                    <button type="submit" class="transition-colors bg-transparent border-none cursor-pointer flex items-center" style="color: #963B33; padding: 0;" title="Delete Contract" onmouseover="this.style.color='#7a2d26'" onmouseout="this.style.color='#963B33'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($contracts)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-6 text-sm text-gray-500">لا توجد عقود مسجلة حالياً.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
            <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($contracts) > 0 ? '1' : '0' ?>–<?= count($contracts) ?> of <?= count($contracts) ?></span>
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
<?php
session_start();
require_once '../../config/db.php';

$active_page = 'client_contracts';
$breadcrumb  = ['I-GAS', 'CRM & Sales', 'Client Contracts', 'Contract Details'];

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contract_id <= 0) {
    header('Location: client_contracts.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        c.*,
        p.company_name,
        p.entity_type,
        p.contact_first_name,
        p.contact_last_name,
        p.job_title,
        p.email,
        p.phone,
        p.country,
        p.city,
        p.address AS partner_address,
        p.cr_number,
        p.tax_id,
        p.segment,
        p.rating
    FROM client_contracts c
    JOIN partners p ON c.client_id = p.id
    WHERE c.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $contract_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: client_contracts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del = $pdo->prepare("DELETE FROM client_contracts WHERE id = :id");
    $del->execute(['id' => $contract_id]);
    header('Location: client_contracts.php');
    exit;
}

$today = new DateTime();
$start = new DateTime($contract['start_date']);
$end   = new DateTime($contract['end_date']);
$expiringThreshold = (clone $today)->modify('+60 days');

$status = $contract['status'];
if ($end >= $today && $end <= $expiringThreshold && $status !== 'terminated' && $status !== 'expired') {
    $status = 'expiring';
}

$statusStyles = [
    'active'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Active'],
    'expiring'   => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Expiring Soon'],
    'expired'    => ['bg' => '#F2F1EF', 'fg' => '#767470', 'dot' => '#A6A39D', 'label' => 'Expired'],
    'terminated' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Terminated'],
];
$s = $statusStyles[$status];

$totalDays = max(1, $start->diff($end)->days);
$elapsedDays = $today >= $start ? $today->diff($start)->days : 0;
if ($today > $end) { $elapsedDays = $totalDays; }
$progressPct = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));

$daysRemaining = $today <= $end ? $today->diff($end)->days : 0;

$words = preg_split('/\s+/', trim($contract['company_name']));
$initials = '';
if (count($words) >= 2) {
    $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
} elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
    $initials = strtoupper(mb_substr($words[0], 0, 2));
}
$initials = $initials ?: 'CL';

$isCorp = ($contract['entity_type'] === 'Corporate');
$avatarBg = $isCorp ? '#1A1A1A' : '#EFEEEC';
$avatarFg = $isCorp ? '#FFFFFF' : '#5C5A56';
$avatarBorder = $isCorp ? '' : 'border:1px solid #DEDCD7;';

$paymentTermsLabel = [
    'cod'   => 'Cash on Delivery',
    'net15' => 'Net 15',
    'net30' => 'Net 30',
    'net45' => 'Net 45',
    'net60' => 'Net 60',
];
$payTerm = $contract['payment_terms'] ?? '';
$payTermDisplay = $paymentTermsLabel[$payTerm] ?? ($payTerm ?: '—');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract <?= htmlspecialchars($contract['contract_number']) ?> | I-GAS Enterprise</title>
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
        .btn-danger { background: var(--paper); color: #963B33; border: 1px solid #E3C9C6; transition: background-color 0.15s ease, border-color 0.15s ease; }
        .btn-danger:hover { background: #FBF1F0; border-color: #963B33; }
        .meter-bar { background: var(--paper-deep); border: 1px solid var(--line-soft); border-radius: 2px; }
        .meter-fill { background: var(--ink); }
        .avatar-sq {
            width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 600; flex-shrink: 0; border-radius: 6px;
        }
        .pill {
            display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500;
            padding: 3px 9px; border-radius: 3px; line-height: 1;
        }
        .info-row { display: flex; justify-content: space-between; align-items: baseline; padding: 11px 0; border-bottom: 1px solid var(--line-soft); }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 12px; color: var(--mute); font-weight: 500; }
        .info-value { font-size: 13.5px; color: var(--ink); font-weight: 500; text-align: right; }
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

    <a href="client_contracts.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5" style="color: var(--mute); text-decoration: none;" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Contract Registry
    </a>

    <div class="flex justify-between items-start mb-7">
        <div class="flex items-center gap-4">
            <span class="avatar-sq" style="background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($initials) ?></span>
            <div>
                <div class="flex items-center gap-3 mb-1.5">
                    <h2 class="text-[24px] font-semibold tracking-tight leading-none num" style="color: var(--ink);"><?= htmlspecialchars($contract['contract_number']) ?></h2>
                    <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                        <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                    </span>
                </div>
                <p class="text-[13.5px]" style="color: var(--mute);"><?= htmlspecialchars($contract['company_name']) ?> · <?= htmlspecialchars($contract['entity_type']) ?></p>
            </div>
        </div>
        <div class="flex gap-3">
            <?php if ($status === 'expiring'): ?>
            <a href="renew_contract.php?id=<?= urlencode($contract['contract_number']) ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>Renew Contract
            </a>
            <?php else: ?>
            <a href="edit_contract.php?id=<?= urlencode($contract['id']) ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                <i data-lucide="pencil" class="w-4 h-4"></i>Edit Contract
            </a>
            <?php endif; ?>
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this contract? This action cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-danger px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Contract Value</p>
            <h3 class="text-[22px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($contract['contract_value']) ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">SAR</span></h3>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Monthly Quota</p>
            <h3 class="text-[22px] font-semibold tracking-tight num" style="color: var(--ink);"><?= htmlspecialchars($contract['monthly_quota']) ?></h3>
            <p class="text-[12px] mt-1" style="color: var(--mute);"><?= htmlspecialchars($contract['gas_type']) ?></p>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Days Remaining</p>
            <h3 class="text-[22px] font-semibold tracking-tight num" style="color: <?= $status === 'expiring' ? '#7A5E1E' : 'var(--ink)' ?>;"><?= number_format($daysRemaining) ?></h3>
            <p class="text-[12px] mt-1" style="color: var(--mute);">of <?= number_format($totalDays) ?> day term</p>
        </div>
        <div class="card rounded-md p-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Term Progress</p>
            <h3 class="text-[22px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $progressPct ?><span class="text-[13px] font-normal ml-1" style="color: var(--mute);">%</span></h3>
            <div class="mt-3 meter-bar h-1.5 w-full overflow-hidden">
                <div class="meter-fill h-full" style="width: <?= $progressPct ?>%; background: var(--ink);"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 flex flex-col gap-5">

            <div class="card rounded-md p-6">
                <h3 class="text-[15px] font-semibold tracking-tight mb-1" style="color: var(--ink);">Contract Terms</h3>
                <p class="text-[12.5px] mb-4" style="color: var(--mute);">Supply agreement details and billing configuration.</p>
                <div>
                    <div class="info-row">
                        <span class="info-label">Contract Reference</span>
                        <span class="info-value num"><?= htmlspecialchars($contract['contract_number']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gas Type</span>
                        <span class="info-value"><?= htmlspecialchars($contract['gas_type']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Monthly Quota</span>
                        <span class="info-value"><?= htmlspecialchars($contract['monthly_quota']) ?> <?= htmlspecialchars($contract['unit'] ?? '') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Start Date</span>
                        <span class="info-value mono"><?= htmlspecialchars(date('d M Y', strtotime($contract['start_date']))) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">End Date</span>
                        <span class="info-value mono"><?= htmlspecialchars(date('d M Y', strtotime($contract['end_date']))) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Terms</span>
                        <span class="info-value"><?= htmlspecialchars($payTermDisplay) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Billing Address</span>
                        <span class="info-value" style="max-width: 60%;"><?= htmlspecialchars($contract['billing_address'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contract Value</span>
                        <span class="info-value num"><?= number_format($contract['contract_value']) ?> SAR</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($contract['notes'])): ?>
            <div class="card rounded-md p-6">
                <h3 class="text-[15px] font-semibold tracking-tight mb-3" style="color: var(--ink);">Notes</h3>
                <p class="text-[13.5px] leading-relaxed" style="color: var(--ink-soft);"><?= nl2br(htmlspecialchars($contract['notes'])) ?></p>
            </div>
            <?php endif; ?>

        </div>

        <div class="flex flex-col gap-5">

            <div class="card rounded-md p-6">
                <h3 class="text-[15px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Client Account</h3>
                <div class="flex items-center gap-3 mb-4">
                    <span class="avatar-sq" style="width:38px;height:38px;font-size:13px; background:<?= $avatarBg ?>; color:<?= $avatarFg ?>; <?= $avatarBorder ?>"><?= htmlspecialchars($initials) ?></span>
                    <div>
                        <p class="text-[13.5px] font-semibold" style="color: var(--ink);"><?= htmlspecialchars($contract['company_name']) ?></p>
                        <p class="text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($contract['entity_type']) ?> · <?= htmlspecialchars($contract['segment'] ?? 'General') ?></p>
                    </div>
                </div>
                <div>
                    <div class="info-row">
                        <span class="info-label">Contact</span>
                        <span class="info-value"><?= htmlspecialchars($contract['contact_first_name'] . ' ' . $contract['contact_last_name']) ?></span>
                    </div>
                    <?php if (!empty($contract['job_title'])): ?>
                    <div class="info-row">
                        <span class="info-label">Job Title</span>
                        <span class="info-value"><?= htmlspecialchars($contract['job_title']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value" style="font-size:12.5px;"><?= htmlspecialchars($contract['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value mono"><?= htmlspecialchars($contract['phone']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location</span>
                        <span class="info-value"><?= htmlspecialchars($contract['city'] . ', ' . $contract['country']) ?></span>
                    </div>
                    <?php if (!empty($contract['cr_number'])): ?>
                    <div class="info-row">
                        <span class="info-label">CR Number</span>
                        <span class="info-value mono"><?= htmlspecialchars($contract['cr_number']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($contract['tax_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">Tax ID</span>
                        <span class="info-value mono"><?= htmlspecialchars($contract['tax_id']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card rounded-md p-6">
                <h3 class="text-[15px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Record Info</h3>
                <div>
                    <div class="info-row">
                        <span class="info-label">Created</span>
                        <span class="info-value mono" style="font-size:12px;"><?= !empty($contract['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($contract['created_at']))) : '—' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value mono" style="font-size:12px;"><?= !empty($contract['updated_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime($contract['updated_at']))) : '—' ?></span>
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
<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['partner_id'])) {
    $action = $_POST['action'];
    $partner_id = (int)$_POST['partner_id'];
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE partners SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $partner_id]);
    
    header("Location: partner_approvals.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM partners ORDER BY created_at DESC");
$stmt->execute();
$all_records = $stmt->fetchAll();

$requests = [];
$pending_clients = 0;
$pending_suppliers = 0;
$total_pending = 0;

foreach ($all_records as $row) {
    if ($row['status'] === 'pending') {
        $total_pending++;
        if ($row['partner_type'] === 'client') {
            $pending_clients++;
        } elseif ($row['partner_type'] === 'supplier') {
            $pending_suppliers++;
        }
    }

    $requests[] = [
        'db_id'   => $row['id'],
        'id'      => $row['reference_id'],
        'name'    => $row['company_name'],
        'type'    => $row['partner_type'],
        'cr'      => $row['cr_number'],
        'status'  => $row['status'],
        'date'    => date('Y-m-d', strtotime($row['created_at'])),
        'contact' => $row['contact_first_name'] . ' ' . $row['contact_last_name'],
        'email'   => $row['email']
    ];
}

$avg_review_time = '4.2h';

$active_page = 'partner_approvals';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Global Administration', 'Partner Approvals'];

$typeStyles = [
    'client'    => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'label' => 'Corporate Client'],
    'supplier'  => ['bg' => '#F2F1EF', 'fg' => '#5C5A56', 'label' => 'Industrial Supplier'],
    'logistics' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'label' => 'Logistics Partner']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Approvals | I-GAS Enterprise</title>
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

        .btn-approve { background: #45663F; color: white; border: 1px solid #45663F; transition: background-color 0.15s ease; cursor: pointer; }
        .btn-approve:hover { background: #355030; }
        .btn-reject { background: transparent; color: #963B33; border: 1px solid var(--line); transition: all 0.15s ease; cursor: pointer; }
        .btn-reject:hover { background: #F8E9E7; border-color: #963B33; }

        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }

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
                <span class="status-dot" style="background: #9A7B2E;"></span>Gatekeeper Protocol Active
            </span>
            <span class="w-px h-3" style="background: var(--line);"></span>
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Verification & Administration Matrix</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Global Administration</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Partner Approvals</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Review commercial credentials, tax registrations, and manage network access nodes.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>Compliance Standards
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Pending Applications</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $total_pending ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">Awaiting administrative sign-off</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Pending Clients</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: #2A6B8A;"><?= $pending_clients ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #E8F1F5; color: #2A6B8A;">Corporate Buyers</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Pending Suppliers</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $pending_suppliers ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #F2F1EF; color: #5C5A56;">Industrial Vendors</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Avg. Review Velocity</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $avg_review_time ?></h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden" style="background: var(--line-soft); border-radius: 2px;">
                        <div class="meter-fill h-full" style="width: 82%; background: #45663F; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Partner Directory & Queue</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search entity or CR..." class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56 outline-none" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5 bg-white outline-none" style="border-color: var(--line); color: var(--ink);">
                                <option>All Categories</option>
                                <option>Clients Only</option>
                                <option>Suppliers Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">All Entities <span class="num text-[11px]" style="color: var(--mute-soft);"><?= count($requests) ?></span></span>
                        <span class="tab-item">Pending Approvals <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $total_pending ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Entity Reference</th>
                                <th class="px-3 py-3 font-medium">Commercial ID (CR)</th>
                                <th class="px-3 py-3 font-medium">Primary Contact Point</th>
                                <th class="px-3 py-3 font-medium">Submission Date</th>
                                <th class="px-3 py-3 font-medium">Classification</th>
                                <th class="pr-6 py-3 font-medium text-right">Status / Protocols</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-[13px]" style="color: var(--mute);">No applications or entities found in the system.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                <?php $style = $typeStyles[$r['type']] ?? $typeStyles['client']; ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col gap-0.5">
                                            <a href="partner_profile.php?type=<?= urlencode($r['type']) ?>" class="font-semibold transition-colors hover:underline" style="color: var(--ink);"><?= htmlspecialchars($r['name']) ?></a>
                                            <span class="text-[11px] mono" style="color: var(--mute-soft);"><?= htmlspecialchars($r['id']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 mono num" style="color: var(--ink);"><?= htmlspecialchars($r['cr']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-medium" style="color: var(--ink);"><?= htmlspecialchars($r['contact']) ?></span>
                                            <span class="text-[12px]" style="color: var(--mute);"><?= htmlspecialchars($r['email']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($r['date']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $style['bg'] ?>; color: <?= $style['fg'] ?>;">
                                            <?= $style['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <form method="POST" action="" class="flex items-center justify-end gap-2 m-0 p-0">
                                                <input type="hidden" name="partner_id" value="<?= $r['db_id'] ?>">
                                                <button type="submit" name="action" value="reject" class="btn-reject px-3 py-1.5 rounded-sm text-[12px] font-medium">Reject</button>
                                                <button type="submit" name="action" value="approve" class="btn-approve px-3 py-1.5 rounded-sm text-[12px] font-medium">Approve</button>
                                            </form>
                                        <?php elseif ($r['status'] === 'approved'): ?>
                                            <span class="pill" style="background: #EAF1E7; color: #45663F; border: 1px solid #D4E3D0;">
                                                <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>Approved
                                            </span>
                                        <?php elseif ($r['status'] === 'rejected'): ?>
                                            <span class="pill" style="background: #F8E9E7; color: #963B33; border: 1px solid #F0D0CD;">
                                                <i data-lucide="x-circle" class="w-3 h-3 mr-1"></i>Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="pill" style="background: var(--paper-deep); color: var(--mute); border: 1px solid var(--line-soft);">
                                                <?= ucfirst(htmlspecialchars($r['status'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft); background: var(--paper-dim);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing <?= count($requests) > 0 ? '1' : '0' ?>–<?= count($requests) ?> of <?= count($requests) ?> Entities</span>
                    <div class="flex items-center gap-1.5">
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors bg-white hover:bg-gray-50" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-left" class="w-3.5 h-3.5"></i></button>
                        <button class="w-7 h-7 flex items-center justify-center rounded-sm text-[12px] font-medium mono" style="background: var(--ink); color: white;">1</button>
                        <button class="w-7 h-7 flex items-center justify-center border rounded-sm transition-colors bg-white hover:bg-gray-50" style="border-color: var(--line); color: var(--mute);"><i data-lucide="chevron-right" class="w-3.5 h-3.5"></i></button>
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
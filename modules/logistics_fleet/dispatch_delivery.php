<?php
require_once '../../config/db.php';

$active_page = 'dispatch_delivery';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $manifest_id_to_delete = $_POST['manifest_id'] ?? null;

    if ($manifest_id_to_delete !== null) {
        $stmt_delete = $pdo->prepare("DELETE FROM dispatches WHERE manifest_id = ?");
        $stmt_delete->execute([$manifest_id_to_delete]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$stmt_total = $pdo->query("SELECT COUNT(*) FROM dispatches");
$total_dispatches = $stmt_total->fetchColumn();

$stmt_active = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status IN ('dispatched', 'in_transit')");
$active_routes = $stmt_active->fetchColumn();

$stmt_del_today = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'delivered' AND dispatch_date = CURDATE()");
$delivered_today = $stmt_del_today->fetchColumn();

$stmt_loading = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'dispatched'");
$loading_count = $stmt_loading->fetchColumn();

$stmt_transit = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'in_transit'");
$transit_count = $stmt_transit->fetchColumn();

$stmt_delayed = $pdo->query("SELECT COUNT(*) FROM dispatches WHERE status = 'cancelled'"); 
$delayed_count = $stmt_delayed->fetchColumn();

$stmt_rate = $pdo->query("SELECT status, COUNT(*) as cnt FROM dispatches WHERE status IN ('delivered', 'cancelled') GROUP BY status");
$rate_data = $stmt_rate->fetchAll(PDO::FETCH_KEY_PAIR);
$delivered_count = $rate_data['delivered'] ?? 0;
$cancelled_count = $rate_data['cancelled'] ?? 0;
$total_completed = $delivered_count + $cancelled_count;
$on_time_rate = $total_completed > 0 ? round(($delivered_count / $total_completed) * 100, 1) : 100;

$query = "
    SELECT 
        d.manifest_id as id,
        d.order_ref,
        d.destination,
        d.vehicle_id as vehicle,
        d.eta_time as eta,
        d.status,
        dr.full_name as driver,
        p.company_name as client
    FROM dispatches d
    LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
    LEFT JOIN purchase_orders po ON d.order_ref = po.order_number
    LEFT JOIN partners p ON po.client_id = p.id
    ORDER BY d.created_at DESC
";
$stmt_dispatches = $pdo->query($query);
$dispatches = $stmt_dispatches->fetchAll(PDO::FETCH_ASSOC);

$statusStyles = [
    'dispatched' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Loading'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Delivered'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Delayed'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch &amp; Delivery | I-GAS Enterprise</title>
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

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary {
            background: var(--paper); color: var(--ink); border: 1px solid var(--line);
            transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer;
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics &amp; Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Dispatch &amp; Delivery</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Real-time tracking of route dispatches, customer drop-offs, and logistics fulfillment.</p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i>Export Manifests
                    </button>
                    <a href="new_dispatch.php" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>New Dispatch
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Active Routes</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $active_routes ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="ml-0" style="color: var(--mute);">units currently deployed</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Delivered Today</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $delivered_today ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span class="pill" style="background: #EAF1E7; color: #45663F;">
                            <i data-lucide="check-circle" class="w-3 h-3"></i>Successful drop-offs
                        </span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">Total Dispatches</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= number_format($total_dispatches) ?></h3>
                    <div class="mt-3 flex items-center text-[12px]">
                        <span style="color: var(--mute);">manifests generated</span>
                    </div>
                </div>

                <div class="card rounded-md p-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-3" style="color: var(--mute);">On-Time Rate</p>
                    <h3 class="text-[24px] font-semibold tracking-tight num" style="color: var(--ink);"><?= $on_time_rate ?>%</h3>
                    <div class="mt-4 meter-bar h-1.5 w-full overflow-hidden">
                        <div class="meter-fill h-full" style="width: <?= $on_time_rate ?>%; background: #45663F;"></div>
                    </div>
                </div>
            </div>

            <div class="card rounded-md flex flex-col overflow-hidden">
                <div class="px-6 pt-5 border-b" style="border-color: var(--line-soft);">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Active Dispatch Registry</h3>
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--mute-soft);"></i>
                                <input type="text" placeholder="Search manifest, order, client" class="pl-8 pr-3 py-1.5 bg-white border rounded-sm text-[12.5px] w-56" style="border-color: var(--line);">
                            </div>
                            <select class="border rounded-sm text-[12.5px] py-1.5 px-2.5" style="border-color: var(--line); color: var(--ink);">
                                <option>All Statuses</option>
                                <option>Loading</option>
                                <option>In Transit</option>
                                <option>Delivered</option>
                                <option>Delayed</option>
                            </select>
                            <button class="flex items-center justify-center w-8 h-8 border rounded-sm transition-colors" style="border-color: var(--line); color: var(--mute);"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-[13px] font-medium">
                        <span class="tab-item active">Active Run <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $active_routes ?></span></span>
                        <span class="tab-item">Loading <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $loading_count ?></span></span>
                        <span class="tab-item">In Transit <span class="num text-[11px]" style="color: var(--mute-soft);"><?= $transit_count ?></span></span>
                        <span class="tab-item text-red-700">Delayed <span class="num text-[11px]" style="color: #963B33;"><?= $delayed_count ?></span></span>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-[0.08em] border-b" style="color: var(--mute); border-color: var(--line-soft);">
                                <th class="pl-6 pr-2 py-3 font-medium w-8"><span class="checkbox-sq"></span></th>
                                <th class="px-3 py-3 font-medium">Manifest ID</th>
                                <th class="px-3 py-3 font-medium">Order Ref</th>
                                <th class="px-3 py-3 font-medium">Client Account</th>
                                <th class="px-3 py-3 font-medium">Route Destination</th>
                                <th class="px-3 py-3 font-medium">Asset Assign</th>
                                <th class="px-3 py-3 font-medium">ETA / Time</th>
                                <th class="px-3 py-3 font-medium">Status</th>
                                <th class="pr-6 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                            <?php if (empty($dispatches)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-[13px]" style="color: var(--mute);">
                                    No dispatches found in the system.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($dispatches as $d): ?>
                                <?php
                                    $raw_status = strtolower($d['status']);
                                    $s = $statusStyles[$raw_status] ?? $statusStyles['dispatched'];
                                    $isDelivered = $raw_status === 'delivered';
                                    $isDelayed = $raw_status === 'cancelled';
                                    $rowColor = $isDelivered ? 'var(--mute-soft)' : 'var(--ink)';
                                    $formatted_eta = !empty($d['eta']) ? date('H:i', strtotime($d['eta'])) : '--:--';
                                ?>
                                <tr class="transition-colors" style="border-color: var(--line-soft);" onmouseover="this.style.background='var(--paper-dim)'" onmouseout="this.style.background='transparent'">
                                    <td class="pl-6 pr-2 py-3.5"><span class="checkbox-sq"></span></td>
                                    <td class="px-3 py-3.5 num font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($d['id']) ?></td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono" style="color: var(--mute);"><?= htmlspecialchars($d['order_ref']) ?></td>
                                    <td class="px-3 py-3.5 font-medium" style="color: <?= $rowColor ?>;"><?= htmlspecialchars($d['client'] ?? 'Unknown Client') ?></td>
                                    <td class="px-3 py-3.5 text-[13px]" style="color: <?= $isDelivered ? 'var(--mute-soft)' : 'var(--ink-soft)' ?>;"><?= htmlspecialchars($d['destination']) ?></td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-col">
                                            <span class="text-[12px] font-medium mono" style="color: <?= $rowColor ?>;"><i data-lucide="truck" class="w-3 h-3 inline mr-1" style="color: var(--mute);"></i><?= htmlspecialchars($d['vehicle']) ?></span>
                                            <span class="text-[11px]" style="color: var(--mute);"><?= htmlspecialchars($d['driver'] ?? 'Unassigned') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 text-[12.5px] mono font-medium" style="color: <?= $isDelayed ? '#963B33' : ($isDelivered ? 'var(--mute-soft)' : 'var(--ink)') ?>;"><?= $formatted_eta ?></td>
                                    <td class="px-3 py-3.5">
                                        <span class="pill" style="background: <?= $s['bg'] ?>; color: <?= $s['fg'] ?>;">
                                            <span class="status-dot" style="background:<?= $s['dot'] ?>;"></span><?= $s['label'] ?>
                                        </span>
                                    </td>
                                    <td class="pr-6 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-4">
                                            <a href="dispatch_details.php?id=<?= urlencode($d['id']) ?>" class="transition-colors" style="color: var(--mute); text-decoration: none;" title="Track Run" onmouseover="this.style.color='var(--ink)'" onmouseout="this.style.color='var(--mute)'">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                            <form method="POST" action="" class="m-0 p-0 inline-block" onsubmit="return confirm('متأكد إنك عايز تحذف الشحنة دي؟');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($d['id']) ?>">
                                                <button type="submit" class="transition-colors bg-transparent border-none cursor-pointer flex items-center" style="color: #963B33; padding: 0;" title="Delete Dispatch" onmouseover="this.style.color='#7a2d26'" onmouseout="this.style.color='#963B33'">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3.5 border-t flex justify-between items-center" style="border-color: var(--line-soft);">
                    <span class="text-[12px] mono" style="color: var(--mute);">Showing 1–<?= count($dispatches) ?> of Active Operations</span>
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
<?php
require_once '../../config/db.php';

$active_page = 'dispatch_delivery';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery', 'Manifest Details'];

$dispatch_id = $_GET['id'] ?? '';

$stmt = $pdo->prepare("
    SELECT 
        d.manifest_id as id,
        d.order_ref,
        p.company_name as client,
        d.destination,
        d.vehicle_id,
        v.make_model as vehicle_name,
        v.plate_number as plate,
        d.driver_id,
        dr.full_name as driver_name,
        dr.mobile_number as driver_phone,
        d.status,
        d.eta_time as eta,
        d.dispatch_date,
        d.created_at,
        d.instructions
    FROM dispatches d
    LEFT JOIN purchase_orders po ON d.order_ref = po.order_number
    LEFT JOIN partners p ON po.client_id = p.id
    LEFT JOIN vehicles v ON d.vehicle_id = v.fleet_id
    LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
    WHERE d.manifest_id = ?
");
$stmt->execute([$dispatch_id]);
$dispatch_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispatch_data) {
    header("Location: dispatch_delivery.php");
    exit;
}

$stmt_items = $pdo->prepare("
    SELECT pi.item_name, pi.qty, pi.unit
    FROM purchase_order_items pi
    JOIN purchase_orders po ON pi.purchase_order_id = po.id
    WHERE po.order_number = ?
    LIMIT 1
");
$stmt_items->execute([$dispatch_data['order_ref']]);
$item_data = $stmt_items->fetch(PDO::FETCH_ASSOC);

$gas_type = $item_data['item_name'] ?? 'Multiple Items / Mixed Freight';
$quantity = $item_data['qty'] ?? 'N/A';
$unit = $item_data['unit'] ?? '';

$dispatch = [
    'id' => $dispatch_data['id'],
    'order_ref' => $dispatch_data['order_ref'],
    'client' => $dispatch_data['client'] ?? 'Unknown Client',
    'destination' => $dispatch_data['destination'],
    'vehicle_id' => $dispatch_data['vehicle_id'],
    'vehicle_name' => $dispatch_data['vehicle_name'] ?? 'Unknown Vehicle',
    'plate' => $dispatch_data['plate'] ?? '---',
    'driver_id' => $dispatch_data['driver_id'],
    'driver_name' => $dispatch_data['driver_name'] ?? 'Unassigned',
    'driver_phone' => $dispatch_data['driver_phone'] ?? '---',
    'status' => strtolower($dispatch_data['status']),
    'eta' => date('H:i', strtotime($dispatch_data['eta'])),
    'gas_type' => $gas_type,
    'quantity' => $quantity,
    'unit' => $unit,
    'created_at' => date('Y-m-d H:i', strtotime($dispatch_data['created_at'])),
    'instructions' => $dispatch_data['instructions'] ?: 'Standard procedures apply.',
    'dispatch_date' => $dispatch_data['dispatch_date']
];

$creation_date = date('d M Y', strtotime($dispatch['created_at']));
$creation_time = date('H:i', strtotime($dispatch['created_at']));
$disp_date_fmt = date('d M Y', strtotime($dispatch['dispatch_date']));

$is_dispatched = $dispatch['status'] === 'dispatched';
$is_transit    = $dispatch['status'] === 'in_transit';
$is_delivered  = $dispatch['status'] === 'delivered';
$is_cancelled  = $dispatch['status'] === 'cancelled';

$timeline = [];

$timeline[] = [
    'time' => $creation_time, 
    'date' => $creation_date, 
    'title' => 'Manifest Generated', 
    'desc' => 'Dispatch order created and routed to logistics.', 
    'status' => 'past'
];

if ($is_cancelled) {
    $timeline[] = [
        'time' => 'N/A', 
        'date' => $disp_date_fmt, 
        'title' => 'Dispatch Cancelled', 
        'desc' => 'The dispatch operation was aborted.', 
        'status' => 'current'
    ];
} else {
    $timeline[] = [
        'time' => '--:--', 
        'date' => $disp_date_fmt, 
        'title' => 'Vehicle Loading', 
        'desc' => 'Freight secured and safety checks completed.', 
        'status' => ($is_dispatched ? 'current' : 'past')
    ];

    if ($is_transit || $is_delivered) {
        $timeline[] = [
            'time' => '--:--', 
            'date' => $disp_date_fmt, 
            'title' => 'Gate Out (Dispatched)', 
            'desc' => 'Vehicle left HQ / Origin point.', 
            'status' => 'past'
        ];
        $timeline[] = [
            'time' => 'Tracking', 
            'date' => 'Live', 
            'title' => 'In Transit', 
            'desc' => 'En route to ' . htmlspecialchars($dispatch['destination']) . '.', 
            'status' => ($is_transit ? 'current' : 'past')
        ];
    } else {
        $timeline[] = [
            'time' => 'Pending', 
            'date' => 'TBD', 
            'title' => 'Gate Out & Transit', 
            'desc' => 'Awaiting vehicle departure.', 
            'status' => 'future'
        ];
    }

    if ($is_delivered) {
        $timeline[] = [
            'time' => '--:--', 
            'date' => '--', 
            'title' => 'Delivered', 
            'desc' => 'Arrived at destination and client signed off.', 
            'status' => 'current'
        ];
    } else {
        $timeline[] = [
            'time' => $dispatch['eta'], 
            'date' => 'ETA', 
            'title' => 'Expected Delivery', 
            'desc' => 'Pending arrival and client sign-off.', 
            'status' => 'future'
        ];
    }
}

$statusStyles = [
    'dispatched' => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Loading'],
    'in_transit' => ['bg' => '#E8F1F5', 'fg' => '#2A6B8A', 'dot' => '#2A6B8A', 'label' => 'In Transit'],
    'delivered'  => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Delivered'],
    'cancelled'  => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Cancelled'],
];

$ds = $statusStyles[$dispatch['status']] ?? $statusStyles['dispatched'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifest <?= htmlspecialchars($dispatch['id']) ?> | I-GAS Enterprise</title>
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

        .info-group { margin-bottom: 20px; }
        .info-label { font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .info-value { font-size: 14px; color: var(--ink); font-weight: 500; }

        .timeline-rail { position: relative; }
        .timeline-rail::before { content: ''; position: absolute; left: 19.5px; top: 8px; bottom: 8px; width: 1px; background: var(--line-soft); }
        .timeline-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--paper); border: 2px solid var(--mute-soft); flex-shrink: 0; position: relative; z-index: 1; margin-top: 4px; }
        .timeline-dot.past { border-color: #45663F; background: #45663F; }
        .timeline-dot.current { border-color: #2A6B8A; background: #2A6B8A; box-shadow: 0 0 0 3px #E8F1F5; }
        .timeline-dot.future { border-color: var(--line); background: var(--paper); }
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

            <a href="dispatch_delivery.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Dispatch Registry
            </a>

            <div class="flex justify-between items-start mb-7">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-[26px] font-semibold tracking-tight leading-none mono" style="color: var(--ink);"><?= htmlspecialchars($dispatch['id']) ?></h2>
                        <span class="pill" style="background: <?= $ds['bg'] ?>; color: <?= $ds['fg'] ?>;">
                            <span class="status-dot" style="background:<?= $ds['dot'] ?>;"></span><?= $ds['label'] ?>
                        </span>
                    </div>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Generated on <?= htmlspecialchars($dispatch['created_at']) ?></p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i>Print Manifest
                    </button>
                    <button class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="map" class="w-4 h-4"></i>Update Status
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                
                <div class="xl:col-span-2 flex flex-col gap-6">
                    
                    <div class="card rounded-md p-6">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">General Information</h3>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="info-group">
                                <p class="info-label">Linked Order Reference</p>
                                <p class="info-value mono flex items-center gap-2">
                                    <?= htmlspecialchars($dispatch['order_ref']) ?>
                                </p>
                            </div>
                            <div class="info-group">
                                <p class="info-label">Client Account</p>
                                <p class="info-value"><?= htmlspecialchars($dispatch['client']) ?></p>
                            </div>
                            <div class="info-group col-span-2">
                                <p class="info-label">Destination Address</p>
                                <p class="info-value"><?= htmlspecialchars($dispatch['destination']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Resource Allocation</h3>
                            
                            <div class="info-group">
                                <p class="info-label">Assigned Vehicle</p>
                                <p class="info-value flex items-center gap-2">
                                    <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>
                                    <?= htmlspecialchars($dispatch['vehicle_name']) ?>
                                </p>
                                <p class="text-[12px] mono mt-1" style="color: var(--mute);"><?= htmlspecialchars($dispatch['vehicle_id']) ?> — <?= htmlspecialchars($dispatch['plate']) ?></p>
                                <a href="vehicle_logs.php?id=<?= urlencode($dispatch['vehicle_id']) ?>" class="text-[11px] font-medium underline mt-2 inline-block" style="color: var(--ink);">View Vehicle Logs</a>
                            </div>

                            <div class="info-group mb-0 mt-6 pt-6 border-t" style="border-color: var(--line-soft);">
                                <p class="info-label">Assigned Driver</p>
                                <p class="info-value flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4" style="color: var(--mute);"></i>
                                    <?= htmlspecialchars($dispatch['driver_name']) ?>
                                </p>
                                <p class="text-[12px] mono mt-1" style="color: var(--mute);"><?= htmlspecialchars($dispatch['driver_id']) ?> — <?= htmlspecialchars($dispatch['driver_phone']) ?></p>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Freight Specifications</h3>
                            
                            <div class="info-group">
                                <p class="info-label">Gas Type / Mix</p>
                                <p class="info-value mono"><?= htmlspecialchars($dispatch['gas_type']) ?></p>
                            </div>
                            
                            <div class="info-group">
                                <p class="info-label">Total Quantity</p>
                                <p class="info-value num text-[20px]"><?= htmlspecialchars($dispatch['quantity']) ?> <span class="text-[13px] font-normal" style="color: var(--mute);"><?= htmlspecialchars($dispatch['unit']) ?></span></p>
                            </div>

                            <div class="info-group mb-0 mt-6 pt-6 border-t" style="border-color: var(--line-soft);">
                                <p class="info-label">Safety & Delivery Instructions</p>
                                <p class="text-[12.5px] mt-1" style="color: var(--mute-soft);"><?= htmlspecialchars($dispatch['instructions']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-1 flex flex-col gap-6">
                    <div class="card rounded-md p-6 flex-1" style="background: var(--paper-deep);">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-6 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">Tracking Timeline</h3>
                        
                        <div class="timeline-rail">
                            <?php foreach ($timeline as $point): ?>
                                <div class="flex gap-4 pb-6 relative">
                                    <div class="timeline-dot <?= $point['status'] ?>"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[14px] font-semibold" style="color: <?= $point['status'] === 'future' ? 'var(--mute)' : 'var(--ink)' ?>;"><?= htmlspecialchars($point['title']) ?></p>
                                        <p class="text-[12.5px] mt-1" style="color: var(--mute);"><?= htmlspecialchars($point['desc']) ?></p>
                                        <div class="mt-2 text-[11px] mono font-medium" style="color: var(--mute-soft);">
                                            <?= htmlspecialchars($point['date']) ?> • <?= htmlspecialchars($point['time']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
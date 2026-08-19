<?php
session_start();
require_once '../../config/db.php';

$tank_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tank_id <= 0) {
    header("Location: customer_tanks.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        t.*, 
        p.company_name AS client_name,
        p.phone AS client_phone,
        p.email AS client_email,
        p.address AS client_address
    FROM customer_tanks t
    LEFT JOIN partners p ON t.client_id = p.id
    WHERE t.id = ?
");
$stmt->execute([$tank_id]);
$tank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tank) {
    header("Location: customer_tanks.php");
    exit;
}

$active_page = 'customer_tanks';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Assets', 'Customer Tanks', htmlspecialchars($tank['serial_number'])];

$level = (int)($tank['current_level_pct'] ?? 0);
$capacity = (float)($tank['capacity_liters'] ?? 0);
$current_liters = ($capacity * $level) / 100;

$tankStatusKey = 'optimal';
if (($tank['status'] ?? '') === 'maintenance') {
    $tankStatusKey = 'maintenance';
} elseif ($level <= 15) {
    $tankStatusKey = 'critical';
} elseif ($level < 30) {
    $tankStatusKey = 'refuel_req';
}

$statusStyles = [
    'optimal'     => ['bg' => '#EAF1E7', 'fg' => '#45663F', 'dot' => '#45663F', 'label' => 'Operational / Normal'],
    'refuel_req'  => ['bg' => '#FBF3DF', 'fg' => '#7A5E1E', 'dot' => '#9A7B2E', 'label' => 'Refuel Needed'],
    'critical'    => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Critical Level'],
    'maintenance' => ['bg' => '#F8E9E7', 'fg' => '#963B33', 'dot' => '#963B33', 'label' => 'Under Maintenance']
];
$statusObj = $statusStyles[$tankStatusKey];

$barColor = '#45663F';
if ($level <= 15) {
    $barColor = '#963B33';
} elseif ($level < 30) {
    $barColor = '#9A7B2E';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tank['serial_number']) ?> Details | I-GAS Enterprise</title>
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

        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        
        .pill { display: inline-flex; items-center; gap: 6px; padding: 4px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .detail-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 4px; display: block; }
        .detail-value { font-size: 14px; font-weight: 500; color: var(--ink); }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 02:00 PM–10:00 PM</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="customer_tanks.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Customer Tanks</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);"><?= htmlspecialchars($tank['serial_number']) ?></span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none flex items-center gap-3" style="color: var(--ink);">
                        <?= htmlspecialchars($tank['serial_number']) ?>
                        <span class="pill" style="background: <?= $statusObj['bg'] ?>; color: <?= $statusObj['fg'] ?>; font-size: 11px;">
                            <span class="status-dot" style="background:<?= $statusObj['dot'] ?>;"></span><?= $statusObj['label'] ?>
                        </span>
                    </h2>
                    <p class="text-[13.5px] mt-2.5 flex items-center gap-2" style="color: var(--mute);">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> Installed at: <?= htmlspecialchars($tank['client_name'] ?? 'Unassigned') ?> — <?= htmlspecialchars($tank['installation_location']) ?>
                    </p>
                </div>
                <div class="flex gap-3">
                    <button class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4"></i>Maintenance
                    </button>
                    <a href="schedule_refuel.php?tank_id=<?= $tank['id'] ?>" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                        <i data-lucide="truck" class="w-4 h-4"></i>Schedule Refuel
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="card rounded-md p-6 lg:col-span-2 flex flex-col justify-center">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <span class="detail-label">Current Telemetry Level</span>
                            <div class="flex items-baseline gap-2">
                                <h3 class="text-[32px] font-semibold tracking-tight num leading-none" style="color: var(--ink);"><?= $level ?>%</h3>
                                <span class="text-[14px] font-medium mono" style="color: var(--mute);">/ 100%</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="detail-label">Estimated Volume</span>
                            <div class="text-[18px] font-semibold num" style="color: var(--ink);"><?= number_format($current_liters, 2) ?> <span class="text-[13px] mono font-normal" style="color: var(--mute);">L</span></div>
                        </div>
                    </div>
                    
                    <div class="w-full h-6 rounded-sm overflow-hidden mb-3 relative" style="background: var(--paper-deep); box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                        <div class="h-full transition-all duration-1000 ease-in-out" style="width: <?= $level ?>%; background: <?= $barColor ?>;"></div>
                        <?php if($level < 30): ?>
                        <div class="absolute top-0 bottom-0 border-r-2 border-dashed border-red-500/50" style="left: 30%;"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex justify-between items-center text-[11px] font-medium uppercase tracking-wide mono" style="color: var(--mute);">
                        <span>Empty (0 L)</span>
                        <span class="flex items-center gap-1 text-orange-600/80"><i data-lucide="bell-ring" class="w-3 h-3"></i> Refuel Threshold (30%)</span>
                        <span>Full (<?= number_format($capacity) ?> L)</span>
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                        <i data-lucide="gauge" class="w-4 h-4" style="color: var(--mute);"></i>Asset Specifications
                    </h3>
                    <div class="flex flex-col gap-4">
                        <div>
                            <span class="detail-label">Gas Type</span>
                            <span class="detail-value"><?= htmlspecialchars($tank['gas_type']) ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="detail-label">Max Capacity</span>
                                <span class="detail-value num"><?= number_format($capacity) ?> <span class="text-[11px] text-[var(--mute)]">L</span></span>
                            </div>
                            <div>
                                <span class="detail-label">Working Pressure</span>
                                <span class="detail-value num"><?= number_format((float)$tank['working_pressure_bar'], 2) ?> <span class="text-[11px] text-[var(--mute)]">BAR</span></span>
                            </div>
                        </div>
                        <div>
                            <span class="detail-label">Asset Added Date</span>
                            <span class="detail-value num"><?= date('M d, Y', strtotime($tank['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="card rounded-md p-0 flex flex-col overflow-hidden lg:col-span-2">
                    <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                        <h3 class="text-[14px] font-semibold tracking-tight flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="history" class="w-4 h-4" style="color: var(--mute);"></i>Recent Telemetry Logs
                        </h3>
                        <span class="text-[11px] uppercase tracking-wider font-medium text-[var(--mute)] mono">Last 7 Days</span>
                    </div>
                    <div class="p-6 text-center text-[13px]" style="color: var(--mute);">
                        <i data-lucide="bar-chart-2" class="w-8 h-8 mx-auto mb-3 text-[var(--line)]"></i>
                        Live telemetry integration module is currently in standby mode.<br>Awaiting IoT sensor calibration.
                    </div>
                </div>

                <div class="card rounded-md p-6">
                    <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                        <i data-lucide="building" class="w-4 h-4" style="color: var(--mute);"></i>Client Information
                    </h3>
                    <div class="flex flex-col gap-4">
                        <div>
                            <span class="detail-label">Company Name</span>
                            <span class="detail-value font-semibold"><?= htmlspecialchars($tank['client_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <span class="detail-label">Phone</span>
                                <span class="detail-value num"><?= htmlspecialchars($tank['client_phone'] ?? 'N/A') ?></span>
                            </div>
                            <div>
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?= htmlspecialchars($tank['client_email'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="detail-label">Registered Address</span>
                            <span class="detail-value text-[13px] leading-relaxed"><?= htmlspecialchars($tank['client_address'] ?? 'N/A') ?></span>
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
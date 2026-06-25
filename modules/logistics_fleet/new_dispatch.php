<?php
require_once '../../config/db.php';

$active_page = 'dispatch_delivery';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Dispatch & Delivery', 'New Dispatch'];

$error_msg = '';

try {
    $stmt_seq = $pdo->query("SELECT COUNT(id) FROM dispatches");
    $count = $stmt_seq->fetchColumn();
    $new_manifest_id = 'DSP-' . str_pad($count + 907, 4, '0', STR_PAD_LEFT);

    $stmt_orders = $pdo->query("SELECT order_number FROM purchase_orders WHERE status IN ('draft', 'processing') ORDER BY id DESC");
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    $stmt_vehicles = $pdo->query("SELECT fleet_id, make_model, plate_number, driver_id FROM vehicles WHERE status = 'available' ORDER BY fleet_id ASC");
    $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

    $stmt_drivers = $pdo->query("SELECT driver_id, full_name, assigned_vehicle FROM drivers WHERE status = 'active' ORDER BY full_name ASC");
    $drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $orders = [];
    $vehicles = [];
    $drivers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manifest_id = $_POST['manifest_id'] ?? $new_manifest_id;
    $order_ref = $_POST['order_ref'] ?? '';
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $driver_id = $_POST['driver_id'] ?? '';
    $destination = trim($_POST['destination'] ?? '');
    $dispatch_date = $_POST['dispatch_date'] ?? '';
    $eta_time = $_POST['eta_time'] ?? '';
    $instructions = trim($_POST['instructions'] ?? '');

    if (empty($order_ref) || empty($vehicle_id) || empty($driver_id) || empty($destination) || empty($dispatch_date) || empty($eta_time)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO dispatches (manifest_id, order_ref, vehicle_id, driver_id, destination, dispatch_date, eta_time, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$manifest_id, $order_ref, $vehicle_id, $driver_id, $destination, $dispatch_date, $eta_time, $instructions]);

            $stmt_v = $pdo->prepare("UPDATE vehicles SET status = 'in_transit' WHERE fleet_id = ?");
            $stmt_v->execute([$vehicle_id]);

            $pdo->commit();
            header("Location: dispatch_delivery.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Error creating dispatch manifest. Manifest ID might already exist.";
        }
    }
}

// خرائط الربط بين السواق والسيارة (مأخوذة من الداتابيز: drivers.assigned_vehicle و vehicles.driver_id)
$driver_to_vehicle = [];
foreach ($drivers as $d) {
    if (!empty($d['assigned_vehicle']) && $d['assigned_vehicle'] !== 'unassigned') {
        $driver_to_vehicle[$d['driver_id']] = $d['assigned_vehicle'];
    }
}

$vehicle_to_driver = [];
foreach ($vehicles as $v) {
    if (!empty($v['driver_id']) && $v['driver_id'] !== 'unassigned') {
        $vehicle_to_driver[$v['fleet_id']] = $v['driver_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Dispatch | I-GAS Enterprise</title>
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

        .form-label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--mute); margin-bottom: 6px; }
        .form-input, .form-select { width: 100%; border: 1px solid var(--line); border-radius: 2px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); background: var(--paper); transition: border-color 0.15s ease; outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--ink); }
        .form-input::placeholder { color: var(--mute-soft); }
        .form-input:disabled { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }

        .input-group { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }

        .auto-hint { font-size: 11px; color: var(--accent); margin-top: 5px; display: none; align-items: center; gap: 4px; }
        .auto-hint.show { display: flex; }
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

            <div >
                <div class="mb-7">
                    <div class="flex items-center gap-2 mb-2">
                        <a href="dispatch_delivery.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Dispatch Registry</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Deployment</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Dispatch Manifest</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Authorize vehicle deployment, bind sales references, and establish logistics routing.</p>
                </div>

                <?php if ($error_msg): ?>
                    <div class="mb-5 p-3 rounded-sm text-[13px] font-medium" style="background: #F8E9E7; color: #963B33; border: 1px solid #963B33;">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <input type="hidden" name="manifest_id" value="<?= htmlspecialchars($new_manifest_id) ?>">
                    
                    <div class="p-8">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="file-text" class="w-4 h-4" style="color: var(--mute);"></i>Manifest Context
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Manifest ID</label>
                                <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($new_manifest_id) ?>" disabled>
                            </div>
                            <div>
                                <label class="form-label">Linked Order Reference</label>
                                <select class="form-select mono" name="order_ref" required>
                                    <option value="" disabled selected>Select active order...</option>
                                    <?php foreach ($orders as $o): ?>
                                        <option value="<?= htmlspecialchars($o['order_number']) ?>"><?= htmlspecialchars($o['order_number']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>Resource Allocation
                        </h3>

                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Assign Fleet Asset</label>
                                <select class="form-select mono" name="vehicle_id" id="vehicle_select" required>
                                    <option value="" disabled selected>Select available vehicle...</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= htmlspecialchars($v['fleet_id']) ?>">
                                            <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="auto-hint" id="vehicle_auto_hint">
                                    <i data-lucide="link" class="w-3 h-3"></i><span>Auto-selected based on assigned driver</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Assign Driver</label>
                                <select class="form-select" name="driver_id" id="driver_select" required>
                                    <option value="" disabled selected>Select available personnel...</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?= htmlspecialchars($d['driver_id']) ?>"><?= htmlspecialchars($d['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="auto-hint" id="driver_auto_hint">
                                    <i data-lucide="link" class="w-3 h-3"></i><span>Auto-selected based on assigned vehicle</span>
                                </div>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="map-pin" class="w-4 h-4" style="color: var(--mute);"></i>Routing &amp; Schedule
                        </h3>

                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="form-label">Destination Address / Drop-off Coordinates</label>
                                <input type="text" name="destination" class="form-input" placeholder="e.g. Industrial City 2, Gate 4, Jubail" required>
                            </div>
                            <div>
                                <label class="form-label">Dispatch Gate Out Date</label>
                                <div class="input-group">
                                    <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                    <input type="date" name="dispatch_date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Expected Arrival (ETA)</label>
                                <div class="input-group">
                                    <i data-lucide="clock" class="w-4 h-4 input-icon"></i>
                                    <input type="time" name="eta_time" class="form-input has-icon mono num" required>
                                </div>
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Special Delivery / Safety Manifest Instructions</label>
                                <textarea name="instructions" class="form-input" rows="3" placeholder="Enter security clearance protocols, hazmat pressure validation notes, or site contact criteria..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="background: var(--paper-dim); border-color: var(--line-soft);">
                        <a href="dispatch_delivery.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel Manifest</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Authorize &amp; Dispatch Run</button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // خرائط الربط بين السواق والسيارة، جاية من الداتابيز (drivers.assigned_vehicle و vehicles.driver_id)
        const driverToVehicle = <?= json_encode($driver_to_vehicle, JSON_UNESCAPED_UNICODE) ?>;
        const vehicleToDriver = <?= json_encode($vehicle_to_driver, JSON_UNESCAPED_UNICODE) ?>;

        const vehicleSelect = document.getElementById('vehicle_select');
        const driverSelect = document.getElementById('driver_select');
        const vehicleAutoHint = document.getElementById('vehicle_auto_hint');
        const driverAutoHint = document.getElementById('driver_auto_hint');

        let isSyncing = false;

        driverSelect.addEventListener('change', function () {
            if (isSyncing) return;
            const selectedDriverId = this.value;
            const linkedVehicle = driverToVehicle[selectedDriverId];

            vehicleAutoHint.classList.remove('show');

            if (linkedVehicle) {
                const optionExists = Array.from(vehicleSelect.options).some(opt => opt.value === linkedVehicle);
                if (optionExists) {
                    isSyncing = true;
                    vehicleSelect.value = linkedVehicle;
                    isSyncing = false;
                    vehicleAutoHint.classList.add('show');
                }
            }
        });

        vehicleSelect.addEventListener('change', function () {
            if (isSyncing) return;
            const selectedVehicleId = this.value;
            const linkedDriver = vehicleToDriver[selectedVehicleId];

            driverAutoHint.classList.remove('show');

            if (linkedDriver) {
                const optionExists = Array.from(driverSelect.options).some(opt => opt.value === linkedDriver);
                if (optionExists) {
                    isSyncing = true;
                    driverSelect.value = linkedDriver;
                    isSyncing = false;
                    driverAutoHint.classList.add('show');
                }
            }
        });
    </script>
</body>
</html>
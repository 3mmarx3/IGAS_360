<?php
require_once '../../config/db.php';

$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Add New Vehicle'];

$error_msg = '';

try {
    $stmt_drivers = $pdo->query("SELECT driver_id, full_name FROM drivers WHERE status = 'active' ORDER BY full_name ASC");
    $drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $drivers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fleet_id = $_POST['fleet_id'] ?? '';
    $plate_number = trim($_POST['plate_number'] ?? '');
    $make_model = trim($_POST['make_model'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? '';
    $vin = trim($_POST['vin'] ?? '');
    $manufacturing_year = !empty($_POST['manufacturing_year']) ? (int)$_POST['manufacturing_year'] : null;
    $load_capacity = !empty($_POST['load_capacity']) ? (float)$_POST['load_capacity'] : 0;
    $cylinder_capacity = !empty($_POST['cylinder_capacity']) ? (int)$_POST['cylinder_capacity'] : 0;
    $fuel_type = $_POST['fuel_type'] ?? 'diesel';
    $driver_id = $_POST['driver_id'] ?? 'unassigned';
    $status = $_POST['status'] ?? 'available';
    $registration_expiry = !empty($_POST['registration_expiry']) ? $_POST['registration_expiry'] : null;
    $insurance_expiry = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;

    if (empty($plate_number) || empty($make_model) || empty($vehicle_type) || empty($vin)) {
        $error_msg = "Please fill in all required vehicle identification fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicles (fleet_id, plate_number, make_model, vehicle_type, vin, manufacturing_year, load_capacity, cylinder_capacity, fuel_type, driver_id, status, registration_expiry, insurance_expiry)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fleet_id, $plate_number, $make_model, $vehicle_type, $vin, $manufacturing_year, 
                $load_capacity, $cylinder_capacity, $fuel_type, $driver_id, $status, 
                $registration_expiry, $insurance_expiry
            ]);
            
            header("Location: vehicles_fleet.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error adding vehicle. Plate number or VIN might already exist.";
        }
    }
}

$stmt_seq = $pdo->query("SELECT COUNT(id) FROM vehicles");
$count = $stmt_seq->fetchColumn();
$next_fleet_id = 'FLT-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Vehicle | I-GAS Enterprise</title>
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
        .input-group { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }
        .has-suffix { padding-right: 40px; }
        .input-suffix { position: absolute; right: 12px; font-size: 12px; color: var(--mute); pointer-events: none; font-family: 'IBM Plex Mono', monospace; }
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Logistics & Fleet</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register New Vehicle</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Add a new unit to the fleet database, configure specifications, and assign drivers.</p>
                </div>

                <?php if ($error_msg): ?>
                    <div class="mb-5 p-3 rounded-sm text-[13px] font-medium" style="background: #F8E9E7; color: #963B33; border: 1px solid #963B33;">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <div class="p-8">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="truck" class="w-4 h-4" style="color: var(--mute);"></i>Vehicle Identification
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Fleet ID</label>
                                <input type="text" name="fleet_id" class="form-input mono" value="<?= htmlspecialchars($next_fleet_id) ?>" readonly style="background: var(--paper-dim); color: var(--mute);">
                            </div>
                            <div>
                                <label class="form-label">Plate Number</label>
                                <input type="text" name="plate_number" class="form-input mono" placeholder="e.g. A B C 1234" required>
                            </div>
                            <div>
                                <label class="form-label">Make & Model</label>
                                <input type="text" name="make_model" class="form-input" placeholder="e.g. Mercedes Actros, Isuzu NPR" required>
                            </div>
                            <div>
                                <label class="form-label">Vehicle Type</label>
                                <select name="vehicle_type" class="form-select" required>
                                    <option value="" disabled selected>Select vehicle classification</option>
                                    <option value="cryo">Cryogenic Tanker</option>
                                    <option value="bobtail">Bobtail Tanker</option>
                                    <option value="flatbed">Flatbed Truck</option>
                                    <option value="pickup">Pickup Truck</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Chassis Number (VIN)</label>
                                <input type="text" name="vin" class="form-input mono" placeholder="17-character VIN" required>
                            </div>
                            <div>
                                <label class="form-label">Manufacturing Year</label>
                                <input type="number" name="manufacturing_year" class="form-input mono" placeholder="YYYY" min="2000" max="2026">
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="settings-2" class="w-4 h-4" style="color: var(--mute);"></i>Technical Specifications
                        </h3>

                        <div class="grid grid-cols-3 gap-6 mb-8">
                            <div>
                                <label class="form-label">Max Load Capacity</label>
                                <div class="input-group">
                                    <input type="number" name="load_capacity" class="form-input has-suffix" placeholder="0" step="0.01">
                                    <span class="input-suffix">TON</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Cylinder Capacity (If Flatbed)</label>
                                <div class="input-group">
                                    <input type="number" name="cylinder_capacity" class="form-input has-suffix" placeholder="0">
                                    <span class="input-suffix">CYL</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Fuel Type</label>
                                <select name="fuel_type" class="form-select">
                                    <option value="diesel">Diesel</option>
                                    <option value="petrol">Petrol (91)</option>
                                    <option value="petrol_95">Petrol (95)</option>
                                </select>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="shield-check" class="w-4 h-4" style="color: var(--mute);"></i>Compliance & Assignment
                        </h3>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="form-label">Assigned Driver</label>
                                <select name="driver_id" class="form-select">
                                    <option value="unassigned">Unassigned</option>
                                    <?php foreach ($drivers as $driver): ?>
                                        <option value="<?= htmlspecialchars($driver['driver_id']) ?>">
                                            <?= htmlspecialchars($driver['full_name']) ?> (<?= htmlspecialchars($driver['driver_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Initial Status</label>
                                <select name="status" class="form-select">
                                    <option value="available">Available / Ready</option>
                                    <option value="maintenance">In Maintenance</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Registration Expiry Date</label>
                                <div class="input-group">
                                    <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                    <input type="date" name="registration_expiry" class="form-input has-icon mono">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Insurance Expiry Date</label>
                                <div class="input-group">
                                    <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                    <input type="date" name="insurance_expiry" class="form-input has-icon mono">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="background: var(--paper-dim); border-color: var(--line-soft);">
                        <a href="vehicles_fleet.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Save & Register Vehicle</button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
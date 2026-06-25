<?php
require_once '../../config/db.php';

$active_page = 'vehicles_fleet';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Logistics & Fleet', 'Vehicles Fleet', 'Manual Log Entry'];

$vehicle_id = $_GET['id'] ?? '';
$error_msg = '';

try {
    $stmt_vehicles = $pdo->query("SELECT fleet_id, plate_number, make_model FROM vehicles ORDER BY fleet_id ASC");
    $vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $vehicles = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fleet_id = $_POST['vehicle_id'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $odometer = !empty($_POST['odometer']) ? (int)$_POST['odometer'] : 0;
    $fuel_liters = !empty($_POST['fuel_liters']) ? (float)$_POST['fuel_liters'] : 0;
    $event_cost = !empty($_POST['event_cost']) ? (float)$_POST['event_cost'] : 0;
    $logged_by = trim($_POST['logged_by'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($fleet_id) || empty($event_type) || empty($event_date) || empty($event_time) || empty($odometer)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vehicle_logs (fleet_id, event_type, event_date, event_time, odometer, fuel_liters, event_cost, logged_by, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fleet_id, $event_type, $event_date, $event_time, $odometer, $fuel_liters, $event_cost, $logged_by, $description]);
            
            header("Location: vehicles_fleet.php");
            exit;
        } catch (PDOException $e) {
            $error_msg = "Error saving log entry: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Log Entry | I-GAS Enterprise</title>
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
                    <div class="flex items-center gap-2 mb-2">
                        <a href="vehicles_fleet.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Fleet Database</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Manual Entry</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Manual Log Entry</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Manually record fleet events, fuel logs, dispatch movements, or workshop maintenance criteria.</p>
                </div>

                <?php if ($error_msg): ?>
                    <div class="mb-5 p-3 rounded-sm text-[13px] font-medium" style="background: #F8E9E7; color: #963B33; border: 1px solid #963B33;">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="card rounded-md flex flex-col overflow-hidden">
                    <div class="p-8">
                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="clipboard-list" class="w-4 h-4" style="color: var(--mute);"></i>Log Context
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="form-label">Target Vehicle</label>
                                <select class="form-select mono" name="vehicle_id" required>
                                    <option value="" disabled <?= empty($vehicle_id) ? 'selected' : '' ?>>Select vehicle asset ID...</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= htmlspecialchars($v['fleet_id']) ?>" <?= $vehicle_id === $v['fleet_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v['fleet_id']) ?> (<?= htmlspecialchars($v['make_model']) ?> — <?= htmlspecialchars($v['plate_number']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Event Type</label>
                                <select class="form-select" name="event_type" required>
                                    <option value="" disabled selected>Select event classification</option>
                                    <option value="dispatch">Dispatch (Outbound Route)</option>
                                    <option value="arrival">Arrival (Inbound Return)</option>
                                    <option value="refuel">Refuel Operations</option>
                                    <option value="maintenance">Maintenance &amp; Repairs</option>
                                    <option value="incident">Incident / Delay Report</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Date of Event</label>
                                <input type="date" class="form-input mono num" name="event_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label class="form-label">Time of Event</label>
                                <input type="time" class="form-input mono num" name="event_time" value="<?= date('H:i') ?>" required>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="gauge" class="w-4 h-4" style="color: var(--mute);"></i>Operational Metrics
                        </h3>

                        <div class="grid grid-cols-3 gap-6 mb-8">
                            <div>
                                <label class="form-label">Current Odometer</label>
                                <div class="input-group">
                                    <input type="number" class="form-input has-suffix mono num" placeholder="0" name="odometer" required>
                                    <span class="input-suffix">KM</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Fuel Added (If Refuel)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-input has-suffix mono num" placeholder="0.00" name="fuel_liters">
                                    <span class="input-suffix">LTR</span>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Associated Cost (SAR)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-input has-suffix mono num" placeholder="0.00" name="event_cost">
                                    <span class="input-suffix">SAR</span>
                                </div>
                            </div>
                        </div>

                        <hr class="mb-8" style="border-color: var(--line-soft);">

                        <h3 class="text-[14px] font-semibold tracking-tight mb-6 flex items-center gap-2" style="color: var(--ink);">
                            <i data-lucide="text-quote" class="w-4 h-4" style="color: var(--mute);"></i>Verification &amp; Narration
                        </h3>

                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="form-label">Logged By / Authority Sign-off</label>
                                <input type="text" name="logged_by" class="form-input" placeholder="e.g. Gate Security, Workshop Supervisor, Ahmed Ali">
                            </div>
                            <div>
                                <label class="form-label">Log Description &amp; Dynamic Details</label>
                                <textarea name="description" class="form-input" rows="4" placeholder="Provide contextual description of the route, refuel voucher numbers, replacement item metrics, or workshop diagnostics criteria..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t flex justify-end gap-3" style="background: var(--paper-dim); border-color: var(--line-soft);">
                        <a href="vehicles_fleet.php" class="btn-secondary px-5 py-2.5 rounded-sm text-[13.5px] font-medium">Cancel</a>
                        <button type="submit" class="btn-primary px-6 py-2.5 rounded-sm text-[13.5px] font-medium">Commit Log Entry</button>
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
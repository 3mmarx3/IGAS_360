<?php
session_start();
require_once '../../config/db.php';

$active_page = 'customer_tanks';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Assets', 'Customer Tanks', 'Register Tank Asset'];

$clientsStmt = $pdo->query("SELECT id, company_name FROM partners WHERE status = 'approved' ORDER BY company_name ASC");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number         = $_POST['serial_number'] ?? '';
    $client_id             = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $installation_location = $_POST['installation_location'] ?? '';
    $gas_type              = $_POST['gas_type'] ?? '';
    $capacity_liters       = (float)($_POST['capacity_liters'] ?? 0);
    $current_level_pct     = (int)($_POST['current_level_pct'] ?? 0);
    $working_pressure_bar  = (float)($_POST['working_pressure_bar'] ?? 0);
    $status                = $_POST['status'] ?? 'optimal';

    if (empty($serial_number) || empty($client_id) || empty($gas_type) || $capacity_liters <= 0) {
        $error_message = "يرجى تعبئة جميع الحقول الأساسية بشكل صحيح.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customer_tanks 
                (serial_number, client_id, installation_location, gas_type, capacity_liters, current_level_pct, working_pressure_bar, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $serial_number, $client_id, $installation_location, $gas_type, $capacity_liters, $current_level_pct, $working_pressure_bar, $status
            ]);
            $success_message = "تم تسجيل الخزان وربطه بالعميل بنجاح.";
        } catch (Exception $e) {
            $error_message = "حدث خطأ أثناء التسجيل: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Tank Asset | I-GAS Enterprise</title>
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
        .form-input:disabled, .form-input[readonly] { background: var(--paper-deep); color: var(--mute); cursor: not-allowed; border-color: var(--line-soft); }

        .input-group { position: relative; display: flex; align-items: center; }
        .has-suffix { padding-right: 45px; }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 02:00 PM–10:00 PM</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="customer_tanks.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Customer Tanks</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Register Tank</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register Tank Asset</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Add a new bulk storage tank and assign it to a client location.</p>
                </div>
                <div class="flex gap-3">
                    <a href="customer_tanks.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button type="button" onclick="document.getElementById('tank-form').submit();" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Save Asset
                    </button>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="p-4 mb-6 rounded-md text-[13.5px] font-medium flex items-center gap-2" style="background: #F8E9E7; color: #963B33; border: 1px solid #E7D5D3;">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($error_message) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="p-4 mb-6 rounded-md text-[13.5px] font-medium flex items-center gap-2" style="background: #EAF1E7; color: #45663F; border: 1px solid #D5E2D1;">
                <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i> <?= htmlspecialchars($success_message) ?>
            </div>
            <?php endif; ?>

            <form action="new_tank.php" method="POST" id="tank-form">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="building-2" class="w-4 h-4" style="color: var(--mute);"></i>Client & Location Assignment
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Client / Partner</label>
                                    <select name="client_id" class="form-select" required>
                                        <option value="" selected disabled>Select assigned client...</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Installation Location</label>
                                    <input type="text" name="installation_location" class="form-input" placeholder="e.g. Main Hospital Block A" required>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="container" class="w-4 h-4" style="color: var(--mute);"></i>Tank Specifications
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Serial Number / Asset ID</label>
                                    <input type="text" name="serial_number" class="form-input mono" placeholder="e.g. TNK-LQX-5001" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Gas Type</label>
                                    <select name="gas_type" class="form-select" required>
                                        <option value="" selected disabled>Select gas...</option>
                                        <option value="Liquid Oxygen">Liquid Oxygen</option>
                                        <option value="Liquid Nitrogen">Liquid Nitrogen</option>
                                        <option value="Liquid Argon">Liquid Argon</option>
                                        <option value="LPG Bulk">LPG Bulk</option>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Capacity</label>
                                    <div class="input-group">
                                        <input type="number" name="capacity_liters" class="form-input has-suffix mono num" placeholder="0.00" step="0.01" min="1" required>
                                        <span class="input-suffix">LITERS</span>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Working Pressure</label>
                                    <div class="input-group">
                                        <input type="number" name="working_pressure_bar" class="form-input has-suffix mono num" placeholder="0.00" step="0.01" min="0" required>
                                        <span class="input-suffix">BAR</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="activity" class="w-4 h-4" style="color: var(--mute);"></i>Initial Telemetry & Status
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Current Gas Level</label>
                                    <div class="input-group">
                                        <input type="number" name="current_level_pct" class="form-input has-suffix mono num" placeholder="0" min="0" max="100" required>
                                        <span class="input-suffix">%</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Operational Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="optimal" selected>Operational / Optimal</option>
                                        <option value="maintenance">Under Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
<?php
session_start();
require_once '../../config/db.php';

$active_page = 'cylinders_inventory';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Production', 'Cylinders Inventory', 'New Batch Registration'];

$new_batch_id = 'BAT-' . rand(8000, 8999);

$suppliersStmt = $pdo->query("SELECT id, company_name FROM partners WHERE partner_type = 'supplier' AND status = 'approved' ORDER BY company_name ASC");
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

$gasesStmt = $pdo->query("SELECT DISTINCT gas_classification FROM cylinder_batches WHERE gas_classification IS NOT NULL AND gas_classification != '' ORDER BY gas_classification ASC");
$dbGases = $gasesStmt->fetchAll(PDO::FETCH_COLUMN);

$volsStmt = $pdo->query("SELECT DISTINCT volume FROM cylinder_batches WHERE volume IS NOT NULL AND volume != '' ORDER BY volume ASC");
$dbVolumes = $volsStmt->fetchAll(PDO::FETCH_COLUMN);

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gas_classification = $_POST['gas_classification'] ?? '';
    $volume             = $_POST['volume'] ?? '';
    $build_material     = $_POST['build_material'] ?? '';
    $valve_spec         = $_POST['valve_spec'] ?? '';
    $color_coding       = $_POST['color_coding'] ?? '';
    $quantity           = (int)($_POST['quantity'] ?? 0);
    $supplier_id        = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $po_ref             = $_POST['po_ref'] ?? '';
    $mfg_date           = $_POST['mfg_date'] ?? '';
    $batch_number       = $_POST['batch_number'] ?? $new_batch_id;
    $sku                = $_POST['sku'] ?? '';
    $working_pressure   = (int)($_POST['working_pressure'] ?? 0);
    $test_pressure      = (int)($_POST['test_pressure'] ?? 0);
    $initial_test_date  = $_POST['initial_test_date'] ?? '';
    $next_test_due      = $_POST['next_test_due'] ?? '';

    if (empty($sku) || $quantity <= 0) {
        $error_message = "يرجى تعبئة جميع الحقول الأساسية بشكل صحيح.";
    } else {
        try {
            $pdo->beginTransaction();

            $batchStmt = $pdo->prepare("
                INSERT INTO cylinder_batches 
                (batch_number, sku, gas_classification, volume, build_material, valve_spec, color_coding, quantity, supplier_id, po_ref, mfg_date, working_pressure, test_pressure, initial_test_date, next_test_due) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $batchStmt->execute([
                $batch_number, $sku, $gas_classification, $volume, $build_material, $valve_spec, $color_coding, 
                $quantity, $supplier_id, $po_ref, $mfg_date, $working_pressure, $test_pressure, $initial_test_date, $next_test_due
            ]);

            $batch_id = $pdo->lastInsertId();

            $cylStmt = $pdo->prepare("
                INSERT INTO cylinders (barcode, batch_id, sku, status) 
                VALUES (?, ?, ?, 'in_plant')
            ");

            for ($i = 1; $i <= $quantity; $i++) {
                $barcode = $batch_number . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                $cylStmt->execute([$barcode, $batch_id, $sku]);
            }

            $pdo->commit();
            $success_message = "تم تسجيل عدد {$quantity} أسطوانة بنجاح وتوليد الباركود الخاص بها.";
            $new_batch_id = 'BAT-' . rand(8000, 8999);
        } catch (Exception $e) {
            $pdo->rollBack();
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
    <title>Register Cylinder Batch | I-GAS Enterprise</title>
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
        .input-icon { position: absolute; left: 12px; color: var(--mute-soft); pointer-events: none; }
        .has-icon { padding-left: 36px; }
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
            <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Shift B · 14:00–22:00</span>
        </div>

        <div class="flex-1 overflow-auto px-8 py-7">

            <div class="flex justify-between items-end mb-7">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="cylinders_inventory.php" class="text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors" style="color: var(--mute); text-decoration: none;">Cylinders Inventory</a>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Batch Registration</span>
                    </div>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Register New Batch</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Add a new batch of physical cylinders to the plant inventory and set compliance details.</p>
                </div>
                <div class="flex gap-3">
                    <a href="cylinders_inventory.php" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        Cancel
                    </a>
                    <button type="button" onclick="document.getElementById('batch-form').submit();" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>Register Assets
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

            <form action="new_batch_registration.php" method="POST" id="batch-form">
                <input type="hidden" name="batch_number" value="<?= htmlspecialchars($new_batch_id) ?>">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="cylinder" class="w-4 h-4" style="color: var(--mute);"></i>Asset Specifications
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Gas Classification</label>
                                    <select name="gas_classification" class="form-select" required onchange="updateSKU()">
                                        <option value="" selected disabled>Select primary gas...</option>
                                        <?php foreach ($dbGases as $gas): ?>
                                            <option value="<?= htmlspecialchars($gas) ?>"><?= htmlspecialchars($gas) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Cylinder Volume / Size</label>
                                    <select name="volume" class="form-select" required onchange="updateSKU()">
                                        <option value="" selected disabled>Select volume...</option>
                                        <?php foreach ($dbVolumes as $vol): ?>
                                            <option value="<?= htmlspecialchars($vol) ?>"><?= htmlspecialchars($vol) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Build Material</label>
                                    <input type="text" name="build_material" class="form-input" placeholder="e.g. Seamless Steel (34CrMo4)" required>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Valve Specification</label>
                                    <input type="text" name="valve_spec" class="form-input mono" placeholder="e.g. CGA 540, BS341" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="form-label">Color Coding Standard</label>
                                    <input type="text" name="color_coding" class="form-input" placeholder="e.g. White Shoulder / Black Body" required>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="package-plus" class="w-4 h-4" style="color: var(--mute);"></i>Batch Details
                            </h3>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Quantity to Register</label>
                                    <div class="input-group">
                                        <input type="number" name="quantity" class="form-input has-suffix mono num" placeholder="0" min="1" required>
                                        <span class="input-suffix">UNITS</span>
                                    </div>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Supplier / Manufacturer</label>
                                    <select name="supplier_id" class="form-select" required>
                                        <option value="" selected disabled>Select supplier...</option>
                                        <?php foreach ($suppliers as $sup): ?>
                                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Purchase Order Ref</label>
                                    <input type="text" name="po_ref" class="form-input mono" placeholder="e.g. PO-8850">
                                </div>
                                <div class="col-span-2 md:col-span-1">
                                    <label class="form-label">Manufacturing Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar" class="w-4 h-4 input-icon"></i>
                                        <input type="date" name="mfg_date" class="form-input has-icon mono num" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-1 flex flex-col gap-6">
                        
                        <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="hash" class="w-4 h-4" style="color: var(--mute);"></i>System Allocation
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div>
                                    <label class="form-label">Generated Batch ID</label>
                                    <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($new_batch_id) ?>" disabled>
                                </div>
                                <div>
                                    <label class="form-label">Target SKU (Auto-matched)</label>
                                    <input type="text" name="sku" id="target_sku" class="form-input mono" placeholder="e.g. CYL-O2-50L" readonly style="background: var(--paper-dim); color: var(--mute);">
                                </div>
                                <div>
                                    <label class="form-label">Initial Location Status</label>
                                    <select class="form-select" disabled>
                                        <option value="plant" selected>In Plant (Ready/Empty)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-5 pb-4 border-b flex items-center gap-2" style="color: var(--ink); border-color: var(--line-soft);">
                                <i data-lucide="shield-check" class="w-4 h-4" style="color: var(--mute);"></i>Compliance &amp; Testing
                            </h3>
                            
                            <div class="flex flex-col gap-5">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label">Working Pressure</label>
                                        <div class="input-group">
                                            <input type="number" name="working_pressure" class="form-input has-suffix mono num" placeholder="200" required>
                                            <span class="input-suffix">BAR</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Test Pressure</label>
                                        <div class="input-group">
                                            <input type="number" name="test_pressure" class="form-input has-suffix mono num" placeholder="300" required>
                                            <span class="input-suffix">BAR</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Initial Hydro-Test Date</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar-check" class="w-4 h-4 input-icon"></i>
                                        <input type="date" name="initial_test_date" class="form-input has-icon mono num" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Next Hydro-Test Due</label>
                                    <div class="input-group">
                                        <i data-lucide="calendar-clock" class="w-4 h-4 input-icon"></i>
                                        <input type="date" name="next_test_due" class="form-input has-icon mono num" value="<?= date('Y-m-d', strtotime('+5 years')) ?>" required>
                                    </div>
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

        function updateSKU() {
            const gasSelect = document.querySelector('select[name="gas_classification"]');
            const volSelect = document.querySelector('select[name="volume"]');
            const skuInput = document.getElementById('target_sku');
            
            const gasMap = {
                'Oxygen': 'O2',
                'Acetylene': 'AC',
                'Argon': 'AR',
                'Nitrogen': 'N2',
                'Helium': 'HE',
                'Carbon Dioxide': 'CO2',
                'Mixed Gas': 'MX'
            };
            
            const gas = gasSelect.value;
            const vol = volSelect.value;
            
            if(gas && vol) {
                let prefix = gasMap[gas];
                if (!prefix) {
                    prefix = gas.substring(0, 2).toUpperCase();
                }
                skuInput.value = 'CYL-' + prefix + '-' + vol;
            } else {
                skuInput.value = '';
            }
        }
    </script>
</body>
</html>
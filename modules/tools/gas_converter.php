<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_conversion') {
    $stmt = $pdo->prepare("INSERT INTO gas_conversion_logs (gas_type, input_qty, input_unit, result_mass, result_nm3, result_sm3, result_liquid, logged_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['gas_type'],
        $_POST['input_qty'],
        $_POST['input_unit'],
        $_POST['result_mass'],
        $_POST['result_nm3'],
        $_POST['result_sm3'],
        $_POST['result_liquid'],
        $_SESSION['user_name'] ?? 'Admin'
    ]);
    header("Location: gas_converter.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $pdo->query("TRUNCATE TABLE gas_conversion_logs");
    header("Location: gas_converter.php");
    exit;
}

$active_page = 'gas_converter';
$breadcrumb  = ['I-GAS', 'Tools', 'Gas Converter'];

$stmt = $pdo->query("SELECT * FROM gas_conversion_logs ORDER BY created_at DESC LIMIT 15");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gas Converter | I-GAS Enterprise</title>
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
        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; border: none; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }
        input:focus, select:focus { outline: none; border-color: var(--ink) !important; }
        th, td { vertical-align: middle; }
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] mb-2" style="color: var(--mute);">Tools</p>
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Gas Converter</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Convert between mass, gas volume (Nm³, Sm³) and liquid volume.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                
                <div class="lg:col-span-5 flex flex-col gap-6">
                    <div class="card rounded-md p-6">
                        <div class="mb-5">
                            <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-2" style="color: var(--mute);">Gas Selection</label>
                            <select id="gasSelect" class="w-full px-3 py-2 border rounded-sm text-[13.5px] bg-white" style="border-color: var(--line);">
                                <option value="Oxygen (O2)">Oxygen (O₂)</option>
                                <option value="Nitrogen (N2)">Nitrogen (N₂)</option>
                                <option value="Argon (Ar)">Argon (Ar)</option>
                                <option value="Carbon Dioxide (CO2)">Carbon Dioxide (CO₂)</option>
                            </select>
                        </div>
                        <div class="mb-5">
                            <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-2" style="color: var(--mute);">Amount to Convert</label>
                            <div class="flex gap-2">
                                <input type="number" id="inputQty" value="1" step="any" class="w-1/2 px-3 py-2 border rounded-sm text-[14px] num" style="border-color: var(--line);">
                                <select id="inputUnit" class="w-1/2 px-2 py-2 border rounded-sm text-[13px] bg-white" style="border-color: var(--line);">
                                    <option value="kg">Mass (kg)</option>
                                    <option value="nm3">Gas - Nm³ (0 °C)</option>
                                    <option value="sm3">Gas - Sm³ (15 °C)</option>
                                    <option value="liq">Liquid (Liters)</option>
                                </select>
                            </div>
                        </div>

                        <div class="border rounded-sm mt-2 mb-6" style="border-color: var(--line-soft);">
                            <table class="w-full text-left text-[13px]">
                                <tbody class="divide-y" style="border-color: var(--line-soft);">
                                    <tr class="bg-gray-50">
                                        <td class="px-4 py-2.5 font-medium" style="color: var(--mute);">Mass (kg)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold num" id="resMass">0.0000</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium" style="color: var(--mute);">Nm³ (0 °C, 1013 mbar)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold num" id="resNm3">0.0000</td>
                                    </tr>
                                    <tr class="bg-gray-50">
                                        <td class="px-4 py-2.5 font-medium" style="color: var(--mute);">Sm³ (15 °C, 1013 mbar)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold num" id="resSm3">0.0000</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium" style="color: var(--mute);">Liquid (Liters at NBP)</td>
                                        <td class="px-4 py-2.5 text-right font-semibold num" id="resLiquid">0.0000</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="log_conversion">
                            <input type="hidden" name="gas_type" id="formGas">
                            <input type="hidden" name="input_qty" id="formQty">
                            <input type="hidden" name="input_unit" id="formUnit">
                            <input type="hidden" name="result_mass" id="formMass">
                            <input type="hidden" name="result_nm3" id="formNm3">
                            <input type="hidden" name="result_sm3" id="formSm3">
                            <input type="hidden" name="result_liquid" id="formLiquid">
                            <button type="submit" class="btn-primary w-full py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>Save to Logs
                            </button>
                        </form>
                    </div>

                    <div class="card rounded-md p-6">
                        <h4 class="text-[13px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Cylinder Content Helper</h4>
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-[11px] font-medium uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Water Capacity (L)</label>
                                <input type="number" id="cylCap" value="40" class="w-full px-3 py-1.5 border rounded-sm text-[13px] num" style="border-color: var(--line);">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium uppercase tracking-[0.08em] mb-1.5" style="color: var(--mute);">Fill Pressure (bar)</label>
                                <input type="number" id="cylPres" value="150" class="w-full px-3 py-1.5 border rounded-sm text-[13px] num" style="border-color: var(--line);">
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 p-3 rounded-sm bg-gray-50 border" style="border-color: var(--line-soft);">
                            <div class="flex justify-between items-center text-[12.5px]">
                                <span style="color: var(--mute);">Volume</span>
                                <span class="font-semibold num" id="cylNm3Out">0.00 Nm³</span>
                            </div>
                            <div class="flex justify-between items-center text-[12.5px]">
                                <span style="color: var(--mute);">Approx. Mass</span>
                                <span class="font-semibold num" id="cylKgOut">0.00 kg</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="card rounded-md flex flex-col h-full overflow-hidden">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Gas Conversions</h3>
                            <form method="POST" action="" onsubmit="return confirm('Clear all conversion logs?');">
                                <input type="hidden" name="action" value="clear_logs">
                                <button type="submit" class="btn-secondary px-3 py-1.5 rounded-sm text-[12px] font-medium gap-1.5 text-red-600 hover:text-red-700">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>Clear History
                                </button>
                            </form>
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="text-[11px] uppercase tracking-[0.08em] border-b bg-gray-50" style="color: var(--mute); border-color: var(--line-soft);">
                                        <th class="pl-6 pr-3 py-3 font-medium">Gas</th>
                                        <th class="px-3 py-3 font-medium">Input</th>
                                        <th class="px-3 py-3 font-medium text-right">Mass (kg)</th>
                                        <th class="px-3 py-3 font-medium text-right">Nm³</th>
                                        <th class="px-3 py-3 font-medium text-right">Liquid (L)</th>
                                        <th class="pr-6 py-3 font-medium text-right">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13px] divide-y" style="border-color: var(--line-soft);">
                                    <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-8" style="color: var(--mute);">No conversion logs found.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr class="transition-colors hover:bg-gray-50">
                                            <td class="pl-6 pr-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($log['gas_type']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[12.5px]"><?= floatval($log['input_qty']) ?> <?= htmlspecialchars($log['input_unit']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[12.5px] text-right font-medium"><?= floatval($log['result_mass']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[12.5px] text-right font-medium"><?= floatval($log['result_nm3']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[12.5px] text-right font-medium"><?= floatval($log['result_liquid']) ?></td>
                                            <td class="pr-6 py-3.5 text-right text-[12px] mono" style="color: var(--mute);"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        const gasProperties = {
            'Oxygen (O2)': { rho_s: 1.3088, rho_l: 1141 },
            'Nitrogen (N2)': { rho_s: 1.1453, rho_l: 807 },
            'Argon (Ar)': { rho_s: 1.6339, rho_l: 1395 },
            'Carbon Dioxide (CO2)': { rho_s: 1.8003, rho_l: 1032 }
        };

        const TN = 273.15;
        const TS = 288.15;

        const gasSelect = document.getElementById('gasSelect');
        const inputQty = document.getElementById('inputQty');
        const inputUnit = document.getElementById('inputUnit');

        const resMass = document.getElementById('resMass');
        const resNm3 = document.getElementById('resNm3');
        const resSm3 = document.getElementById('resSm3');
        const resLiquid = document.getElementById('resLiquid');

        const formGas = document.getElementById('formGas');
        const formQty = document.getElementById('formQty');
        const formUnit = document.getElementById('formUnit');
        const formMass = document.getElementById('formMass');
        const formNm3 = document.getElementById('formNm3');
        const formSm3 = document.getElementById('formSm3');
        const formLiquid = document.getElementById('formLiquid');

        const cylCap = document.getElementById('cylCap');
        const cylPres = document.getElementById('cylPres');
        const cylNm3Out = document.getElementById('cylNm3Out');
        const cylKgOut = document.getElementById('cylKgOut');

        function rhoN(g) {
            return g.rho_s * TS / TN;
        }

        function calculateGas() {
            const gasKey = gasSelect.value;
            const g = gasProperties[gasKey];
            const v = parseFloat(inputQty.value) || 0;
            const u = inputUnit.value;

            const rn = rhoN(g);
            const rs = g.rho_s;
            const rlk = g.rho_l / 1000;

            let m = 0;
            if (u === 'kg') m = v;
            else if (u === 'nm3') m = v * rn;
            else if (u === 'sm3') m = v * rs;
            else if (u === 'liq') m = v * rlk;

            const calcNm3 = m / rn;
            const calcSm3 = m / rs;
            const calcLiq = m / rlk;

            resMass.textContent = m.toFixed(4);
            resNm3.textContent = calcNm3.toFixed(4);
            resSm3.textContent = calcSm3.toFixed(4);
            resLiquid.textContent = calcLiq.toFixed(4);

            formGas.value = gasKey;
            formQty.value = v;
            formUnit.value = u;
            formMass.value = m.toFixed(4);
            formNm3.value = calcNm3.toFixed(4);
            formSm3.value = calcSm3.toFixed(4);
            formLiquid.value = calcLiq.toFixed(4);
        }

        function calculateCyl() {
            const gasKey = gasSelect.value;
            const g = gasProperties[gasKey];
            const cap = parseFloat(cylCap.value) || 0;
            const p = parseFloat(cylPres.value) || 0;

            const nm3 = cap * (p + 1.01325) / 1.01325 / 1000;
            const kg = nm3 * rhoN(g);

            cylNm3Out.textContent = nm3.toFixed(2) + ' Nm³';
            cylKgOut.textContent = kg.toFixed(2) + ' kg';
        }

        gasSelect.addEventListener('change', () => {
            calculateGas();
            calculateCyl();
        });
        inputQty.addEventListener('input', calculateGas);
        inputUnit.addEventListener('change', calculateGas);

        cylCap.addEventListener('input', calculateCyl);
        cylPres.addEventListener('input', calculateCyl);

        calculateGas();
        calculateCyl();
    </script>
</body>
</html>
<?php
session_start();
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_conversion') {
    $stmt = $pdo->prepare("INSERT INTO unit_conversion_logs (category, from_unit, to_unit, input_value, result_value, logged_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['category'],
        $_POST['from_unit'],
        $_POST['to_unit'],
        $_POST['input_value'],
        $_POST['result_value'],
        $_SESSION['user_name'] ?? 'Admin'
    ]);
    header("Location: unit_converter.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $pdo->query("TRUNCATE TABLE unit_conversion_logs");
    header("Location: unit_converter.php");
    exit;
}

$active_page = 'unit_converter';
$breadcrumb  = ['I-GAS', 'Tools', 'Unit Converter'];

$stmt = $pdo->query("SELECT * FROM unit_conversion_logs ORDER BY created_at DESC LIMIT 15");
$logs = $stmt->fetchAll();
$total_logs = count($logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Converter | I-GAS Enterprise</title>
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
        .tab-item { position: relative; transition: color 0.15s ease; cursor: pointer; padding-bottom: 11px; }
        .tab-item::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: transparent; transition: background 0.15s ease; }
        .tab-item.active { color: var(--ink); }
        .tab-item.active::after { background: var(--ink); }
        .tab-item:not(.active) { color: var(--mute); }
        .tab-item:not(.active):hover { color: var(--ink); }
        .cat-btn { padding: 8px 16px; border: 1px solid var(--line); border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; transition: 0.2s; background: var(--paper); color: var(--mute); }
        .cat-btn.active { background: var(--ink); color: var(--paper); border-color: var(--ink); }
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
                    <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Unit Converter</h2>
                    <p class="text-[13.5px] mt-2.5" style="color: var(--mute);">Perform accurate engineering and industrial conversions.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="lg:col-span-1 flex flex-col gap-6">
                    <div class="card rounded-md p-6">
                        <div class="flex flex-wrap gap-2 mb-6" id="categoryContainer">
                            <button class="cat-btn active" data-cat="Pressure">Pressure</button>
                            <button class="cat-btn" data-cat="Volume">Volume</button>
                            <button class="cat-btn" data-cat="Mass">Mass</button>
                            <button class="cat-btn" data-cat="Temperature">Temperature</button>
                        </div>
                        <div class="mb-5">
                            <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-2" style="color: var(--mute);">From</label>
                            <div class="flex gap-2">
                                <input type="number" id="inputVal" value="1" class="w-2/3 px-3 py-2 border rounded-sm text-[14px] num" style="border-color: var(--line);">
                                <select id="fromUnit" class="w-1/3 px-2 py-2 border rounded-sm text-[13px] bg-white" style="border-color: var(--line);"></select>
                            </div>
                        </div>
                        <div class="flex justify-center mb-5">
                            <button id="swapBtn" class="w-8 h-8 rounded-full border flex items-center justify-center bg-white transition-colors hover:bg-gray-50" style="border-color: var(--line); color: var(--mute);">
                                <i data-lucide="arrow-down-up" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="mb-6">
                            <label class="block text-[11px] font-semibold uppercase tracking-[0.08em] mb-2" style="color: var(--mute);">To (Result)</label>
                            <div class="flex gap-2">
                                <input type="text" id="resultVal" readonly class="w-2/3 px-3 py-2 border rounded-sm text-[14px] font-semibold num bg-gray-50" style="border-color: var(--line); color: var(--ink);">
                                <select id="toUnit" class="w-1/3 px-2 py-2 border rounded-sm text-[13px] bg-white" style="border-color: var(--line);"></select>
                            </div>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="log_conversion">
                            <input type="hidden" name="category" id="formCat">
                            <input type="hidden" name="from_unit" id="formFrom">
                            <input type="hidden" name="to_unit" id="formTo">
                            <input type="hidden" name="input_value" id="formInput">
                            <input type="hidden" name="result_value" id="formResult">
                            <button type="submit" class="btn-primary w-full py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>Save to Logs
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="card rounded-md flex flex-col h-full overflow-hidden">
                        <div class="px-6 py-5 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                            <h3 class="text-[15px] font-semibold tracking-tight" style="color: var(--ink);">Recent Conversions</h3>
                            <form method="POST" action="" onsubmit="return confirm('Clear all logs?');">
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
                                        <th class="pl-6 pr-3 py-3 font-medium">Category</th>
                                        <th class="px-3 py-3 font-medium">Input</th>
                                        <th class="px-3 py-3 font-medium">Result</th>
                                        <th class="px-3 py-3 font-medium">User</th>
                                        <th class="pr-6 py-3 font-medium text-right">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-[13px]" style="color: var(--mute);">No conversion logs found.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr class="transition-colors hover:bg-gray-50">
                                            <td class="pl-6 pr-3 py-3.5 font-medium" style="color: var(--ink);"><?= htmlspecialchars($log['category']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[13px]"><?= floatval($log['input_value']) ?> <?= htmlspecialchars($log['from_unit']) ?></td>
                                            <td class="px-3 py-3.5 mono text-[13px] font-semibold" style="color: var(--ink);"><?= floatval($log['result_value']) ?> <?= htmlspecialchars($log['to_unit']) ?></td>
                                            <td class="px-3 py-3.5" style="color: var(--mute);"><?= htmlspecialchars($log['logged_by']) ?></td>
                                            <td class="pr-6 py-3.5 text-right text-[12.5px] mono" style="color: var(--mute);"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
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

        const unitsData = {
            Pressure: { "Pa": 1, "kPa": 1000, "MPa": 1000000, "bar": 100000, "mbar": 100, "psi": 6894.757, "atm": 101325 },
            Volume: { "m³": 1, "L": 0.001, "mL": 0.000001, "ft³": 0.0283168, "gal(US)": 0.00378541 },
            Mass: { "kg": 1, "g": 0.001, "tonne": 1000, "lb": 0.4535924, "oz": 0.02834952 },
            Temperature: { "°C": "C", "°F": "F", "K": "K" }
        };

        const catBtns = document.querySelectorAll('.cat-btn');
        const fromUnit = document.getElementById('fromUnit');
        const toUnit = document.getElementById('toUnit');
        const inputVal = document.getElementById('inputVal');
        const resultVal = document.getElementById('resultVal');
        const swapBtn = document.getElementById('swapBtn');

        const formCat = document.getElementById('formCat');
        const formFrom = document.getElementById('formFrom');
        const formTo = document.getElementById('formTo');
        const formInput = document.getElementById('formInput');
        const formResult = document.getElementById('formResult');

        let currentCat = 'Pressure';

        function populateSelects() {
            const units = Object.keys(unitsData[currentCat]);
            fromUnit.innerHTML = '';
            toUnit.innerHTML = '';
            units.forEach(u => {
                fromUnit.add(new Option(u, u));
                toUnit.add(new Option(u, u));
            });
            if (units.length > 1) {
                toUnit.selectedIndex = 1;
            }
            calculate();
        }

        function convertTemp(val, from, to) {
            let c;
            if (from === '°F') c = (val - 32) * 5/9;
            else if (from === 'K') c = val - 273.15;
            else c = val;

            if (to === '°F') return (c * 9/5) + 32;
            else if (to === 'K') return c + 273.15;
            else return c;
        }

        function calculate() {
            const v = parseFloat(inputVal.value) || 0;
            const fu = fromUnit.value;
            const tu = toUnit.value;
            let res = 0;

            if (currentCat === 'Temperature') {
                res = convertTemp(v, fu, tu);
            } else {
                const map = unitsData[currentCat];
                const baseVal = v * map[fu];
                res = baseVal / map[tu];
            }

            let finalRes = Number.isInteger(res) ? res : parseFloat(res.toFixed(6));
            resultVal.value = finalRes;

            formCat.value = currentCat;
            formFrom.value = fu;
            formTo.value = tu;
            formInput.value = v;
            formResult.value = finalRes;
        }

        catBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                catBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentCat = btn.getAttribute('data-cat');
                populateSelects();
            });
        });

        fromUnit.addEventListener('change', calculate);
        toUnit.addEventListener('change', calculate);
        inputVal.addEventListener('input', calculate);

        swapBtn.addEventListener('click', () => {
            const temp = fromUnit.value;
            fromUnit.value = toUnit.value;
            toUnit.value = temp;
            calculate();
        });

        populateSelects();
    </script>
</body>
</html>
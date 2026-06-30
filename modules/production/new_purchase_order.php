<?php
session_start();
require_once '../../config/db.php';

$active_page = 'raw_materials';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'Procurement', 'Purchase Orders', 'New Order'];

$material_row_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;
$pre_supplier_ref = '';
$pre_material_name = '';
$pre_material_unit = '';
$pre_material_code = '';
$pre_unit_price = 0.00;

if ($material_row_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            rm.material_name, rm.unit, rm.material_sku, rm.unit_cost,
            p.reference_id
        FROM raw_materials rm
        JOIN partners p ON rm.supplier_id = p.id
        WHERE rm.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $material_row_id]);
    $matRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($matRow) {
        $pre_supplier_ref  = $matRow['reference_id'];
        $pre_material_name = $matRow['material_name'];
        $pre_material_unit = $matRow['unit'];
        $pre_material_code = $matRow['material_sku'];
        $pre_unit_price    = (float)$matRow['unit_cost'];
    }
}

$suppliersStmt = $pdo->query("SELECT reference_id, company_name FROM partners WHERE partner_type = 'supplier' AND status = 'approved' ORDER BY company_name ASC");
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

$clientsStmt = $pdo->query("SELECT id, company_name FROM partners WHERE partner_type = 'client' ORDER BY company_name ASC");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id          = (int)$_POST['client_id'];
    $supplier_reference = trim($_POST['supplier_reference']);
    $order_date         = $_POST['order_date'];
    $delivery_date      = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
    $delivery_priority  = $_POST['delivery_priority'] ?? 'standard';
    $payment_terms      = $_POST['payment_terms'] ?? 'cod';
    $specs              = $_POST['specs'] ?? '';
    $delivery_address   = $_POST['delivery_address'] ?? '';

    $checkSupplier = $pdo->prepare("SELECT reference_id FROM partners WHERE reference_id = ? AND partner_type = 'supplier' LIMIT 1");
    $checkSupplier->execute([$supplier_reference]);
    if (!$checkSupplier->fetch()) {
        $error_message = "الـ Supplier المختار غير موجود أو غير معتمد. يرجى اختيار supplier صحيح.";
    } else {

        $order_number = 'ORD-' . rand(7000, 7999);
        $status = 'draft';
        $total_value = 0;

        try {
            $pdo->beginTransaction();

            $poStmt = $pdo->prepare("
                INSERT INTO purchase_orders 
                (order_number, client_id, supplier_reference, specs, order_date, delivery_address, delivery_date, delivery_priority, payment_terms, status, total_value) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $poStmt->execute([
                $order_number, $client_id, $supplier_reference, $specs, $order_date,
                $delivery_address, $delivery_date, $delivery_priority, $payment_terms, $status, $total_value
            ]);

            $po_id = $pdo->lastInsertId();

            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $itemStmt = $pdo->prepare("
                    INSERT INTO purchase_order_items 
                    (purchase_order_id, supplier_reference, product_id, item_name, item_code, qty, unit, unit_price, line_total) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($_POST['items'] as $item) {
                    $qty        = (float)$item['qty'];
                    $price      = (float)$item['price'];
                    $line_total = $qty * $price;
                    $total_value += $line_total;
                    $item_code  = trim($item['code']);

                    $matStmt = $pdo->prepare("SELECT id FROM raw_materials WHERE material_sku = ? LIMIT 1");
                    $matStmt->execute([$item_code]);
                    $matRow = $matStmt->fetch(PDO::FETCH_ASSOC);
                    $actual_product_id = $matRow ? $matRow['id'] : 0;

                    $itemStmt->execute([
                        $po_id,
                        $supplier_reference,
                        $actual_product_id,
                        $item['name'],
                        $item_code,
                        $qty,
                        $item['unit'],
                        $price,
                        $line_total
                    ]);

                    if ($actual_product_id > 0) {
                        $updateStock = $pdo->prepare("UPDATE raw_materials SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?");
                        $updateStock->execute([$qty, $actual_product_id]);

                        $trx_ref = 'TRX-' . rand(10000, 99999);
                        $logTrx = $pdo->prepare("
                            INSERT INTO material_transactions (material_id, transaction_ref, type, quantity, source_ref, logged_by, transaction_date)
                            VALUES (?, ?, 'in', ?, ?, 'System', NOW())
                        ");
                        $logTrx->execute([$actual_product_id, $trx_ref, $qty, $order_number]);
                    }
                }
            }

            $updateTotalStmt = $pdo->prepare("UPDATE purchase_orders SET total_value = ? WHERE id = ?");
            $updateTotalStmt->execute([$total_value, $po_id]);

            $actStmt = $pdo->prepare("
                INSERT INTO client_activities (client_id, activity_text, activity_time, author)
                VALUES (?, ?, NOW(), 'System')
            ");
            $actStmt->execute([
                $client_id,
                'Created new order ' . $order_number . ' with total ' . number_format($total_value, 2) . ' SAR'
            ]);

            $pdo->commit();

            header("Location: raw_materials.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Purchase Order | I-GAS Enterprise</title>
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

        .card { background: var(--paper); border: 1px solid var(--line-soft); }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary { background: var(--ink); color: var(--paper); transition: background-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; border: 1px solid var(--ink); cursor: pointer; }
        .btn-primary:hover { background: var(--ink-soft); }
        .btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); transition: background-color 0.15s ease, border-color 0.15s ease; text-decoration: none; display: inline-flex; justify-content: center; align-items: center; cursor: pointer; }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        th, td { vertical-align: middle; }

        .form-label { font-size: 11px; font-weight: 600; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 5px; display: block; }
        .form-control { width: 100%; background: var(--paper); border: 1px solid var(--line); border-radius: 3px; padding: 8px 12px; font-size: 13.5px; color: var(--ink); outline: none; transition: border-color 0.15s ease; font-family: inherit; }
        .form-control:focus { border-color: var(--accent); }
        .form-control.mono { font-family: 'IBM Plex Mono', monospace; }

        .table-control { width: 100%; background: transparent; border: 1px solid transparent; padding: 4px 8px; font-size: 13px; color: var(--ink); outline: none; transition: all 0.15s ease; }
        .table-control:focus { border-color: var(--line); background: var(--paper-dim); }
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

            <a href="raw_materials.php" class="inline-flex items-center gap-1.5 text-[12.5px] font-medium mb-5 transition-colors" style="color: var(--mute); text-decoration: none;">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back to Inventory Registry
            </a>

            <div class="flex justify-between items-start mb-7">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-md border flex items-center justify-center bg-white flex-shrink-0" style="border-color: var(--line);">
                        <i data-lucide="shopping-cart" class="w-6 h-6" style="color: var(--ink);"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <h2 class="text-[22px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Purchase Order</h2>
                        </div>
                        <p class="text-[13px] mono" style="color: var(--mute-soft);">PROCUREMENT · NEW DOCUMENT SYSTEM</p>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="p-4 mb-6 rounded-md text-[13.5px] font-medium flex items-center gap-2" style="background: #F8E9E7; color: #963B33; border: 1px solid #E7D5D3;">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($error_message) ?>
            </div>
            <?php endif; ?>

            <form action="new_purchase_order.php" method="POST" id="po-master-form">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                    <div class="xl:col-span-2 flex flex-col gap-6">
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-4 uppercase tracking-[0.05em]" style="color: var(--ink);">Order Core Parameters</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Client / Requestor</label>
                                    <select name="client_id" class="form-control" required>
                                        <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Primary Supplier</label>
                                    <select name="supplier_reference" class="form-control" required>
                                        <option value="" disabled <?= empty($pre_supplier_ref) ? 'selected' : '' ?>>Choose Supplier</option>
                                        <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= htmlspecialchars($s['reference_id']) ?>" <?= $pre_supplier_ref === $s['reference_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['company_name']) ?> (<?= htmlspecialchars($s['reference_id']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Issue Date</label>
                                    <input type="date" name="order_date" class="form-control mono" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Target Delivery Date</label>
                                    <input type="date" name="delivery_date" class="form-control mono">
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md flex flex-col overflow-hidden">
                            <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--line-soft);">
                                <h3 class="text-[14px] font-semibold tracking-tight uppercase tracking-[0.05em]" style="color: var(--ink);">Line Item Configuration</h3>
                                <button type="button" onclick="appendItemRow()" class="btn-secondary px-3 py-1.5 text-[12px] font-medium rounded-sm flex items-center gap-1.5">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Row
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse" id="items-data-table">
                                    <thead>
                                        <tr class="text-[11px] uppercase tracking-[0.08em] border-b bg-neutral-50" style="color: var(--mute); border-color: var(--line-soft);">
                                            <th class="pl-6 pr-2 py-3 font-medium w-[22%]">Item Code / SKU</th>
                                            <th class="px-2 py-3 font-medium w-[35%]">Description / Name</th>
                                            <th class="px-2 py-3 font-medium text-right w-[12%]">Qty</th>
                                            <th class="px-2 py-3 font-medium w-[10%]">Unit</th>
                                            <th class="px-2 py-3 font-medium text-right w-[13%]">Unit Cost</th>
                                            <th class="pr-6 pl-2 py-3 font-medium text-right w-[8%]"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-[13.5px] divide-y" style="border-color: var(--line-soft);">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-6">
                        <div class="card rounded-md p-6">
                            <h3 class="text-[14px] font-semibold tracking-tight mb-4 uppercase tracking-[0.05em]" style="color: var(--ink);">Logistics & Logistics</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="form-label">Delivery Priority</label>
                                    <select name="delivery_priority" class="form-control">
                                        <option value="standard" selected>Standard Delivery</option>
                                        <option value="urgent">Urgent Dispatch</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Payment Terms</label>
                                    <select name="payment_terms" class="form-control">
                                        <option value="cod" selected>Cash On Delivery (COD)</option>
                                        <option value="net30">Net 30 Days</option>
                                        <option value="net60">Net 60 Days</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Destination Address</label>
                                    <input type="text" name="delivery_address" class="form-control" placeholder="Jeddah Port, Warehouse 4...">
                                </div>
                                <div>
                                    <label class="form-label">System Specification Notes</label>
                                    <textarea name="specs" rows="3" class="form-control" placeholder="Internal remarks, certifications required..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card rounded-md p-6 flex flex-col justify-between" style="background: var(--paper-deep);">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.1em] mb-1" style="color: var(--mute);">Financial Aggregate Summary</p>
                                <h3 class="text-[28px] font-semibold tracking-tight num" style="color: var(--ink);"><span class="text-[16px] font-normal" style="color: var(--mute);">SAR</span> <span id="po-grand-total">0.00</span></h3>
                            </div>
                            <div class="mt-6 flex flex-col gap-2.5">
                                <button type="submit" class="w-full btn-primary py-2.5 rounded-sm text-[13.5px] font-medium flex items-center gap-2">
                                    <i data-lucide="file-check" class="w-4 h-4"></i> Commit Purchase Order
                                </button>
                                <a href="raw_materials.php" class="w-full btn-secondary py-2.5 rounded-sm text-[13.5px] font-medium text-center">
                                    Abort Operation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        lucide.createIcons();

        let incrementalRowIndex = 0;

        function recalculateSystemTotals() {
            let aggregateSum = 0;
            const rows = document.querySelectorAll('#items-data-table tbody tr');
            
            rows.forEach(r => {
                const qEl = r.querySelector('.qty-field');
                const pEl = r.querySelector('.price-field');
                
                const qtyValue = parseFloat(qEl.value) || 0;
                const priceValue = parseFloat(pEl.value) || 0;
                aggregateSum += (qtyValue * priceValue);
            });

            document.getElementById('po-grand-total').textContent = aggregateSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function appendItemRow(cVal = '', nVal = '', uVal = '', qVal = 1, pVal = 0) {
            if (!cVal) {
                cVal = 'SKU-' + Math.floor(100000 + Math.random() * 900000);
            }

            const tbody = document.querySelector('#items-data-table tbody');
            const tr = document.createElement('tr');
            tr.className = 'transition-colors';
            tr.style.borderColor = 'var(--line-soft)';
            
            tr.innerHTML = `
                <td class="pl-6 pr-2 py-2">
                    <input type="text" name="items[${incrementalRowIndex}][code]" value="${cVal}" class="table-control mono" style="opacity: 0.6; pointer-events: none;" placeholder="SKU-CODE" required readonly tabindex="-1">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="items[${incrementalRowIndex}][name]" value="${nVal}" class="table-control font-medium" placeholder="Item Nomenclature" required>
                </td>
                <td class="px-2 py-2">
                    <input type="number" step="0.01" name="items[${incrementalRowIndex}][qty]" value="${qVal}" class="table-control text-right num qty-field" required oninput="recalculateSystemTotals()">
                </td>
                <td class="px-2 py-2">
                    <input type="text" name="items[${incrementalRowIndex}][unit]" value="${uVal}" class="table-control mono text-center" placeholder="Unit" required>
                </td>
                <td class="px-2 py-2">
                    <input type="number" step="0.01" name="items[${incrementalRowIndex}][price]" value="${pVal}" class="table-control text-right num price-field" required oninput="recalculateSystemTotals()">
                </td>
                <td class="pr-6 pl-2 py-2 text-center">
                    <button type="button" onclick="this.closest('tr').remove(); recalculateSystemTotals();" class="text-gray-400 hover:text-red-700 transition-colors">
                        <i data-lucide="x" class="w-4 h-4 mx-auto"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
            lucide.createIcons({ root: tr });
            incrementalRowIndex++;
            recalculateSystemTotals();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const pName = <?= json_encode($pre_material_name) ?>;
            const pCode = <?= json_encode($pre_material_code) ?>;
            const pUnit = <?= json_encode($pre_material_unit) ?>;
            const pPrice = <?= json_encode($pre_unit_price) ?>;
            
            if (pName) {
                appendItemRow(pCode, pName, pUnit, 1, pPrice);
            } else {
                appendItemRow('', '', '', 1, 0);
            }
        });
    </script>
</body>
</html>
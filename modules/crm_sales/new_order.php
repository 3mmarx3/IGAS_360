<?php
require_once '../../config/db.php';

$active_page = 'clients';
$base_url    = '../../';
$breadcrumb  = ['I-GAS', 'CRM & Accounts', 'Clients Directory', 'New Order'];

$reference_id = $_GET['client_id'] ?? ($_POST['client_reference'] ?? 'ACC-2984');

$stmt = $pdo->prepare("SELECT id, company_name FROM partners WHERE reference_id = ?");
$stmt->execute([$reference_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die("Error: Client account not found in database.");
}

$client_id_internal = $client['id'];
$client_name = $client['company_name'];

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_reference = $_POST['supplier_reference'] ?? '';
    $order_ref = $_POST['order_ref'] ?? ('ORD-' . rand(7800, 7999));
    $po_number = trim($_POST['po_number'] ?? '');
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
    $delivery_time = !empty($_POST['delivery_time']) ? $_POST['delivery_time'] : null;
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $delivery_priority = $_POST['delivery_priority'] ?? 'standard';
    $payment_terms = $_POST['payment_terms'] ?? 'cod';
    $send_email = isset($_POST['send_email']) ? 1 : 0;

    $items = $_POST['items'] ?? [];

    if (!$supplier_reference) {
        $error_msg = "اختر التاجر أولاً.";
    } elseif (empty($items)) {
        $error_msg = "لا يوجد منتجات تم اختيارها.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_supplier = $pdo->prepare("SELECT id, company_name, reference_id FROM partners WHERE reference_id = ? AND partner_type = 'supplier'");
            $stmt_supplier->execute([$supplier_reference]);
            $supplier = $stmt_supplier->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                throw new Exception("التاجر غير موجود.");
            }

            $subtotal = 0.00;
            $prepared_items = [];

            $stmt_product = $pdo->prepare("
                SELECT id, supplier_reference, item_code, item_name, category, quantity, unit, unit_price, currency, lead_time_days
                FROM supplier_products
                WHERE id = ? AND supplier_reference = ?
                LIMIT 1
            ");

            foreach ($items as $item_id => $item_data) {
                $product_id = (int)($item_data['product_id'] ?? 0);
                $qty = (float)($item_data['qty'] ?? 0);
                $discount = (float)($item_data['discount'] ?? 0);

                if ($product_id <= 0 || $qty <= 0) {
                    continue;
                }

                $stmt_product->execute([$product_id, $supplier_reference]);
                $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    continue;
                }

                $unit_price = (float)$product['unit_price'];
                $line_total = $qty * $unit_price;
                $discount_amt = $line_total * ($discount / 100);
                $line_total_after = $line_total - $discount_amt;

                $subtotal += $line_total_after;

                $prepared_items[] = [
                    'product_id' => $product_id,
                    'supplier_reference' => $supplier_reference,
                    'item_code' => $product['item_code'],
                    'item_name' => $product['item_name'],
                    'category' => $product['category'],
                    'qty' => $qty,
                    'unit' => $product['unit'],
                    'unit_price' => $unit_price,
                    'discount' => $discount,
                    'line_total' => $line_total_after
                ];
            }

            if (empty($prepared_items)) {
                throw new Exception("لم يتم العثور على أي منتجات صالحة.");
            }

            $vat = $subtotal * 0.15;
            $grand_total = $subtotal + $vat;

            $specs = 'Supplier: ' . $supplier['company_name'] . ' | Items: ' . count($prepared_items);

            $stmt_insert = $pdo->prepare("
                INSERT INTO purchase_orders (order_number, client_id, supplier_reference, specs, order_date, delivery_address, delivery_date, delivery_time, delivery_priority, payment_terms, status, total_value)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt_insert->execute([
                $order_ref, 
                $client_id_internal, 
                $supplier_reference, 
                $specs, 
                $order_date, 
                $delivery_address, 
                $delivery_date, 
                $delivery_time, 
                $delivery_priority, 
                $payment_terms, 
                $grand_total
            ]);

            $purchase_order_id = $pdo->lastInsertId();

            $stmt_item_insert = $pdo->prepare("
                INSERT INTO purchase_order_items (purchase_order_id, supplier_reference, product_id, item_name, item_code, qty, unit, unit_price, discount_pct, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($prepared_items as $pi) {
                $stmt_item_insert->execute([
                    $purchase_order_id,
                    $pi['supplier_reference'],
                    $pi['product_id'],
                    $pi['item_name'],
                    $pi['item_code'],
                    $pi['qty'],
                    $pi['unit'],
                    $pi['unit_price'],
                    $pi['discount'],
                    $pi['line_total']
                ]);
            }

            $stmt_activity = $pdo->prepare("
                INSERT INTO client_activities (client_id, activity_text, activity_time, author)
                VALUES (?, ?, NOW(), 'System')
            ");
            $stmt_activity->execute([
                $client_id_internal,
                "Created new order {$order_ref} with total " . number_format($grand_total, 2) . " SAR"
            ]);

            $stmt_update_client = $pdo->prepare("
                UPDATE partners
                SET balance_due = balance_due + ?, lifetime_value = lifetime_value + ?, last_order_date = ?, payment_terms = ?
                WHERE id = ?
            ");
            $stmt_update_client->execute([$grand_total, $grand_total, $order_date, $payment_terms, $client_id_internal]);

            $pdo->commit();

            header("Location: view_order.php?order_num=" . urlencode($order_ref));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = $e->getMessage();
        }
    }
}

$order_ref = 'ORD-' . rand(7800, 7999);

$stmt_suppliers = $pdo->query("SELECT reference_id, company_name FROM partners WHERE partner_type = 'supplier' ORDER BY company_name ASC");
$suppliers = $stmt_suppliers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order | I-GAS Enterprise</title>
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
        .nav-row:focus-visible { outline: 1px solid var(--accent); outline-offset: -1px; }

        .card {
            background: var(--paper);
            border: 1px solid var(--line-soft);
        }

        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-primary {
            background: var(--ink);
            color: var(--paper);
            transition: background-color 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover { background: var(--ink-soft); }

        .btn-secondary {
            background: var(--paper);
            color: var(--ink);
            border: 1px solid var(--line);
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .btn-secondary:hover { background: var(--paper-dim); border-color: var(--mute-soft); }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--mute);
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 2px;
            padding: 8px 12px;
            font-size: 13.5px;
            color: var(--ink);
            transition: border-color 0.15s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--ink);
            box-shadow: 0 0 0 1px var(--ink);
        }
        .form-input:disabled, .form-input.readonly {
            background: var(--paper-deep);
            color: var(--mute);
            cursor: not-allowed;
            border-color: var(--line-soft);
        }
        .table-wrap {
            overflow: auto;
            border: 1px solid var(--line-soft);
            border-radius: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line-soft);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }
        th {
            background: var(--paper-dim);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--mute);
        }
        .msg-error {
            background: #FDECEC;
            color: #8A1F1F;
            border: 1px solid #F5B7B1;
            padding: 12px 14px;
            border-radius: 6px;
        }
        .msg-success {
            background: #ECFDF3;
            color: #166534;
            border: 1px solid #BBF7D0;
            padding: 12px 14px;
            border-radius: 6px;
        }
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
        <span class="text-[11px] mono uppercase tracking-wide" style="color: var(--mute);">Order Creation</span>
        <span class="ml-auto text-[11px] mono uppercase tracking-wide" style="color: var(--mute-soft);">v2.4.1</span>
    </div>

    <div class="flex-1 overflow-auto px-8 py-7">
        <div class="flex justify-between items-end mb-7">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <a href="client_profile.php?id=<?= htmlspecialchars($reference_id) ?>" class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--mute); text-decoration: none;">Client Profile</a>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-[var(--mute-soft)]"></i>
                    <span class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color: var(--ink);">Order Creation</span>
                </div>
                <h2 class="text-[26px] font-semibold tracking-tight leading-none" style="color: var(--ink);">Create Order</h2>
            </div>
            <div class="flex gap-3">
                <a href="client_profile.php?id=<?= htmlspecialchars($reference_id) ?>" class="btn-secondary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                    Cancel
                </a>
                <button type="submit" form="orderForm" class="btn-primary px-4 py-2.5 rounded-sm text-[13.5px] font-medium gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>Submit Order
                </button>
            </div>
        </div>

        <?php if ($error_msg): ?>
            <div class="msg-error mb-5"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form id="orderForm" action="" method="POST">
            <input type="hidden" name="client_reference" value="<?= htmlspecialchars($reference_id) ?>">
            <input type="hidden" name="order_ref" value="<?= htmlspecialchars($order_ref) ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <div class="card rounded-md p-6">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">1. Client & Reference</h3>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Client Account</label>
                                <input type="text" class="form-input readonly" value="<?= htmlspecialchars($client_name) ?>" disabled>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Order Reference</label>
                                <input type="text" class="form-input readonly mono num" value="<?= htmlspecialchars($order_ref) ?>" disabled>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Purchase Order (PO) Number</label>
                                <input type="text" name="po_number" class="form-input mono" placeholder="e.g. PO-998234">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Order Date</label>
                                <input type="date" name="order_date" class="form-input mono num" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="card rounded-md p-6">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">2. Supplier & Products</h3>

                        <div class="grid grid-cols-2 gap-5 mb-5">
                            <div>
                                <label class="form-label">Select Supplier</label>
                                <select id="supplierSelect" name="supplier_reference" class="form-input" required>
                                    <option value="">Choose supplier...</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= htmlspecialchars($s['reference_id']) ?>"><?= htmlspecialchars($s['company_name']) ?> (<?= htmlspecialchars($s['reference_id']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Search Product</label>
                                <input id="productSearch" type="text" class="form-input" placeholder="Filter products by name or code" disabled>
                            </div>
                        </div>

                        <div id="productsArea" class="table-wrap">
                            <div class="p-4 text-[13px]" style="color: var(--mute);">Select a supplier to load products.</div>
                        </div>
                    </div>

                    <div class="card rounded-md p-6">
                        <h3 class="text-[15px] font-semibold tracking-tight mb-5 pb-4 border-b" style="color: var(--ink); border-color: var(--line-soft);">3. Logistics & Delivery</h3>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Expected Delivery Date</label>
                                <input type="date" name="delivery_date" class="form-input mono num">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Delivery Time</label>
                                <input type="time" name="delivery_time" class="form-input mono num">
                            </div>
                            <div class="col-span-2">
                                <label class="form-label">Delivery Address / Plant Location</label>
                                <textarea name="delivery_address" class="form-input" rows="2" placeholder="Enter delivery coordinates, gate number, or contact person at site..."></textarea>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Delivery Priority</label>
                                <select name="delivery_priority" class="form-input mono">
                                    <option value="standard">Standard (3-5 Days)</option>
                                    <option value="express">Express (Next Day)</option>
                                    <option value="urgent">Urgent / Emergency</option>
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="form-label">Payment Terms</label>
                                <select name="payment_terms" class="form-input mono">
                                    <option value="net30">Net 30 Days</option>
                                    <option value="net60">Net 60 Days</option>
                                    <option value="cod">Cash on Delivery (COD)</option>
                                    <option value="prepaid">Prepaid</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1 flex flex-col gap-6">
                    <div class="card rounded-md p-6" style="background: var(--paper-deep);">
                        <h3 class="text-[13.5px] font-semibold tracking-tight mb-4" style="color: var(--ink);">Order Summary</h3>
                        <ul class="text-[12.5px] flex flex-col gap-3 mb-5" style="color: var(--mute);">
                            <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                <span>Subtotal</span>
                                <span class="mono num" style="color: var(--ink);" id="disp_subtotal">0.00 SAR</span>
                            </li>
                            <li class="flex justify-between items-center border-b pb-2" style="border-color: var(--line-soft);">
                                <span>VAT (15%)</span>
                                <span class="mono num" style="color: var(--ink);" id="disp_vat">0.00 SAR</span>
                            </li>
                            <li class="flex justify-between items-center pt-1">
                                <span class="font-semibold" style="color: var(--ink);">Grand Total</span>
                                <span class="mono num text-[15px] font-semibold" style="color: var(--ink);" id="disp_total">0.00 SAR</span>
                            </li>
                        </ul>
                        <div class="flex items-center gap-2 mb-5">
                            <input type="checkbox" name="send_email" id="send_email" class="w-4 h-4 border-gray-300 rounded" checked>
                            <label for="send_email" class="text-[12px]" style="color: var(--mute);">Send confirmation to client</label>
                        </div>
                        <button type="button" onclick="updateTotals()" class="w-full btn-secondary py-2.5 rounded-sm text-[13.5px] font-medium border-dashed border-2 mb-3">
                            Calculate Totals
                        </button>
                        <button type="submit" class="w-full btn-primary py-2.5 rounded-sm text-[13.5px] font-medium">
                            Confirm & Route to Production
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
    lucide.createIcons();

    const supplierSelect = document.getElementById('supplierSelect');
    const productsArea = document.getElementById('productsArea');
    const productSearch = document.getElementById('productSearch');
    const orderForm = document.getElementById('orderForm');

    let currentProducts = [];
    let selectedItems = {};

    supplierSelect.addEventListener('change', () => {
        const supRef = supplierSelect.value;
        selectedItems = {};
        renderSummary();

        if (!supRef) {
            productSearch.disabled = true;
            productsArea.innerHTML = '<div class="p-4 text-[13px]" style="color: var(--mute);">Select a supplier to load products.</div>';
            return;
        }

        productSearch.disabled = false;
        productsArea.innerHTML = '<div class="p-4 text-[13px]" style="color: var(--mute);">Loading products...</div>';

        fetch('load_supplier_products.php?supplier=' + encodeURIComponent(supRef))
            .then(r => r.json())
            .then(data => {
                currentProducts = data.products || [];
                renderProducts(currentProducts);
            })
            .catch(() => {
                productsArea.innerHTML = '<div class="p-4 text-[13px]" style="color: var(--mute);">Failed loading products.</div>';
            });
    });

    productSearch.addEventListener('input', () => {
        const q = productSearch.value.toLowerCase().trim();
        const filtered = currentProducts.filter(p =>
            (p.item_name || '').toLowerCase().includes(q) ||
            (p.item_code || '').toLowerCase().includes(q)
        );
        renderProducts(filtered);
    });

    function renderProducts(list) {
        if (!list.length) {
            productsArea.innerHTML = '<div class="p-4 text-[13px]" style="color: var(--mute);">No products found for this supplier.</div>';
            return;
        }

        let html = `
            <table>
                <thead>
                    <tr>
                        <th>Pick</th>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Discount%</th>
                    </tr>
                </thead>
                <tbody>
        `;

        list.forEach(p => {
            const id = p.id;
            const checked = selectedItems[id] ? 'checked' : '';
            const qtyVal = selectedItems[id] ? selectedItems[id].qty : (p.quantity || 1);
            const discVal = selectedItems[id] ? selectedItems[id].discount : 0;

            html += `
                <tr>
                    <td><input type="checkbox" class="pick-item" data-id="${id}" data-supplier="${escapeHtml(p.supplier_reference)}" ${checked}></td>
                    <td>${escapeHtml(p.item_name || '')}</td>
                    <td class="mono">${escapeHtml(p.item_code || '')}</td>
                    <td>${escapeHtml(p.unit || '')}</td>
                    <td class="mono">${parseFloat(p.unit_price || 0).toFixed(2)} SAR</td>
                    <td><input type="number" min="0" step="0.01" class="item-qty form-input mono num" data-id="${id}" value="${qtyVal}" ${checked ? '' : 'disabled'} style="width: 100px;"></td>
                    <td><input type="number" min="0" step="0.01" class="item-discount form-input mono num" data-id="${id}" value="${discVal}" ${checked ? '' : 'disabled'} style="width: 100px;"></td>
                </tr>
            `;
        });

        html += `</tbody></table>`;
        productsArea.innerHTML = html;

        document.querySelectorAll('.pick-item').forEach(ch => {
            ch.addEventListener('change', (e) => {
                const id = e.target.dataset.id;
                const product = currentProducts.find(x => String(x.id) === String(id));
                const qtyInput = document.querySelector('.item-qty[data-id="' + id + '"]');
                const discInput = document.querySelector('.item-discount[data-id="' + id + '"]');

                if (e.target.checked) {
                    qtyInput.disabled = false;
                    discInput.disabled = false;
                    selectedItems[id] = {
                        product_id: product.id,
                        qty: parseFloat(qtyInput.value) || 1,
                        discount: parseFloat(discInput.value) || 0,
                        unit_price: parseFloat(product.unit_price) || 0
                    };
                } else {
                    qtyInput.disabled = true;
                    discInput.disabled = true;
                    delete selectedItems[id];
                }
                bindItemInputs();
                renderSummary();
            });
        });

        document.querySelectorAll('.item-qty').forEach(input => {
            input.addEventListener('input', (e) => {
                const id = e.target.dataset.id;
                if (selectedItems[id]) {
                    selectedItems[id].qty = parseFloat(e.target.value) || 0;
                    renderSummary();
                }
            });
        });

        document.querySelectorAll('.item-discount').forEach(input => {
            input.addEventListener('input', (e) => {
                const id = e.target.dataset.id;
                if (selectedItems[id]) {
                    selectedItems[id].discount = parseFloat(e.target.value) || 0;
                    renderSummary();
                }
            });
        });

        bindItemInputs();
        renderSummary();
    }

    function bindItemInputs() {
        document.querySelectorAll('input[name^="items"]').forEach(el => el.remove());

        Object.keys(selectedItems).forEach(id => {
            const item = selectedItems[id];
            ['product_id', 'qty', 'discount', 'unit_price'].forEach(key => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = `items[${id}][${key}]`;
                inp.value = item[key];
                orderForm.appendChild(inp);
            });
        });
    }

    function renderSummary() {
        let subtotal = 0;

        Object.values(selectedItems).forEach(item => {
            const qty = parseFloat(item.qty) || 0;
            const unitPrice = parseFloat(item.unit_price) || 0;
            const discount = parseFloat(item.discount) || 0;
            const line = qty * unitPrice;
            const lineAfter = line - (line * (discount / 100));
            subtotal += lineAfter;
        });

        const vat = subtotal * 0.15;
        const total = subtotal + vat;

        document.getElementById('disp_subtotal').innerText = subtotal.toFixed(2) + ' SAR';
        document.getElementById('disp_vat').innerText = vat.toFixed(2) + ' SAR';
        document.getElementById('disp_total').innerText = total.toFixed(2) + ' SAR';
    }

    function updateTotals() {
        bindItemInputs();
        renderSummary();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, function(m) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[m];
        });
    }

    orderForm.addEventListener('submit', function() {
        bindItemInputs();
    });
</script>
</body>
</html>
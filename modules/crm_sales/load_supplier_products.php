<?php
require_once '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$supplier = $_GET['supplier'] ?? '';

if (!$supplier) {
    echo json_encode(['products' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        supplier_reference,
        item_code,
        item_name,
        category,
        quantity,
        unit,
        unit_price,
        currency,
        lead_time_days
    FROM supplier_products
    WHERE supplier_reference = ?
    ORDER BY item_name ASC
");
$stmt->execute([$supplier]);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['products' => $products], JSON_UNESCAPED_UNICODE);
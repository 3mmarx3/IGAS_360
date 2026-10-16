<?php
declare(strict_types=1);

/**
 * TEST SUITE: ROW-LEVEL LISTING ISOLATION FOR SALES REPS
 */

$testDb = __DIR__ . '/test_crm.sqlite';
putenv('DB_DRIVER=sqlite');
putenv("DB_SQLITE_PATH={$testDb}");

$pdo = new PDO("sqlite:{$testDb}", null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$results = [];

function record_test(string $name, bool $passed, string $details = ''): void {
    global $results;
    $results[] = [
        'name' => $name,
        'passed' => $passed,
        'details' => $details
    ];
    $status = $passed ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
    echo "{$status} {$name}\n";
    if (!empty($details)) {
        echo "       Details: {$details}\n";
    }
}

echo "=== RUNNING TEST SUITE: ROW-LEVEL LISTINGS ISOLATION ===\n";

// 1. Test clients_directory.php for Sales Rep 3
$repClientsOut = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testDb . '");
    chdir("modules/crm_sales");
    session_start();
    $_SESSION["account_type"] = "staff";
    $_SESSION["user_id"] = 3; // Ahmed
    $_SESSION["role"] = "sales_rep";
    ob_start();
    include "clients_directory.php";
    $content = ob_get_clean();
    echo "HAS_CLIENT_1=" . (str_contains($content, "CLI-101") ? "YES" : "NO") . ";";
    echo "HAS_CLIENT_2=" . (str_contains($content, "CLI-102") ? "YES" : "NO") . ";";
\' 2>&1');

$repClientsPass = str_contains((string)$repClientsOut, 'HAS_CLIENT_1=YES') && str_contains((string)$repClientsOut, 'HAS_CLIENT_2=NO');
record_test('Sales Rep 3 sees only assigned Client 1, Client 2 is isolated in clients_directory.php', $repClientsPass, "Output: " . trim((string)$repClientsOut));

// 2. Test quotations.php for Sales Rep 3
$repQuotesOut = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testDb . '");
    chdir("modules/crm_sales");
    session_start();
    $_SESSION["account_type"] = "staff";
    $_SESSION["user_id"] = 3; // Ahmed
    $_SESSION["role"] = "sales_rep";
    ob_start();
    include "quotations.php";
    $content = ob_get_clean();
    echo "HAS_QUOTE_1=" . (str_contains($content, "QUO-3001") ? "YES" : "NO") . ";";
    echo "HAS_QUOTE_2=" . (str_contains($content, "QUO-4001") ? "YES" : "NO") . ";";
\' 2>&1');

$repQuotesPass = str_contains((string)$repQuotesOut, 'HAS_QUOTE_1=YES') && str_contains((string)$repQuotesOut, 'HAS_QUOTE_2=NO');
record_test('Sales Rep 3 sees only assigned Quotation 1, Quotation 2 is isolated in quotations.php', $repQuotesPass, "Output: " . trim((string)$repQuotesOut));

// 3. Test purchase_orders.php for Sales Rep 3
$repOrdersOut = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testDb . '");
    chdir("modules/crm_sales");
    session_start();
    $_SESSION["account_type"] = "staff";
    $_SESSION["user_id"] = 3; // Ahmed
    $_SESSION["role"] = "sales_rep";
    ob_start();
    include "purchase_orders.php";
    $content = ob_get_clean();
    echo "HAS_ORDER_1=" . (str_contains($content, "PO-3001") ? "YES" : "NO") . ";";
    echo "HAS_ORDER_2=" . (str_contains($content, "PO-4001") ? "YES" : "NO") . ";";
\' 2>&1');

$repOrdersPass = str_contains((string)$repOrdersOut, 'HAS_ORDER_1=YES') && str_contains((string)$repOrdersOut, 'HAS_ORDER_2=NO');
record_test('Sales Rep 3 sees only assigned Order 1, Order 2 is isolated in purchase_orders.php', $repOrdersPass, "Output: " . trim((string)$repOrdersOut));

// 4. Test collections_invoices.php for Sales Rep 3
$repInvoicesOut = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testDb . '");
    chdir("modules/crm_sales");
    session_start();
    $_SESSION["account_type"] = "staff";
    $_SESSION["user_id"] = 3; // Ahmed
    $_SESSION["role"] = "sales_rep";
    ob_start();
    include "collections_invoices.php";
    $content = ob_get_clean();
    echo "HAS_INV_1=" . (str_contains($content, "INV-3001") ? "YES" : "NO") . ";";
    echo "HAS_INV_2=" . (str_contains($content, "INV-4001") ? "YES" : "NO") . ";";
\' 2>&1');

$repInvoicesPass = str_contains((string)$repInvoicesOut, 'HAS_INV_1=YES') && str_contains((string)$repInvoicesOut, 'HAS_INV_2=NO');
record_test('Sales Rep 3 sees only assigned Invoice 1, Invoice 2 is isolated in collections_invoices.php', $repInvoicesPass, "Output: " . trim((string)$repInvoicesOut));

// 5. Test Admin sees ALL records across all sales reps
$adminOut = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testDb . '");
    chdir("modules/crm_sales");
    session_start();
    $_SESSION["account_type"] = "staff";
    $_SESSION["user_id"] = 1;
    $_SESSION["role"] = "admin";
    ob_start();
    include "clients_directory.php";
    $c = ob_get_clean();
    ob_start();
    include "quotations.php";
    $q = ob_get_clean();
    echo "ADMIN_CLIENTS=" . (str_contains($c, "CLI-101") && str_contains($c, "CLI-102") ? "ALL" : "PARTIAL") . ";";
    echo "ADMIN_QUOTES=" . (str_contains($q, "QUO-3001") && str_contains($q, "QUO-4001") ? "ALL" : "PARTIAL") . ";";
\' 2>&1');

$adminPass = str_contains((string)$adminOut, 'ADMIN_CLIENTS=ALL') && str_contains((string)$adminOut, 'ADMIN_QUOTES=ALL');
record_test('Admin sees all clients and quotations across all sales reps', $adminPass, "Output: " . trim((string)$adminOut));

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nRow-Level Listings Summary: {$passCount}/{$totalCount} passed.\n";

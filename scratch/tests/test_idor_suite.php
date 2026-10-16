<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 2: RECORD OWNERSHIP & IDOR ISOLATION
 */

$testDb = __DIR__ . '/test_crm.sqlite';
putenv('DB_DRIVER=sqlite');
putenv("DB_SQLITE_PATH={$testDb}");

$pdo = new PDO("sqlite:{$testDb}", null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

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

echo "=== RUNNING TEST SUITE 2: RECORD OWNERSHIP & IDOR ISOLATION ===\n";

// Test 1: Admin can access any client
$_SESSION['account_type'] = 'staff';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin'; // Note: check what happens with lowercase 'admin'

$adminCanAccessClient1 = check_record_ownership($pdo, 'client_profile', 1);
$adminCanAccessClient2 = check_record_ownership($pdo, 'client_profile', 2);
record_test('Admin access check to Client 1 (should be allowed)', $adminCanAccessClient1, "Result: " . var_export($adminCanAccessClient1, true));
record_test('Admin access check to Client 2 (should be allowed)', $adminCanAccessClient2, "Result: " . var_export($adminCanAccessClient2, true));

// Test 2: Sales Rep Ahmed (User 3) accessing his OWN records
$_SESSION['account_type'] = 'staff';
$_SESSION['user_id'] = 3;
$_SESSION['role'] = 'sales_rep';

$rep3OwnClient = check_record_ownership($pdo, 'client_profile', 1);
$rep3OwnQuote = check_record_ownership($pdo, 'quotations', 1);
$rep3OwnOrder = check_record_ownership($pdo, 'orders', 1);
record_test('Sales Rep 3 accesses own Client 1 (should be allowed)', $rep3OwnClient, "Result: " . var_export($rep3OwnClient, true));
record_test('Sales Rep 3 accesses own Quotation 1 (should be allowed)', $rep3OwnQuote, "Result: " . var_export($rep3OwnQuote, true));
record_test('Sales Rep 3 accesses own Order 1 (should be allowed)', $rep3OwnOrder, "Result: " . var_export($rep3OwnOrder, true));

// Test 3: Sales Rep Ahmed (User 3) attempting IDOR against Sales Rep Fahad (User 4) records
$rep3AccessClient2 = check_record_ownership($pdo, 'client_profile', 2);
$rep3AccessQuote2 = check_record_ownership($pdo, 'quotations', 2);
$rep3AccessOrder2 = check_record_ownership($pdo, 'orders', 2);
record_test('IDOR: Sales Rep 3 blocked from accessing Client 2', !$rep3AccessClient2, "Blocked: " . var_export(!$rep3AccessClient2, true) . ", raw return: " . var_export($rep3AccessClient2, true));
record_test('IDOR: Sales Rep 3 blocked from accessing Quotation 2', !$rep3AccessQuote2, "Blocked: " . var_export(!$rep3AccessQuote2, true) . ", raw return: " . var_export($rep3AccessQuote2, true));
record_test('IDOR: Sales Rep 3 blocked from accessing Order 2', !$rep3AccessOrder2, "Blocked: " . var_export(!$rep3AccessOrder2, true) . ", raw return: " . var_export($rep3AccessOrder2, true));

// Test 4: External Partner IDOR attempt against other accounts
$_SESSION['account_type'] = 'partner';
$_SESSION['user_id'] = 10; // Partner Corp
$_SESSION['reference_id'] = 'CLI-PARTNER';

$partnerAccessOwnQuote = check_record_ownership($pdo, 'quotations', 1); // Client id 1 is NOT partner id 10
$partnerAccessInvoice2 = check_record_ownership($pdo, 'invoices', 2);  // Client id 2 is NOT partner id 10
record_test('IDOR: External Partner blocked from Quotation 1', !$partnerAccessOwnQuote, "Blocked: " . var_export(!$partnerAccessOwnQuote, true));
record_test('IDOR: External Partner blocked from Invoice 2', !$partnerAccessInvoice2, "Blocked: " . var_export(!$partnerAccessInvoice2, true));

// Test 5: Verify logistics_fleet/order_details.php IDOR vulnerability (missing check_record_ownership)
$logisticsFile = file_get_contents(dirname(__DIR__, 2) . '/modules/logistics_fleet/order_details.php');
$hasLogisticsIdorCheck = str_contains($logisticsFile, 'check_record_ownership') || str_contains($logisticsFile, 'require_client_access');
record_test('Logistics Fleet order_details.php enforces IDOR record ownership', $hasLogisticsIdorCheck, "Contains IDOR check: " . var_export($hasLogisticsIdorCheck, true));

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 2 Summary: {$passCount}/{$totalCount} passed.\n";

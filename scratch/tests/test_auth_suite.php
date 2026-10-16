<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 1: AUTHENTICATION & AUTHORIZATION
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

// 1. Test unauthenticated request to includes/auth.php require_auth()
function test_unauthenticated_require_auth(): void {
    // Run isolated PHP CLI script simulating unauthenticated request to require_auth
    $output = shell_exec('php -r \'
        $_SERVER["SCRIPT_NAME"] = "/modules/crm_sales/clients_directory.php";
        require_once "includes/auth.php";
        // Attempt require_auth
        ob_start();
        require_auth();
        $out = ob_get_clean();
        echo "DID_NOT_EXIT";
    \' 2>&1');

    // In CLI, redirect() sends header and exits. If it exits without printing DID_NOT_EXIT, it redirected.
    $passed = !str_contains((string)$output, 'DID_NOT_EXIT');
    record_test('Unauthenticated user is blocked by require_auth()', $passed, "Output: " . trim((string)$output));
}

// 2. Test AJAX unauthenticated returns 401 JSON
function test_unauthenticated_ajax(): void {
    $output = shell_exec('php -r \'
        $_SERVER["HTTP_X_REQUESTED_WITH"] = "xmlhttprequest";
        require_once "includes/auth.php";
        require_auth();
    \' 2>&1');

    $isJson401 = str_contains((string)$output, 'Authentication required');
    record_test('Unauthenticated AJAX request receives 401 JSON error', $isJson401, "Output: " . trim((string)$output));
}

// 3. Test suspended staff account denial
function test_suspended_staff_account(): void {
    $testDb = __DIR__ . '/test_crm.sqlite';
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . $testDb . '");
        require_once "config/db.php";
        require_once "includes/auth.php";
        $_SESSION["account_type"] = "staff";
        $_SESSION["user_id"] = 5; // Suspended Staff in test_crm.sqlite
        $_SESSION["role"] = "sales_rep";

        $status = refresh_user_status($pdo);
        echo $status ? "ACTIVE" : "SUSPENDED_DESTROYED";
    \' 2>&1');

    $passed = str_contains((string)$output, 'SUSPENDED_DESTROYED');
    record_test('Suspended staff account session is destroyed by refresh_user_status()', $passed, "Output: " . trim((string)$output));
}

// 4. Test rep_dashboard.php backdoor / session injection
function test_rep_dashboard_backdoor(): void {
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . __DIR__ . '/test_crm.sqlite");
        require_once "core/init.php";
        // Simulate visiting rep_dashboard unauthenticated
        $_SERVER["SCRIPT_NAME"] = "/rep_dashboard.php";
        ob_start();
        include "rep_dashboard.php";
        ob_end_clean();
        echo "SESSION_USER_ID=" . ($_SESSION["user_id"] ?? "NONE") . ";SESSION_ROLE=" . ($_SESSION["role"] ?? "NONE");
    \' 2>&1');

    $injected = str_contains((string)$output, 'SESSION_USER_ID=2') && str_contains((string)$output, 'SESSION_ROLE=sales_rep');
    record_test('Vulnerability Check: rep_dashboard.php automatically creates unauthorized session', !$injected, "Output: " . trim((string)$output));
}

// 5. Test rep_dashboard.php ?switch_user=1 privilege escalation
function test_rep_dashboard_privilege_escalation(): void {
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . __DIR__ . '/test_crm.sqlite");
        $_GET["switch_user"] = "1";
        $_SERVER["SCRIPT_NAME"] = "/rep_dashboard.php";
        ob_start();
        include "rep_dashboard.php";
        ob_end_clean();
        echo "SESSION_USER_ID=" . ($_SESSION["user_id"] ?? "NONE") . ";SESSION_ROLE=" . ($_SESSION["role"] ?? "NONE");
    \' 2>&1');

    $elevated = str_contains((string)$output, 'SESSION_USER_ID=1') && str_contains((string)$output, 'SESSION_ROLE=admin');
    record_test('Vulnerability Check: rep_dashboard.php allows unauthenticated privilege escalation to admin via switch_user=1', !$elevated, "Output: " . trim((string)$output));
}

// 6. Test logistics_fleet/order_details.php missing auth
function test_logistics_order_details_auth(): void {
    $testDb = __DIR__ . '/test_crm.sqlite';
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . $testDb . '");
        $_GET["order_num"] = "PO-3001";
        chdir("modules/logistics_fleet");
        ob_start();
        include "order_details.php";
        $content = ob_get_clean();
        echo str_contains($content, "PO-3001") ? "UNAUTHENTICATED_ACCESS_ALLOWED" : "BLOCKED";
    \' 2>&1');

    $allowed = str_contains((string)$output, 'UNAUTHENTICATED_ACCESS_ALLOWED');
    record_test('Vulnerability Check: modules/logistics_fleet/order_details.php accessible unauthenticated', !$allowed, "Output: " . trim((string)$output));
}

// 7. Test logistics_fleet/new_log_entry.php missing auth
function test_logistics_new_log_entry_auth(): void {
    $testDb = __DIR__ . '/test_crm.sqlite';
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . $testDb . '");
        chdir("modules/logistics_fleet");
        ob_start();
        include "new_log_entry.php";
        $content = ob_get_clean();
        echo str_contains($content, "Manual Log Entry") ? "UNAUTHENTICATED_ACCESS_ALLOWED" : "BLOCKED";
    \' 2>&1');

    $allowed = str_contains((string)$output, 'UNAUTHENTICATED_ACCESS_ALLOWED');
    record_test('Vulnerability Check: modules/logistics_fleet/new_log_entry.php accessible unauthenticated', !$allowed, "Output: " . trim((string)$output));
}

// 8. Test load_supplier_products.php undefined is_logged_in() function
function test_load_supplier_products(): void {
    $testDb = __DIR__ . '/test_crm.sqlite';
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . $testDb . '");
        chdir("modules/crm_sales");
        include "load_supplier_products.php";
    \' 2>&1');

    $hasFatal = str_contains((string)$output, 'Call to undefined function is_logged_in()');
    record_test('Defect Check: modules/crm_sales/load_supplier_products.php crashes with undefined is_logged_in()', !$hasFatal, "Output: " . trim((string)$output));
}

echo "=== RUNNING TEST SUITE 1: AUTHENTICATION & AUTHORIZATION ===\n";
test_unauthenticated_require_auth();
test_unauthenticated_ajax();
test_suspended_staff_account();
test_rep_dashboard_backdoor();
test_rep_dashboard_privilege_escalation();
test_logistics_order_details_auth();
test_logistics_new_log_entry_auth();
test_load_supplier_products();

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 1 Summary: {$passCount}/{$totalCount} passed.\n";

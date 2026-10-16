<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 6: FUNCTIONAL REGRESSION & RBAC LOGIC
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

echo "=== RUNNING TEST SUITE 6: FUNCTIONAL REGRESSION & RBAC LOGIC ===\n";

// 1. Test has_role signature compatibility with $pdo as first argument
function test_has_role_polymorphism(PDO $pdo): void {
    $_SESSION['account_type'] = 'staff';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';

    $passed = false;
    $error = '';
    try {
        // Many files call has_role($pdo, 'Admin') or has_role($pdo, 'sales_rep')
        $res = has_role($pdo, 'Admin');
        $passed = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    record_test('has_role($pdo, role) polymorphic support without TypeError', $passed, $error ?: 'Executed successfully');
}

// 2. Test has_permission for Admin role
function test_admin_permissions(PDO $pdo): void {
    $_SESSION['account_type'] = 'staff';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';

    $canUsers = has_permission($pdo, 'users.manage');
    $canQuotes = has_permission($pdo, 'quotes.create');
    $canDelete = has_permission($pdo, 'clients.delete');

    $allPassed = $canUsers && $canQuotes && $canDelete;
    record_test('Admin role has global permission grant', $allPassed, "users.manage: {$canUsers}, quotes.create: {$canQuotes}");
}

// 3. Test has_permission for Sales Rep role
function test_sales_rep_permissions(PDO $pdo): void {
    $_SESSION['account_type'] = 'staff';
    $_SESSION['user_id'] = 3;
    $_SESSION['role'] = 'sales_rep';

    $canViewClients = has_permission($pdo, 'clients.view');
    $cannotManageUsers = !has_permission($pdo, 'users.manage');
    $cannotDeleteLogistics = !has_permission($pdo, 'logistics.delete');

    $passed = $canViewClients && $cannotManageUsers && $cannotDeleteLogistics;
    record_test('Sales Rep role has bounded permissions (grants CRM, denies Admin/Logistics)', $passed, "clients.view={$canViewClients}, users.manage={$cannotManageUsers}, logistics.delete={$cannotDeleteLogistics}");
}

// 4. Test URL routing helper
function test_url_helpers(): void {
    $_SERVER['SCRIPT_NAME'] = '/modules/crm_sales/clients_directory.php';
    $url = app_url('index.php');
    $passed = str_ends_with($url, '/index.php');
    record_test('app_url() resolves correct portable relative path', $passed, "Resolved: {$url}");
}

// 5. Test sidebar menu inclusion does not crash with TypeError
function test_sidebar_render(PDO $pdo): void {
    $_SESSION['account_type'] = 'staff';
    $_SESSION['user_id'] = 2;
    $_SESSION['role'] = 'sales_rep';
    $_SESSION['user_name'] = 'Tariq Mansoor';

    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . __DIR__ . '/test_crm.sqlite");
        require_once "config/db.php";
        require_once "includes/auth.php";
        $_SESSION["account_type"] = "staff";
        $_SESSION["user_id"] = 2;
        $_SESSION["role"] = "sales_rep";
        $_SESSION["user_name"] = "Tariq Mansoor";
        ob_start();
        include "includes/aside.php";
        $content = ob_get_clean();
        echo str_contains($content, "I-GAS") ? "SIDEBAR_RENDERED" : "SIDEBAR_FAILED";
    \' 2>&1');

    $passed = str_contains((string)$output, 'SIDEBAR_RENDERED');
    record_test('includes/aside.php renders navigation sidebar without fatal error', $passed, "Output: " . trim((string)$output));
}

test_has_role_polymorphism($pdo);
test_admin_permissions($pdo);
test_sales_rep_permissions($pdo);
test_url_helpers();
test_sidebar_render($pdo);

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 6 Summary: {$passCount}/{$totalCount} passed.\n";

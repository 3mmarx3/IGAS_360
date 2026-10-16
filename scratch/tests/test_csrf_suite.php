<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 3: CSRF & REQUEST METHOD ENFORCEMENT
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

echo "=== RUNNING TEST SUITE 3: CSRF & REQUEST METHOD ENFORCEMENT ===\n";

// 1. Missing CSRF Token on POST is rejected with 403
function test_missing_csrf(): void {
    $output = shell_exec('php -r \'
        require_once "includes/auth.php";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_SESSION["csrf_token"] = "valid_token_123";
        unset($_POST["csrf_token"]);
        ob_start();
        require_csrf();
        $out = ob_get_clean();
        echo "ALLOWED";
    \' 2>&1');

    $blocked = !str_contains((string)$output, 'ALLOWED');
    record_test('POST request without CSRF token is rejected with 403', $blocked, "Output: " . trim((string)$output));
}

// 2. Invalid CSRF Token on POST is rejected with 403
function test_invalid_csrf(): void {
    $output = shell_exec('php -r \'
        require_once "includes/auth.php";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_SESSION["csrf_token"] = "valid_token_123";
        $_POST["csrf_token"] = "tampered_token_999";
        ob_start();
        require_csrf();
        $out = ob_get_clean();
        echo "ALLOWED";
    \' 2>&1');

    $blocked = !str_contains((string)$output, 'ALLOWED');
    record_test('POST request with tampered CSRF token is rejected with 403', $blocked, "Output: " . trim((string)$output));
}

// 3. Valid CSRF Token on POST succeeds
function test_valid_csrf(): void {
    $output = shell_exec('php -r \'
        require_once "includes/auth.php";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_SESSION["csrf_token"] = "valid_token_123";
        $_POST["csrf_token"] = "valid_token_123";
        ob_start();
        require_csrf();
        $out = ob_get_clean();
        echo "PASSED";
    \' 2>&1');

    $passed = str_contains((string)$output, 'PASSED');
    record_test('POST request with valid CSRF token is accepted', $passed, "Output: " . trim((string)$output));
}

// 4. AJAX POST without CSRF returns 403 JSON error
function test_ajax_csrf_json(): void {
    $output = shell_exec('php -r \'
        require_once "includes/auth.php";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_SERVER["HTTP_X_REQUESTED_WITH"] = "xmlhttprequest";
        $_SESSION["csrf_token"] = "valid_token_123";
        $_POST["csrf_token"] = "bad";
        ob_start();
        require_csrf();
        $out = ob_get_clean();
        echo $out;
    \' 2>&1');

    $isJson403 = str_contains((string)$output, 'Invalid or missing CSRF token');
    record_test('AJAX POST request without valid CSRF returns JSON error', $isJson403, "Output: " . trim((string)$output));
}

// 5. Test logistics_fleet/new_log_entry.php rejects POST without CSRF token
function test_logistics_new_log_csrf(): void {
    $testDb = __DIR__ . '/test_crm.sqlite';
    $output = shell_exec('php -r \'
        putenv("DB_DRIVER=sqlite");
        putenv("DB_SQLITE_PATH=' . $testDb . '");
        session_start();
        $_SESSION["account_type"] = "staff";
        $_SESSION["user_id"] = 1;
        $_SESSION["role"] = "admin";
        $_SESSION["csrf_token"] = "valid_token";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_POST["vehicle_id"] = "FL-01";
        $_POST["event_type"] = "BlockedLog";
        chdir("modules/logistics_fleet");
        include "new_log_entry.php";
    \' 2>&1');

    $pdo = new PDO("sqlite:{$testDb}");
    $count = (int)$pdo->query("SELECT COUNT(*) FROM vehicle_logs WHERE event_type = 'BlockedLog'")->fetchColumn();
    $rejected = ($count === 0) && str_contains((string)$output, 'CSRF Validation Failed');
    record_test('modules/logistics_fleet/new_log_entry.php rejects POST without CSRF token', $rejected, "Blocked rows: {$count}, Output contains 403: " . var_export(str_contains((string)$output, 'CSRF Validation Failed'), true));
}

test_missing_csrf();
test_invalid_csrf();
test_valid_csrf();
test_ajax_csrf_json();
test_logistics_new_log_csrf();

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 3 Summary: {$passCount}/{$totalCount} passed.\n";

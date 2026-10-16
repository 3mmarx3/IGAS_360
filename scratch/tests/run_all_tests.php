<?php
declare(strict_types=1);

echo "======================================================================\n";
echo "I-GAS ENTERPRISE — MASTER VERIFICATION TEST RUNNER\n";
echo "======================================================================\n\n";

$suites = [
    'Suite 1: Authentication & Authorization' => __DIR__ . '/test_auth_suite.php',
    'Suite 2: Record Ownership & IDOR'       => __DIR__ . '/test_idor_suite.php',
    'Suite 3: CSRF & Request Methods'        => __DIR__ . '/test_csrf_suite.php',
    'Suite 4: File Upload Security'          => __DIR__ . '/test_upload_suite.php',
    'Suite 5: Database & Migration Safety'   => __DIR__ . '/test_database_suite.php',
    'Suite 6: Functional & RBAC Logic'       => __DIR__ . '/test_functional_suite.php',
    'Suite 7: Row-Level Listings Isolation'  => __DIR__ . '/test_row_level_listings.php'
];

$totalSuites = count($suites);
$passedSuites = 0;

foreach ($suites as $name => $path) {
    echo ">>> Running {$name}...\n";
    $output = shell_exec("php " . escapeshellarg($path) . " 2>&1");
    echo $output . "\n";
    if (str_contains((string)$output, '[FAIL]')) {
        echo "\033[31m[-] Suite {$name} encountered failures!\033[0m\n\n";
    } else {
        echo "\033[32m[+] Suite {$name} passed 100%!\033[0m\n\n";
        $passedSuites++;
    }
}

echo "======================================================================\n";
echo "FINAL TEST SUMMARY: {$passedSuites}/{$totalSuites} Test Suites Passed 100%\n";
echo "======================================================================\n";

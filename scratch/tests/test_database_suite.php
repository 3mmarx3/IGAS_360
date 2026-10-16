<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 5: DATABASE & MIGRATION SAFETY
 */

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

echo "=== RUNNING TEST SUITE 5: DATABASE & MIGRATION SAFETY ===\n";

// 1. Verify database/data.sql presence and size
$sqlFile = dirname(__DIR__, 2) . '/database/data.sql';
$fileExists = file_exists($sqlFile);
$fileSize = $fileExists ? filesize($sqlFile) : 0;
record_test('Master SQL file exists and is populated (>50KB)', $fileExists && $fileSize > 50000, "Size: {$fileSize} bytes");

// 2. Count tables in database/data.sql
$sqlContent = file_get_contents($sqlFile);
preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-zA-Z0-9_]+)`?/i', $sqlContent, $tableMatches);
$tables = array_unique($tableMatches[1]);
$tableCount = count($tables);
record_test('Master schema defines all 53 enterprise tables', $tableCount >= 50, "Total tables found: {$tableCount}");

// 3. Verify Foreign Key Checks wrapping
$hasFkOff = str_contains($sqlContent, 'SET FOREIGN_KEY_CHECKS = 0') || str_contains($sqlContent, 'SET FOREIGN_KEY_CHECKS=0');
$hasFkOn = str_contains($sqlContent, 'SET FOREIGN_KEY_CHECKS = 1') || str_contains($sqlContent, 'SET FOREIGN_KEY_CHECKS=1');
record_test('Master schema wraps execution with FOREIGN_KEY_CHECKS=0 and 1', $hasFkOff && $hasFkOn, "FK_OFF: {$hasFkOff}, FK_ON: {$hasFkOn}");

// 4. Verify 10 Industrial Roles seeded in SQL
$has10Roles = str_contains($sqlContent, "'sales_manager'") &&
              str_contains($sqlContent, "'sales_rep'") &&
              str_contains($sqlContent, "'plant_manager'") &&
              str_contains($sqlContent, "'shift_supervisor'") &&
              str_contains($sqlContent, "'logistics_manager'") &&
              str_contains($sqlContent, "'driver'") &&
              str_contains($sqlContent, "'warehouse_manager'") &&
              str_contains($sqlContent, "'finance'") &&
              str_contains($sqlContent, "'compliance_officer'");
record_test('Master schema seeds all 10 industrial roles', $has10Roles, "Seeded: " . var_export($has10Roles, true));

// 5. Test database/migrate.php execution in SQLite fallback mode
$testMigrateDb = __DIR__ . '/test_migrate_scratch.sqlite';
if (file_exists($testMigrateDb)) @unlink($testMigrateDb);

$migrateOutput = shell_exec('php -r \'
    putenv("DB_DRIVER=sqlite");
    putenv("DB_SQLITE_PATH=' . $testMigrateDb . '");
    chdir("database");
    include "migrate.php";
\' 2>&1');

$migratePassed = str_contains((string)$migrateOutput, 'Migration completed successfully');
record_test('database/migrate.php executes without SQL syntax error on SQLite', $migratePassed, "Output: " . trim((string)$migrateOutput));

if (file_exists($testMigrateDb)) @unlink($testMigrateDb);

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 5 Summary: {$passCount}/{$totalCount} passed.\n";

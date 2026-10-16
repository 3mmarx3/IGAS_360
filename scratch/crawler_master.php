<?php
/**
 * Master System Crawler & QA Tester
 * Iterates through all 98 pages, executes them via run_single_page.php, and compiles report
 */

$files = glob("modules/*/*.php");
$files = array_merge($files, ["index.php", "auth/login.php", "auth/logout.php", "rep_dashboard.php"]);
sort($files);

// Query parameter heuristics for detail/edit pages
$defaultParams = [
    'modules/crm_sales/client_profile.php' => 'id=1',
    'modules/crm_sales/edit_client.php' => 'id=1',
    'modules/crm_sales/client_activity.php' => 'id=1',
    'modules/crm_sales/client_transactions.php' => 'id=1',
    'modules/crm_sales/contract_details.php' => 'id=1',
    'modules/crm_sales/quotation_details.php' => 'id=1',
    'modules/crm_sales/view_quotation.php' => 'id=1',
    'modules/crm_sales/view_order.php' => 'id=1',
    'modules/crm_sales/order_details.php' => 'order_num=PO-3001',
    'modules/crm_sales/view_invoice.php' => 'id=1',
    'modules/crm_sales/rep_profile.php' => 'id=3',
    'modules/crm_sales/renew_contract.php' => 'id=1',
    'modules/logistics_fleet/driver_profile.php' => 'driver_id=DRV-01',
    'modules/logistics_fleet/edit_driver.php' => 'driver_id=DRV-01',
    'modules/logistics_fleet/dispatch_details.php' => 'id=1',
    'modules/logistics_fleet/maintenance_details.php' => 'id=1',
    'modules/logistics_fleet/order_details.php' => 'order_num=PO-3001',
    'modules/main/partner_profile.php' => 'id=1',
    'modules/production/cylinder_details.php' => 'id=1',
    'modules/production/gas_details.php' => 'id=1',
    'modules/production/liquid_profile.php' => 'id=1',
    'modules/production/material_details.php' => 'id=1',
    'modules/production/supplier_profile.php' => 'id=1',
    'modules/production/edit_supplier.php' => 'id=1',
    'modules/production/shift_details.php' => 'id=1',
    'modules/production/tank_details.php' => 'id=1',
    'modules/production/view_order.php' => 'id=1',
    'modules/production/yield_details.php' => 'id=1',
    'modules/warehouse/warehouse_profile.php' => 'id=1'
];

echo "======================================================================\n";
echo "I-GAS ENTERPRISE CRM — AUTOMATED SYSTEM CRAWLER & PAGE AUDITOR\n";
echo "Crawling " . count($files) . " application pages with Super Admin Session...\n";
echo "======================================================================\n\n";

$results = [];
$failures = [];
$warningsCount = 0;

foreach ($files as $idx => $file) {
    $param = $defaultParams[$file] ?? '';
    $cmd = sprintf("php scratch/run_single_page.php %s %s 2>&1", escapeshellarg($file), escapeshellarg($param));
    $rawOutput = shell_exec($cmd);

    // Extract JSON payload
    if (preg_match('/__CRAWLER_RESULT__(.*?)__END_CRAWLER_RESULT__/s', $rawOutput, $m)) {
        $data = json_decode(trim($m[1]), true);
    } else {
        $data = [
            'file' => $file,
            'status' => 'crash',
            'http_code' => 500,
            'exception' => ['message' => 'Process crashed without JSON output', 'raw' => substr($rawOutput, 0, 500)],
            'errors' => [],
            'output_len' => 0
        ];
    }

    $errCount = count($data['errors'] ?? []);
    if ($data['exception']) $errCount++;
    
    $isLogoutRedirect = ($file === 'auth/logout.php' && $data['http_code'] === 302 && $errCount === 0);
    $isClean = (($data['status'] === 'ok' && $data['http_code'] < 400 && $errCount === 0) || $isLogoutRedirect);
    $statusIcon = $isClean ? '[OK]' : '[FAIL]';
    
    $warningsCount += count($data['errors'] ?? []);
    $results[$file] = $data;

    if (!$isClean) {
        $failures[$file] = $data;
        echo sprintf("%-4s (%2d/%2d) %-50s HTTP: %-3d Len: %-7d Errs: %d\n", 
            $statusIcon, $idx + 1, count($files), $file, $data['http_code'], $data['output_len'] ?? 0, $errCount
        );
        if (!empty($data['exception'])) {
            $exType = $data['exception']['type'] ?? 'Error';
            $exMsg  = $data['exception']['message'] ?? 'Unknown exception';
            $exFile = $data['exception']['file'] ?? 'unknown';
            $exLine = $data['exception']['line'] ?? 0;
            echo "     --> Exception: [{$exType}] {$exMsg} in {$exFile}:{$exLine}\n";
        }
        foreach (array_slice($data['errors'] ?? [], 0, 3) as $err) {
            echo "     --> {$err['type']}: {$err['message']} in {$err['file']}:{$err['line']}\n";
        }
    } else {
        echo sprintf("%-4s (%2d/%2d) %-50s HTTP: %-3d Len: %-7d\n", 
            $statusIcon, $idx + 1, count($files), $file, $data['http_code'], $data['output_len'] ?? 0
        );
    }
}

echo "\n======================================================================\n";
echo "CRAWL SUMMARY:\n";
echo "Total Pages Scanned:  " . count($files) . "\n";
echo "Clean Pages (0 Errs): " . (count($files) - count($failures)) . "\n";
echo "Pages with Issues:    " . count($failures) . "\n";
echo "Total Warnings/Notices: {$warningsCount}\n";
echo "======================================================================\n";

file_put_contents('scratch/crawler_results.json', json_encode($results, JSON_PRETTY_PRINT));

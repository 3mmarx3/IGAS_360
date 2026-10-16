<?php
/**
 * scratch/fix_remaining_nowrap_and_borders.php
 */

$root = dirname(__DIR__);

// 1. Fix date cells & status pills missing whitespace-nowrap
$files = [
    $root . '/modules/crm_sales/client_profile.php',
    $root . '/modules/crm_sales/client_transactions.php',
    $root . '/modules/crm_sales/sales_reports.php',
    $root . '/modules/insights/team_access.php',
    $root . '/modules/logistics_fleet/licenses_alerts.php',
    $root . '/modules/production/cost_profit.php',
    $root . '/modules/production/customer_tanks.php',
    $root . '/modules/production/cylinders_inventory.php',
    $root . '/modules/production/daily_log_shifts.php',
    $root . '/modules/production/gases_mixtures.php',
    $root . '/modules/production/liquid_profile.php',
    $root . '/modules/production/monthly_yearly_production.php',
    $root . '/modules/production/production_reports.php',
    $root . '/modules/production/raw_materials.php',
    $root . '/modules/production/suppliers_directory.php',
    $root . '/modules/production/tank_details.php',
    $root . '/modules/production/yield_loss_reports.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $orig = $content;

    // Add whitespace-nowrap to date td
    $content = preg_replace_callback('/<td([^>]*class=["\'][^"\']*)>/i', function($m) {
        $td = $m[1];
        if (strpos($td, 'whitespace-nowrap') === false) {
            return '<td' . str_replace('class="', 'class="whitespace-nowrap ', str_replace("class='", "class='whitespace-nowrap ", $td)) . '>';
        }
        return $m[0];
    }, $content);

    // Add whitespace-nowrap to inline-flex status badges
    $content = preg_replace_callback('/class=["\']([^"\']*\binline-flex items-center gap-1\.5\b[^"\']*)["\']/', function($m) {
        $cls = $m[1];
        if (strpos($cls, 'whitespace-nowrap') === false) {
            return 'class="' . $cls . ' whitespace-nowrap"';
        }
        return $m[0];
    }, $content);

    if ($content !== $orig) {
        file_put_contents($file, $content);
        echo "Updated nowrap in " . basename($file) . "\n";
    }
}

// 2. Add border-s-4 border-s-[#f97316] to remaining KPI cards
$kpiFiles = [
    $root . '/modules/crm_sales/sales_performance.php',
    $root . '/modules/logistics_fleet/maintenance_details.php',
    $root . '/modules/logistics_fleet/order_details.php',
    $root . '/modules/main/partner_profile.php',
    $root . '/modules/production/liquid_tracking.php',
    $root . '/modules/production/new_mixture.php',
    $root . '/modules/production/new_purchase_order.php',
    $root . '/modules/production/new_shift_log.php',
    $root . '/modules/production/view_order.php',
    $root . '/modules/tools/gas_converter.php',
    $root . '/modules/tools/unit_converter.php',
];

foreach ($kpiFiles as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $orig = $content;

    // Pattern for top cards
    $content = preg_replace_callback('/<div class="([^"]*bg-\[#0c1924\][^"]*border border-\[#1a3346\][^"]*)">/', function($m) {
        $cls = $m[1];
        if (strpos($cls, 'border-l-') === false && strpos($cls, 'border-s-') === false) {
            return '<div class="' . $cls . ' border-s-4 border-s-[#f97316]">';
        }
        return $m[0];
    }, $content);

    if ($content !== $orig) {
        file_put_contents($file, $content);
        echo "Updated KPI borders in " . basename($file) . "\n";
    }
}

echo "Completed!\n";

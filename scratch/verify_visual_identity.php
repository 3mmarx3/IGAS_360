<?php
/**
 * CI-STYLE VISUAL IDENTITY & ZERO-SHADOW VERIFICATION SUITE
 * "Pressure & Purity" Visual Identity Directive
 */

echo "======================================================================\n";
echo "I-GAS ENTERPRISE — VISUAL IDENTITY & ZERO-SHADOW AUDIT\n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$name}\n";
        if ($detail) echo "        -> {$detail}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$name}\n";
        if ($detail) echo "        -> ERROR: {$detail}\n";
        $failCount++;
    }
}

// 1. CI-STYLE STRICT ZERO-SHADOW CHECK
echo "\n--- 1. CI-Style Zero-Shadow Scan ---\n";
$shadowPattern = '/\bshadow(-[a-z0-9]+)?\b|box-shadow|drop-shadow|text-shadow/i';
$shadowViolations = [];

$scanDirs = ['modules', 'includes', 'auth', 'assets', 'config'];
$scanFiles = ['index.php', 'rep_dashboard.php'];

foreach ($scanDirs as $d) {
    if (!is_dir($d)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        if (preg_match('/\.(php|css|js|html)$/', $path)) {
            $scanFiles[] = $path;
        }
    }
}

foreach ($scanFiles as $filePath) {
    if (!file_exists($filePath)) continue;
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);

        // Skip permissible CSS reset rules and doc headers
        if (strpos($line, 'box-shadow: none') !== false ||
            strpos($line, '-webkit-box-shadow: none') !== false ||
            strpos($line, 'text-shadow: none') !== false ||
            strpos($line, 'boxShadow: {}') !== false ||
            strpos($line, 'dropShadow: {}') !== false ||
            strpos($line, 'boxShadow: false') !== false ||
            strpos($line, 'dropShadow: false') !== false ||
            strpos($line, 'boxShadowColor: false') !== false ||
            strpos($trimmed, '/*') === 0 ||
            strpos($trimmed, '*') === 0 ||
            strpos($trimmed, '//') === 0) {
            continue;
        }

        // Check if "shadow" appears in class attributes, inline style attributes, or active CSS rules
        $isClassViolation = preg_match('/class=[\"\x27][^\"\x27]*\bshadow(-[a-z0-9]+)?\b[^\"\x27]*[\"\x27]/i', $line);
        $isStyleViolation = preg_match('/style=[\"\x27][^\"\x27]*(box-shadow|drop-shadow|text-shadow)[^\"\x27]*[\"\x27]/i', $line);
        $isCssPropertyViolation = preg_match('/(box-shadow|drop-shadow|text-shadow)\s*:\s*(?!none)/i', $line);

        if ($isClassViolation || $isStyleViolation || $isCssPropertyViolation) {
            $shadowViolations[] = "{$filePath}:" . ($lineNum + 1) . ": " . $trimmed;
        }
    }
}

assert_test(
    "Absolute Zero Shadows Verification",
    count($shadowViolations) === 0,
    count($shadowViolations) === 0 ? "0 shadow violations detected across entire codebase." : implode("\n        -> ", array_slice($shadowViolations, 0, 5))
);

// 2. TAILWIND CONFIG & TOKEN VALIDATION
echo "\n--- 2. Tailwind Config & Tokens ---\n";
$tailwindConfig = file_exists('tailwind.config.js') ? file_get_contents('tailwind.config.js') : '';
assert_test("tailwind.config.js exists", file_exists('tailwind.config.js'));
assert_test("Tailwind disables boxShadow", strpos($tailwindConfig, 'boxShadow: {}') !== false || strpos($tailwindConfig, 'boxShadow: false') !== false);
assert_test("Tailwind disables dropShadow", strpos($tailwindConfig, 'dropShadow: {}') !== false || strpos($tailwindConfig, 'dropShadow: false') !== false);
assert_test("Tailwind includes ink #0A1B26", strpos($tailwindConfig, '#0A1B26') !== false);
assert_test("Tailwind includes cryo teal #0B6E8A", strpos($tailwindConfig, '#0B6E8A') !== false);
assert_test("Tailwind includes safety orange #FF6B1A", strpos($tailwindConfig, '#FF6B1A') !== false);

// 3. THEME.CSS DESIGN TOKENS & BLUEPRINT GRID
echo "\n--- 3. Theme CSS & Engineering Patterns ---\n";
$themeCss = file_exists('assets/css/theme.css') ? file_get_contents('assets/css/theme.css') : '';
assert_test("assets/css/theme.css exists", file_exists('assets/css/theme.css'));
assert_test("Theme CSS defines --ink #0A1B26", strpos($themeCss, '--ink: #0A1B26') !== false);
assert_test("Theme CSS defines --primary #0B6E8A", strpos($themeCss, '--primary: #0B6E8A') !== false);
assert_test("Theme CSS defines --primary-bright #19B5C9", strpos($themeCss, '--primary-bright: #19B5C9') !== false);
assert_test("Theme CSS defines --accent #FF6B1A", strpos($themeCss, '--accent: #FF6B1A') !== false);
assert_test("Theme CSS defines 3 Tonal Surfaces", strpos($themeCss, '--surface-0') !== false && strpos($themeCss, '--surface-1') !== false && strpos($themeCss, '--surface-2') !== false);
assert_test("Theme CSS supports Dark Control-Room Mode", strpos($themeCss, 'html.dark') !== false && strpos($themeCss, '#07131B') !== false);
assert_test("Theme CSS includes 24px Blueprint Grid", strpos($themeCss, '.blueprint-grid') !== false && strpos($themeCss, '24px 24px') !== false);
assert_test("Theme CSS includes Pipeline Dividers", strpos($themeCss, '.pipe-divider') !== false);
assert_test("Theme CSS includes Corner-Notched Cards", strpos($themeCss, '.card-notched') !== false && strpos($themeCss, 'clip-path') !== false);

// 4. UI HELPERS EXECUTION & GAS CHIP VERIFICATION
echo "\n--- 4. Industrial Gas Identity Helpers ---\n";
require_once __DIR__ . '/../includes/ui_helpers.php';

$chipO2 = gas_chip('O2', 'badge');
assert_test("gas_chip('O2') renders subscript O₂", strpos($chipO2, 'O₂') !== false);
assert_test("gas_chip('O2') includes cylinder blue #0066CC", strpos($chipO2, '#0066CC') !== false);

$chipN2 = gas_chip('N2', 'badge');
assert_test("gas_chip('N2') renders subscript N₂", strpos($chipN2, 'N₂') !== false);

$chipC2H2 = gas_chip('C2H2', 'badge');
assert_test("gas_chip('C2H2') renders C₂H₂ with maroon #881337", strpos($chipC2H2, 'C₂H₂') !== false && strpos($chipC2H2, '#881337') !== false);

$statusChip = cylinder_status_chip('full');
assert_test("cylinder_status_chip('full') includes Purged/OK status", strpos($statusChip, 'Full') !== false && strpos($statusChip, 'check-circle') !== false);

$hazardGHS = hazard_badge('oxidizer', 'md');
assert_test("hazard_badge('oxidizer') renders GHS03 diamond", strpos($hazardGHS, 'GHS03') !== false && strpos($hazardGHS, 'ghs-diamond') !== false);

$gaugeHtml = gauge_kpi('BULK O2', '18.4', 'BAR', 18.4, 0, 25, 'nominal');
assert_test("gauge_kpi renders SVG semicircular gauge", strpos($gaugeHtml, '<svg') !== false && strpos($gaugeHtml, 'BULK O2') !== false);

$levelBarHtml = level_bar(85, 100, '%');
assert_test("level_bar renders level bar with 10% ticks", strpos($levelBarHtml, 'level-bar-track') !== false && strpos($levelBarHtml, 'level-bar-ticks') !== false);

$iconCylinder = icon('cylinder');
assert_test("icon('cylinder') renders industrial SVG cylinder", strpos($iconCylinder, '<svg') !== false && strpos($iconCylinder, 'cylinder') !== false);

$pipeDiv = pipe_divider('SECTION STAGE A', 'V-01');
assert_test("pipe_divider renders pipeline divider with node", strpos($pipeDiv, 'pipe-divider') !== false && strpos($pipeDiv, 'V-01') !== false);

$tagPlate = tag_plate('CYL-9011', 'ID');
assert_test("tag_plate renders monospace label plate", strpos($tagPlate, 'tag-plate') !== false && strpos($tagPlate, 'CYL-9011') !== false);

// 5. DESIGN SYSTEM DOCUMENTATION
echo "\n--- 5. Design System Documentation ---\n";
assert_test("docs/DESIGN_SYSTEM.md exists", file_exists('docs/DESIGN_SYSTEM.md'));
$docContent = file_exists('docs/DESIGN_SYSTEM.md') ? file_get_contents('docs/DESIGN_SYSTEM.md') : '';
assert_test("Doc defines 'Pressure & Purity' concept", strpos($docContent, 'Pressure & Purity') !== false);
assert_test("Doc includes Gas Color Coding Table", strpos($docContent, '| Oxygen |') !== false || strpos($docContent, 'O₂') !== false);
assert_test("Doc includes Zero-Shadow Rule section", strpos($docContent, 'Zero-Shadow') !== false || strpos($docContent, 'ZERO-SHADOW') !== false);

// 6. STYLE GUIDE TEST RUN
echo "\n--- 6. Style Guide Test Run ---\n";
$cmd = "php scratch/run_single_page.php modules/tools/style_guide.php 2>&1";
$rawOutput = shell_exec($cmd);
$styleGuidePassed = false;
if (preg_match('/__CRAWLER_RESULT__(.*?)__END_CRAWLER_RESULT__/s', $rawOutput, $m)) {
    $res = json_decode(trim($m[1]), true);
    if (($res['status'] ?? '') === 'ok' && ($res['http_code'] ?? 0) === 200) {
        $styleGuidePassed = true;
    }
}
assert_test("modules/tools/style_guide.php executes with HTTP 200 OK", $styleGuidePassed);

// SUMMARY
echo "\n======================================================================\n";
echo "SUMMARY: Passed {$passCount} / " . ($passCount + $failCount) . " checks.\n";
if ($failCount === 0) {
    echo "STATUS: ALL VISUAL IDENTITY & ZERO-SHADOW TESTS PASSED (100% SUCCESS)\n";
} else {
    echo "STATUS: {$failCount} CHECKS FAILED.\n";
}
echo "======================================================================\n";

exit($failCount > 0 ? 1 : 0);

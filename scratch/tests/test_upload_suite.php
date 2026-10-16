<?php
declare(strict_types=1);

/**
 * ROUND 2 — TEST SUITE 4: FILE UPLOAD SECURITY
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

echo "=== RUNNING TEST SUITE 4: FILE UPLOAD SECURITY ===\n";

// Helper to simulate new_quotation.php file upload logic
function simulate_upload(string $origName, string $content, string $mimeType = ''): array {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_up_');
    file_put_contents($tempFile, $content);
    $fileSize = filesize($tempFile);

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $error_message = '';
    $saved_path = null;

    if ($fileSize > $maxSize) {
        $error_message = "File upload error: Company logo must not exceed 2MB.";
    } elseif (!in_array($fileExt, $allowedExts, true)) {
        $error_message = "File upload error: Only JPG, PNG, WEBP, and SVG image formats are permitted.";
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tempFile);
        finfo_close($finfo);

        if (!in_array($detectedMime, $allowedMimes, true)) {
            $error_message = "File upload error: Detected invalid image MIME type ($detectedMime).";
        } else {
            $upload_dir = __DIR__ . '/test_uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $safeName = 'logo_' . bin2hex(random_bytes(12)) . '.' . $fileExt;
            $dest = $upload_dir . $safeName;
            copy($tempFile, $dest);
            $saved_path = $dest;
        }
    }

    unlink($tempFile);
    return ['error' => $error_message, 'saved' => $saved_path];
}

// 1. Disallowed extension: evil.php
$r1 = simulate_upload('evil.php', '<?php phpinfo(); ?>');
$p1 = str_contains($r1['error'], 'Only JPG, PNG, WEBP, and SVG');
record_test('Reject executable file extension (.php)', $p1, $r1['error']);

// 2. Disallowed extension: evil.phtml
$r2 = simulate_upload('evil.phtml', '<?php phpinfo(); ?>');
$p2 = str_contains($r2['error'], 'Only JPG, PNG, WEBP, and SVG');
record_test('Reject executable file extension (.phtml)', $p2, $r2['error']);

// 3. Spoofed extension: fake_image.png containing PHP code and not a real image
$r3 = simulate_upload('fake_image.png', '<?php phpinfo(); ?>');
$p3 = str_contains($r3['error'], 'Detected invalid image MIME type');
record_test('Reject spoofed MIME type (text/plain disguised as .png)', $p3, $r3['error']);

// 4. File size limit: 3MB image
$largePayload = str_repeat('A', 3 * 1024 * 1024);
$r4 = simulate_upload('large_image.png', $largePayload);
$p4 = str_contains($r4['error'], 'must not exceed 2MB');
record_test('Reject file exceeding 2MB size limit', $p4, $r4['error']);

// 5. Valid small 1x1 transparent PNG image
// 1x1 transparent PNG base64
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$r5 = simulate_upload('valid_logo.png', $pngBytes);
$p5 = empty($r5['error']) && !empty($r5['saved']) && file_exists($r5['saved']);
record_test('Accept valid PNG image and assign randomized name', $p5, "Saved: " . ($r5['saved'] ?? 'NONE'));

// Clean up test saved file
if (!empty($r5['saved']) && file_exists($r5['saved'])) {
    unlink($r5['saved']);
}
if (is_dir(__DIR__ . '/test_uploads/')) {
    @rmdir(__DIR__ . '/test_uploads/');
}

// 6. Verify uploads/.htaccess exists and contains PHP execution prohibition
$htaccessPath = dirname(__DIR__, 2) . '/uploads/.htaccess';
$htaccessExists = file_exists($htaccessPath);
$htaccessContent = $htaccessExists ? file_get_contents($htaccessPath) : '';
$hasEngineOff = str_contains($htaccessContent, 'php_flag engine off');
$hasDenyAll = str_contains($htaccessContent, 'Deny from all');
$p6 = $htaccessExists && $hasEngineOff && $hasDenyAll;
record_test('uploads/.htaccess contains strict PHP execution disablement', $p6, "Exists: " . var_export($htaccessExists, true));

$passCount = count(array_filter($results, fn($r) => $r['passed']));
$totalCount = count($results);
echo "\nSuite 4 Summary: {$passCount}/{$totalCount} passed.\n";

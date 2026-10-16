<?php
/**
 * scratch/ui_audit_scanner.php
 * Automated UI/UX Scanner across all 105 PHP files and CSS files.
 */

$root = dirname(__DIR__);
$dirs = [
    $root . '/*.php',
    $root . '/includes/*.php',
    $root . '/modules/*/*.php',
    $root . '/auth/*.php',
    $root . '/assets/css/*.css',
];

$files = [];
foreach ($dirs as $pattern) {
    foreach (glob($pattern) as $file) {
        if (strpos($file, '/vendor/') === false && strpos($file, '/scratch/') === false) {
            $files[] = $file;
        }
    }
}
$files = array_unique($files);
sort($files);

echo "======================================================================\n";
echo "I-GAS ENTERPRISE — AUTOMATED UI/UX CODEBASE SCANNER\n";
echo "Scanning " . count($files) . " files across root, includes, and modules...\n";
echo "======================================================================\n\n";

$results = [
    'light_bg' => [],
    'dark_text' => [],
    'absolute_search' => [],
    'light_tfoot_summary' => [],
    'missing_whitespace_nowrap' => [],
    'missing_kpi_orange_border' => [],
    'light_progress_bar' => [],
];

$lightBgPatterns = [
    'bg-white', 'bg-slate-50', 'bg-slate-100', 'bg-slate-200',
    'bg-gray-50', 'bg-gray-100', 'bg-gray-200', 'bg-neutral-50',
    'bg-neutral-100', 'bg-neutral-200', 'bg-zinc-50', 'bg-zinc-100',
    'bg-zinc-200', 'bg-teal-50', 'bg-rose-50', 'bg-blue-50', 'bg-amber-50',
];

$darkTextPatterns = [
    'text-gray-900', 'text-slate-900', 'text-slate-800', 'text-gray-800',
    'text-neutral-900', 'text-neutral-800', 'text-black', 'text-gray-700', 'text-slate-700',
];

foreach ($files as $file) {
    $rel = str_replace($root . '/', '', $file);
    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    // Skip CSS files from certain checks
    $isCss = substr($file, -4) === '.css';

    foreach ($lines as $lineNum => $line) {
        $ln = $lineNum + 1;

        if (!$isCss) {
            // Check 1: Light backgrounds
            foreach ($lightBgPatterns as $pat) {
                if (preg_match('/(?<!after:)\b' . preg_quote($pat, '/') . '(\b|\/)/', $line)) {
                    // Check if it's not a dark: variant or legitimate small badge
                    $results['light_bg'][$rel][] = ["line" => $ln, "match" => $pat, "text" => trim($line)];
                    break;
                }
            }

            // Check 2: Dark text on dark background
            foreach ($darkTextPatterns as $pat) {
                if (preg_match('/\b' . preg_quote($pat, '/') . '\b/', $line)) {
                    // Ignore if inside a print style or purely dark:
                    if (strpos($line, 'dark:text-') === false || preg_match('/\b' . preg_quote($pat, '/') . '\b/', str_replace('dark:', '', $line))) {
                        $results['dark_text'][$rel][] = ["line" => $ln, "match" => $pat, "text" => trim($line)];
                        break;
                    }
                }
            }

            // Check 3: Search input with absolute icon
            if (preg_match('/<i[^>]+data-lucide=["\']search["\'][^>]*class=["\'][^"\']*\babsolute\b[^"\']*["\']/', $line) ||
                preg_match('/class=["\'][^"\']*\babsolute\b[^"\']*["\'][^>]*><i[^>]+data-lucide=["\']search["\']/', $line)) {
                $results['absolute_search'][$rel][] = ["line" => $ln, "text" => trim($line)];
            }

            // Check 4: Washed out tfoot, table header card or summary section
            if (preg_match('/<(tfoot|div)[^>]*class=["\'][^"\']*(bg-neutral-50|bg-slate-50|bg-gray-50|bg-gray-100|bg-white)[^"\']*["\']/', $line)) {
                $results['light_tfoot_summary'][$rel][] = ["line" => $ln, "text" => trim($line)];
            }

            // Check 5: Table cells or status badges or action buttons missing whitespace-nowrap
            if (preg_match('/<td[^>]*class=["\']([^"\']*)["\']/', $line, $m)) {
                $cls = $m[1];
                if ((strpos($line, 'SAR') !== false || strpos($line, 'INV-') !== false || strpos($line, 'PO-') !== false || strpos($line, 'ORD-') !== false || strpos($line, 'date(') !== false) && strpos($cls, 'whitespace-nowrap') === false) {
                    $results['missing_whitespace_nowrap'][$rel][] = ["line" => $ln, "type" => "td", "text" => trim($line)];
                }
            }
            if (preg_match('/class=["\'][^"\']*\b(inline-flex items-center gap-1\.5|pill|badge)\b[^"\']*["\']/', $line, $m)) {
                if (strpos($line, 'whitespace-nowrap') === false && strpos($line, 'status') !== false) {
                    $results['missing_whitespace_nowrap'][$rel][] = ["line" => $ln, "type" => "badge", "text" => trim($line)];
                }
            }

            // Check 6: Light progress bar track
            if (preg_match('/<div[^>]*class=["\'][^"\']*\b(bg-neutral-200|bg-gray-200|bg-slate-200|bg-white)\b[^"\']*\b(h-1|h-1\.5|h-2|rounded-full)\b[^"\']*["\']/', $line)) {
                $results['light_progress_bar'][$rel][] = ["line" => $ln, "text" => trim($line)];
            }
        }
    }

    // Check 7: KPI cards without orange left border (border-l- or border-s-)
    if (!$isCss && (strpos($content, 'grid-cols-') !== false || strpos($content, 'Bento') !== false || strpos($content, 'KPI') !== false)) {
        if (preg_match_all('/<div[^>]*class=["\']([^"\']*\b(card|bg-\[#0c1924\])[^"\']*)["\']/i', $content, $kpiMatches)) {
            $hasKpiGrid = false;
            foreach ($lines as $lnIdx => $line) {
                if (preg_match('/grid-cols-[124].*gap-[46]/', $line)) {
                    $hasKpiGrid = true;
                    // Check next 30 lines for cards without orange left border
                    for ($j = $lnIdx; $j < min(count($lines), $lnIdx + 40); $j++) {
                        if (preg_match('/<div[^>]*class=["\']([^"\']*(?:bg-\[#0c1924\]|card)[^"\']*)["\']/', $lines[$j], $cardMatch)) {
                            $cClasses = $cardMatch[1];
                            if (strpos($cClasses, 'border-l-') === false && strpos($cClasses, 'border-s-') === false && strpos($cClasses, 'border-orange') === false && strpos($cClasses, '#f97316') === false) {
                                $results['missing_kpi_orange_border'][$rel][] = ["line" => $j + 1, "text" => trim($lines[$j])];
                            }
                        }
                    }
                    break;
                }
            }
        }
    }
}

// Print Report
foreach ($results as $category => $fileData) {
    echo "----------------------------------------------------------------------\n";
    echo strtoupper(str_replace('_', ' ', $category)) . " (" . count($fileData) . " files affected):\n";
    echo "----------------------------------------------------------------------\n";
    $count = 0;
    foreach ($fileData as $file => $items) {
        echo "  [FILE] $file (" . count($items) . " occurrences)\n";
        foreach (array_slice($items, 0, 4) as $item) {
            $line = $item['line'];
            $snippet = substr($item['text'], 0, 95);
            echo "    L$line: $snippet...\n";
            $count++;
        }
        if (count($items) > 4) {
            echo "    ... and " . (count($items) - 4) . " more lines in this file.\n";
        }
    }
    echo "Subtotal in category: $count\n\n";
}

echo "Scan complete.\n";

<?php
/**
 * scratch/remediate_all_ui.php
 * Systematic UI/UX Remediation across all 105 PHP files.
 */

$root = dirname(__DIR__);
$dirs = [
    $root . '/*.php',
    $root . '/includes/*.php',
    $root . '/modules/*/*.php',
    $root . '/auth/*.php',
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

echo "Loaded " . count($files) . " files for systematic remediation.\n";

$modifiedCount = 0;

foreach ($files as $file) {
    $rel = str_replace($root . '/', '', $file);
    $origContent = file_get_contents($file);
    $content = $origContent;

    // 1. REFACTOR SEARCH INPUT CONTAINERS TO FLEX PATTERN
    // Pattern A: <div class="relative ...">\s*(?:<span[^>]*>)?\s*<i[^>]*data-lucide=["']search["'][^>]*>.*?</(?:span|i)>\s*<input[^>]*>
    $searchPattern = '/<div class="relative\s+w-full\s+sm:w-(?:64|72|80)\s+md:w-(?:72|80)">\s*(?:<span[^>]*>\s*)?<i\s+data-lucide=["\']search["\'][^>]*><\/i>(?:\s*<\/span>)?\s*<input\s+([^>]*?)class=["\']([^"\']*)["\']([^>]*)>\s*<\/div>/is';
    
    $content = preg_replace_callback($searchPattern, function($m) {
        $attrsBefore = $m[1];
        $classes = $m[2];
        $attrsAfter = $m[3];

        // Clean out input classes, keeping only necessary functional attributes
        $inputAttrs = trim($attrsBefore . ' ' . $attrsAfter);
        // Extract id, placeholder, onkeyup, etc.
        return '<div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 w-full sm:w-64 md:w-72">' . "\n" .
               '                                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>' . "\n" .
               '                                <input ' . $inputAttrs . ' class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">' . "\n" .
               '                            </div>';
    }, $content);

    // Also handle slightly varied relative search wrappers (e.g. without md:w- or with ps-9)
    $searchPattern2 = '/<div class="relative\s+(w-full\s+sm:w-[^"]+)">\s*(?:<span[^>]*>\s*)?<i\s+data-lucide=["\']search["\'][^>]*><\/i>(?:\s*<\/span>)?\s*<input\s+([^>]*?)class=["\']([^"\']*\b(?:ps-9|pl-9|ps-10|pl-10)\b[^"\']*)["\']([^>]*)>\s*<\/div>/is';
    $content = preg_replace_callback($searchPattern2, function($m) {
        $wClass = $m[1];
        $attrsBefore = $m[2];
        $attrsAfter = $m[4];
        $inputAttrs = trim($attrsBefore . ' ' . $attrsAfter);
        return '<div class="relative flex items-center bg-[#07111a] border border-[#1e3a50] rounded-md px-3 h-9 focus-within:border-orange-500 ' . $wClass . '">' . "\n" .
               '                                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 mr-2.5 rtl:ml-2.5 shrink-0 pointer-events-none"></i>' . "\n" .
               '                                <input ' . $inputAttrs . ' class="bg-transparent border-0 text-xs font-mono text-slate-100 placeholder-slate-500 focus:outline-none w-full p-0">' . "\n" .
               '                            </div>';
    }, $content);

    // 2. FIX DUAL LIGHT/DARK CLASSES & WASHED OUT TABLE HEADERS / FOOTERS
    // Replace: bg-neutral-50 bg-[#112433]/... or bg-neutral-50 bg-[#0c1924]
    $content = preg_replace('/\bbg-neutral-50\s+bg-\[#112433\]\/[0-9]+\b/', 'bg-[#09151f]', $content);
    $content = preg_replace('/\bbg-neutral-50\s+bg-\[#0c1924\]\b/', 'bg-[#09151f]', $content);
    $content = preg_replace('/\bbg-neutral-50\/[0-9]+\b/', 'bg-[#09151f]', $content);
    $content = preg_replace('/\bborder\s+border-neutral-200\s+border-\[#1e3a50\](\/[0-9]+)?\b/', 'border border-[#1e3a50]$1', $content);

    // 3. FIX PROGRESS BAR TRACKS
    $content = preg_replace('/\bbg-neutral-200\s+bg-\[#112433\]\b/', 'bg-[#132636]', $content);
    $content = preg_replace('/\bbg-neutral-200\s+dark:bg-neutral-700\b/', 'bg-[#132636]', $content);
    $content = preg_replace('/class=["\']([^"\']*\b)bg-neutral-200(\b[^"\']*\b(?:h-1|h-1\.5|h-2)\b[^"\']*)["\']/', 'class="$1bg-[#132636]$2"', $content);
    $content = preg_replace('/class=["\']([^"\']*\b)bg-gray-200(\b[^"\']*\b(?:h-1|h-1\.5|h-2)\b[^"\']*)["\']/', 'class="$1bg-[#132636]$2"', $content);

    // 4. FIX KPI CARDS REMOVING OVERRIDING dark:border-s-teal-400
    $content = str_replace('border-s-4 border-s-[#f97316] dark:border-s-teal-400', 'border-s-4 border-s-[#f97316]', $content);
    $content = str_replace('border-l-4 border-l-[#f97316] dark:border-l-teal-400', 'border-l-4 border-l-[#f97316]', $content);

    // 5. FIX TABLE CELLS & BADGES WHITESPACE-NOWRAP
    // In tables, add whitespace-nowrap to date, currency, status cells
    $content = preg_replace_callback('/<td([^>]*class=["\'][^"\']*)>/i', function($m) {
        $td = $m[1];
        if (strpos($td, 'whitespace-nowrap') === false) {
            // Check if it should have whitespace-nowrap
            if (preg_match('/\b(font-mono|tabular-nums|font-bold)\b/', $td)) {
                return '<td' . str_replace('class="', 'class="whitespace-nowrap ', str_replace("class='", "class='whitespace-nowrap ", $td)) . '>';
            }
        }
        return $m[0];
    }, $content);

    // 6. FIX UNREADABLE TEXT COLORS (text-neutral-700 hover:text-black -> text-slate-300 hover:text-white)
    $content = str_replace('text-neutral-700 hover:text-black', 'text-slate-300 hover:text-white', $content);
    $content = str_replace('text-neutral-800 dark:text-neutral-200', 'text-slate-300 hover:text-white', $content);

    if ($content !== $origContent) {
        file_put_contents($file, $content);
        $modifiedCount++;
        echo "  [UPDATED] $rel\n";
    }
}

echo "\nRemediation complete. Updated $modifiedCount files.\n";

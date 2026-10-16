<?php
/**
 * scratch/fix_remaining_defects.php
 * Exhaustive elimination of all remaining light backgrounds, missing KPI orange borders,
 * and washed-out footers/headers.
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

$modifiedCount = 0;

foreach ($files as $file) {
    $rel = str_replace($root . '/', '', $file);
    $origContent = file_get_contents($file);
    $content = $origContent;

    // 1. ELIMINATE DUAL bg-neutral-50 bg-[#0c1924]
    // In table headers / footers / card sections
    $content = str_replace('bg-neutral-50 bg-[#0c1924]', 'bg-[#09151f]', $content);
    $content = str_replace('bg-neutral-50 bg-[#112433]', 'bg-[#09151f]', $content);
    $content = str_replace('border-neutral-200 border-[#1e3a50]', 'border-[#1e3a50]', $content);

    // Clean up dual text classes: "text-neutral-700 text-slate-400"
    $content = str_replace('text-neutral-700 text-slate-400', 'text-slate-400', $content);
    $content = str_replace('text-neutral-500 text-slate-400', 'text-slate-400', $content);

    // 2. ENFORCE KPI ORANGE LEFT BORDER ON CARDS
    // In files like licenses_alerts, maintenance_fuel, warehouses, etc.
    // Replace: class="bg-[#0c1924] border border-[#1a3346] p-4 rounded-xs"
    // with: class="bg-[#0c1924] border border-[#1a3346] border-l-4 border-l-[#f97316] p-4 rounded-xs"
    $content = preg_replace_callback('/class=["\']([^"\']*\bbg-\[#0c1924\][^"\']*\bborder border-\[#1a3346\][^"\']*)["\']/', function($m) {
        $cls = $m[1];
        // If it's a card container and lacks border-l- or border-s-
        if (strpos($cls, 'border-l-') === false && strpos($cls, 'border-s-') === false && strpos($cls, 'overflow-hidden') === false) {
            if (strpos($cls, 'p-4') !== false || strpos($cls, 'p-5') !== false || strpos($cls, 'p-6') !== false) {
                return 'class="' . $cls . ' border-s-4 border-s-[#f97316]"';
            }
        }
        return $m[0];
    }, $content);

    // Also handle: class="p-5 border border-[#1a3346] bg-[#0c1924]" in partner_profile etc.
    $content = preg_replace('/class=["\']p-5 border border-\[#1a3346\] bg-\[#0c1924\]["\']/', 'class="p-5 border border-[#1a3346] border-s-4 border-s-[#f97316] bg-[#0c1924]"', $content);

    // 3. REPLACE LINGERING LIGHT BADGE/ALERT BACKGROUNDS (bg-teal-50, bg-rose-50, bg-amber-50, bg-neutral-100)
    // where they don't have dark: prefix
    $content = preg_replace('/(?<!dark:)\bbg-teal-50\b/', 'bg-teal-950/30', $content);
    $content = preg_replace('/(?<!dark:)\bbg-rose-50\b/', 'bg-rose-950/30', $content);
    $content = preg_replace('/(?<!dark:)\bbg-amber-50\b/', 'bg-amber-950/30', $content);
    $content = preg_replace('/(?<!dark:)\bbg-blue-50\b/', 'bg-blue-950/30', $content);
    $content = preg_replace('/(?<!dark:)\bbg-neutral-100\b/', 'bg-[#102230]', $content);
    $content = preg_replace('/(?<!dark:)\bbg-gray-100\b/', 'bg-[#102230]', $content);

    // 4. FIX TEXT COLORS FOR THOSE BADGES (text-teal-700 -> text-teal-400, text-rose-700 -> text-rose-400, text-amber-700 -> text-amber-400)
    $content = preg_replace('/(?<!dark:)\btext-teal-700\b/', 'text-teal-400', $content);
    $content = preg_replace('/(?<!dark:)\btext-teal-800\b/', 'text-teal-300', $content);
    $content = preg_replace('/(?<!dark:)\btext-rose-700\b/', 'text-rose-400', $content);
    $content = preg_replace('/(?<!dark:)\btext-rose-800\b/', 'text-rose-300', $content);
    $content = preg_replace('/(?<!dark:)\btext-amber-700\b/', 'text-amber-400', $content);
    $content = preg_replace('/(?<!dark:)\btext-amber-800\b/', 'text-amber-300', $content);
    $content = preg_replace('/(?<!dark:)\btext-neutral-700\b/', 'text-slate-300', $content);

    // 5. FIX BORDERS ON THOSE ALERTS (border-teal-200 -> border-teal-800, border-rose-200 -> border-rose-800, etc.)
    $content = preg_replace('/(?<!dark:)\bborder-teal-200\b/', 'border-teal-800', $content);
    $content = preg_replace('/(?<!dark:)\bborder-teal-300\b/', 'border-teal-800', $content);
    $content = preg_replace('/(?<!dark:)\bborder-rose-200\b/', 'border-rose-800', $content);
    $content = preg_replace('/(?<!dark:)\bborder-rose-300\b/', 'border-rose-800', $content);
    $content = preg_replace('/(?<!dark:)\bborder-amber-200\b/', 'border-amber-800', $content);

    // 6. FIX MISSING WHITESPACE-NOWRAP ON BADGES & ACTION BUTTONS
    $content = preg_replace_callback('/class=["\']([^"\']*\b(?:btn-table-action|tag-plate|pill)\b[^"\']*)["\']/', function($m) {
        $cls = $m[1];
        if (strpos($cls, 'whitespace-nowrap') === false) {
            return 'class="' . $cls . ' whitespace-nowrap"';
        }
        return $m[0];
    }, $content);

    if ($content !== $origContent) {
        file_put_contents($file, $content);
        $modifiedCount++;
        echo "  [CLEANED] $rel\n";
    }
}

echo "\nExhaustive cleanup complete. Modified $modifiedCount files.\n";

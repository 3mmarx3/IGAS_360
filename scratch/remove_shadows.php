<?php
/**
 * Automated Zero-Shadows Refactor Script
 * Removes all box-shadow inline styles, custom CSS declarations, and Tailwind shadow classes.
 */

$dir = realpath(__DIR__ . '/..');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$modifiedCount = 0;
$filesChanged = [];

foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();

    // Skip vendor, git, cache, and scratch directories
    if (strpos($path, '/.git/') !== false || 
        strpos($path, '/vendor/') !== false || 
        strpos($path, '/scratch/') !== false) {
        continue;
    }

    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array($ext, ['php', 'html', 'css'])) continue;

    $content = file_get_contents($path);
    $origContent = $content;

    // 1. Remove inline CSS box-shadow declarations
    $content = preg_replace('/box-shadow\s*:\s*[^;]+;/i', '', $content);
    $content = preg_replace('/box-shadow\s*:\s*none\s*(!important)?\s*;?/i', '', $content);
    // Clean up empty style="" or style=" "
    $content = preg_replace('/style="\s*"/i', '', $content);

    // 2. Remove Tailwind shadow classes within class="..." or class='...'
    $content = preg_replace_callback('/class=(["\'])(.*?)\1/s', function($matches) {
        $quote = $matches[1];
        $classes = $matches[2];
        
        // Remove shadow classes
        $newClasses = preg_replace('/\bshadow-(2xl|xl|lg|md|sm|xs|inner)\b/', '', $classes);
        $newClasses = preg_replace('/\bshadow\b/', '', $newClasses);
        // Normalize multiple spaces
        $newClasses = preg_replace('/\s+/', ' ', trim($newClasses));
        
        return 'class=' . $quote . $newClasses . $quote;
    }, $content);

    if ($content !== $origContent) {
        file_put_contents($path, $content);
        $modifiedCount++;
        $filesChanged[] = str_replace($dir . '/', '', $path);
    }
}

echo "Zero-Shadows Refactoring Complete!\n";
echo "Total files modified: {$modifiedCount}\n";
foreach ($filesChanged as $f) {
    echo " - {$f}\n";
}

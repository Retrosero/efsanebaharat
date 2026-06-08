<?php
$dir = new RecursiveDirectoryIterator('c:/Users/serha/OneDrive/Belgeler/GitHub/efsanebaharat');
$iterator = new RecursiveIteratorIterator($dir);
$regex = '/SELECT\s+.*?\bid\b.*?FROM\s+.*?JOIN/is';

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (preg_match($regex, $content, $matches)) {
            echo "Potential match in: " . $file->getPathname() . "\n";
            // Print a bit of context
            // preg_match_all('/(SELECT\s+.*?\bid\b.*?FROM\s+.*?JOIN)/is', $content, $all_matches);
            // foreach($all_matches[0] as $m) echo "Context: " . substr($m, 0, 100) . "...\n";
        }
    }
}

<?php
// live_watch.php - Returns a hash of file modification times
// Optimized for performance

$files = glob("*.{php,html,css,js}", GLOB_BRACE);
$last_mod = 0;

foreach ($files as $file) {
    $mtime = filemtime($file);
    if ($mtime > $last_mod) {
        $last_mod = $mtime;
    }
}

// Also check important subdirectories if needed
if (is_dir('Image')) {
    $images = glob("Image/*.*");
    foreach ($images as $img) {
        $mtime = filemtime($img);
        if ($mtime > $last_mod) {
            $last_mod = $mtime;
        }
    }
}

echo $last_mod;
?>

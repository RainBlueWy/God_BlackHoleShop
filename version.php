<?php
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// เวอร์ชัน = เวลาอัปเดตล่าสุดของไฟล์ (แก้โค้ดแล้วได้ค่าใหม่ → หน้าเว็บจะรีเฟรชเอง)
$files = [
    'index.php', 'index.css', 'script.js', 'version.php',
    'config.php', 'ticker_config.php', 'auth_guard.php', 'protection_header.php',
    'categories.php', 'profile.php', 'product.php', 'checkout.php',
    'admin_panel.php', 'admin_action.php', 'login.html', 'register.html'
];
$max = 0;
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $m = @filemtime($path);
        if ($m > $max) $max = $m;
    }
}
echo $max ?: time();

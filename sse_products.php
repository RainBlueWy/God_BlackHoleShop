<?php
/**
 * Server-Sent Events: แจ้งให้หน้า categories รู้ว่ามีการเพิ่ม/แก้/ลบสินค้า แล้วอัปเดตรายการแบบ real-time
 */
session_write_close();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
if (ob_get_level()) ob_end_clean();

$version_file = __DIR__ . '/products_sse_version.txt';
if (!file_exists($version_file)) file_put_contents($version_file, '0');
$last = file_get_contents($version_file);

set_time_limit(0);
$start = time();

while (true) {
    if (connection_aborted()) break;
    $now = @file_get_contents($version_file);
    if ($now !== false && $now !== $last) {
        $last = $now;
        echo "data: refresh\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
    echo ": keepalive\n\n";
    if (ob_get_level()) ob_flush();
    flush();
    sleep(1);
    if (time() - $start > 3600) break;
}

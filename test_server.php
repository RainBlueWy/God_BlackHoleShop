<?php
/**
 * เทสว่า Apache + PHP ทำงานหรือไม่ (ไม่ใช้ database)
 * เปิดไฟล์นี้ผ่านเบราว์เซอร์ ถ้าเห็นหน้านี้ = เซิร์ฟเวอร์ทำงาน
 */
header('Content-Type: text/html; charset=utf-8');
$port = $_SERVER['SERVER_PORT'] ?? '?';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = "http://{$host}";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เทสเซิร์ฟเวอร์</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 40px; margin: 0; }
        h1 { color: #22c55e; }
        .box { background: #1e293b; padding: 20px; border-radius: 12px; max-width: 500px; margin: 16px 0; }
        a { color: #38bdf8; }
        code { background: #334155; padding: 2px 8px; border-radius: 4px; }
        .ok { color: #22c55e; font-weight: bold; }
    </style>
</head>
<body>
    <h1>✓ PHP ทำงานแล้ว</h1>
    <p class="ok">ถ้าคุณเห็นหน้านี้ = Apache + PHP ใช้ได้</p>

    <div class="box">
        <p><strong>พอร์ตที่ใช้อยู่:</strong> <code><?= htmlspecialchars($port) ?></code></p>
        <p><strong>ลิงก์ที่ควรใช้เปิดเว็บ:</strong></p>
        <ul>
            <li><a href="<?= htmlspecialchars($base) ?>/god_blackhole/">God_BlackHole หน้าหลัก</a></li>
            <li><a href="<?= htmlspecialchars($base) ?>/phpmyadmin/">phpMyAdmin</a></li>
        </ul>
        <p style="color:#94a3b8; font-size:14px;">เก็บ URL นี้ไว้: <code><?= htmlspecialchars($base) ?></code></p>
    </div>

    <p>ถ้าเปิดลิงก์ด้านบนแล้วไม่ได้ ลอง:</p>
    <ol style="color:#94a3b8;">
        <li>ใน XAMPP กด <strong>Admin</strong> ข้าง Apache → ดู URL ในเบราว์เซอร์ (เช่น <code>http://localhost:1663</code>)</li>
        <li>ใช้ URL นั้น + <code>/god_blackhole/</code> หรือ <code>/phpmyadmin/</code></li>
    </ol>
</body>
</html>

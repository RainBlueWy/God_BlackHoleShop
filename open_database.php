<?php
/**
 * หน้าเปิดลิงก์ phpMyAdmin และเช็คการเชื่อมต่อ DB
 * เปิดหน้านี้ผ่านพอร์ตที่ Apache รันอยู่ (ดูจาก XAMPP ว่า Apache ใช้พอร์ตอะไร)
 */
$port = $_SERVER['SERVER_PORT'] ?? 80;
$host = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$phpmyadmin = rtrim($host, '/') . '/phpmyadmin/';
$god_blackhole = rtrim($host, '/') . '/god_blackhole/';

// ใช้การตั้งค่า DB จาก config.php (localhost = XAMPP, ไม่ใช่ localhost = InfinityFree/เว็บโฮสต์)
$db_ok = false;
$db_error = '';
$db_name_display = 'godblackholedb';
if (extension_loaded('mysqli')) {
    require_once __DIR__ . '/config.php';
    if (isset($conn) && !$conn->connect_error) {
        $db_ok = true;
        $db_name_display = DB_NAME;
    } else {
        $db_error = $conn->connect_error ?? 'Connection failed';
    }
} else {
    $db_error = 'mysqli extension ไม่ได้เปิดใช้';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปิด Database / phpMyAdmin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #1a1a2e; color: #eee; padding: 24px; margin: 0; }
        h1 { color: #0f0; margin-top: 0; }
        .card { background: #16213e; border-radius: 12px; padding: 20px; margin-bottom: 16px; max-width: 560px; }
        .card h2 { margin: 0 0 12px 0; font-size: 1.1rem; color: #7dd3fc; }
        a { color: #38bdf8; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 10px 20px; border-radius: 8px; margin: 4px 8px 4px 0; font-weight: 600; }
        .btn:hover { background: #2563eb; text-decoration: none; }
        .ok { color: #4ade80; }
        .err { color: #f87171; }
        .muted { color: #94a3b8; font-size: 0.9rem; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔗 เปิด Database / phpMyAdmin</h1>

    <div class="card">
        <h2>ลิงก์ที่ใช้พอร์ตปัจจุบัน (พอร์ต <?= (int)$port ?>)</h2>
        <p><a href="<?= htmlspecialchars($phpmyadmin) ?>" class="btn" target="_blank">เปิด phpMyAdmin</a></p>
        <p><a href="<?= htmlspecialchars($god_blackhole) ?>" class="btn" target="_blank">เปิด God_BlackHole</a></p>
        <p class="muted">ถ้าคลิกแล้วยังไม่โหลด แปลว่าคุณเปิดหน้านี้จากพอร์ตผิด (เช่น เปิดจาก localhost:80 แต่ Apache รันที่พอร์ตอื่น)</p>
    </div>

    <div class="card">
        <h2>วิธีให้ phpMyAdmin โหลดได้</h2>
        <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
            <li>ดูที่ XAMPP ว่า Apache แสดงพอร์ตอะไร (เช่น <strong>1663</strong>, 80, 8080)</li>
            <li>เปิดเบราว์เซอร์แล้วพิมพ์: <code>http://localhost:<strong>เลขพอร์ต</strong>/phpmyadmin/</code><br>
                เช่น ถ้าพอร์ต 1663 ให้ใส่ <code>http://localhost:1663/phpmyadmin/</code></li>
            <li>หรือใน XAMPP กด <strong>Admin</strong> ข้าง <strong>Apache</strong> (ไม่ใช่ MySQL) แล้วในหน้า dashboard คลิกเมนู <strong>phpMyAdmin</strong> — ลิงก์จะใช้พอร์ตที่ถูกต้อง</li>
        </ol>
    </div>

    <div class="card">
        <h2>สถานะการเชื่อมต่อ MySQL (<?= htmlspecialchars($db_name_display) ?>)</h2>
        <?php if ($db_ok): ?>
            <p class="ok">✓ เชื่อมต่อฐานข้อมูลได้ปกติ</p>
        <?php else: ?>
            <p class="err">✗ เชื่อมต่อไม่ได้: <?= htmlspecialchars($db_error) ?></p>
            <p class="muted">บน localhost: ตรวจสอบว่า MySQL ใน XAMPP กำลังรัน และมีฐานข้อมูลชื่อ <code>godblackholedb</code><br>บนเว็บโฮสต์: ตรวจสอบ Host/User/Password/DB ใน <code>config.php</code> และว่าชื่อ DB ตรงกับที่สร้างใน Control Panel</p>
        <?php endif; ?>
    </div>
</body>
</html>

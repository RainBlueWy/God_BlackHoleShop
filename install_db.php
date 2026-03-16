<?php
/**
 * ติดตั้งฐานข้อมูล God_BlackHole ทั้งหมด (รันครั้งเดียว)
 * เปิดในเบราว์เซอร์: http://localhost/god_blackhole/install_db.php
 * หรือใช้พอร์ตที่ Apache รันอยู่ เช่น http://localhost:1663/god_blackhole/install_db.php
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='th'><head><meta charset='UTF-8'><title>ติดตั้ง DB</title>";
echo "<style>body{font-family:sans-serif;background:#1a1a2e;color:#e2e8f0;padding:24px;} .ok{color:#4ade80;} .err{color:#f87171;} .warn{color:#fbbf24;} pre{background:#0f172a;padding:12px;border-radius:8px;overflow:auto;}</style></head><body>";
echo "<h1>ติดตั้งฐานข้อมูล God_BlackHole</h1>";

// แสดงว่าเชื่อมต่อที่ไหน (ถ้า phpMyAdmin ไม่เห็นตาราง = คนละตัว)
echo "<p class='warn'><strong>กำลังเชื่อมต่อ:</strong> " . htmlspecialchars(DB_HOST) . " → ฐานข้อมูล <strong>" . htmlspecialchars(DB_NAME) . "</strong></p>";
echo "<p style='color:#94a3b8;font-size:14px;'>ถ้าใน phpMyAdmin ไม่เห็นตาราง แปลว่าคนละ MySQL (ดูพอร์ตใน XAMPP) หรือให้ใช้วิธี <a href='#sql' style='color:#38bdf8;'>นำเข้า SQL ด้านล่าง</a></p>";

$errors = [];

// 1. ตาราง users (ต้องมีก่อน topups เพราะมี FK)
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(500) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=user 10=admin',
    points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    contact_line VARCHAR(255) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql_users)) {
    echo "<p class='ok'>✓ ตาราง users สร้างแล้ว (หรือมีอยู่แล้ว)</p>";
} else {
    $errors[] = "users: " . $conn->error;
}

// 2. ตาราง products (ครบทุกคอลัมน์)
$sql_products = "CREATE TABLE IF NOT EXISTS products (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price VARCHAR(50) NOT NULL,
    sale_price VARCHAR(50) DEFAULT NULL,
    max_slots INT(11) NOT NULL DEFAULT 0,
    sold_count INT(11) NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL,
    script_content TEXT,
    description TEXT,
    features TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql_products)) {
    echo "<p class='ok'>✓ ตาราง products สร้างแล้ว (หรือมีอยู่แล้ว)</p>";
} else {
    $errors[] = "products: " . $conn->error;
}

// เพิ่มคอลัมน์ที่อาจยังไม่มี (จาก update_schema ฯลฯ)
@$conn->query("ALTER TABLE products ADD COLUMN script_content TEXT AFTER image");
@$conn->query("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
@$conn->query("ALTER TABLE products ADD COLUMN max_slots INT(11) NOT NULL DEFAULT 0 AFTER price");
@$conn->query("ALTER TABLE products ADD COLUMN sold_count INT(11) NOT NULL DEFAULT 0 AFTER max_slots");
@$conn->query("ALTER TABLE products ADD COLUMN sale_price VARCHAR(50) DEFAULT NULL AFTER price");
@$conn->query("ALTER TABLE users ADD COLUMN points DECIMAL(10,2) DEFAULT 0.00 AFTER is_active");
@$conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
@$conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER email");

// 3. ตาราง purchases
$sql_purchases = "CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    assigned_admin_id INT(11) DEFAULT NULL,
    admin_status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql_purchases)) {
    echo "<p class='ok'>✓ ตาราง purchases สร้างแล้ว (หรือมีอยู่แล้ว)</p>";
} else {
    $errors[] = "purchases: " . $conn->error;
}
@$conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL AFTER points_used");
@$conn->query("ALTER TABLE purchases ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending' AFTER assigned_admin_id");

// 4. ตาราง ticker
$sql_ticker = "CREATE TABLE IF NOT EXISTS ticker (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql_ticker)) {
    echo "<p class='ok'>✓ ตาราง ticker สร้างแล้ว (หรือมีอยู่แล้ว)</p>";
    $chk = $conn->query("SELECT id FROM ticker WHERE id = 1");
    if ($chk && $chk->num_rows === 0) {
        $def = "ยินดีต้อนรับสู่ God_BlackHole — สคริปต์พรีเมียมคุณภาพสูง รองรับ Roblox อัปเดตสม่ำเสมอ ติดต่อสอบถามได้ที่ Discord";
        $esc = $conn->real_escape_string($def);
        $conn->query("INSERT INTO ticker (id, content, is_active) VALUES (1, '$esc', 1)");
        echo "<p class='ok'>✓ ใส่ข้อความแถบข่าวเริ่มต้นแล้ว</p>";
    }
} else {
    $errors[] = "ticker: " . $conn->error;
}

// 5. ตาราง topups
$sql_topups = "CREATE TABLE IF NOT EXISTS topups (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    slip_image VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql_topups)) {
    echo "<p class='ok'>✓ ตาราง topups สร้างแล้ว (หรือมีอยู่แล้ว)</p>";
} else {
    $errors[] = "topups: " . $conn->error;
}

// 6. ตารางแชท
$conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from_to (from_user_id, to_user_id),
    INDEX idx_to_from (to_user_id, from_user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS chat_task_ended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    customer_id INT NOT NULL,
    ended_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_customer (admin_id, customer_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS user_chat_read_since (
    user_id INT PRIMARY KEY,
    read_at INT UNSIGNED NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "<p class='ok'>✓ ตารางแชท (chat_messages, chat_task_ended, user_chat_read_since) สร้างแล้ว (หรือมีอยู่แล้ว)</p>";

// 7. ใส่สินค้าเริ่มต้น (จาก setup_db.php) ถ้ายังไม่มี
$count = $conn->query("SELECT COUNT(*) AS c FROM products");
$num = $count && ($row = $count->fetch_assoc()) ? (int)$row['c'] : 0;
if ($num === 0) {
    $products = [
        ['seliware', 'Seliware 7 วัน', 'สคริปต์ / สคริปต์ 7 วัน', '119 บาท', 'product-seliware.png', 'Seliware เป็นสคริปต์ Executor ที่มีประสิทธิภาพสูง...', '[]'],
        ['wave', 'Wave 7 วัน', 'สคริปต์ / สคริปต์ 7 วัน', '99 บาท', 'product-wave.png', 'Wave Executor เป็นเครื่องมือที่มีประสิทธิภาพสูง...', '[]'],
        ['celery', 'Celery 7 วัน', 'สคริปต์ / สคริปต์ 7 วัน', '89 บาท', 'product-celery.png', 'Celery เป็น Executor ที่มีราคาประหยัด...', '[]'],
        ['fluxus', 'Fluxus 30 วัน', 'สคริปต์ / สคริปต์ 30 วัน', '299 บาท', 'product-fluxus.png', 'Fluxus เป็น Executor ระดับพรีเมี่ยม...', '[]'],
        ['limited', 'ReaperX | Limited Edition', 'หมวดหมู่พิเศษ', '119 บาท', 'product-limited.png', 'ReaperX Limited Edition แพ็คเกจพิเศษ...', '[]'],
    ];
    $stmt = $conn->prepare("INSERT INTO products (slug, name, category, price, image, description, features) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        foreach ($products as $p) {
            $stmt->bind_param("sssssss", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
            $stmt->execute();
        }
        $stmt->close();
        echo "<p class='ok'>✓ ใส่สินค้าเริ่มต้นแล้ว " . count($products) . " รายการ</p>";
    }
}

if (!empty($errors)) {
    echo "<h2 class='err'>เกิดข้อผิดพลาด</h2><pre>" . implode("\n", $errors) . "</pre>";
} else {
    echo "<h2 class='ok'>ติดตั้งฐานข้อมูลเสร็จแล้ว</h2>";
    $res = $conn->query("SHOW TABLES");
    if ($res && $res->num_rows > 0) {
        echo "<p><strong>ตารางในฐานนี้ (ที่ script เห็น):</strong> ";
        $tables = [];
        while ($r = $res->fetch_array()) $tables[] = $r[0];
        echo implode(", ", $tables) . "</p>";
    }
    echo "<p><a href='index.php' style='color:#38bdf8;'>ไปหน้าหลัก</a> | <a href='register.html' style='color:#38bdf8;'>สมัครสมาชิก</a> | <a href='admin_panel.php' style='color:#d946ef;'>แอดมิน (ต้องมี user ที่ is_active=10)</a></p>";
    echo "<p class='muted' style='color:#94a3b8;'>หมายเหตุ: ต้องการแอดมิน ให้ใน phpMyAdmin แก้ users.is_active เป็น 10 สำหรับ user ที่ต้องการให้เป็นแอดมิน</p>";
    echo "<h3 id='sql' style='margin-top:24px;'>ถ้าใน phpMyAdmin ไม่เห็นตาราง — นำเข้า SQL โดยตรง</h3>";
    echo "<p style='color:#94a3b8;'>ใน phpMyAdmin เลือกฐานข้อมูล <strong>godblackholedb</strong> → แท็บ <strong>SQL</strong> → วางคำสั่งจากไฟล์ <a href='install_db.sql' style='color:#38bdf8;'>install_db.sql</a> แล้วกด Go</p>";
}

$conn->close();
echo "</body></html>";

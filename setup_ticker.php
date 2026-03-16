<?php
/**
 * สร้างตาราง ticker สำหรับแถบข่าว (รันครั้งเดียว)
 */
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS ticker (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    $check = $conn->query("SELECT id FROM ticker WHERE id = 1");
    if ($check->num_rows === 0) {
        $default = "ยินดีต้อนรับสู่ God_BlackHole — สคริปต์พรีเมียมคุณภาพสูง รองรับ Roblox อัปเดตสม่ำเสมอ ติดต่อสอบถามได้ที่ Discord";
        $stmt = $conn->prepare("INSERT INTO ticker (id, content, is_active) VALUES (1, ?, 1)");
        $stmt->bind_param("s", $default);
        $stmt->execute();
        $stmt->close();
        echo "ตาราง ticker สร้างแล้ว และใส่ข้อความเริ่มต้นแล้ว";
    } else {
        echo "ตาราง ticker มีอยู่แล้ว";
    }
} else {
    echo "Error: " . $conn->error;
}

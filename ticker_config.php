<?php
/**
 * โหลดข้อมูลแถบข่าว (ticker) จาก DB
 * ใช้ include ในหน้าที่มี navbar แล้วใช้ตัวแปร $ticker_text, $ticker_enabled
 */
if (!isset($conn)) require_once __DIR__ . '/config.php';

$ticker_text = '';
$ticker_enabled = 0;

$table_exists = $conn->query("SHOW TABLES LIKE 'ticker'");
if ($table_exists && $table_exists->num_rows > 0) {
    $row = $conn->query("SELECT content, is_active FROM ticker WHERE id = 1 LIMIT 1");
    if ($row && $r = $row->fetch_assoc()) {
        $ticker_text = $r['content'];
        $ticker_enabled = (int) $r['is_active'];
    }
}

if (empty($ticker_text)) {
    $ticker_text = 'ยินดีต้อนรับสู่ God_BlackHole — สคริปต์พรีเมียมคุณภาพสูง รองรับ Roblox อัปเดตสม่ำเสมอ ติดต่อสอบถามได้ที่ Discord';
}

<?php
/**
 * แก้คอลัมน์ reference_no ให้รับค่า NULL ได้
 * รันครั้งเดียว: เปิด http://localhost/god_blackhole/fix_reference_no_null.php
 * หลังแก้แล้ว ลบไฟล์นี้ได้
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

$ok = $conn->query("ALTER TABLE topups MODIFY COLUMN reference_no VARCHAR(255) NULL DEFAULT NULL");

if ($ok) {
    echo '<p style="color:green;">แก้แล้ว: reference_no รับค่า NULL ได้ รายการแนบสลิปจะบันทึกได้ปกติ</p>';
    echo '<p><a href="topup.php">ไปหน้าเติมเงิน</a></p>';
} else {
    echo '<p style="color:red;">ผิดพลาด: ' . htmlspecialchars($conn->error) . '</p>';
}

$conn->close();

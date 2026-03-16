<?php
/**
 * บันทึก Line ID / ลิงก์ติดต่อของลูกค้า (ให้แอดมินกดแชทไปหาลูกค้าเมื่อรับออเดอร์)
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$redirect = (isset($_POST['inapp']) || isset($_GET['inapp'])) ? 'profile.php?inapp=1' : 'app.php?page=profile';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$contact_line = isset($_POST['contact_line']) ? trim($_POST['contact_line']) : '';

// จำกัดความยาว
if (strlen($contact_line) > 255) {
    $contact_line = substr($contact_line, 0, 255);
}

// ตรวจสอบว่ามีคอลัมน์ contact_line
$col = @$conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
if ($col && $col->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
}

$stmt = $conn->prepare("UPDATE users SET contact_line = ? WHERE id = ?");
if (!$stmt) {
    $_SESSION['profile_error'] = 'ไม่สามารถบันทึกได้';
    header('Location: ' . $redirect);
    exit;
}
$stmt->bind_param('si', $contact_line, $user_id);
if ($stmt->execute()) {
    $_SESSION['profile_success'] = $contact_line !== '' ? 'บันทึก Line ID เรียบร้อย แอดมินจะติดต่อคุณได้เมื่อรับออเดอร์' : 'ล้าง Line ID เรียบร้อย';
} else {
    $_SESSION['profile_error'] = 'บันทึกไม่สำเร็จ';
}
header('Location: ' . $redirect);
exit;

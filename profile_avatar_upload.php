<?php
/**
 * อัปโหลดรูปโปรไฟล์จากเครื่อง (คอม/โทรศัพท์)
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// สร้างคอลัมน์ avatar / avatar_position ถ้ายังไม่มี
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if (!$check || $check->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT 'รูปโปรไฟล์' AFTER email");
}
$check2 = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_position'");
if (!$check2 || $check2->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar_position VARCHAR(30) DEFAULT '50% 50%' COMMENT 'ตำแหน่งรูปในวงกลม' AFTER avatar");
}
$check3 = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_scale'");
if (!$check3 || $check3->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar_scale INT DEFAULT 100 COMMENT 'ซูมรูปในวงกลม 80-150' AFTER avatar_position");
}

$user_id = (int) $_SESSION['user_id'];
$redirect = (isset($_POST['inapp']) || isset($_GET['inapp'])) ? 'profile.php?inapp=1' : 'profile.php';
$max_size = 15 * 1024 * 1024; // 15MB (รองรับ GIF และรูปใหญ่)
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$upload_dir = __DIR__ . '/uploads/avatars/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['profile_error'] = 'กรุณาเลือกไฟล์รูปภาพ';
    header('Location: ' . $redirect);
    exit;
}

$file = $_FILES['avatar'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    $_SESSION['profile_error'] = 'รองรับเฉพาะไฟล์ JPG, PNG, GIF, WEBP';
    header('Location: ' . $redirect);
    exit;
}

if ($file['size'] > $max_size) {
    $_SESSION['profile_error'] = 'ขนาดไฟล์ไม่เกิน 15 MB (รองรับ GIF และรูปใหญ่)';
    header('Location: ' . $redirect);
    exit;
}

if (!is_dir($upload_dir)) {
    if (!@mkdir($upload_dir, 0755, true)) {
        $_SESSION['profile_error'] = 'สร้างโฟลเดอร์อัปโหลดไม่ได้';
        header('Location: ' . $redirect);
        exit;
    }
}

// ดึง path รูปเก่าเพื่อลบภายหลัง
$stmt = $conn->prepare('SELECT avatar FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$old_path = $row && !empty($row['avatar']) ? __DIR__ . '/' . $row['avatar'] : null;

$new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
$relative_path = 'uploads/avatars/' . $new_name;
$full_path = $upload_dir . $new_name;

if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    $_SESSION['profile_error'] = 'อัปโหลดไม่สำเร็จ';
    header('Location: ' . $redirect);
    exit;
}

$pos = '50% 50%';
if (isset($_POST['x'], $_POST['y'])) {
    $px = max(0, min(100, (int) $_POST['x']));
    $py = max(0, min(100, (int) $_POST['y']));
    $pos = $px . '% ' . $py . '%';
}
$scale = 100;
if (isset($_POST['scale'])) {
    $scale = max(80, min(150, (int) $_POST['scale']));
}
$has_scale = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_scale'");
$has_scale = $has_scale && $has_scale->num_rows > 0;
if ($has_scale) {
    $stmt = $conn->prepare('UPDATE users SET avatar = ?, avatar_position = ?, avatar_scale = ? WHERE id = ?');
    $stmt->bind_param('ssii', $relative_path, $pos, $scale, $user_id);
} else {
    $stmt = $conn->prepare('UPDATE users SET avatar = ?, avatar_position = ? WHERE id = ?');
    $stmt->bind_param('ssi', $relative_path, $pos, $user_id);
}

if ($stmt->execute()) {
    if ($old_path && file_exists($old_path) && realpath($old_path) !== realpath($full_path)) {
        @unlink($old_path);
    }
    $_SESSION['profile_success'] = 'เปลี่ยนรูปโปรไฟล์แล้ว';
} else {
    @unlink($full_path);
    $_SESSION['profile_error'] = 'บันทึกข้อมูลไม่สำเร็จ';
}

header('Location: ' . $redirect);
exit;

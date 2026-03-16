<?php
session_start();
require_once 'config.php';
if ($_SESSION['role'] != 10) exit;

$id = (int)($_POST['id'] ?? 0);
$user = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$pass = $_POST['password'] ?? '';

if (!in_array($role, [0, 1, 10], true)) $role = 1;

// Protection: Prevent editing other admins
$check_target = $conn->query("SELECT is_active FROM users WHERE id = $id");
$target_u = $check_target ? $check_target->fetch_assoc() : null;
if (!$target_u) {
    $_SESSION['error'] = "ไม่พบผู้ใช้นี้";
    header("Location: admin_panel.php" . (isset($_POST['inapp']) ? '?inapp=1' : ''));
    exit;
}
if ($target_u['is_active'] == 10 && $id != $_SESSION['user_id']) {
    $_SESSION['error'] = "ไม่สามารถแก้ไขบัญชีแอดมินคนอื่นได้";
    header("Location: admin_panel.php" . (isset($_POST['inapp']) ? '?inapp=1' : ''));
    exit;
}

if ($pass !== "") {
    $enc = hash_password($pass);
    $stmt = $conn->prepare(
        "UPDATE users SET username=?, email=?, password=?, is_active=? WHERE id=?"
    );
    $stmt->bind_param("sssii", $user, $email, $enc, $role, $id);
} else {
    $stmt = $conn->prepare(
        "UPDATE users SET username=?, email=?, is_active=? WHERE id=?"
    );
    $stmt->bind_param("ssii", $user, $email, $role, $id);
}
$stmt->execute();

// ถ้าแก้ไขบัญชีของตัวเอง — อัปเดต session ทันที แล้ว redirect = รีเฟรชหน้า (ไม่ต้องออกแล้วเข้าใหม่)
if ($id == $_SESSION['user_id']) {
    $_SESSION['role'] = $role;
    $_SESSION['success'] = 'บันทึกแล้ว สิทธิ์อัปเดตแล้ว';
} elseif ($role == 10 && (int)$target_u['is_active'] !== 10) {
    $_SESSION['success'] = 'บันทึกแล้ว — ให้ผู้ใช้นั้นออกจากระบบแล้วเข้าสู่ระบบใหม่เพื่อให้สิทธิ์แอดมินมีผล';
} else {
    $_SESSION['success'] = 'บันทึกแล้ว';
}

header("Location: admin_panel.php" . (isset($_POST['inapp']) ? '?inapp=1' : ''));

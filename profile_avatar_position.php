<?php
/**
 * บันทึกตำแหน่งรูปในวงกลม (เลื่อนซ้าย-ขวา บน-ล่าง)
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (isset($_POST['inapp']) ? 'profile.php?inapp=1' : 'profile.php'));
    exit;
}
$redirect_profile = isset($_POST['inapp']) ? 'profile.php?inapp=1' : 'profile.php';

$user_id = (int) $_SESSION['user_id'];
$x = isset($_POST['x']) ? max(0, min(100, (int) $_POST['x'])) : 50;
$y = isset($_POST['y']) ? max(0, min(100, (int) $_POST['y'])) : 50;
$pos = $x . '% ' . $y . '%';

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_position'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare('UPDATE users SET avatar_position = ? WHERE id = ?');
    $stmt->bind_param('si', $pos, $user_id);
    $stmt->execute();
}
$_SESSION['profile_success'] = 'บันทึกตำแหน่งรูปแล้ว';
header('Location: ' . $redirect_profile);
exit;

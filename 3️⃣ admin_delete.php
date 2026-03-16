<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

// เช็กสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    die('No permission');
}

// ต้องเป็น POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

// ต้องมี id
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    die('No ID');
}

$id = (int)$_POST['id'];

// ห้ามลบตัวเอง หรือ Admin คนอื่น
$check_admin = $conn->query("SELECT is_active FROM users WHERE id = $id");
$u_data = $check_admin->fetch_assoc();

if ($id === (int)$_SESSION['user_id'] || $u_data['is_active'] == 10) {
    die('Cannot delete Admin accounts');
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

reindexUsers(); // Automate re-indexing to maintain sequential IDs

header("Location: admin_panel.php");
exit;

<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

// Admin เท่านั้น
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    die('No permission');
}

// POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

// ลบทุกคน ยกเว้นตัวเอง
$myId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("DELETE FROM users WHERE id != ?");
$stmt->bind_param("i", $myId);
$stmt->execute();

reindexUsers(); // Automate re-indexing to maintain sequential IDs

$_SESSION['success'] = "Deleted all users (except you).";
header("Location: admin_panel.php");
exit;

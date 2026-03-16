<?php
session_start();
require_once 'config.php';

// 1. Get Slug and admin_id from URL
$slug = $_GET['id'] ?? '';
$admin_id = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;

if ($slug === '') {
    header("Location: categories.php");
    exit();
}

// 2. Store in Session
$_SESSION['checkout_slug'] = $slug;
if ($admin_id > 0) {
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_active = 10");
    $chk->bind_param("i", $admin_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $_SESSION['selected_admin_id'] = $admin_id;
    }
    $chk->close();
}

// 3. Redirect to Clean URL (keep inapp=1 if in iframe)
$inapp = isset($_GET['inapp']) ? '?inapp=1' : '';
header("Location: checkout.php" . $inapp);
exit();
?>

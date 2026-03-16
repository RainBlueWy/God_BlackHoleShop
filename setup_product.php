<?php
session_start();
require_once 'config.php';

// 1. Get ID from URL
$id = $_GET['id'] ?? '';

if ($id === '') {
    header("Location: categories.php");
    exit();
}

// 2. Store in Session
$_SESSION['current_product_id'] = $id;

// 3. Redirect to Clean URL (keep inapp=1, bought=1 เพื่อให้ลูกค้าเห็นแชทแอดมินหลังซื้อ)
$inapp = isset($_GET['inapp']) ? '?inapp=1' : '';
$bought = isset($_GET['bought']) ? '&bought=1' : '';
header("Location: product.php" . $inapp . $bought);
exit();
?>

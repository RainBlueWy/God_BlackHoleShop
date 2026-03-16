<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';
require_once 'ticker_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$slug = $_SESSION['checkout_slug'] ?? '';
if ($slug === '') {
    header('Location: categories.php');
    exit;
}

// Ensure purchases table exists and has admin columns
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$ac = $conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
if ($ac->num_rows === 0) $conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL AFTER points_used, ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending' AFTER assigned_admin_id");

$stmt = $conn->prepare("SELECT * FROM products WHERE slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    unset($_SESSION['checkout_slug']);
    header('Location: categories.php');
    exit;
}

// ราคาเป็นพอยท์: ถ้ามีส่วนลด (sale_price) ใช้ราคาหลังลด ไม่ใช่ราคาเดิม
$price_str = $product['price'] ?? '';
$sale_str = isset($product['sale_price']) ? trim($product['sale_price']) : '';
$price_points = 0;
$use_price_display = $price_str; // สำหรับแสดงในหน้าตรง
if ($sale_str !== '') {
    if (stripos($sale_str, 'ฟรี') !== false) {
        $price_points = 0;
        $use_price_display = $sale_str;
    } else {
        $num = preg_replace('/[^0-9.]/', '', $sale_str);
        $price_points = $num !== '' ? (float)$num : 0;
        $use_price_display = $sale_str;
    }
} else {
    if (stripos($price_str, 'ฟรี') !== false) {
        $price_points = 0;
    } else {
        $num = preg_replace('/[^0-9.]/', '', $price_str);
        $price_points = $num !== '' ? (float)$num : 0;
    }
}

$max_slots = (int)($product['max_slots'] ?? 0);
$product_id = (int)$product['id'];

// ใช้ sold_count จาก products (แอดมินกดบันทึกแก้ไข = รีเซ็ตเป็น 0 ให้แสดง รับ X เหลือ X)
$sold_count = array_key_exists('sold_count', $product) ? (int)$product['sold_count'] : 0;
if (!array_key_exists('sold_count', $product)) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM purchases WHERE product_id = ?");
    $count_stmt->bind_param("i", $product_id);
    $count_stmt->execute();
    $sold_count = (int)$count_stmt->get_result()->fetch_assoc()['c'];
    $count_stmt->close();
}

$is_sold_out = ($max_slots > 0 && $sold_count >= $max_slots);

// POST: ยืนยันซื้อ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_buy'])) {
    if ($is_sold_out) {
        $_SESSION['error'] = 'สินค้าหมดแล้ว';
        header('Location: setup_product.php?id=' . urlencode($slug) . (isset($_GET['inapp']) ? '&inapp=1' : ''));
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $pts = $conn->query("SELECT points FROM users WHERE id = $user_id");
    $row = $pts ? $pts->fetch_assoc() : null;
    $user_points = $row ? (float)$row['points'] : 0;
    if ($price_points > 0 && $user_points < $price_points) {
        $_SESSION['error'] = 'พอยท์ไม่พอ (มี ' . number_format($user_points, 0) . ' พอยท์)';
        header('Location: checkout.php' . (isset($_GET['inapp']) ? '?inapp=1' : ''));
        exit;
    }
    $assigned_admin = isset($_SESSION['selected_admin_id']) ? (int)$_SESSION['selected_admin_id'] : null;
    if ($assigned_admin <= 0) {
        $assigned_admin = null;
        $fallback = $conn->query("SELECT id FROM users WHERE is_active = 10 ORDER BY id ASC LIMIT 1");
        if ($fallback && $row = $fallback->fetch_assoc()) $assigned_admin = (int)$row['id'];
    }
    $admin_status = 'pending';
    $ins = $conn->prepare("INSERT INTO purchases (user_id, product_id, points_used, assigned_admin_id, admin_status) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("iidis", $user_id, $product_id, $price_points, $assigned_admin, $admin_status);
    $ins->execute();
    $ins->close();
    // เพิ่ม sold_count ใน products เพื่อให้แสดง รับ X เหลือ Y ถูกต้อง
    $conn->query("UPDATE products SET sold_count = sold_count + 1 WHERE id = " . (int)$product_id);
    // เมื่อลูกค้าซื้อใหม่ ให้เปิดแชทกับแอดมินคนนี้ได้อีก (ล้างสถานะจบงาน)
    if ($assigned_admin > 0) {
        $clear = $conn->prepare("DELETE FROM chat_task_ended WHERE admin_id = ? AND customer_id = ?");
        if ($clear) {
            $clear->bind_param('ii', $assigned_admin, $user_id);
            $clear->execute();
            $clear->close();
        }
    }
    if ($price_points > 0) {
        $conn->query("UPDATE users SET points = points - $price_points WHERE id = $user_id");
    }
    unset($_SESSION['checkout_slug']);
    $_SESSION['success'] = 'ซื้อสำเร็จ! (ใช้ ' . number_format($price_points, 0) . ' พอยท์)';
    $redirect = 'setup_product.php?id=' . urlencode($slug) . (isset($_GET['inapp']) ? '&inapp=1' : '') . '&bought=1';
    header('Location: ' . $redirect);
    exit;
}

if ($is_sold_out) {
    $_SESSION['error'] = 'สินค้าหมดแล้ว (รับครบ ' . $max_slots . ' คนแล้ว)';
    header('Location: setup_product.php?id=' . urlencode($slug) . (isset($_GET['inapp']) ? '&inapp=1' : ''));
    exit;
}

$user_points = 0;
$uid = (int)$_SESSION['user_id'];
$up = $conn->query("SELECT points FROM users WHERE id = $uid");
if ($up && $r = $up->fetch_assoc()) $user_points = (float)$r['points'];
$inapp = isset($_GET['inapp']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ยืนยันซื้อ - <?= htmlspecialchars($product['name']) ?></title>
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>
</head>
<body>
    <div class="noise"></div>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="<?= $inapp ? 'app.php?page=categories' : 'categories.php' ?>" class="logo"><span>⚡</span> God_BlackHole</a>
            <a href="<?= $inapp ? 'app.php?page=categories' : 'categories.php' ?>" class="btn btn-secondary">ย้อนกลับ</a>
        </div>
    </nav>
    <div class="page-frame" style="max-width: 520px; margin-top: 5rem;">
    <div class="container" style="padding-top: 1rem; padding-bottom: 2rem; max-width: 480px;">
        <div class="glass-card" style="padding: 2rem;">
            <h2>ยืนยันซื้อด้วยพอยท์</h2>
            <p><strong><?= htmlspecialchars($product['name']) ?></strong></p>
            <p>ราคา: <?= htmlspecialchars($use_price_display) ?> (<?= number_format($price_points, 0) ?> พอยท์)</p>
            <p>พอยท์ของคุณ: <strong><?= number_format($user_points, 0) ?> พอยท์</strong></p>
            <?php if ($price_points > 0 && $user_points < $price_points): ?>
                <p style="color: var(--accent);">พอยท์ไม่พอ กรุณาเติมเงิน</p>
                <a href="<?= $inapp ? 'app.php?page=topup' : 'topup.php' ?>" class="btn btn-primary">เติมเงิน</a>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="confirm_buy" value="1">
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">ยืนยันซื้อ (ใช้ <?= number_format($price_points, 0) ?> พอยท์)</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    </div><!-- .page-frame -->
</body>
</html>

<?php
/**
 * คืนค่า HTML รายการสินค้าในหมวดหมู่ (สำหรับอัปเดตแบบไม่รีเฟรชหน้า)
 * ใช้กับ categories.php โดยดึงทุก 30 วินาที แล้วแทนที่ .products-grid
 */
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$is_inapp = isset($_GET['inapp']);

$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$purchase_counts = [];
$cr = $conn->query("SELECT product_id, COUNT(*) AS c FROM purchases GROUP BY product_id");
if ($cr && $cr->num_rows > 0) {
    while ($r = $cr->fetch_assoc()) $purchase_counts[(int)$r['product_id']] = (int)$r['c'];
}

$result = $conn->query("SELECT * FROM products ORDER BY updated_at DESC, id DESC");
if (!$result || $result->num_rows === 0) {
    echo '<p style="text-align:center;width:100%;color:#fff;">ไม่พบสินค้าในระบบ</p>';
    exit;
}

$inapp_q = $is_inapp ? '&inapp=1' : '';
while ($row = $result->fetch_assoc()) {
    $pid = isset($row['id']) ? (int)$row['id'] : 0;
    $max_slots = (int)($row['max_slots'] ?? 0);
    $sold = array_key_exists('sold_count', $row) ? (int)$row['sold_count'] : (int)($purchase_counts[$pid] ?? 0);
    $sold_out = ($max_slots > 0 && $sold >= $max_slots);
    $slug = htmlspecialchars(urlencode($row['slug'] ?? ''));
    $name = htmlspecialchars($row['name']);
    $category = htmlspecialchars($row['category']);
    $image = htmlspecialchars($row['image']);
    $p = $row['price'] ?? '';
    $sp = isset($row['sale_price']) ? trim($row['sale_price']) : '';
    if ($sp !== '') {
        $priceHtml = '<span class="price-display price-display--sale"><span class="price-line"><span class="price-original" style="text-decoration:line-through">' . htmlspecialchars($p) . '</span> <span class="price-remain">เหลือ</span></span><span class="price-sale">' . htmlspecialchars($sp) . '</span></span>';
    } else {
        $priceHtml = '<span class="price-display">' . htmlspecialchars($p) . '</span>';
    }
    $remaining = $max_slots > 0 ? max(0, $max_slots - $sold) : 0;
    ?>
<div class="product-card<?= $sold_out ? ' sold-out-card' : '' ?>" onclick="window.location.href='setup_product.php?id=<?= $slug ?><?= $inapp_q ?>'">
    <div class="product-image<?= $sold_out ? ' sold-out' : '' ?>">
        <img src="<?= $image ?>" alt="<?= $name ?>">
        <?php if ($sold_out): ?><span class="sold-out-sticker">สินค้าหมดแล้ว</span>
        <?php elseif (stripos($row['name'], 'Free') !== false): ?><span class="product-badge">ขายดี</span><?php endif; ?>
    </div>
    <div class="product-info">
        <h3 class="product-title"><?= $name ?></h3>
        <p class="product-description"><?= $category ?></p>
        <?php if ($max_slots > 0): ?><p class="product-slots" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.35rem;">รับ <?= $max_slots ?> คน · เหลือ <?= $remaining ?> คน</p><?php endif; ?>
        <div class="product-footer">
            <div><div class="product-price"><?= $priceHtml ?></div></div>
            <?php if ($sold_out): ?>
            <span class="btn btn-secondary" style="padding: 0.5rem 1.5rem; font-size: 0.875rem; cursor: default; opacity: 0.8;">สินค้าหมดแล้ว</span>
            <?php else: ?>
            <a href="setup_product.php?id=<?= $slug ?><?= $inapp_q ?>" class="btn btn-primary" style="padding: 0.5rem 1.5rem; font-size: 0.875rem;">ซื้อสินค้า</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
}

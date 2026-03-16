<?php
session_start();
require_once 'config.php';
require_once 'ticker_config.php';
if (isset($_SESSION['user_id']) && !isset($_GET['inapp'])) {
    header('Location: app.php?page=categories');
    exit;
}
$is_inapp = isset($_GET['inapp']);

// Fetch all products
$sql = "SELECT * FROM products ORDER BY updated_at DESC, id DESC";
$result = $conn->query($sql);

// นับจำนวนคนที่ซื้อแล้วต่อสินค้า (สำหรับแสดงสินค้าหมด)
$purchase_counts = [];
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// นับทุกการซื้อ (รวม pending) เพื่อให้พอครบจำนวนแล้วแสดงสินค้าหมดทันที
$cr = $conn->query("SELECT product_id, COUNT(*) AS c FROM purchases GROUP BY product_id");
if ($cr && $cr->num_rows > 0) {
    while ($r = $cr->fetch_assoc()) $purchase_counts[(int)$r['product_id']] = (int)$r['c'];
}

?>
<!DOCTYPE html>
<html lang="th" style="background-color:#0f0f12">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script>
    (function(){
        var t = localStorage.getItem('godblackhole-theme');
        var isLight = t === 'light';
        document.documentElement.setAttribute('data-theme', isLight ? 'light' : '');
        document.documentElement.style.backgroundColor = isLight ? '#f5f5f8' : '#0f0f12';
    })();
    </script>
    <meta name="description" content="หมวดหมู่สินค้า - ReaperX Hub Premium Scripts">
    <title>หมวดหมู่สินค้า - God_BlackHole</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.9">
    <?php if (isset($_SESSION['user_id'])): ?><link rel="stylesheet" href="points.css?v=3"><?php endif; ?>
    <?php include 'protection_header.php'; ?>
    <style>
        .product-image.sold-out { position: relative; }
        .product-image.sold-out img { filter: grayscale(1); opacity: 0.65; }
        .sold-out-sticker { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); border-radius: inherit; color: #fff; font-weight: 700; font-size: 1.1rem; text-shadow: 0 1px 2px #000; }
    </style>
</head>

<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">⚡</div>
                <span>God_BlackHole</span>
            </a>
            <ul class="nav-links">
                <li class="nav-drawer-close-li">
                    <button type="button" class="nav-drawer-close" id="navDrawerClose" aria-label="ปิดเมนู">× ปิดเมนู</button>
                </li>
                <li><a href="index.php">หน้าหลัก</a></li>
                <li><a href="categories.php">หมวดหมู่</a></li>
                <?php if (isset($_SESSION['user_id'])): ?><li><a href="topup_history.php">ประวัติเติมเงิน</a></li><?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
                    <li><a href="admin_panel.php" style="color: #d946ef; font-weight: bold;">จัดการระบบ (Admin)</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-buttons">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                    <span class="sun">☀</span>
                    <span class="moon">☽</span>
                </button>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (!empty($contact_admin) && !empty($contact_admin['url'])): ?>
                    <a href="<?= htmlspecialchars($contact_admin['url']) ?>" target="_blank" rel="noopener" class="btn" style="background:#eab308;color:#000;font-weight:600;">ติดต่อ <?= htmlspecialchars($contact_admin['name']) ?></a>
                    <?php endif; ?>
                    <div class="points-display">
                        <span class="points-amount">💰 <?php echo $user_points; ?> พอยท์</span>
                        <a href="topup.php" class="btn-topup">+ เติมเงิน</a>
                    </div>
                    <a href="profile.php" class="btn btn-primary">
                        <span>👤</span> <span class="btn-text">โปรไฟล์</span>
                    </a>
                <?php else: ?>
                    <a href="login.html" class="btn btn-secondary">เข้าสู่ระบบ</a>
                    <a href="register.html" class="btn btn-primary">สมัครสมาชิก</a>
                <?php endif; ?>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <span class="nav-end-spacer" aria-hidden="true"></span>
            </div>

        </div>
    </nav>

    <?php if (!empty($ticker_enabled)): ?>
    <div class="ticker-bar" id="tickerBar">
        <div class="ticker-label"><span class="ticker-icon" aria-hidden="true">🔊</span> ข่าวล่าสุด</div>
        <div class="ticker-wrap">
            <div class="ticker-inner">
                <span class="ticker-text"><?= htmlspecialchars($ticker_text) ?></span>
                <span class="ticker-text"><?= htmlspecialchars($ticker_text) ?></span>
            </div>
        </div>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
        <button type="button" class="ticker-close" id="tickerClose" aria-label="ปิดข่าว">×</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="page-frame">
    <!-- Categories Section -->
    <section class="products-section" style="padding-top: 120px;">
        <div class="container">
            <div class="section-header">
                <h2>หมวดหมู่: <span class="gradient-text">God_BlackHole</span></h2>
                <p>เครื่องมือในหมวดหมู่นี้</p>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
                    <a href="admin_products.php?inapp=1" class="btn btn-primary" style="margin-top: 15px;">➕ เพิ่มสินค้าใหม่ (Admin)</a>
                <?php endif; ?>
            </div> <br>

            <div class="products-grid" id="productsGrid" data-inapp="<?= $is_inapp ? '1' : '0' ?>">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()):
                        $pid = isset($row['id']) ? (int)$row['id'] : 0;
                        $max_slots = (int)($row['max_slots'] ?? 0);
                        $sold = array_key_exists('sold_count', $row) ? (int)$row['sold_count'] : (int)($purchase_counts[$pid] ?? 0);
                        $sold_out = ($max_slots > 0 && $sold >= $max_slots);
                    ?>
                <!-- Product Card -->
                <div class="product-card<?= $sold_out ? ' sold-out-card' : '' ?>" onclick="window.location.href='setup_product.php?id=<?= htmlspecialchars(urlencode($row['slug'] ?? '')) ?><?= $is_inapp ? '&inapp=1' : '' ?>'">
                    <div class="product-image<?= $sold_out ? ' sold-out' : '' ?>">
                        <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                        <?php if ($sold_out): ?>
                            <span class="sold-out-sticker">สินค้าหมดแล้ว</span>
                        <?php elseif (stripos($row['name'], 'Free') !== false): ?>
                            <span class="product-badge">ขายดี</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-title"><?= htmlspecialchars($row['name']) ?></h3>
                        <p class="product-description"><?= htmlspecialchars($row['category']) ?></p>
                        <?php if ($max_slots > 0): ?>
                            <?php $remaining = max(0, $max_slots - $sold); ?>
                            <p class="product-slots" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.35rem;">รับ <?= $max_slots ?> คน · เหลือ <?= $remaining ?> คน</p>
                        <?php endif; ?>
                        <div class="product-footer">
                            <div>
                                <div class="product-price"><?php
                                    $p = $row['price'] ?? '';
                                    $sp = isset($row['sale_price']) ? trim($row['sale_price']) : '';
                                    if ($sp !== '') {
                                        echo '<span class="price-display price-display--sale"><span class="price-line"><span class="price-original" style="text-decoration:line-through">' . htmlspecialchars($p) . '</span> <span class="price-remain">เหลือ</span></span><span class="price-sale">' . htmlspecialchars($sp) . '</span></span>';
                                    } else {
                                        echo '<span class="price-display">' . htmlspecialchars($p) . '</span>';
                                    }
                                ?></div>
                            </div>
                            <?php if ($sold_out): ?>
                                <span class="btn btn-secondary" style="padding: 0.5rem 1.5rem; font-size: 0.875rem; cursor: default; opacity: 0.8;">สินค้าหมดแล้ว</span>
                            <?php else: ?>
                                <a href="setup_product.php?id=<?= htmlspecialchars(urlencode($row['slug'] ?? '')) ?><?= $is_inapp ? '&inapp=1' : '' ?>" class="btn btn-primary"
                                    style="padding: 0.5rem 1.5rem; font-size: 0.875rem;">ซื้อสินค้า</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align:center;width:100%;color:#fff;">ไม่พบสินค้าในระบบ</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    </div><!-- .page-frame -->

    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    </script>
    <script src="script.js?v=2.0"></script>
    <script>
    (function(){
        var grid = document.getElementById('productsGrid');
        if (!grid) return;
        var inapp = grid.getAttribute('data-inapp') === '1';
        var apiUrl = 'api_categories_products.php' + (inapp ? '?inapp=1' : '');
        function refreshProducts() {
            var x = new XMLHttpRequest();
            x.open('GET', apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now(), true);
            x.onload = function() { if (x.status === 200 && x.responseText) grid.innerHTML = x.responseText; };
            x.send();
        }
        try {
            var es = new EventSource('sse_products.php');
            es.onmessage = function(e) { if (e.data === 'refresh') refreshProducts(); };
            es.onerror = function() { es.close(); setInterval(refreshProducts, 15000); };
        } catch (err) {
            setInterval(refreshProducts, 15000);
        }
    })();
    </script>
    <?php /* include 'music_player.php'; */ ?>
</body>

</html>
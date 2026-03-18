<?php
session_start();
require_once 'config.php';
require_once 'ticker_config.php';
if (isset($_SESSION['user_id']) && !isset($_GET['inapp'])) {
    header('Location: app.php?page=categories');
    exit;
}
$is_inapp = isset($_GET['inapp']);
$inapp_q = $is_inapp ? '&inapp=1' : '';
$inapp_q0 = $is_inapp ? '?inapp=1' : '';
function gbh_add_inapp(string $url, bool $is_inapp): string {
    if (!$is_inapp) return $url;
    return (strpos($url, '?') !== false) ? ($url . '&inapp=1') : ($url . '?inapp=1');
}

// สร้างตารางและคอลัมน์ถ้ายังไม่มี (รองรับก่อนรัน setup_categories_hierarchy.php)
$conn->query("CREATE TABLE IF NOT EXISTS main_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$conn->query("CREATE TABLE IF NOT EXISTS sub_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    main_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_main (main_id),
    UNIQUE KEY uq_main_slug (main_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$col = $conn->query("SHOW COLUMNS FROM products LIKE 'sub_category_id'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN sub_category_id INT(11) DEFAULT NULL AFTER category");
}

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

$main_slug = isset($_GET['main']) ? trim($_GET['main']) : '';
$sub_slug = isset($_GET['sub']) ? trim($_GET['sub']) : '';
$show_all = isset($_GET['all']) && $_GET['all'] === '1';

$page_mode = 'main'; // main | sub | products
$main_row = null;
$sub_row = null;
$products_result = null;
$sub_list = [];
$main_list = [];

if ($show_all) {
    $page_mode = 'products';
    $products_result = $conn->query("SELECT * FROM products ORDER BY updated_at DESC, id DESC");
} elseif ($main_slug !== '' && $sub_slug !== '') {
    $page_mode = 'products';
    $stmt = $conn->prepare("SELECT id, name, slug FROM main_categories WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $main_slug);
    $stmt->execute();
    $main_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($main_row) {
        $stmt = $conn->prepare("SELECT id, name, slug FROM sub_categories WHERE main_id = ? AND slug = ? LIMIT 1");
        $mid = (int)$main_row['id'];
        $stmt->bind_param("is", $mid, $sub_slug);
        $stmt->execute();
        $sub_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($sub_row) {
            $sid = (int)$sub_row['id'];
            if ($sub_slug === 'all') {
                // all = ดูสินค้าทั้งหมดภายใต้หมวดหลักนี้ (ทุกหมวดหมู่ย่อยของ main_id)
                $products_result = $conn->query("SELECT p.* FROM products p INNER JOIN sub_categories s ON s.id = p.sub_category_id WHERE s.main_id = $mid ORDER BY p.updated_at DESC, p.id DESC");
            } else {
                $products_result = $conn->query("SELECT * FROM products WHERE sub_category_id = $sid ORDER BY updated_at DESC, id DESC");
            }
        }
    }
    if (!$products_result) {
        $products_result = $conn->query("SELECT * FROM products ORDER BY updated_at DESC, id DESC");
    }
} elseif ($main_slug !== '') {
    $page_mode = 'sub';
    $stmt = $conn->prepare("SELECT id, name, slug, image FROM main_categories WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $main_slug);
    $stmt->execute();
    $main_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($main_row) {
        $mid = (int)$main_row['id'];
        // กันกรณีหมวดหลักถูกสร้างก่อนระบบ all: ให้สร้าง sub 'all' ให้อัตโนมัติ
        $conn->query("INSERT IGNORE INTO sub_categories (main_id, name, slug, sort_order) VALUES ($mid, 'ทั้งหมด', 'all', 0)");
        $res = $conn->query("SELECT id, name, slug, image FROM sub_categories WHERE main_id = $mid ORDER BY sort_order ASC, id ASC");
        if ($res) while ($r = $res->fetch_assoc()) $sub_list[] = $r;
    }
} else {
    $res = $conn->query("SELECT id, name, slug, image FROM main_categories ORDER BY sort_order ASC, id ASC");
    if ($res) while ($r = $res->fetch_assoc()) $main_list[] = $r;
    if (count($main_list) === 0) {
        // ไม่สร้างหมวดหมู่เริ่มต้นอัตโนมัติ (ผู้ดูแลจัดการเอง)
        $page_mode = 'products';
        $show_all = true;
        $products_result = $conn->query("SELECT * FROM products ORDER BY updated_at DESC, id DESC");
    }
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
    <meta name="description" content="หมวดหมู่สินค้า - God_BlackHole">
    <title>หมวดหมู่ - God_BlackHole</title>
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
        /* Category cards (เหมือนตัวอย่าง: รูป + แถบดำชื่อ) */
        .category-card { display: block; border-radius: 14px; overflow: hidden; text-decoration: none; color: inherit; transition: transform 0.18s ease, box-shadow 0.18s ease; border: 1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.20); }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(0,0,0,0.35); }
        .category-card .cat-media { position: relative; width: 100%; aspect-ratio: 16/9; background: rgba(255,255,255,0.05); }
        .category-card .cat-image { width: 100%; height: 100%; object-fit: cover; display: block; }
        .category-card .cat-fallback { width: 100%; height: 100%; display:flex; align-items:center; justify-content:center; background: linear-gradient(135deg, rgba(236,0,140,0.28), rgba(139,92,246,0.28)); color: rgba(255,255,255,0.95); font-size: 2.4rem; }
        .category-card .cat-bar { background: rgba(0,0,0,0.72); padding: 10px 14px; }
        .category-card .cat-title { font-size: 1.02rem; font-weight: 700; margin: 0; line-height: 1.2; color: rgba(255,255,255,0.95); text-shadow: 0 1px 2px rgba(0,0,0,0.55); }
        .category-card .cat-desc { display:none; }
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
        .breadcrumb { margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted); }
        .breadcrumb a { color: var(--text-secondary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
    </style>
</head>

<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>
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
                <li><a href="categories.php<?= $inapp_q0 ?>">หมวดหมู่</a></li>
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
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู">
                    <span></span><span></span><span></span>
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
    <section class="products-section" style="padding-top: 120px;">
        <div class="container">
            <div class="section-header">
                <h2>หมวดหมู่: <span class="gradient-text"><?= htmlspecialchars($show_all ? 'ดูสินค้าทั้งหมด' : ($main_row['name'] ?? 'God_BlackHole')) ?></span></h2>
                <?php if ($page_mode === 'main'): ?>
                <p>เลือกหมวดหมู่หลักที่ต้องการ</p>
                <?php elseif ($page_mode === 'sub' && $main_row): ?>
                <p><?= htmlspecialchars($main_row['name']) ?> — เลือกหมวดหมู่ย่อย</p>
                <?php elseif ($page_mode === 'products' && $main_row && $sub_row): ?>
                <p><?= htmlspecialchars($main_row['name']) ?> › <?= htmlspecialchars($sub_row['name']) ?></p>
                <?php else: ?>
                <p>เครื่องมือในหมวดหมู่นี้</p>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
                    <a href="admin_products.php?inapp=1" class="btn btn-primary" style="margin-top: 15px;">➕ เพิ่มสินค้าใหม่ (Admin)</a>
                    <a href="admin_categories.php?inapp=1" class="btn btn-secondary" style="margin-top: 15px;">จัดการหมวดหมู่หลัก/ย่อย</a>
                <?php endif; ?>
            </div>

            <?php if ($page_mode === 'main' && count($main_list) > 0): ?>
            <div class="breadcrumb">
                <a href="categories.php<?= $inapp_q0 ?>">หมวดหมู่</a>
            </div>
            <div class="categories-grid" id="mainCategoriesGrid">
                <!-- ดูดูสินค้าทั้งหมด (ไม่สนหมวด) -->
                <a href="categories.php?all=1<?= $inapp_q ?>" class="category-card">
                    <div class="cat-media">
                        <div class="cat-fallback">🛒</div>
                    </div>
                    <div class="cat-bar">
                        <h3 class="cat-title">ดูสินค้าทั้งหมด</h3>
                    </div>
                </a>
                <?php foreach ($main_list as $m): ?>
                <a href="categories.php?main=<?= htmlspecialchars(urlencode($m['slug'])) ?><?= $inapp_q ?>" class="category-card">
                    <div class="cat-media">
                        <?php if (!empty($m['image'])): ?>
                        <img src="<?= htmlspecialchars($m['image']) ?>" alt="" class="cat-image">
                        <?php else: ?>
                        <div class="cat-fallback">📁</div>
                        <?php endif; ?>
                    </div>
                    <div class="cat-bar">
                        <h3 class="cat-title"><?= htmlspecialchars($m['name']) ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <p style="margin-top: 1.5rem;">
                <a href="categories.php?all=1<?= $inapp_q ?>" class="btn btn-secondary">ดูสินค้าทั้งหมด</a>
            </p>

            <?php elseif ($page_mode === 'sub' && $main_row): ?>
            <div class="breadcrumb">
                <a href="categories.php<?= $inapp_q0 ?>">หมวดหมู่</a> › <a href="categories.php?main=<?= htmlspecialchars(urlencode($main_row['slug'])) ?><?= $inapp_q ?>"><?= htmlspecialchars($main_row['name']) ?></a>
            </div>
            <div style="margin-bottom: 1rem;">
                <a href="categories.php?main=<?= htmlspecialchars(urlencode($main_row['slug'])) ?>&sub=all<?= $inapp_q ?>" class="btn btn-secondary">ดูทั้งหมดในหมวดนี้</a>
            </div>
            <div class="categories-grid" id="subCategoriesGrid">
                <?php foreach ($sub_list as $s): ?>
                <a href="categories.php?main=<?= htmlspecialchars(urlencode($main_row['slug'])) ?>&sub=<?= htmlspecialchars(urlencode($s['slug'])) ?><?= $inapp_q ?>" class="category-card">
                    <div class="cat-media">
                        <?php if (!empty($s['image'])): ?>
                        <img src="<?= htmlspecialchars($s['image']) ?>" alt="" class="cat-image">
                        <?php else: ?>
                        <div class="cat-fallback">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="cat-bar">
                        <h3 class="cat-title"><?= htmlspecialchars($s['name']) ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <?php
            $result = $products_result;
            if (!$result) $result = $conn->query("SELECT * FROM products ORDER BY updated_at DESC, id DESC");
            ?>
            <div class="breadcrumb">
                <a href="categories.php<?= $inapp_q0 ?>">หมวดหมู่</a>
                <?php if ($main_row): ?> › <a href="categories.php?main=<?= htmlspecialchars(urlencode($main_row['slug'])) ?><?= $inapp_q ?>"><?= htmlspecialchars($main_row['name']) ?></a><?php endif; ?>
                <?php if ($sub_row): ?> › <span><?= htmlspecialchars($sub_row['name']) ?></span><?php endif; ?>
                <?php if ($show_all): ?> › <span>ดูสินค้าทั้งหมด</span><?php endif; ?>
            </div>
            <div class="products-grid" id="productsGrid" data-inapp="<?= $is_inapp ? '1' : '0' ?>" data-main="<?= htmlspecialchars($main_slug) ?>" data-sub="<?= htmlspecialchars($sub_slug) ?>" data-all="<?= $show_all ? '1' : '0' ?>">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()):
                        $pid = isset($row['id']) ? (int)$row['id'] : 0;
                        $max_slots = (int)($row['max_slots'] ?? 0);
                        $sold = array_key_exists('sold_count', $row) ? (int)$row['sold_count'] : (int)($purchase_counts[$pid] ?? 0);
                        $sold_out = ($max_slots > 0 && $sold >= $max_slots);
                    ?>
                <div class="product-card<?= $sold_out ? ' sold-out-card' : '' ?>" onclick="window.location.href='setup_product.php?id=<?= htmlspecialchars(urlencode($row['slug'] ?? '')) ?><?= $inapp_q ?>'">
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
                        <?php endif; ?>็
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
                                <a href="setup_product.php?id=<?= htmlspecialchars(urlencode($row['slug'] ?? '')) ?><?= $inapp_q ?>" class="btn btn-primary" style="padding: 0.5rem 1.5rem; font-size: 0.875rem;">ซื้อสินค้า</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align:center;width:100%;color:var(--text-muted);">ไม่พบสินค้าในหมวดนี้</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    </div>

    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    </script>
    <script src="script.js?v=2.0"></script>
    <script>
    (function(){
        var grid = document.getElementById('productsGrid');
        if (!grid) return;
        var inapp = grid.getAttribute('data-inapp') === '1';
        var main = grid.getAttribute('data-main') || '';
        var sub = grid.getAttribute('data-sub') || '';
        var all = grid.getAttribute('data-all') === '1';
        var apiUrl = 'api_categories_products.php?';
        if (main) apiUrl += 'main=' + encodeURIComponent(main) + '&';
        if (sub) apiUrl += 'sub=' + encodeURIComponent(sub) + '&';
        if (all) apiUrl += 'all=1&';
        if (inapp) apiUrl += 'inapp=1&';
        apiUrl = apiUrl.replace(/&$/, '');
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

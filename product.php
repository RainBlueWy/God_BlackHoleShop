<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';
require_once 'ticker_config.php';

/* ===============================
   ตรวจสอบ SESSION สินค้า
================================ */
if (!isset($_SESSION['current_product_id']) || trim($_SESSION['current_product_id']) === '') {
    header("Location: categories.php");
    exit();
}
if (isset($_SESSION['user_id']) && !isset($_GET['inapp'])) {
    header('Location: app.php?page=product');
    exit;
}

$slug = $_SESSION['current_product_id'];

/* ===============================
   ดึงข้อมูลสินค้า
================================ */
$stmt = $conn->prepare("SELECT * FROM products WHERE slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: categories.php");
    exit();
}

$max_slots = (int)($product['max_slots'] ?? 0);
$product_id = (int)$product['id'];
// ใช้ sold_count จาก products (แอดมินกดบันทึกแก้ไข = รีเซ็ตเป็น 0 → รับ X เหลือ X คน)
$sold_count = array_key_exists('sold_count', $product) ? (int)$product['sold_count'] : 0;
if (!array_key_exists('sold_count', $product)) {
    $conn->query("CREATE TABLE IF NOT EXISTS purchases (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_product (product_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM purchases WHERE product_id = ?");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $product_id);
        $count_stmt->execute();
        $sold_count = (int)$count_stmt->get_result()->fetch_assoc()['c'];
        $count_stmt->close();
    }
}
$is_sold_out = ($max_slots > 0 && $sold_count >= $max_slots);

// คอลัมน์สถานะแอดมิน (1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่) — ให้ลูกค้าเห็นใน dropdown
$col_av = @$conn->query("SHOW COLUMNS FROM users LIKE 'availability'");
if ($col_av && $col_av->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN availability TINYINT NOT NULL DEFAULT 1 COMMENT '1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่' AFTER is_active");
}
$availability_labels = [1 => 'ว่าง', 2 => 'ไม่ว่าง', 3 => 'ไม่อยู่'];

// ดึงแอดมินทั้งหมด — ว่าง(1) เลือกได้, ไม่ว่าง(2)/ไม่อยู่(3) แสดงใน dropdown เป็นสีดำและกดไม่ได้
$admins = [];
$ax = $conn->query("SELECT id, username, COALESCE(availability, 1) AS availability FROM users WHERE is_active = 10 ORDER BY username ASC");
if ($ax && $ax->num_rows > 0) {
    while ($a = $ax->fetch_assoc()) $admins[] = $a;
}
$has_available = false;
foreach ($admins as $a) { if ((int)($a['availability'] ?? 1) === 1) { $has_available = true; break; } }

/* ===============================
   จัดการ FEATURES (ลบค่าว่าง)
================================ */
$features = [];
if (!empty($product['features'])) {
    $decoded = json_decode($product['features'], true);
    if (is_array($decoded)) {
        $features = array_filter($decoded, function ($f) {
            return trim($f) !== '';
        });
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> - God_BlackHole</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.9">
    <?php if (isset($_SESSION['user_id'])): ?><link rel="stylesheet" href="points.css?v=3"><?php endif; ?>
    <?php include 'protection_header.php'; ?>

    <style>
        /* Definitive Fix for Mobile Sticky Image */
        @media (max-width: 1024px) {
            .product-gallery {
                position: relative !important;
                top: 0 !important;
                margin-bottom: var(--spacing-lg) !important;
                transform: none !important;
            }
            .product-detail-grid {
                display: block !important;
            }
            .product-gallery img {
                width: 100%;
                height: auto;
                border-radius: var(--radius-xl);
            }
        }
        .product-gallery.sold-out { position: relative; }
        .product-gallery.sold-out img { filter: grayscale(1); opacity: 0.65; }
        .product-gallery .sold-out-sticker { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); border-radius: var(--radius-xl); color: #fff; font-weight: 700; font-size: 1.25rem; text-shadow: 0 1px 3px #000; }
        /* Dropdown เลือกแอดมิน - ปรับสีให้เข้ากับธีม */
        select.admin-select {
            width: 100%;
            padding: 0.6rem 2rem 0.6rem 0.75rem;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239898a6' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
        }
        select.admin-select:hover { border-color: var(--accent); }
        select.admin-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-dim);
        }
        select.admin-select option {
            background: var(--bg-elevated);
            color: var(--text);
            padding: 0.5rem;
        }
    </style>
</head>

<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>

<!-- ===============================
        NAVBAR
================================ -->
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
                <li><a href="admin_panel.php" style="color:#d946ef;">Admin</a></li>
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

<!-- ===============================
        PRODUCT DETAIL
================================ -->
<section class="product-detail">
    <div class="container product-detail-grid">

        <!-- รูปสินค้า -->
        <div class="product-gallery<?= $is_sold_out ? ' sold-out' : '' ?>">
            <img src="<?= htmlspecialchars($product['image']) ?>"
                 alt="<?= htmlspecialchars($product['name']) ?>">
            <?php if ($is_sold_out): ?>
                <span class="sold-out-sticker">สินค้าหมดแล้ว</span>
            <?php endif; ?>
        </div>

        <!-- ข้อมูลสินค้า -->
        <div class="product-detail-info">

            <div class="badge">
                <?= htmlspecialchars($product['category']) ?>
            </div>

            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <?php if ($max_slots > 0): ?>
                <?php $remaining = max(0, $max_slots - $sold_count); ?>
                <p class="product-slots" style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 0.5rem;">รับ <?= $max_slots ?> คน · เหลือ <?= $remaining ?> คน</p>
            <?php endif; ?>

            <div class="price-box">
                <div class="price">
                    <span class="price-label">ราคา:</span>
                    <?php
                        $p = $product['price'] ?? '';
                        $sp = isset($product['sale_price']) ? trim($product['sale_price']) : '';
                        if ($sp !== '') {
                            echo '<span class="price-display price-display--sale"><span class="price-line"><span class="price-original" style="text-decoration:line-through">' . htmlspecialchars($p) . '</span> <span class="price-remain">เหลือ</span></span><span class="price-sale">' . htmlspecialchars($sp) . '</span></span>';
                        } else {
                            echo '<span class="price-display">' . htmlspecialchars($p) . '</span>';
                        }
                    ?>
                </div>

                <?php if ($is_sold_out): ?>
                    <span class="btn btn-secondary" style="width:100%; cursor: default; opacity: 0.8;">สินค้าหมดแล้ว</span>
                <?php elseif (empty($admins)): ?>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">ไม่มีแอดมินในขณะนี้</p>
                <?php else: ?>
                    <form method="get" action="setup_checkout.php" style="margin-top: 0.5rem;">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($product['slug']) ?>">
                        <?php if (isset($_GET['inapp'])): ?><input type="hidden" name="inapp" value="1"><?php endif; ?>
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label for="admin_id" style="font-size: 0.9rem; color: var(--text-muted);">เลือกแอดมินที่จะติดต่อ / รับออเดอร์ (ตัวเลือกสีดำ = ไม่ว่าง/ไม่อยู่ กดไม่ได้)</label>
                            <select name="admin_id" id="admin_id" class="form-control admin-select" <?= $has_available ? 'required' : '' ?>>
                                <option value="">— เลือกแอดมิน —</option>
                                <?php foreach ($admins as $ad):
                                    $av = isset($ad['availability']) ? (int)$ad['availability'] : 1;
                                    $statusText = $availability_labels[$av] ?? 'ว่าง';
                                    $is_available = ($av === 1);
                                ?>
                                    <?php if ($is_available): ?>
                                        <option value="<?= (int)$ad['id'] ?>"><?= htmlspecialchars($ad['username']) ?> (<?= $statusText ?>)</option>
                                    <?php else: ?>
                                        <option disabled style="color:#888;background:#1a1a1a;"><?= htmlspecialchars($ad['username']) ?> (<?= $statusText ?>)</option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($has_available): ?>
                            <button type="submit" class="btn btn-primary" style="width:100%;">🛒 ซื้อสินค้า</button>
                        <?php else: ?>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">ไม่มีแอดมินว่างในขณะนี้ กรุณาลองใหม่ภายหลัง</p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

            <!-- ===============================
                รายละเอียดสินค้า
            ================================ -->
            <?php if (trim($product['description']) !== ''): ?> 
    <div class="glass-card" style="margin-bottom: 1.25rem;">
        <h3>📋 รายละเอียดสินค้า</h3>
        <p style="padding-left: 12px;">
            <?= nl2br(htmlspecialchars($product['description'])) ?>
        </p>
    </div>
<?php endif; ?>


            <!-- ===============================
                ฟีเจอร์เด่น
            ================================ -->
            <?php if (!empty($features)): ?>
            <div class="glass-card" style="margin-bottom: 1.25rem;">
                <h3>✨ ฟีเจอร์เด่น</h3>
                <ul style="padding-left: 12px;">
                    <?php foreach ($features as $f): ?>
                        <li><?= htmlspecialchars($f) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php
            $script_content = trim((string)($product['script_content'] ?? ''));
            if ($script_content !== ''):
            ?>
            <div class="glass-card" id="scriptDiscordCard" style="margin-bottom: 1.25rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                    <h3 style="margin: 0;">Server Vip</h3>
                    <button type="button" class="btn btn-secondary btn-copy-script" style="padding: 0.4rem 0.75rem; font-size: 0.875rem;" title="คัดลอก">📋 คัดลอก</button>
                </div>
                <textarea id="scriptCopySource" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;"><?= htmlspecialchars($script_content) ?></textarea>
                <div class="script-discord-content" style="padding-left: 12px; margin-top: 1rem;">
                    <?= nl2br(htmlspecialchars($script_content)) ?>
                </div>
            </div>
            <script>
            (function(){
                var card = document.getElementById('scriptDiscordCard');
                if (!card) return;
                var btn = card.querySelector('.btn-copy-script');
                var src = document.getElementById('scriptCopySource');
                if (!btn || !src) return;
                btn.addEventListener('click', function() {
                    var text = src.value || '';
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() { copyDone(btn); }).catch(function() { copyFallback(); });
                    } else { copyFallback(); }
                });
                function copyDone(el) { var t = el.textContent; el.textContent = 'คัดลอกแล้ว'; el.disabled = true; setTimeout(function(){ el.textContent = t; el.disabled = false; }, 1500); }
                function copyFallback() {
                    src.style.position = 'fixed'; src.style.left = '0'; src.style.top = '0'; src.style.width = '2em'; src.style.height = '2em'; src.style.opacity = '0.01';
                    src.select(); src.setSelectionRange(0, 99999);
                    try { document.execCommand('copy'); copyDone(btn); } catch (e) {}
                    src.style.position = 'absolute'; src.style.left = '-9999px'; src.style.width = '1px'; src.style.height = '1px';
                }
            })();
            </script><br>
            <?php endif; ?>

        </div>
    </div>
</section>

    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    </script>
    <script src="script.js?v=2.0"></script>
    <?php /* include 'music_player.php'; */ ?>
</body>

</html>

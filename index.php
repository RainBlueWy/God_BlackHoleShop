<?php
session_start();
require_once 'config.php';
require_once 'ticker_config.php';
if (isset($_SESSION['user_id']) && !isset($_GET['inapp'])) {
    header('Location: app.php?page=index');
    exit;
}
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
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
    <meta name="description" content="God_BlackHole - Premium Scripts และเครื่องมือคุณภาพสูงสำหรับ Roblox">
    <title>God_BlackHole - Premium Scripts Made By GodBlackHole</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.8">
    <?php if (isset($_SESSION['user_id'])): ?><link rel="stylesheet" href="points.css?v=3"><?php endif; ?>
    <?php include 'protection_header.php'; ?>


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
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู">
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
        <div class="ticker-label">
            <span class="ticker-icon" aria-hidden="true">🔊</span>
            ข่าวล่าสุด
        </div>
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
    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-bg">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>
                        God_BlackHole<br>
                        <span class="gradient-text">PREMIUM Shop ซื้อขาย</span><br>
                        MADE BY GodBlackHole
                    </h1>
                    <p>
                        ค้นพบคอลเลกชันสคริปต์ Roblox ที่ทรงพลังและมีคุณภาพสูง
                        พร้อมฟีเจอร์ครบครันที่จะยกระดับประสบการณ์การเล่นเกมของคุณให้ดีขึ้นอย่างเหนือชั้น
                    </p>
                    <div class="hero-buttons">
                        <a href="categories.php" class="btn btn-primary"><span class="btn-icon" aria-hidden="true">🛒</span><span>ซื้อสินค้าเลย!</span></a>
                        <a href="https://discord.gg/nBuwhHte" class="btn btn-secondary"><span class="btn-icon" aria-hidden="true">📱</span><span>ติดต่อเรา</span></a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="hero-preview.png" alt="ReaperX Hub Preview" class="hero-preview" id="heroPreview">
                </div>
            </div>
        </div>
        <div class="scroll-hint" id="scrollHint">
            <span>เลื่อนลง</span>
            <div class="scroll-arrow"></div>
        </div>
    </section>

    <!-- Featured Products Section -->
   <!-- <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h2>หมวดหมู่ <span class="gradient-text">สินค้า</span></h2>
                <p>สคริปต์และเครื่องมือคุณภาพสูงที่ได้รับความนิยมสูงสุด</p>
            </div>

            <div class="products-grid"> 
                <!-- Product Card 1 -->
                <!-- <div class="product-card" onclick="window.location.href='product.php?id=limited'">
                    <div class="product-image">
                        <img src="product-limited.png" alt="ReaperX Limited Edition" id="productLimited">
                        <span class="product-badge">ขายดี</span>
                    </div>
                    <div class="product-info">
                        <h3 class="product-title">ReaperX | Limited Edition</h3>
                        <p class="product-description">หมวดหมู่สุดพิเศษสำหรับสมาชิก</p>
                        <div class="product-footer">
                            <div>
                                <div class="product-price">119 บาท</div>
                                <div class="product-duration">สคริปต์ 7 วัน</div>
                            </div>
                        </div>
                    </div>
                </div>

                
                

                

                
            </div>
        </div>
    </section>-->

    </div><!-- .page-frame -->

    <!-- สลับธีมทำงานแยกจาก script หลัก เพื่อให้กดได้แน่นอน -->
    <script>
    (function() {
        var themeKey = 'godblackhole-theme';
        var el = document.getElementById('themeToggle');
        function applyTheme(theme) {
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
        }
        function getStored() { return localStorage.getItem(themeKey); }
        function saveTheme(theme) { localStorage.setItem(themeKey, theme); }
        if (getStored()) applyTheme(getStored());
        else applyTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (el) {
            el.addEventListener('click', function() {
                var isLight = document.documentElement.getAttribute('data-theme') === 'light';
                if (isLight) { saveTheme('dark'); applyTheme('dark'); }
                else { saveTheme('light'); applyTheme('light'); }
            });
        }
    })();
    </script>
    <script src="script.js?v=2.0"></script>
    <?php /* include 'music_player.php'; */ ?>
</body>

</html>
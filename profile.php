<?php
session_start();
require_once 'config.php';
require_once 'ticker_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=profile');
    exit;
}
$is_inapp = true; // ใช้ในฟอร์มเพื่อส่ง inapp กลับเมื่อ redirect
$user_id = (int) $_SESSION['user_id'];
$user_avatar = null;
$avatar_position = '50% 50%';
$avatar_scale = 100;
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
$has_avatar_col = $res && $res->num_rows > 0;
$res2 = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_position'");
$has_position_col = $res2 && $res2->num_rows > 0;
if ($has_avatar_col && !$has_position_col) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar_position VARCHAR(30) DEFAULT '50% 50%' COMMENT 'ตำแหน่งรูปในวงกลม' AFTER avatar");
    $has_position_col = true;
}
if ($has_avatar_col) {
    $sel = 'SELECT avatar' . ($has_position_col ? ', avatar_position' : '');
    $res_scale = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_scale'");
    if ($res_scale && $res_scale->num_rows > 0) $sel .= ', avatar_scale';
    $stmt = $conn->prepare($sel . ' FROM users WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['avatar']) && file_exists(__DIR__ . '/' . $row['avatar'])) {
            $user_avatar = $row['avatar'];
        }
        if ($has_position_col && !empty($row['avatar_position'])) {
            $avatar_position = $row['avatar_position'];
        }
        if (!empty($row['avatar_scale'])) {
            $avatar_scale = max(80, min(150, (int) $row['avatar_scale']));
        }
    }
}
$pos_parts = preg_match('/^(\d+)%\s+(\d+)%$/', trim($avatar_position), $m) ? [(int)$m[1], (int)$m[2]] : [50, 50];
$avatar_x = $pos_parts[0];
$avatar_y = $pos_parts[1];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>โปรไฟล์ของคุณ - God_BlackHole</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>


    <style>
        .profile-container {
            padding-top: 100px; /* Space for fixed navbar */
            padding-bottom: 50px;
            min-height: 100vh;
        }
        .profile-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--spacing-2xl);
            max-width: 800px;
            margin: 0 auto;
            color: var(--text-primary);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: var(--spacing-lg);
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--pink-primary), #a855f7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 10px 20px rgba(236, 0, 140, 0.3);
            flex-shrink: 0;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .profile-avatar:hover { transform: scale(1.05); box-shadow: 0 12px 28px rgba(236, 0, 140, 0.4); }
        .profile-avatar.clickable {
            position: relative;
        }
        .profile-avatar.clickable::after {
            content: 'เปลี่ยนรูป';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.75);
            color: #fff;
            font-size: 0.65rem;
            padding: 0.25rem;
            text-align: center;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: 50% 50%;
            pointer-events: none;
        }
        .profile-avatar.dragging { cursor: grab; }
        .profile-avatar.dragging:active { cursor: grabbing; }
        .avatar-position-section { margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: var(--radius-md); }
        .avatar-position-section label { display: block; font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
        .avatar-position-section input[type="range"] { width: 100%; max-width: 200px; margin-bottom: 0.5rem; }
        .avatar-position-section .pos-value { font-size: 0.85rem; color: var(--text-muted); }
        .avatar-position-section button[type="submit"] { margin-top: 0.5rem; padding: 0.4rem 0.8rem; background: var(--accent); color: #0f0f12; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; }
        .profile-avatar-wrap {
            position: relative;
        }
        .profile-avatar-form {
            margin-top: var(--spacing-md);
        }
        .profile-avatar-form .form-label {
            display: block;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .profile-avatar-form input[type="file"] {
            width: 100%;
            max-width: 320px;
            font-size: 0.9rem;
            padding: 0.5rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.05);
            color: inherit;
        }
        .profile-avatar-form .btn-select-file {
            margin-top: 0.5rem;
            margin-right: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            color: var(--text-primary);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
        }
        .profile-avatar-form .btn-select-file:hover {
            background: rgba(255,255,255,0.15);
            border-color: var(--accent);
        }
        .profile-avatar-form button[type="submit"] {
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--accent);
            color: #0f0f12;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
        }
        .profile-avatar-form button[type="submit"]:hover { opacity: 0.9; }
        .profile-avatar-form .file-name { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem; }
        .profile-msg { margin-bottom: var(--spacing-md); padding: 0.75rem; border-radius: var(--radius-sm); }
        .profile-msg.success { background: rgba(74, 222, 128, 0.2); color: #4ade80; }
        .profile-msg.error { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .profile-info h1 {
            margin: 0;
            font-size: 2rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: var(--spacing-xs);
        }
        .profile-details {
            display: grid;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }
        .detail-item {
            background: rgba(255, 255, 255, 0.05);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .detail-item:hover {
            transform: translateY(-2px);
            border-color: rgba(236, 0, 140, 0.3);
        }
        .detail-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-xs);
        }
        .detail-value {
            font-size: 1.125rem;
            font-weight: 500;
        }
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
                <li><a href="topup_history.php">ประวัติเติมเงิน</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
                    <li><a href="admin_panel.php" style="color: #d946ef; font-weight: bold;">จัดการระบบ (Admin)</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-buttons">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                    <span class="sun">☀</span>
                    <span class="moon">☽</span>
                </button>
                <?php if (!empty($contact_admin) && !empty($contact_admin['url'])): ?>
                <a href="<?= htmlspecialchars($contact_admin['url']) ?>" target="_blank" rel="noopener" class="btn" style="background:#eab308;color:#000;font-weight:600;">ติดต่อ <?= htmlspecialchars($contact_admin['name']) ?></a>
                <?php endif; ?>
                <a href="logout.php" target="_top" class="btn btn-secondary">ออกจากระบบ</a>
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

    <div class="container profile-container">
        <div class="profile-card">
            <?php if (!empty($_SESSION['profile_success'])): ?>
                <div class="profile-msg success"><?= htmlspecialchars($_SESSION['profile_success']); unset($_SESSION['profile_success']); ?></div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['profile_error'])): ?>
                <div class="profile-msg error"><?= htmlspecialchars($_SESSION['profile_error']); unset($_SESSION['profile_error']); ?></div>
            <?php endif; ?>
            <div class="profile-header">
                <div class="profile-avatar-wrap">
                    <form class="profile-avatar-form" action="profile_avatar_upload.php" method="post" enctype="multipart/form-data" id="avatarForm">
                        <?php if (!empty($is_inapp)) { ?><input type="hidden" name="inapp" value="1"><?php } ?>
                        <input type="hidden" name="x" id="uploadPosX" value="50">
                        <input type="hidden" name="y" id="uploadPosY" value="50">
                        <input type="hidden" name="scale" id="uploadScale" value="100">
                        <input type="file" id="avatarFile" name="avatar" accept="image/*" aria-label="เลือกรูปภาพในเครื่อง" style="position:absolute;width:0;height:0;opacity:0;overflow:hidden;">
                        <label for="avatarFile" class="profile-avatar clickable" id="profileAvatarLabel" title="คลิกเพื่อเปลี่ยนรูป">
                            <?php if ($user_avatar): ?>
                                <img id="profileAvatarImg" src="<?= htmlspecialchars($user_avatar) ?>?t=<?= time() ?>" alt="รูปโปรไฟล์" style="object-position: <?= htmlspecialchars($avatar_position) ?>; transform: scale(<?= (int)$avatar_scale / 100 ?>); transform-origin: center;">
                            <?php else: ?>
                                <span id="profileAvatarLetter"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                                <img id="profileAvatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;object-position:50% 50%;pointer-events:none;">
                            <?php endif; ?>
                        </label>
                        <label class="form-label" for="avatarFile" style="display:block;margin-top:0.5rem;cursor:pointer;">คลิกที่วงรูปหรือปุ่มด้านล่างเพื่อเลือกรูปจากเครื่อง</label>
                        <div class="file-name" id="avatarFileName"></div>
                        <!-- ขั้นตอนแก้ไขรูป (เหมือน Edit Image) -->
                        <div id="avatarEditStep" style="display:none;margin-top:1rem;background:rgba(255,255,255,0.05);border-radius:16px;padding:1.25rem;border:1px solid rgba(255,255,255,0.1);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                                <h3 style="margin:0;font-size:1.1rem;">แก้ไขรูป</h3>
                                <div style="display:flex;gap:0.5rem;">
                                    <button type="button" id="btnCancelEdit" class="btn btn-secondary" style="padding:0.4rem 0.8rem;" title="ยกเลิก">✕ ยกเลิก</button>
                                    <button type="submit" id="btnConfirmAvatar" class="btn btn-primary" style="padding:0.4rem 0.8rem;" title="ตกลง">✓ ตกลง</button>
                                </div>
                            </div>
                            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.75rem;">ลากรูปเพื่อจัดตำแหน่ง ปรับซูมด้านล่าง แล้วกดตกลง</p>
                            <div id="avatarEditCrop" style="width:260px;height:260px;margin:0 auto;border-radius:50%;overflow:hidden;background:#222;position:relative;touch-action:none;">
                                <img id="avatarEditImg" src="" alt="" style="width:100%;height:100%;object-fit:cover;object-position:50% 50%;pointer-events:none;display:block;">
                            </div>
                            <div style="margin-top:1rem;">
                                <label style="font-size:0.85rem;color:var(--text-muted);">ซูม: <span id="zoomVal">100</span>%</label>
                                <input type="range" id="avatarZoomSlider" min="80" max="150" value="100" style="width:100%;max-width:280px;margin-top:0.25rem;">
                            </div>
                        </div>
                        <div id="avatarSelectStep" style="margin-top: 0.5rem;">
                            <button type="button" class="btn-select-file" id="btnSelectAvatar">เลือกรูปภาพในเครื่อง</button>
                        </div>
                    </form>
                </div>
                <div class="profile-info">
                    <h1>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                    <p style="color: var(--text-secondary);">สมาชิก God_BlackHole</p>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">ชื่อผู้ใช้ (Username)</div>
                    <div class="detail-value"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">อีเมล (Email)</div>
                    <div class="detail-value"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">สถานะบัญชี (Account Status)</div>
                    <div class="detail-value" style="color: #4ade80;">Active (ใช้งานได้ปกติ)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">รหัสสมาชิก (User ID)</div>
                    <div class="detail-value">#<?php echo str_pad($_SESSION['user_id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    (function(){
        var form = document.getElementById('avatarForm');
        var btn = document.getElementById('btnSelectAvatar');
        var input = document.getElementById('avatarFile');
        var nameEl = document.getElementById('avatarFileName');
        var editStep = document.getElementById('avatarEditStep');
        var selectStep = document.getElementById('avatarSelectStep');
        var btnCancelEdit = document.getElementById('btnCancelEdit');
        var img = document.getElementById('profileAvatarImg');
        var editImg = document.getElementById('avatarEditImg');
        var editCrop = document.getElementById('avatarEditCrop');
        var zoomSlider = document.getElementById('avatarZoomSlider');
        var zoomVal = document.getElementById('zoomVal');
        var letter = document.getElementById('profileAvatarLetter');
        var uploadPosX = document.getElementById('uploadPosX');
        var uploadPosY = document.getElementById('uploadPosY');
        var uploadScale = document.getElementById('uploadScale');
        var circle = document.getElementById('profileAvatarLabel');
        var previewObjectURL = null;
        var savedAvatarSrc = img && img.src ? img.src : '';

        if (btn && input) btn.addEventListener('click', function(e) { e.preventDefault(); input.click(); });
        input.addEventListener('change', function() {
            var f = this.files && this.files[0];
            if (!f || !editImg) return;
            if (previewObjectURL) URL.revokeObjectURL(previewObjectURL);
            previewObjectURL = URL.createObjectURL(f);
            nameEl.textContent = f.name;
            editImg.src = previewObjectURL;
            editImg.style.objectPosition = '50% 50%';
            editImg.style.transform = 'scale(1)';
            if (uploadPosX) uploadPosX.value = 50;
            if (uploadPosY) uploadPosY.value = 50;
            if (uploadScale) uploadScale.value = 100;
            if (zoomSlider) zoomSlider.value = 100;
            if (zoomVal) zoomVal.textContent = '100';
            if (editStep) editStep.style.display = 'block';
            if (selectStep) selectStep.style.display = 'none';
        });
        if (btnCancelEdit) {
            btnCancelEdit.addEventListener('click', function() {
                if (previewObjectURL) { URL.revokeObjectURL(previewObjectURL); previewObjectURL = null; }
                input.value = '';
                nameEl.textContent = '';
                if (img) {
                    if (savedAvatarSrc) { img.src = savedAvatarSrc; img.style.display = ''; }
                    else { img.src = ''; img.style.display = 'none'; }
                }
                if (letter) letter.style.display = '';
                if (editStep) editStep.style.display = 'none';
                if (selectStep) selectStep.style.display = 'block';
            });
        }

        function setEditPos(x, y) {
            x = Math.max(0, Math.min(100, Math.round(x)));
            y = Math.max(0, Math.min(100, Math.round(y)));
            if (editImg) editImg.style.objectPosition = x + '% ' + y + '%';
            if (uploadPosX) uploadPosX.value = x;
            if (uploadPosY) uploadPosY.value = y;
        }
        function setEditScale(s) {
            s = Math.max(80, Math.min(150, parseInt(s, 10) || 100));
            if (uploadScale) uploadScale.value = s;
            if (editImg) editImg.style.transform = 'scale(' + (s / 100) + ')';
            if (zoomVal) zoomVal.textContent = s;
        }
        if (editCrop && editImg && uploadPosX && uploadPosY) {
            var startX, startY, startPosX, startPosY, dragged;
            function onDown(e) {
                var t = e.touches ? e.touches[0] : e;
                startX = t.clientX; startY = t.clientY;
                startPosX = parseInt(uploadPosX.value, 10) || 50;
                startPosY = parseInt(uploadPosY.value, 10) || 50;
                dragged = false;
                e.preventDefault();
            }
            function onMove(e) {
                if (startX == null) return;
                var t = e.touches ? e.touches[0] : e;
                var dx = t.clientX - startX, dy = t.clientY - startY;
                if (!dragged && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) dragged = true;
                if (dragged) {
                    e.preventDefault();
                    var rect = editCrop.getBoundingClientRect();
                    setEditPos(startPosX - (dx / rect.width) * 100, startPosY - (dy / rect.height) * 100);
                }
            }
            function onUp() { startX = null; }
            editCrop.addEventListener('mousedown', onDown);
            editCrop.addEventListener('touchstart', onDown, { passive: false });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('mouseup', onUp);
            document.addEventListener('touchend', onUp);
        }
        if (zoomSlider && zoomVal) {
            zoomSlider.addEventListener('input', function() {
                setEditScale(this.value);
            });
        }

    })();
    </script>
    <script src="script.js?v=2.0"></script>
    <?php /* include 'music_player.php'; */ ?>
</body>
</html>

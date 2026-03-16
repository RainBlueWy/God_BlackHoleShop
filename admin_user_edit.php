<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

// Check Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['inapp'])) {
    $rid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    header('Location: app.php?page=admin_user_edit' . ($rid ? '&id=' . $rid : ''));
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: admin_panel.php");
    exit();
}

// Ensure contact_line exists (สำหรับแอดมินใส่ Line/ลิงก์ติดต่อ)
$cx = $conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
if ($cx->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL COMMENT 'Line ID หรือลิงก์ติดต่อ (แอดมิน)' AFTER is_active");
}

// Get User Data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit User - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>

    <style>
        .edit-container {
            padding-top: 120px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        .form-control {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            color: white;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="admin_panel.php" class="logo">
                <div class="logo-icon">⚡</div>
                <span>Admin Panel</span>
            </a>
            <div class="nav-buttons">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                    <span class="sun">☀</span>
                    <span class="moon">☽</span>
                </button>
                <a href="admin_panel.php" class="btn btn-secondary">ย้อนกลับ</a>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <span class="nav-end-spacer" aria-hidden="true"></span>
            </div>
        </div>
    </nav>


    <div class="container edit-container">
        <div class="glass-card" style="padding: 30px;">
            <h1>แก้ไขผู้ใช้ #<?php echo $user['id']; ?></h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="admin_action.php?action=edit_user" method="POST">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                
                <!-- ID Editing (Dangerous but requested) -->
                <div class="form-group">
                    <label>User ID (ระวังการแก้ไข!)</label>
                    <input type="number" name="new_id" class="form-control" value="<?php echo $user['id']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Role (0=แบน, 1=สมาชิก, 10=admin)</label>
                    <select name="role" class="form-control">
                        <option value="0" <?php if($user['is_active'] == 0) echo 'selected'; ?>>0 - ระงับการใช้งาน (Banned)</option>
                        <option value="1" <?php if($user['is_active'] == 1) echo 'selected'; ?>>1 - สมาชิกทั่วไป (User)</option>
                        <option value="10" <?php if($user['is_active'] == 10) echo 'selected'; ?>>10 - ผู้ดูแลระบบ (Admin)</option>
                    </select>
                </div>

                <div class="form-group" id="contactLineGroup" style="<?= (($user['is_active'] ?? 0) == 10) ? '' : 'display:none;' ?>">
                    <label>Line / ลิงก์ติดต่อ (ลูกค้าจะกดปุ่มติดต่อแอดมินไปที่ลิงก์นี้ — เฉพาะแอดมิน)</label>
                    <input type="text" name="contact_line" class="form-control" value="<?php echo htmlspecialchars($user['contact_line'] ?? ''); ?>" placeholder="เช่น Line ID หรือ https://...">
                </div>
                <script>document.querySelector('select[name=role]').addEventListener('change',function(){ var g=document.getElementById('contactLineGroup'); g.style.display=this.value==='10'?'block':'none'; });</script>

                <div class="form-group">
                    <label>เปลี่ยนรหัสผ่าน (เว้นว่างถ้าไม่เปลี่ยน)</label>
                    <input type="text" name="password" class="form-control" placeholder="กรอกรหัสผ่านใหม่">
                </div>

                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">บันทึกการแก้ไข</button>
                </div>
            </form>
            
            <hr style="margin: 20px 0; border-color: rgba(255,255,255,0.1);">
            
            <form action="admin_action.php?action=delete_user" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้? ไม่สามารถกู้คืนได้!');">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <button type="submit" class="btn btn-danger" style="width: 100%;">ลบผู้ใช้นี้ (Delete User)</button>
            </form>
        </div>
    </div>
    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    </script>
    <script src="script.js?v=2.0"></script>
    <?php /* include 'music_player.php'; */ ?>
</body>

</html>

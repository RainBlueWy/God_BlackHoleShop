<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

// ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: profile.php");
    exit();
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=admin_panel');
    exit;
}

// คอลัมน์สถานะ (1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่) สำหรับกรอง
$col_av = $conn->query("SHOW COLUMNS FROM users LIKE 'availability'");
if ($col_av && $col_av->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN availability TINYINT NOT NULL DEFAULT 1 COMMENT '1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่' AFTER is_active");
}

// ค้นหา
$search = $_GET['search'] ?? '';
$where = " 1=1 ";
$params = [];
$types = "";

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (username LIKE '%$s%' OR email LIKE '%$s%') ";
}

// ดึงผู้ใช้ทั้งหมด
$sql = "SELECT * FROM users WHERE $where ORDER BY id ASC";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// โหลดแถบข่าว (ticker)
$ticker_content = '';
$ticker_active = 0;
$ticker_table = $conn->query("SHOW TABLES LIKE 'ticker'");
if ($ticker_table && $ticker_table->num_rows > 0) {
    $tr = $conn->query("SELECT content, is_active FROM ticker WHERE id = 1 LIMIT 1");
    if ($tr && $row = $tr->fetch_assoc()) {
        $ticker_content = $row['content'];
        $ticker_active = (int) $row['is_active'];
    }
}

// สถานะของแอดมินคนที่ล็อกอิน (1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่) — ใช้แสดงในฟอร์ม "สถานะของฉัน"
$my_availability = 1;
$my_av_row = $conn->query("SELECT COALESCE(availability, 1) AS av FROM users WHERE id = " . (int) $_SESSION['user_id'] . " LIMIT 1");
if ($my_av_row && $row = $my_av_row->fetch_assoc()) {
    $v = (int) $row['av'];
    if (in_array($v, [1, 2, 3], true)) $my_availability = $v;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Admin Panel - God_BlackHole</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css?v=1.5">
<?php include 'protection_header.php'; ?>


<style>
.admin-container{
    padding-top:120px;
    padding-bottom:50px;
    min-height:100vh;
}

/* Original Table Styles Restored */
.admin-table{
    width:100%;
    border-collapse:collapse;
    background:var(--card-bg);
    border-radius:var(--radius-lg);
    margin-top:20px;
}

.admin-table th,
.admin-table td{
    padding:15px;
    border-bottom:1px solid rgba(255,255,255,.1);
    text-align: left;
}

.admin-table th{
    background:rgba(236,0,140,.25);
    color: #fff;
}

/* Internal overrides to ensure scroll works */
.table-responsive {
    z-index: 1;
    position: relative;
    overflow-x: auto !important;
    display: block;
    width: 100%;
    -webkit-overflow-scrolling: touch;
    touch-action: auto; /* Changed to auto for better browser handling */
    border-radius: var(--radius-lg);
    background: var(--card-bg);
}

@media (max-width: 968px) {
    .admin-table {
        min-width: 900px !important; /* Force width larger than mobile screen */
    }
}

.role-admin{background:#a855f7;color:#fff;padding:5px 10px;border-radius:5px}
.role-user{background:#22c55e;color:#fff;padding:5px 10px;border-radius:5px}
.role-banned{background:#ef4444;color:#fff;padding:5px 10px;border-radius:5px}

.action-btn{
    padding:8px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    margin-right:5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    min-height: 40px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Search Bar Styles */
.search-container {
    margin: 20px 0;
    display: flex;
    gap: 10px;
}

.search-input-wrapper {
    position: relative;
    flex: 1;
}

.search-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 12px 15px 12px 40px;
    color: #fff;
    font-size: 1rem;
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.6;
}

@media (max-width: 640px) {
    .search-container { flex-direction: column; }
    .search-btn { width: 100%; }
}
</style>
</head>

<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <div class="container nav-container">
        <a href="index.php" class="logo">
            <div class="logo-icon">⚡</div>
            <span>God_BlackHole Admin</span>
        </a>

        <ul class="nav-links">
            <li class="nav-drawer-close-li">
                <button type="button" class="nav-drawer-close" id="navDrawerClose" aria-label="ปิดเมนู">× ปิดเมนู</button>
            </li>
            <li><a href="index.php">หน้าหลัก</a></li>
            <li><a href="admin_products.php?inapp=1">จัดการสินค้า</a></li>
            <li><a href="admin_topups.php?inapp=1" style="color:#10b981;">💰 จัดการเติมเงิน</a></li>
            <li><a href="admin_orders.php?inapp=1" style="color:#eab308;">📦 รายการสั่งซื้อที่เลือกคุณ</a></li>
        </ul>

        <div class="nav-buttons">
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                <span class="sun">☀</span>
                <span class="moon">☽</span>
            </button>
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

<div class="admin-container container">

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert success" style="background:#dcfce7;color:#166534;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #bbf7d0">
        ✅ <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert error" style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #fecaca">
        🚨 <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<section class="admin-ticker-section" style="margin-bottom:30px;padding:20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
    <h2 style="margin-bottom:15px;font-size:1.25rem;">📢 จัดการแถบข่าว (Ticker)</h2>
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:15px;">ข้อความจะเลื่อนบนเว็บให้ผู้ใช้เห็นได้ แก้ไขหรือปิดได้เฉพาะแอดมิน</p>
    <form method="post" action="admin_action.php?action=save_ticker">
        <div style="margin-bottom:12px;">
            <label style="display:block;margin-bottom:6px;font-weight:600;">ข้อความแถบข่าว</label>
            <textarea name="ticker_text" rows="3" class="form-control" style="width:100%;padding:12px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:1rem;" placeholder="พิมพ์ข้อความที่ต้องการให้เลื่อน..."><?= htmlspecialchars($ticker_content) ?></textarea>
        </div>
        <div style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="ticker_enabled" id="tickerEnabled" value="1" <?= $ticker_active ? 'checked' : '' ?>>
            <label for="tickerEnabled">แสดงแถบข่าวบนเว็บ</label>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">💾 บันทึกแถบข่าว</button>
            <button type="submit" formaction="admin_action.php?action=delete_ticker" formmethod="post" class="btn btn-secondary" style="background:rgba(239,68,68,0.2);border-color:rgba(239,68,68,0.5);color:#fca5a5;" onclick="return confirm('ปิดและลบข้อความแถบข่าว?');">🗑️ ปิด/ลบแถบข่าว</button>
        </div>
    </form>
</section>

<h1>Admin Control Panel</h1>

<!-- สถานะของฉัน: บันทึกลง DB — ลูกค้าเลือกได้เฉพาะแอดมินที่ตั้งเป็น ว่าง -->
<p style="margin-bottom: 8px; color: var(--text-muted); font-size: 0.9rem;">สถานะของฉัน (ลูกค้าเลือกได้เมื่อตั้งเป็น ว่าง หรือ ไม่อยู่ — ไม่ว่าง = ไม่แสดงในรายการ)</p>
<form method="post" action="admin_action.php?action=set_my_availability" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 16px;">
    <label style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-elevated); cursor: pointer; user-select: none;">
        <input type="radio" name="availability" value="1" <?= $my_availability === 1 ? 'checked' : '' ?>>
        <span>1 ว่าง</span>
    </label>
    <label style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-elevated); cursor: pointer; user-select: none;">
        <input type="radio" name="availability" value="2" <?= $my_availability === 2 ? 'checked' : '' ?>>
        <span>2 ไม่ว่าง</span>
    </label>
    <label style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-elevated); cursor: pointer; user-select: none;">
        <input type="radio" name="availability" value="3" <?= $my_availability === 3 ? 'checked' : '' ?>>
        <span>3 ไม่อยู่</span>
    </label>
    <button type="submit" class="btn btn-primary" style="padding: 8px 14px;">บันทึกสถานะ</button>
</form>

<form method="post"
      action="admin_delete_all.php"
      onsubmit="return confirm('⚠️ ลบผู้ใช้ทั้งหมดจริงไหม?')">

    <button type="submit"
        class="action-btn"
        style="background:#7f1d1d;color:#fff">
        💣 ลบผู้ใช้ทั้งหมด
    </button>
</form>

<form method="GET" class="search-container">
    <?php if (isset($_GET['inapp'])): ?><input type="hidden" name="inapp" value="1"><?php endif; ?>
    <div class="search-input-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" name="search" class="search-input" 
               placeholder="ค้นหาชื่อผู้ใช้ หรือ อีเมล..." 
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary search-btn">ค้นหา</button>
    <?php if(!empty($search)): ?>
        <a href="admin_panel.php" class="btn btn-secondary">ล้างการค้นหา</a>
    <?php endif; ?>
</form>

<div class="table-responsive">
<table class="admin-table">
<thead>
<tr>
<th>ID</th>
<th>Username</th>
<th>Email</th>
<th>Role</th>
<th>Password</th>
<th>Actions</th>
</tr>
</thead>

<tbody>
<?php while($u = $result->fetch_assoc()): ?>
<?php $role = $u['is_active'] ?? 0; ?>
<tr>
<td>#<?= $u['id'] ?></td>
<td><?= htmlspecialchars($u['username']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>

<td>
<?php
if ($role == 10) echo '<span class="role-admin">Admin</span>';
elseif ($role == 1) echo '<span class="role-user">User</span>';
else echo '<span class="role-banned">Banned</span>';
?>
</td>

<td>
<span class="hide">********</span>
<span class="show" style="display:none;color:#fbbf24">
<?php
$dec = decrypt_password($u['password']);
if ($dec !== false) echo htmlspecialchars($dec);
elseif (str_starts_with($u['password'], '$2y$')) echo '🔒 Encrypted';
else echo htmlspecialchars($u['password']);
?>
</span>
</td>

<td>
<button class="action-btn" onclick="toggle(this)">👁️</button>

<?php if ($role != 10 || $u['id'] == $_SESSION['user_id']): ?>
<button class="action-btn" style="background:#f59e0b"
onclick="editUser(
<?= $u['id'] ?>,
'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>',
'<?= htmlspecialchars($u['email'],ENT_QUOTES) ?>',
<?= $role ?>
)">✏️</button>

<form method="post" action="admin_action.php?action=delete_user" style="display:inline">
    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    <button type="submit"
        class="action-btn"
        style="background:#ef4444;color:#fff"
        onclick="return confirm('ลบผู้ใช้นี้?')">
        🗑️
    </button>
</form>
<?php else: ?>
    <span style="color:rgba(255,255,255,0.3); font-size: 0.8rem;">🔒 Protected</span>
<?php endif; ?>


</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<!-- MODAL -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center">
    <form method="post" action="admin_update.php"
          style="background: #111; width: 100%; max-width: 450px; padding: 30px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <h2 style="margin-bottom: 25px; text-align: center;" class="gradient-text">แก้ไขผู้ใช้</h2>

        <input type="hidden" name="id" id="mid">
        <?php if (isset($_GET['inapp'])): ?><input type="hidden" name="inapp" value="1"><?php endif; ?>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.9rem;">Username</label>
            <input name="username" id="muser" required 
                   style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.9rem;">Email</label>
            <input name="email" id="memail" required 
                   style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.9rem;">Password (เว้นว่าง = ไม่เปลี่ยน)</label>
            <input name="password" 
                   style="width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;"
                   placeholder="รหัสผ่านใหม่...">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.9rem;">Role</label>
            <select name="is_active" id="mrole" 
                    style="width: 100%; padding: 12px; background: rgba(10,10,10,1); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
                <option value="1">User</option>
                <option value="10">Admin</option>
                <option value="0">Banned</option>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="justify-content: center; width: 100%;">บันทึก</button>
            <button type="button" class="btn btn-secondary" style="justify-content: center; width: 100%;"
                    onclick="modal.style.display='none'">ยกเลิก</button>
        </div>
    </form>
</div>

<script>
(function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
</script>
<script src="script.js?v=2.0"></script>
<script>
function toggle(b){

    let r=b.closest('tr');
    let h=r.querySelector('.hide');
    let s=r.querySelector('.show');
    if(s.style.display==='none'){
        s.style.display='inline';
        h.style.display='none';
    }else{
        s.style.display='none';
        h.style.display='inline';
    }
}

function editUser(id,u,e,r){
    mid.value=id;
    muser.value=u;
    memail.value=e;
    mrole.value=r;
    modal.style.display='flex';
}
</script>
    <?php /* include 'music_player.php'; */ ?>
</body>
</html>

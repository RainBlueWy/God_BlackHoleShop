<?php
session_start();
require_once 'config.php';

// ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: profile.php");
    exit();
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=admin_topups');
    exit;
}

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $topup_id = (int)$_POST['topup_id'];

    // Verify topup exists and is pending
    $chk_stmt = $conn->prepare("SELECT user_id, amount, status FROM topups WHERE id = ? AND status = 'pending'");
    $chk_stmt->bind_param("i", $topup_id);
    $chk_stmt->execute();
    $res = $chk_stmt->get_result();

    if ($res->num_rows === 1) {
        $topup = $res->fetch_assoc();
        $uid = $topup['user_id'];
        $amount = (float)$topup['amount'];

        if ($action === 'approve') {
            $conn->begin_transaction();
            try {
                // Update topup status
                $conn->query("UPDATE topups SET status = 'approved' WHERE id = " . $topup_id);
                // Add points to user
                $conn->query("UPDATE users SET points = points + $amount WHERE id = " . $uid);
                $conn->commit();
                $_SESSION['success'] = "อนุมัติรายการเติมเงินเรียบร้อยแล้ว (+ " . number_format($amount, 2) . " พอยท์)";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอนุมัติ";
            }
        } elseif ($action === 'reject') {
            $conn->query("UPDATE topups SET status = 'rejected' WHERE id = " . $topup_id);
            $_SESSION['success'] = "ปฏิเสธรายการเติมเงินเรียบร้อยแล้ว";
        }
    } else {
        $_SESSION['error'] = "ไม่พบรายการ หรือรายการนี้ถูกจัดการไปแล้ว";
    }
    header("Location: admin_topups.php?inapp=1");
    exit;
}

// Fetch pending topups
$q_pending = $conn->query("
    SELECT t.id, t.amount, t.slip_image, t.created_at, u.username, u.email
    FROM topups t
    JOIN users u ON t.user_id = u.id
    WHERE t.status = 'pending'
    ORDER BY t.created_at ASC
");
$pending_topups = $q_pending->fetch_all(MYSQLI_ASSOC);

// Fetch recent active topups (approved/rejected)
$q_recent = $conn->query("
    SELECT t.id, t.amount, t.status, t.created_at, u.username 
    FROM topups t
    JOIN users u ON t.user_id = u.id
    WHERE t.status != 'pending'
    ORDER BY t.updated_at DESC
    LIMIT 20
");
$recent_topups = $q_recent->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Admin Topups - God_BlackHole</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css?v=1.5">
<?php include 'protection_header.php'; ?>

<style>
.admin-container{
    padding-top:120px;
    padding-bottom:50px;
    min-height:100vh;
}

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

.table-responsive {
    z-index: 1;
    position: relative;
    overflow-x: auto !important;
    display: block;
    width: 100%;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.slip-thumbnail {
    width: 80px;
    height: auto;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: 0.3s;
}
.slip-thumbnail:hover {
    border-color: var(--accent);
}

/* Modal for Image Preview */
.img-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    padding-top: 50px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.9);
}
.img-modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 80vh;
}
.img-modal-close {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    transition: 0.3s;
    cursor: pointer;
}
.img-modal-close:hover,
.img-modal-close:focus {
    color: #bbb;
    text-decoration: none;
    cursor: pointer;
}

/* Confirm Action Modal (แทน alert/confirm) */
.action-modal-overlay {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}
.action-modal-overlay.show {
    display: flex;
}
.action-modal-box {
    background: var(--card-bg, #1a1a24);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    max-width: 380px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.action-modal-box h3 {
    margin: 0 0 0.75rem;
    font-size: 1.1rem;
    color: var(--text, #e8e8ed);
}
.action-modal-box p {
    margin: 0 0 1.25rem;
    color: var(--text-muted, #9898a6);
    font-size: 0.95rem;
}
.action-modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.action-modal-actions .btn {
    padding: 0.5rem 1.25rem;
    border-radius: 10px;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    font-weight: 600;
}
.action-modal-cancel {
    background: rgba(255,255,255,0.1);
    color: var(--text);
}
.action-modal-cancel:hover {
    background: rgba(255,255,255,0.15);
}
.action-modal-confirm {
    background: #10b981;
    color: #fff;
}
.action-modal-confirm:hover {
    background: #0d9668;
}
.action-modal-confirm.reject {
    background: #ef4444;
}
.action-modal-confirm.reject:hover {
    background: #dc2626;
}
</style>
</head>

<body>
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
            <li><a href="admin_panel.php?inapp=1">กลับไปจัดการผู้ใช้</a></li>
            <li><a href="admin_products.php?inapp=1">จัดการสินค้า</a></li>
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

<h2>รอตรวจสอบการเติมเงิน (<?= count($pending_topups) ?> รายการ)</h2>

<?php if (empty($pending_topups)): ?>
    <p style="color:var(--text-muted); margin-bottom: 2rem;">ไม่มีรายการเติมเงินที่รอการตรวจสอบ</p>
<?php else: ?>
    <div class="table-responsive" style="margin-bottom: 3rem;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>วันที่โอน</th>
                    <th>ผู้ใช้</th>
                    <th>จำนวนเงิน (บาท)</th>
                    <th>สลิป</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pending_topups as $t): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($t['username']) ?></strong><br>
                        <small style="color:#aaa;"><?= htmlspecialchars($t['email']) ?></small>
                    </td>
                    <td><span class="gradient-text" style="font-weight:bold; font-size:1.1rem;"><?= number_format($t['amount'], 2) ?></span></td>
                    <td>
                        <?php if(!empty($t['slip_image'])): ?>
                            <img src="uploads/slips/<?= htmlspecialchars($t['slip_image']) ?>" class="slip-thumbnail" onclick="showImg(this.src)" alt="Slip">
                        <?php else: ?>
                            ไม่มีรูป
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:10px;">
                            <form method="post" class="topup-action-form" data-confirm-msg="ยืนยันอนุมัติ? ผู้ใช้จะได้พอยท์ทันที" data-confirm-title="อนุมัติรายการ">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm btn-topup-approve" style="background:#10b981; border-color:#10b981;">✅ อนุมัติ</button>
                            </form>
                            <form method="post" class="topup-action-form" data-confirm-msg="ปฏิเสธรายการนี้? ผู้ใช้จะไม่ได้พอยท์" data-confirm-title="ปฏิเสธรายการ">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="topup_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm btn-topup-reject" style="background:#ef4444; border-color:#ef4444; color:white;">❌ ปฏิเสธ</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>ประวัติรายการตรวจสอบล่าสุด</h2>
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>วันที่ทำรายการ</th>
                <th>ผู้ใช้</th>
                <th>จำนวนเงิน</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recent_topups as $t): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                <td><?= htmlspecialchars($t['username']) ?></td>
                <td><?= number_format($t['amount'], 2) ?></td>
                <td>
                    <?php if($t['status'] === 'approved'): ?>
                        <span style="color:#10b981; font-weight:bold;">อนุมัติแล้ว</span>
                    <?php elseif($t['status'] === 'rejected'): ?>
                        <span style="color:#ef4444; font-weight:bold;">ถูกปฏิเสธ</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>

<!-- The Modal for Images -->
<div id="imageModal" class="img-modal">
  <span class="img-modal-close" onclick="closeImg()">&times;</span>
  <img class="img-modal-content" id="img01">
</div>

<!-- Confirm Action Modal (UI แทน confirm()) -->
<div id="actionModal" class="action-modal-overlay" aria-hidden="true">
    <div class="action-modal-box" onclick="event.stopPropagation()">
        <h3 id="actionModalTitle">ยืนยัน</h3>
        <p id="actionModalMsg"></p>
        <div class="action-modal-actions">
            <button type="button" class="btn action-modal-cancel" id="actionModalCancel">ยกเลิก</button>
            <button type="button" class="btn action-modal-confirm" id="actionModalConfirm">ตกลง</button>
        </div>
    </div>
</div>

<script>
(function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
</script>

<script>
// Modal Image Preview
var modal = document.getElementById("imageModal");
var modalImg = document.getElementById("img01");

function showImg(src) {
  modal.style.display = "block";
  modalImg.src = src;
}

function closeImg() {
  modal.style.display = "none";
}

modal.onclick = function(e) {
    if (e.target !== modalImg) closeImg();
};

// Confirm Action Modal (แทน confirm)
var actionOverlay = document.getElementById("actionModal");
var actionTitle = document.getElementById("actionModalTitle");
var actionMsg = document.getElementById("actionModalMsg");
var actionCancel = document.getElementById("actionModalCancel");
var actionConfirm = document.getElementById("actionModalConfirm");
var pendingForm = null;

document.querySelectorAll(".topup-action-form").forEach(function(form) {
    form.addEventListener("submit", function(e) {
        e.preventDefault();
        var msg = form.getAttribute("data-confirm-msg");
        var title = form.getAttribute("data-confirm-title");
        if (!msg) { form.submit(); return; }
        pendingForm = form;
        actionTitle.textContent = title;
        actionMsg.textContent = msg;
        actionConfirm.classList.remove("reject");
        if (form.querySelector('input[name="action"]').value === "reject") {
            actionConfirm.classList.add("reject");
        }
        actionOverlay.classList.add("show");
        actionOverlay.setAttribute("aria-hidden", "false");
    });
});

function closeActionModal() {
    actionOverlay.classList.remove("show");
    actionOverlay.setAttribute("aria-hidden", "true");
    pendingForm = null;
}

actionCancel.addEventListener("click", closeActionModal);
actionConfirm.addEventListener("click", function() {
    if (pendingForm) {
        pendingForm.submit();
    }
    closeActionModal();
});

actionOverlay.addEventListener("click", function(e) {
    if (e.target === actionOverlay) closeActionModal();
});
</script>
</body>
</html>

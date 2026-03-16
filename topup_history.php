<?php
session_start();
require_once 'config.php';

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$is_inapp = isset($_GET['inapp']) ? true : false;

// Fetch topup history
$stmt = $conn->prepare("SELECT amount, status, created_at, slip_image FROM topups WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$topups = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch purchase history (ซื้อสินค้าด้วยพอยท์)
$purchases = [];
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// ให้ตาราง purchases มี assigned_admin_id (สำหรับดึงแอดมินไปแสดงปุ่มติดต่อ)
$has_admin_col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
if ($has_admin_col && $has_admin_col->num_rows === 0) {
    @$conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL AFTER points_used");
    @$conn->query("ALTER TABLE purchases ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending' AFTER assigned_admin_id");
    $has_admin_col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
}
$purchase_select = "SELECT p.id, p.points_used, p.created_at, pr.name AS product_name";
if ($has_admin_col && $has_admin_col->num_rows > 0) {
    $purchase_select .= ", p.assigned_admin_id";
    $purchase_select .= ", u.username AS admin_name";
}
$purchase_select .= " FROM purchases p LEFT JOIN products pr ON pr.id = p.product_id";
if ($has_admin_col && $has_admin_col->num_rows > 0) {
    $purchase_select .= " LEFT JOIN users u ON u.id = p.assigned_admin_id";
}
$purchase_select .= " WHERE p.user_id = ? ORDER BY p.created_at DESC";
$stmt = $conn->prepare($purchase_select);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $purchases = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ถ้า config ยังไม่มี contact_admin แต่ user มีประวัติซื้อ — ดึงแอดมินจากออเดอร์ล่าสุดมาใช้แสดงปุ่ม/ไอคอนติดต่อ
$contact_admin_from_purchase = null;
if (empty($contact_admin) && !empty($purchases)) {
    $col = @$conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
    if ($col && $col->num_rows === 0) @$conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
    foreach ($purchases as $p) {
        $aid = isset($p['assigned_admin_id']) ? (int)$p['assigned_admin_id'] : 0;
        if ($aid > 0) {
            $au = $conn->prepare("SELECT id, username, contact_line FROM users WHERE id = ? AND is_active = 10");
            if ($au) {
                $au->bind_param("i", $aid);
                $au->execute();
                $ar = $au->get_result()->fetch_assoc();
                $au->close();
                if ($ar && !empty(trim($ar['contact_line'] ?? ''))) {
                    $cl = trim($ar['contact_line']);
                    $contact_admin_from_purchase = [
                        'name' => $ar['username'],
                        'url' => (stripos($cl, 'http') === 0) ? $cl : ('https://line.me/ti/p/~' . $cl)
                    ];
                    if (!isset($_SESSION['selected_admin_id'])) $_SESSION['selected_admin_id'] = $aid;
                    break;
                }
            }
        }
    }
    // ถ้ายังไม่มี แอดมินจากออเดอร์ — ใช้แอดมินคนใดก็ได้ที่ตั้ง Line แล้ว
    if (!$contact_admin_from_purchase) {
        $fallback = @$conn->query("SELECT id, username, contact_line FROM users WHERE is_active = 10 AND contact_line IS NOT NULL AND TRIM(contact_line) != '' ORDER BY id ASC LIMIT 1");
        if ($fallback && $fallback->num_rows > 0) {
            $fb = $fallback->fetch_assoc();
            $cl = trim($fb['contact_line']);
            $contact_admin_from_purchase = ['name' => $fb['username'], 'url' => (stripos($cl, 'http') === 0) ? $cl : ('https://line.me/ti/p/~' . $cl)];
        }
    }
    if ($contact_admin_from_purchase) $contact_admin = $contact_admin_from_purchase;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ประวัติการเติมเงิน - God_BlackHole</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.8">
    <link rel="stylesheet" href="points.css?v=3">
    <?php include 'protection_header.php'; ?>
    <style>
        .history-container {
            max-width: min(800px, 100%);
            margin: clamp(1rem, 4vw, 2rem) auto clamp(1.5rem, 5vw, 3rem);
            padding: clamp(1rem, 4vw, 2rem);
            padding-left: max(clamp(1rem, 4vw, 2rem), env(safe-area-inset-left));
            padding-right: max(clamp(1rem, 4vw, 2rem), env(safe-area-inset-right));
            background: rgba(25, 25, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: clamp(12px, 3vw, 20px);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        [data-theme="light"] .history-container {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(0, 0, 0, 0.1);
        }

        .points-summary {
            background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(0,212,170,0.1)) !important;
            border: 1px solid rgba(16,185,129,0.3) !important;
            border-radius: clamp(12px, 2.5vw, 16px) !important;
            padding: clamp(1rem, 3vw, 1.25rem) clamp(1rem, 3vw, 1.5rem) !important;
            margin-bottom: clamp(1rem, 3vw, 1.5rem) !important;
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: clamp(0.75rem, 2vw, 1rem);
        }
        .points-summary .gradient-text {
            font-size: clamp(1.35rem, 4vw, 1.75rem) !important;
        }
        .points-summary .btn {
            min-height: 44px;
            flex-shrink: 0;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(1rem, 3vw, 2rem);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: clamp(0.75rem, 2vw, 1rem);
        }
        [data-theme="light"] .history-header { border-color: rgba(0,0,0,0.1); }
        .history-header h2 {
            margin: 0;
            font-size: clamp(1.1rem, 3vw, 1.5rem);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .history-table {
            width: 100%;
            min-width: 280px;
            border-collapse: collapse;
        }
        .history-table th, .history-table td {
            padding: clamp(0.6rem, 2vw, 1rem);
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: clamp(0.85rem, 2vw, 1rem);
        }
        .history-table th {
            white-space: nowrap;
        }
        [data-theme="light"] .history-table th, [data-theme="light"] .history-table td {
            border-color: rgba(0,0,0,0.05);
        }

        .status-badge {
            padding: clamp(0.25rem, 1vw, 0.3rem) clamp(0.5rem, 2vw, 0.8rem);
            border-radius: 20px;
            font-size: clamp(0.75rem, 1.8vw, 0.85rem);
            font-weight: 600;
            white-space: nowrap;
        }
        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .status-approved {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .status-rejected {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: clamp(2rem, 6vw, 3rem) clamp(1rem, 3vw, 0);
            color: rgba(255,255,255,0.5);
        }
        .empty-state h3 {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        [data-theme="light"] .empty-state { color: rgba(0,0,0,0.5); }

        .history-tabs {
            display: flex;
            gap: clamp(0.25rem, 1vw, 0.5rem);
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 0;
            flex-wrap: wrap;
        }
        [data-theme="light"] .history-tabs { border-color: rgba(0,0,0,0.1); }
        .history-tab {
            padding: clamp(0.6rem, 2vw, 0.75rem) clamp(0.85rem, 2.5vw, 1.25rem);
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-muted);
            cursor: pointer;
            font-size: clamp(0.85rem, 2vw, 1rem);
            font-weight: 500;
            margin-bottom: -1px;
            border-radius: 0;
            -webkit-tap-highlight-color: transparent;
        }
        .history-tab:hover { color: var(--text); }
        .history-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .history-panel { display: none; }
        .history-panel.active { display: block; }

        .contact-admin-bar {
            display: inline-flex;
            align-items: center;
            gap: clamp(8px, 2vw, 10px);
            padding: clamp(10px, 2.5vw, 12px) clamp(14px, 3vw, 18px);
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: clamp(0.85rem, 2vw, 1rem);
            border-radius: 50px;
            margin-bottom: clamp(0.75rem, 2vw, 1rem);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
            transition: transform 0.2s, box-shadow 0.2s;
            flex-wrap: wrap;
        }
        .contact-admin-bar:hover { color: #fff; transform: scale(1.02); box-shadow: 0 6px 20px rgba(37, 99, 235, 0.45); }
        .contact-admin-bar-icon {
            width: clamp(36px, 8vw, 40px);
            height: clamp(36px, 8vw, 40px);
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-admin-bar-icon svg {
            width: clamp(18px, 4vw, 22px);
            height: clamp(18px, 4vw, 22px);
        }
    </style>
</head>
<body>
    <div class="noise"></div>

    <?php if (!$is_inapp): ?>
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
                <div class="points-display">
                    <span class="points-amount">💰 <?php echo $user_points; ?> พอยท์</span>
                    <a href="topup.php" class="btn-topup">+ เติมเงิน</a>
                </div>
                <a href="profile.php" class="btn btn-primary">
                    <span>👤</span> <span class="btn-text">โปรไฟล์</span>
                </a>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>
    <div style="height: 60px;"></div>
    <?php else: ?>
    <!-- Top Padding for in-app iframe -->
    <div style="height: 20px;"></div>
    <?php endif; ?>

    <div class="page-frame">
    <div class="container">
        <div class="history-container">
            <!-- แสดงยอดพอยท์ปัจจุบันชัดเจน -->
            <div class="points-summary" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(0,212,170,0.1)); border: 1px solid rgba(16,185,129,0.3); border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="color: var(--text-muted); font-size: 0.9rem;">ยอดพอยท์ปัจจุบัน</span>
                    <div class="gradient-text" style="font-size: 1.75rem; font-weight: 700;"><?php echo htmlspecialchars($user_points); ?> พอยท์</div>
                </div>
                <a href="<?php echo $is_inapp ? 'topup.php?inapp=1' : 'app.php?page=topup'; ?>" class="btn btn-primary">+ เติมเงิน</a>
            </div>

            <div class="history-tabs">
                <button type="button" class="history-tab active" data-tab="topup" aria-pressed="true">ประวัติการเติมเงิน</button>
                <button type="button" class="history-tab" data-tab="purchase" aria-pressed="false">ประวัติซื้อสินค้า</button>
            </div>

            <!-- แผง: ประวัติการเติมเงิน -->
            <div id="panel-topup" class="history-panel active">
                <div class="history-header">
                    <h2>ประวัติการเติมเงิน</h2>
                </div>
                <?php if (empty($topups)): ?>
                    <div class="empty-state">
                        <h3>ยังไม่มีประวัติการเติมเงิน</h3>
                        <p>เติมเงินครั้งแรกเพื่อรับพอยท์ไปซื้อสินค้า!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>วัน/เวลา</th>
                                    <th>จำนวนเงิน</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topups as $t): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td><span class="gradient-text" style="font-weight:bold;"><?php echo number_format($t['amount'], 2); ?> ฿</span></td>
                                    <td>
                                        <?php if($t['status'] === 'pending'): ?>
                                            <span class="status-badge status-pending">รอตรวจสอบ</span>
                                        <?php elseif($t['status'] === 'approved'): ?>
                                            <span class="status-badge status-approved">อนุมัติเรียบร้อย</span>
                                        <?php elseif($t['status'] === 'rejected'): ?>
                                            <span class="status-badge status-rejected">ถูกปฏิเสธ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- แผง: ประวัติซื้อสินค้า -->
            <div id="panel-purchase" class="history-panel">
                <div class="history-header">
                    <h2>ประวัติซื้อสินค้า</h2>
                </div>
                <?php if (!empty($contact_admin) && !empty($contact_admin['url'])): ?>
                <a href="<?= htmlspecialchars($contact_admin['url']) ?>" target="_blank" rel="noopener" class="contact-admin-bar" title="คุยกับแอดมิน">
                    <span class="contact-admin-bar-icon">
                        <svg viewBox="0 0 24 24" fill="#fff"><ellipse cx="9" cy="10" rx="7" ry="8"/><ellipse cx="16" cy="15" rx="5" ry="5"/></svg>
                    </span>
                    <span>คุยกับแอดมิน (<?= htmlspecialchars($contact_admin['name']) ?>)</span>
                </a>
                <?php endif; ?>
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <h3>ยังไม่มีประวัติซื้อสินค้า</h3>
                        <p>เมื่อซื้อสินค้าด้วยพอยท์จะแสดงที่นี่</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>วัน/เวลา</th>
                                    <th>สินค้า</th>
                                    <th>พอยท์ที่ใช้</th>
                                    <th>ซื้อกับใคร</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['product_name'] ?? '—'); ?></td>
                                    <td><span class="gradient-text" style="font-weight:bold;"><?php echo number_format((float)$p['points_used'], 0); ?> พอยท์</span></td>
                                    <td><?php echo htmlspecialchars(!empty($p['admin_name']) ? $p['admin_name'] : '—'); ?></td>
                                    <td><span class="status-badge status-approved">ซื้อแล้ว</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    </div><!-- .page-frame -->

    <script>
    (function() {
        var themeKey = 'godblackhole-theme';
        function applyTheme(theme) {
            if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            else document.documentElement.removeAttribute('data-theme');
        }
        var stored = localStorage.getItem(themeKey);
        if (stored) applyTheme(stored);
        else applyTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    })();

    (function() {
        var tabs = document.querySelectorAll('.history-tab');
        var panels = document.querySelectorAll('.history-panel');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var t = this.getAttribute('data-tab');
                tabs.forEach(function(bt) { bt.classList.remove('active'); bt.setAttribute('aria-pressed', 'false'); });
                panels.forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                this.setAttribute('aria-pressed', 'true');
                var panel = document.getElementById('panel-' + t);
                if (panel) panel.classList.add('active');
            });
        });
    })();
    </script>
</body>
</html>

<?php
session_start();
// เปิดดู error จริงบนโฮสต์: ใส่ ?debug=1 ใน URL (ลบออกได้หลังแก้ 500)
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
require_once 'config.php';
require_once 'auth_guard.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: profile.php");
    exit();
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=admin_orders');
    exit;
}

$my_id = (int)$_SESSION['user_id'];

// Ensure columns exist (ใช้ @ และตรวจค่า return เพื่อกัน error บนโฮสต์)
$ac = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
if ($ac !== false && $ac->num_rows === 0) {
    @$conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL AFTER points_used, ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending' AFTER assigned_admin_id");
}
$cx = @$conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
if ($cx !== false && $cx->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
}

// กดรับ (รองรับทั้ง pending และ NULL สำหรับแถวเก่า)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    $pid = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $up = $conn->prepare("UPDATE purchases SET admin_status = 'accepted' WHERE id = ? AND assigned_admin_id = ? AND (admin_status = 'pending' OR admin_status IS NULL)");
    $up->bind_param("ii", $pid, $my_id);
    $up->execute();
    if ($up->affected_rows > 0) $_SESSION['success'] = 'รับออเดอร์เรียบร้อยแล้ว';
    $up->close();
    header('Location: admin_orders.php?inapp=1&chat_open=1');
    exit;
}

// รายการที่ลูกค้าเลือกคุณ (รอรับ)
$pending = [];
$q = $conn->prepare("
    SELECT p.id, p.points_used, p.created_at, p.admin_status,
           u.username AS customer_name, pr.name AS product_name
    FROM purchases p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN products pr ON pr.id = p.product_id
    WHERE p.assigned_admin_id = ? AND p.admin_status = 'pending'
    ORDER BY p.created_at DESC
");
$q->bind_param("i", $my_id);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) $pending[] = $row;
$q->close();

// รายการที่รับแล้ว (ล่าสุด) + แสดงว่าแอดมินคนไหนกดรับ
$accepted = [];
$q2 = $conn->prepare("
    SELECT p.id, p.points_used, p.created_at, p.assigned_admin_id,
           u.username AS customer_name, pr.name AS product_name,
           admin_u.username AS accepted_by_name
    FROM purchases p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN products pr ON pr.id = p.product_id
    LEFT JOIN users admin_u ON admin_u.id = p.assigned_admin_id
    WHERE p.assigned_admin_id = ? AND p.admin_status = 'accepted'
    ORDER BY p.created_at DESC
    LIMIT 30
");
$q2->bind_param("i", $my_id);
$q2->execute();
$res2 = $q2->get_result();
while ($row = $res2->fetch_assoc()) $accepted[] = $row;
$q2->close();

// ภาพรวมทุกแอดมิน: ใครรับงานอะไรบ้าง (เฉพาะที่รับแล้ว) + สถิติรายวัน/เดือน/ปี สำหรับกราฟ
$overview = [];
$overview_chart_labels = [];
$overview_chart_today = [];
$overview_chart_month = [];
$overview_chart_year = [];
$q3a = $conn->query("
    SELECT
        admin_u.username AS admin_name,
        SUM(CASE WHEN DATE(p.created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
        SUM(CASE WHEN p.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS month_cnt,
        SUM(CASE WHEN YEAR(p.created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS year_cnt
    FROM purchases p
    LEFT JOIN users admin_u ON admin_u.id = p.assigned_admin_id
    WHERE p.admin_status = 'accepted' AND p.assigned_admin_id IS NOT NULL
    GROUP BY p.assigned_admin_id, admin_u.username
    ORDER BY year_cnt DESC, admin_name ASC
");
if ($q3a) {
    while ($row = $q3a->fetch_assoc()) {
        $nm = $row['admin_name'] ?? 'ไม่ทราบ';
        $overview_chart_labels[] = $nm;
        $overview_chart_today[] = (int)$row['today_cnt'];
        $overview_chart_month[] = (int)$row['month_cnt'];
        $overview_chart_year[] = (int)$row['year_cnt'];
    }
    $q3a->close();
}
$q3 = $conn->query("
    SELECT p.id, p.points_used, p.created_at, p.assigned_admin_id,
           u.username AS customer_name, pr.name AS product_name,
           admin_u.username AS accepted_by_name
    FROM purchases p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN products pr ON pr.id = p.product_id
    LEFT JOIN users admin_u ON admin_u.id = p.assigned_admin_id
    WHERE p.admin_status = 'accepted' AND p.assigned_admin_id IS NOT NULL
    ORDER BY accepted_by_name ASC, p.created_at DESC
    LIMIT 200
");
if ($q3) {
    while ($row = $q3->fetch_assoc()) {
        $name = $row['accepted_by_name'] ?? 'ไม่ทราบ';
        if (!isset($overview[$name])) $overview[$name] = [];
        $overview[$name][] = $row;
    }
    $q3->close();
}

// ข้อมูลกราฟยอดขาย: เลือกดู 30 วันล่าสุด / วันที่เลือก / เดือนที่เลือก / ปีที่เลือก
$chart_view = isset($_GET['chart_view']) ? $_GET['chart_view'] : '30days';
if (!in_array($chart_view, ['30days', 'day', 'month', 'year'], true)) $chart_view = '30days';
$chart_date = isset($_GET['chart_date']) ? preg_replace('/[^0-9\-]/', '', $_GET['chart_date']) : '';
$chart_month = isset($_GET['chart_month']) ? preg_replace('/[^0-9\-]/', '', $_GET['chart_month']) : '';
$chart_year = isset($_GET['chart_year']) ? (int)$_GET['chart_year'] : (int)date('Y');

$chart_labels = [];
$chart_counts = [];
$chart_points = [];
$chart_title = 'ออเดอร์ที่รับแล้ว 30 วันล่าสุด';
$stats_today = ['count' => 0, 'points' => 0];
$stats_month = ['count' => 0, 'points' => 0];
$stats_year = ['count' => 0, 'points' => 0];

if ($chart_view === '30days') {
    $q4 = $conn->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(points_used), 0) AS pts
        FROM purchases
        WHERE admin_status = 'accepted' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ");
    if ($q4) {
        while ($r = $q4->fetch_assoc()) {
            $chart_labels[] = date('d/m', strtotime($r['d']));
            $chart_counts[] = (int)$r['cnt'];
            $chart_points[] = (float)$r['pts'];
        }
        $q4->close();
    }
    $q5 = $conn->query("
        SELECT SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
               SUM(CASE WHEN DATE(created_at) = CURDATE() THEN points_used ELSE 0 END) AS today_pts,
               SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS month_cnt,
               SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN points_used ELSE 0 END) AS month_pts,
               SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS year_cnt,
               SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) THEN points_used ELSE 0 END) AS year_pts
        FROM purchases WHERE admin_status = 'accepted'
    ");
    if ($q5 && $row = $q5->fetch_assoc()) {
        $stats_today = ['count' => (int)$row['today_cnt'], 'points' => (float)$row['today_pts']];
        $stats_month = ['count' => (int)$row['month_cnt'], 'points' => (float)$row['month_pts']];
        $stats_year = ['count' => (int)$row['year_cnt'], 'points' => (float)$row['year_pts']];
        $q5->close();
    }
} elseif ($chart_view === 'day') {
    if ($chart_date === '') $chart_date = date('Y-m-d');
    $chart_title = 'วันที่ ' . date('d/m/Y', strtotime($chart_date));
    $q4 = $conn->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(points_used), 0) AS pts
        FROM purchases
        WHERE admin_status = 'accepted' AND DATE(created_at) = '" . $conn->real_escape_string($chart_date) . "'
        GROUP BY DATE(created_at)
    ");
    if ($q4 && $r = $q4->fetch_assoc()) {
        $chart_labels[] = date('d/m/Y', strtotime($r['d']));
        $chart_counts[] = (int)$r['cnt'];
        $chart_points[] = (float)$r['pts'];
        $stats_today = ['count' => (int)$r['cnt'], 'points' => (float)$r['pts']];
        $q4->close();
    } else {
        if ($q4) $q4->close();
        $chart_labels[] = date('d/m/Y', strtotime($chart_date));
        $chart_counts[] = 0;
        $chart_points[] = 0;
    }
    $stats_month = $stats_today;
    $stats_year = $stats_today;
} elseif ($chart_view === 'month') {
    if ($chart_month === '') $chart_month = date('Y-m');
    $ym = $chart_month;
    $chart_title = 'รายวัน เดือน ' . date('m/Y', strtotime($ym . '-01'));
    $q4 = $conn->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS cnt, COALESCE(SUM(points_used), 0) AS pts
        FROM purchases
        WHERE admin_status = 'accepted' AND DATE_FORMAT(created_at, '%Y-%m') = '" . $conn->real_escape_string($ym) . "'
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ");
    $stats_month = ['count' => 0, 'points' => 0];
    if ($q4) {
        while ($r = $q4->fetch_assoc()) {
            $chart_labels[] = date('d/m', strtotime($r['d']));
            $chart_counts[] = (int)$r['cnt'];
            $chart_points[] = (float)$r['pts'];
            $stats_month['count'] += (int)$r['cnt'];
            $stats_month['points'] += (float)$r['pts'];
        }
        $q4->close();
    }
    $stats_today = $stats_month;
    $stats_year = $stats_month;
} elseif ($chart_view === 'year') {
    if ($chart_year < 2020 || $chart_year > 2100) $chart_year = (int)date('Y');
    $chart_title = 'รายเดือน ปี ' . $chart_year;
    $q4 = $conn->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS cnt, COALESCE(SUM(points_used), 0) AS pts
        FROM purchases
        WHERE admin_status = 'accepted' AND YEAR(created_at) = " . (int)$chart_year . "
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY m ASC
    ");
    $stats_year = ['count' => 0, 'points' => 0];
    $thai_months = ['01'=>'ม.ค.','02'=>'ก.พ.','03'=>'มี.ค.','04'=>'เม.ย.','05'=>'พ.ค.','06'=>'มิ.ย.','07'=>'ก.ค.','08'=>'ส.ค.','09'=>'ก.ย.','10'=>'ต.ค.','11'=>'พ.ย.','12'=>'ธ.ค.'];
    if ($q4) {
        while ($r = $q4->fetch_assoc()) {
            $chart_labels[] = ($thai_months[substr($r['m'], 5, 2)] ?? substr($r['m'], 5, 2)) . ' ' . substr($r['m'], 0, 4);
            $chart_counts[] = (int)$r['cnt'];
            $chart_points[] = (float)$r['pts'];
            $stats_year['count'] += (int)$r['cnt'];
            $stats_year['points'] += (float)$r['pts'];
        }
        $q4->close();
    }
    $stats_today = $stats_year;
    $stats_month = $stats_year;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>รายการสั่งซื้อที่เลือกคุณ - Admin</title>
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>
    <style>
        .admin-container { padding-top: 120px; padding-bottom: 50px; }
        .order-table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: var(--radius-lg); overflow: hidden; }
        .order-table th, .order-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .order-table th { background: rgba(236, 0, 140, 0.2); }
        .btn-accept { background: #10b981; color: #fff; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-accept:hover { background: #0d9668; }
        .overview-modal { display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.85); overflow-y: auto; padding: 2rem; }
        .overview-modal.show { display: flex; align-items: flex-start; justify-content: center; }
        .overview-content { background: var(--card-bg); border-radius: var(--radius-lg); padding: 1.5rem; max-width: 720px; width: 100%; border: 1px solid var(--border); }
        .overview-content h3 { margin: 1rem 0 0.5rem; color: var(--accent); font-size: 1rem; }
        .overview-content h3:first-child { margin-top: 0; }
        .overview-list { list-style: none; margin: 0 0 1rem; padding-left: 0; }
        .overview-list li { padding: 0.4rem 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 0.9rem; }
        .overview-close { background: var(--text-muted); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; margin-top: 1rem; }
        .overview-close:hover { background: #666; }
        .overview-chart-wrap { position: relative; height: 280px; margin: 1rem 0; }
        .overview-detail-toggle { margin: 1rem 0 0.5rem; font-size: 0.9rem; color: var(--accent); cursor: pointer; }
        .overview-detail { margin-top: 0.5rem; }
        .chart-modal { display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.85); overflow-y: auto; padding: 1rem; align-items: flex-start; justify-content: center; }
        .chart-modal.show { display: flex; }
        .chart-content { background: var(--card-bg); border-radius: var(--radius-lg); padding: 1.5rem; max-width: 720px; width: 100%; border: 1px solid var(--border); }
        .chart-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .chart-stat-card { background: rgba(0,212,170,0.12); border: 1px solid var(--accent); border-radius: 10px; padding: 1rem; text-align: center; }
        .chart-stat-card .label { font-size: 0.8rem; color: var(--text-muted); }
        .chart-stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--accent); }
        .chart-wrap { position: relative; height: 260px; margin: 1rem 0; }
        .chart-filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: flex-end; margin-bottom: 1rem; }
        .chart-filters label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem; color: var(--text-muted); }
        .chart-filters select, .chart-filters input[type=date] { padding: 6px 10px; border-radius: 6px; background: rgba(255,255,255,0.08); border: 1px solid var(--border); color: var(--text); }
        .chart-filters select option { color: #000; background: #fff; }
        .chart-filters .btn { padding: 6px 14px; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="noise"></div>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="admin_panel.php?inapp=1" class="logo">⚡ Admin</a>
            <a href="admin_panel.php?inapp=1" class="btn btn-secondary">ย้อนกลับ</a>
        </div>
    </nav>
    <div class="container admin-container">
        <h1>รายการสั่งซื้อที่ลูกค้าเลือกคุณ</h1>
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(16,185,129,0.2); color: #86efac; padding: 12px; border-radius: 10px; margin-bottom: 1rem;"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <h2 style="margin-top: 2rem;">รอคุณกดรับ</h2>
        <?php if (empty($pending)): ?>
            <p style="color: var(--text-muted);">ไม่มีรายการรอรับ</p>
        <?php else: ?>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>วัน/เวลา</th>
                        <th>ลูกค้า</th>
                        <th>สินค้า</th>
                        <th>พอยท์</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $o): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                        <td><?= htmlspecialchars($o['customer_name']); ?></td>
                        <td><?= htmlspecialchars($o['product_name'] ?? '—'); ?></td>
                        <td><?= number_format((float)$o['points_used'], 0); ?> พอยท์</td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="purchase_id" value="<?= (int)$o['id']; ?>">
                                <button type="submit" name="accept" value="1" class="btn-accept">รับ</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 style="margin-top: 2rem;">รับแล้ว (ล่าสุด)</h2>
        <button type="button" class="btn btn-secondary" style="margin-bottom: 0.5rem;" onclick="document.getElementById('overviewModal').classList.add('show'); if(typeof drawOverviewChart==='function') drawOverviewChart();">📋 ภาพรวมแอดมิน (ใครรับออเดอร์กี่ตัว รายวัน/เดือน/ปี)</button>
        <button type="button" class="btn btn-primary" style="margin-bottom: 1rem;" onclick="var m=document.getElementById('chartModal'); m.classList.add('show'); setTimeout(function(){ if(typeof drawChart==='function') drawChart(); }, 120);">📊 กราฟยอดขาย (ออเดอร์รับแล้ว – เลือกวัน/เดือน/ปีได้)</button>
        <?php if (empty($accepted)): ?>
            <p style="color: var(--text-muted);">ยังไม่มีรายการที่รับแล้ว</p>
        <?php else: ?>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>วัน/เวลา</th>
                        <th>ลูกค้า</th>
                        <th>สินค้า</th>
                        <th>พอยท์</th>
                        <th>แอดมินที่รับ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accepted as $o): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                        <td><?= htmlspecialchars($o['customer_name']); ?></td>
                        <td><?= htmlspecialchars($o['product_name'] ?? '—'); ?></td>
                        <td><?= number_format((float)$o['points_used'], 0); ?> พอยท์</td>
                        <td><?= htmlspecialchars($o['accepted_by_name'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="overviewModal" class="overview-modal" onclick="if(event.target===this) this.classList.remove('show')">
        <div class="overview-content">
            <h2 style="margin-top: 0;">ภาพรวมแอดมิน – ใครรับออเดอร์กี่ตัว (รายวัน / รายเดือน / รายปี)</h2>
            <?php if (empty($overview_chart_labels)): ?>
                <p style="color: var(--text-muted);">ยังไม่มีรายการที่รับแล้ว</p>
            <?php else: ?>
                <div class="overview-chart-wrap">
                    <canvas id="overviewAdminChart"></canvas>
                </div>
                <div class="overview-detail-toggle" onclick="var e=document.getElementById('overviewDetail'); var show=e.style.display==='none'; e.style.display=show?'block':'none'; this.textContent=show?'▲ ซ่อนรายการ':'▼ แสดงรายการรับงานแบบละเอียด';">
                    ▼ แสดงรายการรับงานแบบละเอียด
                </div>
                <div id="overviewDetail" class="overview-detail" style="display:none;">
                    <?php foreach ($overview as $admin_name => $orders): ?>
                        <h3><?= htmlspecialchars($admin_name) ?></h3>
                        <ul class="overview-list">
                            <?php foreach ($orders as $o): ?>
                                <li>
                                    <?= date('d/m/Y H:i', strtotime($o['created_at'])); ?>
                                    · <?= htmlspecialchars($o['customer_name']); ?>
                                    · <?= htmlspecialchars($o['product_name'] ?? '—'); ?>
                                    · <?= number_format((float)$o['points_used'], 0); ?> พอยท์
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <button type="button" class="overview-close" onclick="document.getElementById('overviewModal').classList.remove('show')">ปิด</button>
        </div>
    </div>

    <div id="chartModal" class="chart-modal" onclick="if(event.target===this) this.classList.remove('show')">
        <div class="chart-content">
            <h2 style="margin-top: 0;">กราฟยอดขาย – ออเดอร์ที่รับแล้ว</h2>
            <form method="get" action="admin_orders.php" class="chart-filters" id="chartFilterForm">
                <?php if (isset($_GET['inapp'])): ?><input type="hidden" name="inapp" value="1"><?php endif; ?>
                <label>
                    <span>ช่วงที่ดู</span>
                    <select name="chart_view" id="chartViewSelect" onchange="toggleChartFilters()">
                        <option value="30days" <?= $chart_view === '30days' ? 'selected' : '' ?>>30 วันล่าสุด</option>
                        <option value="day" <?= $chart_view === 'day' ? 'selected' : '' ?>>เลือกวัน</option>
                        <option value="month" <?= $chart_view === 'month' ? 'selected' : '' ?>>เลือกเดือน</option>
                        <option value="year" <?= $chart_view === 'year' ? 'selected' : '' ?>>เลือกปี</option>
                    </select>
                </label>
                <label id="chartDateWrap" style="display:<?= $chart_view === 'day' ? 'flex' : 'none' ?>;">
                    <span>วันที่</span>
                    <input type="date" name="chart_date" value="<?= htmlspecialchars($chart_view === 'day' ? $chart_date : date('Y-m-d')) ?>">
                </label>
                <label id="chartMonthWrap" style="display:<?= $chart_view === 'month' ? 'flex' : 'none' ?>;">
                    <span>เดือน</span>
                    <select name="chart_month">
                        <?php
                        $current_ym = ($chart_view === 'month' && $chart_month !== '') ? $chart_month : date('Y-m');
                        $seen = [];
                        for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--) {
                            for ($m = 1; $m <= 12; $m++) {
                                $ym = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                if (isset($seen[$ym])) continue;
                                $seen[$ym] = true;
                                echo '<option value="' . htmlspecialchars($ym) . '"' . ($current_ym === $ym ? ' selected' : '') . '>' . date('m/Y', strtotime($ym . '-01')) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </label>
                <label id="chartYearWrap" style="display:<?= $chart_view === 'year' ? 'flex' : 'none' ?>;">
                    <span>ปี</span>
                    <select name="chart_year">
                        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= ($chart_year === $y) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">ดูกราฟ</button>
            </form>
            <div class="chart-stats">
                <div class="chart-stat-card">
                    <div class="label"><?= $chart_view === 'day' ? 'วันที่เลือก' : 'วันนี้'; ?></div>
                    <div class="value"><?= $stats_today['count']; ?> ออเดอร์</div>
                    <div class="label"><?= number_format($stats_today['points'], 0); ?> พอยท์</div>
                </div>
                <div class="chart-stat-card">
                    <div class="label"><?= $chart_view === 'month' || $chart_view === 'year' ? 'ช่วงที่เลือก' : 'เดือนนี้ (' . date('m/Y') . ')'; ?></div>
                    <div class="value"><?= $stats_month['count']; ?> ออเดอร์</div>
                    <div class="label"><?= number_format($stats_month['points'], 0); ?> พอยท์</div>
                </div>
                <div class="chart-stat-card">
                    <div class="label"><?= $chart_view === 'year' ? 'ปีที่เลือก' : 'ปีนี้ (' . date('Y') . ')'; ?></div>
                    <div class="value"><?= $stats_year['count']; ?> ออเดอร์</div>
                    <div class="label"><?= number_format($stats_year['points'], 0); ?> พอยท์</div>
                </div>
            </div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($chart_title) ?></p>
            <div class="chart-wrap">
                <canvas id="salesChart"></canvas>
            </div>
            <button type="button" class="overview-close" onclick="document.getElementById('chartModal').classList.remove('show')">ปิด</button>
        </div>
    </div>
    <script>
    function toggleChartFilters(){
        var v = document.getElementById('chartViewSelect').value;
        document.getElementById('chartDateWrap').style.display = v === 'day' ? 'flex' : 'none';
        document.getElementById('chartMonthWrap').style.display = v === 'month' ? 'flex' : 'none';
        document.getElementById('chartYearWrap').style.display = v === 'year' ? 'flex' : 'none';
    }
    </script>
    <script>
    (function(){
        var chart = null;
        window.drawChart = function(){
            var ctx = document.getElementById('salesChart');
            if (!ctx) return;
            if (chart) { chart.destroy(); chart = null; }
            var labels = <?= json_encode($chart_labels); ?>;
            var counts = <?= json_encode($chart_counts); ?>;
            var points = <?= json_encode($chart_points); ?>;
            if (!labels.length) { labels = ['ไม่มีข้อมูล']; counts = [0]; points = [0]; }
            var isDark = !document.documentElement.getAttribute('data-theme') || document.documentElement.getAttribute('data-theme') !== 'light';
            var grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
            var text = isDark ? '#e8e8ed' : '#1a1a1f';
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'จำนวนออเดอร์', data: counts, backgroundColor: 'rgba(0,212,170,0.6)', borderColor: '#00d4aa', borderWidth: 1 },
                        { label: 'พอยท์รวม', data: points, backgroundColor: 'rgba(99,102,241,0.5)', borderColor: '#6366f1', borderWidth: 1, yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: text } } },
                    scales: {
                        x: { grid: { color: grid }, ticks: { color: text, maxRotation: 45 } },
                        y: { type: 'linear', position: 'left', grid: { color: grid }, ticks: { color: text }, title: { display: true, text: 'ออเดอร์', color: text } },
                        y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, ticks: { color: text }, title: { display: true, text: 'พอยท์', color: text } }
                    }
                }
            });
        };
        var overviewChart = null;
        window.drawOverviewChart = function(){
            var ctx = document.getElementById('overviewAdminChart');
            if (!ctx) return;
            if (overviewChart) overviewChart.destroy();
            var isDark = !document.documentElement.getAttribute('data-theme') || document.documentElement.getAttribute('data-theme') !== 'light';
            var grid = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
            var text = isDark ? '#e8e8ed' : '#1a1a1f';
            var labels = <?= json_encode($overview_chart_labels); ?>;
            var today = <?= json_encode($overview_chart_today); ?>;
            var month = <?= json_encode($overview_chart_month); ?>;
            var year = <?= json_encode($overview_chart_year); ?>;
            overviewChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'วันนี้', data: today, backgroundColor: 'rgba(0,212,170,0.7)', borderColor: '#00d4aa', borderWidth: 1 },
                        { label: 'เดือนนี้', data: month, backgroundColor: 'rgba(99,102,241,0.6)', borderColor: '#6366f1', borderWidth: 1 },
                        { label: 'ปีนี้', data: year, backgroundColor: 'rgba(236,72,153,0.5)', borderColor: '#ec4899', borderWidth: 1 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { color: text } } },
                    scales: {
                        x: { grid: { color: grid }, ticks: { color: text, maxRotation: 50 } },
                        y: { grid: { color: grid }, ticks: { color: text, stepSize: 1 }, title: { display: true, text: 'จำนวนออเดอร์', color: text } }
                    }
                }
            });
        };
        if (window.location.search.indexOf('chart_view') !== -1) {
            document.getElementById('chartModal').classList.add('show');
            setTimeout(function(){ drawChart(); }, 150);
        }
    })();
    // อัปเดตรายการออเดอร์อัตโนมัติทุก 60 วินาที — เห็นออเดอร์ใหม่โดยไม่ต้องกด refresh
    setInterval(function(){ window.location.reload(); }, 60000);
    if (window.parent !== window && window.location.search.indexOf('chat_open=1') !== -1) {
        try { window.parent.postMessage({ type: 'gbh_open_chat' }, '*'); } catch (e) {}
        try {
            var u = new URL(window.location.href);
            u.searchParams.delete('chat_open');
            window.history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
        } catch (e) {}
    }
    </script>
</body>
</html>

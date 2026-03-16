<?php
/**
 * API: รายการแชทแบบ Messenger — แต่ละแชทมี avatar, ชื่อ, ข้อความล่าสุด, เวลา (JSON).
 */
if (function_exists('ob_start')) ob_start();
@session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$json_fail = function() {
    if (function_exists('ob_end_clean')) @ob_end_clean();
    echo json_encode(['conversations' => []]);
    exit;
};
try {
    $config_path = (__DIR__ ?: dirname(__FILE__)) . '/config.php';
    $chat_db_path = (__DIR__ ?: dirname(__FILE__)) . '/chat_db.php';
    if (!is_file($config_path) || !is_file($chat_db_path)) $json_fail();
    require_once $config_path;
    require_once $chat_db_path;
} catch (Throwable $e) {
    $json_fail();
}
if (!isset($_SESSION['user_id'])) {
    $json_fail();
}

$me = (int) $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;

// ดึงคู่สนทนา: แอดมิน = ลูกค้าจากออเดอร์, ลูกค้า = แอดมินคนเดียว
$partner_ids = [];
if ($is_admin) {
    $col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
    if (!$col || $col->num_rows === 0) {
        @$conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL");
        @$conn->query("ALTER TABLE purchases ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending'");
    }
    $col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
    if ($col && $col->num_rows > 0) {
        $q = $conn->prepare("SELECT DISTINCT p.user_id FROM purchases p WHERE p.assigned_admin_id = ? AND p.user_id != ? ORDER BY p.user_id LIMIT 50");
        if ($q) {
            $q->bind_param('ii', $me, $me);
            $q->execute();
            $r = $q->get_result();
            while ($row = $r->fetch_assoc()) $partner_ids[] = (int) $row['user_id'];
            $q->close();
        }
    }
    // ไม่แสดงลูกค้าที่แอดมินกดจบงานแล้ว (ให้เหลือแต่หน้าว่าง)
    $te = @$conn->query("SHOW TABLES LIKE 'chat_task_ended'");
    if ($te && $te->num_rows > 0 && !empty($partner_ids)) {
        $ended = [];
        $chk = $conn->prepare("SELECT customer_id FROM chat_task_ended WHERE admin_id = ? AND customer_id IN (" . implode(',', array_fill(0, count($partner_ids), '?')) . ")");
        if ($chk) {
            $types = 'i' . str_repeat('i', count($partner_ids));
            $chk->bind_param($types, $me, ...$partner_ids);
            $chk->execute();
            $res_te = $chk->get_result();
            while ($row = $res_te->fetch_assoc()) $ended[] = (int) $row['customer_id'];
            $chk->close();
            $partner_ids = array_values(array_diff($partner_ids, $ended));
        }
    }
} else {
    $aid = null;
    if (isset($contact_admin) && is_array($contact_admin) && !empty($contact_admin['id'])) {
        $aid = (int) $contact_admin['id'];
    }
    // ออเดอร์ล่าสุดที่มี assigned_admin_id (pending หรือ accepted ก็แสดงแชทได้ — ไม่ต้องรอแอดมินกดรับ)
    if ($aid === null) {
        $pc = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
        if ($pc && $pc->num_rows > 0) {
            $sql = "SELECT assigned_admin_id FROM purchases WHERE user_id = ? AND assigned_admin_id IS NOT NULL AND assigned_admin_id > 0 ORDER BY created_at DESC LIMIT 1";
            $pq = $conn->prepare($sql);
            if ($pq) {
                $pq->bind_param("i", $me);
                $pq->execute();
                $pr = $pq->get_result();
                if ($pr && ($row = $pr->fetch_assoc()) && !empty($row['assigned_admin_id'])) {
                    $aid = (int) $row['assigned_admin_id'];
                }
                $pq->close();
            }
        }
    }
    if ($aid > 0) {
        $partner_ids[] = $aid;
        // ถ้าแอดมินจบงานกับลูกค้าแล้ว และลูกค้ายังไม่ได้ซื้อใหม่ → ไม่แสดงแชทในรายการ (หน้าว่างฝั่งลูกค้า)
        $te = @$conn->query("SHOW TABLES LIKE 'chat_task_ended'");
        if ($te && $te->num_rows > 0) {
            $chk = $conn->prepare("SELECT ended_at FROM chat_task_ended WHERE admin_id = ? AND customer_id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('ii', $aid, $me);
                $chk->execute();
                $row_te = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row_te && !empty($row_te['ended_at'])) {
                    $ended_at = $row_te['ended_at'];
                    $has_status_col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'admin_status'");
                    $reopened_sql = "SELECT 1 FROM purchases WHERE user_id = ? AND assigned_admin_id = ? AND created_at > ?";
                    if ($has_status_col && $has_status_col->num_rows > 0) $reopened_sql .= " AND (admin_status = 'accepted' OR admin_status IS NULL)";
                    $reopened_sql .= " LIMIT 1";
                    $pq = $conn->prepare($reopened_sql);
                    if ($pq) {
                        $pq->bind_param('iis', $me, $aid, $ended_at);
                        $pq->execute();
                        $reopened = $pq->get_result()->num_rows > 0;
                        $pq->close();
                        if (!$reopened) {
                            $partner_ids = [];
                        }
                    } else {
                        $partner_ids = [];
                    }
                }
            }
        }
    }
}

if (empty($partner_ids)) {
    echo json_encode(['conversations' => []]);
    exit;
}

// users: id, username, avatar (ถ้ามีคอลัมน์)
$has_avatar = @$conn->query("SHOW COLUMNS FROM users LIKE 'avatar'") && $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'")->num_rows > 0;
$placeholders = implode(',', array_fill(0, count($partner_ids), '?'));
$sel = "SELECT id, username" . ($has_avatar ? ", avatar" : "") . " FROM users WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sel);
if (!$stmt) {
    echo json_encode(['conversations' => []]);
    exit;
}
$types = str_repeat('i', count($partner_ids));
$stmt->bind_param($types, ...$partner_ids);
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($row = $res->fetch_assoc()) {
    $users[(int)$row['id']] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'avatar' => $has_avatar && !empty($row['avatar']) ? $row['avatar'] : null,
    ];
}
$stmt->close();

$read_since = 0;
$rs = $conn->prepare("SELECT read_at FROM user_chat_read_since WHERE user_id = ?");
if ($rs) {
    $rs->bind_param('i', $me);
    $rs->execute();
    $row = $rs->get_result()->fetch_assoc();
    $rs->close();
    if (!empty($row['read_at'])) $read_since = (int) $row['read_at'];
}
if (isset($_GET['client_read_since']) && is_numeric($_GET['client_read_since'])) {
    $client_since = (int) $_GET['client_read_since'];
    if ($client_since > $read_since) $read_since = $client_since;
}
if (isset($_COOKIE['gbh_chat_read_since']) && is_numeric($_COOKIE['gbh_chat_read_since'])) {
    $client_since = (int) $_COOKIE['gbh_chat_read_since'];
    if ($client_since > $read_since) $read_since = $client_since;
}

// แจ้งเตือนเฉพาะข้อความที่ส่งมาหาฉัน และยังไม่ขึ้นว่าอ่านแล้ว (created_at > read_since)
$conversations = [];
$read_dt = $read_since > 0 ? date('Y-m-d H:i:s', $read_since + 1) : null;
foreach (array_keys($users) as $pid) {
    $last = null;
    $q = $conn->prepare("SELECT id, from_user_id, body, created_at FROM chat_messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?) ORDER BY created_at DESC LIMIT 1");
    $q->bind_param('iiii', $me, $pid, $pid, $me);
    $q->execute();
    $res = $q->get_result();
    if ($row = $res->fetch_assoc()) {
        $last = ['body' => $row['body'], 'created_at' => $row['created_at'], 'from_me' => (int)$row['from_user_id'] === $me];
    }
    $q->close();
    $unread = 0;
    if ($read_dt) {
        $uq = $conn->prepare("SELECT COUNT(*) AS c FROM chat_messages WHERE from_user_id = ? AND to_user_id = ? AND created_at > ?");
        if ($uq) {
            $uq->bind_param('iis', $pid, $me, $read_dt);
            $uq->execute();
            $ur = $uq->get_result()->fetch_assoc();
            $uq->close();
            $unread = $ur ? (int) $ur['c'] : 0;
        }
    }
    $conversations[] = [
        'partner_id' => $pid,
        'username' => $users[$pid]['username'],
        'avatar' => $users[$pid]['avatar'],
        'last_message' => $last ? $last['body'] : null,
        'last_time' => $last ? $last['created_at'] : null,
        'last_from_me' => $last ? $last['from_me'] : false,
        'unread_count' => $unread,
    ];
}

// เรียงตามเวลาข้อความล่าสุด (มีข้อความมาก่อนอยู่บน)
usort($conversations, function ($a, $b) {
    $ta = $a['last_time'] ? strtotime($a['last_time']) : 0;
    $tb = $b['last_time'] ? strtotime($b['last_time']) : 0;
    return $tb - $ta;
});

if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
echo json_encode(['conversations' => $conversations]);

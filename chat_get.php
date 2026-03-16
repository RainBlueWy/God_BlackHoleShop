<?php
/**
 * API: ดึงข้อความแชทระหว่างผู้ใช้ปัจจุบันกับ with_user_id (JSON).
 */
session_start();
require_once 'config.php';
require_once 'chat_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$me = (int) $_SESSION['user_id'];
$with = isset($_GET['with']) ? (int) $_GET['with'] : 0;
if ($with <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;

// ลูกค้าเห็นข้อความกับแอดมินได้เมื่อมีออเดอร์ที่เลือกแอดมินแล้ว (pending หรือ accepted)
if (!$is_admin) {
    $allow = $conn->prepare("SELECT 1 FROM purchases WHERE user_id = ? AND assigned_admin_id = ? AND assigned_admin_id > 0 LIMIT 1");
    if ($allow) {
        $allow->bind_param('ii', $me, $with);
        $allow->execute();
        $ok = $allow->get_result()->num_rows > 0;
        $allow->close();
        if (!$ok) {
            echo json_encode(['messages' => []]);
            exit;
        }
    }
}

// ถ้าผู้เรียกเป็นลูกค้า และแอดมิน "จบงาน" แล้ว และลูกค้ายังไม่ได้ซื้อใหม่ → ไม่แสดงข้อความ
if (!$is_admin) {
    $te = @$conn->query("SHOW TABLES LIKE 'chat_task_ended'");
    if ($te && $te->num_rows > 0) {
        $chk = $conn->prepare("SELECT ended_at FROM chat_task_ended WHERE admin_id = ? AND customer_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('ii', $with, $me);
            $chk->execute();
            $res_te = $chk->get_result();
            $row_te = $res_te ? $res_te->fetch_assoc() : null;
            $chk->close();
            if ($row_te && !empty($row_te['ended_at'])) {
                $ended_at = $row_te['ended_at'];
                $has_status_col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'admin_status'");
                $reopened_sql = "SELECT 1 FROM purchases WHERE user_id = ? AND assigned_admin_id = ? AND created_at > ?";
                if ($has_status_col && $has_status_col->num_rows > 0) $reopened_sql .= " AND (admin_status = 'accepted' OR admin_status IS NULL)";
                $reopened_sql .= " LIMIT 1";
                $pq = $conn->prepare($reopened_sql);
                if ($pq) {
                    $pq->bind_param('iis', $me, $with, $ended_at);
                    $pq->execute();
                    $reopened = $pq->get_result()->num_rows > 0;
                    $pq->close();
                    if (!$reopened) {
                        echo json_encode(['messages' => []]);
                        exit;
                    }
                } else {
                    echo json_encode(['messages' => []]);
                    exit;
                }
            }
        }
    }
}

// ดึงข้อความทั้งจาก me->with และ with->me
$stmt = $conn->prepare("
    SELECT id, from_user_id, to_user_id, body, created_at
    FROM chat_messages
    WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
    ORDER BY created_at ASC
");
$stmt->bind_param('iiii', $me, $with, $with, $me);
$stmt->execute();
$res = $stmt->get_result();
$list = [];
while ($row = $res->fetch_assoc()) {
    $list[] = [
        'id' => (int) $row['id'],
        'from_user_id' => (int) $row['from_user_id'],
        'to_user_id' => (int) $row['to_user_id'],
        'body' => $row['body'],
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode(['messages' => $list]);

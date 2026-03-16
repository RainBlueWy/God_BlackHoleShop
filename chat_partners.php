<?php
/**
 * API: รายชื่อคนที่แชทด้วยได้ — แอดมินได้ลูกค้าจากออเดอร์ที่รับ, ลูกค้าได้แอดมินคนเดียว (JSON).
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['partners' => []]);
    exit;
}

$me = (int) $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;

if ($is_admin) {
    // แอดมิน: ลูกค้าที่มีออเดอร์ที่ assigned_admin_id = ฉัน (ไม่ซ้ำ)
    $col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
    if (!$col || $col->num_rows === 0) {
        echo json_encode(['partners' => []]);
        exit;
    }
    $q = $conn->prepare("
        SELECT DISTINCT u.id, u.username
        FROM purchases p
        JOIN users u ON u.id = p.user_id
        WHERE p.assigned_admin_id = ? AND u.id != ?
        ORDER BY u.username
        LIMIT 50
    ");
    if (!$q) {
        echo json_encode(['partners' => []]);
        exit;
    }
    $q->bind_param('ii', $me, $me);
    $q->execute();
    $res = $q->get_result();
} else {
    // ลูกค้า: แอดมินคนเดียวจาก contact_admin
    $partners = [];
    if (isset($contact_admin) && is_array($contact_admin) && !empty($contact_admin['id'])) {
        $aid = (int) $contact_admin['id'];
        $st = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
        if ($st) {
            $st->bind_param('i', $aid);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            if ($r) $partners[] = ['id' => (int)$r['id'], 'username' => $r['username']];
            $st->close();
        }
    }
    echo json_encode(['partners' => $partners]);
    exit;
}

$partners = [];
while ($row = $res->fetch_assoc()) {
    $partners[] = ['id' => (int) $row['id'], 'username' => $row['username']];
}
$q->close();

echo json_encode(['partners' => $partners]);

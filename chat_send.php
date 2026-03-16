<?php
/**
 * API: ส่งข้อความแชท (POST to_user_id, body). คืน JSON { ok: true, id, created_at } หรือ error.
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
$to_user_id = isset($_POST['to_user_id']) ? (int) $_POST['to_user_id'] : 0;
$body = isset($_POST['body']) ? trim($_POST['body']) : '';

if ($to_user_id <= 0 || $body === '') {
    echo json_encode(['error' => 'missing to_user_id or body']);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;
// ลูกค้าส่งถึงแอดมินได้เมื่อมีออเดอร์ที่เลือกแอดมินแล้ว (pending หรือ accepted)
if (!$is_admin) {
    $allow = $conn->prepare("SELECT 1 FROM purchases WHERE user_id = ? AND assigned_admin_id = ? AND assigned_admin_id > 0 LIMIT 1");
    if ($allow) {
        $allow->bind_param('ii', $me, $to_user_id);
        $allow->execute();
        $ok = $allow->get_result()->num_rows > 0;
        $allow->close();
        if (!$ok) {
            echo json_encode(['error' => 'unauthorized']);
            exit;
        }
    }
}

// จำกัดความยาว
if (mb_strlen($body) > 2000) {
    $body = mb_substr($body, 0, 2000);
}

$stmt = $conn->prepare("INSERT INTO chat_messages (from_user_id, to_user_id, body) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $me, $to_user_id, $body);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'send_failed']);
    exit;
}
$id = (int) $conn->insert_id;
$stmt->close();

echo json_encode([
    'ok' => true,
    'id' => $id,
    'created_at' => date('Y-m-d H:i:s'),
]);

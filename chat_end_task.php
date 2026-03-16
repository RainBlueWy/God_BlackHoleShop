<?php
/**
 * API: แอดมินกดจบงาน — ลบประวัติแชทกับลูกค้าคนนี้ + บันทึกจบงาน (ลูกค้าจะไม่เห็นข้อความแอดมินจนกว่าจะซื้อใหม่)
 */
session_start();
require_once 'config.php';
require_once 'chat_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$admin_id = (int) $_SESSION['user_id'];
$customer_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
if ($customer_id <= 0) {
    echo json_encode(['error' => 'missing customer_id']);
    exit;
}

// ลบข้อความทั้งหมดระหว่างแอดมินกับลูกค้านี้ (รีเซ็ตประวัติ)
$del = $conn->prepare("
    DELETE FROM chat_messages
    WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
");
$del->bind_param('iiii', $admin_id, $customer_id, $customer_id, $admin_id);
$del->execute();
$del->close();

// บันทึกว่าจบงานแล้ว (ลูกค้าจะไม่เห็นข้อความแอดมินจนกว่าจะซื้อใหม่)
$stmt = $conn->prepare("
    INSERT INTO chat_task_ended (admin_id, customer_id, ended_at) VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE ended_at = NOW()
");
$stmt->bind_param('ii', $admin_id, $customer_id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);

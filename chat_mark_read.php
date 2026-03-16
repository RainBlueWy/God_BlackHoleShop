<?php
/**
 * บันทึกเวลาที่ผู้ใช้ "อ่านแล้ว" บนเซิร์ฟเวอร์ (ใช้เวลาเซิร์ฟเวอร์เท่านั้น)
 * เรียกเมื่อเปิดแผงแชท / เข้าแชท / โหลดข้อความ / ส่งข้อความ
 */
session_start();
require_once 'config.php';
require_once 'chat_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$uid = (int) $_SESSION['user_id'];
if ($uid <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}
$now = time();
// รองรับการส่งเวลาข้อความล่าสุดที่อ่านแล้ว (Unix) เพื่อไม่ให้แบดจ์โผล่กลับหลังปิดแชท
$latest = 0;
if (isset($_GET['latest']) && is_numeric($_GET['latest'])) {
    $latest = (int) $_GET['latest'];
} elseif (isset($_POST['latest']) && is_numeric($_POST['latest'])) {
    $latest = (int) $_POST['latest'];
}
// ถ้ามี latest ให้บวก 1 วินาที เพื่อกันข้อความที่ created_at พอดีกับวินาทีนั้นยังถูกนับเป็นยังไม่อ่าน
// ถ้าไม่มี (ปิดแผง) ใช้เวลาปัจจุบัน+1 วินาที เพื่อให้ข้อความทั้งหมดที่อยู่ก่อนหน้านี้ถือว่าอ่านแล้ว แบดจ์จะไม่โผล่กลับ
$read_at = $now + 1;
if ($latest > 0) {
    $read_at = ($latest + 1 > $read_at) ? ($latest + 1) : $read_at;
}
$_SESSION['chat_read_since'] = $read_at;

$ok = false;
$stmt = $conn->prepare("INSERT INTO user_chat_read_since (user_id, read_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE read_at = GREATEST(read_at, ?)");
if ($stmt) {
    $stmt->bind_param('iii', $uid, $read_at, $read_at);
    $ok = $stmt->execute();
    $stmt->close();
}
echo json_encode(['ok' => $ok]);

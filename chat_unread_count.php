<?php
/**
 * API: จำนวนข้อความที่ส่งมาหาฉันและยังไม่ได้เปิดดู (to_user_id = ฉัน, created_at > since).
 * ข้อความเก่าที่เคยส่งแล้วไม่นับ — เฉพาะข้อความที่ส่งมาหลัง read_since เท่านั้น
 * GET since = unix timestamp (วินาที) หรือไม่ส่ง = ใช้ read_at จาก DB/cookie
 * GET get_time=1 = คืนค่า server_time (unix) เพื่อให้ client ใช้เป็น "อ่านแล้ว" ให้ตรงกับ DB
 */
session_start();
require_once 'config.php';
require_once 'chat_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'server_time' => time()]);
    exit;
}

$me = (int) $_SESSION['user_id'];
$get_time = isset($_GET['get_time']) && $_GET['get_time'] === '1';
$since = 0;
$stmt = $conn->prepare("SELECT read_at FROM user_chat_read_since WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($row['read_at'])) {
        $since = (int) $row['read_at'];
    }
}
if ($since <= 0 && isset($_SESSION['chat_read_since'])) {
    $since = (int) $_SESSION['chat_read_since'];
}
if ($since <= 0 && isset($_GET['since'])) {
    $since = (int) $_GET['since'];
}
if (isset($_GET['client_read_since']) && is_numeric($_GET['client_read_since'])) {
    $client_since = (int) $_GET['client_read_since'];
    if ($client_since > $since) $since = $client_since;
}
if (isset($_COOKIE['gbh_chat_read_since']) && is_numeric($_COOKIE['gbh_chat_read_since'])) {
    $client_since = (int) $_COOKIE['gbh_chat_read_since'];
    if ($client_since > $since) $since = $client_since;
}

if ($get_time) {
    echo json_encode(['server_time' => time()]);
    exit;
}

$count = 0;
$sender_name = '';
$since_dt = null;
// แจ้งเตือนเฉพาะข้อความที่ส่งมาหาฉัน และยังไม่ขึ้นว่าอ่านแล้ว (created_at > read_since)
if ($since > 0) {
    $since_dt = date('Y-m-d H:i:s', $since + 1);
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM chat_messages WHERE to_user_id = ? AND created_at > ?");
    if ($stmt) {
        $stmt->bind_param('is', $me, $since_dt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $count = $row ? (int) $row['c'] : 0;
        $stmt->close();
    }
}
// since <= 0 = ยังไม่มีจุดตัด "อ่านแล้ว" → ไม่นับข้อความเก่าเป็นแจ้งเตือน
$latest_unread_at = null;
if ($count > 0) {
    $sql = $since_dt
        ? "SELECT from_user_id, created_at FROM chat_messages WHERE to_user_id = ? AND created_at > ? ORDER BY created_at DESC LIMIT 1"
        : "SELECT from_user_id, created_at FROM chat_messages WHERE to_user_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($since_dt) {
            $stmt->bind_param('is', $me, $since_dt);
        } else {
            $stmt->bind_param('i', $me);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $uid = (int) $row['from_user_id'];
            if (!empty($row['created_at'])) {
                $latest_unread_at = strtotime($row['created_at']);
            }
            $u = $conn->prepare("SELECT username FROM users WHERE id = ?");
            if ($u) {
                $u->bind_param('i', $uid);
                $u->execute();
                $ur = $u->get_result()->fetch_assoc();
                $u->close();
                if ($ur && !empty($ur['username'])) $sender_name = $ur['username'];
            }
        }
    }
}
echo json_encode(['count' => $count, 'sender_name' => $sender_name, 'latest_unread_at' => $latest_unread_at]);

<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount'] ?? 0);

if ($amount < 10) {
    echo json_encode(['success' => false, 'message' => 'จำนวนเงินไม่ถูกต้อง (ขั้นต่ำ 10 บาท)']);
    exit;
}

// Handle File Upload
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'กรุณาอัปโหลดสลิป']);
    exit;
}

$file = $_FILES['slip'];
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

// Vefify Mime Type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่รองรับ รองรับเฉพาะ JPG/PNG']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($extension)) {
    if ($mime_type == 'image/png') $extension = 'png';
    else $extension = 'jpg';
}

$new_filename = 'slip_' . uniqid() . '_' . $user_id . '.' . $extension;
$upload_dir = __DIR__ . '/uploads/slips/';

// Ensure directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$upload_path = $upload_dir . $new_filename;

if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'บันทึกรูปภาพไม่สำเร็จ']);
    exit;
}

// รายการแนบสลิปใช้ reference_no เป็น slip_xxx (ไม่ใช้ NULL เพื่อรองรับ DB ที่กำหนด reference_no เป็น NOT NULL)
$reference_no = 'slip_' . uniqid() . '_' . $user_id;

// รองรับทั้ง schema เก่า (มี payment_method) และใหม่
$chk = $conn->query("SHOW COLUMNS FROM topups LIKE 'payment_method'");
$has_payment_method = $chk && $chk->num_rows > 0;
if ($has_payment_method) {
    $stmt = $conn->prepare("INSERT INTO topups (user_id, amount, reference_no, slip_image, payment_method, status) VALUES (?, ?, ?, ?, '', 'pending')");
} else {
    $stmt = $conn->prepare("INSERT INTO topups (user_id, amount, reference_no, slip_image, status) VALUES (?, ?, ?, ?, 'pending')");
}
if ($stmt) {
    $stmt->bind_param("idss", $user_id, $amount, $reference_no, $new_filename);
}
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
if ($stmt->execute()) {
    // ดึงยอดพอยท์ปัจจุบันเพื่อแสดงในข้อความ
    $pts = $conn->query("SELECT points FROM users WHERE id = " . (int)$user_id);
    $current_points = '0.00';
    if ($pts && $row = $pts->fetch_assoc()) {
        $current_points = number_format((float)$row['points'], 2);
    }
    echo json_encode([
        'success' => true,
        'message' => 'แจ้งโอนเงินสำเร็จ! กรุณารอแอดมินตรวจสอบ',
        'current_points' => $current_points
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

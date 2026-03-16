<?php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $response['message'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (strlen($username) < 3) {
        $response['message'] = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
    } elseif (!validate_email($email)) {
        $response['message'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $response['message'] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $confirm_password) {
        $response['message'] = 'รหัสผ่านไม่ตรงกัน';
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['message'] = 'ชื่อผู้ใช้หรืออีเมลหรือUserนี้ถูกใช้งานแล้ว';
        } else {
            // NEW: If table is empty, reset ID counter to 1
            $count_res = $conn->query("SELECT COUNT(*) as total FROM users");
            $count_row = $count_res->fetch_assoc();
            if ($count_row['total'] == 0) {
                $conn->query("ALTER TABLE users AUTO_INCREMENT = 1");
            }

            // Insert new user
            $hashed_password = hash_password($password);
            $insert_sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'สมัครสมาชิกสำเร็จ! กำลังเข้าสู่ระบบ...';
                
                // Auto login after registration
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 1; // Default role for new users is 1 (User)
                
                $response['redirect'] = 'app.php?page=profile'; // Redirect to app so music works
            } else {
                $response['message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            }
        }
        $stmt->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>

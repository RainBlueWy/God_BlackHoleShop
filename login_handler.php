<?php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password)) {
        $response['message'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        // Check user credentials
        $sql = "SELECT id, username, email, password, is_active FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['is_active'] == 0) {
                $response['message'] = 'บัญชีของคุณถูกระงับ (โดนแบน)';
            } elseif (verify_password($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['is_active']; // Store role in session (1=User, 10=Admin)
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $response['success'] = true;
                $response['message'] = 'เข้าสู่ระบบสำเร็จ!';
                
                // Redirect to app wrapper so music continues across pages (แอดมินไปหน้ารายการสั่งซื้อ)
                if ($user['is_active'] == 10) {
                    $response['redirect'] = 'app.php?page=admin_orders';
                } else {
                    $response['redirect'] = 'app.php?page=profile';
                }
            } else {
                sleep(2); // Brute-force protection: Delay response by 2 seconds
                $response['message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } else {
            sleep(2); // Brute-force protection: Delay response by 2 seconds
            $response['message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
        $stmt->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>

<?php
/**
 * Database Configuration
 * God_BlackHole Website
 */
define('LIVE_RELOAD', true); // Set to false to disable auto-update

// true = ไม่ให้คลิกขวา (กัน context menu) , false = ให้คลิกขวาได้
define('DISABLE_RIGHT_CLICK', false);

// Database credentials
// Detect environment
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

if ($is_localhost) {
    // XAMPP: ถ้า MySQL ใช้พอร์ต 3306 ใช้ 'localhost' ; ถ้าเป็น 3308 ใช้ '127.0.0.1:3308'
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'godblackholedb'); // สร้าง DB นี้ใน phpMyAdmin ถ้ายังไม่มี
} else {
    // Database credentials for InfinityFree (จาก MySQL Connection Details ใน cPanel)
    define('DB_HOST', 'sql104.infinityfree.com');
    define('DB_USER', 'if0_41391330');
    define('DB_PASS', 'FpnaemoKsn7Aw5T');
    define('DB_NAME', 'if0_41391330_ad_name');
}

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ซิงค์ role ใน session จาก DB ทุกครั้งที่โหลด — เมนูแอดมินจะขึ้นทันทีหลังถูกตั้งเป็นแอดมิน (ไม่ต้องออกแล้วเข้าใหม่)
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc() && isset($row['is_active'])) {
            $_SESSION['role'] = (int) $row['is_active'];
        }
        $stmt->close();
    }
}

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Encryption Configuration
define('ENCRYPTION_KEY', 'GodBlackHoleSecretKey2024!!Secure'); // 32 characters for AES-256
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Function to encrypt password (Two-Way Encryption)
function hash_password($password) {
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($password, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    // Return IV + Encrypted Data (Base64 encoded)
    return base64_encode($iv . $encrypted);
}

// Function to verify password
function verify_password($password, $stored_value) {
    // 1. Try to decrypt (New System)
    $decrypted = decrypt_password($stored_value);
    if ($decrypted !== false) {
        return $password === $decrypted;
    }
    
    // 2. Try to verify as Hash (Old System - for Admin/Old accounts)
    if (password_verify($password, $stored_value)) {
        return true;
    }
    
    // 3. Last resort: Direct comparison (Old Plain Text System)
    return $password === $stored_value;
}

// Function to decrypt password (For Admin Panel)
function decrypt_password($encrypted_data) {
    $data = base64_decode($encrypted_data);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    
    // Check if data is long enough
    if (strlen($data) < $iv_length) {
        return false;
    }
    
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

// Function to re-index all user IDs sequentially
function reindexUsers() {
    global $conn;
    
    // 1. Get all users ordered by ID
    $result = $conn->query("SELECT id FROM users ORDER BY id ASC");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row['id'];
    }

    if (empty($users)) {
        $conn->query("ALTER TABLE users AUTO_INCREMENT = 1");
        return;
    }

    // 2. Disable Foreign Key Checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    $new_id = 1;
    foreach ($users as $old_id) {
        if ($old_id != $new_id) {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_id, $old_id);
            $stmt->execute();
            
            // Update user_sessions if they exist
            $stmt_s = $conn->prepare("UPDATE user_sessions SET user_id = ? WHERE user_id = ?");
            $stmt_s->bind_param("ii", $new_id, $old_id);
            $stmt_s->execute();
        }
        $new_id++;
    }

    // 3. Reset Auto Increment
    $conn->query("ALTER TABLE users AUTO_INCREMENT = $new_id");

    // 4. Re-enable Foreign Key Checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// Global user points
$user_points = '0.00';
if (isset($_SESSION['user_id']) && isset($conn)) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $user_points = number_format((float)$row['points'], 2);
        }
        $stmt->close();
    }
}

// แอดมินที่ลูกค้าเลือก หรือแอดมินจากออเดอร์ที่ซื้อไปแล้ว (สำหรับปุ่มติดต่อ/แชททุกหน้า)
$contact_admin = null;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $cx = @$conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
    if ($cx && $cx->num_rows === 0) $conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
    $aid = null;
    $uid = (int)$_SESSION['user_id'];
    if ($uid > 0) {
        // 1) ออเดอร์ล่าสุดที่มี assigned_admin_id (pending หรือ accepted ก็ให้เห็นแชท/ปุ่มติดต่อ)
        $pc = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
        if ($pc && $pc->num_rows > 0) {
            $sql = "SELECT assigned_admin_id FROM purchases WHERE user_id = ? AND assigned_admin_id IS NOT NULL AND assigned_admin_id > 0 ORDER BY created_at DESC LIMIT 1";
            $pq = $conn->prepare($sql);
            if ($pq) {
                $pq->bind_param("i", $uid);
                if (@$pq->execute()) {
                    $pr = $pq->get_result();
                    if ($pr && ($row = $pr->fetch_assoc()) && !empty($row['assigned_admin_id'])) {
                        $aid = (int)$row['assigned_admin_id'];
                        $_SESSION['selected_admin_id'] = $aid;
                    }
                }
                $pq->close();
            }
        }
        // 2) เฉพาะออเดอร์เก่าที่ไม่มี assigned_admin_id (ก่อนมีระบบเลือกแอดมิน) — ถึงจะใช้แอดมินคนใดก็ได้ที่ตั้ง Line (ออเดอร์ใหม่ต้องรอแอดมินกดรับก่อนถึงเห็นแชท)
        if ($aid === null && $pc && $pc->num_rows > 0) {
                $has_old_purchase = $conn->prepare("SELECT 1 FROM purchases WHERE user_id = ? AND assigned_admin_id IS NULL LIMIT 1");
                if ($has_old_purchase) {
                    $has_old_purchase->bind_param("i", $uid);
                    if (@$has_old_purchase->execute()) {
                        $hr = $has_old_purchase->get_result();
                        if ($hr && $hr->num_rows > 0) {
                            $fallback = @$conn->query("SELECT id, username, contact_line FROM users WHERE is_active = 10 AND contact_line IS NOT NULL AND TRIM(contact_line) != '' ORDER BY id ASC LIMIT 1");
                            if ($fallback && $fallback->num_rows > 0) {
                                $fb = $fallback->fetch_assoc();
                                if (!empty($fb['id'])) {
                                    $aid = (int)$fb['id'];
                                    $_SESSION['selected_admin_id'] = $aid;
                                }
                            }
                        }
                    }
                    $has_old_purchase->close();
                }
        }
    }
    if ($aid > 0) {
        $st = $conn->prepare("SELECT id, username, contact_line FROM users WHERE id = ? AND is_active = 10");
        if ($st) {
            $st->bind_param("i", $aid);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) {
                $contact_admin = ['id' => (int)$r['id'], 'name' => $r['username']];
                $cl = isset($r['contact_line']) ? trim($r['contact_line']) : '';
                if ($cl !== '') {
                    $contact_admin['url'] = (stripos($cl, 'http') === 0) ? $cl : ('https://line.me/ti/p/~' . $cl);
                }
            }
        }
    }
}
?>

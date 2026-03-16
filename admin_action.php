<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

// Check if admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: index.php");
    exit();
}

$action = $_GET['action'] ?? '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- USER MANAGEMENT ---
    
    if ($action === 'edit_user') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $new_id = filter_input(INPUT_POST, 'new_id', FILTER_VALIDATE_INT);
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $role = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
        $password = $_POST['password'];

        if (!$id || !$new_id || empty($username) || empty($email)) {
            $_SESSION['error'] = "Missing required fields.";
            header("Location: admin_panel.php");
            exit();
        }

        // Validate Role (0, 1, 10)
        if (!in_array($role, [0, 1, 10])) {
            $_SESSION['error'] = "Invalid Role. Use 0, 1, or 10.";
            header("Location: admin_user_edit.php?id=$id");
            exit();
        }
        
        // Start Transaction for ID update safety
        $conn->begin_transaction();

        try {
            // Ensure contact_line column exists
            $col = $conn->query("SHOW COLUMNS FROM users LIKE 'contact_line'");
            if ($col->num_rows === 0) $conn->query("ALTER TABLE users ADD COLUMN contact_line VARCHAR(255) DEFAULT NULL AFTER is_active");
            $contact_line = isset($_POST['contact_line']) ? trim($_POST['contact_line']) : '';
            // Update basic info first
            $sql = "UPDATE users SET username = ?, email = ?, is_active = ?, contact_line = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisi", $username, $email, $role, $contact_line, $id);
            $stmt->execute();
            $stmt->close();

            // Update Password if provided
            if (!empty($password)) {
                // Determine encryption method based on current system (using hash_password from config.php)
                // Assuming hash_password uses the customized encryption/hashing logic
                $encrypted_pass = hash_password($password); 
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $encrypted_pass, $id);
                $stmt->execute();
                $stmt->close();
            }

            // Update ID if changed
            if ($id !== $new_id) {
                // Check if new ID exists
                $check = $conn->query("SELECT id FROM users WHERE id = $new_id");
                if ($check->num_rows > 0) {
                    throw new Exception("New ID $new_id already exists.");
                }

                // If we have foreign keys (like user_sessions), we need to update them OR rely on ON UPDATE CASCADE
                // Since this is a simple setup, we'll just update the user ID. 
                // WARNING: If there are other tables referencing users without CASCADE, this will break.
                // Assuming 'user_sessions' might exist, let's try to update it manually if strict mode isn't on, 
                // but standard practice is updating the parent.
                
                $sql = "UPDATE users SET id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $new_id, $id);
                $stmt->execute();
                $stmt->close();
                
                $id = $new_id; // Update Current ID handler
            }

            $conn->commit();
            $_SESSION['success'] = "User updated successfully.";
            header("Location: admin_panel.php");

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error updating user: " . $e->getMessage();
            header("Location: admin_user_edit.php?id=$id");
        }
        exit();
    }

    if ($action === 'delete_user') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if ($id) {
            // Prevent deleting yourself or other admins
            $check_admin = $conn->query("SELECT is_active FROM users WHERE id = $id");
            $u_data = $check_admin->fetch_assoc();
            
            if ($id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete yourself.";
            } elseif ($u_data['is_active'] == 10) {
                $_SESSION['error'] = "You cannot delete another Admin account.";
            } else {
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "User deleted successfully.";
                    reindexUsers(); // Automate re-indexing to maintain sequential IDs
                } else {
                    $_SESSION['error'] = "Error deleting user.";
                }
                $stmt->close();
            }
        }
        header("Location: admin_panel.php");
        exit();
    }

    // --- PRODUCT MANAGEMENT ---

    if ($action === 'add_product' || $action === 'edit_product') {
        $redirect_product = "admin_products.php?inapp=1";
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $price = trim((string)($_POST['price'] ?? ''));
        // ถ้าใส่แค่เลข ให้ลงท้ายด้วย ฿
        if ($price !== '' && $price !== 'ฟรี' && preg_match('/^\d+(\.\d+)?\s*$/', $price)) {
            $price = trim($price) . '฿';
        }
        $max_slots = isset($_POST['max_slots']) ? max(0, (int)$_POST['max_slots']) : 0;
        $description = (string)($_POST['description'] ?? '');
        // Image Handling
        $image_url = trim((string)($_POST['image_url'] ?? ''));

        if (empty($name) || empty($slug) || empty($category) || empty($price)) {
            $_SESSION['error'] = "กรุณากรอก ชื่อสินค้า, URL Slug, หมวดหมู่ และราคา ให้ครบ";
            header("Location: " . $redirect_product);
            exit();
        }

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image_file']['tmp_name'];
            $file_name = $_FILES['image_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed)) {
                $new_name = uniqid('prod_', true) . '.' . $file_ext;
                $upload_path = $new_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $image_url = $new_name;
                } else {
                    $_SESSION['error'] = "อัปโหลดรูปไม่สำเร็จ (Failed to upload image).";
                    header("Location: " . $redirect_product);
                    exit();
                }
            } else {
                $_SESSION['error'] = "ประเภทไฟล์รูปไม่รองรับ ใช้ได้เฉพาะ JPG, PNG, GIF, WEBP";
                header("Location: " . $redirect_product);
                exit();
            }
        }

        if ($action === 'add_product' && $image_url === '') {
            $_SESSION['error'] = "กรุณาใส่รูปภาพ (อัปโหลดไฟล์ หรือใส่ URL รูป)";
            header("Location: " . $redirect_product);
            exit();
        }
        if ($action === 'edit_product' && $image_url === '' && $id) {
            $old = $conn->query("SELECT image FROM products WHERE id = " . (int)$id . " LIMIT 1");
            if ($old) {
                $row = $old->fetch_assoc();
                if ($row && !empty($row['image'])) {
                    $image_url = $row['image'];
                }
            }
        }

        $script_content = (string)($_POST['script_content'] ?? '');

        // Handle Feature List (Convert new lines to JSON array)
        $features_text = (string)($_POST['features'] ?? '');
        $features_array = array_filter(array_map('trim', explode("\n", $features_text)));
        $features_json = json_encode(array_values($features_array), JSON_UNESCAPED_UNICODE);

        // Ensure max_slots column exists (for older DBs)
        $col = $conn->query("SHOW COLUMNS FROM products LIKE 'max_slots'");
        if ($col->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN max_slots INT(11) NOT NULL DEFAULT 0 COMMENT 'รับกี่คน (0 = ไม่จำกัด)' AFTER price");
        }
        // Ensure sold_count column exists (ใช้แสดง เหลือ X คน; แอดมินกดบันทึกแก้ไข = รีเซ็ตเป็น รับ X เหลือ X)
        $col_sold = $conn->query("SHOW COLUMNS FROM products LIKE 'sold_count'");
        if ($col_sold->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sold_count INT(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนที่ถือว่าขายแล้ว (รีเซ็ตเป็น 0 ตอนแอดมินกดบันทึกแก้ไข)' AFTER max_slots");
            // ค่าเริ่มต้นให้ตรงกับจำนวนที่ซื้อไปแล้ว
            $conn->query("UPDATE products p SET p.sold_count = (SELECT COUNT(*) FROM purchases WHERE product_id = p.id)");
        }
        // ส่วนลด: ราคาหลังลด (ว่าง = ไม่มีส่วนลด)
        $col_sale = $conn->query("SHOW COLUMNS FROM products LIKE 'sale_price'");
        if ($col_sale->num_rows === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN sale_price VARCHAR(50) DEFAULT NULL COMMENT 'ราคาหลังลด (เช่น 20฿) ว่าง = ไม่มีส่วนลด' AFTER price");
        }
        $sale_price = trim((string)($_POST['sale_price'] ?? ''));
        if ($sale_price !== '' && $sale_price !== 'ฟรี' && preg_match('/^\d+(\.\d+)?\s*$/', $sale_price)) {
            $sale_price = trim($sale_price) . '฿';
        }
        if ($sale_price === '') $sale_price = null;

        if ($action === 'add_product') {
            $sql = "INSERT INTO products (name, slug, category, price, sale_price, max_slots, sold_count, description, image, features, script_content) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssissss", $name, $slug, $category, $price, $sale_price, $max_slots, $description, $image_url, $features_json, $script_content);
        } else {
            $sql = "UPDATE products SET name=?, slug=?, category=?, price=?, sale_price=?, max_slots=?, sold_count=0, description=?, image=?, features=?, script_content=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssissssi", $name, $slug, $category, $price, $sale_price, $max_slots, $description, $image_url, $features_json, $script_content, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "บันทึกสินค้าเรียบร้อย (จะแสดงในหมวดหมู่แล้ว)";
            @file_put_contents(__DIR__ . '/products_sse_version.txt', (string)time());
        } else {
            $_SESSION['error'] = "บันทึกไม่สำเร็จ: " . $stmt->error . " (ตรวจสอบว่า URL Slug ไม่ซ้ำกับสินค้าอื่น)";
        }
        $stmt->close();
        header("Location: " . $redirect_product);
        exit();
    }

    if ($action === 'delete_product') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id) {
            $sql = "DELETE FROM products WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "ลบสินค้าแล้ว";
                @file_put_contents(__DIR__ . '/products_sse_version.txt', (string)time());
            } else {
                $_SESSION['error'] = "ลบสินค้าไม่สำเร็จ";
            }
            $stmt->close();
        }
        header("Location: admin_products.php?inapp=1");
        exit();
    }

    // --- TICKER (แถบข่าว) ---
    if ($action === 'save_ticker') {
        $content = trim($_POST['ticker_text'] ?? '');
        $is_active = isset($_POST['ticker_enabled']) ? 1 : 0;

        $conn->query("CREATE TABLE IF NOT EXISTS ticker (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $check = $conn->query("SELECT id FROM ticker WHERE id = 1");
        if ($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE ticker SET content = ?, is_active = ? WHERE id = 1");
            $stmt->bind_param("si", $content, $is_active);
        } else {
            $stmt = $conn->prepare("INSERT INTO ticker (id, content, is_active) VALUES (1, ?, ?)");
            $stmt->bind_param("si", $content, $is_active);
        }
        if ($stmt->execute()) {
            $_SESSION['success'] = "บันทึกแถบข่าวแล้ว";
        } else {
            $_SESSION['error'] = "บันทึกแถบข่าวไม่สำเร็จ";
        }
        $stmt->close();
        header("Location: admin_panel.php");
        exit();
    }

    if ($action === 'delete_ticker') {
        $conn->query("CREATE TABLE IF NOT EXISTS ticker (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("INSERT IGNORE INTO ticker (id, content, is_active) VALUES (1, '', 0)");
        $stmt = $conn->prepare("UPDATE ticker SET content = '', is_active = 0 WHERE id = 1");
        if ($stmt->execute()) {
            $_SESSION['success'] = "ปิด/ลบแถบข่าวแล้ว";
        }
        $stmt->close();
        header("Location: admin_panel.php");
        exit();
    }

    // --- ตั้งค่าสถานะของแอดมิน (ว่าง/ไม่ว่าง/ไม่อยู่) — ลูกค้าเห็นเฉพาะแอดมินที่ว่าง ---
    if ($action === 'set_my_availability') {
        $av = isset($_POST['availability']) ? (int) $_POST['availability'] : 0;
        if (!in_array($av, [1, 2, 3], true)) {
            $_SESSION['error'] = 'สถานะไม่ถูกต้อง';
            header('Location: admin_panel.php?inapp=1');
            exit;
        }
        $uid = (int) $_SESSION['user_id'];
        $col = @$conn->query("SHOW COLUMNS FROM users LIKE 'availability'");
        if ($col && $col->num_rows === 0) {
            @$conn->query("ALTER TABLE users ADD COLUMN availability TINYINT NOT NULL DEFAULT 1 COMMENT '1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่' AFTER is_active");
        }
        $stmt = $conn->prepare("UPDATE users SET availability = ? WHERE id = ?");
        $stmt->bind_param("ii", $av, $uid);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'บันทึกสถานะแล้ว (ลูกค้าเลือกได้เมื่อคุณตั้งเป็น ว่าง หรือ ไม่อยู่)';
        header('Location: admin_panel.php?inapp=1');
        exit;
    }
}

// Redirect if accessed directly without POST or valid action
header("Location: admin_panel.php");
?>

<?php
/**
 * Ensure chat_messages table exists for in-web chat (แอดมิน–ลูกค้า).
 */
if (!isset($conn)) {
    session_start();
    require_once __DIR__ . '/config.php';
}
$t = @$conn->query("SHOW TABLES LIKE 'chat_messages'");
if (!$t || $t->num_rows === 0) {
    $conn->query("
        CREATE TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_from_to (from_user_id, to_user_id),
            INDEX idx_to_from (to_user_id, from_user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
$t2 = @$conn->query("SHOW TABLES LIKE 'chat_task_ended'");
if (!$t2 || $t2->num_rows === 0) {
    $conn->query("
        CREATE TABLE chat_task_ended (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            customer_id INT NOT NULL,
            ended_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_admin_customer (admin_id, customer_id),
            INDEX idx_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
$t3 = @$conn->query("SHOW TABLES LIKE 'user_chat_read_since'");
if (!$t3 || $t3->num_rows === 0) {
    $conn->query("
        CREATE TABLE user_chat_read_since (
            user_id INT PRIMARY KEY,
            read_at INT UNSIGNED NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Ensure purchases columns exist (InfinityFree บางครั้ง schema ยังไม่อัปเดต)
$tp = @$conn->query("SHOW TABLES LIKE 'purchases'");
if ($tp && $tp->num_rows > 0) {
    $col = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'assigned_admin_id'");
    if ($col && $col->num_rows === 0) {
        @$conn->query("ALTER TABLE purchases ADD COLUMN assigned_admin_id INT(11) DEFAULT NULL");
    }
    $col2 = @$conn->query("SHOW COLUMNS FROM purchases LIKE 'admin_status'");
    if ($col2 && $col2->num_rows === 0) {
        @$conn->query("ALTER TABLE purchases ADD COLUMN admin_status VARCHAR(20) DEFAULT 'pending'");
    }
}

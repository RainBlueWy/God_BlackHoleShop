<?php
/**
 * เพิ่มคอลัมน์ avatar ในตาราง users (รันครั้งเดียว)
 */
require_once 'config.php';

$sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL COMMENT 'รูปโปรไฟล์ ( path ใน uploads/avatars/ )' AFTER email";

if ($conn->query($sql) === true) {
    echo "Column 'avatar' added to users successfully.";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "Column 'avatar' already exists.";
    } else {
        echo "Error: " . $conn->error;
    }
}

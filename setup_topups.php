<?php
require_once 'config.php';

echo "Setting up Topup Database Tables...<br>";

// 1. Add points column to users if not exists
$checkColumn = $conn->query("SHOW COLUMNS FROM `users` LIKE 'points'");
if ($checkColumn->num_rows == 0) {
    if ($conn->query("ALTER TABLE `users` ADD COLUMN `points` DECIMAL(10,2) DEFAULT 0.00 AFTER `is_active`") === TRUE) {
        echo "- Added 'points' column to `users` table successfully.<br>";
    } else {
        echo "- Error adding 'points' column: " . $conn->error . "<br>";
    }
} else {
    echo "- 'points' column already exists in `users` table.<br>";
}

// 2. Create topups table
$createTableSQL = "
CREATE TABLE IF NOT EXISTS `topups` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `slip_image` VARCHAR(255) NOT NULL COMMENT 'Upload slip filename',
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($createTableSQL) === TRUE) {
    echo "- Created 'topups' table successfully.<br>";
} else {
    echo "- Error creating 'topups' table: " . $conn->error . "<br>";
}

echo "<br>Setup Completed.";
$conn->close();
?>

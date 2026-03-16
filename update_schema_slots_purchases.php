<?php
/**
 * Add max_slots (รับกี่คน) to products and create purchases table for sold-out logic.
 * Run once: open in browser or: php update_schema_slots_purchases.php
 */
require_once 'config.php';

// 1. Add max_slots to products if not exists
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'max_slots'");
if ($check->num_rows === 0) {
    if ($conn->query("ALTER TABLE products ADD COLUMN max_slots INT(11) NOT NULL DEFAULT 0 COMMENT 'รับกี่คน (0 = ไม่จำกัด)' AFTER price")) {
        echo "Added column products.max_slots.<br>";
    } else {
        echo "Error adding max_slots: " . $conn->error . "<br>";
    }
} else {
    echo "Column products.max_slots already exists.<br>";
}

// 2. Create purchases table (who bought which product with points)
$sql = "CREATE TABLE IF NOT EXISTS purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    points_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product (product_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Table purchases created or already exists.<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}
echo "<p><a href='admin_products.php?inapp=1'>กลับไปจัดการสินค้า</a></p>";

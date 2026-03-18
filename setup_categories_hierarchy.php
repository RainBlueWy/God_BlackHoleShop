<?php
/**
 * สร้างตารางหมวดหมู่หลัก + หมวดหมู่ย่อย และเพิ่ม sub_category_id ใน products
 * รันครั้งเดียว: เปิดเบราว์เซอร์ไปที่ setup_categories_hierarchy.php
 */
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>ตั้งค่าหมวดหมู่หลัก / หมวดหมู่ย่อย</h2>";

// 1. ตารางหมวดหมู่หลัก
$conn->query("CREATE TABLE IF NOT EXISTS main_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ ตาราง main_categories พร้อม</p>";

// 2. ตารางหมวดหมู่ย่อย
$conn->query("CREATE TABLE IF NOT EXISTS sub_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    main_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_main (main_id),
    KEY idx_slug (slug),
    UNIQUE KEY uq_main_slug (main_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "<p>✓ ตาราง sub_categories พร้อม</p>";

// 3. เพิ่มคอลัมน์ sub_category_id ใน products (ถ้ายังไม่มี)
$col = $conn->query("SHOW COLUMNS FROM products LIKE 'sub_category_id'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN sub_category_id INT(11) DEFAULT NULL AFTER category");
    echo "<p>✓ เพิ่มคอลัมน์ products.sub_category_id แล้ว</p>";
} else {
    echo "<p>✓ products.sub_category_id มีอยู่แล้ว</p>";
}

// 4. สร้างหมวดหมู่หลัก + ย่อยเริ่มต้น ถ้ายังไม่มี
$check = $conn->query("SELECT id FROM main_categories WHERE slug = 'god-blackhole' LIMIT 1");
if ($check && $check->num_rows === 0) {
    $conn->query("INSERT INTO main_categories (name, slug, sort_order) VALUES ('God_BlackHole', 'god-blackhole', 0)");
    $main_id = (int)$conn->insert_id;
    $conn->query("INSERT INTO sub_categories (main_id, name, slug, sort_order) VALUES ($main_id, 'ทั้งหมด', 'all', 0)");
    $sub_id = (int)$conn->insert_id;
    $conn->query("UPDATE products SET sub_category_id = $sub_id WHERE sub_category_id IS NULL");
    echo "<p>✓ สร้างหมวดหมู่หลัก God_BlackHole และหมวดหมู่ย่อย \"ทั้งหมด\" แล้ว (สินค้าทั้งหมดอยู่ในนี้)</p>";
}

echo "<p><a href='categories.php'>ไปหน้าหมวดหมู่</a> | <a href='admin_panel.php'>แอดมิน</a></p>";

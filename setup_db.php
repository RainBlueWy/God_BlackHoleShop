<?php
require_once 'config.php';

echo "<h2>Setting up Database...</h2>";

// 1. Create Products Table
$sql_create = "CREATE TABLE IF NOT EXISTS products (
    id INT(11) AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสสินค้า',
    slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'รหัสอ้างอิง URL',
    name VARCHAR(100) NOT NULL COMMENT 'ชื่อสินค้า',
    category VARCHAR(100) NOT NULL COMMENT 'หมวดหมู่',
    price VARCHAR(50) NOT NULL COMMENT 'ราคา',
    image VARCHAR(255) NOT NULL COMMENT 'รูปภาพ',
    description TEXT COMMENT 'รายละเอียด',
    features TEXT COMMENT 'ฟีเจอร์ (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_create) === TRUE) {
    echo "Table 'products' created or already exists.<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// 2. Data to Seek
$products = [
    [
        'slug' => 'seliware',
        'name' => 'Seliware 7 วัน',
        'category' => 'สคริปต์ / สคริปต์ 7 วัน',
        'price' => '119 บาท',
        'image' => 'product-seliware.png',
        'description' => 'Seliware เป็นสคริปต์ Executor ที่มีประสิทธิภาพสูง รองรับเกมส่วนใหญ่บน Roblox พร้อมฟีเจอร์ครบครันและอัปเดตอย่างสม่ำเสมอ เหมาะสำหรับผู้ที่ต้องการประสบการณ์การใช้งานที่ราบรื่น',
        'features' => json_encode(['รองรับเกมส่วนใหญ่บน Roblox', 'อัปเดตอย่างสม่ำเสมอ', 'ใช้งานง่าย เหมาะสำหรับมือใหม่', 'ประสิทธิภาพสูง ไม่กระตุก', 'รองรับ Script Hub มากมาย', 'ระบบ Anti-Ban ที่ดีเยี่ยม', 'รองรับ Windows 10/11', 'ใช้งานได้ 7 วันเต็ม'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'wave',
        'name' => 'Wave 7 วัน',
        'category' => 'สคริปต์ / สคริปต์ 7 วัน',
        'price' => '99 บาท',
        'image' => 'product-wave.png',
        'description' => 'Wave Executor เป็นเครื่องมือที่มีประสิทธิภาพสูง พร้อมฟีเจอร์ที่ทันสมัยและใช้งานง่าย เหมาะสำหรับผู้ที่ต้องการ Executor ที่มีเสถียรภาพสูง',
        'features' => json_encode(['UI ที่สวยงามและใช้งานง่าย', 'รองรับ Script หลากหลาย', 'ประสิทธิภาพสูง ทำงานเร็ว', 'อัปเดตบ่อย รองรับเกมใหม่', 'ระบบความปลอดภัยสูง', 'รองรับ Auto Execute', 'มี Script Hub ในตัว', 'ใช้งานได้ 7 วัน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'celery',
        'name' => 'Celery 7 วัน',
        'category' => 'สคริปต์ / สคริปต์ 7 วัน',
        'price' => '89 บาท',
        'image' => 'product-celery.png',
        'description' => 'Celery เป็น Executor ที่มีราคาประหยัดแต่ฟีเจอร์ครบครัน เหมาะสำหรับผู้เริ่มต้นที่ต้องการทดลองใช้งาน Executor คุณภาพดี',
        'features' => json_encode(['ราคาประหยัด คุ้มค่า', 'ฟีเจอร์ครบครัน', 'ใช้งานง่าย เหมาะมือใหม่', 'รองรับเกมยอดนิยม', 'มี Script Library', 'อัปเดตสม่ำเสมอ', 'ระบบ Anti-Kick', 'ใช้งานได้ 7 วัน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'fluxus',
        'name' => 'Fluxus 30 วัน',
        'category' => 'สคริปต์ / สคริปต์ 30 วัน',
        'price' => '299 บาท',
        'image' => 'product-fluxus.png',
        'description' => 'Fluxus เป็น Executor ระดับพรีเมี่ยมที่ใช้งานได้ยาวนาน 30 วัน พร้อมฟีเจอร์ขั้นสูงและการรองรับที่ดีเยี่ยม คุ้มค่าสำหรับผู้ใช้งานระยะยาว',
        'features' => json_encode(['ใช้งานได้ยาวนาน 30 วัน', 'ฟีเจอร์ระดับพรีเมี่ยม', 'รองรับเกมทุกประเภท', 'UI ที่ทันสมัยและสวยงาม', 'มี Built-in Scripts มากมาย', 'ระบบ Auto Update', 'Support ตลอด 24 ชั่วโมง', 'คุ้มค่าที่สุด'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'synapse',
        'name' => 'Synapse X 7 วัน',
        'category' => 'สคริปต์ / สคริปต์ 7 วัน',
        'price' => '149 บาท',
        'image' => 'product-synapse.png',
        'description' => 'Synapse X เป็น Executor ที่มีชื่อเสียงและได้รับความนิยมสูงสุด มีฟีเจอร์ที่ทรงพลังและเสถียรภาพสูง เหมาะสำหรับผู้ที่ต้องการคุณภาพระดับท็อป',
        'features' => json_encode(['Executor ที่ดีที่สุดในตลาด', 'รองรับ Script ทุกประเภท', 'ประสิทธิภาพสูงสุด', 'UI ที่ใช้งานง่าย', 'มี Script Hub ครบครัน', 'อัปเดตทันทีเมื่อเกมอัปเดต', 'ระบบความปลอดภัยระดับสูง', 'ใช้งานได้ 7 วัน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'krnl',
        'name' => 'KRNL 7 วัน',
        'category' => 'สคริปต์ / สคริปต์ 7 วัน',
        'price' => 'ฟรี',
        'image' => 'product-krnl.png',
        'description' => 'KRNL เป็น Executor ฟรีที่มีคุณภาพดี เหมาะสำหรับผู้ที่ต้องการทดลองใช้งาน Executor โดยไม่ต้องเสียค่าใช้จ่าย',
        'features' => json_encode(['ใช้งานฟรี ไม่มีค่าใช้จ่าย', 'ฟีเจอร์พื้นฐานครบครัน', 'รองรับเกมยอดนิยม', 'ใช้งานง่าย', 'มี Script Library', 'อัปเดตสม่ำเสมอ', 'เหมาะสำหรับมือใหม่', 'ใช้งานได้ 7 วัน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'limited',
        'name' => 'ReaperX | Limited Edition',
        'category' => 'หมวดหมู่พิเศษ',
        'price' => '119 บาท',
        'image' => 'product-limited.png',
        'description' => 'ReaperX Limited Edition เป็นแพ็คเกจพิเศษที่รวมฟีเจอร์เด่นและสิทธิพิเศษมากมาย สำหรับสมาชิกที่ต้องการประสบการณ์พิเศษ',
        'features' => json_encode(['แพ็คเกจพิเศษจำกัดจำนวน', 'ฟีเจอร์พิเศษเฉพาะสมาชิก', 'รองรับทุกเกม', 'Priority Support', 'อัปเดตก่อนใคร', 'Script Premium ฟรี', 'สิทธิพิเศษมากมาย', 'คุ้มค่าสุดๆ'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'executor',
        'name' => 'ReaperX | Executor',
        'category' => 'หมวดหมู่พิเศษ',
        'price' => '119 บาท',
        'image' => 'product-executor.png',
        'description' => 'ReaperX Executor เป็น Executor ที่พัฒนาโดยทีมงาน ReaperX มีฟีเจอร์ที่ทรงพลังและใช้งานง่าย พร้อมการอัปเดตอย่างต่อเนื่อง',
        'features' => json_encode(['พัฒนาโดยทีม ReaperX', 'ฟีเจอร์ครบครัน', 'UI สวยงาม ใช้งานง่าย', 'รองรับเกมทุกประเภท', 'มี Script Hub ในตัว', 'อัปเดตบ่อย', 'ระบบความปลอดภัยสูง', 'ใช้งานได้ 7 วัน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'reset',
        'name' => 'ReaperX | Reset HWID',
        'category' => 'บริการพิเศษ',
        'price' => '119 บาท',
        'image' => 'product-reset.png',
        'description' => 'บริการ Reset HWID สำหรับผู้ที่ต้องการเปลี่ยนเครื่องหรือติดตั้งใหม่ สามารถใช้ Key เดิมได้อีกครั้ง',
        'features' => json_encode(['Reset HWID ได้ทันที', 'ใช้ Key เดิมได้อีกครั้ง', 'เปลี่ยนเครื่องได้', 'ติดตั้งใหม่ได้', 'รวดเร็ว ทันใจ', 'ไม่ต้องซื้อ Key ใหม่', 'Support ตลอด 24 ชม.', 'คุ้มค่า ประหยัด'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'website',
        'name' => 'ReaperX | Rent Website',
        'category' => 'บริการพิเศษ',
        'price' => '119 บาท',
        'image' => 'product-website.png',
        'description' => 'บริการเช่าเว็บไซต์สำหรับขายสคริปต์ พร้อมระบบจัดการที่ครบครัน เหมาะสำหรับผู้ที่ต้องการเปิดร้านขายสคริปต์',
        'features' => json_encode(['เว็บไซต์สำเร็จรูป', 'ระบบจัดการครบครัน', 'ดีไซน์สวยงาม', 'รองรับการชำระเงิน', 'ระบบสมาชิก', 'แก้ไขได้ตามต้องการ', 'Support ตลอด 24 ชม.', 'เหมาะสำหรับเปิดร้าน'], JSON_UNESCAPED_UNICODE)
    ],
    [
        'slug' => 'Eat Slimes to Grow HUGE',
        'name' => 'Eat Slimes to Grow HUGE',
        'category' => 'สคริปต์ / สคริปต์ Free วัน',
        'price' => 'Freeตอนนี้',
        'image' => 'Eat Slimes to Grow HUGE.jpg',
        'description' => 'สคริปต์สำหรับเกม Eat Slimes to Grow HUGE ช่วยให้ตัวใหญ่ขึ้นอย่างรวดเร็ว',
        'features' => json_encode(['Auto Farm', 'Auto Eat', 'Safe Mode'], JSON_UNESCAPED_UNICODE)
    ]
];

// 3. Loop Insert
$stmt = $conn->prepare("INSERT IGNORE INTO products (slug, name, category, price, image, description, features) VALUES (?, ?, ?, ?, ?, ?, ?)");

if ($stmt) {
    foreach ($products as $p) {
        $stmt->bind_param("sssssss", $p['slug'], $p['name'], $p['category'], $p['price'], $p['image'], $p['description'], $p['features']);
        if ($stmt->execute()) {
             if ($stmt->affected_rows > 0) {
                 echo "Inserted: " . $p['name'] . "<br>";
             } else {
                 echo "Skipped (Already exists): " . $p['name'] . "<br>";
             }
        } else {
            echo "Error inserting " . $p['name'] . ": " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
} else {
    echo "Prepare failed: " . $conn->error;
}

$conn->close();
echo "<h3>Setup Complete!</h3>";
echo "<a href='index.php'>Go Home</a>";
?>

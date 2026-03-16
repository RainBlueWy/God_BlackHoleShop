<?php
require_once 'config.php';

// Add script_content column
$sql = "ALTER TABLE products ADD COLUMN script_content TEXT COMMENT 'เนื้อหาสคริปต์หรือลิงก์' AFTER image";

if ($conn->query($sql) === TRUE) {
    echo "Column 'script_content' added successfully.";
} else {
    echo "Error adding column: " . $conn->error;
}

// Update existing rows with default scripts based on hardcoded values form checkout.php
$defaults = [
    'seliware' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/seliware/main/script.lua"))()',
    'wave' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/wave/main/script.lua"))()',
    'celery' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/celery/main/script.lua"))()',
    'fluxus' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/fluxus/main/script.lua"))()',
    'synapse' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/synapse/main/script.lua"))()',
    'krnl' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/krnl/main/script.lua"))()',
    'limited' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/limited/main/script.lua"))()',
    'executor' => 'loadstring(game:HttpGet("https://raw.githubusercontent.com/GodBlackHole/executor/main/script.lua"))()',
    'reset' => 'https://discord.gg/nBuwhHte',
    'website' => 'https://discord.gg/nBuwhHte'
];

foreach ($defaults as $slug => $script) {
    if (strpos($script, 'loadstring') !== false) {
        $content = "คลิกปุ่มด้านล่างเพื่อคัดลอกลิงก์ script\n" . $script;
    } else {
        $content = "เข้าร่วม Discord เพื่อรับบริการ\n" . $script;
    }
    
    // Simplification: Just storing the raw link/script for now to match the UI which expects a link text
    // Actually the UI splits it. Let's just store the main content text.
    $stmt = $conn->prepare("UPDATE products SET script_content = ? WHERE slug = ?");
    $stmt->bind_param("ss", $script, $slug);
    $stmt->execute();
}
echo "<br>Data updated.";
?>

<?php
require_once 'config.php';

// Add updated_at column
$sql = "ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'เวลาอัปเดตล่าสุด' AFTER created_at";

if ($conn->query($sql) === TRUE) {
    echo "Column 'updated_at' added successfully.<br>";
} else {
    // If it fails, maybe it exists, let's try to update existing rows to be sure
    echo "Error adding column (might already exist): " . $conn->error . "<br>";
}

// Update existing rows to have updated_at = created_at if it's NULL (though default current_timestamp handles new ones)
// and we want to ensure sorting works for old items too.
// Actually, let's just make sure everything has a value.
$sql = "UPDATE products SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'";
$conn->query($sql);

echo "Schema updated.";
?>

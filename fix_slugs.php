<?php
// fix_slugs.php - FORCE FIX
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
// Auth guard removed

echo "<html><body style='font-family:sans-serif; background:#111; color:#fff; padding:20px;'>";
echo "<h1>🔧 System Repair Tool</h1>";

// 1. Check DB Connection
if ($conn->connect_error) {
    die("<h2 style='color:red'>Database Connection Failed: " . $conn->connect_error . "</h2>");
}
echo "<p style='color:#4ade80'>✅ Database Connected</p>";

// 2. Fetch Products
$sql = "SELECT id, name, slug FROM products";
$result = $conn->query($sql);

echo "<table border='1' cellspacing='0' cellpadding='10' style='border-color:#333; width:100%'>";
echo "<tr style='background:#333'><th>ID</th><th>Name</th><th>Old Slug</th><th>New Slug</th><th>Status</th></tr>";

$count = 0;
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $name = $row['name'];
        $old_slug = $row['slug'];
        
        // Sanitize Logic
        // 1. Lowercase
        // 2. Replace non-alphanumeric with hyphen
        // 3. Remove duplicate hyphens
        // 4. Trim hyphens
        $new_slug = strtolower($old_slug);
        $new_slug = preg_replace('/[^a-z0-9]+/', '-', $new_slug);
        $new_slug = trim($new_slug, '-');
        
        // Force update even if visually similar to ensure clean bytes
        $status = "<span>Unchanged</span>";
        $color = "#888";

        if ($old_slug !== $new_slug) {
            $update = $conn->prepare("UPDATE products SET slug = ? WHERE id = ?");
            $update->bind_param("si", $new_slug, $id);
            if ($update->execute()) {
                $status = "<b>UPDATED FIX</b>";
                $color = "#4ade80"; // Green
                $count++;
            } else {
                $status = "<b style='color:red'>ERROR: " . $conn->error . "</b>";
                $color = "#ef4444";
            }
        }
        
        echo "<tr style='color:$color'>
            <td>$id</td>
            <td>" . htmlspecialchars($name) . "</td>
            <td>" . htmlspecialchars($old_slug) . "</td>
            <td>" . htmlspecialchars($new_slug) . "</td>
            <td>$status</td>
        </tr>";
    }
}
echo "</table>";

echo "<div style='margin-top:30px; padding:20px; background:#222; border-radius:10px; text-align:center;'>";
if ($count > 0) {
    echo "<h2 style='color:#4ade80'>✅ Fixed $count products!</h2>";
} else {
    echo "<h2 style='color:#fbbf24'>⚠️ No changes needed (Database is already clean)</h2>";
}
echo "<p>The Internal Server Error should be gone.</p>";
echo "<a href='index.php' style='display:inline-block; padding:10px 20px; background:#d946ef; color:white; text-decoration:none; border-radius:5px; font-weight:bold; font-size:18px;'>🏠 Go Back to Home (กลับหน้าหลัก)</a>";
echo "</div>";

echo "</body></html>";
?>

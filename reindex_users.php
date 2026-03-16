<?php
require_once 'config.php';

// 1. Get all users ordered by current ID
$result = $conn->query("SELECT id FROM users ORDER BY id ASC");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row['id'];
}

if (empty($users)) {
    die("No users found to re-index.");
}

// 2. Disable Foreign Key Checks to allow updating IDs
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$new_id = 1;
$updates_count = 0;

foreach ($users as $old_id) {
    if ($old_id != $new_id) {
        // Update user ID
        $stmt = $conn->prepare("UPDATE users SET id = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_id, $old_id);
        $stmt->execute();
        
        // Update user_sessions if they exist
        $stmt_session = $conn->prepare("UPDATE user_sessions SET user_id = ? WHERE user_id = ?");
        $stmt_session->bind_param("ii", $new_id, $old_id);
        $stmt_session->execute();
        
        $updates_count++;
    }
    $new_id++;
}

// 3. Reset Auto Increment to the next number
$conn->query("ALTER TABLE users AUTO_INCREMENT = $new_id");

// 4. Re-enable Foreign Key Checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<h1>✅ Re-index Complete!</h1>";
echo "<p>Updated $updates_count user IDs.</p>";
echo "<p>Next available ID is: $new_id</p>";
echo "<a href='admin_panel.php'>Go back to Admin Panel</a>";
?>

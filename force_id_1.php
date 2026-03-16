<?php
session_start();
require_once 'config.php';

// 1. Get current user's current ID
if (!isset($_SESSION['user_id'])) {
    die("Please login first!");
}

$old_id = $_SESSION['user_id'];
$new_id = 1;

if ($old_id == $new_id) {
    die("Your ID is already 1!");
}

// 2. Check if ID 1 is already taken
$check = $conn->query("SELECT id FROM users WHERE id = 1");
if ($check->num_rows > 0) {
    // If ID 1 is taken by someone else, we might need to swap or delete them.
    // For simplicity, we assume the user wants to be #1 and current #1 is trash or doesn't exist.
    die("Error: User ID 1 is already taken by another account. Please delete that account first.");
}

// 3. Update the ID
$sql = "UPDATE users SET id = $new_id WHERE id = $old_id";
if ($conn->query($sql)) {
    // Update session too
    $_SESSION['user_id'] = $new_id;
    
    // 4. Reset Auto Increment to 2
    $conn->query("ALTER TABLE users AUTO_INCREMENT = 2");
    
    echo "<h1>✅ Success!</h1>";
    echo "<p>Your ID has been changed from #$old_id to #$new_id.</p>";
    echo "<p>Next user will be #2.</p>";
    echo "<a href='profile.php'>Go to Profile</a>";
} else {
    echo "Error updating record: " . $conn->error;
}
?>

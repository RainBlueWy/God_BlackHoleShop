<?php
require_once 'config.php';

// Force reset the standard auto_increment
$conn->query("ALTER TABLE users AUTO_INCREMENT = 1");

echo "<h1>✅ ID System Reset!</h1>";
echo "<p>Next user will get the lowest available ID number.</p>";
echo "<a href='index.php'>Go back</a>";
?>

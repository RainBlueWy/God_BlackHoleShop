<?php
session_start();
require_once 'config.php';

// Logout
session_destroy();
header('Location: index.php');
exit();
?>

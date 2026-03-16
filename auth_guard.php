<?php
// auth_guard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ถ้ายังไม่ login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

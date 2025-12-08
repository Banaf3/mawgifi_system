<?php
session_start();

// Store user type before destroying session for redirect
$user_type = $_SESSION['user_type'] ?? null;

// Destroy all session data
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page with logout message
header("Location: login.php?logout=success");
exit();
?>

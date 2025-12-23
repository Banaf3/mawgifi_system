<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout check (10 minutes)
$timeout_duration = 600;

if (isset($_SESSION['login_time'])) {
    $elapsed_time = time() - $_SESSION['login_time'];
    
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: /mawgifi_system/login.php?timeout=1");
        exit();
    }
}

// Update last activity time
$_SESSION['login_time'] = time();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function isAdmin() {
    return getCurrentUserType() === 'admin';
}

function isStaff() {
    return getCurrentUserType() === 'staff';
}

function isStudent() {
    return getCurrentUserType() === 'student';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /mawgifi_system/login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Redirect if not staff
function requireStaff() {
    requireLogin();
    if (!isStaff() && !isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}
?>

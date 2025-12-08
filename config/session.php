<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        header("Location: login.php");
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

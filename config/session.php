<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user type
function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Get current username
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// Check if user is admin
function isAdmin() {
    return getCurrentUserType() === 'admin';
}

// Check if user is staff
function isStaff() {
    return getCurrentUserType() === 'staff';
}

// Check if user is student
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

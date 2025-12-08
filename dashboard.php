<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Redirect to appropriate dashboard based on user type
if (isLoggedIn()) {
    switch (getCurrentUserType()) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'staff':
            header("Location: staff/dashboard.php");
            exit();
        case 'student':
            header("Location: student/dashboard.php");
            exit();
    }
}

// If not logged in, redirect to login
header("Location: login.php");
exit();
?>

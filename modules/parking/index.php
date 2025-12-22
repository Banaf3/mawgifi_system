<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

// Ensure user is logged in
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';
$dashboard_link = ($user_type === 'admin') ? '../../admin/dashboard.php' :
    (($user_type === 'staff') ? '../../staff/dashboard.php' : '../../student/dashboard.php');

// Role-based button names
$is_student = ($user_type === 'user');
$nav_vehicles = $is_student ? 'My Vehicles' : 'Vehicles';
$nav_parking = $is_student ? 'Find Parking' : 'Parking Areas';
$nav_bookings = $is_student ? 'My Bookings' : 'Bookings';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Areas - Mawgifi</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }

        .navbar {
            background: var(--primary-grad);
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar .brand {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: white;
            color: #6a67ce;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .logout-btn {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #e53e3e;
            border-color: #e53e3e;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .module-header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .module-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        .module-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .content-area {
            margin-top: 30px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            min-height: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="<?php echo $dashboard_link; ?>">Dashboard</a>
            <a href="../membership/index.php"><?php echo $nav_vehicles; ?></a>
            <a href="../parking/index.php" class="active"><?php echo $nav_parking; ?></a>
            <a href="../booking/index.php"><?php echo $nav_bookings; ?></a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="module-header">
            <h1><?php echo $is_student ? 'Find Parking' : 'Parking Areas & Spaces'; ?></h1>
            <p>Manage Parking Area & Spaces</p>
        </div>

        <div class="content-area">
            <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
            <p style="margin-top: 15px; color: #718096;">
                This module allows for the management of parking lots, individual spaces, and availability.
                New setup ready for development.
            </p>
        </div>
    </div>
</body>

</html>
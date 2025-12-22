<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

$username = $_SESSION['username'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mawgifi</title>
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
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-light);
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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .dashboard-welcome {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin-bottom: 40px;
        }

        .dashboard-welcome h2 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .dashboard-welcome p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .module-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            border-top: 5px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .module-card.m1 {
            border-color: #667eea;
        }

        .module-card.m2 {
            border-color: #764ba2;
        }

        .module-card.m3 {
            border-color: #6b46c1;
        }

        .module-card h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        .module-card p {
            color: var(--text-light);
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="#" class="active">Dashboard</a>
            <a href="../modules/membership/index.php">Vehicles</a>
            <a href="../modules/parking/index.php">Parking Areas</a>
            <a href="../modules/booking/index.php">Bookings</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-welcome">
            <h2>Welcome Back, <?php echo htmlspecialchars($username); ?>!</h2>
            <p>Select a module below to start managing the system.</p>
        </div>

        <div class="modules-grid">
            <a href="../modules/membership/index.php" class="module-card m1">
                <h3>Vehicles</h3>
                <p>Manage user memberships, profiles, and vehicle registrations.</p>
            </a>

            <a href="../modules/parking/index.php" class="module-card m2">
                <h3>Parking Areas</h3>
                <p>Manage parking areas, spaces, and monitor availability status.</p>
            </a>

            <a href="../modules/booking/index.php" class="module-card m3">
                <h3>Bookings</h3>
                <p>Oversee parking bookings and manage QR code access systems.</p>
            </a>
        </div>
    </div>
</body>

</html>
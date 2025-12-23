<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

requireAdmin();

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles Management - Mawgifi</title>
    <style>
        :root {
            --grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: var(--grad);
            color: #fff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: #fff;
            color: #6a67ce;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
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
            color: #fff;
            padding: 8px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: #e53e3e;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>
        <div class="nav-links">
            <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
            <a href="index.php" class="active">Vehicles</a>
            <a href="../parking/index.php">Parking Map</a>
            <a href="../../admin/parking_management.php">Manage Parking</a>
            <a href="../booking/index.php">Bookings</a>
            <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
            <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
            <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
        </div>
        <div class="user-profile">
            <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <span><?= htmlspecialchars($username) ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Vehicles Management</h1>
        </div>
        <div class="card">
            <h3>🚧 Under Construction</h3>
            <p>The membership/vehicles module is being set up.</p>
        </div>
    </div>
</body>

</html>
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

        .module-card.m4 {
            border-color: #48bb78;
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
            <a href="../Moudel1/Admin.php?view=dashboard" class="active">Dashboard</a>
            <a href="../modules/membership/index.php">Vehicles</a>
            <a href="../modules/parking/index.php">Parking Map</a>
            <a href="parking_management.php">Manage Parking</a>
            <a href="event_management.php">Events</a>
            <a href="../modules/booking/index.php">Bookings</a>
            <a href="../Moudel1/Admin.php?view=register">Register Student</a>
            <a href="../Moudel1/Admin.php?view=manage">Manage Profile</a>
            <a href="../Moudel1/Admin.php?view=profile">Profile</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm rounded-pill">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Bootstrap Card for Welcome Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center p-5">
                <h2 class="card-title fw-bold text-dark">Welcome Back, <?php echo htmlspecialchars($username); ?>!</h2>
                <p class="card-text text-muted fs-5">Select a module below to start managing the system.</p>
            </div>
        </div>

        <!-- Bootstrap Row/Col Grid for Modules -->
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <a href="../modules/parking/index.php" class="card module-card m2 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-p-circle-fill text-primary me-2"></i>Parking Map</h3>
                        <p class="card-text text-muted">View parking areas map and monitor real-time slot availability.</p>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="parking_management.php" class="card module-card m4 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-car-front-fill text-success me-2"></i>Manage Parking</h3>
                        <p class="card-text text-muted">Add, edit, and delete parking areas and individual parking spaces.</p>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="event_management.php" class="card module-card m1 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-calendar-event-fill text-info me-2"></i>Event Management</h3>
                        <p class="card-text text-muted">Track facility events like maintenance, cleaning, and lawn mowing.</p>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="../modules/booking/index.php" class="card module-card m3 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-journal-check text-warning me-2"></i>Bookings</h3>
                        <p class="card-text text-muted">Oversee parking bookings and manage QR code access systems.</p>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="../modules/membership/index.php" class="card module-card m1 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-truck-front-fill text-danger me-2"></i>Vehicles</h3>
                        <p class="card-text text-muted">Manage user memberships, profiles, and vehicle registrations.</p>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-4">
                <a href="../Moudel1/Admin.php?view=profile" class="card module-card m2 h-100 text-decoration-none shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="card-title"><i class="bi bi-people-fill text-secondary me-2"></i>User Management</h3>
                        <p class="card-text text-muted">Register students, manage profiles, and handle user accounts.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
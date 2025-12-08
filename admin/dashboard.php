<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

$conn = getDBConnection();

$total_students = $conn->query("SELECT COUNT(*) as count FROM User")->fetch_assoc()['count'];
$total_vehicles = $conn->query("SELECT COUNT(*) as count FROM Vehicle")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking")->fetch_assoc()['count'];
$active_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking WHERE booking_end >= NOW()")->fetch_assoc()['count'];

$recent_bookings = $conn->query("
    SELECT b.*, u.UserName, v.license_plate, ps.space_number 
    FROM Booking b
    JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
    JOIN User u ON v.user_id = u.user_id
    LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
    ORDER BY b.created_at DESC
    LIMIT 5
");

closeDBConnection($conn);
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
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info span {
            background: rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .logout-btn {
            background: white;
            color: #764ba2;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        
        .logout-btn:hover {
            opacity: 0.9;
        }
        
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-top: 5px solid #667eea;
        }
        
        .stat-card h3 {
            color: var(--text-light);
            font-size: 13px;
            margin-bottom: 12px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .section h2 {
            margin-bottom: 25px;
            color: var(--text-dark);
            font-size: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: var(--bg-light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 13px;
            text-transform: uppercase;
        }
        
        table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: var(--text-dark);
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            .user-info span:first-child {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Mawgifi - Admin Dashboard</h1>
        <div class="user-info">
            <span>Administrator</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Vehicles</h3>
                <div class="number"><?php echo $total_vehicles; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="number"><?php echo $total_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Bookings</h3>
                <div class="number"><?php echo $active_bookings; ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2>Recent Bookings</h2>
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Student</th>
                        <th>Vehicle</th>
                        <th>Space</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $booking['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars($booking['UserName']); ?></td>
                        <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($booking['booking_start'])); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($booking['booking_end'])); ?></td>
                        <td>
                            <?php 
                            $now = time();
                            $end = strtotime($booking['booking_end']);
                            if ($end > $now) {
                                echo '<span class="badge badge-success">Active</span>';
                            } else {
                                echo '<span class="badge badge-danger">Expired</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

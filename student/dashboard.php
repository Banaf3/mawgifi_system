<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require user login
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

$vehicles = $conn->query("SELECT * FROM Vehicle WHERE user_id = $user_id");

$bookings = $conn->query("
    SELECT b.*, v.license_plate, v.vehicle_type, ps.space_number, pa.area_name, e.event_name
    FROM Booking b
    JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
    LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
    LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
    LEFT JOIN Event e ON b.event_id = e.event_id
    WHERE v.user_id = $user_id
    ORDER BY b.created_at DESC
");

// Get statistics
$total_vehicles = $conn->query("SELECT COUNT(*) as count FROM Vehicle WHERE user_id = $user_id")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking b JOIN Vehicle v ON b.vehicle_id = v.vehicle_id WHERE v.user_id = $user_id")->fetch_assoc()['count'];
$active_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking b JOIN Vehicle v ON b.vehicle_id = v.vehicle_id WHERE v.user_id = $user_id AND b.booking_end >= NOW()")->fetch_assoc()['count'];

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Mawgifi</title>
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
            margin-bottom: 30px;
        }
        
        .section h2 {
            margin-bottom: 25px;
            color: var(--text-dark);
            font-size: 1.5rem;
        }
        
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .vehicle-card {
            background: var(--primary-grad);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .vehicle-card h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .vehicle-card p {
            margin: 8px 0;
            font-size: 14px;
            opacity: 0.95;
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
            .user-info span:last-of-type {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Mawgifi - Student Dashboard</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>My Vehicles</h3>
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
            <h2>My Vehicles</h2>
            <div class="vehicle-grid">
                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                <div class="vehicle-card">
                    <h3>🚙 <?php echo htmlspecialchars($vehicle['vehicle_model']); ?></h3>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                    <p><strong>Plate:</strong> <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                    <p><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="section">
            <h2>My Bookings</h2>
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Vehicle</th>
                        <th>Space</th>
                        <th>Area</th>
                        <th>Event</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $booking['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($booking['area_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($booking['event_name'] ?? 'Regular'); ?></td>
                        <td><?php echo date('M d, H:i', strtotime($booking['booking_start'])); ?></td>
                        <td><?php echo date('M d, H:i', strtotime($booking['booking_end'])); ?></td>
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

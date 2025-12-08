<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require user login
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's vehicles
$vehicles = $conn->query("SELECT * FROM Vehicle WHERE user_id = $user_id");

// Get user's bookings
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar .user-info span {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .navbar .logout-btn {
            background: rgba(255,255,255,0.3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .navbar .logout-btn:hover {
            background: rgba(255,255,255,0.4);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #4facfe;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .vehicle-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .vehicle-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .vehicle-card p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🅿️ Mawgifi - Student</h1>
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <span>📧 <?php echo htmlspecialchars($_SESSION['email']); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
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
            <h2>🚗 My Vehicles</h2>
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
            <h2>📅 My Bookings</h2>
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

<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require staff access
requireStaff();

$conn = getDBConnection();

// Get statistics
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking WHERE DATE(booking_start) = CURDATE()")->fetch_assoc()['count'];
$active_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking WHERE booking_end >= NOW()")->fetch_assoc()['count'];
$available_spaces = $conn->query("SELECT COUNT(*) as count FROM ParkingSpace ps JOIN Availability a ON ps.Availability_id = a.Availability_id WHERE a.status = 'available'")->fetch_assoc()['count'];

// Get today's bookings
$todays_bookings = $conn->query("
    SELECT b.*, u.UserName, u.PhoneNumber, v.license_plate, v.vehicle_type, ps.space_number 
    FROM Booking b
    JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
    JOIN Student u ON v.user_id = u.user_id
    LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
    WHERE DATE(b.booking_start) = CURDATE()
    ORDER BY b.booking_start ASC
");

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Mawgifi</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            color: #f5576c;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🅿️ Mawgifi - Staff</h1>
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars(getCurrentUsername()); ?></span>
            <span>👔 Staff Member</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Bookings</h3>
                <div class="number"><?php echo $today_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Bookings</h3>
                <div class="number"><?php echo $active_bookings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Available Spaces</h3>
                <div class="number"><?php echo $available_spaces; ?></div>
            </div>
        </div>
        
        <div class="section">
            <h2>Today's Bookings</h2>
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Student</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Space</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $todays_bookings->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $booking['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars($booking['UserName']); ?></td>
                        <td><?php echo htmlspecialchars($booking['PhoneNumber']); ?></td>
                        <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($booking['vehicle_type']); ?></td>
                        <td><?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?></td>
                        <td><?php echo date('H:i', strtotime($booking['booking_start'])) . ' - ' . date('H:i', strtotime($booking['booking_end'])); ?></td>
                        <td><span class="badge badge-success">Confirmed</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

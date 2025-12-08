<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require staff access
requireStaff();

$conn = getDBConnection();

$today_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking WHERE DATE(booking_start) = CURDATE()")->fetch_assoc()['count'];
$active_bookings = $conn->query("SELECT COUNT(*) as count FROM Booking WHERE booking_end >= NOW()")->fetch_assoc()['count'];
$available_spaces = $conn->query("SELECT COUNT(*) as count FROM ParkingSpace ps JOIN Availability a ON ps.Availability_id = a.Availability_id WHERE a.status = 'available'")->fetch_assoc()['count'];

$todays_bookings = $conn->query("
    SELECT b.*, u.UserName, u.PhoneNumber, v.license_plate, v.vehicle_type, ps.space_number 
    FROM Booking b
    JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
    JOIN User u ON v.user_id = u.user_id
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
            background: #d4edda;
            color: #155724;
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
        <h1>Mawgifi - Staff Dashboard</h1>
        <div class="user-info">
            <span>Staff Member</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
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

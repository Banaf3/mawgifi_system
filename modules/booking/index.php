<?php

/**
 * My Bookings Page - Module 2
 * This page displays all booking reservations for the logged-in user
 * Users can view their active, upcoming, and past bookings with QR codes
 */

// Include session and database configuration files
require_once '../../config/session.php';
require_once '../../config/database.php';

// Make sure user is logged in before accessing this page
requireLogin();

// Get user information from session with default values
$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

// Set dashboard link based on user type (admin, staff, or student)
$dashboard_link = ($user_type === 'admin') ? '../../admin/dashboard.php' : (($user_type === 'staff') ? '../../staff/dashboard.php' : '../../Moudel1/Student.php');

// Check if user is a student to customize navigation labels
$is_student = ($user_type === 'user');
$nav_vehicles = $is_student ? 'My Vehicles' : 'Vehicles';
$nav_parking = $is_student ? 'Find Parking' : 'Parking Areas';
$nav_bookings = $is_student ? 'My Bookings' : 'Bookings';

// Connect to database and fetch user's bookings
$conn = getDBConnection();
$bookings = [];
$stats = ['total' => 0, 'active' => 0, 'upcoming' => 0, 'completed' => 0];

if ($conn) {
    // Get all bookings for the current user with vehicle and space details
    $sql = "SELECT b.booking_id, b.booking_start, b.booking_end, b.booking_qr_code,
                   v.vehicle_model, v.license_plate,
                   ps.space_number,
                   pa.area_name
            FROM Booking b
            JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
            LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
            LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
            WHERE v.user_id = ?
            ORDER BY b.booking_start DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Current time for comparing booking status
    $now = new DateTime();

    while ($row = $result->fetch_assoc()) {
        // Determine the status of each booking
        $start = new DateTime($row['booking_start']);
        $end = new DateTime($row['booking_end']);

        if ($now >= $start && $now <= $end) {
            $row['status'] = 'active';
            $stats['active']++;
        } elseif ($now < $start) {
            $row['status'] = 'upcoming';
            $stats['upcoming']++;
        } else {
            $row['status'] = 'completed';
            $stats['completed']++;
        }

        $bookings[] = $row;
        $stats['total']++;
    }

    $stmt->close();
    $conn->close();
}

// Build the base URL for QR code verification
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $scheme . '://' . $host . '/mawgifi_system/modules/booking/verify.php?code=';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Mawgifi</title>

    <!-- QRCode.js library from CDN - Used to generate QR codes locally in the browser -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* CSS Variables for consistent theming across the page */
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
        }

        /* Reset default browser styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Main body styling */
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }

        /* Navigation bar styling with gradient background */
        .navbar {
            background: var(--primary-grad);
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        /* Brand logo text styling */
        .navbar .brand {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        /* Navigation links container */
        .nav-links {
            display: flex;
            gap: 15px;
        }

        /* Individual navigation link styling */
        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        /* Navigation link hover effect */
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        /* Active navigation link styling */
        .nav-links a.active {
            background: white;
            color: #6a67ce;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* User profile section in navbar */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Username display */
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Avatar circle with user initial */
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

        /* Logout button styling */
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

        /* Logout button hover effect */
        .logout-btn:hover {
            background: #e53e3e;
            border-color: #e53e3e;
        }

        /* Main container for page content */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Page header section styling */
        .module-header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        /* Header title styling */
        .module-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        /* Header description text */
        .module-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Statistics cards container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        /* Individual stat card styling */
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        /* Stat number styling */
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
        }

        /* Stat label styling */
        .stat-card .label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* Active stat highlight */
        .stat-card.active .number {
            color: #48bb78;
        }

        /* Upcoming stat highlight */
        .stat-card.upcoming .number {
            color: #ed8936;
        }

        /* Completed stat highlight */
        .stat-card.completed .number {
            color: #718096;
        }

        /* Bookings list container */
        .bookings-container {
            margin-top: 30px;
        }

        /* Section title styling */
        .section-title {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Booking card styling */
        .booking-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s;
        }

        /* Booking card hover effect */
        .booking-card:hover {
            transform: translateY(-2px);
        }

        /* Booking information section */
        .booking-info {
            flex: 1;
        }

        /* Booking slot number styling */
        .booking-slot {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Booking details styling */
        .booking-details {
            color: var(--text-light);
            margin-top: 5px;
        }

        /* Booking time styling */
        .booking-time {
            margin-top: 8px;
            font-size: 0.9rem;
        }

        /* Status badge styling */
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Active status badge */
        .status-badge.active {
            background: #c6f6d5;
            color: #276749;
        }

        /* Upcoming status badge */
        .status-badge.upcoming {
            background: #feebc8;
            color: #c05621;
        }

        /* Completed status badge */
        .status-badge.completed {
            background: #e2e8f0;
            color: #718096;
        }

        /* QR button styling */
        .qr-btn {
            background: var(--primary-grad);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 15px;
            transition: transform 0.2s;
        }

        /* QR button hover effect */
        .qr-btn:hover {
            transform: scale(1.05);
        }

        /* Empty state styling */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        /* Empty state icon */
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }

        /* Find parking button */
        .btn-find-parking {
            display: inline-block;
            margin-top: 20px;
            background: var(--primary-grad);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }

        /* Find parking button hover */
        .btn-find-parking:hover {
            transform: translateY(-2px);
        }

        /* QR code popup styling */
        .qr-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        /* QR code card container */
        .qr-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        /* QR code container */
        .qr-container {
            margin: 20px auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            display: inline-block;
        }

        /* Booking details in popup */
        .popup-details {
            text-align: left;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        /* Detail item styling */
        .popup-details p {
            margin: 8px 0;
            color: var(--text-dark);
        }

        /* Detail label */
        .popup-details strong {
            color: var(--text-light);
        }

        /* Print button styling */
        .btn-print {
            background: #48bb78;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
        }

        /* Close button styling */
        .btn-close {
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
        }

        /* Action buttons container */
        .booking-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Edit button styling */
        .edit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: transform 0.2s, opacity 0.2s;
        }

        .edit-btn:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }

        /* Delete button styling */
        .delete-btn {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: transform 0.2s, opacity 0.2s;
        }

        .delete-btn:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }

        /* Edit modal styling */
        .edit-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .edit-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 90%;
        }

        .edit-card h2 {
            margin-bottom: 20px;
            color: var(--text-dark);
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .booking-info-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .booking-info-display p {
            margin: 5px 0;
            color: var(--text-dark);
        }

        .booking-info-display strong {
            color: var(--text-light);
        }

        .btn-save {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
            transition: transform 0.2s;
        }

        .btn-save:hover {
            transform: scale(1.02);
        }

        .btn-cancel {
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
        }

        .edit-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        /* Confirm delete modal */
        .confirm-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }

        .confirm-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .confirm-card h2 {
            color: #e53e3e;
            margin-bottom: 15px;
        }

        .confirm-card p {
            color: var(--text-light);
            margin-bottom: 25px;
        }

        .btn-confirm-delete {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
        }

        /* Print styles - only show QR card when printing */
        @media print {
            body * {
                visibility: hidden;
            }

            .qr-card,
            .qr-card * {
                visibility: visible;
            }

            .qr-card {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
            }

            .btn-print,
            .btn-close {
                display: none !important;
            }
        }

        /* Responsive design for smaller screens */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .qr-btn {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <!-- Navigation Links -->
        <div class="nav-links">
            <a href="<?php echo $dashboard_link; ?>">Dashboard</a>
            <a href="../../Moudel1/Student.php"><?php echo $nav_vehicles; ?></a>
            <a href="../parking/index.php"><?php echo $nav_parking; ?></a>
            <a href="../booking/index.php" class="active"><?php echo $nav_bookings; ?></a>
        </div>

        <!-- User Profile Section -->
        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container">
        <!-- Page Header -->
        <div class="module-header">
            <h1><?php echo $is_student ? 'My Bookings' : 'Booking Management'; ?></h1>
            <p>View and manage your parking reservations</p>
        </div>

        <?php if ($stats['total'] > 0): ?>
            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="number"><?php echo $stats['total']; ?></div>
                    <div class="label">Total Bookings</div>
                </div>
                <div class="stat-card active">
                    <div class="number"><?php echo $stats['active']; ?></div>
                    <div class="label">Active Now</div>
                </div>
                <div class="stat-card upcoming">
                    <div class="number"><?php echo $stats['upcoming']; ?></div>
                    <div class="label">Upcoming</div>
                </div>
                <div class="stat-card completed">
                    <div class="number"><?php echo $stats['completed']; ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>

            <!-- Bookings List -->
            <div class="bookings-container">
                <?php
                // Group bookings by status
                $grouped_bookings = ['active' => [], 'upcoming' => [], 'completed' => []];
                foreach ($bookings as $booking) {
                    $grouped_bookings[$booking['status']][] = $booking;
                }
                ?>

                <?php if (count($grouped_bookings['active']) > 0): ?>
                    <h2 class="section-title">🟢 Active Bookings</h2>
                    <?php foreach ($grouped_bookings['active'] as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-info">
                                <div class="booking-slot">
                                    <?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="booking-details">
                                    <?php echo htmlspecialchars($booking['area_name'] ?? 'Unknown Area'); ?> •
                                    <?php echo htmlspecialchars($booking['vehicle_model'] . ' - ' . $booking['license_plate']); ?>
                                </div>
                                <div class="booking-time">
                                    📅 <?php echo date('F j, Y', strtotime($booking['booking_start'])); ?> •
                                    🕐 <?php echo date('g:i A', strtotime($booking['booking_start'])); ?> -
                                    <?php echo date('g:i A', strtotime($booking['booking_end'])); ?>
                                </div>
                            </div>
                            <span class="status-badge active">Active</span>
                            <div class="booking-actions">
                                <button class="qr-btn" onclick="showQRCode(<?php echo htmlspecialchars(json_encode($booking)); ?>, '<?php echo $base_url; ?>')">
                                    📱 View QR
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (count($grouped_bookings['upcoming']) > 0): ?>
                    <h2 class="section-title" style="margin-top: 30px;">🟠 Upcoming Bookings</h2>
                    <?php foreach ($grouped_bookings['upcoming'] as $booking): ?>
                        <div class="booking-card" id="booking-card-<?php echo $booking['booking_id']; ?>">
                            <div class="booking-info">
                                <div class="booking-slot">
                                    <?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="booking-details">
                                    <?php echo htmlspecialchars($booking['area_name'] ?? 'Unknown Area'); ?> •
                                    <?php echo htmlspecialchars($booking['vehicle_model'] . ' - ' . $booking['license_plate']); ?>
                                </div>
                                <div class="booking-time">
                                    📅 <?php echo date('F j, Y', strtotime($booking['booking_start'])); ?> •
                                    🕐 <?php echo date('g:i A', strtotime($booking['booking_start'])); ?> -
                                    <?php echo date('g:i A', strtotime($booking['booking_end'])); ?>
                                </div>
                            </div>
                            <span class="status-badge upcoming">Upcoming</span>
                            <div class="booking-actions">
                                <button class="qr-btn" onclick="showQRCode(<?php echo htmlspecialchars(json_encode($booking)); ?>, '<?php echo $base_url; ?>')">
                                    📱 QR
                                </button>
                                <button class="edit-btn" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    ✏️ Edit
                                </button>
                                <button class="delete-btn" onclick="showDeleteConfirm(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (count($grouped_bookings['completed']) > 0): ?>
                    <h2 class="section-title" style="margin-top: 30px;">⚫ Past Bookings</h2>
                    <?php foreach ($grouped_bookings['completed'] as $booking): ?>
                        <div class="booking-card" id="booking-card-<?php echo $booking['booking_id']; ?>">
                            <div class="booking-info">
                                <div class="booking-slot">
                                    <?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>
                                </div>
                                <div class="booking-details">
                                    <?php echo htmlspecialchars($booking['area_name'] ?? 'Unknown Area'); ?> •
                                    <?php echo htmlspecialchars($booking['vehicle_model'] . ' - ' . $booking['license_plate']); ?>
                                </div>
                                <div class="booking-time">
                                    📅 <?php echo date('F j, Y', strtotime($booking['booking_start'])); ?> •
                                    🕐 <?php echo date('g:i A', strtotime($booking['booking_start'])); ?> -
                                    <?php echo date('g:i A', strtotime($booking['booking_end'])); ?>
                                </div>
                            </div>
                            <span class="status-badge completed">Completed</span>
                            <div class="booking-actions">
                                <button class="delete-btn" onclick="showDeleteConfirm(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Empty State when no bookings exist -->
            <div class="module-header" style="margin-top: 30px;">
                <div class="empty-state">
                    <span class="empty-icon">📅</span>
                    <h2>No Bookings Yet</h2>
                    <p style="color: var(--text-light); margin-top: 10px;">
                        You haven't made any parking reservations yet.
                    </p>
                    <a href="../parking/index.php" class="btn-find-parking">Find Parking</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Popup -->
    <div class="qr-popup" id="qrPopup">
        <div class="qr-card">
            <h2>Booking QR Code</h2>

            <!-- QR Code Container - Will be generated by JavaScript -->
            <div class="qr-container" id="qrCodeContainer"></div>

            <!-- Booking Details -->
            <div class="popup-details">
                <p><strong>Slot:</strong> <span id="popupSlot">--</span></p>
                <p><strong>Area:</strong> <span id="popupArea">--</span></p>
                <p><strong>Vehicle:</strong> <span id="popupVehicle">--</span></p>
                <p><strong>Date:</strong> <span id="popupDate">--</span></p>
                <p><strong>Time:</strong> <span id="popupTime">--</span></p>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-light); margin-bottom: 15px;">
                📱 Scan this QR code to verify your booking
            </p>

            <!-- Action Buttons -->
            <button class="btn-print" onclick="window.print()">🖨️ Print QR Code</button>
            <button class="btn-close" onclick="closeQrPopup()">Close</button>
        </div>
    </div>

    <!-- Edit Booking Popup -->
    <div class="edit-popup" id="editPopup">
        <div class="edit-card">
            <h2>✏️ Edit Booking</h2>
            
            <div class="booking-info-display">
                <p><strong>Slot:</strong> <span id="editSlot">--</span></p>
                <p><strong>Vehicle:</strong> <span id="editVehicle">--</span></p>
            </div>

            <form id="editBookingForm">
                <input type="hidden" id="editBookingId" name="booking_id">
                
                <div class="form-group">
                    <label for="editDate">📅 Booking Date</label>
                    <input type="date" id="editDate" name="booking_date" required>
                </div>

                <div class="form-group">
                    <label for="editStartTime">🕐 Start Time</label>
                    <input type="time" id="editStartTime" name="start_time" required>
                </div>

                <div class="form-group">
                    <label for="editEndTime">🕐 End Time</label>
                    <input type="time" id="editEndTime" name="end_time" required>
                </div>

                <div class="edit-buttons">
                    <button type="submit" class="btn-save">💾 Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="closeEditPopup()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Popup -->
    <div class="confirm-popup" id="confirmPopup">
        <div class="confirm-card">
            <h2>🗑️ Delete Booking</h2>
            <p>Are you sure you want to delete the booking for slot <strong id="deleteSlotName">--</strong>?</p>
            <p style="font-size: 0.85rem;">This action cannot be undone.</p>
            <input type="hidden" id="deleteBookingId">
            <div class="edit-buttons">
                <button class="btn-confirm-delete" onclick="confirmDelete()">🗑️ Delete</button>
                <button class="btn-cancel" onclick="closeConfirmPopup()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for handling QR code display

        // Function to show the QR code popup for a booking
        function showQRCode(booking, baseUrl) {
            // Update popup details with booking information
            document.getElementById('popupSlot').textContent = booking.space_number || 'N/A';
            document.getElementById('popupArea').textContent = booking.area_name || 'Unknown Area';
            document.getElementById('popupVehicle').textContent = booking.vehicle_model + ' - ' + booking.license_plate;

            // Format and display the date
            const startDate = new Date(booking.booking_start);
            const endDate = new Date(booking.booking_end);
            document.getElementById('popupDate').textContent = startDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Format and display the time range
            document.getElementById('popupTime').textContent =
                startDate.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit'
                }) +
                ' - ' +
                endDate.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit'
                });

            // Build the verification URL for the QR code
            const qrUrl = baseUrl + encodeURIComponent(booking.booking_qr_code);

            // Clear any previous QR code
            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.innerHTML = '';

            // Generate new QR code using QRCode.js library
            new QRCode(qrContainer, {
                text: qrUrl,
                width: 200,
                height: 200,
                correctLevel: QRCode.CorrectLevel.M
            });

            // Show the popup
            document.getElementById('qrPopup').style.display = 'flex';
        }

        // Function to close the QR code popup
        function closeQrPopup() {
            document.getElementById('qrPopup').style.display = 'none';
        }

        // Close popup when clicking outside the card
        document.getElementById('qrPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQrPopup();
            }
        });

        // ============ EDIT BOOKING FUNCTIONS ============

        // Function to show the edit modal
        function showEditModal(booking) {
            // Display booking info
            document.getElementById('editSlot').textContent = booking.space_number || 'N/A';
            document.getElementById('editVehicle').textContent = booking.vehicle_model + ' - ' + booking.license_plate;
            
            // Set hidden booking ID
            document.getElementById('editBookingId').value = booking.booking_id;
            
            // Parse existing booking date and time
            const startDate = new Date(booking.booking_start);
            const endDate = new Date(booking.booking_end);
            
            // Format date as YYYY-MM-DD for input
            const dateStr = startDate.toISOString().split('T')[0];
            document.getElementById('editDate').value = dateStr;
            
            // Format times as HH:MM for inputs
            const startTimeStr = startDate.toTimeString().slice(0, 5);
            const endTimeStr = endDate.toTimeString().slice(0, 5);
            document.getElementById('editStartTime').value = startTimeStr;
            document.getElementById('editEndTime').value = endTimeStr;
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('editDate').min = today;
            
            // Show the popup
            document.getElementById('editPopup').style.display = 'flex';
        }

        // Function to close the edit modal
        function closeEditPopup() {
            document.getElementById('editPopup').style.display = 'none';
        }

        // Close edit popup when clicking outside
        document.getElementById('editPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditPopup();
            }
        });

        // Handle edit form submission
        document.getElementById('editBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeEditPopup();
                    // Reload page to show updated booking
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ An error occurred. Please try again.');
            });
        });

        // ============ DELETE BOOKING FUNCTIONS ============

        // Function to show delete confirmation
        function showDeleteConfirm(bookingId, slotName) {
            document.getElementById('deleteBookingId').value = bookingId;
            document.getElementById('deleteSlotName').textContent = slotName;
            document.getElementById('confirmPopup').style.display = 'flex';
        }

        // Function to close delete confirmation
        function closeConfirmPopup() {
            document.getElementById('confirmPopup').style.display = 'none';
        }

        // Close confirm popup when clicking outside
        document.getElementById('confirmPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmPopup();
            }
        });

        // Function to confirm and execute delete
        function confirmDelete() {
            const bookingId = document.getElementById('deleteBookingId').value;
            
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            
            fetch('delete_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeConfirmPopup();
                    // Remove the booking card from DOM
                    const bookingCard = document.getElementById('booking-card-' + bookingId);
                    if (bookingCard) {
                        bookingCard.remove();
                    }
                    // Reload to update statistics
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ An error occurred. Please try again.');
            });
        }
    </script>
</body>

</html>
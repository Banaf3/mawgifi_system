<?php

/**
 * QR Code Verification Page - Module 2
 * This page displays booking information when a QR code is scanned
 * It shows the parking space details and current booking status
 */

// Include database configuration (no session required for public verification)
require_once '../../config/database.php';

// Get the QR code from the URL parameter
$qr_code = isset($_GET['code']) ? $_GET['code'] : '';

// Initialize variables for booking data
$booking = null;
$error_message = '';

// Check if a QR code was provided
if (empty($qr_code)) {
    $error_message = 'No QR code provided. Please scan a valid booking QR code.';
} else {
    // Connect to database and look up the booking
    $conn = getDBConnection();

    if ($conn) {
        // Query to get booking details with related information
        $sql = "SELECT b.booking_id, b.booking_start, b.booking_end, b.booking_qr_code, b.created_at,
                       v.vehicle_model, v.license_plate, v.vehicle_type,
                       u.UserName,
                       ps.space_number,
                       pa.area_name
                FROM Booking b
                JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
                JOIN User u ON v.user_id = u.user_id
                LEFT JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
                LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
                WHERE b.booking_qr_code = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $qr_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();

            // Determine booking status
            $now = new DateTime();
            $start = new DateTime($booking['booking_start']);
            $end = new DateTime($booking['booking_end']);

            if ($now >= $start && $now <= $end) {
                $booking['status'] = 'active';
                $booking['status_text'] = 'Currently Active';
                $booking['status_color'] = '#48bb78';
            } elseif ($now < $start) {
                $booking['status'] = 'upcoming';
                $booking['status_text'] = 'Upcoming';
                $booking['status_color'] = '#ed8936';
            } else {
                $booking['status'] = 'expired';
                $booking['status_text'] = 'Expired';
                $booking['status_color'] = '#718096';
            }
        } else {
            $error_message = 'Booking not found. The QR code may be invalid or the booking has been deleted.';
        }

        $stmt->close();
        $conn->close();
    } else {
        $error_message = 'Unable to connect to the database. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Booking - Mawgifi</title>

    <style>
        /* CSS Variables for consistent theming */
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header bar styling */
        .header {
            background: var(--primary-grad);
            color: white;
            padding: 20px 40px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        /* Header title */
        .header h1 {
            font-size: 1.8rem;
            font-weight: 800;
        }

        /* Main content container */
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        /* Verification card styling */
        .verify-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        /* Status icon styling */
        .status-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        /* Status badge styling */
        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            margin-bottom: 25px;
        }

        /* Booking details section */
        .booking-details {
            text-align: left;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-top: 20px;
        }

        /* Detail row styling */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Last detail row without border */
        .detail-row:last-child {
            border-bottom: none;
        }

        /* Detail label styling */
        .detail-label {
            color: var(--text-light);
            font-weight: 500;
        }

        /* Detail value styling */
        .detail-value {
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
        }

        /* Slot number highlight styling */
        .slot-highlight {
            font-size: 2.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 10px;
        }

        /* Area name styling */
        .area-name {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        /* Error card styling */
        .error-card {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        /* Error icon styling */
        .error-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        /* Error message styling */
        .error-message {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-top: 15px;
            line-height: 1.6;
        }

        /* Back to home button styling */
        .btn-home {
            display: inline-block;
            margin-top: 25px;
            background: var(--primary-grad);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }

        /* Button hover effect */
        .btn-home:hover {
            transform: translateY(-2px);
        }

        /* Footer styling */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Responsive adjustments */
        @media (max-width: 480px) {

            .verify-card,
            .error-card {
                padding: 30px 20px;
            }

            .slot-highlight {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Header Bar -->
    <div class="header">
        <h1>🅿️ Mawgifi - Parking Verification</h1>
    </div>

    <!-- Main Content Container -->
    <div class="container">
        <?php if ($booking): ?>
            <!-- Booking Found - Display Details -->
            <div class="verify-card">
                <!-- Status Icon based on booking status -->
                <?php if ($booking['status'] === 'active'): ?>
                    <div class="status-icon">✅</div>
                <?php elseif ($booking['status'] === 'upcoming'): ?>
                    <div class="status-icon">🕐</div>
                <?php else: ?>
                    <div class="status-icon">⏰</div>
                <?php endif; ?>

                <!-- Status Badge -->
                <div class="status-badge" style="background: <?php echo $booking['status_color']; ?>;">
                    <?php echo htmlspecialchars($booking['status_text']); ?>
                </div>

                <!-- Parking Slot Information -->
                <div class="slot-highlight">
                    <?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>
                </div>
                <div class="area-name">
                    <?php echo htmlspecialchars($booking['area_name'] ?? 'Unknown Area'); ?>
                </div>

                <!-- Detailed Booking Information -->
                <div class="booking-details">
                    <div class="detail-row">
                        <span class="detail-label">Booking ID</span>
                        <span class="detail-value">#<?php echo $booking['booking_id']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Booked By</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['UserName']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Vehicle</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['vehicle_model']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">License Plate</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['license_plate']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date</span>
                        <span class="detail-value"><?php echo date('F j, Y', strtotime($booking['booking_start'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Time</span>
                        <span class="detail-value">
                            <?php echo date('g:i A', strtotime($booking['booking_start'])); ?> -
                            <?php echo date('g:i A', strtotime($booking['booking_end'])); ?>
                        </span>
                    </div>
                </div>

                <!-- Back to Home Button -->
                <a href="../../index.html" class="btn-home">Back to Home</a>
            </div>

        <?php else: ?>
            <!-- Error State - Booking Not Found -->
            <div class="error-card">
                <div class="error-icon">❌</div>
                <h2>Verification Failed</h2>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="../../index.html" class="btn-home">Back to Home</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© <?php echo date('Y'); ?> Mawgifi Parking System. All rights reserved.</p>
    </div>
</body>

</html>
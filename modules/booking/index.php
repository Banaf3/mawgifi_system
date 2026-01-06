<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

requireLogin();

$username = $_SESSION['username'] ?? 'User';
$user_id = getCurrentUserId();
$user_type = $_SESSION['user_type'] ?? 'user';
$is_admin_or_staff = (strtolower($user_type) === 'admin' || strtolower($user_type) === 'staff');
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/mawgifi_system";

$conn = getDBConnection();

// Enable error reporting for debugging
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($is_admin_or_staff) {
    // Fetch ALL bookings for Admin/Staff
    $stmt = $conn->prepare(
        "SELECT b.*, ps.space_number, pa.area_name, v.license_plate, v.vehicle_model, u.UserName as student_name,
                DATE_FORMAT(b.booking_start, '%M %d, %Y') as booking_date,
                DATE_FORMAT(b.booking_start, '%h:%i %p') as start_time,
                DATE_FORMAT(b.booking_end, '%h:%i %p') as end_time
         FROM Booking b
         JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
         LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
         JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
         JOIN User u ON v.user_id = u.user_id
         ORDER BY b.booking_start DESC"
    );
} else {
    // Fetch ONLY user's bookings
    $stmt = $conn->prepare(
        "SELECT b.*, ps.space_number, pa.area_name, v.license_plate, v.vehicle_model,
                DATE_FORMAT(b.booking_start, '%M %d, %Y') as booking_date,
                DATE_FORMAT(b.booking_start, '%h:%i %p') as start_time,
                DATE_FORMAT(b.booking_end, '%h:%i %p') as end_time
         FROM Booking b
         JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
         LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id
         JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
         WHERE v.user_id = ? AND b.booking_end >= NOW() ORDER BY b.booking_start ASC"
    );
    $stmt->bind_param("i", $user_id);
}

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    die("Query execution failed: " . $stmt->error);
}
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Calculate status for each booking
foreach ($bookings as &$booking) {
    $now = time();
    $start = strtotime($booking['booking_start']);
    $end = strtotime($booking['booking_end']);

    // Check if checked out
    if (isset($booking['check_out']) && !empty($booking['check_out'])) {
        $booking['status'] = 'completed';
    }
    // Check if checked in or currently within booking time
    elseif ((isset($booking['check_in']) && !empty($booking['check_in'])) || ($now >= $start && $now <= $end)) {
        $booking['status'] = 'active';
    }
    // Check if booking has ended
    elseif ($now > $end) {
        $booking['status'] = 'completed';
    }
    // Otherwise it's upcoming
    else {
        $booking['status'] = 'upcoming';
    }
}
unset($booking);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_admin_or_staff ? 'Bookings' : 'My Bookings' ?> - Mawgifi</title>
    <link rel="stylesheet" href="../../assets/module.css">
    <style>
        .booking-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .booking-info {
            flex: 1;
        }

        .booking-info h3 {
            font-size: 1rem;
        }

        .booking-slot {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2d3748;
        }

        .booking-details {
            color: #718096;
            font-size: 0.9rem;
            margin: 4px 0;
        }

        .booking-time {
            font-size: 0.85rem;
            color: #4a5568;
            background: #f7fafc;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #c6f6d5;
            color: #276749;
        }

        .status-badge.upcoming {
            background: #feebc8;
            color: #c05621;
        }

        .status-badge.completed {
            background: #e2e8f0;
            color: #4a5568;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 15px;
        }

        .empty-state a {
            color: #667eea;
            font-weight: 600;
        }

        /* Modal specific styles */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
        }

        .btn-modal-cancel {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #2d3748;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-modal-save {
            background: var(--primary-grad);
            border: none;
            color: #fff;
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

        .qr-btn {
            background: #edf2f7;
            color: #2d3748;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
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

        /* Search and Filter Styles */
        .search-filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
            background: #f7fafc;
            border-radius: 12px;
            display: none;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_type']) && strtolower($_SESSION['user_type']) === 'admin'): ?>
                <!-- Admin Navbar -->
                <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
                <a href="../membership/index.php">Vehicles</a>
                <a href="../parking/index.php">Parking Map</a>
                <a href="../../admin/parking_management.php">Manage Parking</a>
                <a href="../../admin/event_management.php">Events</a>
                <a href="index.php" class="active">Bookings</a>
                <a href="../../Moudel1/Admin.php?view=reports">Reports</a>
                <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
                <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
                <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
            <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff'): ?>
                <!-- Staff Navbar -->
                <a href="../../Moudel1/Stafe.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Stafe.php?view=requests">Vehicles Request</a>
                <a href="../parking/index.php">Parking Areas</a>
                <a href="index.php" class="active">Bookings</a>
                <a href="../../Moudel1/Stafe.php?view=profile">Profile</a>
            <?php else: ?>
                <!-- Student Navbar -->
                <a href="../../Moudel1/Student.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Student.php?view=vehicles">My Vehicles</a>
                <a href="../parking/index.php">Find Parking</a>
                <a href="index.php" class="active"><?= $is_admin_or_staff ? 'Bookings' : 'My Bookings' ?></a>
                <a href="../../Moudel1/Student.php?view=profile">Profile</a>
            <?php endif; ?>
        </div>
        <div class="user-profile">
            <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <span><?= htmlspecialchars($username) ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">🎫 <?= $is_admin_or_staff ? 'Bookings' : 'My Bookings' ?></h1>

        <!-- Search and Filter -->
        <?php if (!empty($bookings)): ?>
        <div class="search-filter-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by slot, vehicle, user..." onkeyup="filterBookings()">
            </div>
            <select class="filter-select" id="statusFilter" onchange="filterBookings()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="upcoming">Upcoming</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <div class="no-results" id="noResults">
            <span style="font-size: 2rem;">🔍</span>
            <p>No bookings match your search criteria</p>
        </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span>📭</span>
                <p>No bookings yet. <a href="../parking/index.php">Find a parking slot</a></p>
            </div>
        <?php endif; ?>

        <!-- Bookings List -->
        <?php if (!empty($bookings)): ?>
            <div class="bookings-container">
                <?php
                // Group bookings by status
                $grouped_bookings = ['active' => [], 'upcoming' => [], 'completed' => []];
                foreach ($bookings as $booking) {
                    $status = $booking['status'] ?? 'upcoming'; // Default to 'upcoming' if status not set
                    if (isset($grouped_bookings[$status])) {
                        $grouped_bookings[$status][] = $booking;
                    }
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
                                    <?php if (!empty($booking['student_name'])): ?>
                                        <div style="font-weight:600;color:#667eea;margin-bottom:2px;">User:
                                            <?php echo htmlspecialchars($booking['student_name']); ?>
                                        </div>
                                    <?php endif; ?>
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
                                <button class="qr-btn"
                                    onclick="showQRCode(<?php echo htmlspecialchars(json_encode($booking)); ?>, '<?php echo $base_url; ?>')">
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
                                    <?php if (!empty($booking['student_name'])): ?>
                                        <div style="font-weight:600;color:#667eea;margin-bottom:2px;">User:
                                            <?php echo htmlspecialchars($booking['student_name']); ?>
                                        </div>
                                    <?php endif; ?>
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
                                <button class="qr-btn"
                                    onclick="showQRCode(<?php echo htmlspecialchars(json_encode($booking)); ?>, '<?php echo $base_url; ?>')">
                                    📱 QR
                                </button>
                                <button class="edit-btn"
                                    onclick="showEditModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)">
                                    ✏️ Edit
                                </button>
                                <button class="delete-btn"
                                    onclick="showDeleteConfirm(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>')">
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
                                    <?php if (!empty($booking['student_name'])): ?>
                                        <div style="font-weight:600;color:#667eea;margin-bottom:2px;">User:
                                            <?php echo htmlspecialchars($booking['student_name']); ?>
                                        </div>
                                    <?php endif; ?>
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
                                <button class="delete-btn"
                                    onclick="showDeleteConfirm(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['space_number'] ?? 'N/A'); ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Popup -->
    <div class="modal-overlay" id="qrPopup">
        <div class="modal" style="text-align:center;">
            <h3>📱 Booking QR</h3>
            <div id="qrCodeContainer" style="margin: 20px 0;"></div>
            <p style="margin-top:15px;color:#666;">Scan at the parking slot</p>
            <button class="btn-modal-cancel" onclick="closeQrPopup()"
                style="margin-top:20px; width: 100%;">Close</button>
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
                    <input type="date" id="editDate" name="date" required>
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
        // Close popup when clicking outside the card
        document.getElementById('qrPopup').addEventListener('click', function (e) {
            if (e.target === this) {
                closeQrPopup();
            }
        });

        function showQRCode(booking, baseUrl) {
            const qrData = encodeURIComponent(
                baseUrl + '/modules/booking/scan.php?slot=' + booking.space_number
            );
            const img = document.createElement('img');
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + qrData;
            img.style.borderRadius = '8px';
            const container = document.getElementById('qrCodeContainer');
            container.innerHTML = '';
            container.appendChild(img);
            document.getElementById('qrPopup').classList.add('active');
        }

        function closeQrPopup() {
            document.getElementById('qrPopup').classList.remove('active');
        }

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
        document.getElementById('editPopup').addEventListener('click', function (e) {
            if (e.target === this) {
                closeEditPopup();
            }
        });

        // Handle edit form submission
        document.getElementById('editBookingForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => data[key] = value);

            fetch('api/update_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
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
        document.getElementById('confirmPopup').addEventListener('click', function (e) {
            if (e.target === this) {
                closeConfirmPopup();
            }
        });

        // Function to confirm and execute delete
        function confirmDelete() {
            const bookingId = document.getElementById('deleteBookingId').value;

            fetch('api/delete_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId })
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
                    alert('❌ An error occurred. Please try again.');
                });
        }

        // Filter bookings function
        function filterBookings() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const bookingCards = document.querySelectorAll('.booking-card');
            const sectionTitles = document.querySelectorAll('.section-title');
            let visibleCount = 0;

            bookingCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                const cardId = card.id;
                
                // Determine card status from parent section or badge
                let cardStatus = 'upcoming';
                const statusBadge = card.querySelector('.status-badge');
                if (statusBadge) {
                    if (statusBadge.classList.contains('active')) cardStatus = 'active';
                    else if (statusBadge.classList.contains('completed')) cardStatus = 'completed';
                    else cardStatus = 'upcoming';
                }

                const matchesSearch = cardText.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || cardStatus === statusFilter;

                if (matchesSearch && matchesStatus) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide section titles based on visible cards in each section
            sectionTitles.forEach(title => {
                let nextElement = title.nextElementSibling;
                let hasVisibleCards = false;
                
                while (nextElement && !nextElement.classList.contains('section-title')) {
                    if (nextElement.classList.contains('booking-card') && nextElement.style.display !== 'none') {
                        hasVisibleCards = true;
                        break;
                    }
                    nextElement = nextElement.nextElementSibling;
                }
                
                title.style.display = hasVisibleCards ? 'block' : 'none';
            });

            // Show no results message
            const noResults = document.getElementById('noResults');
            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }
    </script>
</body>

</html>
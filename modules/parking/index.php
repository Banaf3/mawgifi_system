<?php
/**
 * Find Parking Page - Module 2
 * This page displays a parking map with all available slots
 * Users can select a slot and book it, receiving a QR code confirmation
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
$dashboard_link = ($user_type === 'admin') ? '../../admin/dashboard.php' :
    (($user_type === 'staff') ? '../../staff/dashboard.php' : '../../Moudel1/Student.php');

// Check if user is a student to customize navigation labels
$is_student = ($user_type === 'user');
$nav_vehicles = $is_student ? 'My Vehicles' : 'Vehicles';
$nav_parking = $is_student ? 'Find Parking' : 'Parking Areas';
$nav_bookings = $is_student ? 'My Bookings' : 'Bookings';

// Define parking areas with their slot ranges
// Area A: slots 1-14, Area B: slots 15-44, etc.
$parking_areas = [
    'A' => ['start' => 1, 'end' => 14, 'color' => '#667eea', 'name' => 'Area A'],
    'B' => ['start' => 15, 'end' => 44, 'color' => '#764ba2', 'name' => 'Area B'],
    'C' => ['start' => 45, 'end' => 65, 'color' => '#48bb78', 'name' => 'Area C'],
    'D' => ['start' => 66, 'end' => 86, 'color' => '#ed8936', 'name' => 'Area D'],
    'E' => ['start' => 87, 'end' => 100, 'color' => '#e53e3e', 'name' => 'Area E']
];

// Function to determine which area a slot belongs to
function getAreaForSlot($slot_number, $areas) {
    foreach ($areas as $area_code => $area_info) {
        if ($slot_number >= $area_info['start'] && $slot_number <= $area_info['end']) {
            return $area_code;
        }
    }
    return 'A'; // Default to Area A if not found
}

// Connect to database and get user's vehicles
$conn = getDBConnection();
$vehicles = [];
if ($conn) {
    // Get approved vehicles for the current user
    $sql = "SELECT vehicle_id, vehicle_type, vehicle_model, license_plate 
            FROM Vehicle 
            WHERE user_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}

// Get currently booked slots to mark them as taken
$booked_slots = [];
if ($conn) {
    $sql = "SELECT ps.space_number 
            FROM Booking b 
            JOIN ParkingSpace ps ON b.Space_id = ps.Space_id 
            WHERE b.booking_end > NOW()";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Extract number from space_number like "A-01" -> 1
            preg_match('/(\d+)/', $row['space_number'], $matches);
            if (isset($matches[1])) {
                $booked_slots[] = (int)$matches[1];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Parking - Mawgifi</title>
    
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
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }

        /* Page header section styling */
        .page-header {
            background: white;
            padding: 25px 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Header title styling */
        .page-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        /* Page description text */
        .page-header p {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Area legend container - shows color coding for each area */
        .area-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        /* Individual legend item */
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 25px;
            font-size: 0.9rem;
        }

        /* Color box in legend */
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 5px;
        }

        /* Parking Slot Interactive Styles */
        .parking-slot {
            cursor: pointer;
            transition: all 0.3s ease;
            pointer-events: all;
        }

        /* Hover effect for available slots */
        .parking-slot:hover {
            opacity: 0.8;
            stroke: #2f855a;
            stroke-width: 2px;
        }

        /* Selected slot styling */
        .parking-slot.selected {
            stroke: #2b6cb0;
            stroke-width: 3px;
            filter: drop-shadow(0 0 8px rgba(66, 153, 225, 0.7));
        }

        /* Taken/booked slot styling */
        .parking-slot.taken {
            fill: #f56565 !important;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Container for the SVG parking map */
        .svg-container {
            width: 100%;
            height: calc(100vh - 280px);
            padding: 0;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            max-width: 1400px;
            margin: 0 auto;
        }

        /* SVG element sizing */
        svg {
            width: 100%;
            height: 100%;
            max-height: 100%;
        }

        /* Booking popup form container */
        .booking-popup {
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

        /* Booking form card */
        .booking-form {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 90%;
        }

        /* Form title */
        .booking-form h2 {
            margin-bottom: 20px;
            color: var(--text-dark);
            text-align: center;
        }

        /* Form group styling */
        .form-group {
            margin-bottom: 15px;
        }

        /* Form label styling */
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Form input and select styling */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        /* Input focus effect */
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Primary button styling */
        .btn-primary {
            background: var(--primary-grad);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: transform 0.2s;
        }

        /* Button hover effect */
        .btn-primary:hover {
            transform: translateY(-2px);
        }

        /* Cancel button styling */
        .btn-cancel {
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        /* Selected slot display in form */
        .selected-slot-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Slot number in display */
        .selected-slot-display .slot-number {
            font-size: 1.8rem;
            font-weight: 700;
        }

        /* Area name in display */
        .selected-slot-display .area-name {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* QR code success popup styling */
        .qr-popup {
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

        /* Success icon styling */
        .success-icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }

        /* QR code container */
        .qr-container {
            margin: 20px auto;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 15px;
            display: inline-block;
        }

        /* Booking details list */
        .booking-details {
            text-align: left;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        /* Individual detail item */
        .booking-details p {
            margin: 8px 0;
            color: var(--text-dark);
        }

        /* Detail label */
        .booking-details strong {
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

        /* Print styles - only show QR card when printing */
        @media print {
            body * {
                visibility: hidden;
            }
            .qr-card, .qr-card * {
                visibility: visible;
            }
            .qr-card {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
            }
            .btn-print, .btn-close {
                display: none !important;
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
            <a href="../parking/index.php" class="active"><?php echo $nav_parking; ?></a>
            <a href="../booking/index.php"><?php echo $nav_bookings; ?></a>
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
        <!-- Page Header with Area Legend -->
        <div class="page-header">
            <h1><?php echo $is_student ? 'Find Parking' : 'Parking Areas'; ?></h1>
            <p>Select an available parking slot to make a reservation</p>
            
            <!-- Area Color Legend -->
            <div class="area-legend">
                <?php foreach ($parking_areas as $code => $area): ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?php echo $area['color']; ?>;"></div>
                        <span><?php echo $area['name']; ?> (Slots <?php echo $area['start']; ?>-<?php echo $area['end']; ?>)</span>
                    </div>
                <?php endforeach; ?>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f56565;"></div>
                    <span>Booked/Unavailable</span>
                </div>
            </div>
        </div>

        <!-- SVG Parking Map Container -->
        <div class="svg-container">
            <?php include '../../assets/parking_slots_optimized.php'; ?>
        </div>
    </div>

    <!-- Booking Form Popup -->
    <div class="booking-popup" id="bookingPopup">
        <div class="booking-form">
            <h2>Book Parking Slot</h2>
            
            <!-- Display Selected Slot -->
            <div class="selected-slot-display">
                <div class="slot-number" id="displaySlotNumber">--</div>
                <div class="area-name" id="displayAreaName">Select a slot</div>
            </div>

            <!-- Booking Form -->
            <form id="bookingForm">
                <input type="hidden" name="slot_number" id="slotNumber">
                <input type="hidden" name="area_code" id="areaCode">
                
                <!-- Vehicle Selection -->
                <div class="form-group">
                    <label for="vehicleSelect">Select Vehicle</label>
                    <select name="vehicle_id" id="vehicleSelect" required>
                        <option value="">-- Choose your vehicle --</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                <?php echo htmlspecialchars($vehicle['vehicle_model'] . ' - ' . $vehicle['license_plate']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Booking Date -->
                <div class="form-group">
                    <label for="bookingDate">Booking Date</label>
                    <input type="date" name="booking_date" id="bookingDate" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>

                <!-- Start Time -->
                <div class="form-group">
                    <label for="startTime">Start Time</label>
                    <input type="time" name="start_time" id="startTime" required>
                </div>

                <!-- End Time -->
                <div class="form-group">
                    <label for="endTime">End Time</label>
                    <input type="time" name="end_time" id="endTime" required>
                </div>

                <!-- Submit and Cancel Buttons -->
                <button type="submit" class="btn-primary">Book Now</button>
                <button type="button" class="btn-cancel" onclick="closeBookingPopup()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- QR Code Success Popup -->
    <div class="qr-popup" id="qrPopup">
        <div class="qr-card">
            <div class="success-icon">✅</div>
            <h2>Booking Confirmed!</h2>
            
            <!-- QR Code Container - Will be generated by JavaScript -->
            <div class="qr-container" id="qrCodeContainer"></div>
            
            <!-- Booking Details -->
            <div class="booking-details">
                <p><strong>Slot:</strong> <span id="qrSlotInfo">--</span></p>
                <p><strong>Vehicle:</strong> <span id="qrVehicleInfo">--</span></p>
                <p><strong>Date:</strong> <span id="qrDateInfo">--</span></p>
                <p><strong>Time:</strong> <span id="qrTimeInfo">--</span></p>
                <p><strong>Booking ID:</strong> <span id="qrBookingId">--</span></p>
            </div>

            <!-- Action Buttons -->
            <button class="btn-print" onclick="window.print()">🖨️ Print QR Code</button>
            <button class="btn-close" onclick="closeQrPopup()">Close</button>
        </div>
    </div>

    <script>
        // JavaScript for handling slot selection and booking

        // Wait for the page to load completely
        document.addEventListener('DOMContentLoaded', function() {
            
            // Define parking areas with their slot ranges and colors
            const parkingAreas = {
                'A': { start: 1, end: 14, color: '#667eea', name: 'Area A' },
                'B': { start: 15, end: 44, color: '#764ba2', name: 'Area B' },
                'C': { start: 45, end: 65, color: '#48bb78', name: 'Area C' },
                'D': { start: 66, end: 86, color: '#ed8936', name: 'Area D' },
                'E': { start: 87, end: 100, color: '#e53e3e', name: 'Area E' }
            };

            // Array of booked slot numbers from PHP
            const bookedSlots = <?php echo json_encode($booked_slots); ?>;

            // Function to determine which area a slot belongs to
            function getAreaForSlot(slotNum) {
                for (const [code, area] of Object.entries(parkingAreas)) {
                    if (slotNum >= area.start && slotNum <= area.end) {
                        return { code: code, ...area };
                    }
                }
                return { code: 'A', ...parkingAreas['A'] };
            }

            // Get all parking slot elements from the SVG
            const slots = document.querySelectorAll('.parking-slot');
            let selectedSlot = null;

            // Loop through each slot and set up styling and click handlers
            slots.forEach(slot => {
                // Get the slot number from the element ID (e.g., "slot-1" -> 1)
                const slotId = slot.id;
                const slotNum = parseInt(slotId.replace('slot-', ''));
                
                // Get the area information for this slot
                const areaInfo = getAreaForSlot(slotNum);
                
                // Set the slot color based on its area
                slot.setAttribute('fill', areaInfo.color);
                
                // Check if this slot is already booked
                if (bookedSlots.includes(slotNum)) {
                    slot.classList.add('taken');
                    slot.setAttribute('fill', '#f56565'); // Red for taken
                }

                // Add click event listener for available slots
                slot.addEventListener('click', function() {
                    // Don't allow clicking on taken slots
                    if (this.classList.contains('taken')) return;

                    // Remove selection from previously selected slot
                    if (selectedSlot) {
                        selectedSlot.classList.remove('selected');
                    }

                    // Select this slot
                    this.classList.add('selected');
                    selectedSlot = this;

                    // Update the booking form with slot information
                    const area = getAreaForSlot(slotNum);
                    document.getElementById('displaySlotNumber').textContent = slotNum + area.code;
                    document.getElementById('displayAreaName').textContent = area.name;
                    document.getElementById('slotNumber').value = slotNum;
                    document.getElementById('areaCode').value = area.code;

                    // Show the booking popup
                    document.getElementById('bookingPopup').style.display = 'flex';
                });
            });

            // Set default date to today
            document.getElementById('bookingDate').value = new Date().toISOString().split('T')[0];

            // Handle form submission
            document.getElementById('bookingForm').addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent normal form submission

                // Collect form data
                const formData = new FormData(this);

                // Send booking request to server
                fetch('process_booking.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide booking popup
                        document.getElementById('bookingPopup').style.display = 'none';

                        // Update QR popup with booking details
                        document.getElementById('qrSlotInfo').textContent = data.slot_display;
                        document.getElementById('qrVehicleInfo').textContent = data.vehicle_info;
                        document.getElementById('qrDateInfo').textContent = data.booking_date;
                        document.getElementById('qrTimeInfo').textContent = data.start_time + ' - ' + data.end_time;
                        document.getElementById('qrBookingId').textContent = data.booking_id;

                        // Generate QR code using QRCode.js library
                        const qrContainer = document.getElementById('qrCodeContainer');
                        qrContainer.innerHTML = ''; // Clear any previous QR code
                        
                        // Create the QR code with the verification URL
                        new QRCode(qrContainer, {
                            text: data.qr_url,
                            width: 200,
                            height: 200,
                            correctLevel: QRCode.CorrectLevel.M
                        });

                        // Show QR popup
                        document.getElementById('qrPopup').style.display = 'flex';

                        // Mark the slot as taken
                        if (selectedSlot) {
                            selectedSlot.classList.remove('selected');
                            selectedSlot.classList.add('taken');
                            selectedSlot.setAttribute('fill', '#f56565');
                        }
                    } else {
                        // Show error message
                        alert('Booking failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });

        // Function to close the booking popup
        function closeBookingPopup() {
            document.getElementById('bookingPopup').style.display = 'none';
            // Remove selection from slot
            const selected = document.querySelector('.parking-slot.selected');
            if (selected) {
                selected.classList.remove('selected');
            }
        }

        // Function to close the QR code popup
        function closeQrPopup() {
            document.getElementById('qrPopup').style.display = 'none';
        }
    </script>
</body>

</html>
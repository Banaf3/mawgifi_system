<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../admin/check_event_status.php';

requireLogin();

$username = $_SESSION['username'] ?? 'User';
$user_id = getCurrentUserId();
$user_type = $_SESSION['user_type'] ?? 'user';
$is_student = ($user_type === 'user');

// Default parking areas with their slot ranges and colors
// These are the master definitions - actual availability comes from database
$parking_areas = [
    'A' => ['start' => 1, 'end' => 14, 'color' => '#667eea', 'name' => 'Area A', 'exists' => false],
    'B' => ['start' => 15, 'end' => 44, 'color' => '#764ba2', 'name' => 'Area B', 'exists' => false],
    'C' => ['start' => 45, 'end' => 65, 'color' => '#48bb78', 'name' => 'Area C', 'exists' => false],
    'D' => ['start' => 66, 'end' => 86, 'color' => '#ed8936', 'name' => 'Area D', 'exists' => false],
    'E' => ['start' => 87, 'end' => 100, 'color' => '#e53e3e', 'name' => 'Area E', 'exists' => false]
];

// Function to determine which area a slot belongs to
function getAreaForSlot($slot_number, $areas)
{
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
$available_spaces = []; // Array of space numbers that exist in database
$area_slot_mapping = []; // Maps slot numbers to area data

if ($conn) {
    // Query 1: Get all existing areas from database with their color and status
    // Check if columns exist first
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_color'");
    $has_color_column = $columns_check && $columns_check->num_rows > 0;

    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");
    $has_status_column = $columns_check && $columns_check->num_rows > 0;

    // Build query based on available columns
    $area_sql = "SELECT area_id, area_name";
    if ($has_color_column) {
        $area_sql .= ", area_color";
    }
    if ($has_status_column) {
        $area_sql .= ", area_status";
    }
    $area_sql .= " FROM ParkingArea";

    $area_result = $conn->query($area_sql);
    $db_areas = [];

    if ($area_result) {
        while ($area_row = $area_result->fetch_assoc()) {
            $db_areas[] = $area_row;
            $area_name = trim($area_row['area_name']);
            // Extract area code from "Area A" format or just "A"
            if (preg_match('/^Area\s*([A-E])$/i', $area_name, $matches)) {
                $area_code = strtoupper($matches[1]);
            } else {
                $area_code = strtoupper($area_name);
            }
            // Match area code (A, B, C, D, E) and update with database values
            if (isset($parking_areas[$area_code])) {
                $parking_areas[$area_code]['exists'] = true;
                $parking_areas[$area_code]['area_id'] = $area_row['area_id'];
                $parking_areas[$area_code]['color'] = isset($area_row['area_color']) ? $area_row['area_color'] : $parking_areas[$area_code]['color'];
                $parking_areas[$area_code]['status'] = isset($area_row['area_status']) ? $area_row['area_status'] : 'available';
                $parking_areas[$area_code]['name'] = $area_name;
            }
        }
    }

    // Query 2: Get all existing parking spaces from database with their status
    $space_sql = "SELECT ps.space_number, ps.Space_id, ps.status, pa.area_name";
    if ($has_color_column) {
        $space_sql .= ", pa.area_color";
    }
    if ($has_status_column) {
        $space_sql .= ", pa.area_status";
    }
    $space_sql .= " FROM ParkingSpace ps JOIN ParkingArea pa ON ps.area_id = pa.area_id";

    $space_result = $conn->query($space_sql);
    if ($space_result) {
        while ($space_row = $space_result->fetch_assoc()) {
            // Extract slot number from space_number
            // Format can be "A-01", "A-1", "1", "01", etc.
            $space_num = $space_row['space_number'];
            $slot_number = null;

            if (preg_match('/[A-E]-(\d+)/i', $space_num, $matches)) {
                // Format: A-01, B-15, etc.
                $slot_number = (int) $matches[1];
            } elseif (preg_match('/^(\d+)$/', $space_num, $matches)) {
                // Format: just a number like "1", "15"
                $slot_number = (int) $matches[1];
            }

            if ($slot_number !== null) {
                $available_spaces[] = $slot_number;
                // Store mapping from slot number to area data including space status
                $area_slot_mapping[$slot_number] = [
                    'space_id' => $space_row['Space_id'],
                    'area_name' => $space_row['area_name'],
                    'area_color' => isset($space_row['area_color']) ? $space_row['area_color'] : '#a0a0a0',
                    'area_status' => isset($space_row['area_status']) ? $space_row['area_status'] : 'available',
                    'status' => isset($space_row['status']) ? $space_row['status'] : 'available',
                    'space_number' => $space_num
                ];
            }
        }
    }

    // Query 3: Get approved vehicles for the current user
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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Areas - Mawgifi</title>
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
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
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

        /* Main Layout */
        .container {
            display: flex;
            flex-direction: column;
            padding: 20px;
            height: calc(100vh - 70px);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .page-header h1 {
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .page-header p {
            color: var(--text-light);
        }

        /* Area Color Legend */
        .area-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
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

        /* Unavailable slot styling - areas/spaces not in database */
        .parking-slot.unavailable {
            fill: #1a1a1a !important;
            cursor: not-allowed;
            pointer-events: none;
            opacity: 0.7;
        }

        /* Unavailable slot - no hover effect */
        .parking-slot.unavailable:hover {
            opacity: 0.7;
            stroke: none;
            stroke-width: 0;
        }

        /* Legend item for unavailable areas */
        .unavailable-legend {
            opacity: 0.7;
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
        }

        .svg-container svg {
            width: 100%;
            height: 100%;
        }

        /* Booking Panel */
        .booking-panel {
            width: 300px;
            background: #fff;
            border-left: 1px solid #e2e8f0;
            padding: 20px;
            overflow-y: auto;
            position: fixed;
            right: 0;
            top: 70px;
            bottom: 0;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.05);
        }

        .booking-panel h2 {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .time-row {
            display: flex;
            gap: 5px;
        }

        .time-row select {
            flex: 1;
        }

        .slot-display {
            background: var(--primary-grad);
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }

        .slot-display .slot-id {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .slot-display .slot-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .qr-section {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .qr-section img {
            background: #fff;
            padding: 5px;
            border-radius: 4px;
        }

        .qr-section p {
            font-size: 0.75rem;
            color: #888;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 8px;
        }

        .btn-primary {
            background: var(--primary-grad);
            color: #fff;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        .message {
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }

        .message.success {
            background: #c6f6d5;
            color: #276749;
        }

        .message.error {
            background: #fed7d7;
            color: #c53030;
        }

        .placeholder-text {
            color: #999;
            text-align: center;
            padding: 20px 10px;
            font-size: 0.9rem;
        }

        .placeholder-text span {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }

        .no-vehicle-msg {
            background: #fef3c7;
            color: #92400e;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
        }

        .no-vehicle-msg a {
            color: #92400e;
            font-weight: 600;
        }

        .legend {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 0.75rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .legend-box {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        /* Slot styles */
        .parking-slot {
            cursor: pointer;
            transition: all 0.2s;
        }

        .parking-slot:hover {
            fill: #48bb78 !important;
        }

        .parking-slot.selected {
            fill: #4299e1 !important;
            stroke: #2b6cb0;
            stroke-width: 2px;
        }

        .parking-slot.booked {
            fill: #f56565 !important;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>
        <div class="nav-links">
            <?php if ($is_student): ?>
                <a href="../../Moudel1/Student.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Student.php?view=vehicles">My Vehicles</a>
                <a href="../parking/index.php" class="active">Find Parking</a>
                <a href="../booking/index.php">My Bookings</a>
                <a href="../../Moudel1/Student.php?view=profile">Profile</a>
            <?php elseif (strtolower($user_type) === 'admin'): ?>
                <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
                <a href="../membership/index.php">Vehicles</a>
                <a href="../parking/index.php" class="active">Parking Map</a>
                <a href="../../admin/parking_management.php">Manage Parking</a>
                <a href="../../admin/event_management.php">Events</a>
                <a href="../booking/index.php">Bookings</a>
                <a href="../../Moudel1/Admin.php?view=reports">Reports</a>
                <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
                <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
                <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
            <?php else: ?>
                <a href="../../Moudel1/Stafe.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Stafe.php?view=requests">Vehicles</a>
                <a href="../parking/index.php" class="active">Parking Areas</a>
                <a href="../../Moudel1/Stafe.php?view=bookings">Bookings</a>
                <a href="../../Moudel1/Stafe.php?view=profile">Profile</a>
            <?php endif; ?>
        </div>
        <div class="user-profile">
            <div class="avatar-circle"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <span class="user-name"><?= htmlspecialchars($username) ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container" style="margin-right: 320px;">
        <!-- Page Header with Area Legend -->
        <div class="page-header">
            <h1><?php echo $is_student ? 'Find Parking' : 'Parking Areas'; ?></h1>
            <p>Select an available parking slot to make a reservation</p>

            <!-- Area Color Legend -->
            <div class="area-legend">
                <?php
                // Only show areas that exist in the database
                foreach ($parking_areas as $code => $area):
                    if (!$area['exists'])
                        continue; // Skip areas that don't exist
                    ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?php echo $area['color']; ?>;"></div>
                        <span>
                            <?php echo $area['name']; ?> (Slots <?php echo $area['start']; ?>-<?php echo $area['end']; ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (
                    empty(array_filter($parking_areas, function ($a) {
                    return $a['exists'];
                }))
                ): ?>
                    <div class="legend-item" style="opacity: 0.7;">
                        <em style="color: #999;">No parking areas configured yet</em>
                    </div>
                <?php endif; ?>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f56565;"></div>
                    <span>Booked</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #1a1a1a;"></div>
                    <span>Not in System</span>
                </div>
            </div>
        </div>

        <!-- SVG Parking Map Container -->
        <div class="svg-container">
            <?php include '../../assets/parking_slots_optimized.php'; ?>
        </div>

    </div>

    <div class="booking-panel">
        <?php if ($is_student): ?>
            <h2>📝 Book Parking</h2>
        <?php else: ?>
            <h2>🅿️ Slot Details</h2>
        <?php endif; ?>

        <div class="form-group">
            <label>Date</label>
            <input type="date" id="bookingDate" min="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label>Start Time</label>
            <div class="time-row">
                <select id="startHour">
                    <?php for ($h = 7; $h <= 11; $h++): ?>
                        <option value="<?= $h ?>"><?= $h ?></option>
                    <?php endfor; ?>
                    <option value="12">12</option>
                    <?php for ($h = 1; $h <= 6; $h++): ?>
                        <option value="<?= $h ?>"><?= $h ?></option>
                    <?php endfor; ?>
                </select>
                <select id="startAmPm">
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>End Time</label>
            <div class="time-row">
                <select id="endHour">
                    <?php for ($h = 7; $h <= 11; $h++): ?>
                        <option value="<?= $h ?>" <?= $h == 10 ? 'selected' : '' ?>><?= $h ?></option>
                    <?php endfor; ?>
                    <option value="12">12</option>
                    <?php for ($h = 1; $h <= 6; $h++): ?>
                        <option value="<?= $h ?>"><?= $h ?></option>
                    <?php endfor; ?>
                </select>
                <select id="endAmPm">
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
        </div>

        <button class="btn btn-secondary" onclick="loadSlots()">
            🔍 Check Availability
        </button>

        <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">

        <?php if ($is_student): ?>
            <?php if (empty($vehicles)): ?>
                <div class="no-vehicle-msg">
                    <p>No approved vehicle.</p>
                    <a href="../../Moudel1/Student.php?view=vehicles">Register one</a>
                </div>
            <?php else: ?>
                <div id="placeholder" class="placeholder-text">
                    <span>👆</span>
                    Select date/time, then click a slot
                </div>

                <div id="bookingForm" style="display:none;">
                    <div class="slot-display">
                        <div class="slot-label">Selected Slot</div>
                        <div class="slot-id" id="slotDisplay">-</div>
                    </div>

                    <div class="form-group">
                        <label>Vehicle</label>
                        <select id="vehicleSelect">
                            <option value="">-- Select --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['vehicle_id'] ?>">
                                    <?= htmlspecialchars($v['license_plate']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="qr-section" id="qrSection" style="display:none;">
                        <img id="qrImage" src="" alt="QR" width="90">
                        <p>Scan when parking</p>
                    </div>

                    <div id="messageBox"></div>

                    <button class="btn btn-primary" id="confirmBtn" type="button" onclick="confirmBooking()">
                        ✓ Confirm Booking
                    </button>
                    <button class="btn btn-secondary" type="button" onclick="clearSelection()">
                        ✕ Cancel
                    </button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Admin/Staff View -->
            <div id="placeholder" class="placeholder-text">
                <span>👆</span>
                Click a slot to view details
            </div>

            <div id="slotDetails" style="display:none;">
                <div class="slot-display">
                    <div class="slot-label">Slot Number</div>
                    <div class="slot-id" id="slotDisplay">-</div>
                </div>

                <div id="bookingInfo">
                    <p style="color:#888;text-align:center;">No booking for selected time</p>
                </div>

                <div class="qr-section" id="qrSection">
                    <img id="qrImage" src="" alt="QR" width="120">
                    <p>Slot QR Code</p>
                </div>

                <button class="btn btn-primary" onclick="printQR()">
                    🖨️ Print QR Code
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Wait for the page to load completely
        document.addEventListener('DOMContentLoaded', function () {

            // Define parking areas with their slot ranges, colors, and existence status from database
            const parkingAreas = <?php echo json_encode(array_map(function ($area) {
                return [
                    'start' => $area['start'],
                    'end' => $area['end'],
                    'color' => $area['color'],
                    'name' => $area['name'],
                    'exists' => $area['exists'],
                    'status' => $area['status'] ?? 'available'
                ];
            }, $parking_areas)); ?>;

            // Mapping of slot numbers to their area data from database
            const slotAreaMapping = <?php echo json_encode($area_slot_mapping); ?>;

            // Array of available space numbers from database (spaces that exist)
            const availableSpaces = <?php echo json_encode($available_spaces); ?>;

            // Function to determine which area a slot belongs to
            function getAreaForSlot(slotNum) {
                // First check if we have specific mapping from database
                if (slotAreaMapping[slotNum]) {
                    const mapping = slotAreaMapping[slotNum];
                    return {
                        code: 'DB',
                        color: mapping.area_color,
                        status: mapping.area_status,
                        spaceStatus: mapping.status || 'available',
                        name: mapping.area_name,
                        exists: true
                    };
                }

                // Fallback to predefined areas
                for (const [code, area] of Object.entries(parkingAreas)) {
                    if (slotNum >= area.start && slotNum <= area.end) {
                        return {
                            code: code,
                            ...area
                        };
                    }
                }
                return {
                    code: 'A',
                    ...parkingAreas['A'],
                    exists: false
                };
            }

            // Function to check if a slot is available in the database
            function isSlotAvailable(slotNum) {
                // Check if the space exists in the database
                return availableSpaces.includes(slotNum);
            }

            // Get all parking slot elements from the SVG
            // Use global selector to avoid conflicts
            const slots = document.querySelectorAll('.parking-slot');
            let selectedSlot = null;

            // Loop through each slot and set up styling and click handlers
            slots.forEach(slot => {
                // Get the slot number from the element ID (e.g., "slot-1" -> 1)
                const slotId = slot.id;
                const slotNum = parseInt(slotId.replace('slot-', ''));

                // Get the area information for this slot
                const areaInfo = getAreaForSlot(slotNum);

                // First check: Is the area available in database?
                // If area doesn't exist in database, show as black (unavailable)
                if (!areaInfo.exists) {
                    slot.classList.add('unavailable');
                    slot.setAttribute('fill', '#1a1a1a'); // Black for unavailable area
                    slot.style.cursor = 'not-allowed';
                    slot.style.pointerEvents = 'none';
                    return; // Skip further processing
                }

                // Second check: Is the specific slot available in database?
                // If slot doesn't exist in database, show as dark gray
                if (!isSlotAvailable(slotNum)) {
                    slot.classList.add('unavailable');
                    slot.setAttribute('fill', '#333333'); // Dark gray for unavailable slot
                    slot.style.cursor = 'not-allowed';
                    slot.style.pointerEvents = 'none';
                    return; // Skip further processing
                }

                // Check area status - if occupied, temporarily closed, or under maintenance, show as red
                if (areaInfo.status && ['occupied', 'temporarily_closed', 'under_maintenance'].includes(areaInfo.status)) {
                    slot.classList.add('area-closed');
                    slot.setAttribute('fill', '#f56565'); // Red for closed/occupied areas
                    slot.style.cursor = 'not-allowed';
                    slot.style.pointerEvents = 'none';
                    return; // Skip further processing
                }

                // Check individual space status - if NOT available, show as RED
                if (areaInfo.spaceStatus && areaInfo.spaceStatus !== 'available') {
                    slot.classList.add('space-unavailable');
                    slot.setAttribute('fill', '#f56565'); // Red for occupied/reserved/maintenance
                    slot.style.cursor = 'not-allowed';
                    slot.style.pointerEvents = 'none';
                    return; // Skip further processing
                }

                // Set the slot color based on its area (slot is available)
                slot.setAttribute('fill', areaInfo.color);

                // Add click event listener for available slots
                slot.addEventListener('click', function () {
                    // Don't allow clicking on taken or unavailable slots
                    // Use selectSlot function which handles logic
                    selectSlot(this);
                });
            });

            // Set default date to today
            document.getElementById('bookingDate').value = new Date().toISOString().split('T')[0];

            // Load slots availability
            loadSlots();
        });

        let selectedSlot = null;
        const slots = document.querySelectorAll('.parking-slot');
        const isStudent = <?= $is_student ? 'true' : 'false' ?>;

        function getTime(hourId, ampmId) {
            let h = parseInt(document.getElementById(hourId).value);
            const ap = document.getElementById(ampmId).value;
            if (ap === 'PM' && h < 12) h += 12;
            if (ap === 'AM' && h === 12) h = 0;
            return h.toString().padStart(2, '0') + ':00';
        }

        function loadSlots() {
            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            if (!date) {
                alert('Select a date');
                return;
            }

            slots.forEach(s => s.classList.remove('booked', 'selected'));
            // Note: Colors are reset by reload or by class removal, but 'fill' attribute remains. 
            // .booked class will override fill via CSS !important.

            clearSelection();

            fetch(`../booking/api/get_slots.php?date=${date}&start=${start}&end=${end}`)
                .then(r => r.json())
                .then(data => {
                    (data.booked || []).forEach(id => {
                        const el = document.getElementById('slot-' + id);
                        if (el) {
                            el.classList.add('booked');
                            // Force fill color for booked slots
                            el.setAttribute('fill', '#f56565');
                        }
                    });
                });
        }

        function selectSlot(el) {
            // Students can't select booked slots, admin/staff can
            if (isStudent && el.classList.contains('booked')) return;
            if (el.classList.contains('unavailable')) return;

            slots.forEach(s => s.classList.remove('selected'));
            el.classList.add('selected');

            selectedSlot = el.id.replace('slot-', '');

            if (isStudent) {
                selectSlotStudent();
            } else {
                selectSlotAdmin();
            }
        }

        function selectSlotStudent() {
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('bookingForm').style.display = 'block';
            document.getElementById('slotDisplay').textContent = '#' + selectedSlot;

            const qrData = encodeURIComponent(
                location.origin + '/mawgifi_system/modules/booking/scan.php?slot=' + selectedSlot
            );
            document.getElementById('qrImage').src =
                'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=' + qrData;
            document.getElementById('qrSection').style.display = 'block';
            document.getElementById('messageBox').innerHTML = '';
        }

        function selectSlotAdmin() {
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('slotDetails').style.display = 'block';
            document.getElementById('slotDisplay').textContent = '#' + selectedSlot;

            // Generate QR for this slot
            const qrData = encodeURIComponent(
                location.origin + '/mawgifi_system/modules/booking/scan.php?slot=' + selectedSlot
            );
            document.getElementById('qrImage').src =
                'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + qrData;

            // Fetch booking info for this slot
            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            fetch(`../booking/api/get_slot_booking.php?slot=${selectedSlot}&date=${date}&start=${start}&end=${end}`)
                .then(r => r.json())
                .then(data => {
                    const infoDiv = document.getElementById('bookingInfo');
                    if (data.booking) {
                        const b = data.booking;
                        infoDiv.innerHTML = `
                            <div style="background:#f0f9ff;padding:12px;border-radius:8px;margin:10px 0;">
                                <p style="margin:5px 0;"><strong>Status:</strong> 
                                    <span style="color:${b.status === 'checked_in' ? '#38a169' : '#dd6b20'};">
                                        ${b.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </p>
                                <p style="margin:5px 0;"><strong>Student:</strong> ${b.username}</p>
                                <p style="margin:5px 0;"><strong>Vehicle:</strong> ${b.license_plate}</p>
                                <p style="margin:5px 0;"><strong>Model:</strong> ${b.vehicle_model}</p>
                                <p style="margin:5px 0;"><strong>Time:</strong> ${b.start_time} - ${b.end_time}</p>
                                ${b.check_in_time ? '<p style="margin:5px 0;"><strong>Checked In:</strong> ' + b.check_in_time + '</p>' : ''}
                                ${b.check_out_time ? '<p style="margin:5px 0;"><strong>Checked Out:</strong> ' + b.check_out_time + '</p>' : ''}
                            </div>`;
                    } else {
                        infoDiv.innerHTML = '<p style="color:#888;text-align:center;">No booking for selected time</p>';
                    }
                });
        }

        function clearSelection() {
            slots.forEach(s => s.classList.remove('selected'));
            selectedSlot = null;
            const ph = document.getElementById('placeholder');
            if (ph) ph.style.display = 'block';

            if (isStudent) {
                const bf = document.getElementById('bookingForm');
                if (bf) bf.style.display = 'none';
            } else {
                const sd = document.getElementById('slotDetails');
                if (sd) sd.style.display = 'none';
            }
        }

        function confirmBooking() {
            const vid = document.getElementById('vehicleSelect').value;
            if (!vid) {
                showMsg('Select a vehicle', 'error');
                return;
            }
            if (!selectedSlot) {
                showMsg('Select a slot', 'error');
                return;
            }

            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            // Client-side validation for past time
            const bookingDateTime = new Date(date + 'T' + start);
            const now = new Date();

            if (bookingDateTime < now) {
                showMsg('Cannot book for a past time', 'error');
                return;
            }

            document.getElementById('confirmBtn').disabled = true;

            fetch('../booking/api/create_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    vehicle_id: vid,
                    slot_id: selectedSlot,
                    date: date,
                    start_time: start,
                    end_time: end
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMsg('Booking confirmed!', 'success');
                        setTimeout(() => location.href = '../booking/index.php', 1000);
                    } else {
                        showMsg(data.message || 'Failed', 'error');
                        document.getElementById('confirmBtn').disabled = false;
                    }
                })
                .catch(() => {
                    showMsg('Error occurred', 'error');
                    document.getElementById('confirmBtn').disabled = false;
                });
        }

        function showMsg(msg, type) {
            document.getElementById('messageBox').innerHTML =
                '<div class="message ' + type + '">' + msg + '</div>';
        }

        function printQR() {
            const qrSrc = document.getElementById('qrImage').src;
            const slotNum = selectedSlot;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head><title>Print QR - Slot #${slotNum}</title></head>
                <body style="text-align:center;font-family:Arial,sans-serif;padding:40px;">
                    <h1 style="color:#5a67d8;">Mawgifi Parking</h1>
                    <h2>Slot #${slotNum}</h2>
                    <img src="${qrSrc}" style="width:200px;height:200px;">
                    <p style="color:#666;margin-top:20px;">Scan to view booking or reserve this slot</p>
                    <script>window.onload = function() { window.print(); }<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    </script>
</body>

</html>
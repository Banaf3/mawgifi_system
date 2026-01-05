<?php
/**
 * =============================================================================
 * PARKING BOOKING - PUBLIC USER INTERFACE
 * =============================================================================
 * 
 * FILE: Module2/public/index.php
 * PURPOSE: Interactive parking map for users to select and book parking spaces
 * AUTHOR: Mawgifi System Team
 * 
 * =============================================================================
 * DESCRIPTION
 * =============================================================================
 * This is the main user-facing interface for the parking system. It displays:
 * - An interactive SVG parking map with 100 spaces (5 areas x 20 spaces)
 * - Real-time availability information from the database
 * - A booking form for selecting date, time, and vehicle
 * - QR code generation for successful bookings
 * 
 * Users can:
 * - Click on available parking spaces to select them
 * - Choose booking date and time
 * - Submit booking request
 * - Receive QR code confirmation
 * 
 * Admins have additional capabilities:
 * - Edit existing bookings
 * - Update space status
 * 
 * =============================================================================
 * FILE STRUCTURE (1122 lines)
 * =============================================================================
 * 
 * SECTION 1: PHP BACKEND (Lines 1-130)
 * - Session authentication
 * - Database queries for areas, spaces, vehicles
 * - Parking areas configuration
 * - Slot-to-area mapping logic
 * 
 * SECTION 2: HTML HEAD & CSS (Lines 132-750)
 * - Complete CSS styling
 * - SVG parking map styling
 * - Slot colors (available, unavailable, selected)
 * - Booking form styling
 * - Modal and toast styling
 * - Responsive design
 * 
 * SECTION 3: HTML BODY (Lines 751-900)
 * - Navigation bar
 * - Parking map container
 * - SVG with 100 parking slots
 * - Booking form panel
 * - Modal dialogs
 * 
 * SECTION 4: JAVASCRIPT (Lines 901-1122)
 * - getAreaForSlot(): Determine area from slot number
 * - isSlotAvailable(): Check if slot exists in DB
 * - getTime(): Format time from form inputs
 * - loadSlots(): Fetch and display slot status
 * - selectSlot(): Handle slot click
 * - selectSlotStudent() / selectSlotAdmin(): Role-based selection
 * - clearSelection(): Reset slot selection
 * - confirmBooking(): Submit booking via AJAX
 * - showMsg(): Display toast notifications
 * - printQR(): Print QR code
 * 
 * =============================================================================
 * DATA FLOW
 * =============================================================================
 * 
 * 1. PAGE LOAD:
 *    index.php -> database queries -> Render SVG with PHP data
 *         |                               |
 *         v                               v
 *    User/Vehicle data            Area colors & slot availability
 * 
 * 2. SLOT SELECTION:
 *    User clicks slot
 *         |
 *         v
 *    selectSlot() -> Check availability -> Update UI
 *         |
 *         v
 *    Show booking form
 * 
 * 3. BOOKING SUBMISSION:
 *    User fills form -> confirmBooking()
 *         |
 *         v
 *    fetch() -> process_booking.php
 *         |
 *         v
 *    JSON response -> Show QR code or error
 * 
 * =============================================================================
 * PARKING AREAS LAYOUT
 * =============================================================================
 * 
 * Area A: Slots 1-14   (Purple)    - Left section
 * Area B: Slots 15-44  (Purple)    - Top section
 * Area C: Slots 45-65  (Green)     - Center section
 * Area D: Slots 66-86  (Orange)    - Right section
 * Area E: Slots 87-100 (Red)       - Bottom section
 * 
 * Total: 100 parking spaces
 * 
 * =============================================================================
 * SLOT STATUS COLORS
 * =============================================================================
 * - Available (exists in DB): Area color from database
 * - Unavailable (not in DB): Gray (#a0a0a0)
 * - Selected: Yellow (#fbbf24)
 * - Area Closed: Red (#e53e3e)
 * 
 * =============================================================================
 * DEPENDENCIES
 * =============================================================================
 * - config/session.php: Authentication (requireLogin())
 * - config/database.php: Database connection
 * - admin/check_event_status.php: Auto-update area status based on events
 * - public/process_booking.php: Handle booking submissions
 * - External: api.qrserver.com for QR code generation
 * 
 * =============================================================================
 * SECURITY
 * =============================================================================
 * - requireLogin(): Only authenticated users can access
 * - user_id from session for vehicle queries
 * - All database queries use prepared statements
 * 
 * =============================================================================
 */

// Line 126: Include session management (provides requireLogin())
require_once '../../config/session.php';
// Line 127: Include database connection helper
require_once '../../config/database.php';
// Line 128: Include event status checker (auto-updates area status based on events)
require_once '../admin/check_event_status.php';

// -----------------------------------------------------------------------------
// AUTHENTICATION CHECK
// -----------------------------------------------------------------------------
// Line 134: Verify user is logged in, redirect to login if not
requireLogin();

// Line 137: Get username from session for display
$username = $_SESSION['username'] ?? 'User';
// Line 138: Get user ID for vehicle queries
$user_id = getCurrentUserId();
// Line 139: Get user type (admin vs user)
$user_type = $_SESSION['user_type'] ?? 'user';
// Line 140: Check if user is a student (non-admin)
$is_student = ($user_type === 'user');

// -----------------------------------------------------------------------------
// DEFAULT PARKING AREAS CONFIGURATION
// -----------------------------------------------------------------------------
// These define the 5 parking areas (A-E) with their slot ranges and default colors.
// Actual colors and status are loaded from database and override these defaults.
// 'exists' flag tracks if the area exists in the database.

$parking_areas = [
    'A' => ['start' => 1, 'end' => 14, 'color' => '#667eea', 'name' => 'Area A', 'exists' => false],    // Line 151: Area A - slots 1-14
    'B' => ['start' => 15, 'end' => 44, 'color' => '#764ba2', 'name' => 'Area B', 'exists' => false],   // Line 152: Area B - slots 15-44
    'C' => ['start' => 45, 'end' => 65, 'color' => '#48bb78', 'name' => 'Area C', 'exists' => false],   // Line 153: Area C - slots 45-65
    'D' => ['start' => 66, 'end' => 86, 'color' => '#ed8936', 'name' => 'Area D', 'exists' => false],   // Line 154: Area D - slots 66-86
    'E' => ['start' => 87, 'end' => 100, 'color' => '#e53e3e', 'name' => 'Area E', 'exists' => false]   // Line 155: Area E - slots 87-100
];

/**
 * FUNCTION: getAreaForSlot($slot_number, $areas)
 * 
 * PURPOSE: Determine which area a slot number belongs to
 * 
 * @param int $slot_number - The slot number (1-100)
 * @param array $areas - Array of area definitions with start/end ranges
 * @return string - Area code (A, B, C, D, or E)
 * 
 * ALGORITHM:
 * Loop through each area and check if slot_number falls within its range.
 * Return area code on match, or default to 'A' if not found.
 */
function getAreaForSlot($slot_number, $areas)
{
    foreach ($areas as $area_code => $area_info) {  // Line 173: Loop through each area
        if ($slot_number >= $area_info['start'] && $slot_number <= $area_info['end']) {  // Line 174: Check if slot is in range
            return $area_code;  // Line 175: Return matching area code
        }
    }
    return 'A'; // Line 178: Default to Area A if not found
}

// -----------------------------------------------------------------------------
// DATABASE QUERIES
// -----------------------------------------------------------------------------

// Line 184: Establish database connection
$conn = getDBConnection();
$vehicles = [];           // Line 185: Array to store user's approved vehicles
$available_spaces = [];   // Line 186: Array of slot numbers that exist in database
$area_slot_mapping = [];  // Line 187: Maps slot numbers to their area and status data

if ($conn) {  // Line 189: Only proceed if connection successful
    // =========================================================================
    // QUERY 1: GET ALL PARKING AREAS FROM DATABASE
    // =========================================================================
    // First check if color and status columns exist (schema compatibility)
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_color'");  // Line 195: Check for color column
    $has_color_column = $columns_check && $columns_check->num_rows > 0;  // Line 196: Store result
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");  // Line 198: Check for status column
    $has_status_column = $columns_check && $columns_check->num_rows > 0;  // Line 199: Store result
    
    // Build SELECT query based on available columns
    $area_sql = "SELECT area_id, area_name";  // Line 202: Start building query
    if ($has_color_column) {
        $area_sql .= ", area_color";  // Line 204: Add color if exists
    }
    if ($has_status_column) {
        $area_sql .= ", area_status";  // Line 207: Add status if exists
    }
    $area_sql .= " FROM ParkingArea";  // Line 209: Complete query
    
    $area_result = $conn->query($area_sql);  // Line 211: Execute query
    $db_areas = [];  // Line 212: Array to store database area records
    
    if ($area_result) {  // Line 214: If query succeeded
        while ($area_row = $area_result->fetch_assoc()) {  // Line 215: Loop through results
            $db_areas[] = $area_row;  // Line 216: Add to array
            $area_name = trim($area_row['area_name']);  // Line 217: Get area name
            
            // Extract area code from name format "Area A" or just "A"
            if (preg_match('/^Area\s*([A-E])$/i', $area_name, $matches)) {  // Line 220: Match "Area X" format
                $area_code = strtoupper($matches[1]);  // Line 221: Extract letter
            } else {
                $area_code = strtoupper($area_name);  // Line 223: Use name as code
            }
            
            // Update parking_areas with database values
            if (isset($parking_areas[$area_code])) {  // Line 227: If valid area code
                $parking_areas[$area_code]['exists'] = true;  // Line 228: Mark as existing
                $parking_areas[$area_code]['area_id'] = $area_row['area_id'];  // Line 229: Store area ID
                $parking_areas[$area_code]['color'] = isset($area_row['area_color']) ? $area_row['area_color'] : $parking_areas[$area_code]['color'];  // Line 230: Use DB color or default
                $parking_areas[$area_code]['status'] = isset($area_row['area_status']) ? $area_row['area_status'] : 'available';  // Line 231: Use DB status or default
                $parking_areas[$area_code]['name'] = $area_name;  // Line 232: Store name
            }
        }
    }

    // =========================================================================
    // QUERY 2: GET ALL PARKING SPACES WITH STATUS
    // =========================================================================
    // Join with ParkingArea to get area info for each space
    
    $space_sql = "SELECT ps.space_number, ps.Space_id, ps.status, pa.area_name, pa.area_color, pa.area_status
                  FROM ParkingSpace ps 
                  JOIN ParkingArea pa ON ps.area_id = pa.area_id";  // Line 243-245: Join spaces with areas
    
    $space_result = $conn->query($space_sql);  // Line 247: Execute query
    if ($space_result) {  // Line 248: If query succeeded
        while ($space_row = $space_result->fetch_assoc()) {  // Line 249: Loop through results
            // Parse space number - handles formats like "A-01", "1", "01"
            $space_num = $space_row['space_number'];  // Line 251: Get space number string
            $slot_number = null;  // Line 252: Initialize slot number
            
            // Try to extract number from formats like "A-01", "B-15"
            if (preg_match('/[A-Z]-(\d+)/i', $space_num, $matches)) {  // Line 255: Match "X-NN" format
                $slot_number = (int) $matches[1];  // Line 256: Extract numeric part
            } elseif (is_numeric($space_num)) {  // Line 257: If just a number
                $slot_number = (int) $space_num;  // Line 258: Use directly
            }
            
            // Store slot data if valid slot number (1-100)
            if ($slot_number !== null && $slot_number > 0 && $slot_number <= 100) {  // Line 262: Validate slot number range
                $available_spaces[] = $slot_number;  // Line 263: Add to available spaces array
                // Store comprehensive mapping from slot number to all its data
                $area_slot_mapping[$slot_number] = [  // Line 265: Create mapping entry
                    'space_id' => $space_row['Space_id'],       // Line 266: Database ID
                    'area_name' => $space_row['area_name'],     // Line 267: Area name
                    'area_color' => $space_row['area_color'] ?? '#a0a0a0',  // Line 268: Color with gray default
                    'area_status' => $space_row['area_status'] ?? 'available',  // Line 269: Area status
                    'status' => $space_row['status'] ?? 'available',  // Line 270: Space status
                    'space_number' => $space_num  // Line 271: Original space number string
                ];
            }
        }
    }

    // =========================================================================
    // QUERY 3: GET USER'S APPROVED VEHICLES
    // =========================================================================
    // Only fetch vehicles that have been approved by admin
    
    $sql = "SELECT vehicle_id, vehicle_type, vehicle_model, license_plate 
            FROM Vehicle 
            WHERE user_id = ? AND status = 'approved'";  // Line 282-284: Select approved vehicles for user
    $stmt = $conn->prepare($sql);  // Line 285: Prepare statement
    $stmt->bind_param("i", $user_id);  // Line 286: Bind user ID parameter
    $stmt->execute();  // Line 287: Execute query
    $result = $stmt->get_result();  // Line 288: Get results
    while ($row = $result->fetch_assoc()) {  // Line 289: Loop through results
        $vehicles[] = $row;  // Line 290: Add each vehicle to array
    }
    $stmt->close();  // Line 292: Close statement
}
$conn->close();  // Line 294: Close database connection
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
                <a href="index.php" class="active">Find Parking</a>
                <a href="../../modules/booking/index.php">My Bookings</a>
                <a href="../../Moudel1/Student.php?view=profile">Profile</a>
            <?php elseif (strtolower($user_type) === 'admin'): ?>
                <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
                <a href="../../modules/membership/index.php">Vehicles</a>
                <a href="index.php" class="active">Parking Map</a>
                <a href="../admin/parking_management.php">Manage Parking</a>
                <a href="../../admin/event_management.php">Events</a>
                <a href="../../modules/booking/index.php">Bookings</a>
                <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
                <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
                <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
            <?php else: ?>
                <a href="../../Moudel1/Stafe.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Stafe.php?view=requests">Vehicles</a>
                <a href="index.php" class="active">Parking Areas</a>
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
                    if (!$area['exists']) continue; // Skip areas that don't exist
                ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?php echo $area['color']; ?>;"></div>
                        <span>
                            <?php echo $area['name']; ?> (Slots <?php echo $area['start']; ?>-<?php echo $area['end']; ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty(array_filter($parking_areas, function($a) { return $a['exists']; }))): ?>
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
                    <a href="../membership/index.php">Register one</a>
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
        // =====================================================================
        // JAVASCRIPT SECTION - PARKING BOOKING PUBLIC INTERFACE
        // =====================================================================
        // 
        // This section contains all client-side JavaScript for the parking
        // booking interface. Functions are organized into:
        // 1. Initialization (DOMContentLoaded)
        // 2. Area/Slot Mapping Functions
        // 3. Slot Loading and Display
        // 4. Slot Selection Handlers
        // 5. Booking Submission
        // 6. Utility Functions (toast, print)
        // 
        // Data from PHP is injected via PHP echo json_encode()
        // =====================================================================

        // =====================================================================
        // INITIALIZATION - RUNS WHEN DOM IS READY
        // =====================================================================
        // Wait for the page to load completely before attaching event handlers
        document.addEventListener('DOMContentLoaded', function() {

            // -----------------------------------------------------------------
            // INJECT PHP DATA INTO JAVASCRIPT
            // -----------------------------------------------------------------
            
            // Define parking areas with their slot ranges, colors, and existence status from database
            // This PHP array is converted to a JavaScript object
            const parkingAreas = <?php echo json_encode(array_map(function ($area) {
                                        return [
                                            'start' => $area['start'],      // First slot number in this area
                                            'end' => $area['end'],          // Last slot number in this area
                                            'color' => $area['color'],      // Hex color code for available slots
                                            'name' => $area['name'],        // Display name (e.g., "Area A")
                                            'exists' => $area['exists'],    // Whether area exists in database
                                            'status' => $area['status'] ?? 'available'  // Area status
                                        ];
                                    }, $parking_areas)); ?>;

            // Mapping of slot numbers to their area data from database
            // Key: slot number (1-100), Value: object with space_id, area_name, colors, status
            const slotAreaMapping = <?php echo json_encode($area_slot_mapping); ?>;

            // Debug logging for development
            console.log('Slot 1 mapping:', slotAreaMapping[1]);  // Line: Debug slot 1 data
            console.log('Full mapping:', slotAreaMapping);  // Line: Debug all slot mappings

            // Array of available space numbers from database (spaces that exist)
            // Only these slot numbers are clickable
            const availableSpaces = <?php echo json_encode($available_spaces); ?>;

            // -----------------------------------------------------------------
            // FUNCTION: getAreaForSlot(slotNum)
            // -----------------------------------------------------------------
            /**
             * Determine which area a slot number belongs to
             * First checks database mapping, then falls back to predefined ranges
             * 
             * @param {number} slotNum - Slot number (1-100)
             * @returns {Object} - Area info with code, color, status, exists flag
             */
            function getAreaForSlot(slotNum) {
                // First check: Do we have specific data from database for this slot?
                if (slotAreaMapping[slotNum]) {  // Line: Check if slot exists in mapping
                    const mapping = slotAreaMapping[slotNum];  // Line: Get mapping data
                    return {
                        code: 'DB',                           // Line: Mark as from database
                        color: mapping.area_color,            // Line: Use database color
                        status: mapping.area_status,          // Line: Area status (available/closed)
                        spaceStatus: mapping.status || 'available',  // Line: Individual space status
                        name: mapping.area_name,              // Line: Area name
                        exists: true                          // Line: Slot exists in database
                    };
                }
                
                // Fallback: Use predefined area ranges
                for (const [code, area] of Object.entries(parkingAreas)) {  // Line: Loop through areas
                    if (slotNum >= area.start && slotNum <= area.end) {  // Line: Check if slot is in range
                        return {
                            code: code,   // Line: Area code (A, B, C, D, E)
                            ...area       // Line: Spread all area properties
                        };
                    }
                }
                
                // Default: Return Area A if not found
                return {
                    code: 'A',
                    ...parkingAreas['A'],
                    exists: false  // Line: Mark as not in database
                };
            }

            // -----------------------------------------------------------------
            // FUNCTION: isSlotAvailable(slotNum)
            // -----------------------------------------------------------------
            /**
             * Check if a slot number exists in the database
             * Only slots in availableSpaces array are clickable
             * 
             * @param {number} slotNum - Slot number to check
             * @returns {boolean} - True if slot exists in database
             */
            function isSlotAvailable(slotNum) {
                return availableSpaces.includes(slotNum);  // Line: Check if slot is in available array
            }

            // -----------------------------------------------------------------
            // INITIALIZE ALL PARKING SLOTS
            // -----------------------------------------------------------------
            // Get all SVG slot elements and set up their styling and click handlers
            
            const slots = document.querySelectorAll('.parking-slot');  // Line: Get all slot elements
            let selectedSlot = null;  // Line: Track currently selected slot

            // Loop through each slot element
            slots.forEach(slot => {
                // Parse slot number from element ID (e.g., "slot-1" -> 1)
                const slotId = slot.id;  // Line: Get element ID
                const slotNum = parseInt(slotId.replace('slot-', ''));  // Line: Extract number

                // Get area information for this slot
                const areaInfo = getAreaForSlot(slotNum);  // Line: Get area data

                // ---------------------------------------------------------
                // CHECK 1: Is the area available in database?
                // ---------------------------------------------------------
                // If area doesn't exist in database, show as black (unavailable)
                if (!areaInfo.exists) {  // Line: Check if area exists
                    slot.classList.add('unavailable');  // Line: Add unavailable class
                    slot.setAttribute('fill', '#1a1a1a');  // Line: Set fill to black
                    slot.style.cursor = 'not-allowed';  // Line: Change cursor
                    slot.style.pointerEvents = 'none';  // Line: Disable clicks
                    return;  // Line: Skip further processing
                }

                // ---------------------------------------------------------
                // CHECK 2: Is the specific slot available in database?
                // ---------------------------------------------------------
                // If slot doesn't exist in database, show as dark gray
                if (!isSlotAvailable(slotNum)) {  // Line: Check if slot exists
                    slot.classList.add('unavailable');  // Line: Add unavailable class
                    slot.setAttribute('fill', '#333333');  // Line: Set fill to dark gray
                    slot.style.cursor = 'not-allowed';  // Line: Change cursor
                    slot.style.pointerEvents = 'none';  // Line: Disable clicks
                    return;  // Line: Skip further processing
                }

                // ---------------------------------------------------------
                // CHECK 3: Is the area open/available?
                // ---------------------------------------------------------
                // Check area status - if occupied, temporarily closed, or under maintenance, show as red
                if (areaInfo.status && ['occupied', 'temporarily_closed', 'under_maintenance'].includes(areaInfo.status)) {  // Line: Check area status
                    slot.classList.add('area-closed');  // Line: Add closed class
                    slot.setAttribute('fill', '#f56565');  // Line: Set fill to red
                    slot.style.cursor = 'not-allowed';  // Line: Change cursor
                    slot.style.pointerEvents = 'none';  // Line: Disable clicks
                    return;  // Line: Skip further processing
                }

                // ---------------------------------------------------------
                // CHECK 4: Is the individual space available?
                // ---------------------------------------------------------
                // Check individual space status
                console.log('Slot', slotNum, 'spaceStatus:', areaInfo.spaceStatus);  // Line: Debug log
                
                // If space status is NOT available, show as RED
                if (areaInfo.spaceStatus && areaInfo.spaceStatus !== 'available') {  // Line: Check space status
                    console.log('Marking slot', slotNum, 'as RED due to status:', areaInfo.spaceStatus);  // Line: Debug log
                    slot.classList.add('space-unavailable');  // Line: Add unavailable class
                    slot.setAttribute('fill', '#f56565');  // Line: Set fill to red
                    slot.style.cursor = 'not-allowed';  // Line: Change cursor
                    slot.style.pointerEvents = 'none';  // Line: Disable clicks
                    return;  // Line: Skip further processing
                }

                // ---------------------------------------------------------
                // SLOT IS AVAILABLE - Set color and enable clicking
                // ---------------------------------------------------------
                slot.setAttribute('fill', areaInfo.color);  // Line: Set fill to area color

                // Add click event listener for available slots
                slot.addEventListener('click', function() {  // Line: Add click handler
                    selectSlot(this);  // Line: Call selection function
                });
            });

            // Set default date to today
            document.getElementById('bookingDate').value = new Date().toISOString().split('T')[0];  // Line: Set date input to today

            // Load initial slot availability based on default date/time
            loadSlots();  // Line: Fetch booking data
        });

        // =====================================================================
        // GLOBAL VARIABLES
        // =====================================================================
        
        let selectedSlot = null;  // Line: Currently selected slot number
        const slots = document.querySelectorAll('.parking-slot');  // Line: All slot elements
        const isStudent = <?= $is_student ? 'true' : 'false' ?>;  // Line: Boolean from PHP

        // =====================================================================
        // FUNCTION: getTime(hourId, ampmId)
        // =====================================================================
        /**
         * Convert hour and AM/PM select values to 24-hour time string
         * 
         * @param {string} hourId - ID of hour select element
         * @param {string} ampmId - ID of AM/PM select element
         * @returns {string} - Time in "HH:00" format
         */
        function getTime(hourId, ampmId) {
            let h = parseInt(document.getElementById(hourId).value);  // Line: Get hour value
            const ap = document.getElementById(ampmId).value;  // Line: Get AM/PM value
            if (ap === 'PM' && h < 12) h += 12;  // Line: Convert PM to 24-hour
            if (ap === 'AM' && h === 12) h = 0;  // Line: Handle midnight
            return h.toString().padStart(2, '0') + ':00';  // Line: Format as HH:00
        }

        // =====================================================================
        // FUNCTION: loadSlots()
        // =====================================================================
        /**
         * Fetch booking data from API and update slot display
         * Marks booked slots with red color and 'booked' class
         * 
         * FLOW:
         * 1. Get selected date and time range
         * 2. Clear previous selection
         * 3. Fetch booked slots from get_slots.php API
         * 4. Mark booked slots in the SVG
         */
        function loadSlots() {
            const date = document.getElementById('bookingDate').value;  // Line: Get selected date
            const start = getTime('startHour', 'startAmPm');  // Line: Get start time
            const end = getTime('endHour', 'endAmPm');  // Line: Get end time

            if (!date) {  // Line: Validate date
                alert('Select a date');  // Line: Show alert
                return;  // Line: Exit function
            }

            // Reset slot classes (preserve area-closed and unavailable states)
            slots.forEach(s => {  // Line: Loop through all slots
                // Only remove booked/selected classes, preserve area-closed, space-unavailable, unavailable
                if (!s.classList.contains('area-closed') && 
                    !s.classList.contains('space-unavailable') && 
                    !s.classList.contains('unavailable')) {  // Line: Check if slot is not permanently unavailable
                    s.classList.remove('booked', 'selected');  // Line: Remove temporary classes
                }
            });

            clearSelection();  // Line: Clear any current selection

            // Fetch booked slots from API
            fetch(`../booking/api/get_slots.php?date=${date}&start=${start}&end=${end}`)  // Line: API call
                .then(r => r.json())  // Line: Parse JSON
                .then(data => {
                    // Mark each booked slot
                    (data.booked || []).forEach(id => {  // Line: Loop through booked IDs
                        const el = document.getElementById('slot-' + id);  // Line: Get slot element
                        // Only mark as booked if not already permanently unavailable
                        if (el && !el.classList.contains('area-closed') && 
                            !el.classList.contains('space-unavailable') && 
                            !el.classList.contains('unavailable')) {  // Line: Check slot state
                            el.classList.add('booked');  // Line: Add booked class
                            el.setAttribute('fill', '#f56565');  // Line: Set fill to red
                        }
                    });
                });
        }

        // =====================================================================
        // FUNCTION: selectSlot(el)
        // =====================================================================
        /**
         * Handle click on a parking slot
         * Validates slot is selectable and delegates to role-specific handler
         * 
         * @param {HTMLElement} el - The clicked slot element
         */
        function selectSlot(el) {
            // Students can't select booked slots, but admin/staff can
            if (isStudent && el.classList.contains('booked')) return;  // Line: Block students from booked
            if (el.classList.contains('unavailable')) return;  // Line: Block unavailable slots
            if (el.classList.contains('area-closed')) return;
            if (el.classList.contains('space-unavailable')) return;

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

            document.getElementById('confirmBtn').disabled = true;

            fetch('../../modules/booking/api/create_booking.php', {
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
                        setTimeout(() => location.href = '../../modules/booking/index.php', 1000);
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
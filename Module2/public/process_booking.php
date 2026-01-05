<?php

/**
 * Process Booking - Module 2
 * This file handles the AJAX request when a user submits a parking booking
 * It validates the data, creates the booking, and returns a JSON response with QR info
 */

// Include session configuration file for user authentication
require_once '../../config/session.php';  // Line 9: Load session management functions

// Include database configuration file for database connection
require_once '../../config/database.php';  // Line 12: Load database helper functions

// Set the response content type to JSON format
header('Content-Type: application/json');  // Line 15: Tell browser we're returning JSON data

// Check if user is logged in by verifying session variable exists
if (!isset($_SESSION['user_id'])) {  // Line 18: Check if user_id exists in session
    // Return error message if user is not logged in
    echo json_encode(['success' => false, 'message' => 'Please login first']);  // Line 20: Send JSON error response
    exit;  // Line 21: Stop script execution
}

// Store the user ID from session for later use
$user_id = $_SESSION['user_id'];  // Line 25: Get logged in user's ID

// Verify this is a POST request (form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  // Line 28: Check request method
    // Return error if not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);  // Line 30: Send error for wrong method
    exit;  // Line 31: Stop script execution
}

// Get and sanitize form data from POST request
$vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;  // Line 35: Get vehicle ID as integer
$slot_number = isset($_POST['slot_number']) ? (int)$_POST['slot_number'] : 0;  // Line 36: Get slot number as integer
$area_code = isset($_POST['area_code']) ? $_POST['area_code'] : '';  // Line 37: Get area code (A, B, C, D, or E)
$booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';  // Line 38: Get booking date
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';  // Line 39: Get start time
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';  // Line 40: Get end time

// Validate that all required fields have values
if (
    $vehicle_id <= 0 || $slot_number <= 0 || empty($area_code) ||  // Line 43: Check vehicle and slot
    empty($booking_date) || empty($start_time) || empty($end_time)
) {  // Line 44: Check date and time
    // Return error if any field is missing
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);  // Line 46: Send validation error
    exit;  // Line 47: Stop script execution
}

// Establish database connection using helper function
$conn = getDBConnection();  // Line 51: Connect to MySQL database
if (!$conn) {  // Line 52: Check if connection was successful
    // Return error if database connection failed
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);  // Line 54: Send connection error
    exit;  // Line 55: Stop script execution
}

// SQL query to verify vehicle belongs to current user and is approved
$sql = "SELECT v.vehicle_id, v.vehicle_model, v.license_plate  -- Line 59: Select vehicle details
        FROM Vehicle v  -- Line 60: From Vehicle table
        WHERE v.vehicle_id = ? AND v.user_id = ? AND v.status = 'approved'";  // Line 61: Match ID, user, and status
$stmt = $conn->prepare($sql);  // Line 62: Prepare SQL statement to prevent injection
$stmt->bind_param("ii", $vehicle_id, $user_id);  // Line 63: Bind two integer parameters
$stmt->execute();  // Line 64: Execute the prepared statement
$result = $stmt->get_result();  // Line 65: Get the query results

// Check if vehicle was found
if ($result->num_rows === 0) {  // Line 68: No matching vehicle found
    // Return error for invalid vehicle
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle selection']);  // Line 70: Send vehicle error
    $stmt->close();  // Line 71: Close the statement
    $conn->close();  // Line 72: Close database connection
    exit;  // Line 73: Stop script execution
}

// Fetch the vehicle data as associative array
$vehicle = $result->fetch_assoc();  // Line 77: Get vehicle row as array
$stmt->close();  // Line 78: Close the statement

// Create space number string in format "A-01" or "B-15"
$space_number = $area_code . '-' . str_pad($slot_number, 2, '0', STR_PAD_LEFT);  // Line 81: Format: Area-Number

// First, verify the area exists in database (admin must create it first)
// Area names can be stored as "Area A" or just "A"
$sql = "SELECT area_id FROM ParkingArea WHERE area_name = ? OR area_name = ?";
$area_name_full = 'Area ' . $area_code; // Format: "Area A"
$area_name_short = $area_code; // Format: "A"
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $area_name_full, $area_name_short);
$stmt->execute();
$area_result = $stmt->get_result();

if ($area_result->num_rows === 0) {
    // Area doesn't exist in database - cannot book
    echo json_encode(['success' => false, 'message' => 'This parking area is not available. Please contact administrator.']);
    $stmt->close();
    $conn->close();
    exit;
}
$area_row = $area_result->fetch_assoc();
$area_id = $area_row['area_id'];
$stmt->close();

// SQL to check if parking space exists in database
// Space can be stored as "A-01", "A-1", or just "1"
$space_number_formatted = $area_code . '-' . str_pad($slot_number, 2, '0', STR_PAD_LEFT); // A-01
$space_number_short = $area_code . '-' . $slot_number; // A-1
$space_number_plain = (string)$slot_number; // 1

$sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ? OR space_number = ? OR space_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $space_number_formatted, $space_number_short, $space_number_plain);
$stmt->execute();
$result = $stmt->get_result();

// If parking space doesn't exist, reject the booking
if ($result->num_rows === 0) {
    // Space doesn't exist in database - cannot book
    echo json_encode(['success' => false, 'message' => 'This parking space is not available. Please contact administrator.']);
    $stmt->close();
    $conn->close();
    exit;
}

// Space exists, get its ID
$space_row = $result->fetch_assoc();
$space_id = $space_row['Space_id'];
$stmt->close();

// Format booking start and end datetime strings
$booking_start = $booking_date . ' ' . $start_time . ':00';  // Line 131: Combine date and start time
$booking_end = $booking_date . ' ' . $end_time . ':00';  // Line 132: Combine date and end time

// SQL to check for overlapping bookings on same space
$sql = "SELECT booking_id FROM Booking  -- Line 135: Select any conflicting booking
        WHERE Space_id = ?  -- Line 136: Match the space
        AND booking_start < ?  -- Line 137: Existing booking starts before new ends
        AND booking_end > ?";  // Line 138: Existing booking ends after new starts
$stmt = $conn->prepare($sql);  // Line 139: Prepare overlap check
$stmt->bind_param("iss", $space_id, $booking_end, $booking_start);  // Line 140: Bind parameters
$stmt->execute();  // Line 141: Execute query
$result = $stmt->get_result();  // Line 142: Get results

// Check if there's a conflicting booking
if ($result->num_rows > 0) {  // Line 145: Overlap found
    // Return error for already booked slot
    echo json_encode(['success' => false, 'message' => 'This slot is already booked for the selected time']);  // Line 147
    $stmt->close();  // Line 148: Close statement
    $conn->close();  // Line 149: Close connection
    exit;  // Line 150: Stop execution
}
$stmt->close();  // Line 152: Close overlap check statement

// Generate unique QR code string for this booking
$booking_qr_code = 'MAWGIFI-' . strtoupper(uniqid()) . '-' . $slot_number . $area_code;  // Line 155: Create unique code

// SQL to insert new booking record
$sql = "INSERT INTO Booking (vehicle_id, Space_id, booking_start, booking_end, booking_qr_code)  -- Line 158
        VALUES (?, ?, ?, ?, ?)";  // Line 159: Five values to insert
$stmt = $conn->prepare($sql);  // Line 160: Prepare insert statement
$stmt->bind_param("iisss", $vehicle_id, $space_id, $booking_start, $booking_end, $booking_qr_code);  // Line 161: Bind all params

// Execute the booking insert
if ($stmt->execute()) {  // Line 164: Try to insert booking
    $booking_id = $conn->insert_id;  // Line 165: Get the new booking's ID

    // Determine if server is using HTTPS or HTTP
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';  // Line 168: Check protocol
    $host = $_SERVER['HTTP_HOST'];  // Line 169: Get server hostname
    // Build the full URL for QR code verification page
    $qr_url = $scheme . '://' . $host . '/mawgifi_system/modules/booking/verify.php?code=' . urlencode($booking_qr_code);  // Line 171

    // Format slot display text
    $slot_display = $slot_number . $area_code . ' (Area ' . $area_code . ')';  // Line 174: e.g., "15B (Area B)"
    // Format vehicle info text
    $vehicle_info = $vehicle['vehicle_model'] . ' - ' . $vehicle['license_plate'];  // Line 176: e.g., "Toyota - ABC123"

    // Return success response with all booking details as JSON
    echo json_encode([  // Line 179: Start JSON response
        'success' => true,  // Line 180: Indicate success
        'message' => 'Booking created successfully',  // Line 181: Success message
        'booking_id' => $booking_id,  // Line 182: New booking ID
        'slot_display' => $slot_display,  // Line 183: Formatted slot text
        'vehicle_info' => $vehicle_info,  // Line 184: Formatted vehicle text
        'booking_date' => date('F j, Y', strtotime($booking_date)),  // Line 185: Formatted date
        'start_time' => date('g:i A', strtotime($start_time)),  // Line 186: Formatted start time
        'end_time' => date('g:i A', strtotime($end_time)),  // Line 187: Formatted end time
        'qr_code' => $booking_qr_code,  // Line 188: The QR code string
        'qr_url' => $qr_url  // Line 189: URL to encode in QR
    ]);
} else {
    // Booking insert failed, return error with database message
    echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $conn->error]);  // Line 193
}

$stmt->close();  // Line 196: Close the statement
$conn->close();  // Line 197: Close database connection

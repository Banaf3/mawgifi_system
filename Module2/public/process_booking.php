<?php
/**
 * =============================================================================
 * PROCESS BOOKING - MODULE 2 PUBLIC
 * =============================================================================
 * 
 * PURPOSE:
 * This file handles AJAX requests when a user submits a parking booking.
 * It validates the booking data, checks for conflicts, creates the booking,
 * and returns a JSON response with QR code information.
 * 
 * FLOW:
 * 1. Verify user is logged in (session check)
 * 2. Verify request is POST method
 * 3. Validate and sanitize all form inputs
 * 4. Verify vehicle belongs to current user and is approved
 * 5. Verify parking area exists in database
 * 6. Verify parking space exists in database
 * 7. Check for overlapping bookings (time conflict)
 * 8. Generate unique QR code
 * 9. Insert booking record
 * 10. Return success response with booking details
 * 
 * INPUT (POST):
 * - vehicle_id: ID of user's vehicle
 * - slot_number: The slot number (1-100)
 * - area_code: Area letter (A, B, C, D, E)
 * - booking_date: Date of booking (YYYY-MM-DD)
 * - start_time: Start time (HH:MM)
 * - end_time: End time (HH:MM)
 * 
 * OUTPUT (JSON):
 * - success: boolean
 * - message: status message
 * - booking_id, slot_display, vehicle_info, qr_code, qr_url (on success)
 * 
 * =============================================================================
 */

// Include session configuration file for user authentication
// This file provides session_start() and authentication helpers
require_once '../../config/session.php';  // Line 38: Load session management functions

// Include database configuration file for database connection
// This file provides getDBConnection() function
require_once '../../config/database.php';  // Line 42: Load database helper functions

// Set the response content type to JSON format
// This tells the browser/client that we're returning JSON data
header('Content-Type: application/json');  // Line 46: Set HTTP header for JSON response

// =============================================================================
// STEP 1: AUTHENTICATION CHECK
// =============================================================================
// Verify user is logged in by checking if user_id exists in session

if (!isset($_SESSION['user_id'])) {  // Line 53: Check if user_id session variable exists
    // User is not logged in - return error response
    echo json_encode(['success' => false, 'message' => 'Please login first']);  // Line 55: Return JSON error
    exit;  // Line 56: Stop script execution immediately
}

// Store the user ID from session for use in queries
$user_id = $_SESSION['user_id'];  // Line 60: Extract user ID from session

// =============================================================================
// STEP 2: REQUEST METHOD VALIDATION
// =============================================================================
// Only accept POST requests (form submissions)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {  // Line 67: Check if request method is POST
    // Not a POST request - reject it
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);  // Line 69: Return error
    exit;  // Line 70: Stop execution
}

// =============================================================================
// STEP 3: INPUT SANITIZATION AND VALIDATION
// =============================================================================
// Get and clean all form data from POST request

// Get vehicle_id - cast to integer for safety (prevents SQL injection)
// If not set, default to 0 (which will fail validation)
$vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;  // Line 80: Sanitize vehicle ID

// Get slot_number - cast to integer
$slot_number = isset($_POST['slot_number']) ? (int)$_POST['slot_number'] : 0;  // Line 83: Sanitize slot number

// Get area_code - keep as string (A, B, C, D, E)
$area_code = isset($_POST['area_code']) ? $_POST['area_code'] : '';  // Line 86: Get area code

// Get booking_date - string in YYYY-MM-DD format
$booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';  // Line 89: Get booking date

// Get start_time - string in HH:MM format
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';  // Line 92: Get start time

// Get end_time - string in HH:MM format
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';  // Line 95: Get end time

// Validate that ALL required fields have valid values
if (
    $vehicle_id <= 0 || $slot_number <= 0 || empty($area_code) ||  // Line 99: Check numeric fields
    empty($booking_date) || empty($start_time) || empty($end_time)  // Line 100: Check string fields
) {
    // One or more fields are invalid/missing
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);  // Line 103: Return validation error
    exit;  // Line 104: Stop execution
}

// =============================================================================
// STEP 4: DATABASE CONNECTION
// =============================================================================

$conn = getDBConnection();  // Line 111: Get MySQLi database connection

if (!$conn) {  // Line 113: Check if connection failed
    // Database connection failed
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);  // Line 115: Return error
    exit;  // Line 116: Stop execution
}

// =============================================================================
// STEP 5: VERIFY VEHICLE OWNERSHIP AND APPROVAL
// =============================================================================
// Check that the vehicle belongs to the logged-in user AND is approved

$sql = "SELECT v.vehicle_id, v.vehicle_model, v.license_plate  -- Line 124: Select vehicle details
        FROM Vehicle v                                          -- Line 125: From Vehicle table
        WHERE v.vehicle_id = ? AND v.user_id = ? AND v.status = 'approved'";  // Line 126: Match ID, user, and approved status

$stmt = $conn->prepare($sql);  // Line 128: Prepare statement to prevent SQL injection
$stmt->bind_param("ii", $vehicle_id, $user_id);  // Line 129: Bind two integer parameters ("ii")
$stmt->execute();  // Line 130: Execute the prepared query
$result = $stmt->get_result();  // Line 131: Get result set

// Check if a matching vehicle was found
if ($result->num_rows === 0) {  // Line 134: No matching vehicle
    // Vehicle not found, doesn't belong to user, or not approved
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle selection']);  // Line 136: Return error
    $stmt->close();  // Line 137: Close statement to free resources
    $conn->close();  // Line 138: Close database connection
    exit;  // Line 139: Stop execution
}

// Fetch the vehicle data as associative array
$vehicle = $result->fetch_assoc();  // Line 143: Get vehicle row as array
$stmt->close();  // Line 144: Close statement

// =============================================================================
// STEP 6: CREATE SPACE NUMBER AND VERIFY AREA EXISTS
// =============================================================================

// Create space number string in format "A-01" or "B-15"
// str_pad adds leading zeros: 1 becomes "01", 15 stays "15"
$space_number = $area_code . '-' . str_pad($slot_number, 2, '0', STR_PAD_LEFT);  // Line 152: Format space number

// Verify the area exists in database
// Area names can be "Area A" or just "A" depending on how they were created
$sql = "SELECT area_id FROM ParkingArea WHERE area_name = ? OR area_name = ?";  // Line 156: SQL to find area
$area_name_full = 'Area ' . $area_code;  // Line 157: Full format: "Area A"
$area_name_short = $area_code;  // Line 158: Short format: "A"

$stmt = $conn->prepare($sql);  // Line 160: Prepare statement
$stmt->bind_param("ss", $area_name_full, $area_name_short);  // Line 161: Bind both name formats
$stmt->execute();  // Line 162: Execute query
$area_result = $stmt->get_result();  // Line 163: Get results

if ($area_result->num_rows === 0) {  // Line 165: Area not found
    // Area doesn't exist - admin needs to create it first
    echo json_encode(['success' => false, 'message' => 'This parking area is not available. Please contact administrator.']);  // Line 167: Return error
    $stmt->close();  // Line 168: Close statement
    $conn->close();  // Line 169: Close connection
    exit;  // Line 170: Stop execution
}

// Get the area ID for later use
$area_row = $area_result->fetch_assoc();  // Line 174: Fetch area row
$area_id = $area_row['area_id'];  // Line 175: Extract area_id
$stmt->close();  // Line 176: Close statement

// =============================================================================
// STEP 7: VERIFY PARKING SPACE EXISTS
// =============================================================================
// Check if the specific space exists in the database
// Space can be stored in different formats

$space_number_formatted = $area_code . '-' . str_pad($slot_number, 2, '0', STR_PAD_LEFT);  // Line 184: "A-01" format
$space_number_short = $area_code . '-' . $slot_number;  // Line 185: "A-1" format
$space_number_plain = (string)$slot_number;  // Line 186: "1" format (just number)

// Try to find space by any of these formats
$sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ? OR space_number = ? OR space_number = ?";  // Line 189: SQL with 3 options
$stmt = $conn->prepare($sql);  // Line 190: Prepare statement
$stmt->bind_param("sss", $space_number_formatted, $space_number_short, $space_number_plain);  // Line 191: Bind all three formats
$stmt->execute();  // Line 192: Execute query
$result = $stmt->get_result();  // Line 193: Get results

if ($result->num_rows === 0) {  // Line 195: Space not found
    // Space doesn't exist - admin needs to create it
    echo json_encode(['success' => false, 'message' => 'This parking space is not available. Please contact administrator.']);  // Line 197: Return error
    $stmt->close();  // Line 198: Close statement
    $conn->close();  // Line 199: Close connection
    exit;  // Line 200: Stop execution
}

// Space exists - get its ID
$space_row = $result->fetch_assoc();  // Line 204: Fetch space row
$space_id = $space_row['Space_id'];  // Line 205: Extract Space_id
$stmt->close();  // Line 206: Close statement

// =============================================================================
// STEP 8: CHECK FOR OVERLAPPING BOOKINGS
// =============================================================================
// Make sure no one else has booked this space during the requested time

// Format complete datetime strings for comparison
// Format: "2024-01-15 14:00:00"
$booking_start = $booking_date . ' ' . $start_time . ':00';  // Line 215: Combine date and start time
$booking_end = $booking_date . ' ' . $end_time . ':00';  // Line 216: Combine date and end time

// SQL to find conflicting bookings
// Two time ranges overlap if: existing.start < new.end AND existing.end > new.start
$sql = "SELECT booking_id FROM Booking         -- Line 220: Select any conflicting booking
        WHERE Space_id = ?                      -- Line 221: Same parking space
        AND booking_start < ?                   -- Line 222: Existing booking starts before new one ends
        AND booking_end > ?";                   // Line 223: Existing booking ends after new one starts

$stmt = $conn->prepare($sql);  // Line 225: Prepare overlap check
$stmt->bind_param("iss", $space_id, $booking_end, $booking_start);  // Line 226: Bind: int, string, string
$stmt->execute();  // Line 227: Execute query
$result = $stmt->get_result();  // Line 228: Get results

if ($result->num_rows > 0) {  // Line 230: Found overlapping booking
    // Time slot is already booked
    echo json_encode(['success' => false, 'message' => 'This slot is already booked for the selected time']);  // Line 232: Return conflict error
    $stmt->close();  // Line 233: Close statement
    $conn->close();  // Line 234: Close connection
    exit;  // Line 235: Stop execution
}
$stmt->close();  // Line 237: Close overlap check statement

// =============================================================================
// STEP 9: GENERATE UNIQUE QR CODE
// =============================================================================
// Create a unique identifier for this booking

// Format: MAWGIFI-UNIQUE_ID-SLOT_AREA
// uniqid() generates unique ID based on current time in microseconds
// strtoupper() converts to uppercase for consistency
$booking_qr_code = 'MAWGIFI-' . strtoupper(uniqid()) . '-' . $slot_number . $area_code;  // Line 247: Generate QR code string

// =============================================================================
// STEP 10: INSERT BOOKING RECORD
// =============================================================================

$sql = "INSERT INTO Booking (vehicle_id, Space_id, booking_start, booking_end, booking_qr_code)  -- Line 253: INSERT statement
        VALUES (?, ?, ?, ?, ?)";  // Line 254: Five placeholder values

$stmt = $conn->prepare($sql);  // Line 256: Prepare INSERT statement
// Bind parameters: int, int, string, string, string
$stmt->bind_param("iisss", $vehicle_id, $space_id, $booking_start, $booking_end, $booking_qr_code);  // Line 258: Bind all values

// Execute the insert and check result
if ($stmt->execute()) {  // Line 261: Try to insert booking
    // SUCCESS - booking created
    $booking_id = $conn->insert_id;  // Line 263: Get the auto-generated booking ID

    // Build QR code URL for verification page
    // Determine if using HTTPS or HTTP
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';  // Line 267: Check for HTTPS
    $host = $_SERVER['HTTP_HOST'];  // Line 268: Get server hostname (e.g., localhost)
    
    // Build complete verification URL that will be encoded in QR
    $qr_url = $scheme . '://' . $host . '/mawgifi_system/modules/booking/verify.php?code=' . urlencode($booking_qr_code);  // Line 271: Build full URL

    // Format slot display text for user-friendly output
    $slot_display = $slot_number . $area_code . ' (Area ' . $area_code . ')';  // Line 274: e.g., "15B (Area B)"
    
    // Format vehicle info for display
    $vehicle_info = $vehicle['vehicle_model'] . ' - ' . $vehicle['license_plate'];  // Line 277: e.g., "Toyota Camry - ABC123"

    // Return comprehensive success response with all booking details
    echo json_encode([  // Line 280: Start JSON response
        'success' => true,  // Line 281: Success flag
        'message' => 'Booking created successfully',  // Line 282: Success message
        'booking_id' => $booking_id,  // Line 283: New booking ID
        'slot_display' => $slot_display,  // Line 284: Formatted slot text
        'vehicle_info' => $vehicle_info,  // Line 285: Formatted vehicle text
        'booking_date' => date('F j, Y', strtotime($booking_date)),  // Line 286: Formatted date (e.g., "January 15, 2024")
        'start_time' => date('g:i A', strtotime($start_time)),  // Line 287: Formatted time (e.g., "2:00 PM")
        'end_time' => date('g:i A', strtotime($end_time)),  // Line 288: Formatted end time
        'qr_code' => $booking_qr_code,  // Line 289: The QR code string
        'qr_url' => $qr_url  // Line 290: URL to encode in QR image
    ]);
} else {
    // FAILURE - database insert failed
    echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $conn->error]);  // Line 294: Return error with DB message
}

// =============================================================================
// CLEANUP
// =============================================================================

$stmt->close();  // Line 300: Close the prepared statement
$conn->close();  // Line 301: Close database connection

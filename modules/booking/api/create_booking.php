<?php
// ============================================================================
// create_booking.php - API endpoint to create a new parking booking
// ============================================================================

// Prevent HTML error output interfering with JSON response
ini_set('display_errors', 0);
ob_start();

require_once '../../../config/session.php';
require_once '../../../config/database.php';

// ENSURE JSON RESPONSE
header('Content-Type: application/json; charset=utf-8');

// Helper to send JSON response and exit
function sendJson($data) {
    ob_end_clean(); // Clean buffer to ensure only JSON is output
    echo json_encode($data);
    exit;
}

try {
    // ============================================================================
    // Authentication Check
    // ============================================================================
    if (!isLoggedIn()) {
        sendJson(['success' => false, 'message' => 'Not logged in']);
    }

    // ============================================================================
    // Parse and Validate Input Data
    // ============================================================================
    // Check if input is empty
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        sendJson(['success' => false, 'message' => 'No input data received']);
    }

    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJson(['success' => false, 'message' => 'Invalid JSON input']);
    }

    $vehicle_id = (int) ($data['vehicle_id'] ?? 0);
    $slot_id = $data['slot_id'] ?? '';
    $date = $data['date'] ?? '';
    $start_time = $data['start_time'] ?? '';
    $end_time = $data['end_time'] ?? '';

    // Check all required fields are present
    if (!$vehicle_id || !$slot_id || !$date || !$start_time || !$end_time) {
        sendJson(['success' => false, 'message' => 'Missing required fields']);
    }
    
    $user_id = getCurrentUserId();
    $conn = getDBConnection();

    // ============================================================================
    // Vehicle Ownership and Status Verification
    // ============================================================================
    // First check if vehicle exists and belongs to user (regardless of status)
    $stmt = $conn->prepare("SELECT status FROM Vehicle WHERE vehicle_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        sendJson(['success' => false, 'message' => 'Vehicle not found or does not belong to you']);
    }

    $vehicle = $result->fetch_assoc();
    $stmt->close();

    // Check if vehicle is approved
    if ($vehicle['status'] !== 'approved') {
        $conn->close();
        sendJson(['success' => false, 'message' => 'Vehicle is not approved yet. Status: ' . $vehicle['status']]);
    }

    // ============================================================================
    // Get or Create Parking Space Record
    // ============================================================================
    // Check if space already exists in database
    $stmt = $conn->prepare("SELECT Space_id FROM ParkingSpace WHERE space_number = ?");
    $stmt->bind_param("s", $slot_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Use existing space
        $space_id = $result->fetch_assoc()['Space_id'];
    } else {
        // Determine correct area_id based on slot number
        $slot_num = (int)$slot_id;
        $area_name = 'Area A'; // Default
        
        if ($slot_num >= 1 && $slot_num <= 14) $area_name = 'Area A';
        elseif ($slot_num >= 15 && $slot_num <= 44) $area_name = 'Area B';
        elseif ($slot_num >= 45 && $slot_num <= 65) $area_name = 'Area C';
        elseif ($slot_num >= 66 && $slot_num <= 86) $area_name = 'Area D';
        elseif ($slot_num >= 87 && $slot_num <= 100) $area_name = 'Area E';

        // Find the database ID for this area name
        $stmt_area = $conn->prepare("SELECT area_id FROM ParkingArea WHERE area_name = ? LIMIT 1");
        $stmt_area->bind_param("s", $area_name);
        $stmt_area->execute();
        $res_area = $stmt_area->get_result();
        
        if ($res_area->num_rows > 0) {
            $area_id = $res_area->fetch_assoc()['area_id'];
        } else {
            // Fallback: get any valid area_id to prevent FK failure
            $res_any = $conn->query("SELECT area_id FROM ParkingArea LIMIT 1");
            if ($row_any = $res_any->fetch_assoc()) {
                $area_id = $row_any['area_id'];
            } else {
                 sendJson(['success' => false, 'message' => 'System Error: No parking areas defined in database']);
            }
        }
        $stmt_area->close();

        // Create new space with the correct area_id
        $stmt2 = $conn->prepare("INSERT INTO ParkingSpace (area_id, space_number) VALUES (?, ?)");
        $stmt2->bind_param("is", $area_id, $slot_id);
        if (!$stmt2->execute()) {
            $err = $stmt2->error;
            $stmt->close();
            $stmt2->close();
            $conn->close();
            sendJson(['success' => false, 'message' => 'Failed to create parking space: ' . $err]);
        }
        $space_id = $conn->insert_id;
        $stmt2->close();
    }
    $stmt->close();

    // ============================================================================
    // Format and Validate Booking Times
    // ============================================================================
    $booking_start = $date . ' ' . $start_time;
    $booking_end = $date . ' ' . $end_time;

    // Handle overnight bookings (when end time is earlier than start time)
    if (strtotime($booking_end) <= strtotime($booking_start)) {
        $booking_end = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $end_time;
    }

    // ============================================================================
    // Validation: Prevent Booking in the Past
    // ============================================================================
    if (strtotime($booking_start) < time()) {
        $conn->close();
        sendJson(['success' => false, 'message' => 'Cannot book for a past time.']);
    }

    // ============================================================================
    // Check for Booking Conflicts
    // ============================================================================
    require_once 'utils.php';
    if (checkBookingConflict($conn, $space_id, $booking_start, $booking_end)) {
        $conn->close();
        sendJson(['success' => false, 'message' => 'Slot already booked for this time']);
    }

    // ============================================================================
    // Create the Booking Record
    // ============================================================================
    // Generate QR code URL for the booking
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Gets /mawgifi_system
    $qr_url = $protocol . '://' . $host . $base_path . '/modules/booking/scan.php?slot=' . $slot_id;

    // Insert booking record into database
    $stmt = $conn->prepare("INSERT INTO Booking (vehicle_id, Space_id, booking_start, booking_end, booking_qr_code, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iisss", $vehicle_id, $space_id, $booking_start, $booking_end, $qr_url);

    if ($stmt->execute()) {
        // Success - return booking ID and QR code URL
        $booking_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        sendJson(['success' => true, 'booking_id' => $booking_id, 'qr_code' => $qr_url]);
    } else {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        sendJson(['success' => false, 'message' => 'Failed to create booking: ' . $err]);
    }

} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Server Exception: ' . $e->getMessage()]);
} catch (Error $e) {
    sendJson(['success' => false, 'message' => 'Server Fatal Error: ' . $e->getMessage()]);
}

?>

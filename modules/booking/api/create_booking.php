<?php
require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$vehicle_id = (int) ($data['vehicle_id'] ?? 0);
$slot_id = $data['slot_id'] ?? '';
$date = $data['date'] ?? '';
$start_time = $data['start_time'] ?? '';
$end_time = $data['end_time'] ?? '';

if (!$vehicle_id || !$slot_id || !$date || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Verify vehicle belongs to user
$stmt = $conn->prepare("SELECT 1 FROM Vehicle WHERE vehicle_id = ? AND user_id = ? AND status = 'approved'");
$stmt->bind_param("ii", $vehicle_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle']);
    exit;
}
$stmt->close();

// Get or create parking space
$stmt = $conn->prepare("SELECT Space_id FROM ParkingSpace WHERE space_number = ?");
$stmt->bind_param("s", $slot_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $space_id = $result->fetch_assoc()['Space_id'];
} else {
    $stmt2 = $conn->prepare("INSERT INTO ParkingSpace (area_id, space_number) VALUES (1, ?)");
    $stmt2->bind_param("s", $slot_id);
    $stmt2->execute();
    $space_id = $conn->insert_id;
    $stmt2->close();
}
$stmt->close();

// Format booking times
$booking_start = $date . ' ' . $start_time;
$booking_end = $date . ' ' . $end_time;
if (strtotime($booking_end) <= strtotime($booking_start)) {
    $booking_end = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $end_time;
}

// Check for conflicts
require_once 'utils.php';
if (checkBookingConflict($conn, $space_id, $booking_start, $booking_end)) {
    echo json_encode(['success' => false, 'message' => 'Slot already booked']);
    exit;
}

// Create booking
$qr_url = 'http://localhost/mawgifi_system/modules/booking/scan.php?slot=' . $slot_id;
$stmt = $conn->prepare("INSERT INTO Booking (vehicle_id, Space_id, booking_start, booking_end, booking_qr_code, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("iisss", $vehicle_id, $space_id, $booking_start, $booking_end, $qr_url);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'booking_id' => $conn->insert_id, 'qr_code' => $qr_url]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
}

$stmt->close();
$conn->close();

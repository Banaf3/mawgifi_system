<?php
require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = (int) ($data['booking_id'] ?? 0);
$date = $data['date'] ?? '';
$start_time = $data['start_time'] ?? '';
$end_time = $data['end_time'] ?? '';

if (!$booking_id || !$date || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Verify booking belongs to user and is pending
$stmt = $conn->prepare(
    "SELECT b.Space_id, b.booking_start, b.status FROM Booking b
     JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
     WHERE b.booking_id = ? AND v.user_id = ?"
);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}
if ($booking['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Can only update pending bookings']);
    exit;
}
if (strtotime($booking['booking_start']) <= time()) {
    echo json_encode(['success' => false, 'message' => 'Cannot update started booking']);
    exit;
}

// Format new times
$booking_start = $date . ' ' . $start_time . ':00';
$booking_end = $date . ' ' . $end_time . ':00';
if (strtotime($end_time) <= strtotime($start_time)) {
    $booking_end = date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $end_time . ':00';
}

// Check for conflicts
$space_id = $booking['Space_id'];
require_once 'utils.php';
if (checkBookingConflict($conn, $space_id, $booking_start, $booking_end, $booking_id)) {
    echo json_encode(['success' => false, 'message' => 'Time slot conflicts with another booking']);
    exit;
}

// Update booking
$stmt = $conn->prepare("UPDATE Booking SET booking_start = ?, booking_end = ? WHERE booking_id = ?");
$stmt->bind_param("ssi", $booking_start, $booking_end, $booking_id);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success, 'message' => $success ? 'Updated' : 'Failed to update']);

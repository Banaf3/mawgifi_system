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

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit;
}

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Verify booking belongs to user and get booking details with time check
$stmt = $conn->prepare(
    "SELECT b.status, b.booking_start, 
            NOW() as server_now,
            TIMESTAMPDIFF(MINUTE, NOW(), b.booking_start) as minutes_until_start
     FROM Booking b
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
    echo json_encode(['success' => false, 'message' => 'Already checked in']);
    exit;
}

// Check if within 1 hour (60 minutes) before booking start
// minutes_until_start > 60 means more than 1 hour before booking
$minutes_until_start = (int)$booking['minutes_until_start'];

if ($minutes_until_start > 60) {
    $time_until = $minutes_until_start - 60;
    echo json_encode(['success' => false, 'message' => "Check-in opens 1 hour before booking. Please wait $time_until more minutes."]);
    $conn->close();
    exit;
}

// Update check-in
$stmt = $conn->prepare("UPDATE Booking SET check_in_time = NOW(), status = 'checked_in' WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success, 'message' => $success ? 'Checked in successfully' : 'Check-in failed']);

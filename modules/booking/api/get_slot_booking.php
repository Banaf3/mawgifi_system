<?php
require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

$slot = $_GET['slot'] ?? '';
$date = $_GET['date'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (!$slot || !$date) { echo json_encode(['booking' => null]); exit; }

$conn = getDBConnection();

// Get Space_id
$stmt = $conn->prepare("SELECT Space_id FROM ParkingSpace WHERE space_number = ?");
$stmt->bind_param("s", $slot);
$stmt->execute();
$space = $stmt->get_result()->fetch_assoc();
if (!$space) { echo json_encode(['booking' => null]); exit; }
$space_id = $space['Space_id'];
$stmt->close();

// Find overlapping booking
$booking_start = $date . ' ' . $start;
$booking_end = $date . ' ' . $end;

$stmt = $conn->prepare(
    "SELECT b.*, u.UserName as username, v.license_plate, v.vehicle_model,
            DATE_FORMAT(b.booking_start, '%h:%i %p') as start_time,
            DATE_FORMAT(b.booking_end, '%h:%i %p') as end_time,
            DATE_FORMAT(b.check_in_time, '%h:%i %p') as check_in_time,
            DATE_FORMAT(b.check_out_time, '%h:%i %p') as check_out_time
     FROM Booking b
     JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
     JOIN User u ON v.user_id = u.user_id
     WHERE b.Space_id = ? AND b.status IN ('pending', 'checked_in')
     AND ((b.booking_start < ? AND b.booking_end > ?) OR (b.booking_start < ? AND b.booking_end > ?) OR (b.booking_start >= ? AND b.booking_end <= ?))
     LIMIT 1"
);
$stmt->bind_param("issssss", $space_id, $booking_end, $booking_start, $booking_end, $booking_start, $booking_start, $booking_end);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode(['booking' => $booking ?: null]);

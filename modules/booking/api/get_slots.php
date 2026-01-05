<?php
require_once '../../../config/session.php';
require_once '../../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$date = $_GET['date'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if (!$date || !$start || !$end) {
    echo json_encode(['booked' => []]);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT DISTINCT ps.space_number FROM Booking b
     JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
     WHERE b.status IN ('pending', 'checked_in') AND DATE(b.booking_start) = ?
     AND ((TIME(b.booking_start) < ? AND TIME(b.booking_end) > ?)
          OR (TIME(b.booking_start) < ? AND TIME(b.booking_end) > ?)
          OR (TIME(b.booking_start) >= ? AND TIME(b.booking_end) <= ?))"
);
$stmt->bind_param("sssssss", $date, $end, $start, $end, $start, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$booked = [];
while ($row = $result->fetch_assoc()) {
    $booked[] = $row['space_number'];
}

$stmt->close();
$conn->close();

echo json_encode(['booked' => $booked]);

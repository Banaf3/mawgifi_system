<?php

/**
 * Update Booking - Module 2
 * This file handles the AJAX request to update a booking's date and time
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Verify this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';

// Validate required fields
if ($booking_id <= 0 || empty($booking_date) || empty($start_time) || empty($end_time)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Validate that end time is after start time
if ($end_time <= $start_time) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

// Validate that booking is not in the past
$booking_start_dt = new DateTime($booking_date . ' ' . $start_time);
$now = new DateTime();
if ($booking_start_dt < $now) {
    echo json_encode(['success' => false, 'message' => 'Cannot book in the past']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify the booking belongs to the user and get current booking info
$sql = "SELECT b.booking_id, b.booking_start, b.booking_end, b.Space_id, b.vehicle_id
        FROM Booking b
        JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
        WHERE b.booking_id = ? AND v.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or you do not have permission to edit it']);
    $stmt->close();
    $conn->close();
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if booking is currently active (cannot edit active bookings)
$current_start = new DateTime($booking['booking_start']);
$current_end = new DateTime($booking['booking_end']);

if ($now >= $current_start && $now <= $current_end) {
    echo json_encode(['success' => false, 'message' => 'Cannot edit an active booking']);
    $conn->close();
    exit;
}

// Format new booking times
$new_booking_start = $booking_date . ' ' . $start_time . ':00';
$new_booking_end = $booking_date . ' ' . $end_time . ':00';

// Check for overlapping bookings on the same space (excluding current booking)
$sql = "SELECT booking_id FROM Booking 
        WHERE Space_id = ? 
        AND booking_id != ?
        AND booking_start < ? 
        AND booking_end > ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $booking['Space_id'], $booking_id, $new_booking_end, $new_booking_start);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot conflicts with another booking']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Update the booking
$sql = "UPDATE Booking SET booking_start = ?, booking_end = ?, updated_at = NOW() WHERE booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $new_booking_start, $new_booking_end, $booking_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Booking updated successfully',
        'new_start' => $new_booking_start,
        'new_end' => $new_booking_end
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
}

$stmt->close();
$conn->close();

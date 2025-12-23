<?php

/**
 * Delete Booking - Module 2
 * This file handles the AJAX request to delete a booking
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

// Get booking ID from POST data
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify the booking belongs to the user and is not active (in progress)
$sql = "SELECT b.booking_id, b.booking_start, b.booking_end 
        FROM Booking b
        JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
        WHERE b.booking_id = ? AND v.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or you do not have permission to delete it']);
    $stmt->close();
    $conn->close();
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if booking is currently active (cannot delete active bookings)
$now = new DateTime();
$start = new DateTime($booking['booking_start']);
$end = new DateTime($booking['booking_end']);

if ($now >= $start && $now <= $end) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete an active booking. Please wait until it ends.']);
    $conn->close();
    exit;
}

// Delete the booking
$sql = "DELETE FROM Booking WHERE booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete booking']);
}

$stmt->close();
$conn->close();

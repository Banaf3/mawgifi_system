<?php
// Shared utility functions for booking API

function checkBookingConflict($conn, $space_id, $start, $end, $exclude_booking_id = 0)
{
    // start and end should be 'Y-m-d H:i:s'
    $sql = "SELECT 1 FROM Booking WHERE Space_id = ? AND status IN ('pending', 'checked_in')
            AND ((booking_start < ? AND booking_end > ?) 
              OR (booking_start < ? AND booking_end > ?) 
              OR (booking_start >= ? AND booking_end <= ?))";

    $params = [$space_id, $end, $start, $end, $start, $start, $end];
    $types = "issssss";

    if ($exclude_booking_id > 0) {
        $sql .= " AND booking_id != ?";
        $params[] = $exclude_booking_id;
        $types .= "i";
    }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $result; // Returns true if conflict exists
}
?>
<?php
/**
 * Event Status Checker - Automatically updates area status based on event schedules
 * This should be included before displaying parking areas
 */

require_once __DIR__ . '/../config/database.php';

function updateAreaStatusBasedOnEvents() {
    $conn = getDBConnection();
    if (!$conn) {
        return;
    }

    // Check if Event table has area_id column
    $check_event = $conn->query("SHOW COLUMNS FROM Event LIKE 'area_id'");
    if (!$check_event || $check_event->num_rows == 0) {
        $conn->close();
        return;
    }

    // Check if ParkingArea has area_status column
    $check_status = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");
    if (!$check_status || $check_status->num_rows == 0) {
        $conn->close();
        return;
    }

    // Get all areas with active events (current time is within event period)
    $sql = "SELECT DISTINCT e.area_id 
            FROM Event e 
            WHERE e.area_id IS NOT NULL 
            AND NOW() >= e.event_time 
            AND NOW() <= DATE_ADD(e.event_time, INTERVAL e.duration_minutes MINUTE)";
    
    $result = $conn->query($sql);
    $active_event_areas = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $active_event_areas[] = $row['area_id'];
        }
    }

    // Get all areas that have events assigned but are not currently active
    $sql2 = "SELECT DISTINCT e.area_id 
             FROM Event e 
             WHERE e.area_id IS NOT NULL";
    
    $result2 = $conn->query($sql2);
    $all_event_areas = [];
    
    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            $all_event_areas[] = $row['area_id'];
        }
    }

    // Close areas with active events
    if (!empty($active_event_areas)) {
        $area_ids = implode(',', array_map('intval', $active_event_areas));
        $conn->query("UPDATE ParkingArea 
                      SET area_status = 'temporarily_closed' 
                      WHERE area_id IN ($area_ids)");
    }

    // Reopen areas where events have ended (only if they were closed for events)
    $areas_to_reopen = array_diff($all_event_areas, $active_event_areas);
    if (!empty($areas_to_reopen)) {
        $area_ids = implode(',', array_map('intval', $areas_to_reopen));
        $conn->query("UPDATE ParkingArea 
                      SET area_status = 'available' 
                      WHERE area_id IN ($area_ids) 
                      AND area_status = 'temporarily_closed'");
    }

    $conn->close();
}

// Auto-run when included
updateAreaStatusBasedOnEvents();

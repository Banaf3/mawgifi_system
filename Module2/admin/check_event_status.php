<?php

/**
 * =============================================================================
 * EVENT STATUS CHECKER - MODULE 2
 * =============================================================================
 * 
 * PURPOSE:
 * This file automatically synchronizes parking area statuses with event schedules.
 * When an event is active in a parking area, that area is automatically closed.
 * When the event ends, the area is automatically reopened.
 * 
 * FLOW:
 * 1. Include this file before displaying parking areas
 * 2. Function checks database schema compatibility
 * 3. Finds all areas with currently active events
 * 4. Closes those areas (sets status to 'temporarily_closed')
 * 5. Reopens areas where events have ended
 * 
 * DEPENDENCIES:
 * - database.php: Provides getDBConnection() function
 * - Event table: Must have area_id column
 * - ParkingArea table: Must have area_status column
 * 
 * =============================================================================
 */

// Include database configuration from the root config folder
// __DIR__ returns the directory of THIS file (Module2/admin/)
// /../../config/ navigates up two levels to mawgifi_system, then into config folder
require_once __DIR__ . '/../../config/database.php';  // Line 27: Load database connection helper

/**
 * FUNCTION: updateAreaStatusBasedOnEvents()
 * 
 * PURPOSE: Automatically update parking area status based on active events
 * 
 * ALGORITHM:
 * 1. Connect to database
 * 2. Verify required columns exist (schema compatibility)
 * 3. Query for areas with ACTIVE events (happening right now)
 * 4. Query for ALL areas that have ANY events assigned
 * 5. Close areas with active events
 * 6. Reopen areas where events have ended
 * 
 * RETURNS: void (no return value)
 */
function updateAreaStatusBasedOnEvents()
{  // Line 42: Define the main function

    // STEP 1: Establish database connection
    $conn = getDBConnection();  // Line 45: Call helper function to get MySQLi connection object

    // Check if connection was successful
    if (!$conn) {  // Line 48: If connection failed (returns null/false)
        return;  // Line 49: Exit function early - cannot proceed without database
    }

    // =========================================================================
    // STEP 2: SCHEMA COMPATIBILITY CHECKS
    // =========================================================================
    // We need to verify the database has the required columns before querying
    // This prevents errors if the schema hasn't been updated yet

    // Check if Event table has 'area_id' column
    // SHOW COLUMNS returns column info if it exists, empty if not
    $check_event = $conn->query("SHOW COLUMNS FROM Event LIKE 'area_id'");  // Line 60: Query to check column existence

    if (!$check_event || $check_event->num_rows == 0) {  // Line 62: If query failed OR column doesn't exist
        $conn->close();  // Line 63: Clean up database connection
        return;  // Line 64: Exit - Event table doesn't support area linking
    }

    // Check if ParkingArea table has 'area_status' column
    $check_status = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");  // Line 68: Check for status column

    if (!$check_status || $check_status->num_rows == 0) {  // Line 70: If column doesn't exist
        $conn->close();  // Line 71: Clean up connection
        return;  // Line 72: Exit - ParkingArea doesn't support status
    }

    // =========================================================================
    // STEP 3: FIND AREAS WITH CURRENTLY ACTIVE EVENTS
    // =========================================================================
    // An event is "active" when:
    // - Current time (NOW()) is >= event start time
    // - Current time (NOW()) is <= event end time (start + duration)

    $sql = "SELECT DISTINCT e.area_id   -- Line 83: Select unique area IDs only (no duplicates)
            FROM Event e                 -- Line 84: From the Event table, alias as 'e'
            WHERE e.area_id IS NOT NULL  -- Line 85: Only events that are assigned to an area
            AND NOW() >= e.event_time    -- Line 86: Event has started (current time >= start)
            AND NOW() <= DATE_ADD(e.event_time, INTERVAL e.duration_minutes MINUTE)";  // Line 87: Event hasn't ended yet

    $result = $conn->query($sql);  // Line 89: Execute the query
    $active_event_areas = [];  // Line 90: Initialize empty array to store active area IDs

    // Loop through results and collect area IDs
    if ($result) {  // Line 93: If query was successful
        while ($row = $result->fetch_assoc()) {  // Line 94: Fetch each row as associative array
            $active_event_areas[] = $row['area_id'];  // Line 95: Add area_id to our array
        }
    }

    // =========================================================================
    // STEP 4: GET ALL AREAS THAT HAVE ANY EVENTS (PAST, PRESENT, OR FUTURE)
    // =========================================================================
    // We need this to know which areas to potentially REOPEN after events end

    $sql2 = "SELECT DISTINCT e.area_id  -- Line 104: Select unique area IDs
             FROM Event e                -- Line 105: From Event table
             WHERE e.area_id IS NOT NULL";  // Line 106: Only events with assigned areas

    $result2 = $conn->query($sql2);  // Line 108: Execute query
    $all_event_areas = [];  // Line 109: Initialize array for all event areas

    if ($result2) {  // Line 111: If query successful
        while ($row = $result2->fetch_assoc()) {  // Line 112: Loop through each result row
            $all_event_areas[] = $row['area_id'];  // Line 113: Add to array
        }
    }

    // =========================================================================
    // STEP 5: CLOSE AREAS WITH ACTIVE EVENTS
    // =========================================================================
    // Set status to 'temporarily_closed' for areas where events are happening NOW

    if (!empty($active_event_areas)) {  // Line 122: Only if there are active events
        // Convert array to comma-separated string for SQL IN clause
        // array_map('intval', ...) ensures all values are integers (SQL injection prevention)
        $area_ids = implode(',', array_map('intval', $active_event_areas));  // Line 125: e.g., "1,3,5"

        // Update those areas to temporarily_closed status
        $conn->query("UPDATE ParkingArea              -- Line 128: Update ParkingArea table
                      SET area_status = 'temporarily_closed'  -- Line 129: Set status to closed
                      WHERE area_id IN ($area_ids)");  // Line 130: Only for specified area IDs
    }

    // =========================================================================
    // STEP 6: REOPEN AREAS WHERE EVENTS HAVE ENDED
    // =========================================================================
    // array_diff returns areas that are in $all_event_areas but NOT in $active_event_areas
    // These are areas that HAD events but those events are no longer active

    $areas_to_reopen = array_diff($all_event_areas, $active_event_areas);  // Line 139: Find ended event areas

    if (!empty($areas_to_reopen)) {  // Line 141: Only if there are areas to reopen
        $area_ids = implode(',', array_map('intval', $areas_to_reopen));  // Line 142: Convert to SQL-safe string

        // Only reopen if they were closed FOR EVENTS (not manually closed)
        $conn->query("UPDATE ParkingArea              -- Line 145: Update ParkingArea table
                      SET area_status = 'available'   -- Line 146: Set status back to available
                      WHERE area_id IN ($area_ids)    -- Line 147: For specified areas
                      AND area_status = 'temporarily_closed'");  // Line 148: ONLY if closed by events
    }

    $conn->close();  // Line 151: Close database connection to free resources
}

// =========================================================================
// AUTO-EXECUTE ON INCLUDE
// =========================================================================
// This line runs the function automatically when this file is included
// No need to manually call the function - just include this file

updateAreaStatusBasedOnEvents();  // Line 159: Execute the function immediately

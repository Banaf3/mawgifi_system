<?php

/**
 * Event Management API
 * Handles CRUD operations for facility events
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

header('Content-Type: application/json');

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$type = $_REQUEST['type'] ?? '';

switch ($type) {
    case 'list':
        listEvents($conn);
        break;
    case 'get':
        getEvent($conn);
        break;
    case 'create':
        createEvent($conn);
        break;
    case 'update':
        updateEvent($conn);
        break;
    case 'delete':
        deleteEvent($conn);
        break;
    case 'stats':
        getEventStats($conn);
        break;
    case 'debug':
        debugInfo($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request type']);
}

$conn->close();

/**
 * List all events
 */
function listEvents($conn)
{
    // Check if area_id column exists
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $has_area_column = $result && $result->num_rows > 0;

    $sql = "SELECT e.event_id, e.event_name, e.event_type, e.event_time, e.duration_minutes, e.RecordReport, e.created_at";
    if ($has_area_column) {
        $sql .= ", e.area_id, pa.area_name";
    }
    $sql .= " FROM Event e";
    if ($has_area_column) {
        $sql .= " LEFT JOIN ParkingArea pa ON e.area_id = pa.area_id";
    }
    $sql .= " ORDER BY e.event_time DESC";

    $result = $conn->query($sql);

    if ($result) {
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        echo json_encode(['success' => true, 'events' => $events]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch events: ' . $conn->error]);
    }
}

/**
 * Get single event by ID
 */
function getEvent($conn)
{
    $eventId = intval($_GET['id'] ?? 0);

    if ($eventId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        return;
    }

    // Check if area_id column exists
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $has_area_column = $result && $result->num_rows > 0;

    $sql = "SELECT event_id, event_name, event_type, event_time, duration_minutes, RecordReport, created_at";
    if ($has_area_column) {
        $sql .= ", area_id";
    }
    $sql .= " FROM Event WHERE event_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }

    $stmt->close();
}

/**
 * Create new event
 */
function createEvent($conn)
{
    $eventName = trim($_POST['event_name'] ?? '');
    $eventType = trim($_POST['event_type'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $durationMinutes = intval($_POST['duration_minutes'] ?? 0);
    $recordReport = trim($_POST['RecordReport'] ?? '');

    $areaId = !empty($_POST['area_id']) ? intval($_POST['area_id']) : null;

    // Validation
    if (empty($eventName)) {
        echo json_encode(['success' => false, 'message' => 'Event name is required']);
        return;
    }

    if (empty($eventType)) {
        echo json_encode(['success' => false, 'message' => 'Event type is required']);
        return;
    }

    if (empty($eventTime)) {
        echo json_encode(['success' => false, 'message' => 'Event date and time is required']);
        return;
    }

    if ($durationMinutes <= 0) {
        echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']);
        return;
    }

    // Convert datetime-local format to MySQL datetime
    $eventTime = str_replace('T', ' ', $eventTime) . ':00';

    // Check if area_id column exists
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $has_area_column = $result && $result->num_rows > 0;

    // Debug info
    $debug = [
        'raw_area_id' => $_POST['area_id'] ?? 'NOT SET',
        'parsed_area_id' => $areaId,
        'has_area_column' => $has_area_column,
        'will_insert_with_area' => ($has_area_column && $areaId)
    ];

    if ($has_area_column && $areaId) {
        $stmt = $conn->prepare("INSERT INTO Event (event_name, event_type, area_id, event_time, duration_minutes, RecordReport) 
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisis", $eventName, $eventType, $areaId, $eventTime, $durationMinutes, $recordReport);
    } else {
        $stmt = $conn->prepare("INSERT INTO Event (event_name, event_type, event_time, duration_minutes, RecordReport) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $eventName, $eventType, $eventTime, $durationMinutes, $recordReport);
    }

    if ($stmt->execute()) {
        $event_id = $conn->insert_id;

        // If area is assigned and area_status column exists, set it to temporarily_closed
        if ($areaId && $has_area_column) {
            $check_status = "SHOW COLUMNS FROM ParkingArea LIKE 'area_status'";
            $result = $conn->query($check_status);
            if ($result && $result->num_rows > 0) {
                $update_stmt = $conn->prepare("UPDATE ParkingArea SET area_status = 'temporarily_closed' WHERE area_id = ?");
                $update_stmt->bind_param("i", $areaId);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully' . ($areaId ? ' and parking area closed' : ''),
            'event_id' => $event_id,
            'debug' => $debug
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create event: ' . $stmt->error, 'debug' => $debug]);
    }

    $stmt->close();
}

/**
 * Update existing event
 */
function updateEvent($conn)
{
    $eventId = intval($_POST['event_id'] ?? 0);
    $eventName = trim($_POST['event_name'] ?? '');
    $eventType = trim($_POST['event_type'] ?? '');
    $eventTime = trim($_POST['event_time'] ?? '');
    $durationMinutes = intval($_POST['duration_minutes'] ?? 0);
    $recordReport = trim($_POST['RecordReport'] ?? '');
    $areaId = !empty($_POST['area_id']) ? intval($_POST['area_id']) : null;

    // Validation
    if ($eventId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        return;
    }

    if (empty($eventName)) {
        echo json_encode(['success' => false, 'message' => 'Event name is required']);
        return;
    }

    if (empty($eventType)) {
        echo json_encode(['success' => false, 'message' => 'Event type is required']);
        return;
    }

    if (empty($eventTime)) {
        echo json_encode(['success' => false, 'message' => 'Event date and time is required']);
        return;
    }

    if ($durationMinutes <= 0) {
        echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']);
        return;
    }

    // Get old area_id if exists
    $old_area_id = null;
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $has_area_column = $result && $result->num_rows > 0;

    if ($has_area_column) {
        $get_old = $conn->prepare("SELECT area_id FROM Event WHERE event_id = ?");
        $get_old->bind_param("i", $eventId);
        $get_old->execute();
        $old_result = $get_old->get_result();
        if ($old_row = $old_result->fetch_assoc()) {
            $old_area_id = $old_row['area_id'];
        }
        $get_old->close();
    }

    // Convert datetime-local format to MySQL datetime
    $eventTime = str_replace('T', ' ', $eventTime) . ':00';

    // Update event with or without area_id
    if ($has_area_column) {
        // Build query dynamically based on whether area_id is set
        if ($areaId !== null) {
            $stmt = $conn->prepare("UPDATE Event 
                                    SET event_name = ?, event_type = ?, area_id = ?, event_time = ?, duration_minutes = ?, RecordReport = ? 
                                    WHERE event_id = ?");
            $stmt->bind_param("ssisisi", $eventName, $eventType, $areaId, $eventTime, $durationMinutes, $recordReport, $eventId);
        } else {
            $stmt = $conn->prepare("UPDATE Event 
                                    SET event_name = ?, event_type = ?, area_id = NULL, event_time = ?, duration_minutes = ?, RecordReport = ? 
                                    WHERE event_id = ?");
            $stmt->bind_param("sssisi", $eventName, $eventType, $eventTime, $durationMinutes, $recordReport, $eventId);
        }
    } else {
        $stmt = $conn->prepare("UPDATE Event 
                                SET event_name = ?, event_type = ?, event_time = ?, duration_minutes = ?, RecordReport = ? 
                                WHERE event_id = ?");
        $stmt->bind_param("sssisi", $eventName, $eventType, $eventTime, $durationMinutes, $recordReport, $eventId);
    }

    if ($stmt->execute()) {
        // Handle area status changes
        $check_status = "SHOW COLUMNS FROM ParkingArea LIKE 'area_status'";
        $status_result = $conn->query($check_status);
        $has_status = $status_result && $status_result->num_rows > 0;

        if ($has_area_column && $has_status) {
            // Reopen old area if it was changed
            if ($old_area_id && $old_area_id != $areaId) {
                $reopen_stmt = $conn->prepare("UPDATE ParkingArea SET area_status = 'available' WHERE area_id = ?");
                $reopen_stmt->bind_param("i", $old_area_id);
                $reopen_stmt->execute();
                $reopen_stmt->close();
            }

            // Close new area
            if ($areaId) {
                $close_stmt = $conn->prepare("UPDATE ParkingArea SET area_status = 'temporarily_closed' WHERE area_id = ?");
                $close_stmt->bind_param("i", $areaId);
                $close_stmt->execute();
                $close_stmt->close();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Event updated successfully', 'affected_rows' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update event: ' . $stmt->error]);
    }

    $stmt->close();
}

/**
 * Delete event
 */
function deleteEvent($conn)
{
    $eventId = intval($_POST['event_id'] ?? 0);

    if ($eventId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        return;
    }

    // Get area_id before deleting to reopen it
    $area_id = null;
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $has_area_column = $result && $result->num_rows > 0;

    if ($has_area_column) {
        $get_area = $conn->prepare("SELECT area_id FROM Event WHERE event_id = ?");
        $get_area->bind_param("i", $eventId);
        $get_area->execute();
        $area_result = $get_area->get_result();
        if ($area_row = $area_result->fetch_assoc()) {
            $area_id = $area_row['area_id'];
        }
        $get_area->close();
    }

    $stmt = $conn->prepare("DELETE FROM Event WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Reopen the area if it was closed
            if ($area_id) {
                $check_status = "SHOW COLUMNS FROM ParkingArea LIKE 'area_status'";
                $status_result = $conn->query($check_status);
                if ($status_result && $status_result->num_rows > 0) {
                    $reopen_stmt = $conn->prepare("UPDATE ParkingArea SET area_status = 'available' WHERE area_id = ?");
                    $reopen_stmt->bind_param("i", $area_id);
                    $reopen_stmt->execute();
                    $reopen_stmt->close();
                }
            }

            echo json_encode(['success' => true, 'message' => 'Event deleted successfully' . ($area_id ? ' and area reopened' : '')]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Event not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete event: ' . $stmt->error]);
    }

    $stmt->close();
}

/**
 * Get event statistics
 */
function getEventStats($conn)
{
    $stats = [];

    // Total events
    $result = $conn->query("SELECT COUNT(*) as total FROM Event");
    $stats['total_events'] = $result->fetch_assoc()['total'];

    // Events by type
    $result = $conn->query("SELECT event_type, COUNT(*) as count FROM Event GROUP BY event_type");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['event_type']] = intval($row['count']);
    }

    // Upcoming events (future events)
    $result = $conn->query("SELECT COUNT(*) as count FROM Event WHERE event_time > NOW()");
    $stats['upcoming_events'] = $result->fetch_assoc()['count'];

    // Past events
    $result = $conn->query("SELECT COUNT(*) as count FROM Event WHERE event_time <= NOW()");
    $stats['past_events'] = $result->fetch_assoc()['count'];

    echo json_encode(['success' => true, 'stats' => $stats]);
}

/**
 * Debug info - check schema and test POST
 */
function debugInfo($conn)
{
    $debug = [];

    // Check Event table columns
    $result = $conn->query("SHOW COLUMNS FROM Event");
    $debug['event_columns'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['event_columns'][] = $row['Field'];
    }

    // Check if area_id exists
    $check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
    $result = $conn->query($check_sql);
    $debug['has_area_id_column'] = $result && $result->num_rows > 0;

    // List existing events with area_id
    $result = $conn->query("SELECT event_id, event_name, area_id FROM Event ORDER BY event_id DESC LIMIT 5");
    $debug['recent_events'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['recent_events'][] = $row;
    }

    // Get all areas
    $result = $conn->query("SELECT area_id, area_name FROM ParkingArea");
    $debug['areas'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['areas'][] = $row;
    }

    // Check received POST data
    $debug['post_data'] = $_POST;

    echo json_encode(['success' => true, 'debug' => $debug]);
}

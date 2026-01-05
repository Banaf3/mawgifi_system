<?php
/**
 * =============================================================================
 * PARKING API - MODULE 2
 * =============================================================================
 * 
 * PURPOSE:
 * This is the REST API endpoint that handles all CRUD (Create, Read, Update, Delete)
 * operations for Parking Areas and Parking Spaces. It receives AJAX requests from
 * the admin interface and returns JSON responses.
 * 
 * ENDPOINTS:
 * GET  ?type=stats     - Get total space count and area list
 * GET  ?type=map       - Get map visualization data (colors, statuses, slots)
 * POST ?type=area      - Area operations (action: create/update/delete)
 * POST ?type=space     - Space operations (action: create/update/delete/bulk_create)
 * 
 * FLOW:
 * 1. Start session and verify admin authentication
 * 2. Parse type parameter from URL (?type=area|space|stats|map)
 * 3. For stats/map: return data immediately
 * 4. For area/space: parse action from POST and route to appropriate function
 * 5. Return JSON response with success/failure and data
 * 
 * SECURITY:
 * - Admin-only access (checks session user_type)
 * - Prepared statements for all SQL (prevents injection)
 * - Input validation in all functions
 * 
 * =============================================================================
 */

// =============================================================================
// SESSION INITIALIZATION
// =============================================================================
// Check if session is already started before starting a new one
// PHP_SESSION_NONE means no session exists yet

if (session_status() === PHP_SESSION_NONE) {  // Line 35: Check current session status
    session_start();  // Line 36: Start new session if none exists
}

// =============================================================================
// DEPENDENCIES
// =============================================================================

// Include database configuration for getDBConnection() function
require_once '../config/database.php';  // Line 44: Load database helper

// Include event status checker to auto-update area statuses
require_once 'check_event_status.php';  // Line 47: Load event status synchronization

// =============================================================================
// RESPONSE HEADER
// =============================================================================
// Tell the browser we're returning JSON data

header('Content-Type: application/json');  // Line 54: Set content type to JSON

// =============================================================================
// ADMIN AUTHENTICATION CHECK
// =============================================================================
// Only administrators can use this API

// Check three conditions:
// 1. user_id must exist in session (logged in)
// 2. user_type must exist in session
// 3. user_type must be 'admin'

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {  // Line 66: Check admin auth
    // Not authorized - return error and stop
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login as admin.']);  // Line 68: Return JSON error
    exit;  // Line 69: Stop script execution
}

// =============================================================================
// PARSE URL PARAMETERS
// =============================================================================
// Get the 'type' parameter from query string (?type=area or ?type=space)

$type = isset($_GET['type']) ? $_GET['type'] : '';  // Line 77: Get type parameter or empty string

// =============================================================================
// DATABASE CONNECTION
// =============================================================================

$conn = getDBConnection();  // Line 83: Get MySQLi connection object

if (!$conn) {  // Line 85: Check if connection failed
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);  // Line 86: Return error
    exit;  // Line 87: Stop execution
}

// =============================================================================
// HANDLE STATS REQUEST (?type=stats)
// =============================================================================
// Returns total space count and list of areas for dropdowns

if ($type === 'stats') {  // Line 94: Check if stats requested
    // Count total parking spaces
    $sql = "SELECT COUNT(*) as total_spaces FROM ParkingSpace";  // Line 96: SQL to count spaces
    $result = $conn->query($sql);  // Line 97: Execute query
    $row = $result->fetch_assoc();  // Line 98: Fetch result as associative array
    
    // Get all areas for dropdown menus
    $area_sql = "SELECT area_id, area_name FROM ParkingArea ORDER BY area_name";  // Line 101: SQL to get areas
    $area_result = $conn->query($area_sql);  // Line 102: Execute query
    $areas = [];  // Line 103: Initialize empty array
    
    // Build array of areas
    while ($area_row = $area_result->fetch_assoc()) {  // Line 106: Loop through results
        $areas[] = $area_row;  // Line 107: Add each area to array
    }
    
    // Return JSON response with stats
    echo json_encode([  // Line 111: Output JSON
        'success' => true,  // Line 112: Success flag
        'total_spaces' => (int)$row['total_spaces'],  // Line 113: Cast to integer for clean JSON
        'areas' => $areas  // Line 114: Array of areas
    ]);
    $conn->close();  // Line 116: Close database connection
    exit;  // Line 117: Stop execution
}

// =============================================================================
// HANDLE MAP DATA REQUEST (?type=map)
// =============================================================================
// Returns area data with colors/statuses and slot mappings for map visualization

if ($type === 'map') {  // Line 124: Check if map data requested
    // Complex SQL query with LEFT JOINs to get area data with booking counts
    $sql = "SELECT pa.area_id, pa.area_name, pa.area_color, pa.area_status,  -- Line 126: Select area columns
                   COUNT(ps.Space_id) as total_spaces,                        -- Line 127: Count spaces per area
                   SUM(CASE WHEN b.booking_id IS NOT NULL AND b.status = 'checked_in'  -- Line 128: Count checked-in bookings
                       AND b.booking_end > NOW() THEN 1 ELSE 0 END) as occupied_spaces  -- Line 129: Only active bookings
            FROM ParkingArea pa                                               -- Line 130: From areas table
            LEFT JOIN ParkingSpace ps ON pa.area_id = ps.area_id             -- Line 131: Join spaces
            LEFT JOIN Booking b ON ps.Space_id = b.Space_id                  -- Line 132: Join bookings
            GROUP BY pa.area_id";                                             // Line 133: Group by area
    
    $result = $conn->query($sql);  // Line 135: Execute query
    $areas = [];  // Line 136: Initialize areas array
    
    // Build array of areas with their data
    while ($row = $result->fetch_assoc()) {  // Line 139: Loop through results
        $areas[] = $row;  // Line 140: Add each area to array
    }
    
    // Get slot-to-area mapping for individual slot coloring
    $slotSql = "SELECT ps.space_number, pa.area_name, pa.area_color, pa.area_status  -- Line 144: Select slot data
                FROM ParkingSpace ps                                                  -- Line 145: From spaces
                JOIN ParkingArea pa ON ps.area_id = pa.area_id";                     // Line 146: Join to get area info
    $slotResult = $conn->query($slotSql);  // Line 147: Execute query
    $slots = [];  // Line 148: Initialize slots array
    
    // Build associative array mapping space_number to area data
    while ($row = $slotResult->fetch_assoc()) {  // Line 151: Loop through results
        $slots[$row['space_number']] = [  // Line 152: Key by space_number
            'area_name' => $row['area_name'],  // Line 153: Store area name
            'area_color' => $row['area_color'],  // Line 154: Store area color
            'area_status' => $row['area_status']  // Line 155: Store area status
        ];
    }
    
    // Return JSON response with map data
    echo json_encode([  // Line 160: Output JSON
        'success' => true,  // Line 161: Success flag
        'areas' => $areas,  // Line 162: Array of areas with stats
        'slots' => $slots  // Line 163: Mapping of slots to areas
    ]);
    $conn->close();  // Line 165: Close connection
    exit;  // Line 166: Stop execution
}

// =============================================================================
// PARSE POST ACTION
// =============================================================================
// For area/space operations, get the action from POST data

$action = isset($_POST['action']) ? $_POST['action'] : '';  // Line 173: Get action or empty string

// =============================================================================
// ROUTE PARKING AREA OPERATIONS (?type=area)
// =============================================================================

if ($type === 'area') {  // Line 179: Check if area operations requested
    // Use switch to route to appropriate function based on action
    switch ($action) {  // Line 181: Switch on action value
        case 'create':  // Line 182: Create new area
            createArea($conn);  // Line 183: Call createArea function
            break;  // Line 184: Exit switch
        case 'update':  // Line 185: Update existing area
            updateArea($conn);  // Line 186: Call updateArea function
            break;  // Line 187: Exit switch
        case 'delete':  // Line 188: Delete area
            deleteArea($conn);  // Line 189: Call deleteArea function
            break;  // Line 190: Exit switch
        default:  // Line 191: Unknown action
            echo json_encode(['success' => false, 'message' => 'Invalid action']);  // Line 192: Return error
    }
}

// =============================================================================
// ROUTE PARKING SPACE OPERATIONS (?type=space)
// =============================================================================

elseif ($type === 'space') {  // Line 200: Check if space operations requested
    switch ($action) {  // Line 201: Switch on action
        case 'create':  // Line 202: Create single space
            createSpace($conn);  // Line 203: Call createSpace function
            break;  // Line 204: Exit switch
        case 'update':  // Line 205: Update existing space
            updateSpace($conn);  // Line 206: Call updateSpace function
            break;  // Line 207: Exit switch
        case 'delete':  // Line 208: Delete space
            deleteSpace($conn);  // Line 209: Call deleteSpace function
            break;  // Line 210: Exit switch
        case 'bulk_create':  // Line 211: Create multiple spaces at once
            bulkCreateSpaces($conn);  // Line 212: Call bulkCreateSpaces function
            break;  // Line 213: Exit switch
        default:  // Line 214: Unknown action
            echo json_encode(['success' => false, 'message' => 'Invalid action']);  // Line 215: Return error
    }
} else {  // Line 217: Invalid type parameter
    echo json_encode(['success' => false, 'message' => 'Invalid type parameter']);  // Line 218: Return error
}

$conn->close();  // Line 221: Close database connection

// =============================================================================
// =============================================================================
// PARKING AREA FUNCTIONS
// =============================================================================
// =============================================================================

/**
 * FUNCTION: createArea($conn)
 * 
 * PURPOSE: Create a new parking area in the database
 * 
 * INPUT (POST):
 * - area_name: Name of area (e.g., "Area A")
 * - area_type: Type (Standard, VIP, etc.)
 * - area_size: Size in square meters
 * - area_color: Hex color for map
 * - area_status: Status (available, occupied, etc.)
 * - availability_id: Foreign key to Availability table
 * 
 * OUTPUT: JSON response with success/failure
 */
function createArea($conn) {  // Line 244: Define createArea function
    // -------------------------------------------------------------------------
    // EXTRACT AND SANITIZE INPUT FROM POST DATA
    // -------------------------------------------------------------------------
    
    // Get area name - trim() removes whitespace from beginning and end
    $area_name = isset($_POST['area_name']) ? trim($_POST['area_name']) : '';  // Line 250: Get area name
    
    // Get area type - defaults to 'Standard' if not provided
    $area_type = isset($_POST['area_type']) ? trim($_POST['area_type']) : 'Standard';  // Line 253: Get area type
    
    // Get area size - floatval() converts to decimal number, null if empty
    $area_size = isset($_POST['area_size']) && $_POST['area_size'] !== '' ? floatval($_POST['area_size']) : null;  // Line 256: Get area size
    
    // Get area color - defaults to gray (#a0a0a0) if not provided
    $area_color = isset($_POST['area_color']) ? trim($_POST['area_color']) : '#a0a0a0';  // Line 259: Get area color
    
    // Get area status - defaults to 'available' if not provided
    $area_status = isset($_POST['area_status']) ? trim($_POST['area_status']) : 'available';  // Line 262: Get area status
    
    // Get availability_id - intval() converts to integer, null if empty
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;  // Line 265: Get availability FK

    // -------------------------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // -------------------------------------------------------------------------
    
    if (empty($area_name)) {  // Line 271: Check if area name is empty
        echo json_encode(['success' => false, 'message' => 'Area name is required']);  // Line 272: Return error
        return;  // Line 273: Exit function early
    }

    // -------------------------------------------------------------------------
    // CHECK FOR DUPLICATE AREA NAME
    // -------------------------------------------------------------------------
    // Ensure no other area has this name
    
    $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ?";  // Line 281: SQL to find existing area
    $check_stmt = $conn->prepare($check_sql);  // Line 282: Prepare statement
    $check_stmt->bind_param("s", $area_name);  // Line 283: Bind area name as string
    $check_stmt->execute();  // Line 284: Execute query
    
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 286: If area name exists
        echo json_encode(['success' => false, 'message' => 'An area with this name already exists']);  // Line 287: Return error
        $check_stmt->close();  // Line 288: Close statement
        return;  // Line 289: Exit function
    }
    $check_stmt->close();  // Line 291: Close statement

    // -------------------------------------------------------------------------
    // SCHEMA COMPATIBILITY CHECK
    // -------------------------------------------------------------------------
    // Check if database has the newer columns (area_color, area_status)
    // This allows the code to work with both old and new database schemas
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_color'");  // Line 299: Check for color column
    $has_color_column = $columns_check && $columns_check->num_rows > 0;  // Line 300: True if column exists
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");  // Line 302: Check for status column
    $has_status_column = $columns_check && $columns_check->num_rows > 0;  // Line 303: True if column exists

    // -------------------------------------------------------------------------
    // BUILD AND EXECUTE INSERT QUERY
    // -------------------------------------------------------------------------
    // Use different SQL depending on which columns exist
    
    if ($has_color_column && $has_status_column) {  // Line 310: If new columns exist
        // Insert with all columns including color and status
        $sql = "INSERT INTO ParkingArea (area_name, area_type, AreaSize, area_color, area_status, Availability_id) VALUES (?, ?, ?, ?, ?, ?)";  // Line 312: Full INSERT
        $stmt = $conn->prepare($sql);  // Line 313: Prepare statement
        // "ssdssi" = string, string, double, string, string, integer
        $stmt->bind_param("ssdssi", $area_name, $area_type, $area_size, $area_color, $area_status, $availability_id);  // Line 315: Bind all params
    } else {
        // Fallback for older schema without color/status columns
        $sql = "INSERT INTO ParkingArea (area_name, area_type, AreaSize, Availability_id) VALUES (?, ?, ?, ?)";  // Line 318: Basic INSERT
        $stmt = $conn->prepare($sql);  // Line 319: Prepare statement
        // "ssdi" = string, string, double, integer
        $stmt->bind_param("ssdi", $area_name, $area_type, $area_size, $availability_id);  // Line 321: Bind basic params
    }

    // Execute and return result
    if ($stmt->execute()) {  // Line 325: Try to execute INSERT
        // Success - return new area ID
        echo json_encode([  // Line 327: Output JSON
            'success' => true,  // Line 328: Success flag
            'message' => 'Parking area created successfully',  // Line 329: Success message
            'area_id' => $conn->insert_id  // Line 330: Return auto-generated ID
        ]);
    } else {
        // Failure - return error with database message
        echo json_encode(['success' => false, 'message' => 'Failed to create area: ' . $conn->error]);  // Line 334: Return error
    }
    $stmt->close();  // Line 336: Close statement
}

/**
 * FUNCTION: updateArea($conn)
 * 
 * PURPOSE: Update an existing parking area's information
 * 
 * INPUT (POST):
 * - area_id: ID of area to update
 * - area_name, area_type, area_size, area_color, area_status, availability_id
 * 
 * OUTPUT: JSON response with success/failure
 */
function updateArea($conn) {  // Line 350: Define updateArea function
    // -------------------------------------------------------------------------
    // EXTRACT AND SANITIZE INPUT FROM POST DATA
    // -------------------------------------------------------------------------
    
    // Get area ID - required for identifying which area to update
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;  // Line 357: Get area ID as integer
    
    // Get other fields (same as createArea)
    $area_name = isset($_POST['area_name']) ? trim($_POST['area_name']) : '';  // Line 360: Get area name
    $area_type = isset($_POST['area_type']) ? trim($_POST['area_type']) : 'Standard';  // Line 361: Get area type
    $area_size = isset($_POST['area_size']) && $_POST['area_size'] !== '' ? floatval($_POST['area_size']) : null;  // Line 362: Get area size
    $area_color = isset($_POST['area_color']) ? trim($_POST['area_color']) : '#a0a0a0';  // Line 363: Get area color
    $area_status = isset($_POST['area_status']) ? trim($_POST['area_status']) : 'available';  // Line 364: Get area status
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;  // Line 365: Get availability FK

    // -------------------------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // -------------------------------------------------------------------------
    
    if ($area_id <= 0) {  // Line 372: Check if area_id is valid
        echo json_encode(['success' => false, 'message' => 'Invalid area ID']);  // Line 373: Return error
        return;  // Line 374: Exit function
    }
    
    if (empty($area_name)) {  // Line 377: Check if area name is empty
        echo json_encode(['success' => false, 'message' => 'Area name is required']);  // Line 378: Return error
        return;  // Line 379: Exit function
    }

    // -------------------------------------------------------------------------
    // CHECK FOR DUPLICATE AREA NAME (EXCLUDING CURRENT AREA)
    // -------------------------------------------------------------------------
    // Make sure no OTHER area has this name (allow keeping same name for this area)
    
    $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ? AND area_id != ?";  // Line 387: SQL with exclusion
    $check_stmt = $conn->prepare($check_sql);  // Line 388: Prepare statement
    $check_stmt->bind_param("si", $area_name, $area_id);  // Line 389: Bind name and ID to exclude
    $check_stmt->execute();  // Line 390: Execute query
    
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 392: If another area has this name
        echo json_encode(['success' => false, 'message' => 'An area with this name already exists']);  // Line 393: Return error
        $check_stmt->close();  // Line 394: Close statement
        return;  // Line 395: Exit function
    }
    $check_stmt->close();  // Line 397: Close statement

    // -------------------------------------------------------------------------
    // SCHEMA COMPATIBILITY CHECK
    // -------------------------------------------------------------------------
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_color'");  // Line 403: Check color column
    $has_color_column = $columns_check && $columns_check->num_rows > 0;  // Line 404: Store result
    
    $columns_check = $conn->query("SHOW COLUMNS FROM ParkingArea LIKE 'area_status'");  // Line 406: Check status column
    $has_status_column = $columns_check && $columns_check->num_rows > 0;  // Line 407: Store result

    // -------------------------------------------------------------------------
    // BUILD AND EXECUTE UPDATE QUERY
    // -------------------------------------------------------------------------
    
    if ($has_color_column && $has_status_column) {  // Line 413: If new columns exist
        // Update with all columns
        $sql = "UPDATE ParkingArea SET area_name = ?, area_type = ?, AreaSize = ?, area_color = ?, area_status = ?, Availability_id = ? WHERE area_id = ?";  // Line 415: Full UPDATE
        $stmt = $conn->prepare($sql);  // Line 416: Prepare statement
        // "ssdssii" = string, string, double, string, string, int, int
        $stmt->bind_param("ssdssii", $area_name, $area_type, $area_size, $area_color, $area_status, $availability_id, $area_id);  // Line 418: Bind params
    } else {
        // Fallback for older schema
        $sql = "UPDATE ParkingArea SET area_name = ?, area_type = ?, AreaSize = ?, Availability_id = ? WHERE area_id = ?";  // Line 421: Basic UPDATE
        $stmt = $conn->prepare($sql);  // Line 422: Prepare statement
        $stmt->bind_param("ssdii", $area_name, $area_type, $area_size, $availability_id, $area_id);  // Line 423: Bind params
    }

    // Execute and check result
    if ($stmt->execute()) {  // Line 427: Try to execute UPDATE
        if ($stmt->affected_rows > 0) {  // Line 428: Check if any rows were actually changed
            echo json_encode(['success' => true, 'message' => 'Parking area updated successfully']);  // Line 429: Success with changes
        } else {
            // No rows changed - data was same as before (still success)
            echo json_encode(['success' => true, 'message' => 'No changes made']);  // Line 432: Success but no changes
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update area: ' . $conn->error]);  // Line 435: Return error
    }
    $stmt->close();  // Line 437: Close statement
}

/**
 * FUNCTION: deleteArea($conn)
 * 
 * PURPOSE: Delete a parking area from the database
 * NOTE: This will also delete all parking spaces in the area due to foreign key CASCADE
 * 
 * INPUT (POST):
 * - area_id: ID of area to delete
 * 
 * OUTPUT: JSON response with success/failure
 * 
 * SAFETY: Will not delete if area has active bookings
 */
function deleteArea($conn) {  // Line 453: Define deleteArea function
    // Get area ID from POST
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;  // Line 455: Get area ID as integer

    // Validate area ID
    if ($area_id <= 0) {  // Line 458: Check if valid
        echo json_encode(['success' => false, 'message' => 'Invalid area ID']);  // Line 459: Return error
        return;  // Line 460: Exit function
    }

    // -------------------------------------------------------------------------
    // CHECK FOR ACTIVE BOOKINGS
    // -------------------------------------------------------------------------
    // Cannot delete area if there are active bookings for any of its spaces
    
    $check_sql = "SELECT b.booking_id               -- Line 468: Select booking IDs
                  FROM Booking b                     -- Line 469: From Booking table
                  JOIN ParkingSpace ps ON b.Space_id = ps.Space_id  -- Line 470: Join to get space's area
                  WHERE ps.area_id = ? AND b.booking_end > NOW()";  // Line 471: Match area, only future bookings
    $check_stmt = $conn->prepare($check_sql);  // Line 472: Prepare statement
    $check_stmt->bind_param("i", $area_id);  // Line 473: Bind area ID
    $check_stmt->execute();  // Line 474: Execute query
    
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 476: If active bookings exist
        echo json_encode(['success' => false, 'message' => 'Cannot delete area with active bookings']);  // Line 477: Return error
        $check_stmt->close();  // Line 478: Close statement
        return;  // Line 479: Exit function
    }
    $check_stmt->close();  // Line 482: Close statement

    // -------------------------------------------------------------------------
    // DELETE AREA FROM DATABASE
    // -------------------------------------------------------------------------
    // NOTE: Parking spaces will be automatically deleted due to ON DELETE CASCADE
    // foreign key constraint in ParkingSpace table
    
    $sql = "DELETE FROM ParkingArea WHERE area_id = ?";  // Line 490: DELETE query
    $stmt = $conn->prepare($sql);  // Line 491: Prepare statement
    $stmt->bind_param("i", $area_id);  // Line 492: Bind area ID

    // Execute and check result
    if ($stmt->execute()) {  // Line 495: Try to execute DELETE
        if ($stmt->affected_rows > 0) {  // Line 496: Check if row was deleted
            echo json_encode(['success' => true, 'message' => 'Parking area deleted successfully']);  // Line 497: Success
        } else {
            echo json_encode(['success' => false, 'message' => 'Area not found']);  // Line 499: Area didn't exist
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete area: ' . $conn->error]);  // Line 502: Database error
    }
    $stmt->close();  // Line 504: Close statement
}

// =============================================================================
// SPACE MANAGEMENT FUNCTIONS
// =============================================================================
// These functions handle CRUD operations for individual parking spaces

/**
 * FUNCTION: createSpace($conn)
 * 
 * PURPOSE: Create a new parking space within an area
 * 
 * INPUT (POST):
 * - area_id: ID of area to add space to (required)
 * - space_number: Display number for the space (required)
 * - qr_code: Unique QR code for check-in (optional, auto-generated if empty)
 * - status: Initial status (optional, defaults to 'available')
 * 
 * OUTPUT: JSON with success status and new space_id
 * 
 * CONSTRAINTS:
 * - Maximum 100 spaces total across all areas
 * - Space numbers must be unique
 * - Valid statuses: available, occupied, reserved, maintenance
 * 
 * ALGORITHM:
 * 1. Extract and validate input parameters
 * 2. Check total space count against 100 limit
 * 3. Auto-generate QR code if not provided
 * 4. Check for duplicate space number
 * 5. Insert new space into ParkingSpace table
 * 6. Return success with new space_id
 */
function createSpace($conn) {  // Line 543: Define createSpace function
    // -------------------------------------------------------------------------
    // EXTRACT INPUT PARAMETERS
    // -------------------------------------------------------------------------
    
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;  // Line 548: Get area ID as integer
    $space_number = isset($_POST['space_number']) ? trim($_POST['space_number']) : '';  // Line 549: Get space number
    $qr_code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : '';  // Line 550: Get QR code (optional)
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'available';  // Line 551: Get status, default available

    // -------------------------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // -------------------------------------------------------------------------
    
    if ($area_id <= 0) {  // Line 557: Check if area ID is valid
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);  // Line 558: Return error
        return;  // Line 559: Exit function
    }
    if (empty($space_number)) {  // Line 561: Check if space number is empty
        echo json_encode(['success' => false, 'message' => 'Space number is required']);  // Line 562: Return error
        return;  // Line 563: Exit function
    }

    // -------------------------------------------------------------------------
    // VALIDATE STATUS VALUE (WHITELIST)
    // -------------------------------------------------------------------------
    
    $valid_statuses = ['available', 'occupied', 'reserved', 'maintenance'];  // Line 570: Define allowed statuses
    if (!in_array($status, $valid_statuses)) {  // Line 571: Check if status is in whitelist
        $status = 'available';  // Line 572: Default to available if invalid
    }

    // -------------------------------------------------------------------------
    // CHECK TOTAL SPACE COUNT (100 LIMIT)
    // -------------------------------------------------------------------------
    // System-wide limit of 100 parking spaces to match SVG map layout
    
    $count_sql = "SELECT COUNT(*) as total FROM ParkingSpace";  // Line 580: Count all spaces
    $count_result = $conn->query($count_sql);  // Line 581: Execute query
    $count_row = $count_result->fetch_assoc();  // Line 582: Get result row
    if ($count_row['total'] >= 100) {  // Line 583: If already at 100
        echo json_encode(['success' => false, 'message' => 'Cannot create space. Maximum limit of 100 spaces reached.']);  // Line 584: Return error
        return;  // Line 585: Exit function
    }

    // -------------------------------------------------------------------------
    // AUTO-GENERATE QR CODE IF NOT PROVIDED
    // -------------------------------------------------------------------------
    // Format: SPACE-<UNIQUE_ID>-<SPACE_NUMBER>
    
    if (empty($qr_code)) {  // Line 593: If QR code not provided
        $qr_code = 'SPACE-' . strtoupper(uniqid()) . '-' . $space_number;  // Line 594: Generate unique QR code
    }

    // -------------------------------------------------------------------------
    // CHECK FOR DUPLICATE SPACE NUMBER
    // -------------------------------------------------------------------------
    
    $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";  // Line 601: Query to find existing
    $check_stmt = $conn->prepare($check_sql);  // Line 602: Prepare statement
    $check_stmt->bind_param("s", $space_number);  // Line 603: Bind space number
    $check_stmt->execute();  // Line 604: Execute query
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 605: If space number exists
        echo json_encode(['success' => false, 'message' => 'A space with this number already exists']);  // Line 606: Return error
        $check_stmt->close();  // Line 607: Close statement
        return;  // Line 608: Exit function
    }
    $check_stmt->close();  // Line 610: Close statement

    // -------------------------------------------------------------------------
    // INSERT NEW SPACE INTO DATABASE
    // -------------------------------------------------------------------------
    
    $sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code, status) VALUES (?, ?, ?, ?)";  // Line 616: INSERT query
    $stmt = $conn->prepare($sql);  // Line 617: Prepare statement
    $stmt->bind_param("isss", $area_id, $space_number, $qr_code, $status);  // Line 618: Bind all parameters

    // Execute and return result
    if ($stmt->execute()) {  // Line 621: Try to execute INSERT
        echo json_encode([  // Line 622: Return success response
            'success' => true,  // Line 623: Success flag
            'message' => 'Parking space created successfully',  // Line 624: Success message
            'space_id' => $conn->insert_id  // Line 625: Return new space ID
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create space: ' . $conn->error]);  // Line 628: Return error
    }
    $stmt->close();  // Line 630: Close statement
}

/**
 * FUNCTION: updateSpace($conn)
 * 
 * PURPOSE: Update an existing parking space's properties
 * 
 * INPUT (POST):
 * - space_id: ID of space to update (required)
 * - area_id: New area ID (required)
 * - space_number: Updated display number (required)
 * - qr_code: Updated QR code
 * - status: New status value
 * 
 * OUTPUT: JSON with success/failure message
 * 
 * ALGORITHM:
 * 1. Extract and validate all input parameters
 * 2. Validate status against whitelist
 * 3. Check for duplicate space number (excluding self)
 * 4. Execute UPDATE query
 * 5. Return result with status confirmation
 */
function updateSpace($conn) {  // Line 652: Define updateSpace function
    // -------------------------------------------------------------------------
    // EXTRACT INPUT PARAMETERS
    // -------------------------------------------------------------------------
    
    $space_id = isset($_POST['space_id']) ? intval($_POST['space_id']) : 0;  // Line 657: Get space ID
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;  // Line 658: Get area ID
    $space_number = isset($_POST['space_number']) ? trim($_POST['space_number']) : '';  // Line 659: Get space number
    $qr_code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : '';  // Line 660: Get QR code
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'available';  // Line 661: Get status

    // -------------------------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // -------------------------------------------------------------------------
    
    if ($space_id <= 0) {  // Line 667: Check space ID validity
        echo json_encode(['success' => false, 'message' => 'Invalid space ID']);  // Line 668: Return error
        return;  // Line 669: Exit function
    }
    if ($area_id <= 0) {  // Line 671: Check area ID validity
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);  // Line 672: Return error
        return;  // Line 673: Exit function
    }
    if (empty($space_number)) {  // Line 675: Check space number
        echo json_encode(['success' => false, 'message' => 'Space number is required']);  // Line 676: Return error
        return;  // Line 677: Exit function
    }

    // -------------------------------------------------------------------------
    // VALIDATE STATUS VALUE (WHITELIST)
    // -------------------------------------------------------------------------
    
    $valid_statuses = ['available', 'occupied', 'reserved', 'maintenance'];  // Line 684: Define allowed statuses
    if (!in_array($status, $valid_statuses)) {  // Line 685: Check if status is valid
        $status = 'available';  // Line 686: Default to available if invalid
    }

    // -------------------------------------------------------------------------
    // CHECK FOR DUPLICATE SPACE NUMBER (EXCLUDING SELF)
    // -------------------------------------------------------------------------
    
    $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ? AND Space_id != ?";  // Line 693: Query with exclusion
    $check_stmt = $conn->prepare($check_sql);  // Line 694: Prepare statement
    $check_stmt->bind_param("si", $space_number, $space_id);  // Line 695: Bind space number and ID
    $check_stmt->execute();  // Line 696: Execute query
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 697: If another space has this number
        echo json_encode(['success' => false, 'message' => 'A space with this number already exists']);  // Line 698: Return error
        $check_stmt->close();  // Line 699: Close statement
        return;  // Line 700: Exit function
    }
    $check_stmt->close();  // Line 702: Close statement

    // -------------------------------------------------------------------------
    // EXECUTE UPDATE QUERY
    // -------------------------------------------------------------------------
    
    $sql = "UPDATE ParkingSpace SET area_id = ?, space_number = ?, qr_code = ?, status = ? WHERE Space_id = ?";  // Line 709: UPDATE query
    $stmt = $conn->prepare($sql);  // Line 710: Prepare statement
    $stmt->bind_param("isssi", $area_id, $space_number, $qr_code, $status, $space_id);  // Line 711: Bind params

    // Execute and return result
    if ($stmt->execute()) {  // Line 714: Try to execute UPDATE
        // Include status in response so user knows it was saved
        echo json_encode(['success' => true, 'message' => 'Space updated successfully. Status: ' . ucfirst($status)]);  // Line 716: Success with status
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update space: ' . $conn->error]);  // Line 718: Return error
    }
    $stmt->close();  // Line 720: Close statement
}

/**
 * FUNCTION: deleteSpace($conn)
 * 
 * PURPOSE: Delete a parking space from the database
 * 
 * INPUT (POST):
 * - space_id: ID of space to delete (required)
 * 
 * OUTPUT: JSON with success/failure message
 * 
 * SAFETY: Will not delete if space has active (future) bookings
 * 
 * ALGORITHM:
 * 1. Get and validate space_id
 * 2. Check for active bookings (booking_end > NOW())
 * 3. Execute DELETE if no conflicts
 * 4. Return appropriate message
 */
function deleteSpace($conn) {  // Line 742: Define deleteSpace function
    // Get space ID from POST
    $space_id = isset($_POST['space_id']) ? intval($_POST['space_id']) : 0;  // Line 744: Get space ID as integer

    // Validate space ID
    if ($space_id <= 0) {  // Line 747: Check if valid
        echo json_encode(['success' => false, 'message' => 'Invalid space ID']);  // Line 748: Return error
        return;  // Line 749: Exit function
    }

    // -------------------------------------------------------------------------
    // CHECK FOR ACTIVE BOOKINGS
    // -------------------------------------------------------------------------
    // Cannot delete a space if it has any bookings that haven't ended yet
    
    $check_sql = "SELECT booking_id FROM Booking WHERE Space_id = ? AND booking_end > NOW()";  // Line 757: Find active bookings
    $check_stmt = $conn->prepare($check_sql);  // Line 758: Prepare statement
    $check_stmt->bind_param("i", $space_id);  // Line 759: Bind space ID
    $check_stmt->execute();  // Line 760: Execute query
    if ($check_stmt->get_result()->num_rows > 0) {  // Line 761: If active bookings exist
        echo json_encode(['success' => false, 'message' => 'Cannot delete space with active bookings']);  // Line 762: Return error
        $check_stmt->close();  // Line 763: Close statement
        return;  // Line 764: Exit function
    }
    $check_stmt->close();  // Line 766: Close statement

    // -------------------------------------------------------------------------
    // DELETE SPACE FROM DATABASE
    // -------------------------------------------------------------------------
    
    $sql = "DELETE FROM ParkingSpace WHERE Space_id = ?";  // Line 772: DELETE query
    $stmt = $conn->prepare($sql);  // Line 773: Prepare statement
    $stmt->bind_param("i", $space_id);  // Line 774: Bind space ID

    // Execute and check result
    if ($stmt->execute()) {  // Line 777: Try to execute DELETE
        if ($stmt->affected_rows > 0) {  // Line 778: Check if row was deleted
            echo json_encode(['success' => true, 'message' => 'Parking space deleted successfully']);  // Line 779: Success
        } else {
            echo json_encode(['success' => false, 'message' => 'Space not found']);  // Line 781: Space didn't exist
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete space: ' . $conn->error]);  // Line 784: Database error
    }
    $stmt->close();  // Line 786: Close statement
}

/**
 * FUNCTION: bulkCreateSpaces($conn)
 * 
 * PURPOSE: Create multiple parking spaces at once with sequential numbering
 * 
 * INPUT (POST):
 * - area_id: ID of area to add spaces to (required)
 * - prefix: Letter prefix for space numbers, e.g., "A" (required)
 * - start_number: Starting number for sequence (required)
 * - end_number: Ending number for sequence (required)
 * 
 * OUTPUT: JSON with success status, created count, skipped count
 * 
 * EXAMPLE:
 * - prefix="A", start=1, end=20 creates: A-01, A-02, A-03, ... A-20
 * 
 * CONSTRAINTS:
 * - Maximum 100 spaces per batch
 * - Total system spaces cannot exceed 100
 * - Duplicate space numbers are skipped (not failed)
 * 
 * ALGORITHM:
 * 1. Validate all input parameters
 * 2. Check current space count against limit
 * 3. Begin database transaction
 * 4. Loop through number range, creating each space
 * 5. Skip duplicates, count successes
 * 6. Commit transaction and return summary
 */
function bulkCreateSpaces($conn) {  // Line 820: Define bulkCreateSpaces function
    // -------------------------------------------------------------------------
    // EXTRACT INPUT PARAMETERS
    // -------------------------------------------------------------------------
    
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;  // Line 825: Get area ID
    $prefix = isset($_POST['prefix']) ? strtoupper(trim($_POST['prefix'])) : '';  // Line 826: Get prefix, convert to uppercase
    $start_number = isset($_POST['start_number']) ? intval($_POST['start_number']) : 0;  // Line 827: Get start of range
    $end_number = isset($_POST['end_number']) ? intval($_POST['end_number']) : 0;  // Line 828: Get end of range

    // -------------------------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // -------------------------------------------------------------------------
    
    if ($area_id <= 0) {  // Line 834: Check area ID validity
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);  // Line 835: Return error
        return;  // Line 836: Exit function
    }
    if (empty($prefix)) {  // Line 838: Check if prefix is empty
        echo json_encode(['success' => false, 'message' => 'Space prefix is required']);  // Line 839: Return error
        return;  // Line 840: Exit function
    }
    if ($start_number <= 0 || $end_number <= 0) {  // Line 842: Check if numbers are positive
        echo json_encode(['success' => false, 'message' => 'Start and end numbers must be positive']);  // Line 843: Return error
        return;  // Line 844: Exit function
    }
    if ($end_number < $start_number) {  // Line 846: Check order of range
        echo json_encode(['success' => false, 'message' => 'End number must be greater than or equal to start number']);  // Line 847: Return error
        return;  // Line 848: Exit function
    }
    if (($end_number - $start_number + 1) > 100) {  // Line 850: Check batch size
        echo json_encode(['success' => false, 'message' => 'Cannot create more than 100 spaces at once']);  // Line 851: Return error
        return;  // Line 852: Exit function
    }

    // -------------------------------------------------------------------------
    // CHECK TOTAL SPACE COUNT (100 LIMIT)
    // -------------------------------------------------------------------------
    
    $count_sql = "SELECT COUNT(*) as total FROM ParkingSpace";  // Line 859: Count all existing spaces
    $count_result = $conn->query($count_sql);  // Line 860: Execute query
    $count_row = $count_result->fetch_assoc();  // Line 861: Get result row
    $current_total = $count_row['total'];  // Line 862: Store current count
    $spaces_to_create = $end_number - $start_number + 1;  // Line 863: Calculate how many spaces will be created
    
    if ($current_total + $spaces_to_create > 100) {  // Line 865: Check if would exceed limit
        echo json_encode([  // Line 866: Return detailed error
            'success' => false,   // Line 867: Failure flag
            'message' => "Cannot create $spaces_to_create space(s). Current total: $current_total. Would exceed 100 space limit."  // Line 868: Detailed message
        ]);
        return;  // Line 870: Exit function
    }

    // -------------------------------------------------------------------------
    // BEGIN TRANSACTION FOR ATOMIC OPERATION
    // -------------------------------------------------------------------------
    // All spaces are created together or none are (rollback on error)
    
    $conn->begin_transaction();  // Line 878: Start transaction
    
    $created = 0;  // Line 880: Counter for successfully created spaces
    $skipped = 0;  // Line 881: Counter for skipped (duplicate) spaces

    try {  // Line 883: Try block for transaction safety
        // ---------------------------------------------------------------------
        // LOOP THROUGH NUMBER RANGE AND CREATE SPACES
        // ---------------------------------------------------------------------
        
        for ($slot = $start_number; $slot <= $end_number; $slot++) {  // Line 888: Loop from start to end
            // Generate space number with zero-padding (e.g., A-01, A-02)
            $space_number = $prefix . '-' . str_pad($slot, 2, '0', STR_PAD_LEFT);  // Line 890: Format: PREFIX-XX
            // Generate QR code with 3-digit padding for uniqueness
            $qr_code = 'SPACE-' . $prefix . '-' . str_pad($slot, 3, '0', STR_PAD_LEFT);  // Line 892: Format: SPACE-PREFIX-XXX
            
            // Check if this space number already exists
            $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";  // Line 895: Query to find existing
            $check_stmt = $conn->prepare($check_sql);  // Line 896: Prepare statement
            $check_stmt->bind_param("s", $space_number);  // Line 897: Bind space number
            $check_stmt->execute();  // Line 898: Execute query
            $result = $check_stmt->get_result();  // Line 899: Get result
            
            if ($result->num_rows === 0) {  // Line 901: If space doesn't exist
                // Create the new space
                $insert_sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code) VALUES (?, ?, ?)";  // Line 903: INSERT query
                $insert_stmt = $conn->prepare($insert_sql);  // Line 904: Prepare statement
                $insert_stmt->bind_param("iss", $area_id, $space_number, $qr_code);  // Line 905: Bind parameters
                $insert_stmt->execute();  // Line 906: Execute INSERT
                $insert_stmt->close();  // Line 907: Close INSERT statement
                $created++;  // Line 908: Increment created counter
            } else {
                $skipped++;  // Line 910: Space exists, increment skip counter
            }
            $check_stmt->close();  // Line 912: Close check statement
        }
        
        // All insertions successful - commit the transaction
        $conn->commit();  // Line 916: Commit all changes
        
        // Build response message
        $message = "Created $created parking space(s)";  // Line 919: Base message
        if ($skipped > 0) {  // Line 920: If any were skipped
            $message .= " ($skipped already existed)";  // Line 921: Add skip info to message
        }
        
        // Return success response with details
        echo json_encode([  // Line 925: Return success response
            'success' => true,  // Line 926: Success flag
            'message' => $message,  // Line 927: Human-readable message
            'created' => $created,  // Line 928: Number created
            'skipped' => $skipped  // Line 929: Number skipped
        ]);
        
    } catch (Exception $e) {  // Line 932: Catch any errors
        // Error occurred - rollback all changes
        $conn->rollback();  // Line 934: Undo all insertions
        echo json_encode(['success' => false, 'message' => 'Failed to create spaces: ' . $e->getMessage()]);  // Line 935: Return error
    }
}

// =============================================================================
// END OF PARKING API
// =============================================================================
// 
// SUMMARY OF ENDPOINTS:
// 
// GET Endpoints (via ?type=):
//   ?type=stats       - Get parking statistics (areas, spaces, bookings, revenue)
//   ?type=map         - Get full parking map data (areas with nested spaces)
// 
// POST Endpoints (via ?entity=&action=):
//   Areas:
//     ?entity=area&action=create  - Create new parking area
//     ?entity=area&action=update  - Update existing area
//     ?entity=area&action=delete  - Delete area (cascade deletes spaces)
// 
//   Spaces:
//     ?entity=space&action=create     - Create single parking space
//     ?entity=space&action=update     - Update existing space
//     ?entity=space&action=delete     - Delete space
//     ?entity=space&action=bulk_create - Create multiple spaces at once
// 
// SECURITY: All endpoints require admin session authentication
// DATABASE: Uses MySQLi prepared statements for SQL injection prevention
// 
?>

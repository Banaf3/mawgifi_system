<?php
/**
 * Parking API - Admin Module
 * Handles CRUD operations for Parking Areas and Parking Spaces
 * Returns JSON responses for AJAX requests
 */

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Set JSON response header
header('Content-Type: application/json');

// Require admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login as admin.']);
    exit;
}

// Get the type parameter (area or space)
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get action from POST
$action = isset($_POST['action']) ? $_POST['action'] : '';

// ========== PARKING AREA OPERATIONS ==========
if ($type === 'area') {
    switch ($action) {
        case 'create':
            createArea($conn);
            break;
        case 'update':
            updateArea($conn);
            break;
        case 'delete':
            deleteArea($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

// ========== PARKING SPACE OPERATIONS ==========
elseif ($type === 'space') {
    switch ($action) {
        case 'create':
            createSpace($conn);
            break;
        case 'update':
            updateSpace($conn);
            break;
        case 'delete':
            deleteSpace($conn);
            break;
        case 'bulk_create':
            bulkCreateSpaces($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type parameter']);
}

$conn->close();

// ========== AREA FUNCTIONS ==========

/**
 * Create a new parking area
 */
function createArea($conn) {
    $area_name = isset($_POST['area_name']) ? trim($_POST['area_name']) : '';
    $area_type = isset($_POST['area_type']) ? trim($_POST['area_type']) : 'Standard';
    $area_size = isset($_POST['area_size']) && $_POST['area_size'] !== '' ? floatval($_POST['area_size']) : null;
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;

    // Validate required fields
    if (empty($area_name)) {
        echo json_encode(['success' => false, 'message' => 'Area name is required']);
        return;
    }

    // Check if area name already exists
    $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $area_name);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An area with this name already exists']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Insert new area
    $sql = "INSERT INTO ParkingArea (area_name, area_type, AreaSize, Availability_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdi", $area_name, $area_type, $area_size, $availability_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Parking area created successfully',
            'area_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create area: ' . $conn->error]);
    }
    $stmt->close();
}

/**
 * Update an existing parking area
 */
function updateArea($conn) {
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
    $area_name = isset($_POST['area_name']) ? trim($_POST['area_name']) : '';
    $area_type = isset($_POST['area_type']) ? trim($_POST['area_type']) : 'Standard';
    $area_size = isset($_POST['area_size']) && $_POST['area_size'] !== '' ? floatval($_POST['area_size']) : null;
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;

    // Validate required fields
    if ($area_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid area ID']);
        return;
    }
    if (empty($area_name)) {
        echo json_encode(['success' => false, 'message' => 'Area name is required']);
        return;
    }

    // Check if area name already exists (for another area)
    $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ? AND area_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $area_name, $area_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An area with this name already exists']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Update area
    $sql = "UPDATE ParkingArea SET area_name = ?, area_type = ?, AreaSize = ?, Availability_id = ? WHERE area_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdii", $area_name, $area_type, $area_size, $availability_id, $area_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Parking area updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update area: ' . $conn->error]);
    }
    $stmt->close();
}

/**
 * Delete a parking area
 */
function deleteArea($conn) {
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;

    if ($area_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid area ID']);
        return;
    }

    // Check if area has active bookings
    $check_sql = "SELECT b.booking_id 
                  FROM Booking b 
                  JOIN ParkingSpace ps ON b.Space_id = ps.Space_id 
                  WHERE ps.area_id = ? AND b.booking_end > NOW()";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $area_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete area with active bookings']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Delete area (parking spaces will be deleted due to ON DELETE CASCADE)
    $sql = "DELETE FROM ParkingArea WHERE area_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $area_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Parking area deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Area not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete area: ' . $conn->error]);
    }
    $stmt->close();
}

// ========== SPACE FUNCTIONS ==========

/**
 * Create a new parking space
 */
function createSpace($conn) {
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
    $space_number = isset($_POST['space_number']) ? trim($_POST['space_number']) : '';
    $qr_code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : '';
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;

    // Validate required fields
    if ($area_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);
        return;
    }
    if (empty($space_number)) {
        echo json_encode(['success' => false, 'message' => 'Space number is required']);
        return;
    }

    // Generate QR code if not provided
    if (empty($qr_code)) {
        $qr_code = 'SPACE-' . strtoupper(uniqid()) . '-' . $space_number;
    }

    // Check if space number already exists
    $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $space_number);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A space with this number already exists']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Insert new space
    $sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code, Availability_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $area_id, $space_number, $qr_code, $availability_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Parking space created successfully',
            'space_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create space: ' . $conn->error]);
    }
    $stmt->close();
}

/**
 * Update an existing parking space
 */
function updateSpace($conn) {
    $space_id = isset($_POST['space_id']) ? intval($_POST['space_id']) : 0;
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
    $space_number = isset($_POST['space_number']) ? trim($_POST['space_number']) : '';
    $qr_code = isset($_POST['qr_code']) ? trim($_POST['qr_code']) : '';
    $availability_id = isset($_POST['availability_id']) && $_POST['availability_id'] !== '' ? intval($_POST['availability_id']) : null;

    // Validate required fields
    if ($space_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid space ID']);
        return;
    }
    if ($area_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);
        return;
    }
    if (empty($space_number)) {
        echo json_encode(['success' => false, 'message' => 'Space number is required']);
        return;
    }

    // Check if space number already exists (for another space)
    $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ? AND Space_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $space_number, $space_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A space with this number already exists']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Update space
    $sql = "UPDATE ParkingSpace SET area_id = ?, space_number = ?, qr_code = ?, Availability_id = ? WHERE Space_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issii", $area_id, $space_number, $qr_code, $availability_id, $space_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Parking space updated successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update space: ' . $conn->error]);
    }
    $stmt->close();
}

/**
 * Delete a parking space
 */
function deleteSpace($conn) {
    $space_id = isset($_POST['space_id']) ? intval($_POST['space_id']) : 0;

    if ($space_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid space ID']);
        return;
    }

    // Check if space has active bookings
    $check_sql = "SELECT booking_id FROM Booking WHERE Space_id = ? AND booking_end > NOW()";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $space_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete space with active bookings']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    // Delete space
    $sql = "DELETE FROM ParkingSpace WHERE Space_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $space_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Parking space deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Space not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete space: ' . $conn->error]);
    }
    $stmt->close();
}

/**
 * Bulk create multiple parking spaces
 */
function bulkCreateSpaces($conn) {
    $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
    $prefix = isset($_POST['prefix']) ? strtoupper(trim($_POST['prefix'])) : '';
    $start_number = isset($_POST['start_number']) ? intval($_POST['start_number']) : 0;
    $end_number = isset($_POST['end_number']) ? intval($_POST['end_number']) : 0;

    // Validate required fields
    if ($area_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a parking area']);
        return;
    }
    if (empty($prefix)) {
        echo json_encode(['success' => false, 'message' => 'Space prefix is required']);
        return;
    }
    if ($start_number <= 0 || $end_number <= 0) {
        echo json_encode(['success' => false, 'message' => 'Start and end numbers must be positive']);
        return;
    }
    if ($end_number < $start_number) {
        echo json_encode(['success' => false, 'message' => 'End number must be greater than or equal to start number']);
        return;
    }
    if (($end_number - $start_number + 1) > 100) {
        echo json_encode(['success' => false, 'message' => 'Cannot create more than 100 spaces at once']);
        return;
    }

    // Start transaction
    $conn->begin_transaction();
    
    $created = 0;
    $skipped = 0;

    try {
        for ($slot = $start_number; $slot <= $end_number; $slot++) {
            $space_number = $prefix . '-' . str_pad($slot, 2, '0', STR_PAD_LEFT);
            $qr_code = 'SPACE-' . $prefix . '-' . str_pad($slot, 3, '0', STR_PAD_LEFT);
            
            // Check if space already exists
            $check_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $space_number);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Create the space
                $insert_sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iss", $area_id, $space_number, $qr_code);
                $insert_stmt->execute();
                $insert_stmt->close();
                $created++;
            } else {
                $skipped++;
            }
            $check_stmt->close();
        }
        
        $conn->commit();
        
        $message = "Created $created parking space(s)";
        if ($skipped > 0) {
            $message .= " ($skipped already existed)";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'created' => $created,
            'skipped' => $skipped
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create spaces: ' . $e->getMessage()]);
    }
}
?>

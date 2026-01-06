<?php

/**
 * =============================================================================
 * PARKING MANAGEMENT - ADMIN MODULE
 * =============================================================================
 * 
 * FILE: Module2/admin/parking_management.php
 * PURPOSE: Admin interface for managing Parking Areas and Parking Spaces
 * AUTHOR: Mawgifi System Team
 * 
 * =============================================================================
 * DESCRIPTION
 * =============================================================================
 * This file provides a complete admin dashboard for managing the parking system.
 * It allows administrators to:
 * - View all parking areas and their space counts
 * - Create, edit, and delete parking areas
 * - View all parking spaces with their status and area association
 * - Create, edit, and delete individual parking spaces
 * - Bulk create multiple parking spaces at once
 * 
 * =============================================================================
 * FILE STRUCTURE (1247 lines)
 * =============================================================================
 * 
 * SECTION 1: PHP BACKEND (Lines 1-60)
 * - Session authentication
 * - Database queries for areas, spaces, and availability
 * 
 * SECTION 2: HTML HEAD & CSS (Lines 62-850)
 * - Complete CSS styling for admin dashboard
 * - Navigation bar styles
 * - Tab component styles
 * - Card and table styles
 * - Modal dialog styles
 * - Form element styles
 * - Button and badge styles
 * - Toast notification styles
 * - Responsive design breakpoints
 * 
 * SECTION 3: HTML BODY (Lines 851-875)
 * - Navigation bar with user info
 * - Tab buttons (Areas / Spaces)
 * - Areas tab content with table and add button
 * - Spaces tab content with table and add buttons
 * 
 * SECTION 4: MODAL DIALOGS (Lines 740-870)
 * - Area modal (create/edit form)
 * - Space modal (create/edit form)
 * - Bulk space modal (create multiple spaces)
 * 
 * SECTION 5: JAVASCRIPT (Lines 876-1247)
 * - showToast(): Display notification messages
 * - openAreaModal() / closeAreaModal(): Area form modal control
 * - editArea(): Populate form for editing
 * - submitAreaForm(): AJAX submit to parking_api.php
 * - deleteArea(): Confirm and delete via AJAX
 * - openSpaceModal() / closeSpaceModal(): Space form modal control
 * - editSpace(): Populate form for editing
 * - submitSpaceForm(): AJAX submit for space
 * - deleteSpace(): Confirm and delete space
 * - openBulkSpaceModal() / closeBulkSpaceModal(): Bulk form control
 * - updateBulkPreview(): Live preview of spaces to be created
 * - submitBulkSpaceForm(): Create multiple spaces
 * - updateTotalSpaceCount(): Update UI counters
 * - validateSpaceCount(): Check against 100 space limit
 * 
 * =============================================================================
 * DATA FLOW
 * =============================================================================
 * 
 * 1. PAGE LOAD:
 *    parking_management.php -> database.php -> MySQL
 *         |                                      |
 *         |<--------- Areas, Spaces, Availability
 *         |
 *         v
 *    Render HTML tables with PHP foreach loops
 * 
 * 2. CRUD OPERATIONS:
 *    User Action (click button)
 *         |
 *         v
 *    JavaScript function (e.g., submitAreaForm)
 *         |
 *         v
 *    Fetch API -> parking_api.php
 *         |
 *         v
 *    JSON Response
 *         |
 *         v
 *    showToast() + page reload
 * 
 * =============================================================================
 * DEPENDENCIES
 * =============================================================================
 * - config/database.php: Database connection (getDBConnection())
 * - config/session.php: Session management (requireAdmin())
 * - admin/check_event_status.php: Auto-update area status based on events
 * - api/parking_api.php: REST API for all CRUD operations
 * 
 * =============================================================================
 * DATABASE TABLES USED
 * =============================================================================
 * - ParkingArea: area_id, area_name, area_type, AreaSize, area_color, area_status
 * - ParkingSpace: Space_id, area_id, space_number, qr_code, status
 * - Availability: Availability_id, date, time_range (for scheduling)
 * 
 * =============================================================================
 * SECURITY
 * =============================================================================
 * - requireAdmin(): Redirects non-admin users to login
 * - All database queries use MySQLi prepared statements (in parking_api.php)
 * - CSRF protection should be added for production
 * 
 * =============================================================================
 */

// Line 116: Include database connection helper
require_once '../../config/database.php';
// Line 117: Include session management and authentication
require_once '../../config/session.php';
// Line 118: Include event status checker (auto-updates area status)
require_once 'check_event_status.php';

// -----------------------------------------------------------------------------
// AUTHENTICATION CHECK
// -----------------------------------------------------------------------------
// Line 124: Verify user is logged in as admin, redirect to login if not
requireAdmin();

// Line 127: Get username from session for display, default to 'Administrator'
$username = $_SESSION['username'] ?? 'Administrator';
// Line 128: Establish database connection
$conn = getDBConnection();

// -----------------------------------------------------------------------------
// FETCH PARKING AREAS WITH SPACE COUNTS
// -----------------------------------------------------------------------------
// Query all areas and count how many spaces each area has using a subquery

$areas = [];  // Line 135: Initialize empty array for areas
if ($conn) {  // Line 136: Only proceed if connection successful
    // Line 137-140: SQL query to get all areas with space count
    // Uses correlated subquery to count spaces for each area
    // SELECT pa.* - Select all area columns
    // (SELECT COUNT(*) ...) as space_count - Count spaces per area
    // FROM ParkingArea pa - From ParkingArea table
    // ORDER BY pa.area_name - Sort alphabetically
    $sql = "SELECT pa.*, 
            (SELECT COUNT(*) FROM ParkingSpace ps WHERE ps.area_id = pa.area_id) as space_count
            FROM ParkingArea pa 
            ORDER BY pa.area_name";
    $result = $conn->query($sql);  // Line 142: Execute query
    if ($result) {  // Line 143: If query succeeded
        while ($row = $result->fetch_assoc()) {  // Line 144: Loop through results
            $areas[] = $row;  // Line 145: Add each area to array
        }
    }
}

// -----------------------------------------------------------------------------
// FETCH PARKING SPACES WITH AREA INFORMATION
// -----------------------------------------------------------------------------
// Query all spaces and JOIN with areas to get the area name for display

$spaces = [];  // Line 154: Initialize empty array for spaces
if ($conn) {  // Line 155: Only proceed if connection successful
    // Line 156-159: SQL query to get spaces with their area names
    // SELECT ps.*, pa.area_name - Select space columns and area name
    // FROM ParkingSpace ps - From ParkingSpace table
    // LEFT JOIN ParkingArea pa - Join to get area info
    // ORDER BY pa.area_name, ps.space_number - Sort by area, then space number
    $sql = "SELECT ps.*, pa.area_name 
            FROM ParkingSpace ps 
            LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id 
            ORDER BY pa.area_name, ps.space_number";
    $result = $conn->query($sql);  // Line 161: Execute query
    if ($result) {  // Line 162: If query succeeded
        while ($row = $result->fetch_assoc()) {  // Line 163: Loop through results
            $spaces[] = $row;  // Line 164: Add each space to array
        }
    }
}

// -----------------------------------------------------------------------------
// FETCH AVAILABILITY OPTIONS
// -----------------------------------------------------------------------------
// Query availability records for dropdown selection in area form

$availabilities = [];  // Line 173: Initialize empty array for availabilities
if ($conn) {  // Line 174: Only proceed if connection successful
    $sql = "SELECT * FROM Availability ORDER BY date DESC";  // Line 175: Get all, newest first
    $result = $conn->query($sql);  // Line 176: Execute query
    if ($result) {  // Line 177: If query succeeded
        while ($row = $result->fetch_assoc()) {  // Line 178: Loop through results
            $availabilities[] = $row;  // Line 179: Add each availability to array
        }
    }
}

// Line 183: Close database connection to free resources
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Management - Mawgifi Admin</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
            --success: #48bb78;
            --danger: #e53e3e;
            --warning: #ed8936;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-light);
        }

        .navbar {
            background: var(--primary-grad);
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar .brand {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: white;
            color: #6a67ce;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .avatar-circle {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .logout-btn {
            background: rgba(0, 0, 0, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 18px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #e53e3e;
            border-color: #e53e3e;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--text-dark);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .tab-btn {
            padding: 12px 30px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .tab-btn.active {
            background: var(--primary-grad);
            color: white;
            border-color: transparent;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 1.3rem;
            color: var(--text-dark);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-grad);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-primary {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }

        .badge-success {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(237, 137, 54, 0.1);
            color: var(--warning);
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-light);
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 2000;
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-light);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
            <a href="../../modules/membership/index.php">Vehicles</a>
            <a href="../public/index.php">Parking Map</a>
            <a href="parking_management.php" class="active">Manage Parking</a>
            <a href="../../admin/event_management.php">Events</a>
            <a href="../../modules/booking/index.php">Bookings</a>
            <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
            <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
            <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>🅿️ Parking Management</h1>
            <div style="background: #fef3c7; padding: 15px; border-radius: 10px; border-left: 4px solid #f59e0b;">
                <strong>⚠️ Total Parking Limit:</strong> <span id="totalSpaceCount">0</span> / 100 spaces used
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button type="button" class="tab-btn active" data-tab="areas">Parking Areas</button>
            <button type="button" class="tab-btn" data-tab="spaces">Parking Spaces</button>
        </div>

        <!-- Parking Areas Tab -->
        <div id="areas-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2>All Parking Areas</h2>
                    <button type="button" class="btn btn-primary" onclick="openAreaModal()">
                        ➕ Add New Area
                    </button>
                </div>
                <div class="table-container">
                    <?php if (empty($areas)): ?>
                        <div class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p>No parking areas found. Click "Add New Area" to create one.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Area Name</th>
                                    <th>Area Type</th>
                                    <th>Area Size</th>
                                    <th>Status</th>
                                    <th>Color</th>
                                    <th>Spaces</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areas as $area): ?>
                                    <tr>
                                        <td><?php echo $area['area_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($area['area_name']); ?></strong></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo htmlspecialchars($area['area_type'] ?? 'Standard'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $area['AreaSize'] ? $area['AreaSize'] . ' m²' : 'N/A'; ?></td>
                                        <td>
                                            <?php
                                            $status = $area['area_status'] ?? 'available';
                                            $statusColor = 'success';
                                            $statusText = ucwords(str_replace('_', ' ', $status));
                                            if (in_array($status, ['occupied', 'temporarily_closed', 'under_maintenance'])) {
                                                $statusColor = 'danger';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $statusColor; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <div style="width: 30px; height: 30px; background-color: <?php echo htmlspecialchars($area['area_color'] ?? '#a0a0a0'); ?>; border-radius: 5px; border: 2px solid #e2e8f0;"></div>
                                        </td>
                                        <td><span class="badge badge-success"><?php echo $area['space_count']; ?> spaces</span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($area['created_at'])); ?></td>
                                        <td class="action-btns">
                                            <button type="button" class="btn btn-primary btn-sm"
                                                onclick='editArea(<?php echo htmlspecialchars(json_encode($area), ENT_QUOTES, "UTF-8"); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="deleteArea(<?php echo $area['area_id']; ?>, '<?php echo htmlspecialchars($area['area_name'], ENT_QUOTES); ?>')">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Parking Spaces Tab -->
        <div id="spaces-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>All Parking Spaces</h2>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-success" onclick="openBulkSpaceModal()">
                            📦 Bulk Create Spaces
                        </button>
                        <button type="button" class="btn btn-primary" onclick="openSpaceModal()">
                            ➕ Add New Space
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <?php if (empty($spaces)): ?>
                        <div class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <p>No parking spaces found. Click "Add New Space" to create one.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Space Number</th>
                                    <th>Area</th>
                                    <th>Status</th>
                                    <th>QR Code</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spaces as $space): ?>
                                    <tr>
                                        <td><?php echo $space['Space_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($space['space_number']); ?></strong></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo htmlspecialchars($space['area_name'] ?? 'Unassigned'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $space['status'] ?? 'available';
                                            $status_color = 'success';
                                            if (in_array($status, ['occupied', 'reserved', 'maintenance'])) {
                                                $status_color = 'danger';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $status_color; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($space['qr_code']): ?>
                                                <span class="badge badge-success">Has QR</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">No QR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($space['created_at'])); ?></td>
                                        <td class="action-btns">
                                            <button type="button" class="btn btn-primary btn-sm"
                                                onclick='editSpace(<?php echo htmlspecialchars(json_encode($space), ENT_QUOTES, "UTF-8"); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="deleteSpace(<?php echo $space['Space_id']; ?>, '<?php echo htmlspecialchars($space['space_number'], ENT_QUOTES); ?>')">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Area Modal -->
    <div class="modal" id="areaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="areaModalTitle">Add New Parking Area</h3>
                <button type="button" class="modal-close" onclick="closeAreaModal()">&times;</button>
            </div>
            <form id="areaForm" onsubmit="submitAreaForm(event)">
                <div class="modal-body">
                    <input type="hidden" id="area_id" name="area_id" value="">

                    <div class="form-group">
                        <label for="area_name">Area Name *</label>
                        <input type="text" id="area_name" name="area_name" placeholder="e.g., Area A, Zone 1" required>
                    </div>

                    <div class="form-group">
                        <label for="area_type">Area Type</label>
                        <select id="area_type" name="area_type">
                            <option value="Standard">Standard</option>
                            <option value="VIP">VIP</option>
                            <option value="Handicapped">Handicapped</option>
                            <option value="Electric Vehicle">Electric Vehicle</option>
                            <option value="Motorcycle">Motorcycle</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="area_size">Area Size (m²)</label>
                        <input type="number" id="area_size" name="area_size" step="0.01" placeholder="e.g., 500.00">
                    </div>

                    <div class="form-group">
                        <label for="area_color">Area Color for Map *</label>
                        <input type="color" id="area_color" name="area_color" value="#a0a0a0" required>
                        <small style="color: #718096;">This color will be used to display this area on the parking map</small>
                    </div>

                    <div class="form-group">
                        <label for="area_status">Area Status</label>
                        <select id="area_status" name="area_status">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="temporarily_closed">Temporarily Closed</option>
                            <option value="under_maintenance">Under Maintenance</option>
                        </select>
                        <small style="color: #718096;">Occupied, Temporarily Closed, and Under Maintenance areas will show as red on the map</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeAreaModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Area</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Space Modal -->
    <div class="modal" id="spaceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="spaceModalTitle">Add New Parking Space</h3>
                <button type="button" class="modal-close" onclick="closeSpaceModal()">&times;</button>
            </div>
            <form id="spaceForm" onsubmit="submitSpaceForm(event)">
                <div class="modal-body">
                    <input type="hidden" id="space_id" name="space_id" value="">

                    <div class="form-group">
                        <label for="space_area_id">Parking Area *</label>
                        <select id="space_area_id" name="area_id" required>
                            <option value="">-- Select Area --</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['area_id']; ?>">
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="space_number">Space Number *</label>
                        <input type="text" id="space_number" name="space_number" placeholder="e.g., A-01, B-15"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="qr_code">QR Code Preview</label>
                        <div id="qr_preview_container" style="margin: 10px 0; text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px; display: none;">
                            <img id="qr_preview_image" src="" alt="QR Code" style="width: 150px; height: 150px; border-radius: 8px; border: 2px solid #e2e8f0;">
                            <p style="margin-top: 10px; color: #718096; font-size: 13px;">Scan this QR at the parking slot</p>
                        </div>
                        <input type="hidden" id="qr_code" name="qr_code" placeholder="Leave empty to auto-generate">
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="reserved">Reserved</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                        <small style="color: #718096;">Space status - shows RED on map when not available</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeSpaceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Space</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Create Spaces Modal -->
    <div class="modal" id="bulkSpaceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Create Parking Spaces</h3>
                <button type="button" class="modal-close" onclick="closeBulkSpaceModal()">&times;</button>
            </div>
            <form id="bulkSpaceForm" onsubmit="submitBulkSpaceForm(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="bulk_area_id">Parking Area *</label>
                        <select id="bulk_area_id" name="area_id" required>
                            <option value="">-- Select Area --</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['area_id']; ?>">
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bulk_prefix">Space Prefix *</label>
                        <input type="text" id="bulk_prefix" name="prefix" placeholder="e.g., A, B, C" required
                            maxlength="5">
                        <small style="color: #718096;">This will be used before the slot number (e.g., A-01)</small>
                    </div>

                    <div class="form-group">
                        <label for="bulk_start">Start Number *</label>
                        <input type="number" id="bulk_start" name="start_number" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label for="bulk_end">End Number *</label>
                        <input type="number" id="bulk_end" name="end_number" min="1" value="14" required>
                    </div>

                    <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 10px;">
                        <strong>Preview:</strong>
                        <span id="bulk_preview">A-01 to A-14 (14 spaces)</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeBulkSpaceModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Spaces</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>

    <script>
        // =====================================================================
        // JAVASCRIPT SECTION - PARKING MANAGEMENT ADMIN
        // =====================================================================
        // 
        // This section contains all client-side JavaScript for the parking
        // management admin interface. Functions are organized into:
        // 1. Utility Functions (toast notifications)
        // 2. Tab Navigation
        // 3. Area Management (CRUD)
        // 4. Space Management (CRUD)
        // 5. Bulk Space Creation
        // 6. Validation and Statistics
        // 
        // All API calls use fetch() to communicate with parking_api.php
        // =====================================================================

        // =====================================================================
        // TAB NAVIGATION
        // =====================================================================
        // Handles switching between "Parking Areas" and "Parking Spaces" tabs
        // Uses data-tab attribute on buttons to identify which tab to show

        document.querySelectorAll('.tab-btn').forEach(btn => { // Line: Loop through all tab buttons
            btn.addEventListener('click', () => { // Line: Add click handler to each
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active')); // Line: Deactivate all tab buttons
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active')); // Line: Hide all tab content

                // Add active class to clicked tab and its content
                btn.classList.add('active'); // Line: Activate clicked button
                document.getElementById(btn.dataset.tab + '-tab').classList.add('active'); // Line: Show corresponding content
            });
        });

        // =====================================================================
        // UTILITY FUNCTION: showToast()
        // =====================================================================
        /**
         * Display a toast notification message to the user
         * 
         * @param {string} message - The message to display
         * @param {string} type - 'success' (green) or 'error' (red)
         * 
         * The toast appears at the bottom-right and auto-hides after 3 seconds
         */
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast'); // Line: Get toast element
            toast.textContent = message; // Line: Set message text
            toast.className = 'toast ' + type + ' show'; // Line: Add type class and show class
            setTimeout(() => toast.classList.remove('show'), 3000); // Line: Auto-hide after 3 seconds
        }

        // =====================================================================
        // AREA MANAGEMENT FUNCTIONS
        // =====================================================================
        // Functions for Create, Read, Update, Delete operations on parking areas

        /**
         * Open the area modal for creating a new area
         * Resets the form and clears the area_id (indicates new record)
         */
        function openAreaModal() {
            document.getElementById('areaModalTitle').textContent = 'Add New Parking Area'; // Line: Set modal title
            document.getElementById('areaForm').reset(); // Line: Clear all form fields
            document.getElementById('area_id').value = ''; // Line: Clear hidden area_id (new mode)
            document.getElementById('areaModal').classList.add('show'); // Line: Display modal
        }

        /**
         * Close the area modal dialog
         */
        function closeAreaModal() {
            document.getElementById('areaModal').classList.remove('show'); // Line: Hide modal
        }

        /**
         * Open the area modal pre-filled for editing an existing area
         * 
         * @param {Object} area - Area object with properties:
         *   - area_id: Database ID
         *   - area_name: Display name
         *   - area_type: Type category
         *   - AreaSize: Numeric size
         *   - area_color: Hex color code
         *   - area_status: Status string
         */
        function editArea(area) {
            document.getElementById('areaModalTitle').textContent = 'Edit Parking Area'; // Line: Set modal title for edit
            document.getElementById('area_id').value = area.area_id; // Line: Set hidden ID (edit mode)
            document.getElementById('area_name').value = area.area_name; // Line: Pre-fill area name
            document.getElementById('area_type').value = area.area_type || 'Standard'; // Line: Pre-fill type with default
            document.getElementById('area_size').value = area.AreaSize || ''; // Line: Pre-fill size
            document.getElementById('area_color').value = area.area_color || '#a0a0a0'; // Line: Pre-fill color with default gray
            document.getElementById('area_status').value = area.area_status || 'available'; // Line: Pre-fill status
            document.getElementById('areaModal').classList.add('show'); // Line: Display modal
        }

        /**
         * Handle area form submission (create or update)
         * 
         * FLOW:
         * 1. Prevent default form submit
         * 2. Build FormData from form fields
         * 3. Determine action (create or update) based on area_id presence
         * 4. Send POST request to parking_api.php
         * 5. Parse JSON response
         * 6. Show toast and reload page on success
         * 
         * @param {Event} e - Form submit event
         */
        async function submitAreaForm(e) {
            e.preventDefault(); // Line: Stop form from traditional submit
            const formData = new FormData(document.getElementById('areaForm')); // Line: Collect all form fields
            const areaId = formData.get('area_id'); // Line: Check if area_id exists

            // Line: Append action based on whether this is create or update
            formData.append('action', areaId ? 'update' : 'create');

            try {
                // Line: Send POST request to API
                const response = await fetch('../api/parking_api.php?type=area', {
                    method: 'POST', // Line: HTTP method
                    body: formData // Line: Form data as body
                });
                const text = await response.text(); // Line: Get raw response text

                let data; // Line: Variable for parsed JSON
                try {
                    data = JSON.parse(text); // Line: Parse JSON response
                } catch (parseError) {
                    showToast('Server error: Invalid response', 'error'); // Line: Show error to user
                    return; // Line: Exit function
                }

                // Line: Handle success or error from API
                if (data.success) {
                    showToast(data.message, 'success'); // Line: Show success message
                    closeAreaModal(); // Line: Close the modal
                    setTimeout(() => location.reload(), 1000); // Line: Reload page after 1 second
                } else {
                    showToast(data.message, 'error'); // Line: Show error message
                }
            } catch (error) {
                showToast('An error occurred: ' + error.message, 'error'); // Line: Show error to user
            }
        }

        /**
         * Delete a parking area after confirmation
         * 
         * WARNING: This will cascade delete all parking spaces in the area
         * 
         * @param {number} areaId - Database ID of area to delete
         * @param {string} areaName - Name for confirmation dialog
         */
        async function deleteArea(areaId, areaName) {
            // Line: Show confirmation dialog with warning about cascade delete
            if (!confirm(`Are you sure you want to delete "${areaName}"? This will also delete all parking spaces in this area.`)) {
                return; // Line: User cancelled, exit function
            }

            try {
                // Line: Send POST request to delete endpoint
                const response = await fetch('../api/parking_api.php?type=area', {
                    method: 'POST', // Line: HTTP method
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }, // Line: Form content type
                    body: `action=delete&area_id=${areaId}` // Line: URL-encoded body
                });
                const text = await response.text(); // Line: Get raw response text

                let data; // Line: Variable for parsed JSON
                try {
                    data = JSON.parse(text); // Line: Parse JSON response
                } catch (parseError) {
                    showToast('Server error: Invalid response', 'error'); // Line: Show error to user
                    return; // Line: Exit function
                }

                // Line: Handle success or error from API
                if (data.success) {
                    showToast(data.message, 'success'); // Line: Show success message
                    setTimeout(() => location.reload(), 1000); // Line: Reload page after 1 second
                } else {
                    showToast(data.message, 'error'); // Line: Show error message
                }
            } catch (error) {
                showToast('An error occurred: ' + error.message, 'error'); // Line: Show error to user
            }
        }

        // =====================================================================
        // SPACE MANAGEMENT FUNCTIONS
        // =====================================================================
        // Functions for Create, Read, Update, Delete operations on parking spaces

        /**
         * Open the space modal for creating a new parking space
         * Resets the form and clears the space_id (indicates new record)
         */
        function openSpaceModal() {
            document.getElementById('spaceModalTitle').textContent = 'Add New Parking Space'; // Line: Set modal title
            document.getElementById('spaceForm').reset(); // Line: Clear all form fields
            document.getElementById('space_id').value = ''; // Line: Clear hidden space_id (new mode)
            document.getElementById('spaceModal').classList.add('show'); // Line: Display modal
        }

        /**
         * Close the space modal dialog
         */
        function closeSpaceModal() {
            document.getElementById('spaceModal').classList.remove('show'); // Line: Hide modal
        }

        /**
         * Open the space modal pre-filled for editing an existing space
         * Also generates and displays a QR code preview for the space
         * 
         * @param {Object} space - Space object with properties:
         *   - Space_id: Database ID
         *   - area_id: Parent area ID
         *   - space_number: Display number (e.g., "A-01")
         *   - qr_code: QR code string
         *   - status: Current status
         */
        function editSpace(space) {
            document.getElementById('spaceModalTitle').textContent = 'Edit Parking Space'; // Line: Set modal title for edit
            document.getElementById('space_id').value = space.Space_id; // Line: Set hidden ID (edit mode)
            document.getElementById('space_area_id').value = space.area_id; // Line: Pre-fill area dropdown
            document.getElementById('space_number').value = space.space_number; // Line: Pre-fill space number
            document.getElementById('qr_code').value = space.qr_code || ''; // Line: Pre-fill QR code
            document.getElementById('status').value = space.status || 'available'; // Line: Pre-fill status

            // -------------------------------------------------------------------
            // Generate QR Code Preview using external QR API
            // -------------------------------------------------------------------
            // The QR code encodes the scan.php URL with the space number
            const baseUrl = window.location.origin + '/mawgifi_system'; // Line: Get base URL
            const qrData = encodeURIComponent(baseUrl + '/modules/booking/scan.php?slot=' + space.space_number); // Line: Encode scan URL
            const qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + qrData; // Line: Build QR API URL
            document.getElementById('qr_preview_image').src = qrCodeUrl; // Line: Set image source
            document.getElementById('qr_preview_container').style.display = 'block'; // Line: Show QR container

            document.getElementById('spaceModal').classList.add('show'); // Line: Display modal
        }

        /**
         * Handle space form submission (create or update)
         * 
         * FLOW:
         * 1. Prevent default form submit
         * 2. For new spaces, validate against 100 space limit
         * 3. Build FormData from form fields
         * 4. Determine action (create or update) based on space_id presence
         * 5. Send POST request to parking_api.php
         * 6. Parse JSON response
         * 7. Show toast and reload page on success
         * 
         * @param {Event} e - Form submit event
         */
        async function submitSpaceForm(e) {
            e.preventDefault(); // Line: Stop form from traditional submit

            // -------------------------------------------------------------------
            // Validate space count before creating new space
            // -------------------------------------------------------------------
            const spaceId = document.getElementById('space_id').value; // Line: Get space ID
            if (!spaceId) { // Line: Only validate for new spaces (not updates)
                const validation = await validateSpaceCount(1); // Line: Check if 1 more space is allowed
                if (!validation.valid) { // Line: If validation failed
                    showToast(validation.message, 'error'); // Line: Show error message
                    return; // Line: Exit function
                }
            }

            const formData = new FormData(document.getElementById('spaceForm')); // Line: Collect all form fields
            formData.append('action', spaceId ? 'update' : 'create'); // Line: Append action type

            try {
                // Line: Send POST request to API
                const response = await fetch('../api/parking_api.php?type=space', {
                    method: 'POST', // Line: HTTP method
                    body: formData // Line: Form data as body
                });
                const text = await response.text(); // Line: Get raw response text

                let data; // Line: Variable for parsed JSON
                try {
                    data = JSON.parse(text); // Line: Parse JSON response
                } catch (parseError) {
                    showToast('Server error: Invalid response', 'error'); // Line: Show error to user
                    return; // Line: Exit function
                }

                // Line: Handle success or error from API
                if (data.success) {
                    showToast(data.message, 'success'); // Line: Show success message
                    closeSpaceModal(); // Line: Close the modal
                    setTimeout(() => location.reload(), 1000); // Line: Reload page after 1 second
                } else {
                    showToast(data.message, 'error'); // Line: Show error message
                }
            } catch (error) {
                showToast('An error occurred: ' + error.message, 'error'); // Line: Show error to user
            }
        }

        /**
         * Delete a parking space after confirmation
         * 
         * @param {number} spaceId - Database ID of space to delete
         * @param {string} spaceNumber - Display number for confirmation dialog
         */
        async function deleteSpace(spaceId, spaceNumber) {
            // Line: Show confirmation dialog
            if (!confirm(`Are you sure you want to delete space "${spaceNumber}"?`)) {
                return; // Line: User cancelled, exit function
            }

            try {
                // Line: Send POST request to delete endpoint
                const response = await fetch('../api/parking_api.php?type=space', {
                    method: 'POST', // Line: HTTP method
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }, // Line: Form content type
                    body: `action=delete&space_id=${spaceId}` // Line: URL-encoded body
                });
                const text = await response.text(); // Line: Get raw response text

                let data; // Line: Variable for parsed JSON
                try {
                    data = JSON.parse(text); // Line: Parse JSON response
                } catch (parseError) {
                    showToast('Server error: Invalid response', 'error'); // Line: Show error to user
                    return; // Line: Exit function
                }

                // Line: Handle success or error from API
                if (data.success) {
                    showToast(data.message, 'success'); // Line: Show success message
                    setTimeout(() => location.reload(), 1000); // Line: Reload page after 1 second
                } else {
                    showToast(data.message, 'error'); // Line: Show error message
                }
            } catch (error) {
                showToast('An error occurred', 'error'); // Line: Show generic error
            }
        }

        // =====================================================================
        // BULK SPACE CREATION FUNCTIONS
        // =====================================================================
        // Functions for creating multiple parking spaces at once with sequential numbering

        /**
         * Open the bulk space creation modal
         * Resets the form and updates the preview display
         */
        function openBulkSpaceModal() {
            document.getElementById('bulkSpaceForm').reset(); // Line: Clear all form fields
            updateBulkPreview(); // Line: Update the preview text
            document.getElementById('bulkSpaceModal').classList.add('show'); // Line: Display modal
        }

        /**
         * Close the bulk space creation modal
         */
        function closeBulkSpaceModal() {
            document.getElementById('bulkSpaceModal').classList.remove('show'); // Line: Hide modal
        }

        /**
         * Update the bulk creation preview text
         * Shows what spaces will be created based on current form values
         * 
         * Example output: "A-01 to A-14 (14 spaces)"
         * 
         * Called automatically when prefix, start, or end inputs change
         */
        function updateBulkPreview() {
            const prefix = document.getElementById('bulk_prefix').value || 'X'; // Line: Get prefix with fallback
            const start = parseInt(document.getElementById('bulk_start').value) || 1; // Line: Parse start number
            const end = parseInt(document.getElementById('bulk_end').value) || 1; // Line: Parse end number
            const count = Math.max(0, end - start + 1); // Line: Calculate number of spaces (minimum 0)

            // Line: Format start and end numbers with zero-padding
            const startStr = prefix + '-' + String(start).padStart(2, '0'); // Line: e.g., "A-01"
            const endStr = prefix + '-' + String(end).padStart(2, '0'); // Line: e.g., "A-14"

            // Line: Update preview text in modal
            document.getElementById('bulk_preview').textContent =
                `${startStr} to ${endStr} (${count} spaces)`;
        }

        // -------------------------------------------------------------------
        // EVENT LISTENERS FOR LIVE PREVIEW UPDATE
        // -------------------------------------------------------------------
        // Automatically update preview when user types in any field
        document.getElementById('bulk_prefix').addEventListener('input', updateBulkPreview); // Line: Listen for prefix changes
        document.getElementById('bulk_start').addEventListener('input', updateBulkPreview); // Line: Listen for start changes
        document.getElementById('bulk_end').addEventListener('input', updateBulkPreview); // Line: Listen for end changes

        /**
         * Handle bulk space creation form submission
         * 
         * FLOW:
         * 1. Prevent default form submit
         * 2. Calculate number of spaces to create
         * 3. Validate against 100 space limit
         * 4. Build FormData with bulk_create action
         * 5. Send POST request to parking_api.php
         * 6. Parse JSON response
         * 7. Show toast and reload page on success
         * 
         * @param {Event} e - Form submit event
         */
        async function submitBulkSpaceForm(e) {
            e.preventDefault(); // Line: Stop form from traditional submit

            // -------------------------------------------------------------------
            // Calculate space count and validate
            // -------------------------------------------------------------------
            const start = parseInt(document.getElementById('bulk_start').value) || 1; // Line: Get start number
            const end = parseInt(document.getElementById('bulk_end').value) || 1; // Line: Get end number
            const count = Math.max(0, end - start + 1); // Line: Calculate number of spaces

            const validation = await validateSpaceCount(count); // Line: Check if count is allowed
            if (!validation.valid) { // Line: If validation failed
                showToast(validation.message, 'error'); // Line: Show error message
                return; // Line: Exit function
            }

            const formData = new FormData(document.getElementById('bulkSpaceForm')); // Line: Collect all form fields
            formData.append('action', 'bulk_create'); // Line: Set action to bulk_create

            try {
                // Line: Send POST request to API
                const response = await fetch('../api/parking_api.php?type=space', {
                    method: 'POST', // Line: HTTP method
                    body: formData // Line: Form data as body
                });
                const text = await response.text(); // Line: Get raw response text

                let data; // Line: Variable for parsed JSON
                try {
                    data = JSON.parse(text); // Line: Parse JSON response
                } catch (parseError) {
                    showToast('Server error: Invalid response', 'error'); // Line: Show error to user
                    return; // Line: Exit function
                }

                // Line: Handle success or error from API
                if (data.success) {
                    showToast(data.message, 'success'); // Line: Show success message (includes created/skipped counts)
                    closeBulkSpaceModal(); // Line: Close the modal
                    setTimeout(() => location.reload(), 1000); // Line: Reload page after 1 second
                } else {
                    showToast(data.message, 'error'); // Line: Show error message
                }
            } catch (error) {
                showToast('An error occurred: ' + error.message, 'error'); // Line: Show error to user
            }
        }

        // -------------------------------------------------------------------
        // MODAL CLOSE ON OUTSIDE CLICK
        // -------------------------------------------------------------------
        // Close any modal when user clicks outside the modal content area
        document.querySelectorAll('.modal').forEach(modal => { // Line: Loop through all modals
            modal.addEventListener('click', (e) => { // Line: Add click handler
                if (e.target === modal) { // Line: If click was on backdrop (not content)
                    modal.classList.remove('show'); // Line: Hide the modal
                }
            });
        });

        // =====================================================================
        // STATISTICS AND VALIDATION FUNCTIONS
        // =====================================================================
        // Functions for fetching statistics and validating space limits

        /**
         * Fetch and display total space count from API
         * Updates the counter UI and changes background color based on usage:
         * - Green: Under 90 spaces
         * - Yellow: 90-99 spaces (warning)
         * - Red: 100 spaces (at limit)
         */
        function updateTotalSpaceCount() {
            fetch('../api/parking_api.php?type=stats') // Line: Fetch stats from API
                .then(response => response.json()) // Line: Parse JSON response
                .then(data => {
                    if (data.success) { // Line: If API call succeeded
                        const totalSpaces = data.total_spaces || 0; // Line: Get total count with default 0
                        const countElement = document.getElementById('totalSpaceCount'); // Line: Get counter element
                        countElement.textContent = totalSpaces; // Line: Update displayed count

                        // -----------------------------------------------------------
                        // Change background color based on usage level
                        // -----------------------------------------------------------
                        const parent = countElement.parentElement.parentElement; // Line: Get parent container
                        if (totalSpaces >= 100) { // Line: At limit
                            parent.style.background = '#fee2e2'; // Line: Red background
                            parent.style.borderColor = '#ef4444'; // Line: Red border
                        } else if (totalSpaces >= 90) { // Line: Near limit
                            parent.style.background = '#fef3c7'; // Line: Yellow background
                            parent.style.borderColor = '#f59e0b'; // Line: Yellow border
                        } else { // Line: Under 90
                            parent.style.background = '#d1fae5'; // Line: Green background
                            parent.style.borderColor = '#10b981'; // Line: Green border
                        }
                    }
                })
                .catch(error => {}); // Line: Handle any errors silently
        }

        /**
         * Validate if creating additional spaces would exceed the 100 space limit
         * 
         * FLOW:
         * 1. Fetch current space count from API
         * 2. Check if current + new would exceed 100
         * 3. Return validation result object
         * 
         * @param {number} newSpaceCount - Number of spaces to be created
         * @returns {Object} { valid: boolean, message: string }
         */
        async function validateSpaceCount(newSpaceCount) {
            try {
                const response = await fetch('../api/parking_api.php?type=stats'); // Line: Fetch stats
                const data = await response.json(); // Line: Parse JSON
                if (data.success) { // Line: If API succeeded
                    const currentTotal = data.total_spaces || 0; // Line: Get current count
                    if (currentTotal + newSpaceCount > 100) { // Line: Would exceed limit?
                        return { // Line: Return failure result
                            valid: false, // Line: Validation failed
                            message: `Cannot create ${newSpaceCount} space(s). Current total: ${currentTotal}, would exceed 100 space limit.` // Line: Error message
                        };
                    }
                    return {
                        valid: true
                    }; // Line: Validation passed
                }
            } catch (error) {
                // Handle validation errors silently
            }
            return {
                valid: true
            }; // Line: Allow if validation fails (fail-open)
        }

        // =====================================================================
        // INITIALIZATION
        // =====================================================================
        // Run on page load

        updateTotalSpaceCount(); // Line: Fetch and display initial space count
    </script>
</body>

</html>
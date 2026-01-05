<?php
/**
 * Parking Management - Admin Module
 * This page allows admins to manage Parking Areas and Parking Spaces
 * CRUD operations: Create, Read, Update, Delete
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once 'check_event_status.php';

// Require admin access
requireAdmin();

$username = $_SESSION['username'] ?? 'Administrator';
$conn = getDBConnection();

// Fetch all parking areas with space count
$areas = [];
if ($conn) {
    $sql = "SELECT pa.*, 
            (SELECT COUNT(*) FROM ParkingSpace ps WHERE ps.area_id = pa.area_id) as space_count
            FROM ParkingArea pa 
            ORDER BY pa.area_name";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $areas[] = $row;
        }
    }
}

// Fetch all parking spaces with area info
$spaces = [];
if ($conn) {
    $sql = "SELECT ps.*, pa.area_name 
            FROM ParkingSpace ps 
            LEFT JOIN ParkingArea pa ON ps.area_id = pa.area_id 
            ORDER BY pa.area_name, ps.space_number";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $spaces[] = $row;
        }
    }
}

// Fetch availability options
$availabilities = [];
if ($conn) {
    $sql = "SELECT * FROM Availability ORDER BY date DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $availabilities[] = $row;
        }
    }
}

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
            <a href="../Moudel1/Admin.php?view=dashboard">Dashboard</a>
            <a href="../modules/membership/index.php">Vehicles</a>
            <a href="../modules/parking/index.php">Parking Map</a>
            <a href="parking_management.php" class="active">Manage Parking</a>
            <a href="event_management.php">Events</a>
            <a href="../modules/booking/index.php">Bookings</a>
            <a href="../Moudel1/Admin.php?view=register">Register Student</a>
            <a href="../Moudel1/Admin.php?view=manage">Manage Profile</a>
            <a href="../Moudel1/Admin.php?view=profile">Profile</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
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
        // Debug - verify script is loading
        console.log('Parking Management JS Loaded');

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                console.log('Tab clicked:', btn.dataset.tab);
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                btn.classList.add('active');
                document.getElementById(btn.dataset.tab + '-tab').classList.add('active');
            });
        });

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // ========== AREA FUNCTIONS ==========

        function openAreaModal() {
            console.log('Opening area modal');
            document.getElementById('areaModalTitle').textContent = 'Add New Parking Area';
            document.getElementById('areaForm').reset();
            document.getElementById('area_id').value = '';
            document.getElementById('areaModal').classList.add('show');
        }

        function closeAreaModal() {
            document.getElementById('areaModal').classList.remove('show');
        }

        function editArea(area) {
            console.log('Editing area:', area);
            document.getElementById('areaModalTitle').textContent = 'Edit Parking Area';
            document.getElementById('area_id').value = area.area_id;
            document.getElementById('area_name').value = area.area_name;
            document.getElementById('area_type').value = area.area_type || 'Standard';
            document.getElementById('area_size').value = area.AreaSize || '';
            document.getElementById('area_color').value = area.area_color || '#a0a0a0';
            document.getElementById('area_status').value = area.area_status || 'available';
            document.getElementById('areaModal').classList.add('show');
        }

        async function submitAreaForm(e) {
            e.preventDefault();
            console.log('Submitting area form');
            const formData = new FormData(document.getElementById('areaForm'));
            const areaId = formData.get('area_id');

            formData.append('action', areaId ? 'update' : 'create');

            try {
                const response = await fetch('parking_api.php?type=area', {
                    method: 'POST',
                    body: formData
                });
                console.log('Response status:', response.status);
                const text = await response.text();
                console.log('Response text:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    showToast('Server error: Invalid response', 'error');
                    return;
                }

                if (data.success) {
                    showToast(data.message, 'success');
                    closeAreaModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showToast('An error occurred: ' + error.message, 'error');
            }
        }

        async function deleteArea(areaId, areaName) {
            if (!confirm(`Are you sure you want to delete "${areaName}"? This will also delete all parking spaces in this area.`)) {
                return;
            }

            try {
                const response = await fetch('parking_api.php?type=area', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&area_id=${areaId}`
                });
                console.log('Delete area response status:', response.status);
                const text = await response.text();
                console.log('Delete area response:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    showToast('Server error: Invalid response', 'error');
                    return;
                }

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showToast('An error occurred: ' + error.message, 'error');
            }
        }
        // ========== SPACE FUNCTIONS ==========

        function openSpaceModal() {
            console.log('Opening space modal');
            document.getElementById('spaceModalTitle').textContent = 'Add New Parking Space';
            document.getElementById('spaceForm').reset();
            document.getElementById('space_id').value = '';
            document.getElementById('spaceModal').classList.add('show');
        }

        function closeSpaceModal() {
            document.getElementById('spaceModal').classList.remove('show');
        }

        function editSpace(space) {
            console.log('Editing space:', space);
            document.getElementById('spaceModalTitle').textContent = 'Edit Parking Space';
            document.getElementById('space_id').value = space.Space_id;
            document.getElementById('space_area_id').value = space.area_id;
            document.getElementById('space_number').value = space.space_number;
            document.getElementById('qr_code').value = space.qr_code || '';
            document.getElementById('status').value = space.status || 'available';
            
            // Generate and show QR code preview
            const baseUrl = window.location.origin + '/mawgifi_system';
            const qrData = encodeURIComponent(baseUrl + '/modules/booking/scan.php?slot=' + space.space_number);
            const qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + qrData;
            document.getElementById('qr_preview_image').src = qrCodeUrl;
            document.getElementById('qr_preview_container').style.display = 'block';
            
            document.getElementById('spaceModal').classList.add('show');
        }

        async function submitSpaceForm(e) {
            e.preventDefault();
            console.log('Submitting space form');
            
            // Validate space count before creating (only for new spaces, not updates)
            const spaceId = document.getElementById('space_id').value;
            if (!spaceId) {
                const validation = await validateSpaceCount(1);
                if (!validation.valid) {
                    showToast(validation.message, 'error');
                    return;
                }
            }
            
            const formData = new FormData(document.getElementById('spaceForm'));
            formData.append('action', spaceId ? 'update' : 'create');

            try {
                const response = await fetch('parking_api.php?type=space', {
                    method: 'POST',
                    body: formData
                });
                console.log('Response status:', response.status);
                const text = await response.text();
                console.log('Response text:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    showToast('Server error: Invalid response', 'error');
                    return;
                }

                if (data.success) {
                    showToast(data.message, 'success');
                    closeSpaceModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showToast('An error occurred: ' + error.message, 'error');
            }
        }

        async function deleteSpace(spaceId, spaceNumber) {
            if (!confirm(`Are you sure you want to delete space "${spaceNumber}"?`)) {
                return;
            }

            try {
                const response = await fetch('parking_api.php?type=space', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete&space_id=${spaceId}`
                });
                console.log('Delete response status:', response.status);
                const text = await response.text();
                console.log('Delete response:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    showToast('Server error: Invalid response', 'error');
                    return;
                }

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('An error occurred', 'error');
            }
        }

        // ========== BULK SPACE FUNCTIONS ==========

        function openBulkSpaceModal() {
            document.getElementById('bulkSpaceForm').reset();
            updateBulkPreview();
            document.getElementById('bulkSpaceModal').classList.add('show');
        }

        function closeBulkSpaceModal() {
            document.getElementById('bulkSpaceModal').classList.remove('show');
        }

        function updateBulkPreview() {
            const prefix = document.getElementById('bulk_prefix').value || 'X';
            const start = parseInt(document.getElementById('bulk_start').value) || 1;
            const end = parseInt(document.getElementById('bulk_end').value) || 1;
            const count = Math.max(0, end - start + 1);

            const startStr = prefix + '-' + String(start).padStart(2, '0');
            const endStr = prefix + '-' + String(end).padStart(2, '0');

            document.getElementById('bulk_preview').textContent =
                `${startStr} to ${endStr} (${count} spaces)`;
        }

        // Add event listeners for bulk preview update
        document.getElementById('bulk_prefix').addEventListener('input', updateBulkPreview);
        document.getElementById('bulk_start').addEventListener('input', updateBulkPreview);
        document.getElementById('bulk_end').addEventListener('input', updateBulkPreview);

        async function submitBulkSpaceForm(e) {
            e.preventDefault();
            console.log('Submitting bulk space form');
            
            // Validate space count before creating
            const start = parseInt(document.getElementById('bulk_start').value) || 1;
            const end = parseInt(document.getElementById('bulk_end').value) || 1;
            const count = Math.max(0, end - start + 1);
            
            const validation = await validateSpaceCount(count);
            if (!validation.valid) {
                showToast(validation.message, 'error');
                return;
            }
            
            const formData = new FormData(document.getElementById('bulkSpaceForm'));
            formData.append('action', 'bulk_create');

            try {
                const response = await fetch('parking_api.php?type=space', {
                    method: 'POST',
                    body: formData
                });
                console.log('Bulk create response status:', response.status);
                const text = await response.text();
                console.log('Bulk create response:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    showToast('Server error: Invalid response', 'error');
                    return;
                }

                if (data.success) {
                    showToast(data.message, 'success');
                    closeBulkSpaceModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showToast('An error occurred: ' + error.message, 'error');
            }
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Calculate and display total space count
        function updateTotalSpaceCount() {
            fetch('parking_api.php?type=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const totalSpaces = data.total_spaces || 0;
                        const countElement = document.getElementById('totalSpaceCount');
                        countElement.textContent = totalSpaces;
                        
                        // Change color based on usage
                        const parent = countElement.parentElement.parentElement;
                        if (totalSpaces >= 100) {
                            parent.style.background = '#fee2e2';
                            parent.style.borderColor = '#ef4444';
                        } else if (totalSpaces >= 90) {
                            parent.style.background = '#fef3c7';
                            parent.style.borderColor = '#f59e0b';
                        } else {
                            parent.style.background = '#d1fae5';
                            parent.style.borderColor = '#10b981';
                        }
                    }
                })
                .catch(error => console.error('Error fetching stats:', error));
        }

        // Validate space count before creating
        async function validateSpaceCount(newSpaceCount) {
            try {
                const response = await fetch('parking_api.php?type=stats');
                const data = await response.json();
                if (data.success) {
                    const currentTotal = data.total_spaces || 0;
                    if (currentTotal + newSpaceCount > 100) {
                        return {
                            valid: false,
                            message: `Cannot create ${newSpaceCount} space(s). Current total: ${currentTotal}, would exceed 100 space limit.`
                        };
                    }
                    return { valid: true };
                }
            } catch (error) {
                console.error('Validation error:', error);
            }
            return { valid: true }; // Allow if validation fails
        }

        // Initialize
        updateTotalSpaceCount();
        console.log('All functions defined successfully');
    </script>
</body>

</html>
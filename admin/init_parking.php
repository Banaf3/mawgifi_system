<?php
/**
 * Initialize Parking Data - Admin Module
 * This script creates all parking areas and 100 parking spaces
 * Run this once to set up the complete parking system
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Define parking areas with their slot ranges (total 100 slots)
$parking_areas = [
    'A' => ['start' => 1, 'end' => 14, 'type' => 'Standard', 'size' => 350.00],      // 14 slots
    'B' => ['start' => 15, 'end' => 44, 'type' => 'Standard', 'size' => 750.00],     // 30 slots
    'C' => ['start' => 45, 'end' => 65, 'type' => 'Standard', 'size' => 525.00],     // 21 slots
    'D' => ['start' => 66, 'end' => 86, 'type' => 'Standard', 'size' => 525.00],     // 21 slots
    'E' => ['start' => 87, 'end' => 100, 'type' => 'VIP', 'size' => 350.00]          // 14 slots
];

$areas_created = 0;
$spaces_created = 0;
$errors = [];

// Start transaction
$conn->begin_transaction();

try {
    foreach ($parking_areas as $area_code => $area_info) {
        $area_name = 'Area ' . $area_code;
        
        // Check if area already exists
        $check_sql = "SELECT area_id FROM ParkingArea WHERE area_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $area_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Create the area
            $insert_sql = "INSERT INTO ParkingArea (area_name, area_type, AreaSize) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssd", $area_name, $area_info['type'], $area_info['size']);
            $insert_stmt->execute();
            $area_id = $conn->insert_id;
            $insert_stmt->close();
            $areas_created++;
        } else {
            $row = $result->fetch_assoc();
            $area_id = $row['area_id'];
        }
        $check_stmt->close();
        
        // Create parking spaces for this area
        for ($slot = $area_info['start']; $slot <= $area_info['end']; $slot++) {
            $space_number = $area_code . '-' . str_pad($slot, 2, '0', STR_PAD_LEFT);
            $qr_code = 'SPACE-' . $area_code . '-' . str_pad($slot, 3, '0', STR_PAD_LEFT);
            
            // Check if space already exists
            $check_space_sql = "SELECT Space_id FROM ParkingSpace WHERE space_number = ?";
            $check_space_stmt = $conn->prepare($check_space_sql);
            $check_space_stmt->bind_param("s", $space_number);
            $check_space_stmt->execute();
            $space_result = $check_space_stmt->get_result();
            
            if ($space_result->num_rows === 0) {
                // Create the space
                $insert_space_sql = "INSERT INTO ParkingSpace (area_id, space_number, qr_code) VALUES (?, ?, ?)";
                $insert_space_stmt = $conn->prepare($insert_space_sql);
                $insert_space_stmt->bind_param("iss", $area_id, $space_number, $qr_code);
                $insert_space_stmt->execute();
                $insert_space_stmt->close();
                $spaces_created++;
            }
            $check_space_stmt->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    $success = true;
    
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    $errors[] = $e->getMessage();
}

$conn->close();

// Get summary
$total_areas = count($parking_areas);
$total_spaces = 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Parking Data - Mawgifi</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success: #48bb78;
            --danger: #e53e3e;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        h1 {
            background: var(--primary-grad);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        .status {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .status.success { background: rgba(72, 187, 120, 0.1); color: var(--success); }
        .status.error { background: rgba(229, 62, 62, 0.1); color: var(--danger); }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        .stat {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #718096;
            margin-top: 5px;
        }
        .areas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .areas-table th, .areas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .areas-table th { background: #f8fafc; font-weight: 600; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary-grad);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        .btn:hover { transform: translateY(-2px); }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-a { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .badge-b { background: rgba(118, 75, 162, 0.1); color: #764ba2; }
        .badge-c { background: rgba(72, 187, 120, 0.1); color: #48bb78; }
        .badge-d { background: rgba(237, 137, 54, 0.1); color: #ed8936; }
        .badge-e { background: rgba(229, 62, 62, 0.1); color: #e53e3e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🅿️ Parking Data Initialization</h1>
        
        <?php if ($success): ?>
            <div class="status success">
                ✅ Parking data initialized successfully!
            </div>
        <?php else: ?>
            <div class="status error">
                ❌ Error: <?php echo implode(', ', $errors); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-number"><?php echo $areas_created; ?>/<?php echo $total_areas; ?></div>
                <div class="stat-label">Areas Created</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $spaces_created; ?>/<?php echo $total_spaces; ?></div>
                <div class="stat-label">Spaces Created</div>
            </div>
        </div>
        
        <h3 style="color: #2d3748; margin-bottom: 10px;">Parking Areas Summary</h3>
        <table class="areas-table">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Type</th>
                    <th>Slots</th>
                    <th>Range</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parking_areas as $code => $info): ?>
                    <tr>
                        <td><span class="badge badge-<?php echo strtolower($code); ?>">Area <?php echo $code; ?></span></td>
                        <td><?php echo $info['type']; ?></td>
                        <td><?php echo ($info['end'] - $info['start'] + 1); ?> slots</td>
                        <td><?php echo $code; ?>-<?php echo str_pad($info['start'], 2, '0', STR_PAD_LEFT); ?> to <?php echo $code; ?>-<?php echo str_pad($info['end'], 2, '0', STR_PAD_LEFT); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold; background: #f8fafc;">
                    <td colspan="2">Total</td>
                    <td>100 slots</td>
                    <td>5 areas</td>
                </tr>
            </tbody>
        </table>
        
        <a href="parking_management.php" class="btn">Go to Parking Management</a>
    </div>
</body>
</html>

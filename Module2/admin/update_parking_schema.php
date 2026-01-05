<?php

/**
 * Database Schema Update Script
 * Adds area_color and area_status columns to ParkingArea table
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

echo "<!DOCTYPE html><html><head><title>Database Schema Update</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;border-radius:10px;margin:10px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;font-weight:bold;}";
echo ".error{color:red;font-weight:bold;}";
echo ".warning{color:orange;font-weight:bold;}";
echo ".btn{display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}";
echo ".btn:hover{background:#5568d3;}</style></head><body>";

echo "<h2>Updating Database Schema...</h2>";

$messages = [];
$has_errors = false;

// Check if columns already exist
$check_sql = "SHOW COLUMNS FROM ParkingArea LIKE 'area_color'";
$result = $conn->query($check_sql);
$color_exists = $result->num_rows > 0;

$check_sql = "SHOW COLUMNS FROM ParkingArea LIKE 'area_status'";
$result = $conn->query($check_sql);
$status_exists = $result->num_rows > 0;

if ($color_exists && $status_exists) {
    $messages[] = ['type' => 'success', 'msg' => '✓ Columns already exist. Database is up to date.'];
} else {
    // Add area_color column
    if (!$color_exists) {
        $sql = "ALTER TABLE ParkingArea ADD COLUMN area_color VARCHAR(7) DEFAULT '#a0a0a0' AFTER area_type";
        if ($conn->query($sql)) {
            $messages[] = ['type' => 'success', 'msg' => '✓ Successfully added area_color column'];
        } else {
            $messages[] = ['type' => 'error', 'msg' => '✗ Error adding area_color: ' . $conn->error];
            $has_errors = true;
        }
    } else {
        $messages[] = ['type' => 'success', 'msg' => '✓ area_color column already exists'];
    }

    // Add area_status column
    if (!$status_exists) {
        $sql = "ALTER TABLE ParkingArea ADD COLUMN area_status ENUM('available', 'occupied', 'temporarily_closed', 'under_maintenance') DEFAULT 'available' AFTER area_type";
        if ($conn->query($sql)) {
            $messages[] = ['type' => 'success', 'msg' => '✓ Successfully added area_status column'];
        } else {
            $messages[] = ['type' => 'error', 'msg' => '✗ Error adding area_status: ' . $conn->error];
            $has_errors = true;
        }
    } else {
        $messages[] = ['type' => 'success', 'msg' => '✓ area_status column already exists'];
    }

    // Update existing areas with colors if we just added the column
    if (!$color_exists && !$has_errors) {
        $updates = [
            "UPDATE ParkingArea SET area_color = '#667eea' WHERE (area_name LIKE '%A%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",
            "UPDATE ParkingArea SET area_color = '#764ba2' WHERE (area_name LIKE '%B%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",
            "UPDATE ParkingArea SET area_color = '#48bb78' WHERE (area_name LIKE '%C%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",
            "UPDATE ParkingArea SET area_color = '#ed8936' WHERE (area_name LIKE '%D%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1",
            "UPDATE ParkingArea SET area_color = '#e53e3e' WHERE (area_name LIKE '%E%') AND (area_color = '#a0a0a0' OR area_color IS NULL) LIMIT 1"
        ];

        foreach ($updates as $sql) {
            $conn->query($sql);
        }
        $messages[] = ['type' => 'success', 'msg' => '✓ Applied default colors to existing areas'];
    }
}

// Display messages
echo "<div class='box'>";
foreach ($messages as $msg) {
    echo "<p class='{$msg['type']}'>{$msg['msg']}</p>";
}
echo "</div>";

if (!$has_errors) {
    echo "<div class='box' style='background:#d1fae5;border-left:4px solid #10b981;'>";
    echo "<h3 style='color:#059669;'>✓ Update Completed Successfully!</h3>";
    echo "<p>The database schema has been updated. You can now:</p>";
    echo "<ul>";
    echo "<li>Set custom colors for parking areas</li>";
    echo "<li>Change area status (Available, Occupied, Closed, Maintenance)</li>";
    echo "<li>See colors reflected on the parking map</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='box' style='background:#fee2e2;border-left:4px solid #ef4444;'>";
    echo "<h3 style='color:#dc2626;'>✗ Some errors occurred</h3>";
    echo "<p>Please check the error messages above and try again or run the SQL manually.</p>";
    echo "</div>";
}

// Display current areas
echo "<div class='box'>";
echo "<h3>Current Parking Areas:</h3>";
$sql = "SELECT * FROM ParkingArea";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#667eea;color:white;'>";
    echo "<th style='border:1px solid #ddd;padding:8px;'>ID</th>";
    echo "<th style='border:1px solid #ddd;padding:8px;'>Name</th>";
    echo "<th style='border:1px solid #ddd;padding:8px;'>Type</th>";
    echo "<th style='border:1px solid #ddd;padding:8px;'>Color</th>";
    echo "<th style='border:1px solid #ddd;padding:8px;'>Status</th>";
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$row['area_id']}</td>";
        echo "<td style='border:1px solid #ddd;padding:8px;'><strong>{$row['area_name']}</strong></td>";
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$row['area_type']}</td>";
        $color = isset($row['area_color']) ? $row['area_color'] : '#a0a0a0';
        echo "<td style='border:1px solid #ddd;padding:8px;'>";
        echo "<div style='display:inline-block;width:50px;height:30px;background:{$color};border:1px solid #ccc;vertical-align:middle;'></div> ";
        echo $color;
        echo "</td>";
        $status = isset($row['area_status']) ? $row['area_status'] : 'N/A';
        echo "<td style='border:1px solid #ddd;padding:8px;'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No parking areas found.</p>";
}
echo "</div>";

$conn->close();
?>

<br>
<a href="parking_management.php" class="btn">← Back to Parking Management</a>
<a href="check_database.php" class="btn">View Database Info</a>

</body>

</html>
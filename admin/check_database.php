<?php
/**
 * Database Diagnostic Tool
 * Checks if the required columns exist in ParkingArea table
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Require admin access
requireAdmin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

echo "<!DOCTYPE html><html><head><title>Database Check</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;border-radius:10px;margin:10px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;font-weight:bold;}";
echo ".error{color:red;font-weight:bold;}";
echo ".warning{color:orange;font-weight:bold;}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;}";
echo "th,td{border:1px solid #ddd;padding:8px;text-align:left;}";
echo "th{background:#667eea;color:white;}";
echo ".btn{display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}";
echo ".btn:hover{background:#5568d3;}</style></head><body>";

echo "<h1>🔍 Database Diagnostic Check</h1>";

// Check ParkingArea table structure
echo "<div class='box'>";
echo "<h2>ParkingArea Table Columns</h2>";
$result = $conn->query("DESCRIBE ParkingArea");
if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    $has_color = false;
    $has_status = false;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
        if ($row['Field'] === 'area_color') $has_color = true;
        if ($row['Field'] === 'area_status') $has_status = true;
    }
    echo "</table>";
    
    echo "<h3>Column Status:</h3>";
    echo "<p>area_color: " . ($has_color ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</p>";
    echo "<p>area_status: " . ($has_status ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</p>";
    
    if (!$has_color || !$has_status) {
        echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;border-left:4px solid #ffc107;'>";
        echo "<strong>⚠️ Action Required:</strong><br>";
        echo "The required columns are missing. You need to update your database schema.<br><br>";
        echo "<strong>Option 1 - Run Update Script:</strong><br>";
        echo "<a href='update_parking_schema.php' class='btn'>Click Here to Update Database</a><br><br>";
        echo "<strong>Option 2 - Manual SQL:</strong><br>";
        echo "Copy the SQL from <a href='update_parking_schema.sql' target='_blank'>update_parking_schema.sql</a> and run it in phpMyAdmin";
        echo "</div>";
    } else {
        echo "<div style='background:#d1fae5;padding:15px;border-radius:5px;margin:10px 0;border-left:4px solid:#10b981;'>";
        echo "<strong>✓ Database is up to date!</strong><br>";
        echo "All required columns exist. The color and status features should work properly.";
        echo "</div>";
    }
} else {
    echo "<p class='error'>Error checking table: " . $conn->error . "</p>";
}
echo "</div>";

// Check current parking areas
echo "<div class='box'>";
echo "<h2>Current Parking Areas</h2>";
$sql = "SELECT * FROM ParkingArea";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Size</th>";
    if ($has_color) echo "<th>Color</th>";
    if ($has_status) echo "<th>Status</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['area_id']}</td>";
        echo "<td>{$row['area_name']}</td>";
        echo "<td>{$row['area_type']}</td>";
        echo "<td>{$row['AreaSize']}</td>";
        if ($has_color) {
            $color = $row['area_color'] ?? '#a0a0a0';
            echo "<td><div style='display:inline-block;width:50px;height:30px;background:{$color};border:1px solid #ccc;'></div> {$color}</td>";
        }
        if ($has_status) {
            echo "<td>{$row['area_status']}</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>No parking areas found in database.</p>";
}
echo "</div>";

// Check parking spaces count
echo "<div class='box'>";
echo "<h2>Parking Spaces Summary</h2>";
$result = $conn->query("SELECT COUNT(*) as total FROM ParkingSpace");
$row = $result->fetch_assoc();
$total_spaces = $row['total'];
echo "<p><strong>Total Parking Spaces:</strong> {$total_spaces} / 100</p>";
if ($total_spaces >= 100) {
    echo "<p class='error'>⚠️ Maximum limit reached!</p>";
} elseif ($total_spaces >= 90) {
    echo "<p class='warning'>⚠️ Nearly full (90%+)</p>";
} else {
    echo "<p class='success'>✓ Space available</p>";
}
echo "</div>";

$conn->close();

echo "<br><a href='parking_management.php' class='btn'>← Back to Parking Management</a>";
echo "</body></html>";
?>

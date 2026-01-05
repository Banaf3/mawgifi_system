<?php
/**
 * Quick test to check Event table schema and add area_id if needed
 */

require_once '../config/database.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

header('Content-Type: text/html');
echo "<h2>Event Table Schema Check</h2>";

// Check current columns
$result = $conn->query("SHOW COLUMNS FROM Event");
echo "<h3>Current Event Table Columns:</h3><ul>";
$has_area_id = false;
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    if ($row['Field'] === 'area_id') {
        $has_area_id = true;
    }
}
echo "</ul>";

if (!$has_area_id) {
    echo "<p style='color:red'><strong>area_id column does NOT exist!</strong></p>";
    echo "<p>Adding area_id column now...</p>";
    
    // Try to add the column
    $sql = "ALTER TABLE Event ADD COLUMN area_id INT NULL AFTER event_type";
    if ($conn->query($sql)) {
        echo "<p style='color:green'>Successfully added area_id column!</p>";
        
        // Add foreign key
        $fk_sql = "ALTER TABLE Event ADD CONSTRAINT fk_event_area FOREIGN KEY (area_id) REFERENCES ParkingArea(area_id) ON DELETE SET NULL";
        if ($conn->query($fk_sql)) {
            echo "<p style='color:green'>Foreign key constraint added!</p>";
        } else {
            echo "<p style='color:orange'>Could not add foreign key: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:red'>Failed to add column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:green'><strong>area_id column EXISTS!</strong></p>";
}

// Show sample events
echo "<h3>Recent Events:</h3>";
$result = $conn->query("SELECT * FROM Event ORDER BY event_id DESC LIMIT 5");
echo "<table border='1' cellpadding='5'><tr>";
// Get column names
$fields = $result->fetch_fields();
foreach ($fields as $field) {
    echo "<th>" . $field->name . "</th>";
}
echo "</tr>";
$result->data_seek(0);
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . ($value === null ? 'NULL' : htmlspecialchars($value)) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Show areas
echo "<h3>Available Areas:</h3>";
$result = $conn->query("SELECT area_id, area_name FROM ParkingArea ORDER BY area_name");
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>ID " . $row['area_id'] . ": " . $row['area_name'] . "</li>";
}
echo "</ul>";

$conn->close();
echo "<p><a href='event_management.php'>Back to Event Management</a></p>";
?>

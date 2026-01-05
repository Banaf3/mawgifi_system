<?php
/**
 * Event Schema Update - Add area_id to Event table
 */

require_once '../config/database.php';
require_once '../config/session.php';

requireAdmin();

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

echo "<!DOCTYPE html><html><head><title>Event Schema Update</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;border-radius:10px;margin:10px 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;font-weight:bold;}";
echo ".error{color:red;font-weight:bold;}";
echo ".btn{display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}";
echo "</style></head><body>";

echo "<h2>Updating Event Schema...</h2>";

$messages = [];

// Check if area_id column exists
$check_sql = "SHOW COLUMNS FROM Event LIKE 'area_id'";
$result = $conn->query($check_sql);
$area_id_exists = $result && $result->num_rows > 0;

if ($area_id_exists) {
    $messages[] = ['type' => 'success', 'msg' => '✓ area_id column already exists'];
} else {
    // Add area_id column
    $sql = "ALTER TABLE Event ADD COLUMN area_id INT NULL AFTER event_type, ADD FOREIGN KEY (area_id) REFERENCES ParkingArea(area_id) ON DELETE SET NULL";
    if ($conn->query($sql)) {
        $messages[] = ['type' => 'success', 'msg' => '✓ Successfully added area_id column to Event table'];
    } else {
        $messages[] = ['type' => 'error', 'msg' => '✗ Error adding area_id: ' . $conn->error];
    }
}

// Display messages
echo "<div class='box'>";
foreach ($messages as $msg) {
    echo "<p class='{$msg['type']}'>{$msg['msg']}</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>Event Schema Status:</h3>";
echo "<p>Events can now be linked to parking areas. When an event is scheduled:</p>";
echo "<ul>";
echo "<li>The selected parking area will be automatically closed during the event</li>";
echo "<li>The area will reopen automatically after the event ends</li>";
echo "<li>Events without an area assignment will not affect parking</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>

<br>
<a href="event_management.php" class="btn">← Back to Event Management</a>

</body>
</html>

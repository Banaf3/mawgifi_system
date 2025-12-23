<?php
require_once '../config/database.php';

$conn = getDBConnection();
if (!$conn) {
    die("DB Connection failed");
}

echo "DB Connected OK<br>";

$r = $conn->query('SELECT COUNT(*) as cnt FROM ParkingArea');
if ($r) {
    $row = $r->fetch_assoc();
    echo "ParkingArea count: " . $row['cnt'] . "<br>";
} else {
    echo "ParkingArea query failed<br>";
}

$r2 = $conn->query('SELECT COUNT(*) as cnt FROM ParkingSpace');
if ($r2) {
    $row2 = $r2->fetch_assoc();
    echo "ParkingSpace count: " . $row2['cnt'] . "<br>";
} else {
    echo "ParkingSpace query failed<br>";
}

echo "<br><a href='init_parking.php'>Run Init Parking</a>";

$conn->close();
?>

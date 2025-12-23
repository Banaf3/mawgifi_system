<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

$slot = $_GET['slot'] ?? '';
if (!$slot) { header('Location: ../parking/index.php'); exit; }

$conn = getDBConnection();
$booking = null;
$is_owner = false;
$user_id = isLoggedIn() ? getCurrentUserId() : 0;

// Get Space_id
$stmt = $conn->prepare("SELECT Space_id FROM ParkingSpace WHERE space_number = ?");
$stmt->bind_param("s", $slot);
$stmt->execute();
$space = $stmt->get_result()->fetch_assoc();
$space_id = $space['Space_id'] ?? null;
$stmt->close();

// Find active booking for this slot (current time or user's today booking)
if ($space_id) {
    $stmt = $conn->prepare(
        "SELECT b.*, ps.space_number, v.license_plate, v.vehicle_model, v.user_id as vehicle_owner,
                DATE_FORMAT(b.booking_start, '%M %d, %Y') as booking_date,
                DATE_FORMAT(b.booking_start, '%h:%i %p') as start_time,
                DATE_FORMAT(b.booking_end, '%h:%i %p') as end_time,
                DATE_FORMAT(b.check_in_time, '%h:%i %p') as check_in_display,
                DATE_FORMAT(b.check_out_time, '%h:%i %p') as check_out_display
         FROM Booking b
         JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
         JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
         WHERE b.Space_id = ? AND b.status IN ('pending', 'checked_in')
         AND ((b.booking_start <= NOW() AND b.booking_end >= NOW()) 
              OR (? > 0 AND v.user_id = ? AND DATE(b.booking_start) = CURDATE()))
         ORDER BY (b.booking_start <= NOW() AND b.booking_end >= NOW()) DESC, b.booking_start ASC
         LIMIT 1"
    );
    $stmt->bind_param("iii", $space_id, $user_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $is_owner = $booking && $user_id && $user_id == $booking['vehicle_owner'];
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slot #<?= htmlspecialchars($slot) ?> - Mawgifi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 20px;
        }
        .card {
            background: #fff; padding: 30px; border-radius: 16px;
            max-width: 400px; width: 100%; text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .logo { font-size: 1.8rem; font-weight: 800; color: #5a67d8; margin-bottom: 5px; }
        .subtitle { color: #888; font-size: 0.9rem; margin-bottom: 20px; }
        .slot-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; padding: 20px 40px; border-radius: 12px;
            font-size: 2.5rem; font-weight: bold; display: inline-block; margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block; padding: 6px 16px; border-radius: 20px;
            font-size: 0.85rem; font-weight: 600; margin-bottom: 20px;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-checked_in { background: #c6f6d5; color: #276749; }
        .status-available { background: #e2e8f0; color: #4a5568; }
        .info-section { background: #f8fafc; border-radius: 10px; padding: 15px; margin: 15px 0; text-align: left; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-row label { color: #666; font-size: 0.9rem; }
        .info-row span { font-weight: 600; color: #2d3748; }
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: 8px;
            cursor: pointer; font-size: 1rem; font-weight: 600; margin-top: 10px;
            text-decoration: none; display: block;
        }
        .btn:hover { opacity: 0.9; }
        .btn-checkin { background: #48bb78; color: #fff; }
        .btn-checkout { background: #ed8936; color: #fff; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .message { padding: 12px; border-radius: 8px; margin: 15px 0; font-size: 0.9rem; }
        .message.info { background: #bee3f8; color: #2b6cb0; }
        .message.warning { background: #fef3c7; color: #92400e; }
        .message.success { background: #c6f6d5; color: #276749; }
        .message.error { background: #fed7d7; color: #c53030; }
        #resultMsg { display: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Mawgifi</div>
        <div class="subtitle">Smart Parking System</div>
        <div class="slot-badge">#<?= htmlspecialchars($slot) ?></div>
        
        <?php if ($booking && $is_owner): ?>
            <div class="status-badge status-<?= $booking['status'] ?>">
                <?= $booking['status'] === 'pending' ? '⏳ Not Checked In' : '✓ Checked In' ?>
            </div>
            <div class="info-section">
                <div class="info-row"><label>Vehicle</label><span><?= htmlspecialchars($booking['license_plate']) ?></span></div>
                <div class="info-row"><label>Model</label><span><?= htmlspecialchars($booking['vehicle_model']) ?></span></div>
                <div class="info-row"><label>Date</label><span><?= $booking['booking_date'] ?></span></div>
                <div class="info-row"><label>Time</label><span><?= $booking['start_time'] ?> - <?= $booking['end_time'] ?></span></div>
                <?php if ($booking['check_in_display']): ?>
                    <div class="info-row"><label>Checked In</label><span><?= $booking['check_in_display'] ?></span></div>
                <?php endif; ?>
            </div>
            <div id="resultMsg" class="message"></div>
            <?php if ($booking['status'] === 'pending'): ?>
                <button class="btn btn-checkin" onclick="callApi('check_in', <?= $booking['booking_id'] ?>)">✓ Check In Now</button>
            <?php else: ?>
                <button class="btn btn-checkout" onclick="callApi('check_out', <?= $booking['booking_id'] ?>)">✓ Check Out Now</button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">View My Bookings</a>
            
        <?php elseif ($booking && !isLoggedIn()): ?>
            <div class="status-badge status-checked_in">Currently Booked</div>
            <div class="message info">Reserved: <?= $booking['start_time'] ?> - <?= $booking['end_time'] ?></div>
            <div class="message warning">If this is your booking, please login to check in.</div>
            <a href="../../login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">Login to Check In</a>
            
        <?php elseif ($booking): ?>
            <div class="status-badge status-checked_in">Currently Booked</div>
            <div class="message warning">This slot is reserved until <?= $booking['end_time'] ?></div>
            <a href="../parking/index.php" class="btn btn-primary">Find Available Slots</a>
            
        <?php else: ?>
            <div class="status-badge status-available">Available</div>
            <?php if (isLoggedIn()): ?>
                <div class="message info">This slot is available! Book it now.</div>
                <a href="../parking/index.php?slot=<?= urlencode($slot) ?>" class="btn btn-primary">Book This Slot</a>
            <?php else: ?>
                <div class="message warning">Please login to book this slot.</div>
                <a href="../../login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">Login to Book</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function callApi(action, bookingId) {
            const msg = document.getElementById('resultMsg');
            fetch('api/' + action + '.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId })
            })
            .then(r => r.json())
            .then(data => {
                msg.style.display = 'block';
                msg.className = 'message ' + (data.success ? 'success' : 'error');
                msg.textContent = data.message;
                if (data.success) setTimeout(() => location.reload(), 1000);
            })
            .catch(() => { msg.style.display = 'block'; msg.className = 'message error'; msg.textContent = 'Error'; });
        }
    </script>
</body>
</html>

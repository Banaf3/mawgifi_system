<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

requireLogin();

$username = $_SESSION['username'] ?? 'User';
$user_id = getCurrentUserId();

$conn = getDBConnection();
$stmt = $conn->prepare(
    "SELECT b.*, ps.space_number, v.license_plate, v.vehicle_model,
            DATE_FORMAT(b.booking_start, '%M %d, %Y') as booking_date,
            DATE_FORMAT(b.booking_start, '%h:%i %p') as start_time,
            DATE_FORMAT(b.booking_end, '%h:%i %p') as end_time
     FROM Booking b
     JOIN ParkingSpace ps ON b.Space_id = ps.Space_id
     JOIN Vehicle v ON b.vehicle_id = v.vehicle_id
     WHERE v.user_id = ? ORDER BY b.booking_start DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Mawgifi</title>
    <style>
        :root {
            --grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: var(--grad);
            color: #fff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar .brand {
            font-size: 1.6rem;
            font-weight: 800;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 50px;
            font-weight: 500;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-links a.active {
            background: #fff;
            color: #6a67ce;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
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
            color: #fff;
            padding: 8px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: #e53e3e;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .booking-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .slot-badge {
            background: var(--grad);
            color: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            min-width: 80px;
        }

        .slot-badge .num {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .slot-badge .label {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .booking-info {
            flex: 1;
        }

        .booking-info h3 {
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .booking-info p {
            font-size: 0.85rem;
            color: #718096;
            margin: 3px 0;
        }

        .booking-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-checked_in {
            background: #c6f6d5;
            color: #276749;
        }

        .status-completed {
            background: #e2e8f0;
            color: #4a5568;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #c53030;
        }

        .qr-preview {
            text-align: center;
        }

        .qr-preview img {
            width: 70px;
            height: 70px;
            border-radius: 6px;
        }

        .qr-preview p {
            font-size: 0.7rem;
            color: #888;
            margin-top: 4px;
        }

        .btn-action {
            background: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-top: 8px;
            margin-right: 5px;
        }

        .btn-update {
            border: 1px solid #667eea;
            color: #667eea;
        }

        .btn-update:hover {
            background: #e0e7ff;
        }

        .btn-cancel {
            border: 1px solid #e53e3e;
            color: #e53e3e;
        }

        .btn-cancel:hover {
            background: #fed7d7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 15px;
        }

        .empty-state a {
            color: #667eea;
            font-weight: 600;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
        }

        .modal h3 {
            margin-bottom: 20px;
        }

        .modal .form-group {
            margin-bottom: 15px;
        }

        .modal label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .modal input,
        .modal select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .time-row {
            display: flex;
            gap: 10px;
        }

        .time-row select {
            flex: 1;
        }

        .modal-btns {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-btns button {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modal-cancel {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #2d3748;
        }

        .btn-modal-save {
            background: var(--grad);
            border: none;
            color: #fff;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_type']) && strtolower($_SESSION['user_type']) === 'admin'): ?>
                <!-- Admin Navbar -->
                <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
                <a href="../membership/index.php">Vehicles</a>
                <a href="../parking/index.php">Parking Areas</a>
                <a href="index.php" class="active">Bookings</a>
                <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
                <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
                <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
            <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff'): ?>
                <!-- Staff Navbar -->
                <a href="../../Moudel1/Stafe.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Stafe.php?view=requests">Vehicles</a>
                <a href="../parking/index.php">Find Parking</a>
                <a href="index.php" class="active">Bookings</a>
                <a href="../../Moudel1/Stafe.php?view=profile">Profile</a>
            <?php else: ?>
                <!-- Student Navbar -->
                <a href="../../Moudel1/Student.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Student.php?view=vehicles">My Vehicles</a>
                <a href="../parking/index.php">Find Parking</a>
                <a href="index.php" class="active">My Bookings</a>
                <a href="../../Moudel1/Student.php?view=profile">Profile</a>
            <?php endif; ?>
        </div>
        <div class="user-profile">
            <div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <span><?= htmlspecialchars($username) ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">🎫 My Bookings</h1>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <span>📭</span>
                <p>No bookings yet. <a href="../parking/index.php">Find a parking slot</a></p>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): ?>
                <div class="booking-card">
                    <div class="slot-badge">
                        <div class="label">SLOT</div>
                        <div class="num">#<?= htmlspecialchars($b['space_number']) ?></div>
                    </div>
                    <div class="booking-info">
                        <h3><?= htmlspecialchars($b['vehicle_model']) ?> • <?= htmlspecialchars($b['license_plate']) ?></h3>
                        <p>📅 <?= $b['booking_date'] ?></p>
                        <p>🕐 <?= $b['start_time'] ?> - <?= $b['end_time'] ?></p>
                        <?php if ($b['status'] === 'pending' && strtotime($b['booking_start']) > time()): ?>
                            <button class="btn-action btn-update"
                                onclick="openModal(<?= $b['booking_id'] ?>, '<?= date('Y-m-d', strtotime($b['booking_start'])) ?>', <?= date('H', strtotime($b['booking_start'])) ?>, <?= date('H', strtotime($b['booking_end'])) ?>)">Update</button>
                            <button class="btn-action btn-cancel" onclick="cancelBooking(<?= $b['booking_id'] ?>)">Cancel</button>
                        <?php endif; ?>
                    </div>
                    <span
                        class="booking-status status-<?= $b['status'] ?>"><?= ucfirst(str_replace('_', ' ', $b['status'])) ?></span>
                    <div class="qr-preview">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode($b['booking_qr_code']) ?>"
                            alt="QR">
                        <p>Scan at slot</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="modal" onclick="if(event.target===this)closeModal()">
        <div class="modal">
            <h3>📝 Update Booking</h3>
            <input type="hidden" id="bookingId">
            <div class="form-group">
                <label>Date</label>
                <input type="date" id="updateDate" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <div class="time-row">
                    <select
                        id="startHour"><?php for ($i = 1; $i <= 12; $i++)
                            echo "<option value='$i'>$i</option>"; ?></select>
                    <select id="startAmPm">
                        <option>AM</option>
                        <option>PM</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <div class="time-row">
                    <select id="endHour"><?php for ($i = 1; $i <= 12; $i++)
                        echo "<option value='$i'>$i</option>"; ?></select>
                    <select id="endAmPm">
                        <option>AM</option>
                        <option>PM</option>
                    </select>
                </div>
            </div>
            <div class="modal-btns">
                <button class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-modal-save" onclick="saveUpdate()">Save</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modal');

        function openModal(id, date, sh, eh) {
            document.getElementById('bookingId').value = id;
            document.getElementById('updateDate').value = date;
            document.getElementById('startAmPm').value = sh >= 12 ? 'PM' : 'AM';
            document.getElementById('endAmPm').value = eh >= 12 ? 'PM' : 'AM';
            document.getElementById('startHour').value = sh > 12 ? sh - 12 : (sh === 0 ? 12 : sh);
            document.getElementById('endHour').value = eh > 12 ? eh - 12 : (eh === 0 ? 12 : eh);
            modal.classList.add('active');
        }

        function closeModal() { modal.classList.remove('active'); }

        function getTime(hId, apId) {
            let h = parseInt(document.getElementById(hId).value);
            const ap = document.getElementById(apId).value;
            if (ap === 'PM' && h < 12) h += 12;
            if (ap === 'AM' && h === 12) h = 0;
            return h.toString().padStart(2, '0') + ':00';
        }

        function saveUpdate() {
            const date = document.getElementById('updateDate').value;
            if (!date) { alert('Select a date'); return; }

            fetch('api/update_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    booking_id: document.getElementById('bookingId').value,
                    date, start_time: getTime('startHour', 'startAmPm'), end_time: getTime('endHour', 'endAmPm')
                })
            }).then(r => r.json()).then(d => d.success ? location.reload() : alert(d.message));
        }

        function cancelBooking(id) {
            if (!confirm('Cancel this booking?')) return;
            fetch('api/cancel_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: id })
            }).then(r => r.json()).then(d => d.success ? location.reload() : alert(d.message));
        }
    </script>
</body>

</html>
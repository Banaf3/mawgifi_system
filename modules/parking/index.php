<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

requireLogin();

$username = $_SESSION['username'] ?? 'User';
$user_id = getCurrentUserId();
$user_type = $_SESSION['user_type'] ?? 'user';
$is_student = ($user_type === 'user');

// Get user's approved vehicles (only for students)
$conn = getDBConnection();
$vehicles = [];
if ($is_student) {
    $stmt = $conn->prepare(
        "SELECT vehicle_id, vehicle_model, license_plate 
         FROM Vehicle WHERE user_id = ? AND status = 'approved'"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Areas - Mawgifi</title>
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
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

        /* Main Layout */
        .main-container {
            display: flex;
            height: calc(100vh - 70px);
        }

        /* Map Section */
        .map-section {
            flex: 1;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .map-section svg {
            width: 100%;
            height: 100%;
        }

        /* Booking Panel */
        .booking-panel {
            width: 300px;
            background: #fff;
            border-left: 1px solid #e2e8f0;
            padding: 20px;
            overflow-y: auto;
        }

        .booking-panel h2 {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .time-row {
            display: flex;
            gap: 5px;
        }

        .time-row select {
            flex: 1;
        }

        .slot-display {
            background: var(--primary-grad);
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }

        .slot-display .slot-id {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .slot-display .slot-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .qr-section {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .qr-section img {
            background: #fff;
            padding: 5px;
            border-radius: 4px;
        }

        .qr-section p {
            font-size: 0.75rem;
            color: #888;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 8px;
        }

        .btn-primary {
            background: var(--primary-grad);
            color: #fff;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        .message {
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }

        .message.success {
            background: #c6f6d5;
            color: #276749;
        }

        .message.error {
            background: #fed7d7;
            color: #c53030;
        }

        .placeholder-text {
            color: #999;
            text-align: center;
            padding: 20px 10px;
            font-size: 0.9rem;
        }

        .placeholder-text span {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }

        .no-vehicle-msg {
            background: #fef3c7;
            color: #92400e;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
        }

        .no-vehicle-msg a {
            color: #92400e;
            font-weight: 600;
        }

        .legend {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 0.75rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .legend-box {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        /* Slot styles */
        .parking-slot {
            cursor: pointer;
            transition: all 0.2s;
        }

        .parking-slot:hover {
            fill: #48bb78 !important;
        }

        .parking-slot.selected {
            fill: #4299e1 !important;
            stroke: #2b6cb0;
            stroke-width: 2px;
        }

        .parking-slot.booked {
            fill: #f56565 !important;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>
        <div class="nav-links">
            <?php if ($is_student): ?>
                <a href="../../Moudel1/Student.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Student.php?view=vehicles">My Vehicles</a>
                <a href="../parking/index.php" class="active">Find Parking</a>
                <a href="../booking/index.php">My Bookings</a>
                <a href="../../Moudel1/Student.php?view=profile">Profile</a>
            <?php elseif (strtolower($user_type) === 'admin'): ?>
                <a href="../../Moudel1/Admin.php?view=dashboard">Dashboard</a>
                <a href="../membership/index.php">Vehicles</a>
                <a href="../parking/index.php" class="active">Parking Areas</a>
                <a href="../booking/index.php">Bookings</a>
                <a href="../../Moudel1/Admin.php?view=register">Register Student</a>
                <a href="../../Moudel1/Admin.php?view=manage">Manage Profile</a>
                <a href="../../Moudel1/Admin.php?view=profile">Profile</a>
            <?php else: ?>
                <a href="../../Moudel1/Stafe.php?view=dashboard">Dashboard</a>
                <a href="../../Moudel1/Stafe.php?view=requests">Vehicles</a>
                <a href="../parking/index.php" class="active">Parking Areas</a>
                <a href="../../Moudel1/Stafe.php?view=bookings">Bookings</a>
                <a href="../../Moudel1/Stafe.php?view=profile">Profile</a>
            <?php endif; ?>
        </div>
        <div class="user-profile">
            <div class="avatar-circle"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <span class="user-name"><?= htmlspecialchars($username) ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="map-section">
            <?php include '../../assets/parking_slots_optimized.php'; ?>
        </div>

        <div class="booking-panel">
            <?php if ($is_student): ?>
                <h2>📝 Book Parking</h2>
            <?php else: ?>
                <h2>🅿️ Slot Details</h2>
            <?php endif; ?>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box" style="background:#a0a0a0;"></div> Available
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background:#f56565;"></div> Booked
                </div>
                <div class="legend-item">
                    <div class="legend-box" style="background:#4299e1;"></div> Selected
                </div>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" id="bookingDate" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label>Start Time</label>
                <div class="time-row">
                    <select id="startHour">
                        <?php for ($h = 7; $h <= 11; $h++): ?>
                            <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endfor; ?>
                        <option value="12">12</option>
                        <?php for ($h = 1; $h <= 6; $h++): ?>
                            <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="startAmPm">
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>End Time</label>
                <div class="time-row">
                    <select id="endHour">
                        <?php for ($h = 7; $h <= 11; $h++): ?>
                            <option value="<?= $h ?>" <?= $h == 10 ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endfor; ?>
                        <option value="12">12</option>
                        <?php for ($h = 1; $h <= 6; $h++): ?>
                            <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="endAmPm">
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>
            </div>

            <button class="btn btn-secondary" onclick="loadSlots()">
                🔍 Check Availability
            </button>

            <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">

            <?php if ($is_student): ?>
                <?php if (empty($vehicles)): ?>
                    <div class="no-vehicle-msg">
                        <p>No approved vehicle.</p>
                        <a href="../membership/index.php">Register one</a>
                    </div>
                <?php else: ?>
                    <div id="placeholder" class="placeholder-text">
                        <span>👆</span>
                        Select date/time, then click a slot
                    </div>

                    <div id="bookingForm" style="display:none;">
                        <div class="slot-display">
                            <div class="slot-label">Selected Slot</div>
                            <div class="slot-id" id="slotDisplay">-</div>
                        </div>

                        <div class="form-group">
                            <label>Vehicle</label>
                            <select id="vehicleSelect">
                                <option value="">-- Select --</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?= $v['vehicle_id'] ?>">
                                        <?= htmlspecialchars($v['license_plate']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="qr-section" id="qrSection" style="display:none;">
                            <img id="qrImage" src="" alt="QR" width="90">
                            <p>Scan when parking</p>
                        </div>

                        <div id="messageBox"></div>

                        <button class="btn btn-primary" id="confirmBtn" onclick="confirmBooking()">
                            ✓ Confirm Booking
                        </button>
                        <button class="btn btn-secondary" onclick="clearSelection()">
                            ✕ Cancel
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Admin/Staff View -->
                <div id="placeholder" class="placeholder-text">
                    <span>👆</span>
                    Click a slot to view details
                </div>

                <div id="slotDetails" style="display:none;">
                    <div class="slot-display">
                        <div class="slot-label">Slot Number</div>
                        <div class="slot-id" id="slotDisplay">-</div>
                    </div>

                    <div id="bookingInfo">
                        <p style="color:#888;text-align:center;">No booking for selected time</p>
                    </div>

                    <div class="qr-section" id="qrSection">
                        <img id="qrImage" src="" alt="QR" width="120">
                        <p>Slot QR Code</p>
                    </div>

                    <button class="btn btn-primary" onclick="printQR()">
                        🖨️ Print QR Code
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedSlot = null;
        const slots = document.querySelectorAll('.parking-slot');
        const isStudent = <?= $is_student ? 'true' : 'false' ?>;

        document.getElementById('bookingDate').value =
            new Date().toISOString().split('T')[0];

        slots.forEach(slot => {
            slot.addEventListener('click', () => selectSlot(slot));
        });

        function getTime(hourId, ampmId) {
            let h = parseInt(document.getElementById(hourId).value);
            const ap = document.getElementById(ampmId).value;
            if (ap === 'PM' && h < 12) h += 12;
            if (ap === 'AM' && h === 12) h = 0;
            return h.toString().padStart(2, '0') + ':00';
        }

        function loadSlots() {
            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            if (!date) { alert('Select a date'); return; }

            slots.forEach(s => s.classList.remove('booked', 'selected'));
            clearSelection();

            fetch(`../booking/api/get_slots.php?date=${date}&start=${start}&end=${end}`)
                .then(r => r.json())
                .then(data => {
                    (data.booked || []).forEach(id => {
                        const el = document.getElementById('slot-' + id);
                        if (el) el.classList.add('booked');
                    });
                });
        }

        function selectSlot(el) {
            // Students can't select booked slots, admin/staff can
            if (isStudent && el.classList.contains('booked')) return;

            slots.forEach(s => s.classList.remove('selected'));
            el.classList.add('selected');

            selectedSlot = el.id.replace('slot-', '');

            if (isStudent) {
                selectSlotStudent();
            } else {
                selectSlotAdmin();
            }
        }

        function selectSlotStudent() {
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('bookingForm').style.display = 'block';
            document.getElementById('slotDisplay').textContent = '#' + selectedSlot;

            const qrData = encodeURIComponent(
                location.origin + '/mawgifi_system/modules/booking/scan.php?slot=' + selectedSlot
            );
            document.getElementById('qrImage').src =
                'https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=' + qrData;
            document.getElementById('qrSection').style.display = 'block';
            document.getElementById('messageBox').innerHTML = '';
        }

        function selectSlotAdmin() {
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('slotDetails').style.display = 'block';
            document.getElementById('slotDisplay').textContent = '#' + selectedSlot;

            // Generate QR for this slot
            const qrData = encodeURIComponent(
                location.origin + '/mawgifi_system/modules/booking/scan.php?slot=' + selectedSlot
            );
            document.getElementById('qrImage').src =
                'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + qrData;

            // Fetch booking info for this slot
            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            fetch(`../booking/api/get_slot_booking.php?slot=${selectedSlot}&date=${date}&start=${start}&end=${end}`)
                .then(r => r.json())
                .then(data => {
                    const infoDiv = document.getElementById('bookingInfo');
                    if (data.booking) {
                        const b = data.booking;
                        infoDiv.innerHTML = `
                            <div style="background:#f0f9ff;padding:12px;border-radius:8px;margin:10px 0;">
                                <p style="margin:5px 0;"><strong>Status:</strong> 
                                    <span style="color:${b.status === 'checked_in' ? '#38a169' : '#dd6b20'};">
                                        ${b.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </p>
                                <p style="margin:5px 0;"><strong>Student:</strong> ${b.username}</p>
                                <p style="margin:5px 0;"><strong>Vehicle:</strong> ${b.license_plate}</p>
                                <p style="margin:5px 0;"><strong>Model:</strong> ${b.vehicle_model}</p>
                                <p style="margin:5px 0;"><strong>Time:</strong> ${b.start_time} - ${b.end_time}</p>
                                ${b.check_in_time ? '<p style="margin:5px 0;"><strong>Checked In:</strong> ' + b.check_in_time + '</p>' : ''}
                                ${b.check_out_time ? '<p style="margin:5px 0;"><strong>Checked Out:</strong> ' + b.check_out_time + '</p>' : ''}
                            </div>`;
                    } else {
                        infoDiv.innerHTML = '<p style="color:#888;text-align:center;">No booking for selected time</p>';
                    }
                });
        }

        function clearSelection() {
            slots.forEach(s => s.classList.remove('selected'));
            selectedSlot = null;
            const ph = document.getElementById('placeholder');
            if (ph) ph.style.display = 'block';

            if (isStudent) {
                const bf = document.getElementById('bookingForm');
                if (bf) bf.style.display = 'none';
            } else {
                const sd = document.getElementById('slotDetails');
                if (sd) sd.style.display = 'none';
            }
        }

        function confirmBooking() {
            const vid = document.getElementById('vehicleSelect').value;
            if (!vid) { showMsg('Select a vehicle', 'error'); return; }
            if (!selectedSlot) { showMsg('Select a slot', 'error'); return; }

            const date = document.getElementById('bookingDate').value;
            const start = getTime('startHour', 'startAmPm');
            const end = getTime('endHour', 'endAmPm');

            document.getElementById('confirmBtn').disabled = true;

            fetch('../booking/api/create_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    vehicle_id: vid,
                    slot_id: selectedSlot,
                    date: date,
                    start_time: start,
                    end_time: end
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMsg('Booking confirmed!', 'success');
                        setTimeout(() => location.href = '../booking/index.php', 1000);
                    } else {
                        showMsg(data.message || 'Failed', 'error');
                        document.getElementById('confirmBtn').disabled = false;
                    }
                })
                .catch(() => {
                    showMsg('Error occurred', 'error');
                    document.getElementById('confirmBtn').disabled = false;
                });
        }

        function showMsg(msg, type) {
            document.getElementById('messageBox').innerHTML =
                '<div class="message ' + type + '">' + msg + '</div>';
        }

        function printQR() {
            const qrSrc = document.getElementById('qrImage').src;
            const slotNum = selectedSlot;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head><title>Print QR - Slot #${slotNum}</title></head>
                <body style="text-align:center;font-family:Arial,sans-serif;padding:40px;">
                    <h1 style="color:#5a67d8;">Mawgifi Parking</h1>
                    <h2>Slot #${slotNum}</h2>
                    <img src="${qrSrc}" style="width:200px;height:200px;">
                    <p style="color:#666;margin-top:20px;">Scan to view booking or reserve this slot</p>
                    <script>window.onload = function() { window.print(); }<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        loadSlots();
    </script>
</body>

</html>
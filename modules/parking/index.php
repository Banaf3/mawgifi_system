<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

// Ensure user is logged in
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';
$dashboard_link = ($user_type === 'admin') ? '../../admin/dashboard.php' :
    (($user_type === 'staff') ? '../../staff/dashboard.php' : '../../student/dashboard.php');

// Role-based button names
$is_student = ($user_type === 'user');
$nav_vehicles = $is_student ? 'My Vehicles' : 'Vehicles';
$nav_parking = $is_student ? 'Find Parking' : 'Parking Areas';
$nav_bookings = $is_student ? 'My Bookings' : 'Bookings';
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

        .container {
            max-width: 100%;
            margin: 0;
            padding: 20px;
        }

        .module-header {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .module-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--text-dark);
        }

        .module-header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Parking Slot Interactive Styles */
        .parking-slot {
            cursor: pointer;
            transition: all 0.3s ease;
            pointer-events: all;
        }

        .parking-slot:hover {
            fill: #48bb78 !important;
            /* Green-500 */
            opacity: 0.8;
            stroke: #2f855a;
            stroke-width: 1px;
        }

        .parking-slot.selected {
            fill: #4299e1 !important;
            /* Blue-500 */
            stroke: #2b6cb0;
            stroke-width: 2px;
            filter: drop-shadow(0 0 5px rgba(66, 153, 225, 0.5));
        }

        .parking-slot.taken {
            fill: #f56565 !important;
            /* Red-500 */
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Responsive SVG container */
        .svg-container {
            width: 100%;
            height: calc(100vh - 200px);
            /* Fill available height */
            padding: 0;
            overflow: hidden;
            /* or auto if scrolling needed */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        svg {
            width: 100%;
            height: 100%;
            max-height: 100%;
        }

        #bookingForm {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="<?php echo $dashboard_link; ?>">Dashboard</a>
            <a href="../membership/index.php"><?php echo $nav_vehicles; ?></a>
            <a href="../parking/index.php" class="active"><?php echo $nav_parking; ?></a>
            <a href="../booking/index.php"><?php echo $nav_bookings; ?></a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h2 style="text-align: center; margin-bottom: 20px; color: var(--text-dark);">Select a parking slot to book</h2>

        <div class="svg-container">
            <?php include '../../assets/parking_slots_optimized.php'; ?>
        </div>

        <!-- Booking Form (Hidden until selection) -->
        <form id="bookingForm" method="POST" action="../booking/process_booking.php" style="display: none;">
            <input type="hidden" name="slot_id" id="selected_slot_id">
            <h3 style="margin-bottom: 10px; color: var(--text-dark);">Confirm Booking</h3>
            <p style="margin-bottom: 15px; color: var(--text-light);">Selected Slot: <strong id="display_slot"
                    style="color: var(--text-dark);">None</strong></p>
            <button type="submit" style="
                background: var(--primary-grad); 
                color: white; 
                border: none; 
                padding: 10px 30px; 
                border-radius: 10px; 
                font-size: 1rem; 
                font-weight: 600;
                cursor: pointer;
                width: 100%;">
                Book Now
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const slots = document.querySelectorAll('.parking-slot');
            const bookingForm = document.getElementById('bookingForm');
            const slotInput = document.getElementById('selected_slot_id');
            const slotDisplay = document.getElementById('display_slot');

            slots.forEach(slot => {
                slot.addEventListener('click', function () {
                    // Check if taken
                    if (this.classList.contains('taken')) return;

                    // Deselect others
                    slots.forEach(s => s.classList.remove('selected'));

                    // Select this one
                    this.classList.add('selected');

                    // Update form data
                    const rawId = this.id; // slot-123
                    const cleanId = rawId.replace('slot-', '');

                    slotInput.value = cleanId;
                    slotDisplay.textContent = '#' + cleanId;

                    // Show booking form
                    bookingForm.style.display = 'block';
                });
            });
        });
    </script>
</body>

</html>
<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Ensure user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'Admin';

// Only admin can access this page
if ($user_type !== 'admin') {
    header("Location: ../logout.php");
    exit();
}

// Determine current view
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

$conn = getDBConnection();
$error_message = '';
$success_message = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($new_username) || empty($new_email)) {
        $error_message = "Username and Email are required.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE User SET UserName = ?, Email = ?, PhoneNumber = ?, Password = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $new_username, $new_email, $new_phone, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE User SET UserName = ?, Email = ?, PhoneNumber = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $new_username, $new_email, $new_phone, $user_id);
        }

        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $_SESSION['username'] = $new_username;
            $username = $new_username;
        } else {
            $error_message = "Failed to update profile.";
        }
        $stmt->close();
    }
}

// Fetch user profile data
$user_data = [];
$stmt = $conn->prepare("SELECT UserName, Email, PhoneNumber FROM User WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Get stats for dashboard
$total_students = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM User WHERE UserType = 'user'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_students = $row['count'];
$stmt->close();

$total_staff = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM User WHERE UserType = 'staff'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_staff = $row['count'];
$stmt->close();

$total_vehicles = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM Vehicle");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_vehicles = $row['count'];
$stmt->close();

// Reports Data: Vehicles by Type
$vehicle_types = [];
$vehicle_counts = [];
$stmt = $conn->prepare("SELECT vehicle_type, COUNT(*) as count FROM Vehicle GROUP BY vehicle_type");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $vehicle_types[] = $row['vehicle_type'];
    $vehicle_counts[] = $row['count'];
}
$stmt->close();

// Reports Data: Bookings by Status
$booking_statuses = [];
$booking_counts = [];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM Booking GROUP BY status");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $booking_statuses[] = ucfirst($row['status']);
    $booking_counts[] = $row['count'];
}
$stmt->close();

// Reports Data: Parking Spaces Status
$spaces_available = 0;
$spaces_booked = 0;
$spaces_maintenance = 0;
$result = $conn->query("SELECT status, COUNT(*) as count FROM ParkingSpace GROUP BY status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'available') $spaces_available = $row['count'];
        elseif ($row['status'] === 'occupied' || $row['status'] === 'reserved') $spaces_booked += $row['count'];
        elseif ($row['status'] === 'maintenance') $spaces_maintenance = $row['count'];
    }
}

// Reports Data: Parking Areas Status
$areas_available = 0;
$areas_closed = 0;
$result = $conn->query("SELECT area_status, COUNT(*) as count FROM ParkingArea GROUP BY area_status");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['area_status'] === 'available') $areas_available = $row['count'];
        else $areas_closed += $row['count'];
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $delete_id = intval($_POST['delete_user_id']);
    if ($delete_id !== $user_id) { // Can't delete yourself
        $stmt = $conn->prepare("DELETE FROM User WHERE user_id = ? AND UserType != 'admin'");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Failed to delete user!";
        }
        $stmt->close();
    } else {
        $error_message = "You cannot delete yourself!";
    }
}

// Handle update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $edit_username = trim($_POST['edit_username']);
    $edit_email = trim($_POST['edit_email']);
    $edit_phone = trim($_POST['edit_phone']);
    $edit_type = $_POST['edit_usertype'];

    if (!empty($edit_username) && !empty($edit_email)) {
        $stmt = $conn->prepare("UPDATE User SET UserName = ?, Email = ?, PhoneNumber = ?, UserType = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $edit_username, $edit_email, $edit_phone, $edit_type, $edit_id);
        if ($stmt->execute()) {
            $success_message = "User updated successfully!";
        } else {
            $error_message = "Failed to update user!";
        }
        $stmt->close();
    } else {
        $error_message = "Username and Email are required!";
    }
}

// Handle Register Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_student'])) {
    $new_username = trim($_POST['new_username']);
    $new_email = trim($_POST['new_email']);
    $new_phone = trim($_POST['new_phone']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($new_username) || empty($new_email) || empty($new_password)) {
        $error_message = "Username, Email and Password are required.";
    } elseif ($new_password !== $confirm_new_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM User WHERE Email = ?");
        $check_stmt->bind_param("s", $new_email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Email already exists!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_type = 'user'; // Student type

            $stmt = $conn->prepare("INSERT INTO User (UserName, Email, PhoneNumber, password, UserType) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $new_username, $new_email, $new_phone, $hashed_password, $user_type);

            if ($stmt->execute()) {
                $success_message = "Student registered successfully! They can now login.";
            } else {
                $error_message = "Failed to register student: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch all users for manage view
$all_users = [];
if ($current_view === 'manage') {
    $stmt = $conn->prepare("SELECT user_id, UserName, Email, PhoneNumber, UserType FROM User WHERE UserType != 'admin' ORDER BY UserType, UserName");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_users[] = $row;
    }
    $stmt->close();
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
    if ($current_view === 'dashboard')
        echo 'Admin Dashboard';
    elseif ($current_view === 'profile')
        echo 'My Profile';
    elseif ($current_view === 'register')
        echo 'Register Student';
    elseif ($current_view === 'manage')
        echo 'Manage Profile';
    elseif ($current_view === 'reports')
        echo 'Reports Dashboard';
    else
        echo 'Admin Dashboard';
    ?> - Mawgifi</title>
    <link rel="stylesheet" href="Admin.css">
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="Admin.php?view=dashboard" <?php echo $current_view === 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a>
            <a href="../modules/membership/index.php">Vehicles</a>
            <a href="../modules/parking/index.php">Parking Map</a>
            <a href="../admin/parking_management.php">Manage Parking</a>
            <a href="../admin/event_management.php">Events</a>
            <a href="../modules/booking/index.php">Bookings</a>
            <a href="Admin.php?view=reports" <?php echo $current_view === 'reports' ? 'class="active"' : ''; ?>>Reports</a>
            <a href="Admin.php?view=register" <?php echo $current_view === 'register' ? 'class="active"' : ''; ?>>Register
                Student</a>
            <a href="Admin.php?view=manage" <?php echo $current_view === 'manage' ? 'class="active"' : ''; ?>>Manage
                Profile</a>
            <a href="Admin.php?view=profile" <?php echo $current_view === 'profile' ? 'class="active"' : ''; ?>>Profile</a>
        </div>

        <div class="user-profile">
            <div class="avatar-circle"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($current_view === 'dashboard'): ?>
            <!-- Dashboard View -->
            <div class="dashboard-welcome">
                <h2>Welcome Back, <?php echo htmlspecialchars($username); ?>!</h2>
                <p>Manage the Mawgifi parking system</p>
            </div>

            <div class="modules-grid">
                <a href="../modules/membership/index.php" class="module-card m1">
                    <div class="module-icon">🚗</div>
                    <h3>Vehicles</h3>
                    <p>Manage user memberships, profiles, and vehicle registrations.</p>
                </a>

                <a href="../modules/parking/index.php" class="module-card m2">
                    <div class="module-icon">🅿️</div>
                    <h3>Parking Areas</h3>
                    <p>Manage parking areas, spaces, and monitor availability status.</p>
                </a>

                <a href="../modules/booking/index.php" class="module-card m3">
                    <div class="module-icon">📋</div>
                    <h3>Bookings</h3>
                    <p>Oversee parking bookings and manage QR code access systems.</p>
                </a>
            </div>

            <!-- Quick Stats -->
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_staff; ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_vehicles; ?></div>
                    <div class="stat-label">Total Vehicles</div>
                </div>
            </div>

        <?php elseif ($current_view === 'register'): ?>
            <!-- Register Student View -->
            <div class="module-header">
                <h1>Register Student</h1>
                <p>Register a new student to the system</p>
            </div>

            <div class="content-area">
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <div class="form-section">
                    <h2>New Student Information</h2>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_username">Username</label>
                                <input type="text" id="new_username" name="new_username" placeholder="Enter username"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="new_email">Email Address</label>
                                <input type="email" id="new_email" name="new_email" placeholder="Enter email" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_phone">Phone Number</label>
                                <input type="text" id="new_phone" name="new_phone" placeholder="Enter phone number">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Enter password"
                                    required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_new_password">Confirm Password</label>
                                <input type="password" id="confirm_new_password" name="confirm_new_password"
                                    placeholder="Confirm password" required>
                            </div>
                        </div>

                        <button type="submit" name="register_student" class="btn">Register Student</button>
                    </form>
                </div>
            </div>

        <?php elseif ($current_view === 'manage'): ?>
            <!-- Manage Profile View -->
            <div class="module-header">
                <h1>Manage Profile</h1>
                <p>Manage all student and staff profiles</p>
            </div>

            <div class="content-area">
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_users)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No users found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['UserName']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['PhoneNumber'] ?? '-'); ?></td>
                                        <td><span
                                                class="badge <?php echo $user['UserType'] === 'staff' ? 'badge-staff' : 'badge-student'; ?>"><?php echo ucfirst($user['UserType'] === 'user' ? 'Student' : $user['UserType']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-edit"
                                                onclick="openEditModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['UserName']); ?>', '<?php echo htmlspecialchars($user['Email']); ?>', '<?php echo htmlspecialchars($user['PhoneNumber'] ?? ''); ?>', '<?php echo $user['UserType']; ?>')">Edit</button>
                                            <form method="POST" style="display:inline;"
                                                onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="delete_user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" name="delete_user"
                                                    class="btn btn-sm btn-delete">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeEditModal()">&times;</span>
                    <h2>Edit User</h2>
                    <form method="POST" action="">
                        <input type="hidden" id="edit_user_id" name="edit_user_id">
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="edit_username" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="edit_email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="text" id="edit_phone" name="edit_phone">
                        </div>
                        <div class="form-group">
                            <label for="edit_usertype">User Type</label>
                            <select id="edit_usertype" name="edit_usertype">
                                <option value="user">Student</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <button type="submit" name="update_user" class="btn">Update User</button>
                    </form>
                </div>
            </div>

            <script>
                function openEditModal(id, username, email, phone, usertype) {
                    document.getElementById('edit_user_id').value = id;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_phone').value = phone;
                    document.getElementById('edit_usertype').value = usertype;
                    document.getElementById('editModal').style.display = 'flex';
                }
                function closeEditModal() {
                    document.getElementById('editModal').style.display = 'none';
                }
                window.onclick = function (event) {
                    var modal = document.getElementById('editModal');
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                }
            </script>

        <?php elseif ($current_view === 'profile'): ?>
            <!-- Profile View -->
            <div class="module-header">
                <h1>👤 My Profile</h1>
                <p>Update your personal information</p>
            </div>

            <div class="content-area">
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <div class="form-section">
                    <h2>Personal Information</h2>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username"
                                    value="<?php echo htmlspecialchars($user_data['UserName'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($user_data['PhoneNumber'] ?? ''); ?>"
                                    placeholder="Enter phone number">
                            </div>
                        </div>

                        <h2 style="margin-top: 30px;">Change Password</h2>
                        <p style="color: var(--text-light); margin-bottom: 20px;">Leave blank to keep current password</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" id="password" name="password" placeholder="Enter new password">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Confirm new password">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </form>
                </div>
            </div>
        <?php elseif ($current_view === 'reports'): ?>
            <!-- Reports Dashboard View -->
            <div class="module-header">
                <h1>📊 System Reports</h1>
                <p>Graphical overview of system data</p>
            </div>

            <div class="content-area">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                    
                    <!-- Chart 1: Users -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 15px; text-align: center;">Users Distribution</h3>
                        <canvas id="usersChart"></canvas>
                    </div>

                    <!-- Chart 2: Vehicles -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 15px; text-align: center;">Vehicles by Type</h3>
                        <canvas id="vehiclesChart"></canvas>
                    </div>

                    <!-- Chart 3: Bookings -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); grid-column: 1 / -1;">
                        <h3 style="margin-bottom: 15px; text-align: center;">Booking Statuses</h3>
                        <div style="height: 300px;">
                            <canvas id="bookingsChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 4: Parking Spaces -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 15px; text-align: center;">Parking Spaces Status</h3>
                        <canvas id="spacesChart"></canvas>
                    </div>

                    <!-- Chart 5: Parking Areas -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 15px; text-align: center;">Parking Areas Status</h3>
                        <canvas id="areasChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Chart.js CDN -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            <script>
                // Users Chart
                const ctxUsers = document.getElementById('usersChart').getContext('2d');
                new Chart(ctxUsers, {
                    type: 'doughnut',
                    data: {
                        labels: ['Students', 'Staff'],
                        datasets: [{
                            data: [<?php echo $total_students; ?>, <?php echo $total_staff; ?>],
                            backgroundColor: ['#4299e1', '#48bb78'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true }
                });

                // Vehicles Chart
                const ctxVehicles = document.getElementById('vehiclesChart').getContext('2d');
                new Chart(ctxVehicles, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($vehicle_types); ?>,
                        datasets: [{
                            data: <?php echo json_encode($vehicle_counts); ?>,
                            backgroundColor: ['#ed8936', '#9f7aea', '#38b2ac', '#f56565'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true }
                });

                // Bookings Chart
                const ctxBookings = document.getElementById('bookingsChart').getContext('2d');
                new Chart(ctxBookings, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($booking_statuses); ?>,
                        datasets: [{
                            label: 'Number of Bookings',
                            data: <?php echo json_encode($booking_counts); ?>,
                            backgroundColor: '#667eea',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, grid: { display: false } },
                            x: { grid: { display: false } }
                        }
                    }
                });

                // Parking Spaces Chart
                const ctxSpaces = document.getElementById('spacesChart').getContext('2d');
                new Chart(ctxSpaces, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available', 'Booked/Reserved', 'Maintenance'],
                        datasets: [{
                            data: [<?php echo $spaces_available; ?>, <?php echo $spaces_booked; ?>, <?php echo $spaces_maintenance; ?>],
                            backgroundColor: ['#48bb78', '#f56565', '#ed8936'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true }
                });

                // Parking Areas Chart
                const ctxAreas = document.getElementById('areasChart').getContext('2d');
                new Chart(ctxAreas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available', 'Closed/Maintenance'],
                        datasets: [{
                            data: [<?php echo $areas_available; ?>, <?php echo $areas_closed; ?>],
                            backgroundColor: ['#4299e1', '#e53e3e'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true }
                });
            </script>
        <?php endif; ?>
    </div>
</body>

</html>
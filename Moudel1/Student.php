<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Ensure user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';

// Only students can access this page
if ($user_type !== 'user') {
    header("Location: ../logout.php");
    exit();
}

// Determine current view
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

$conn = getDBConnection();
$error_message = '';
$success_message = '';

// Handle Add Vehicle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $vehicle_type = trim($_POST['vehicle_type']);
    $vehicle_model = trim($_POST['vehicle_model']);
    $license_plate = trim($_POST['license_plate']);

    if (empty($vehicle_type) || empty($vehicle_model) || empty($license_plate)) {
        $error_message = "Please fill in all fields.";
    } else {
        // Check if license plate already exists
        $check_stmt = $conn->prepare("SELECT vehicle_id FROM Vehicle WHERE license_plate = ?");
        $check_stmt->bind_param("s", $license_plate);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "This license plate is already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO Vehicle (user_id, vehicle_type, vehicle_model, license_plate) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $vehicle_type, $vehicle_model, $license_plate);

            if ($stmt->execute()) {
                $success_message = "Vehicle registered successfully!";
            } else {
                $error_message = "Failed to register vehicle. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Delete Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle'])) {
    $vehicle_id = intval($_POST['vehicle_id']);

    $stmt = $conn->prepare("DELETE FROM Vehicle WHERE vehicle_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "Vehicle deleted successfully!";
    } else {
        $error_message = "Failed to delete vehicle.";
    }
    $stmt->close();
}

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

// Fetch user's vehicles
$vehicles = [];
$stmt = $conn->prepare("SELECT vehicle_id, vehicle_type, vehicle_model, license_plate, created_at, approved_by, Approved_date, status FROM Vehicle WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}
$stmt->close();
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        if ($current_view === 'dashboard') echo 'Student Dashboard';
        elseif ($current_view === 'profile') echo 'My Profile';
        else echo 'My Vehicles';
    ?> - Mawgifi</title>
    <link rel="stylesheet" href="Student.css">
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="Student.php?view=dashboard" <?php echo $current_view === 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a>
            <a href="Student.php?view=vehicles" <?php echo $current_view === 'vehicles' ? 'class="active"' : ''; ?>>My Vehicles</a>
            <a href="../modules/parking/index.php">Find Parking</a>
            <a href="../modules/booking/index.php">My Bookings</a>
            <a href="Student.php?view=profile" <?php echo $current_view === 'profile' ? 'class="active"' : ''; ?>>Profile</a>
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
            <p>Manage your parking experience with Mawgifi</p>
        </div>

        <div class="modules-grid">
            <a href="Student.php?view=vehicles" class="module-card m1">
                <div class="module-icon">🚗</div>
                <h3>My Vehicles</h3>
                <p>Register and manage your vehicles for parking access.</p>
            </a>

            <a href="../modules/parking/index.php" class="module-card m2">
                <div class="module-icon">🅿️</div>
                <h3>Find Parking</h3>
                <p>Search for available parking spaces near you.</p>
            </a>

            <a href="../modules/booking/index.php" class="module-card m3">
                <div class="module-icon">📅</div>
                <h3>My Bookings</h3>
                <p>View and manage your parking reservations.</p>
            </a>
        </div>

        <!-- Quick Stats -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($vehicles); ?></div>
                <div class="stat-label">Registered Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $approved = 0;
                    foreach ($vehicles as $v) {
                        if ($v['status'] === 'approved') $approved++;
                    }
                    echo $approved;
                ?></div>
                <div class="stat-label">Approved Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $pending = 0;
                    foreach ($vehicles as $v) {
                        if ($v['status'] === 'pending') $pending++;
                    }
                    echo $pending;
                ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

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
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['UserName'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['PhoneNumber'] ?? ''); ?>" placeholder="Enter phone number">
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
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- Vehicles View -->
        <div class="module-header">
            <h1>My Vehicles</h1>
            <p>Register and manage your vehicles</p>
        </div>

        <div class="content-area">
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <!-- Add Vehicle Form -->
            <div class="form-section">
                <h2>Register New Vehicle</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type</label>
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select Type</option>
                                <option value="Car">Car</option>
                                <option value="Motorcycle">Motorcycle</option>
                                <option value="SUV">SUV</option>
                                <option value="Van">Van</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="vehicle_model">Vehicle Model</label>
                            <input type="text" id="vehicle_model" name="vehicle_model" placeholder="e.g. Toyota Camry" required>
                        </div>

                        <div class="form-group">
                            <label for="license_plate">License Plate</label>
                            <input type="text" id="license_plate" name="license_plate" placeholder="e.g. ABC1234" required>
                        </div>
                    </div>

                    <button type="submit" name="add_vehicle" class="btn">Register Vehicle</button>
                </form>
            </div>

            <!-- Vehicles List -->
            <div class="vehicles-section">
                <h2>My Registered Vehicles</h2>

                <?php if (empty($vehicles)): ?>
                    <div class="no-vehicles">
                        <p>You haven't registered any vehicles yet.</p>
                    </div>
                <?php else: ?>
                    <table class="vehicles-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Model</th>
                                <th>License Plate</th>
                                <th>Registered On</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($vehicle['license_plate']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                    <td>
                                        <?php if ($vehicle['status'] === 'approved'): ?>
                                            <span class="status-badge status-approved">Approved</span>
                                        <?php elseif ($vehicle['status'] === 'rejected'): ?>
                                            <span class="status-badge status-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this vehicle?');">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>

</html>

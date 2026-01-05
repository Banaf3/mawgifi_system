<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Ensure user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';

// Only staff and admin can access this page
if ($user_type !== 'staff' && $user_type !== 'admin') {
    header("Location: ../logout.php");
    exit();
}

// Determine current view
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

$conn = getDBConnection();
$error_message = '';
$success_message = '';

// Handle Approve Vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_vehicle'])) {
    $vehicle_id = intval($_POST['vehicle_id']);

    $stmt = $conn->prepare("UPDATE Vehicle SET Approved_date = NOW(), status = 'approved' WHERE vehicle_id = ?");
    $stmt->bind_param("i", $vehicle_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "Vehicle approved successfully!";
    } else {
        $error_message = "Failed to approve vehicle.";
    }
    $stmt->close();
}

// Handle Reject Vehicle (Mark as rejected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_vehicle'])) {
    $vehicle_id = intval($_POST['vehicle_id']);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($rejection_reason)) {
        $error_message = "Please provide a reason for rejection.";
    } else {
        $stmt = $conn->prepare("UPDATE Vehicle SET status = 'rejected' WHERE vehicle_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $vehicle_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Vehicle rejected.";
        } else {
            $error_message = "Failed to reject vehicle.";
        }
        $stmt->close();
    }
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

// Fetch pending vehicles (only from students)
$pending_vehicles = [];
$stmt = $conn->prepare("
    SELECT v.vehicle_id, v.vehicle_type, v.vehicle_model, v.license_plate, v.created_at, v.grant_document,
           u.UserName, u.Email, u.PhoneNumber
    FROM Vehicle v
    JOIN User u ON v.user_id = u.user_id
    WHERE v.status = 'pending' AND u.UserType = 'user'
    ORDER BY v.created_at ASC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_vehicles[] = $row;
}
$stmt->close();

// Fetch approved vehicles (only from students)
$approved_vehicles = [];
$stmt = $conn->prepare("
    SELECT v.vehicle_id, v.vehicle_type, v.vehicle_model, v.license_plate, v.created_at, v.Approved_date,
           u.UserName, u.Email
    FROM Vehicle v
    JOIN User u ON v.user_id = u.user_id
    WHERE v.status = 'approved' AND u.UserType = 'user'
    ORDER BY v.Approved_date DESC
    LIMIT 20
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $approved_vehicles[] = $row;
}
$stmt->close();

// Count total stats for dashboard
$total_pending = count($pending_vehicles);
$total_approved = count($approved_vehicles);

// Count rejected vehicles
$rejected_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM Vehicle v JOIN User u ON v.user_id = u.user_id WHERE v.status = 'rejected' AND u.UserType = 'user'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$rejected_count = $row['count'];
$stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
    if ($current_view === 'dashboard')
        echo 'Staff Dashboard';
    elseif ($current_view === 'profile')
        echo 'My Profile';
    else
        echo 'Vehicle Requests';
    ?> - Mawgifi</title>
    <link rel="stylesheet" href="Stafe.css?v=<?php echo time(); ?>">
</head>

<body>
    <nav class="navbar">
        <div class="brand">Mawgifi</div>

        <div class="nav-links">
            <a href="Stafe.php?view=dashboard" <?php echo $current_view === 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a>
            <a href="Stafe.php?view=requests" <?php echo $current_view === 'requests' ? 'class="active"' : ''; ?>>Vehicles
                Request</a>
            <a href="../modules/parking/index.php">Parking Areas</a>
            <a href="../modules/booking/index.php">Bookings</a>
            <a href="Stafe.php?view=profile" <?php echo $current_view === 'profile' ? 'class="active"' : ''; ?>>Profile</a>
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
                <p>Manage student vehicle requests and parking operations</p>
            </div>

            <div class="modules-grid">
                <a href="Stafe.php?view=requests" class="module-card m1">
                    <div class="module-icon">🚗</div>
                    <h3>Vehicle Requests</h3>
                    <p>Review and approve student vehicle registrations.</p>
                </a>

                <a href="../modules/parking/index.php" class="module-card m2">
                    <div class="module-icon">🅿️</div>
                    <h3>Parking Areas</h3>
                    <p>Manage parking zones and monitor space availability.</p>
                </a>

                <a href="../modules/booking/index.php" class="module-card m3">
                    <div class="module-icon">📋</div>
                    <h3>Bookings</h3>
                    <p>Oversee parking reservations and manage bookings.</p>
                </a>
            </div>

            <!-- Quick Stats -->
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_pending; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_approved; ?></div>
                    <div class="stat-label">Approved Vehicles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected Requests</div>
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

                <div class="section">
                    <h2>Personal Information</h2>
                    <form method="POST" action="" style="max-width: 600px;">
                        <div class="cards-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 20px;">
                            <div style="display: flex; flex-direction: column;">
                                <label style="font-weight: 600; margin-bottom: 8px;">Username</label>
                                <input type="text" name="username"
                                    value="<?php echo htmlspecialchars($user_data['UserName'] ?? ''); ?>" required
                                    style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                            </div>

                            <div style="display: flex; flex-direction: column;">
                                <label style="font-weight: 600; margin-bottom: 8px;">Email Address</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>" required
                                    style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Phone Number</label>
                            <input type="text" name="phone"
                                value="<?php echo htmlspecialchars($user_data['PhoneNumber'] ?? ''); ?>"
                                placeholder="Enter phone number"
                                style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; width: 100%; max-width: 280px;">
                        </div>

                        <h2 style="margin-top: 30px;">Change Password</h2>
                        <p style="color: #718096; margin-bottom: 20px;">Leave blank to keep current password</p>

                        <div class="cards-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 20px;">
                            <div style="display: flex; flex-direction: column;">
                                <label style="font-weight: 600; margin-bottom: 8px;">New Password</label>
                                <input type="password" name="password" placeholder="Enter new password"
                                    style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                            </div>

                            <div style="display: flex; flex-direction: column;">
                                <label style="font-weight: 600; margin-bottom: 8px;">Confirm Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password"
                                    style="padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-approve"
                            style="flex: none; padding: 12px 30px;">Update Profile</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Requests View -->
            <div class="module-header">
                <h1>🚗 Vehicles Request</h1>
                <p>Approve or reject student vehicle registrations</p>
            </div>

            <div class="content-area">
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <!-- Pending Requests -->
                <div class="section">
                    <h2>Pending Requests <span class="badge"><?php echo count($pending_vehicles); ?></span></h2>

                    <?php if (empty($pending_vehicles)): ?>
                        <div class="no-data">
                            <p>No pending vehicle requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="cards-grid">
                            <?php foreach ($pending_vehicles as $vehicle): ?>
                                <div class="vehicle-card pending">
                                    <div class="card-header">
                                        <span class="vehicle-type"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></span>
                                        <span class="status-badge status-pending">Pending</span>
                                    </div>
                                    <div class="card-body">
                                        <h3><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></h3>
                                        <p class="license-plate"><?php echo htmlspecialchars($vehicle['license_plate']); ?></p>

                                        <div class="owner-info">
                                            <p><strong>Owner:</strong> <?php echo htmlspecialchars($vehicle['UserName']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($vehicle['Email']); ?></p>
                                            <?php if ($vehicle['PhoneNumber']): ?>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($vehicle['PhoneNumber']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($vehicle['grant_document'])): ?>
                                                <p><strong>Vehicle Grant:</strong> <a
                                                        href="../<?php echo htmlspecialchars($vehicle['grant_document']); ?>"
                                                        target="_blank" style="color: #667eea; text-decoration: underline;">View
                                                        Document</a></p>
                                            <?php else: ?>
                                                <p><strong>Vehicle Grant:</strong> <span style="color: #e53e3e;">Not uploaded</span></p>
                                            <?php endif; ?>
                                        </div>

                                        <p class="date">Submitted:
                                            <?php echo date('M d, Y H:i', strtotime($vehicle['created_at'])); ?></p>
                                    </div>
                                    <div class="card-actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <button type="submit" name="approve_vehicle" class="btn btn-approve">✓ Approve</button>
                                        </form>
                                        <button type="button" class="btn btn-reject"
                                            onclick="openRejectModal(<?php echo $vehicle['vehicle_id']; ?>, '<?php echo htmlspecialchars($vehicle['vehicle_model'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($vehicle['license_plate'], ENT_QUOTES); ?>')">✗
                                            Reject</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recently Approved -->
                <div class="section">
                    <h2>Recently Approved</h2>

                    <?php if (empty($approved_vehicles)): ?>
                        <div class="no-data">
                            <p>No approved vehicles yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="vehicles-table">
                            <thead>
                                <tr>
                                    <th>Owner</th>
                                    <th>Vehicle</th>
                                    <th>License Plate</th>
                                    <th>Approved Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_vehicles as $vehicle): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vehicle['UserName']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['vehicle_type'] . ' - ' . $vehicle['vehicle_model']); ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($vehicle['license_plate']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($vehicle['Approved_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reject Vehicle Modal -->
    <div id="rejectVehicleModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content"
            style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; position: relative;">
            <span class="close-modal" onclick="closeRejectModal()"
                style="position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #666;">&times;</span>
            <h2 style="margin-bottom: 10px; color: #333;">Reject Vehicle</h2>
            <p id="rejectVehicleInfo" style="color: #666; margin-bottom: 20px;"></p>
            <form method="POST" action="">
                <input type="hidden" id="reject_vehicle_id" name="vehicle_id">

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="rejection_reason" style="display: block; margin-bottom: 8px; font-weight: 500;">Reason
                        for Rejection</label>
                    <textarea id="rejection_reason" name="rejection_reason" required rows="4"
                        placeholder="Please explain why this vehicle is being rejected..."
                        style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeRejectModal()" class="btn"
                        style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="reject_vehicle" class="btn btn-reject"
                        style="padding: 10px 20px;">Reject Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(vehicleId, vehicleModel, licensePlate) {
            document.getElementById('reject_vehicle_id').value = vehicleId;
            document.getElementById('rejectVehicleInfo').textContent = 'Vehicle: ' + vehicleModel + ' (' + licensePlate + ')';
            document.getElementById('rejection_reason').value = '';
            document.getElementById('rejectVehicleModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectVehicleModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            var modal = document.getElementById('rejectVehicleModal');
            if (event.target === modal) {
                closeRejectModal();
            }
        }
    </script>
</body>

</html>
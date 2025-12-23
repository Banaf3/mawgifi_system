<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Store redirect URL if provided
$redirect_url = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($redirect_url) {
        header("Location: " . $redirect_url);
        exit();
    }
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'staff':
            header("Location: Moudel1/Stafe.php");
            break;
        case 'user':
            header("Location: Moudel1/Student.php");
            break;
    }
    exit();
}

$error_message = '';
$success_message = '';
$show_forgot = isset($_GET['forgot']) || isset($_POST['reset']);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT user_id, UserName, Email, password, UserType FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['UserName'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['user_type'] = $user['UserType'];
                $_SESSION['login_time'] = time();

                // Redirect to custom URL if provided
                if ($redirect_url) {
                    header("Location: " . $redirect_url);
                    exit();
                }

                switch ($user['UserType']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'staff':
                        header("Location: Moudel1/Stafe.php");
                        break;
                    case 'user':
                        header("Location: Moudel1/Student.php");
                        break;
                    default:
                        header("Location: dashboard.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }

        $stmt->close();
        closeDBConnection($conn);
    }
}

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters.";
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT user_id FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE User SET password = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['user_id']);

            if ($update_stmt->execute()) {
                $success_message = "Password has been reset successfully. You can now login.";
                $show_forgot = false;
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error_message = "Email address not found.";
        }

        $stmt->close();
        closeDBConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Parking Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(90deg, #000000 0%, #1a1a1a 25%, rgba(0, 0, 0, 0.7) 40%, rgba(0, 0, 0, 0) 100%), url('assets/faculty.webp');
            background-size: cover;
            background-position: 70% center;
            background-repeat: no-repeat;
            min-height: 100vh;
            position: relative;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            position: absolute;
            left: 8%;
            top: 50%;
            transform: translateY(-50%);
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
            color: #ffffff;
            font-weight: 700;
        }

        .login-header p {
            font-size: 18px;
            color: #e0e0e0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 16px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .login-footer {
            text-align: left;
            margin-top: 30px;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .login-container {
                left: 50%;
                transform: translate(-50%, -50%);
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Mawgifi System</h1>
            <p><?php echo $show_forgot ? 'Reset your password.' : 'Welcome back! Please login to your account.'; ?></p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($show_forgot): ?>
            <!-- Forgot Password Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                        placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Confirm new password">
                </div>

                <button type="submit" name="reset" class="btn">Reset Password</button>
            </form>

            <div class="login-footer">
                <a href="login.php">Back to Login</a>
            </div>
        <?php else: ?>
            <!-- Login Form -->
            <form method="POST" action="">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                        placeholder="Enter your password">
                </div>

                <button type="submit" name="login" class="btn">Login</button>
            </form>

            <div class="login-footer">
                <a href="login.php?forgot=1">Forgot Password?</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
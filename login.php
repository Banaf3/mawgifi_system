<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        $conn = getDBConnection();
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, UserName, Email, password, UserType FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['PasswordHash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['UserName'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['user_type'] = $user['UserType'];
                $_SESSION['login_time'] = time();
                
                // Redirect based on user type
                switch ($user['UserType']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'staff':
                        header("Location: staff/dashboard.php");
                        break;
                    case 'user':
                        header("Location: student/dashboard.php");
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
            background: linear-gradient(90deg, #000000 0%, #1a1a1a 25%, rgba(0,0,0,0.7) 40%, rgba(0,0,0,0) 100%), url('assets/faculty.webp');
            background-size: cover;
            background-position: 70% center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            margin-left: 8%;
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
                margin-left: 0;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Mawgifi System</h1>
            <p>Welcome back! Please login to your account.</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
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
            <a href="register.php">Don't have an account? Register here</a>
        </div>
    </div>
</body>
</html>

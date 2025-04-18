<?php
// Start the session
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle logout
if (isset($_GET['logout'])) {
    // Destroy the session
    session_destroy();
    // Redirect to login page
    header('Location: admin-login.php');
    exit();
}

// Initialize variables for error messages
$error = '';

try {
    // Include the database connection
    $pdo = require '../database/db_connection.php';
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    $error = "Database connection error. Please check your configuration.";
}

// Add this function after session_start()
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

// Function to get location info
function getLocationInfo($ip) {
    try {
        $url = "http://ip-api.com/json/" . $ip;
        $response = @file_get_contents($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                return [
                    'city' => $data['city'] ?? 'Unknown City',
                    'region' => $data['regionName'] ?? 'Unknown Region',
                    'country' => $data['country'] ?? 'Unknown Country',
                    'ip' => $ip
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching location data: " . $e->getMessage());
    }
    
    return [
        'city' => 'Local',
        'region' => 'Network',
        'country' => '',
        'ip' => $ip
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the username and password from the form
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Debug login attempt
    error_log("Login attempt - Username: " . $username);

    // Check if username and password are provided
    if (!empty($username) && !empty($password)) {
        try {
            if (!isset($pdo)) {
                throw new Exception("Database connection is not available");
            }
            
            // Change the query to check the admin table
            $stmt = $pdo->prepare('SELECT * FROM admin WHERE username = ? AND password = ? AND Status = ?');
            $stmt->execute([$username, $password, 'active']);
            $admin = $stmt->fetch();

            if ($admin) {
                // Debug successful login
                error_log("Login successful for username: " . $username);
                
                // Get location information
                $ip = getClientIP();
                $locationInfo = getLocationInfo($ip);
                
                // Add login success log
                error_log("Admin login successful: " . $username . " from " . $ip);
                
                // Format location string
                $location = trim(implode(', ', array_filter([
                    $locationInfo['city'],
                    $locationInfo['region'],
                    $locationInfo['country']
                ])));
                
                // Add IP address if not local
                if ($locationInfo['city'] !== 'Local') {
                    $location .= " ({$locationInfo['ip']})";
                }

                // Update last login time and location
                $updateStmt = $pdo->prepare("
                    UPDATE admin 
                    SET last_login = NOW(),
                        login_location = ?
                    WHERE admin_id = ?
                ");
                $updateStmt->execute([$location, $admin['admin_id']]);

                // Set session variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_first_name'] = $admin['FirstName'];
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_status'] = $admin['Status'];

                // Debug session data
                error_log("Session data after login: " . print_r($_SESSION, true));

                // Redirect to the admin dashboard
                header('Location: admin-dashboard.php');
                exit();
            } else {
                error_log("Login failed - Invalid credentials for username: " . $username);
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    } else {
        $error = 'Please fill in both fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Book King</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background: #F8F8F8;
            padding: 20px;
        }

        .split-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            height: calc(100vh - 40px);
            max-height: 800px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .login-side {
            flex: 0.9;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px;
            position: relative;
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo img {
            width: 180px;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(176, 113, 84, 0.15));
        }

        .brand-side {
            flex: 1.1;
            background: #B07154;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 50px;
            position: relative;
            overflow: hidden;
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.1) 75%, transparent 75%, transparent);
            background-size: 40px 40px;
            opacity: 0.1;
            animation: slide 30s linear infinite;
        }

        @keyframes slide {
            from { transform: translateX(-50%) translateY(-50%) rotate(0deg); }
            to { transform: translateX(-50%) translateY(-50%) rotate(360deg); }
        }

        .brand-content {
            text-align: center;
            position: relative;
            z-index: 1;
            max-width: 500px;
            padding: 20px;
        }

        .brand-logo {
            width: 260px;
            margin-bottom: 35px;
            filter: brightness(0) invert(1);
        }

        .brand-title {
            color: white;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .brand-description {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            line-height: 1.7;
            max-width: 440px;
            margin: 0 auto;
            font-weight: 500;
        }

        .welcome-text {
            color: #B07154;
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.2;
            text-align: center;
        }

        .login-subtitle {
            color: #666;
            margin-bottom: 35px;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
        }

        .input-container {
            margin-bottom: 20px;
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 15px 18px;
            padding-left: 45px;
            padding-right: 45px;
            border: 2px solid #F0F0F0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        .input-field:focus {
            outline: none;
            border-color: #B07154;
            box-shadow: 0 0 0 3px rgba(176, 113, 84, 0.1);
        }

        .input-field::placeholder {
            color: #999;
            font-size: 14px;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #B07154;
            opacity: 0.7;
            font-size: 18px;
            z-index: 2;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            opacity: 0.7;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
            padding: 8px;
            z-index: 2;
        }

        .toggle-password:hover {
            opacity: 1;
            color: #B07154;
        }

        .signin-btn {
            width: 100%;
            padding: 16px;
            background: #B07154;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }

        .signin-btn:hover {
            background: #95604A;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(176, 113, 84, 0.2);
        }

        .signin-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #FFF5F5;
            color: #DC2626;
            border: 1px solid #FCA5A5;
            padding: 15px 18px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 1366px) {
            .split-container {
                max-width: 1000px;
                max-height: 700px;
            }

            .login-side, .brand-side {
                padding: 40px;
            }

            .brand-logo {
                width: 220px;
                margin-bottom: 30px;
            }

            .brand-title {
                font-size: 32px;
                margin-bottom: 16px;
            }

            .brand-description {
                font-size: 15px;
            }
        }

        @media (max-width: 1024px) {
            .split-container {
                max-width: 900px;
                max-height: 650px;
            }

            .login-side {
                flex: 1;
                padding: 30px;
            }
            
            .brand-side {
                flex: 1;
                padding: 30px;
            }

            .welcome-text {
                font-size: 28px;
            }

            .login-subtitle {
                font-size: 14px;
                margin-bottom: 30px;
            }

            .brand-logo {
                width: 200px;
            }

            .brand-title {
                font-size: 28px;
            }

            .brand-description {
                font-size: 14px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .split-container {
                height: 100vh;
                max-height: none;
                border-radius: 0;
                box-shadow: none;
            }

            .brand-side {
                display: none;
            }

            .login-side {
                padding: 24px;
                border-radius: 0;
            }

            .login-container {
                max-width: 400px;
            }

            .welcome-text {
                font-size: 28px;
            }

            .login-subtitle {
                font-size: 14px;
                margin-bottom: 30px;
            }

            .input-field {
                padding: 14px 16px;
            }

            .signin-btn {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="split-container">
        <div class="login-side">
            <div class="login-container">
                <div class="login-logo">
                    <img src="../images/logo2.png" alt="Book King Logo">
                </div>
                <h1 class="welcome-text">Welcome Back!</h1>
                <p class="login-subtitle">Sign in to access your admin dashboard</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-container">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" class="input-field" placeholder="Enter your username" required autocomplete="off">
                    </div>
                    <div class="input-container">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="input-field" placeholder="Enter your password" required>
                        <i class="fas fa-eye-slash toggle-password" onclick="togglePassword()"></i>
                    </div>
                    <button type="submit" class="signin-btn">
                        <span class="signin-btn-text">Sign In</span>
                    </button>
                </form>

                <div class="admin-links" style="margin-top: 20px; text-align: center;">
                    <a href="admin-manage.php" style="color: #B07154; text-decoration: none; font-size: 14px;">
                        <i class="fas fa-users-cog"></i> Manage Administrators
                    </a>
                </div>
            </div>
        </div>

        <div class="brand-side">
            <div class="brand-content">
                <img src="../images/logo2.png" alt="Book King Logo" class="brand-logo">
                <h2 class="brand-title">Book King Library</h2>
                <p class="brand-description">
                    Welcome to your comprehensive library management portal. Efficiently manage books, users, and resources with our powerful administrative tools.
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                    <div class="admin-actions" style="margin-top: 20px;">
                        <a href="admin-manage.php" style="color: white; text-decoration: none; display: inline-block; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 8px; margin: 5px;">
                            <i class="fas fa-users-cog"></i> Manage Administrators
                        </a>
                        <a href="admin-dashboard.php" style="color: white; text-decoration: none; display: inline-block; padding: 10px 20px; background: rgba(255,255,255,0.2); border-radius: 8px; margin: 5px;">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </div>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>

</html>
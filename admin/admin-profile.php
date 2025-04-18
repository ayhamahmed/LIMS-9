<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection
$pdo = require '../database/db_connection.php';

// Include the authentication file
require 'auth.php';

// Check if the user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin-login.php');
    exit();
}

// Handle status toggle via AJAX
if (isset($_POST['toggle_status']) && isset($_POST['admin_id'])) {
    // Check if the admin performing the action is active
    if ($_SESSION['admin_status'] !== 'active') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Your account is deactivated. You cannot perform this action.']);
        exit();
    }

    $admin_id = (int)$_POST['admin_id'];
    
    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT Status FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $currentStatus = $stmt->fetchColumn();
        
        // Toggle status
        $newStatus = ($currentStatus === 'active') ? 'deactive' : 'active';
        
        // Update status
        $stmt = $pdo->prepare("UPDATE admin SET Status = ? WHERE admin_id = ?");
        $stmt->execute([$newStatus, $admin_id]);

        // If admin deactivated themselves, destroy their session
        if ($admin_id === $_SESSION['admin_id'] && $newStatus === 'deactive') {
            $_SESSION['admin_status'] = 'deactive';
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'new_status' => $newStatus,
            'message' => 'Status updated successfully',
            'self_deactivated' => ($admin_id === $_SESSION['admin_id'] && $newStatus === 'deactive')
        ]);
        exit();
    } catch (PDOException $e) {
        error_log("Status update error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred while updating status.'
        ]);
        exit();
    }
}

// Get admin ID from URL
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch admin details
try {
    $stmt = $pdo->prepare('SELECT admin_id, Username, FirstName, LastName, Status, last_login, login_location FROM admin WHERE admin_id = ?');
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        $_SESSION['error_message'] = "Admin not found";
        header('Location: admin-dashboard.php');
        exit();
    }

    // Set default values if fields are null
    $admin['last_login'] = $admin['last_login'] ?? date('Y-m-d H:i:s');
    $admin['login_location'] = $admin['login_location'] ?? 'Not Available';
} catch (PDOException $e) {
    error_log("Error fetching admin details: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading admin profile";
    header('Location: admin-dashboard.php');
    exit();
}

// Add this PHP code near the top after session_start()
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

// Get client IP and location data
$client_ip = getClientIP();
$location_data = null;

try {
    // Use ipapi.co for geolocation (free tier)
    $location_json = @file_get_contents("http://ip-api.com/json/" . $client_ip);
    if ($location_json) {
        $location_data = json_decode($location_json, true);
    }
} catch (Exception $e) {
    error_log("Error fetching location data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Book King</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        :root {
            --primary: #B07154;
            --primary-light: #F4DECB;
            --text-dark: #2C3E50;
            --text-light: #666;
            --success: #2ECC71;
            --bg-light: #F8F9FA;
        }

        body {
            background: linear-gradient(135deg, var(--primary-light) 0%, #FFF5EE 100%);
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        .content {
            margin-left: 240px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            padding: 0;
        }

        .content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(176, 113, 84, 0.1) 0%, rgba(244, 222, 203, 0.2) 100%);
            z-index: -1;
        }

        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
        }

        .datetime-display {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .time-section, .date-section {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
        }

        .time-icon, .date-icon {
            width: 24px;
            height: 24px;
            fill: var(--primary);
        }

        .time-display, .date-display {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .admin-name-1 {
            font-size: 22px;
            color: #B07154;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .main-content {
            flex: 1;
            display: flex;
            padding: 24px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }

        .profile-container {
            flex: 1;
            display: flex;
            justify-content: stretch;
            align-items: stretch;
            padding: 0;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 24px;
            width: 100%;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(176, 113, 84, 0.1);
            display: flex;
            flex-direction: column;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 0 10px;
        }

        .back-link {
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 600;
            padding: 12px 24px;
            background: var(--primary-light);
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .back-link:hover {
            transform: translateX(-5px);
            background: #EFD5C3;
        }

        .back-link svg {
            width: 20px;
            height: 20px;
            fill: var(--primary);
        }

        .profile-content {
            display: flex;
            gap: 30px;
            flex: 1;
            height: calc(100vh - 240px);
        }

        .profile-left {
            flex: 0 0 300px;
            padding: 24px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            text-align: center;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(176, 113, 84, 0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
        }

        .profile-image {
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(176, 113, 84, 0.2);
        }

        .profile-image img {
            width: 100px;
            height: 100px;
            filter: brightness(0) invert(1);
        }

        .profile-name {
            font-size: 32px;
            color: var(--text-dark);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .profile-role {
            color: var(--text-light);
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .status-badges {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 8px;
        }

        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-badge.active {
            background: rgba(39, 174, 96, 0.1);
            color: #27AE60;
        }

        .status-badge.active:hover {
            background: rgba(39, 174, 96, 0.2);
            transform: translateY(-2px);
        }

        .status-badge.inactive {
            background: rgba(231, 76, 60, 0.1);
            color: #E74C3C;
        }

        .status-badge.inactive:hover {
            background: rgba(231, 76, 60, 0.2);
            transform: translateY(-2px);
        }

        .status-update-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .status-update-modal {
            background: white;
            padding: 24px;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-title {
            color: #2C3E50;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .modal-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 24px;
        }

        .modal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        .modal-button {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Montserrat', sans-serif;
        }

        .confirm-button {
            background: #B07154;
            color: white;
        }

        .confirm-button:hover {
            background: #95604A;
        }

        .cancel-button {
            background: #E5E7EB;
            color: #4B5563;
        }

        .cancel-button:hover {
            background: #D1D5DB;
        }

        .profile-right {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(176, 113, 84, 0.08);
            overflow-y: auto;
            height: 100%;
        }

        .details-title {
            color: #B07154;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(176, 113, 84, 0.1);
            font-family: 'Montserrat', sans-serif;
        }

        .profile-info-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(176, 113, 84, 0.1);
            border: 1px solid rgba(176, 113, 84, 0.1);
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .info-item {
            background: rgba(176, 113, 84, 0.03);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(176, 113, 84, 0.08);
        }

        .info-item:hover {
            background: rgba(176, 113, 84, 0.06);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(176, 113, 84, 0.1);
            border-color: rgba(176, 113, 84, 0.15);
        }

        .info-label {
            color: #B07154;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }

        .info-value {
            color: #2C3E50;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
        }

        .permission-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            background: rgba(176, 113, 84, 0.1);
            color: #B07154;
            font-family: 'Montserrat', sans-serif;
        }

        .location-value {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .location-value::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #B07154;
            flex-shrink: 0;
        }

        .no-location {
            color: #666;
            font-style: italic;
        }

        .info-value.location-value {
            min-height: 24px;
            line-height: 24px;
        }

        @media (max-width: 1200px) {
            .profile-content {
                flex-direction: column;
                height: auto;
            }

            .profile-left {
                flex: 0 0 auto;
                width: 100%;
                margin-bottom: 20px;
            }

            .profile-right {
                width: 100%;
            }

            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }

            .main-content {
                padding: 16px;
            }

            .profile-card {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .profile-image {
                width: 140px;
                height: 140px;
            }

            .profile-image img {
                width: 80px;
                height: 80px;
            }

            .profile-name {
                font-size: 24px;
            }

            .profile-role {
                font-size: 16px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-info-section {
                padding: 20px;
            }
            
            .info-item {
                padding: 15px;
            }
        }

        .change-password-btn {
            margin-top: 20px;
            padding: 12px 24px;
            background: #B07154;
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .change-password-btn:hover {
            background: #95604A;
            transform: translateY(-2px);
        }

        .password-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .password-modal {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #B07154;
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2C3E50;
            font-weight: 500;
        }

        .password-input-container {
            position: relative;
        }

        .password-input-container input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .password-requirements {
            margin: 20px 0;
            padding: 15px;
            background: #F8F9FA;
            border-radius: 8px;
        }

        .password-requirements p {
            margin: 0 0 10px 0;
            color: #2C3E50;
            font-weight: 500;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #666;
        }

        .password-requirements li {
            margin: 5px 0;
        }

        .password-requirements li.valid {
            color: #27AE60;
        }

        .form-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .cancel-btn {
            padding: 10px 20px;
            background: #E5E7EB;
            color: #4B5563;
            border: none;
            border-radius: 6px;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-btn {
            padding: 10px 20px;
            background: #B07154;
            color: white;
            border: none;
            border-radius: 6px;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #95604A;
        }

        .cancel-btn:hover {
            background: #D1D5DB;
        }

        .success-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #B07154;
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(176, 113, 84, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            z-index: 2000;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.5s ease forwards, fadeOut 0.5s ease 2.5s forwards;
        }

        .success-notification::before {
            content: '✓';
            background: rgba(255, 255, 255, 0.2);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="../images/logo.png" alt="Book King Logo">
        </div>
        <div class="nav-group">
            <a href="../admin/admin-dashboard.php" class="nav-item">
                <div class="icon">
                    <img src="../images/element-2 2.svg" alt="Dashboard" width="24" height="24">
                </div>
                <div class="text">Dashboard</div>
            </a>
            <a href="../admin/catalog.php" class="nav-item">
                <div class="icon">
                    <img src="../images/Vector.svg" alt="Catalog" width="20" height="20">
                </div>
                <div class="text">Catalog</div>
            </a>
            <a href="../admin/book-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/book.png" alt="Books" width="24" height="24">
                </div>
                <div class="text">Books</div>
            </a>
            <a href="../admin/user-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/people 3.png" alt="Users" width="24" height="24">
                </div>
                <div class="text">Users</div>
            </a>
            <a href="../admin/branch-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/buildings-2 1.png" alt="Branches" width="24" height="24">
                </div>
                <div class="text">Branches</div>
            </a>
        </div>
        <a href="../admin/admin-logout.php" class="nav-item">
            <div class="icon">
                <img src="../images/logout 3.png" alt="Log Out" width="24" height="24">
            </div>
            <div class="text">Log Out</div>
        </a>
    </div>
    <div class="content">
        <div class="header">
            <div class="admin-profile">
                <div class="admin-info">
                    <span class="admin-name-1">Welcome, <?= htmlspecialchars($_SESSION['admin_first_name']) ?></span>
                </div>
            </div>
            <div class="datetime-display">
                <div class="time-section">
                    <svg class="time-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
                        <path d="M13 7h-2v6l4.5 2.7.7-1.2-3.2-1.9z"/>
                    </svg>
                    <span id="current-time" class="time-display"></span>
                </div>
                <div class="date-section">
                    <svg class="date-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16v7zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1v3z"/>
                    </svg>
                    <span id="current-date" class="date-display"></span>
                </div>
            </div>
        </div>
        <div class="main-content">
            <div class="profile-container">
                <div class="profile-card">
                    <div class="profile-header">
                        <a href="admin-dashboard.php" class="back-link">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                            Back to Dashboard
                        </a>
                    </div>
                    <div class="profile-content">
                        <div class="profile-left">
                            <div class="profile-image">
                                <img src="../images/security-user 1.png" alt="Admin">
                            </div>
                            <div class="profile-name"><?= htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']) ?></div>
                            <div class="profile-role">System Administrator</div>
                            <div class="status-badges">
                                <div class="status-badge <?= $admin['Status'] === 'active' ? 'active' : 'deactive' ?>" 
                                     onclick="toggleStatus(<?= $admin['admin_id'] ?>)" 
                                     id="status-badge">
                                    <span class="status-text"><?= ucfirst($admin['Status']) ?></span>
                                </div>
                            </div>
                            <button class="change-password-btn" onclick="showChangePasswordModal()">
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>
                        </div>
                        <div class="profile-right">
                            <div class="details-title">Profile Information</div>
                            <div class="profile-info-section">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">Admin ID</div>
                                        <div class="info-value"><?= htmlspecialchars($admin['admin_id']) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Username</div>
                                        <div class="info-value"><?= htmlspecialchars($admin['Username']) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Account Status</div>
                                        <div class="status-badge <?= $admin['Status'] === 'active' ? 'active' : 'inactive' ?>">
                                            <?= ucfirst($admin['Status'] ?? 'Active') ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Permission Level</div>
                                        <div class="permission-badge">Administrator</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Last Login</div>
                                        <div class="info-value">
                                            <?php 
                                            if (!empty($admin['last_login'])) {
                                                echo date('M d, Y, g:i A', strtotime($admin['last_login']));
                                            } else {
                                                echo 'Never logged in';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Login Location</div>
                                        <div class="info-value location-value">
                                            <?php 
                                            if (!empty($admin['login_location'])) {
                                                echo htmlspecialchars($admin['login_location']);
                                            } else {
                                                echo '<span class="no-location">Not Available</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="status-update-overlay" id="statusUpdateModal">
        <div class="status-update-modal">
            <div class="modal-title">Update Account Status</div>
            <div class="modal-message">Are you sure you want to change the account status?</div>
            <div class="modal-buttons">
                <button class="modal-button confirm-button" onclick="confirmStatusUpdate()">Confirm</button>
                <button class="modal-button cancel-button" onclick="closeStatusModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div class="password-modal-overlay" id="changePasswordModal">
        <div class="password-modal">
            <div class="modal-header">
                <h3>Change Password</h3>
                <button class="close-btn" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <form id="changePasswordForm" onsubmit="return handlePasswordChange(event)">
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <div class="password-input-container">
                        <input type="password" id="currentPassword" name="currentPassword" required>
                        <i class="fas fa-eye-slash toggle-password" onclick="togglePasswordVisibility('currentPassword')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="newPassword" name="newPassword" required>
                        <i class="fas fa-eye-slash toggle-password" onclick="togglePasswordVisibility('newPassword')"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                        <i class="fas fa-eye-slash toggle-password" onclick="togglePasswordVisibility('confirmPassword')"></i>
                    </div>
                </div>
                <div class="password-requirements">
                    <p>Password must contain:</p>
                    <ul>
                        <li id="length">At least 8 characters</li>
                        <li id="uppercase">One uppercase letter</li>
                        <li id="lowercase">One lowercase letter</li>
                        <li id="number">One number</li>
                    </ul>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeChangePasswordModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateDateTime() {
            const now = new Date();
            
            // Update time
            const timeDiv = document.getElementById('current-time');
            timeDiv.textContent = now.toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            
            // Update date
            const dateDiv = document.getElementById('current-date');
            dateDiv.textContent = now.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            });
        }

        // Update immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Detective feature functionality
        function updateDetectiveInfo() {
            // Simulate last login detection
            setTimeout(() => {
                document.getElementById('last-login').textContent = new Date().toLocaleString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }, 2000);

            // Simulate location detection
            setTimeout(() => {
                document.getElementById('login-location').textContent = 'Local Network (192.168.1.xxx)';
            }, 3000);

            // Simulate system access analysis
            setTimeout(() => {
                document.getElementById('system-access').textContent = 'Full System Control';
            }, 4000);
        }

        // Initialize detective features
        updateDetectiveInfo();

        let pendingAdminId = null;
        let pendingStatusUpdate = null;

        function showStatusModal(adminId, currentStatus) {
            // Check if admin is active
            if ('<?= $_SESSION['admin_status'] ?>' !== 'active') {
                alert('Your account is deactivated. You cannot change account status.');
                return false;
            }

            pendingAdminId = adminId;
            pendingStatusUpdate = currentStatus === 'active' ? 'deactive' : 'active';
            const modal = document.getElementById('statusUpdateModal');
            const message = document.querySelector('.modal-message');
            
            // Customize message for self-activation
            if (adminId === <?= $_SESSION['admin_id'] ?>) {
                message.textContent = currentStatus === 'active' 
                    ? 'Are you sure you want to deactivate your own account? You will be logged out.'
                    : 'Are you sure you want to activate your account?';
            } else {
                message.textContent = `Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this account?`;
            }
            
            modal.style.display = 'flex';
        }

        function closeStatusModal() {
            const modal = document.getElementById('statusUpdateModal');
            modal.style.display = 'none';
            pendingAdminId = null;
            pendingStatusUpdate = null;
        }

        function confirmStatusUpdate() {
            if (pendingAdminId === null) return;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `toggle_status=1&admin_id=${pendingAdminId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update all status badges
                    const statusBadges = document.querySelectorAll('.status-badge');
                    statusBadges.forEach(badge => {
                        badge.classList.remove('active', 'inactive');
                        badge.classList.add(data.new_status === 'active' ? 'active' : 'inactive');
                        const statusText = badge.querySelector('.status-text');
                        if (statusText) {
                            statusText.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
                        }
                    });
                    
                    // Show success notification
                    const notification = document.createElement('div');
                    notification.className = 'success-notification';
                    notification.textContent = data.message || `Account status updated to ${data.new_status}`;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);

                    // Handle self-deactivation
                    if (data.self_deactivated) {
                        setTimeout(() => {
                            window.location.href = 'admin-login.php?error=deactivated';
                        }, 2000);
                    }
                } else {
                    // Show error message
                    const errorNotification = document.createElement('div');
                    errorNotification.className = 'error-notification';
                    errorNotification.style.background = '#E74C3C';
                    errorNotification.style.color = 'white';
                    errorNotification.style.padding = '16px 24px';
                    errorNotification.style.borderRadius = '12px';
                    errorNotification.style.position = 'fixed';
                    errorNotification.style.top = '20px';
                    errorNotification.style.right = '20px';
                    errorNotification.style.zIndex = '2000';
                    errorNotification.style.boxShadow = '0 4px 15px rgba(231, 76, 60, 0.3)';
                    errorNotification.style.fontFamily = 'Montserrat, sans-serif';
                    errorNotification.style.display = 'flex';
                    errorNotification.style.alignItems = 'center';
                    errorNotification.style.gap = '12px';
                    errorNotification.innerHTML = `
                        <span style="
                            background: rgba(255,255,255,0.2);
                            width: 24px;
                            height: 24px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">!</span>
                        <span>${data.message || 'Failed to update status'}</span>
                    `;
                    document.body.appendChild(errorNotification);
                    
                    setTimeout(() => {
                        errorNotification.remove();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the status. Please try again.');
            })
            .finally(() => {
                closeStatusModal();
            });
        }

        // Update the click handler for status badges
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                const adminId = <?= $admin['admin_id'] ?>;
                const currentStatus = this.classList.contains('active') ? 'active' : 'inactive';
                showStatusModal(adminId, currentStatus);
            });
        });

        // Update location information periodically
        function updateLocationInfo() {
            fetch("get_location.php")
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        document.getElementById("login-location").textContent = 
                            `${data.city}, ${data.regionName}, ${data.country} (${data.query})`;
                    }
                })
                .catch(error => console.error("Error updating location:", error));
        }

        // Update location every 5 minutes
        setInterval(updateLocationInfo, 300000);

        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'flex';
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            document.getElementById('changePasswordForm').reset();
            resetPasswordRequirements();
        }

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };

            // Update UI
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById(req);
                if (requirements[req]) {
                    element.classList.add('valid');
                } else {
                    element.classList.remove('valid');
                }
            });

            return Object.values(requirements).every(Boolean);
        }

        function resetPasswordRequirements() {
            ['length', 'uppercase', 'lowercase', 'number'].forEach(req => {
                document.getElementById(req).classList.remove('valid');
            });
        }

        function handlePasswordChange(event) {
            event.preventDefault();
            
            if ('<?= $_SESSION['admin_status'] ?>' !== 'active') {
                alert('Your account is deactivated. You cannot change your password.');
                return false;
            }
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!validatePassword(newPassword)) {
                alert('Please ensure your password meets all requirements.');
                return false;
            }

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                return false;
            }

            // Send password change request
            fetch('change-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    currentPassword: currentPassword,
                    newPassword: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create and show success notification
                    const notification = document.createElement('div');
                    notification.className = 'success-notification';
                    notification.textContent = 'Password changed successfully!';
                    document.body.appendChild(notification);

                    // Remove notification after animation
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);

                    closeChangePasswordModal();
                } else {
                    alert(data.message || 'Failed to change password. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });

            return false;
        }

        // Add password validation on input
        document.getElementById('newPassword').addEventListener('input', function() {
            validatePassword(this.value);
        });

        // Add this function to check admin status
        function checkAdminStatus() {
            const isActive = '<?= $_SESSION['admin_status'] ?>' === 'active';
            const managementButtons = document.querySelectorAll('.change-password-btn, .nav-item:not([href*="admin-logout"]), .status-badge');
            
            if (!isActive) {
                managementButtons.forEach(button => {
                    if (button.classList.contains('nav-item')) {
                        button.addEventListener('click', function(e) {
                            if (!button.href.includes('admin-logout.php')) {
                                e.preventDefault();
                                alert('Your account is deactivated. You cannot access management features.');
                            }
                        });
                        button.style.opacity = '0.5';
                        button.style.cursor = 'not-allowed';
                    } else {
                        button.disabled = true;
                        button.style.opacity = '0.5';
                        button.style.cursor = 'not-allowed';
                        button.onclick = function(e) {
                            e.preventDefault();
                            alert('Your account is deactivated. You cannot access management features.');
                        };
                    }
                });
            }
        }

        // Call the function when the page loads
        document.addEventListener('DOMContentLoaded', checkAdminStatus);

        // Add to your existing JavaScript
        function toggleStatus(adminId) {
            if ('<?= $_SESSION['admin_status'] ?>' !== 'active') {
                alert('Your account is deactivated. You cannot change account status.');
                return false;
            }
            
            // ... rest of your existing toggleStatus code ...
        }
    </script>
</body>
</html> 
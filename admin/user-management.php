<?php
// Start session at the beginning of the file
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin/admin-login.php');
    exit();
}

// Get admin name from session
$adminFirstName = $_SESSION['admin_first_name'] ?? 'Admin';
$adminLastName = $_SESSION['admin_last_name'] ?? '';

// Include the database connection
$pdo = require '../database/db_connection.php';

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = "User deleted successfully";
        header('Location: user-management.php');
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error deleting user: " . $e->getMessage();
    }
}

// Initialize messages
$successMessage = null;
$errorMessage = null;

// Get messages from session and clear them
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Initialize variables
$errors = [];

// First, let's add the missing columns if they don't exist
try {
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS firstname VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS lastname VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS phone VARCHAR(15) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
} catch (PDOException $e) {
    $errors[] = "Error updating table structure: " . $e->getMessage();
}

// Fetch all users with only existing columns
try {
    $stmt = $pdo->query("SELECT 
        user_id, 
        email,
        firstname,
        lastname,
        is_active 
        FROM users 
        ORDER BY user_id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching users: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <style>
        .container {
            padding: 20px;
            background: #FEF3E8;
            border-radius: 12px;
            margin: 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0;
        }

        .page-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            font-family: 'Montserrat', sans-serif;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-table th {
            background: #FEF3E8;
            color: #B07154;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #F4DECB;
        }

        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #F4DECB;
            color: #333;
            font-size: 14px;
        }

        .user-table tr:last-child td {
            border-bottom: none;
        }

        .user-table tr:hover {
            background-color: #FEF3E8;
        }

        .status-active {
            background: #DEF7EC;
            color: #03543F;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-inactive {
            background: #FDE8E8;
            color: #9B1C1C;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .edit-btn {
            background: #F4DECB;
            color: #B07154;
        }

        .edit-btn:hover {
            background: #E4C4A9;
            transform: translateY(-1px);
        }

        .delete-btn {
            background: #B07154;
            color: white;
        }

        .delete-btn:hover {
            background: #8B5B43;
            transform: translateY(-1px);
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .user-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                border-radius: 8px;
                margin: 10px 0;
            }

            .user-table th,
            .user-table td {
                padding: 12px;
                font-size: 13px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }

            .action-btn {
                width: 100%;
                text-align: center;
            }
        }

        /* Container styles */
        .container {
            padding: 20px;
            background: #FEF3E8;
            border-radius: 12px;
            margin: 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0;
        }

        .page-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            font-family: 'Montserrat', sans-serif;
        }

        /* Mobile responsive container */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        .success-message {
            background-color: #DEF7EC;
            color: #03543F;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #03543F;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .error-message {
            background-color: #FDE8E8;
            color: #9B1C1C;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #9B1C1C;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Auto-hide messages after 3 seconds */
        .success-message, .error-message {
            animation: fadeOut 0.5s ease 3s forwards;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .close-btn {
            color: #B07154;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            padding: 0 8px;
            line-height: 1;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #95604A;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #F4DECB;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #B07154;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        .cancel-btn, .submit-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .cancel-btn {
            background: #F4DECB;
            color: #B07154;
        }

        .cancel-btn:hover {
            background: #E8C5B0;
        }

        .submit-btn {
            background: #B07154;
            color: white;
        }

        .submit-btn:hover {
            background: #95604A;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(176, 113, 84, 0.2);
        }

        /* Notification Styles */
        #notificationContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }

        .notification {
            background: #FEF3E8;
            border: 2px solid #B07154;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(176, 113, 84, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(120%);
            transition: transform 0.4s ease;
            min-width: 320px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-icon {
            font-size: 24px;
        }

        .notification-text {
            color: #B07154;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            font-size: 16px;
            flex-grow: 1;
        }

        .notification-close {
            cursor: pointer;
            color: #B07154;
            font-size: 24px;
            padding: 0 5px;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .notification-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                padding: 20px;
                width: 95%;
            }

            .notification {
                width: 90%;
                min-width: auto;
                margin: 0 auto 10px;
            }

            #notificationContainer {
                left: 0;
                right: 0;
                padding: 0 10px;
            }
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
            color: #B07154;
        }

        .time-icon, .date-icon {
            width: 24px;
            height: 24px;
        }

        .time-display, .date-display {
            font-size: 16px;
            font-weight: 500;
            color: #B07154;
            font-family: 'Montserrat', sans-serif;
        }

        .admin-name-1 {
            font-size: 22px;
            color: #B07154;
            font-weight: 600;
            margin-bottom: 4px;
            font-family: 'Montserrat', sans-serif;
        }

        @media (max-width: 768px) {
            .datetime-display {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        /* Delete confirmation modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .delete-confirm-modal {
            max-width: 400px !important;
            text-align: center;
            padding: 30px !important;
            background: #FEF3E8 !important;
            border: 2px solid #F4DECB;
            margin: 10% auto;
            border-radius: 12px;
            position: relative;
        }

        .delete-icon {
            margin: 0 auto 20px;
            width: 48px;
            height: 48px;
        }

        .delete-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            font-family: 'Montserrat', sans-serif;
        }

        .delete-message {
            color: #8B5B43;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 24px;
            font-family: 'Montserrat', sans-serif;
        }

        .delete-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .cancel-delete-btn {
            padding: 10px 20px;
            border: 2px solid #F4DECB;
            background: white;
            color: #B07154;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Montserrat', sans-serif;
        }

        .cancel-delete-btn:hover {
            background: #F4DECB;
            border-color: #B07154;
        }

        .confirm-delete-btn {
            padding: 10px 20px;
            border: none;
            background: #B07154;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Montserrat', sans-serif;
        }

        .confirm-delete-btn:hover {
            background: #8B5B43;
        }

        @media (max-width: 640px) {
            .delete-confirm-modal {
                margin: 16px;
                padding: 20px !important;
            }

            .delete-actions {
                flex-direction: column-reverse;
            }

            .cancel-delete-btn,
            .confirm-delete-btn {
                width: 100%;
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="mobile-menu-btn">
        <span></span>
        <span></span>
        <span></span>
    </div>
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
            <a href="../admin/user-management.php" class="nav-item active">
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
            <a href="../admin/borrowers-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/user.png" alt="Borrowers" width="24" height="24">
                </div>
                <div class="text">Borrowers</div>
            </a>
            <a href="../admin/admin-manage.php" class="nav-item">
                <div class="icon">
                    <img src="../images/security-user 1.png" alt="Manage Admins" width="24" height="24">
                </div>
                <div class="text">Manage Admins</div>
            </a>
        </div>
        <a href="../admin/admin-logout.php" class="nav-item logout">
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
                    <span class="admin-name-1">Welcome, <?= htmlspecialchars($adminFirstName . ' ' . $adminLastName) ?></span>
                </div>
            </div>
            <div class="datetime-display">
                <div class="time-section">
                    <svg class="time-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#B07154" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.59-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                    </svg>
                    <span class="time-display">--:--:-- --</span>
                </div>
                <div class="date-section">
                    <svg class="date-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#B07154" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16v7zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1v3z"/>
                    </svg>
                    <span class="date-display">--- --, ----</span>
                </div>
            </div>
        </div>
        <div class="container">
            <?php if ($successMessage): ?>
                <div class="success-message">
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="error-message">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <div class="header-section">
                <h1 class="page-title">User Management</h1>
            </div>

            <table class="user-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['user_id']) ?></td>
                                <td><?= htmlspecialchars($user['firstname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['lastname'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="status-<?= isset($user['is_active']) ? ($user['is_active'] ? 'active' : 'inactive') : 'inactive' ?>">
                                        <?= isset($user['is_active']) ? ($user['is_active'] ? 'Active' : 'Inactive') : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit-btn" onclick="editUser(<?= $user['user_id'] ?>)">Edit</button>
                                        <button class="action-btn delete-btn" onclick="deleteUser(<?= $user['user_id'] ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Update the Edit Modal HTML -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="form-group">
                    <label for="editFirstName">First Name</label>
                    <input type="text" id="editFirstName" name="firstname" required>
                </div>
                <div class="form-group">
                    <label for="editLastName">Last Name</label>
                    <input type="text" id="editLastName" name="lastname" required>
                </div>
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add notification container -->
    <div id="notificationContainer"></div>

    <!-- Add the delete confirmation modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content delete-confirm-modal">
            <div class="delete-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24">
                    <path fill="#B07154" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                </svg>
            </div>
            <h2 class="delete-title">Delete User</h2>
            <p class="delete-message">Are you sure you want to delete this user? This action cannot be undone.</p>
            <div class="delete-actions">
                <button class="cancel-delete-btn" onclick="closeDeleteModal()">Cancel</button>
                <button class="confirm-delete-btn" onclick="confirmDelete()">Delete User</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update datetime display
            function updateDateTime() {
                const now = new Date();
                
                // Update time
                const timeDisplay = document.querySelector('.time-display');
                timeDisplay.textContent = now.toLocaleString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });

                // Update date
                const dateDisplay = document.querySelector('.date-display');
                dateDisplay.textContent = now.toLocaleDateString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric'
                });
            }

            // Update immediately and then every second
            updateDateTime();
            setInterval(updateDateTime, 1000);

            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const body = document.body;

            // Create overlay element
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            body.appendChild(overlay);

            function toggleMenu() {
                mobileMenuBtn.classList.toggle('active');
                sidebar.classList.toggle('active');
                content.classList.toggle('sidebar-active');
                overlay.classList.toggle('active');
                body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }

            mobileMenuBtn.addEventListener('click', toggleMenu);
            overlay.addEventListener('click', toggleMenu);

            // Close menu when clicking a nav item on mobile
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                        toggleMenu();
                    }
                });
            });

            // Handle resize events
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (window.innerWidth > 768) {
                        mobileMenuBtn.classList.remove('active');
                        sidebar.classList.remove('active');
                        content.classList.remove('sidebar-active');
                        overlay.classList.remove('active');
                        body.style.overflow = '';
                    }
                }, 250);
            });

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const userElements = document.querySelectorAll('.users-table tr:not(:first-child), .mobile-card');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                userElements.forEach(element => {
                    const isTableRow = element.tagName === 'TR';
                    const name = isTableRow 
                        ? element.querySelector('.user-name').textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info .user-name').textContent.toLowerCase();
                    const email = isTableRow
                        ? element.children[1].textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info div:nth-child(2)').textContent.toLowerCase();
                    const phone = isTableRow
                        ? element.children[2].textContent.toLowerCase()
                        : element.querySelector('.mobile-card-info div:nth-child(3)').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || 
                        email.includes(searchTerm) || 
                        phone.includes(searchTerm)) {
                        element.style.display = '';
                    } else {
                        element.style.display = 'none';
                    }
                });
            });

            // Remove success/error messages after 3 seconds
            setTimeout(function() {
                const messages = document.querySelectorAll('.success-message, .error-message');
                messages.forEach(function(message) {
                    message.style.display = 'none';
                });
            }, 3000);
        });

        let currentUserId = null;
        let userToDelete = null;

        function editUser(userId) {
            currentUserId = userId;
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
            currentUserId = null;
        }

        // Handle form submission
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'User updated successfully');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message || 'Error updating user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Error updating user');
            })
            .finally(() => {
                closeEditModal();
            });
        });

        function showNotification(type, message) {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-icon">${type === 'success' ? '✅' : '❌'}</div>
                <div class="notification-text">${message}</div>
                <div class="notification-close">×</div>
            `;
            
            container.appendChild(notification);
            
            notification.offsetHeight;
            notification.classList.add('show');
            
            notification.querySelector('.notification-close').addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => {
                    container.removeChild(notification);
                }, 400);
            });
            
            setTimeout(() => {
                if (container.contains(notification)) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (container.contains(notification)) {
                            container.removeChild(notification);
                        }
                    }, 400);
                }
            }, 3000);
        }

        function deleteUser(userId) {
            userToDelete = userId;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            userToDelete = null;
        }

        function confirmDelete() {
            if (userToDelete !== null) {
                window.location.href = `user-management.php?delete=${userToDelete}`;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editUserModal');
            if (event.target == modal) {
                closeEditModal();
            }
            if (event.target == document.getElementById('deleteConfirmModal')) {
                closeDeleteModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>
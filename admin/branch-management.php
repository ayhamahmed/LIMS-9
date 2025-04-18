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

// Initialize variables
$showAddForm = false;
$errors = [];
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchName = trim($_POST['branch_name'] ?? '');
    $branchLocation = trim($_POST['branch_location'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $branchId = isset($_POST['branch_id']) ? filter_var($_POST['branch_id'], FILTER_VALIDATE_INT) : null;

    if (empty($branchName) || empty($branchLocation)) {
        $errors[] = "Branch name and location are required.";
    } else {
        try {
            if ($branchId) {
                // Update existing branch
                $stmt = $pdo->prepare("UPDATE branches SET branch_name = ?, branch_location = ?, contact_number = ? WHERE branch_id = ?");
                $stmt->execute([$branchName, $branchLocation, $contactNumber, $branchId]);
                header("Location: branch-management.php?success=updated");
                exit();
            } else {
                // Add new branch
                $stmt = $pdo->prepare("INSERT INTO branches (branch_name, branch_location, contact_number) VALUES (?, ?, ?)");
                $stmt->execute([$branchName, $branchLocation, $contactNumber]);
                header("Location: branch-management.php?success=added");
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle branch deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM branches WHERE branch_id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: branch-management.php?success=deleted");
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error deleting branch: " . $e->getMessage();
    }
}

// Get search query if any
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch branches from database with search functionality
try {
    // First determine what columns exist in the branches table
    $columnCheck = $pdo->query("SHOW COLUMNS FROM branches");
    $columns = $columnCheck->fetchAll(PDO::FETCH_COLUMN);
    
    $hasContactNumber = in_array('contact_number', $columns);
    $hasIsActive = in_array('is_active', $columns);
    
    // Create base query based on available columns
    $baseQuery = 'SELECT branch_id, branch_name, branch_location';
    
    if ($hasContactNumber) {
        $baseQuery .= ', contact_number';
    } else {
        $baseQuery .= ", '' as contact_number";
    }
    
    if ($hasIsActive) {
        $baseQuery .= ', is_active';
    } else {
        $baseQuery .= ', 1 as is_active';
    }
    
    $baseQuery .= ' FROM branches';
    
    // Add search condition if search query is provided
    if (!empty($searchQuery)) {
        $stmt = $pdo->prepare($baseQuery . ' WHERE branch_name LIKE ? OR branch_location LIKE ? ORDER BY branch_id');
        $searchParam = "%$searchQuery%";
        $stmt->execute([$searchParam, $searchParam]);
    } else {
        $stmt = $pdo->query($baseQuery . ' ORDER BY branch_id');
    }
    
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add computed values for display purposes
    foreach ($branches as &$branch) {
        // Add placeholder values for book counts since we don't have that relationship yet
        $branch['total_books'] = 0; // Placeholder
        $branch['borrowed_books'] = 0; // Placeholder
        
        // Ensure is_active is set
        if (!isset($branch['is_active'])) {
            $branch['is_active'] = 1;
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
    $branches = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Branch Management - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="../assets/css/branch-management.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        *::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        body {
            width: 100%;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        body::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        /* Add any critical styles here as a fallback */
        .branch-management-container {
            padding: 20px;
            background: #FEF3E8;
            min-height: calc(100vh - 80px);
            margin-top: 0;
        }
        
        .content-header {
            margin-bottom: 20px;
            padding: 0;
        }

        .content-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(176, 113, 84, 0.1);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(176, 113, 84, 0.1);
        }
        
        /* Basic styles in case CSS file fails to load */
        .notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 8px;
            z-index: 1000;
        }

        /* Add these styles to make the modal work properly */
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

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .save-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Updated button styles */
        .add-branch-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .add-branch-btn:hover {
            background: #45a049;
        }

        .delete-btn {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .delete-btn:hover {
            background: #d32f2f;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        #modalTitle {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .form-buttons {
                flex-direction: column;
            }

            .form-buttons button {
                width: 100%;
            }
        }

        /* Updated header and button styles */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0;
            border-bottom: 2px solid rgba(176, 113, 84, 0.1);
        }

        .page-title {
            color: #B07154;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            padding-bottom: 10px;
        }

        .controls-container {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            padding-bottom: 10px;
        }

        .table-wrapper {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(176, 113, 84, 0.1);
        }

        /* Brown theme button styles */
        .add-branch-btn {
            background: #B07154;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .add-branch-btn:hover {
            background: #8B5B43;
        }

        /* Update modal buttons to match theme */
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .form-buttons .add-branch-btn {
            background: #B07154;
        }

        .form-buttons .add-branch-btn:hover {
            background: #8B5B43;
        }

        .form-buttons .delete-btn {
            background: #FFE5E5;
            color: #B07154;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .form-buttons .delete-btn:hover {
            background: #FFD1D1;
        }

        /* Update modal styles to match theme */
        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #modalTitle {
            color: #B07154;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }

        .form-group label {
            color: #B07154;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group input {
            border: 2px solid #F4DECB;
            padding: 10px;
            border-radius: 8px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #B07154;
        }

        .close-btn {
            color: #B07154;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 15px;
        }

        .close-btn:hover {
            color: #8B5B43;
        }

        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .controls-container {
                justify-content: stretch;
            }

            .add-branch-btn {
                width: 100%;
            }
        }

        /* Action buttons in the table */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: background-color 0.3s;
        }

        .edit-btn {
            background: #F4DECB;
            color: #B07154;
        }

        .edit-btn:hover {
            background: #E4C4A9;
        }

        .delete-btn {
            background: #B07154;
            color: white;
        }

        .delete-btn:hover {
            background: #8B5B43;
        }

        /* Modal buttons */
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .form-buttons .delete-btn {
            background: #B07154;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-buttons .delete-btn:hover {
            background: #8B5B43;
        }

        .form-buttons .add-branch-btn {
            background: #B07154;
            color: white;
        }

        .form-buttons .add-branch-btn:hover {
            background: #8B5B43;
        }

        .success-message {
            background-color: #F4DECB;
            color: #8B5B43;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 2px solid #B07154;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            position: relative;
            animation: slideDown 0.3s ease-out, fadeOut 0.5s ease 3s forwards;
        }

        .success-message::before {
            content: '✓';
            background: #B07154;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
                visibility: hidden;
            }
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
        }

        .admin-name-1 {
            font-size: 22px;
            color: #B07154;
            font-weight: 600;
            margin-bottom: 4px;
        }

        @media (max-width: 768px) {
            .datetime-display {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        /* Delete confirmation modal styles */
        .delete-confirm-modal {
            max-width: 400px !important;
            text-align: center;
            padding: 30px !important;
            background: #FEF3E8 !important;
            border: 2px solid #F4DECB;
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
        }

        .delete-message {
            color: #8B5B43;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 24px;
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
            <a href="../admin/user-management.php" class="nav-item">
                <div class="icon">
                    <img src="../images/people 3.png" alt="Users" width="24" height="24">
                </div>
                <div class="text">Users</div>
            </a>
            <a href="branch-management.php" class="nav-item active">
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
                        <path fill="#B07154" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.59-8 8-8 8 3.589 8 8-3.589 8-8 8zm0-18c-5.514 0-10 4.486-10 10s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.59-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
                        <path fill="#B07154" d="M13 7h-2v6l4.5 2.7.7-1.2-3.2-1.9z"/>
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
        <div class="branch-management-container">
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
                <h1 class="page-title">Branch Management</h1>
                <div class="controls-container">
                    <button class="add-branch-btn" onclick="openModal()">Add New Branch</button>
                </div>
            </div>

            <!-- Modal for Add/Edit Branch -->
            <div id="branchModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal()">&times;</span>
                    <h2 id="modalTitle">Add New Branch</h2>
                    <form id="branchForm" method="POST" action="add_branch.php">
                        <input type="hidden" id="branchId" name="branch_id">
                        <div class="form-group">
                            <label for="branchName">Branch Name</label>
                            <input type="text" id="branchName" name="branch_name" required>
                        </div>
                        <div class="form-group">
                            <label for="branchLocation">Location</label>
                            <input type="text" id="branchLocation" name="branch_location" required>
                        </div>
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" name="contact_number">
                        </div>
                        <div class="form-buttons">
                            <button type="button" class="delete-btn" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="add-branch-btn">Save Branch</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="branches-table">
                <table>
                    <thead>
                        <tr>
                            <th>Branch Name</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($branches)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">No branches found. Add your first branch!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td class="branch-name">
                                        <?= htmlspecialchars($branch['branch_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($branch['branch_location']) ?></td>
                                    <td><?= htmlspecialchars($branch['contact_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="status-badge <?= $branch['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                            <?= $branch['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn edit-btn" onclick="editBranch(<?= htmlspecialchars(json_encode($branch)) ?>)">Edit</button>
                                            <button class="action-btn delete-btn" onclick="deleteBranch(<?= $branch['branch_id'] ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <?php if (empty($branches)): ?>
                <div class="mobile-card">
                    <div style="text-align: center; padding: 20px;">No branches found. Add your first branch!</div>
                </div>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-info">
                                <div class="branch-name">
                                    <?= htmlspecialchars($branch['branch_name']) ?>
                                </div>
                                <div><?= htmlspecialchars($branch['branch_location']) ?></div>
                                <div><?= htmlspecialchars($branch['contact_number'] ?? 'N/A') ?></div>
                            </div>
                            <span class="status-badge <?= $branch['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $branch['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="mobile-card-actions">
                            <button class="action-btn edit-btn" onclick="editBranch(<?= htmlspecialchars(json_encode($branch)) ?>)">Edit</button>
                            <button class="action-btn delete-btn" onclick="deleteBranch(<?= $branch['branch_id'] ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 12px; position: relative;">
            <span onclick="closeEditModal()" style="position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer;">&times;</span>
            <h2 class="form-title">Edit Branch</h2>
            <form id="editBranchForm" method="POST" action="update_branch.php">
                <input type="hidden" id="editBranchId" name="branch_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="editBranchName">Branch Name</label>
                        <input type="text" id="editBranchName" name="branch_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editBranchLocation">Location</label>
                        <input type="text" id="editBranchLocation" name="branch_location" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editContactNumber">Contact Number</label>
                        <input type="text" id="editContactNumber" name="contact_number">
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="button" class="action-btn delete-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="action-btn edit-btn">Update Branch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add the delete confirmation modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content delete-confirm-modal">
            <div class="delete-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24">
                    <path fill="#B07154" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                </svg>
            </div>
            <h2 class="delete-title">Delete Branch</h2>
            <p class="delete-message">Are you sure you want to delete this branch? This action cannot be undone.</p>
            <div class="delete-actions">
                <button class="cancel-delete-btn" onclick="closeDeleteModal()">Cancel</button>
                <button class="confirm-delete-btn" onclick="confirmDelete()">Delete Branch</button>
            </div>
        </div>
    </div>

    <script src="./js/branch-management.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update datetime display
            function updateDateTime() {
                const now = new Date();
                
                // Update time
                const timeDisplay = document.querySelector('.time-display');
                timeDisplay.textContent = now.toLocaleString('en-US', {
                    hour: 'numeric',
                    minute: 'numeric',
                    second: 'numeric',
                    hour12: true
                });

                // Update date
                const dateDisplay = document.querySelector('.date-display');
                dateDisplay.textContent = now.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            }

            // Update immediately and then every second
            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Check if CSS file is loaded
            let cssLoaded = false;
            for(let i = 0; i < document.styleSheets.length; i++) {
                if(document.styleSheets[i].href && document.styleSheets[i].href.includes('branch-management.css')) {
                    cssLoaded = true;
                    break;
                }
            }
            
            if(!cssLoaded) {
                console.error('Branch management CSS file failed to load');
            }

            // Test JS functionality
            if(typeof openModal === 'undefined') {
                console.error('Branch management JS file failed to load');
            }
        });

        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add New Branch';
            document.getElementById('branchForm').reset();
            document.getElementById('branchId').value = '';
            document.getElementById('branchForm').action = 'add_branch.php';
            document.getElementById('branchModal').style.display = 'block';
        }

        function editBranch(branch) {
            document.getElementById('modalTitle').textContent = 'Edit Branch';
            document.getElementById('branchId').value = branch.branch_id;
            document.getElementById('branchName').value = branch.branch_name;
            document.getElementById('branchLocation').value = branch.branch_location;
            document.getElementById('contactNumber').value = branch.contact_number || '';
            document.getElementById('branchForm').action = 'update_branch.php';
            document.getElementById('branchModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('branchModal').style.display = 'none';
        }

        let branchToDelete = null;

        function deleteBranch(branchId) {
            branchToDelete = branchId;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            branchToDelete = null;
        }

        function confirmDelete() {
            if (branchToDelete !== null) {
                window.location.href = `branch-management.php?delete=${branchToDelete}`;
            }
        }

        // Update window click handler to include delete modal
        window.onclick = function(event) {
            if (event.target == document.getElementById('branchModal')) {
                closeModal();
            }
            if (event.target == document.getElementById('deleteConfirmModal')) {
                closeDeleteModal();
            }
        }

        // Update escape key handler to include delete modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeDeleteModal();
            }
        });
    </script>
</body>

</html>
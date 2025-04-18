<?php
// Start the session
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session information
error_log("Session data: " . print_r($_SESSION, true));

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("Admin not logged in. Redirecting to login page.");
    header('Location: admin-login.php');
    exit();
}

// Include the database connection
try {
    $pdo = require '../database/db_connection.php';
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new admin
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO admin (username, password, FirstName, LastName, email, Status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['username'],
                        $_POST['password'],
                        $_POST['firstname'],
                        $_POST['lastname'],
                        $_POST['email'],
                        $_POST['status']
                    ]);
                    $success_message = "Admin added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding admin: " . $e->getMessage();
                }
                break;

            case 'edit':
                // Edit existing admin
                try {
                    $stmt = $pdo->prepare("
                        UPDATE admin 
                        SET username = ?, 
                            password = ?,
                            FirstName = ?,
                            LastName = ?,
                            email = ?,
                            Status = ?
                        WHERE admin_id = ?
                    ");
                    $stmt->execute([
                        $_POST['username'],
                        $_POST['password'],
                        $_POST['firstname'],
                        $_POST['lastname'],
                        $_POST['email'],
                        $_POST['status'],
                        $_POST['admin_id']
                    ]);
                    $success_message = "Admin updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating admin: " . $e->getMessage();
                }
                break;

            case 'delete':
                // Delete admin
                try {
                    $stmt = $pdo->prepare("DELETE FROM admin WHERE admin_id = ?");
                    $stmt->execute([$_POST['admin_id']]);
                    $success_message = "Admin deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting admin: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all admins
$admins = $pdo->query("SELECT * FROM admin ORDER BY username")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Book King</title>
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
            background: #F8F8F8;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #B07154;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        .success {
            background: #DFF2BF;
            color: #4F8A10;
        }

        .error {
            background: #FFE8E6;
            color: #D8000C;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #B07154;
            color: white;
        }

        .btn-primary:hover {
            background: #95604A;
        }

        .btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #F8B4B4;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: #FBD5D5;
            color: #9B1C1C;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(155, 28, 28, 0.1);
        }

        .btn-danger:active {
            transform: translateY(0);
            box-shadow: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #F8F8F8;
            font-weight: 600;
        }

        tr:hover {
            background: #F8F8F8;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #B07154;
        }

        .header-actions {
            margin-bottom: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #F4DECB;
            color: #B07154;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #E4C4A9;
            transform: translateX(-4px);
        }

        .back-btn svg {
            transition: transform 0.3s ease;
        }

        .back-btn:hover svg {
            transform: translateX(-4px);
        }

        /* Delete Confirmation Modal */
        #deleteConfirmModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .delete-modal-content {
            background: white;
            width: 90%;
            max-width: 400px;
            margin: 50px auto;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        .delete-warning-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            color: #9B1C1C;
        }

        .delete-modal-title {
            color: #9B1C1C;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .delete-modal-message {
            color: #666;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .delete-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-cancel {
            background: #F3F4F6;
            color: #374151;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #E5E7EB;
        }

        .btn-confirm-delete {
            background: #DC2626;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-confirm-delete:hover {
            background: #B91C1C;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            .delete-modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .delete-modal-actions {
                flex-direction: column;
            }

            .btn-cancel, .btn-confirm-delete {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <a href="../admin/admin-dashboard.php" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
        <h1>Manage Administrators</h1>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <button class="btn btn-primary" onclick="showAddModal()">Add New Admin</button>

        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td><?php echo htmlspecialchars($admin['Status']); ?></td>
                        <td>
                            <button class="btn btn-primary" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($admin)); ?>)">Edit</button>
                            <button class="btn btn-danger" onclick="deleteAdmin(<?php echo $admin['admin_id']; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Admin Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Add New Admin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" id="firstname" name="firstname" required>
                </div>
                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" id="lastname" name="lastname" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="deactive">Deactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Admin</button>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Admin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                <div class="form-group">
                    <label for="edit_firstname">First Name</label>
                    <input type="text" id="edit_firstname" name="firstname" required>
                </div>
                <div class="form-group">
                    <label for="edit_lastname">Last Name</label>
                    <input type="text" id="edit_lastname" name="lastname" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="active">Active</option>
                        <option value="deactive">Deactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Admin</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="delete-modal-content">
            <svg class="delete-warning-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="delete-modal-title">Delete Administrator</h3>
            <p class="delete-modal-message">Are you sure you want to delete this administrator? This action cannot be undone.</p>
            <div class="delete-modal-actions">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn-confirm-delete" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(admin) {
            document.getElementById('edit_admin_id').value = admin.admin_id;
            document.getElementById('edit_username').value = admin.username;
            document.getElementById('edit_firstname').value = admin.FirstName;
            document.getElementById('edit_lastname').value = admin.LastName;
            document.getElementById('edit_email').value = admin.email;
            document.getElementById('edit_status').value = admin.Status;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        let adminToDelete = null;

        function deleteAdmin(adminId) {
            adminToDelete = adminId;
            document.getElementById('deleteConfirmModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            adminToDelete = null;
        }

        function confirmDelete() {
            if (!adminToDelete) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="admin_id" value="${adminToDelete}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.id === 'deleteConfirmModal') {
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
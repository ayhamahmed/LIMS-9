<?php
session_start();
require_once('../includes/config.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's borrowed books
$stmt = $conn->prepare("
    SELECT b.id as borrow_id, b.borrow_date, b.due_date, bk.title, bk.isbn, bk.cover_image
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ? AND b.return_date IS NULL
    ORDER BY b.due_date ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Books</title>
    <link rel="stylesheet" href="../assets/css/user-return-books.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <div class="user-icon">
                    <?php
                    $profilePicture = isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] !== 'default.jpg' 
                        ? 'uploads/profile_pictures/' . $_SESSION['profile_picture'] 
                        : 'images/user.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="User" class="profile-picture">
                </div>
                <div>
                    <div class="user-name"><?php echo isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'User'; ?></div>
                    <div class="user-role">User</div>
                </div>
            </div>
            <div class="time">
                <div id="current-time" class="current-time">7:41 AM</div>
                <div id="current-date" class="current-date">Apr 18, 2025</div>
            </div>
        </div>

        <div class="page-header">
            <h1>Return Books</h1>
        </div>
        <div id="successMessage" class="success-message"></div>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="books-grid">
                <?php while ($book = $result->fetch_assoc()): ?>
                    <div class="book-card" id="book-<?php echo $book['borrow_id']; ?>">
                        <img src="<?php echo htmlspecialchars($book['cover_image'] ?? '../assets/images/default-book.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($book['title']); ?>" 
                             class="book-cover">
                        <div class="book-info">
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div>ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                            <div>Borrowed: <?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></div>
                            <div class="due-date">Due: <?php echo date('M d, Y', strtotime($book['due_date'])); ?></div>
                        </div>
                        <button class="return-btn" onclick="returnBook(<?php echo $book['borrow_id']; ?>)">
                            Return Book
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-books">
                <p>You have no books to return.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function returnBook(borrowId) {
            if (confirm('Are you sure you want to return this book?')) {
                fetch('return_book_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'borrow_id=' + borrowId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const bookCard = document.getElementById('book-' + borrowId);
                        bookCard.style.display = 'none';
                        
                        const successMessage = document.getElementById('successMessage');
                        successMessage.textContent = data.message;
                        successMessage.style.display = 'block';
                        
                        setTimeout(() => {
                            successMessage.style.display = 'none';
                        }, 3000);
                        
                        const remainingBooks = document.querySelectorAll('.book-card[style="display: block"]').length;
                        if (remainingBooks === 0) {
                            const booksGrid = document.querySelector('.books-grid');
                            booksGrid.innerHTML = '<div class="no-books"><p>You have no books to return.</p></div>';
                        }
                    } else {
                        alert(data.message || 'Error returning book');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error returning book');
                });
            }
        }
    </script>
</body>
</html> 
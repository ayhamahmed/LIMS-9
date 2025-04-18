<?php
session_start();

// Include the database connection
$pdo = require 'database/db_connection.php';

// Function to get random cover URL
function getRandomCover($title, $author) {
    // List of background colors (pastel colors)
    $colors = [
        'F8B195', 'F67280', 'C06C84', '6C5B7B', '355C7D', // warm to cool
        'A8E6CF', 'DCEDC1', 'FFD3B6', 'FFAAA5', 'FF8B94', // nature
        'B5EAD7', 'C7CEEA', 'E2F0CB', 'FFDAC1', 'FFB7B2', // soft
        'E7D3EA', 'DCD3FF', 'B5D8EB', 'BBE1FA', 'D6E5FA'  // pastel
    ];
    
    // Get random color
    $bgColor = $colors[array_rand($colors)];
    
    // Format title and author for URL
    $text = urlencode($title . "\nby " . $author);
    
    return "https://placehold.co/400x600/${bgColor}/333333/png?text=${text}";
}

// Fetch books data from the database
try {
    $stmt = $pdo->prepare("
        SELECT b.*,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM borrowed_books bb 
                    WHERE bb.book_id = b.book_id 
                    AND bb.return_date IS NULL
                ) THEN 'Unavailable'
                ELSE 'Available'
            END as availability
        FROM books b
        ORDER BY b.book_id
    ");
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update books with cover images if they don't have one
    foreach ($books as &$book) {
        if (empty($book['cover_image_url'])) {
            $book['cover_image_url'] = getRandomCover($book['title'], $book['author']);
        }
    }
} catch (PDOException $e) {
    die('Error fetching books: ' . $e->getMessage());
}

// Get user's full name from session
$userFullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Borrow Books</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/user-borrow-books.css?v=<?php echo time(); ?>">
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
            background: #FEF3E8;
            position: relative;
            overflow-x: hidden;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        body::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <img src="images/logo.png" alt="Book King Logo">
        </div>
        <div class="sidebar-item home" onclick="window.location.href='user-dashboard.php'">
            <img src="images/element-2 2.svg" alt="Home" class="icon-image">
        </div>
        <div class="sidebar-item list" onclick="window.location.href='user-return-books.php'">
            <img src="images/Vector.svg" alt="List" class="icon-image">
        </div>
        <div class="sidebar-item book" onclick="window.location.href='user-borrow-books.php'">
            <img src="images/book.png" alt="Book" class="icon-image">
        </div>
        <div class="sidebar-item logout" onclick="handleLogout()">
            <img src="images/logout 3.png" alt="Logout" class="icon-image">
        </div>
    </div>

    <div class="main-content">
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
                    <div class="user-name"><?php echo htmlspecialchars($userFullName); ?></div>
                    <div class="user-role">User</div>
                </div>
            </div>
            <div class="time">
                <div id="current-time" class="current-time"></div>
                <div id="current-date" class="current-date"></div>
            </div>
        </div>

        <!-- Replace the existing table structure with this -->
        <div class="book-management-container">
            <div class="controls-header">
                <h1 class="page-title">Library Lane Books</h1>
                <div class="search-box">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search books...">
                </div>
            </div>
        
            <div class="books-table">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Type</th>
                            <th>Language</th>
                            <th>Status</th>
                            <th>Add to Cart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($book['cover_image_url'] ?? 'images/default-book-cover.jpg') ?>" 
                                         alt="<?= htmlspecialchars($book['title']) ?>" 
                                         class="book-cover">
                                </td>
                                <td class="book-title"><?= htmlspecialchars($book['title']) ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['type']) ?></td>
                                <td><?= htmlspecialchars($book['language']) ?></td>
                                <td>
                                    <span class="status-badge <?= $book['availability'] === 'Available' ? 'status-available' : 'status-borrowed' ?>">
                                        <?= htmlspecialchars($book['availability']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="checkbox-wrapper">
                                        <input type="checkbox" class="book-checkbox"
                                            <?= $book['availability'] === 'Unavailable' ? 'disabled' : '' ?>
                                            data-book-id="<?= htmlspecialchars($book['book_id']) ?>"
                                            data-book-title="<?= htmlspecialchars($book['title']) ?>"
                                            data-book-type="<?= htmlspecialchars($book['type']) ?>"
                                            data-book-language="<?= htmlspecialchars($book['language']) ?>">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button class="acquire-button" onclick="processSelectedBooks()">
            <div class="acquire-icon"></div>
            <span>Acquire</span>
        </button>
    </div>

    <div class="book-king-sidebar">
        <div>B</div>
        <div>O</div>
        <div>O</div>
        <div>K</div>
        <div>&nbsp;</div>
        <div>K</div>
        <div>I</div>
        <div>N</div>
        <div>G</div>
    </div>

    <!-- Profile Picture Upload Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProfileModal()">&times;</span>
            <h2>Update Profile Picture</h2>
            <div class="profile-picture-container">
                <?php
                $modalProfilePicture = isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] !== 'default.jpg' 
                    ? 'uploads/profile_pictures/' . $_SESSION['profile_picture'] 
                    : 'images/user.png';
                ?>
                <img src="<?php echo htmlspecialchars($modalProfilePicture); ?>" alt="Profile Picture" class="profile-picture">
                <div class="profile-picture-actions">
                    <label for="profile-picture-input" class="btn btn-primary">Change Picture</label>
                    <input type="file" id="profile-picture-input" accept="image/*">
                    <button class="btn btn-danger" onclick="removeProfilePicture()">Remove Picture</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add real-time clock functionality
        function updateTime() {
            const now = new Date();

            // Update time
            const timeString = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;

            // Update date
            const dateString = now.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            });
            document.getElementById('current-date').textContent = dateString;
        }

        // Update time every second
        setInterval(updateTime, 1000);

        // Initial call to display time immediately
        document.addEventListener('DOMContentLoaded', updateTime);

        // Add this after your existing script
        function processSelectedBooks() {
            const selectedBooks = [];
            document.querySelectorAll('.book-checkbox:checked').forEach(checkbox => {
                selectedBooks.push({
                    id: checkbox.dataset.bookId,
                    title: checkbox.dataset.bookTitle,
                    type: checkbox.dataset.bookType,
                    language: checkbox.dataset.bookLanguage
                });
            });

            if (selectedBooks.length === 0) {
                alert('Please select at least one book');
                return;
            }

            // Store selected books in session storage
            sessionStorage.setItem('selectedBooks', JSON.stringify(selectedBooks));
            window.location.href = 'user-borrow-confirm.php';
        }

        // Replace the existing searchBooks function with this one
        function searchBooks() {
            const searchInput = document.querySelector('.search-input');
            const searchTerm = searchInput.value.toLowerCase().trim();
            const tableRows = document.querySelectorAll('.books-table tbody tr');

            tableRows.forEach(row => {
                const title = row.querySelector('.book-title').textContent.toLowerCase();
                const author = row.cells[2].textContent.toLowerCase();
                const type = row.cells[3].textContent.toLowerCase();
                const language = row.cells[4].textContent.toLowerCase();

                const matches = title.includes(searchTerm) ||
                    author.includes(searchTerm) ||
                    type.includes(searchTerm) ||
                    language.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
            });
        }

        // Update the event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            
            // Real-time search as user types
            searchInput.addEventListener('input', searchBooks);
        });

        // a function to handle the logout button
        function handleLogout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Add these event listeners at the bottom of your script section
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');

            // Add input event listener for real-time search
            searchInput.addEventListener('input', searchBooks);

            // Add keyup event for immediate search on backspace
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    searchBooks();
                }
            });
        });

        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }

        function removeProfilePicture() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                fetch('update_user_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'remove_picture=1'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    // Refresh the page to update all profile picture elements
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing the profile picture');
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('profileModal');
            if (event.target == modal) {
                closeProfileModal();
            }
        }
    </script>
</body>

</html>
<?php
// database/db_connection.php

try {
    // First, connect without specifying the database
    $pdo = new PDO(
        "mysql:host=localhost",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS lims");
    
    // Connect to the specific database
    $pdo = new PDO(
        "mysql:host=localhost;dbname=lims;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Drop and recreate admin table to ensure correct structure
    $pdo->exec("DROP TABLE IF EXISTS admin");
    $pdo->exec("CREATE TABLE admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        FirstName VARCHAR(50) NOT NULL,
        LastName VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        Status ENUM('active', 'deactive') NOT NULL DEFAULT 'active',
        last_login DATETIME NULL,
        login_location VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default admin accounts
    $pdo->exec("INSERT INTO admin (username, password, FirstName, LastName, email, Status) VALUES 
        ('allain', '123', 'Allain', 'User', 'allain@example.com', 'active'),
        ('ayham', '123', 'Ayham', 'User', 'ayham@example.com', 'active')
    ");
    
    return $pdo;
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    throw new Exception('Database connection failed. Please check your database configuration.');
}

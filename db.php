<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "scheduler";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Database connection failed: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

// Create Users Table (Updated Role Enum)
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Create Admin Messages
$conn->query("CREATE TABLE IF NOT EXISTS admin_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_email VARCHAR(150) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('Unread', 'Read') NOT NULL DEFAULT 'Unread',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
)");

// Create Events
$conn->query("CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) NULL,
    description TEXT,
    room VARCHAR(100) DEFAULT NULL,
    assigned_staff_id INT UNSIGNED NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL
)");

// Event Gallery
$conn->query("CREATE TABLE IF NOT EXISTS event_gallery (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    CONSTRAINT fk_event_gallery FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
)");

// Visitor Codes
$conn->query("CREATE TABLE IF NOT EXISTS visitor_invitation_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL DEFAULT 'Visitor Access',
    is_active TINYINT(1) NOT NULL DEFAULT 1
)");
?>
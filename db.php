<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "scheduler";

// this connects to the database, then throws an error if it fails.
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

//this is the part that creates the meeting_requests table, so long as it doesn't exist. It contains every variable that a meeting would need.
$conn->query("
    CREATE TABLE IF NOT EXISTS meeting_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        requester_id INT UNSIGNED NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
        title VARCHAR(150) NOT NULL,
        pic VARCHAR(100) NOT NULL,
        attendees INT UNSIGNED NOT NULL,
        room VARCHAR(50) NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        reviewed_by INT UNSIGNED NULL,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_requests_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Stores messages sent from users to admins through the user dashboard.
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id INT UNSIGNED NULL,
        sender_name VARCHAR(100) NOT NULL,
        sender_email VARCHAR(150) NOT NULL,
        subject VARCHAR(150) NOT NULL,
        content TEXT NOT NULL,
        status ENUM('Unread', 'Read') NOT NULL DEFAULT 'Unread',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin_messages_status_created (status, created_at),
        CONSTRAINT fk_admin_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
    )
");


// Stores primary event details (Posters, titles, dates)
$conn->query("
    CREATE TABLE IF NOT EXISTS events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        image_path VARCHAR(255) NULL,
        description TEXT,
        room VARCHAR(100) DEFAULT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

// Stores multiple recap photos tied to a specific event
$conn->query("
    CREATE TABLE IF NOT EXISTS event_gallery (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id INT UNSIGNED NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_event_gallery FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    )
");
?>

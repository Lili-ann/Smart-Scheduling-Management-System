<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "scheduler";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

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
?>

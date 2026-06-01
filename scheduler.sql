DROP DATABASE IF EXISTS `scheduler`;
CREATE DATABASE `scheduler`;
USE `scheduler`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ========================================================
-- 1. USERS TABLE (Roles: Admin, Staff)
-- ========================================================
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Staff') NOT NULL DEFAULT 'Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserting current users
INSERT INTO `users` (`id`, `fullname`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Admin Account', 'admin@gmail.com', '$2y$10$tf9x7eSJ.cHEMpEhFhZAGuzT7uoI9jTog85AWDpK7rtExAyK7GLsC', 'Admin', '2026-06-01 20:31:53', '2026-06-01 20:31:53'),
(3, 'Lilian (Staff)', 'lilian@gmail.com', '$2y$10$ycRZJYLrdfks1vea4531IOtDR.CQac8fOdhrO4bM/s.r39PlMLT9a', 'Staff', '2026-06-01 20:31:53', '2026-06-01 20:31:53'),
(4, 'Staff', 'staff@gmail.com', '$2y$10$yD0bxStzed3liqVqbtz41eCy5PRPNjLmuYt15sQoT6QNs2wpKAUii', 'Staff', '2026-06-01 20:33:11', '2026-06-01 20:36:28');

-- ========================================================
-- 2. VISITOR CODES TABLE
-- ========================================================
CREATE TABLE `visitor_invitation_codes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL UNIQUE,
  `label` varchar(100) NOT NULL DEFAULT 'Visitor Access',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert the default visitor code
INSERT INTO `visitor_invitation_codes` (`id`, `code`, `label`) VALUES
(1, 'VISITOR2026', 'Default Visitor Code');

-- ========================================================
-- 3. EVENTS TABLE
-- ========================================================
CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text,
  `room` varchar(100) DEFAULT NULL,
  `assigned_staff_id` int(10) UNSIGNED DEFAULT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_events_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserting 2 Dummy Events (Assigned to Staff 4 and 3)
INSERT INTO `events` (`id`, `title`, `image_path`, `description`, `room`, `assigned_staff_id`, `date`, `start_time`, `end_time`) VALUES
(101, 'AI & Future Tech Summit', 'https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?w=800&q=80', 'Join us for an immersive day exploring the frontiers of Artificial Intelligence, Machine Learning, and how they will shape our tomorrow. Featuring guest speakers from top tech companies.', 'Grand Hall A', 4, '2026-06-15', '09:00:00', '16:00:00'),
(102, 'Annual Photography Gala', 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800&q=80', 'A showcase of the best student and staff photography from this academic year. Awards will be presented at the end of the evening. Light refreshments will be served.', 'Art Studio 3', 3, '2026-06-20', '18:00:00', '21:00:00');

-- ========================================================
-- 4. EVENT GALLERY TABLE
-- ========================================================
CREATE TABLE `event_gallery` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_event_gallery` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dummy Recap Images for the Events
INSERT INTO `event_gallery` (`event_id`, `image_path`) VALUES
(101, 'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=400&q=80'),
(101, 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?w=400&q=80'),
(102, 'https://images.unsplash.com/photo-1552168324-d612d77725e3?w=400&q=80');

-- ========================================================
-- 5. ADMIN MESSAGES TABLE (For Visitor Help Requests)
-- ========================================================
CREATE TABLE `admin_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_email` varchar(150) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_admin_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert a test message from a visitor
INSERT INTO `admin_messages` (`sender_name`, `sender_email`, `subject`, `content`) VALUES
('John Doe', 'john@example.com', 'Visitor message regarding: AI & Future Tech Summit', 'Hi, I need help finding the location of this event. Is parking available?');

-- Insert for generating code
CREATE TABLE IF NOT EXISTS `visitor_codes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_visitor_codes_events FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


COMMIT;

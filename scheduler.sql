-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 12:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scheduler`
--

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int(10) UNSIGNED NOT NULL,
  `status` enum('Upcoming','Ended','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `title` varchar(150) NOT NULL,
  `pic` varchar(100) NOT NULL,
  `attendees` int(10) UNSIGNED NOT NULL,
  `room` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `status`, `title`, `pic`, `attendees`, `room`, `date`, `start_time`, `end_time`, `created_by`, `created_at`, `updated_at`) VALUES
(33, 'Ended', 'IT Lecturers Meeting', 'Mr Junaid', 14, '509', '2026-05-20', '10:30:00', '12:30:00', NULL, '2026-05-13 08:26:07', '2026-05-13 08:31:07'),
(34, 'Ended', 'Media Club meeting', 'Fredy', 10, '507', '2026-05-14', '15:36:00', '17:30:00', 2, '2026-05-13 08:36:23', '2026-05-13 10:13:45'),
(37, 'Ended', 'IT students meeting', 'lilian', 22, '606', '2026-05-22', '07:00:00', '08:30:00', 3, '2026-05-13 10:12:49', '2026-05-13 10:17:27'),
(38, 'Upcoming', 'IT staff Meeting', 'lilian', 11, '508', '2026-05-18', '05:10:00', '07:10:00', 3, '2026-05-13 10:18:30', '2026-05-13 10:18:30');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_attendance`
--

CREATE TABLE `meeting_attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `meeting_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `attendance_status` enum('Attended','Not Attended') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_attendance`
--

INSERT INTO `meeting_attendance` (`id`, `meeting_id`, `user_id`, `attendance_status`, `created_at`) VALUES
(1, 34, 2, 'Attended', '2026-05-13 08:44:10'),
(3, 33, 2, 'Attended', '2026-05-13 08:44:15'),
(5, 34, 5, 'Attended', '2026-05-13 10:16:37'),
(6, 33, 5, 'Attended', '2026-05-13 10:16:39'),
(7, 37, 5, 'Attended', '2026-05-13 10:16:41');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_requests`
--

CREATE TABLE `meeting_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `requester_id` int(10) UNSIGNED NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `title` varchar(150) NOT NULL,
  `pic` varchar(100) NOT NULL,
  `attendees` int(10) UNSIGNED NOT NULL,
  `room` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_requests`
--

INSERT INTO `meeting_requests` (`id`, `requester_id`, `status`, `title`, `pic`, `attendees`, `room`, `date`, `start_time`, `end_time`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'Approved', 'test', 'Rafdah', 11, '506', '2026-05-13', '16:22:00', '16:30:00', 2, '2026-05-13 09:22:22', '2026-05-13 09:22:14', '2026-05-13 09:22:22'),
(2, 3, 'Approved', 'IT students meeting', 'lilian', 22, '606', '2026-05-22', '07:00:00', '08:30:00', 4, '2026-05-13 10:12:49', '2026-05-13 10:09:35', '2026-05-13 10:12:49'),
(3, 3, 'Approved', 'IT staff Meeting', 'lilian', 11, '508', '2026-05-18', '05:10:00', '07:10:00', 4, '2026-05-13 10:18:30', '2026-05-13 10:10:18', '2026-05-13 10:18:30');

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_email` varchar(150) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','User') NOT NULL DEFAULT 'User',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES
(2, 'Rafdah', 'abcd@gmail.com', '$2y$10$ywrjOGKDVuVAqL5Wkpr1gOeF5VcbG3ktP2ey2amwwp3H2Z7eYRs2C', 'User', '2026-05-12 17:09:45', '2026-05-12 17:09:45'),
(3, 'Lilian', 'lilian@gmail.com', '$2y$10$ycRZJYLrdfks1vea4531IOtDR.CQac8fOdhrO4bM/s.r39PlMLT9a', 'User', '2026-05-13 10:07:20', '2026-05-13 10:12:32'),
(4, 'Admin', 'admin@gmail.com', '$2y$10$tf9x7eSJ.cHEMpEhFhZAGuzT7uoI9jTog85AWDpK7rtExAyK7GLsC', 'Admin', '2026-05-13 10:11:44', '2026-05-13 10:11:44'),
(5, 'User', 'user@gmail.com', '$2y$10$KCdHuDZIleWzz.nAlxgOk.XSlmEIEMD09LMQOu.5ZWwIXfaPh8hHu', 'User', '2026-05-13 10:16:18', '2026-05-13 10:16:18'),
(6, 'JJ', 'jj@gmail.com', '$2y$10$ZkItWwW2Z6X3K5pG/WG65u6/kSNx9DHTXs8ezTDPdpuZqSSZ3/sa2', 'User', '2026-05-13 10:18:20', '2026-05-13 10:18:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_meetings_created_by` (`created_by`);

--
-- Indexes for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_meeting` (`user_id`,`meeting_id`),
  ADD KEY `fk_attendance_meeting` (`meeting_id`);

--
-- Indexes for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_requests_requester` (`requester_id`),
  ADD KEY `fk_requests_reviewer` (`reviewed_by`);

--
-- Indexes for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_messages_status_created` (`status`,`created_at`),
  ADD KEY `fk_admin_messages_sender` (`sender_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `meetings`
--
ALTER TABLE `meetings`
  ADD CONSTRAINT `fk_meetings_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  ADD CONSTRAINT `fk_attendance_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD CONSTRAINT `fk_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `fk_admin_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2025 at 08:07 AM
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
-- Database: `onlinecarbooking`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('admin','driver','client') NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `role`, `name`, `email`, `password_hash`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Admin', 'admin@gmail.com', '$2y$10$fAIUbxhK/sEWluSFpNbTUeMeQYjKoToz9anTnD4YK7dOP9u7acJWO', NULL, 1, '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(2, 'driver', 'Shane Lopez', 'shaaane@mail.com', '$2y$10$MWD3iKYEN5eQOz6HKPkGV.jSQ.s4nIBgu39NRqVFKW4z.ppsMem7G', NULL, 1, '2025-09-12 20:14:29', '2025-09-13 19:58:42'),
(4, 'driver', 'Test Driver', 'test@mail.com', '$2y$10$/jDtqLb3fFppBAl/An.EjOoh3g3JQvtAGYchWeWDMuY8ZGrwRbN8S', '09668226441', 1, '2025-09-22 05:09:31', '2025-09-22 05:09:31'),
(5, 'driver', 'Alex Turner', '505@mail.com', '$2y$10$huUzbRwfW3XpSWZ9.WoO6uUwHKeN448sfYTEzri7NxuHNT2dZZjey', '09942317653', 1, '2025-09-26 20:53:29', '2025-09-27 09:19:13'),
(6, 'driver', 'Noah Enguerra', 'noah@mail.com', '$2y$10$7bNJgxVl/rUpyANp58zFlO8J3iG.NxLb5qsr9L7Iz95nAFj28V2zq', '09123456789', 1, '2025-09-28 15:16:43', '2025-09-28 15:16:43'),
(7, 'driver', 'Keihle Pascual', 'kei@mail.com', '$2y$10$3Spppd0TQP/ZpbtcpY06X.8t.P.4b5/rOZwzpGSAzR0597.U9kyzS', '09784563214', 1, '2025-09-29 04:27:49', '2025-09-29 04:27:49'),
(8, 'driver', 'Rey Cabral', 'r.cabral@gmail.com', '$2y$10$ypv7rgC9tI2is0px2KaEs.EKaqJTIYExvirC535OzhAYIKykHmFii', '09877651234', 1, '2025-09-30 00:07:41', '2025-09-30 00:07:41'),
(9, 'driver', 'Arnold Lagman', 'a.lagman@gmail.com', '$2y$10$u0hXfoN/f1p6LIMwofkUke3O.MfqOUQrcfNqhjwpNbGPoiFEYrDo2', '09871234563', 1, '2025-09-30 00:08:30', '2025-09-30 00:08:30'),
(10, 'driver', 'Nestor Sanchez', 'n.sanchez@gmail.com', '$2y$10$L7BNQJFnvbyrZLjVP6PUzOjdemDyWJZilhYG4GLm90ptw190ye6/u', '0912345641', 1, '2025-09-30 00:09:46', '2025-09-30 00:09:46'),
(11, 'driver', 'Richie Sibal', 'r.sibal@gmail.com', '$2y$10$tByemB9al7eCaqMdh9F79Oq5XMdaunDQpJ31SlkiU6uFfJZ/yzYsS', '0768126543', 1, '2025-09-30 00:10:51', '2025-09-30 00:10:51'),
(12, 'driver', 'Manuel Valencia', 'm.valencia@gmail.com', '$2y$10$cCUgKiKyfeGYiYkZMYQSwe.wfSggzjPfAsYbTbqiSPySVuWQB3xfK', '09236571234', 1, '2025-09-30 00:11:35', '2025-09-30 00:11:35');

-- --------------------------------------------------------

--
-- Table structure for table `auth_logins`
--

CREATE TABLE `auth_logins` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `ip_addr` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_type` enum('admin','personal') NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `pax` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `contact_name` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(32) DEFAULT NULL,
  `pickup_point` varchar(255) NOT NULL,
  `dropoff_point` varchar(255) NOT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `dropoff_lat` decimal(10,7) DEFAULT NULL,
  `dropoff_lng` decimal(10,7) DEFAULT NULL,
  `scheduled_start_at` datetime NOT NULL,
  `scheduled_end_at` datetime DEFAULT NULL,
  `status` enum('pending','awaiting_driver','accepted','rejected','cancelled','in_progress','completed') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','partial') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_type`, `created_by`, `client_id`, `driver_id`, `vehicle_id`, `pax`, `contact_name`, `contact_phone`, `pickup_point`, `dropoff_point`, `pickup_lat`, `pickup_lng`, `dropoff_lat`, `dropoff_lng`, `scheduled_start_at`, `scheduled_end_at`, `status`, `payment_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'admin', 1, NULL, NULL, 1, 3, NULL, '+639171234567', '100 Main St, Town', 'Airport Terminal 1', NULL, NULL, NULL, NULL, '2025-08-12 14:00:00', NULL, 'cancelled', 'unpaid', NULL, '2025-09-12 20:14:29', '2025-09-22 04:38:16'),
(2, 'admin', 1, NULL, 2, 1, 1, 'Felicity Morelli', '09988233611', 'Angeles University Foundation', 'SM City Clark', NULL, NULL, NULL, NULL, '2025-09-25 13:44:00', NULL, 'completed', 'unpaid', '', '2025-09-25 12:44:46', '2025-09-29 04:19:13'),
(3, 'admin', 1, NULL, 2, 1, 1, 'Keihle Dianne', '09111111111', 'SM City Clark, Angeles, Central Luzon, Philippines', 'Angeles University Foundation Medical Center, MacArthur Highway, Ninoy Aquino, Central Luzon, Philippines', NULL, NULL, NULL, NULL, '2025-09-30 07:14:00', NULL, 'completed', 'unpaid', 'chello', '2025-09-29 04:18:15', '2025-09-29 23:34:23'),
(4, 'admin', 1, NULL, 7, 2, 1, 'Samantha Ticsay', '09988233611', 'Cuatro de Julio Street, Salapungan, Ninoy Aquino, Pandan, Angeles, Central Luzon, 2009, Philippines', 'SM City Clark, Angeles, Central Luzon, Philippines', NULL, NULL, NULL, NULL, '2025-09-30 13:30:00', NULL, 'completed', 'unpaid', '', '2025-09-29 23:33:47', '2025-09-29 23:35:24'),
(5, 'admin', 1, NULL, 8, 8, 1, 'Jovita Tipon', '09998776543', 'Nouveau Residences, Cutud, Central Luzon, Philippines', 'SM City Baguio, Luneta Hill Drive, District 10, Cordillera Administrative Region, Philippines', NULL, NULL, NULL, NULL, '2025-10-03 03:33:00', NULL, 'awaiting_driver', 'unpaid', 'yay', '2025-09-30 00:34:32', '2025-09-30 00:34:32');

-- --------------------------------------------------------

--
-- Table structure for table `booking_events`
--

CREATE TABLE `booking_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_role` enum('admin','driver','system') NOT NULL,
  `event_type` enum('create','assign','accept','reject','admin_approve_reject','admin_deny_reject','start_trip','complete_trip','cancel','restore','update') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_events`
--

INSERT INTO `booking_events` (`id`, `booking_id`, `actor_id`, `actor_role`, `event_type`, `details`, `created_at`) VALUES
(3, 1, 1, 'admin', 'cancel', '[]', '2025-09-15 13:53:09'),
(4, 1, 1, 'admin', 'restore', '[]', '2025-09-15 13:53:12'),
(5, 1, 1, 'admin', 'assign', '[]', '2025-09-15 15:41:30'),
(6, 1, 1, 'admin', 'complete_trip', '[]', '2025-09-15 15:41:38'),
(7, 1, 1, 'admin', 'cancel', '[]', '2025-09-21 20:42:00'),
(8, 1, 1, 'admin', 'cancel', '[]', '2025-09-21 21:06:28'),
(9, 1, 1, 'admin', 'restore', '[]', '2025-09-22 04:24:43'),
(10, 1, 1, 'admin', 'cancel', '[]', '2025-09-22 04:25:00'),
(11, 1, 1, 'admin', 'restore', '[]', '2025-09-22 04:36:25'),
(12, 1, 1, 'admin', 'cancel', '[]', '2025-09-22 04:38:16'),
(13, 2, 1, 'admin', 'cancel', '[]', '2025-09-29 02:58:20'),
(14, 2, 1, 'admin', 'restore', '[]', '2025-09-29 02:58:40'),
(15, 2, 1, 'admin', 'cancel', '[]', '2025-09-29 03:02:06'),
(16, 2, 1, 'admin', 'cancel', '[]', '2025-09-29 03:04:32'),
(17, 2, 1, 'admin', 'restore', '[]', '2025-09-29 04:13:28'),
(18, 2, 1, 'admin', 'cancel', '[]', '2025-09-29 04:13:37'),
(19, 2, 1, 'admin', 'restore', '[]', '2025-09-29 04:18:50'),
(20, 2, 1, 'admin', 'assign', '[]', '2025-09-29 04:19:02'),
(21, 2, 1, 'admin', 'complete_trip', '[]', '2025-09-29 04:19:13'),
(22, 3, 1, 'admin', 'assign', '[]', '2025-09-29 23:33:59'),
(23, 3, 1, 'admin', 'complete_trip', '[]', '2025-09-29 23:34:23'),
(24, 4, 1, 'admin', 'cancel', '[]', '2025-09-29 23:34:31'),
(25, 4, 1, 'admin', 'restore', '[]', '2025-09-29 23:34:40'),
(26, 4, 1, 'admin', 'assign', '[]', '2025-09-29 23:34:57'),
(27, 4, 1, 'admin', 'complete_trip', '[]', '2025-09-29 23:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `booking_offers`
--

CREATE TABLE `booking_offers` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `driver_id` int(10) UNSIGNED NOT NULL,
  `offered_by` int(10) UNSIGNED NOT NULL,
  `response` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `response_at` datetime DEFAULT NULL,
  `driver_reason` varchar(255) DEFAULT NULL,
  `admin_verification` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `booking_offers`
--
DELIMITER $$
CREATE TRIGGER `trg_offer_accept` AFTER UPDATE ON `booking_offers` FOR EACH ROW BEGIN
  IF NEW.response='accepted' AND OLD.response <> 'accepted' THEN
    UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id=NEW.booking_id;
    INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
    VALUES(NEW.booking_id, NEW.driver_id, 'driver', 'accept', JSON_OBJECT('offer_id', NEW.id));
    INSERT INTO notifications(recipient_id, type, payload)
    SELECT offered_by, 'trip_assigned', JSON_OBJECT('booking_id', NEW.booking_id, 'response','accepted')
    FROM booking_offers WHERE id=NEW.id;
  END IF;

  IF NEW.response='rejected' AND OLD.response <> 'rejected' THEN
    UPDATE bookings SET status='rejected', updated_at=NOW() WHERE id=NEW.booking_id;
    INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
    VALUES(NEW.booking_id, NEW.driver_id, 'driver', 'reject', JSON_OBJECT('offer_id', NEW.id, 'reason', NEW.driver_reason));
    INSERT INTO notifications(recipient_id, type, payload)
    SELECT offered_by, 'trip_rejected', JSON_OBJECT('booking_id', NEW.booking_id, 'reason', NEW.driver_reason)
    FROM booking_offers WHERE id=NEW.id;
  END IF;

  IF NEW.admin_verification='approved' AND OLD.admin_verification <> 'approved' THEN
    UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=NEW.booking_id AND status='rejected';
    INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
    VALUES(NEW.booking_id, NEW.verified_by, 'admin', 'admin_approve_reject', JSON_OBJECT('offer_id', NEW.id));
    INSERT INTO notifications(recipient_id, type, payload)
    SELECT driver_id, 'admin_decision', JSON_OBJECT('booking_id', NEW.booking_id, 'decision','approved')
    FROM booking_offers WHERE id=NEW.id;
  END IF;

  IF NEW.admin_verification='denied' AND OLD.admin_verification <> 'denied' THEN
    UPDATE bookings SET status='awaiting_driver', updated_at=NOW() WHERE id=NEW.booking_id AND status='rejected';
    INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
    VALUES(NEW.booking_id, NEW.verified_by, 'admin', 'admin_deny_reject', JSON_OBJECT('offer_id', NEW.id));
    INSERT INTO notifications(recipient_id, type, payload)
    SELECT driver_id, 'admin_decision', JSON_OBJECT('booking_id', NEW.booking_id, 'decision','denied')
    FROM booking_offers WHERE id=NEW.id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_runs`
--

CREATE TABLE `booking_runs` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `pickup_button_at` datetime DEFAULT NULL,
  `dropoff_button_at` datetime DEFAULT NULL,
  `odo_start_km` decimal(10,1) DEFAULT NULL,
  `odo_end_km` decimal(10,1) DEFAULT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `fuel_used_liters` decimal(10,2) DEFAULT NULL,
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_profile`
--

CREATE TABLE `driver_profile` (
  `account_id` int(10) UNSIGNED NOT NULL,
  `license_no` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `current_status` enum('available','on_trip','off') NOT NULL DEFAULT 'available',
  `hired_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_profile`
--

INSERT INTO `driver_profile` (`account_id`, `license_no`, `address`, `notes`, `current_status`, `hired_at`, `created_at`, `updated_at`) VALUES
(2, '123', 'taga san fernando, pampanga', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 23:34:23'),
(4, '123', 'taga ac', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(5, '123', 'somewhere', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(6, '12345', 'taga ac din', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(7, '123', 'taga idk somewhere friendship', NULL, 'available', NULL, '2025-09-29 04:27:49', '2025-09-29 23:35:24'),
(8, '123', 'angeles', NULL, 'available', NULL, '2025-09-30 00:07:41', '2025-09-30 00:07:41'),
(9, '123', 'angeles', NULL, 'available', NULL, '2025-09-30 00:08:30', '2025-09-30 00:08:30'),
(10, '789', 'angeles', NULL, 'available', NULL, '2025-09-30 00:09:46', '2025-09-30 00:09:46'),
(11, '362', 'angeles', NULL, 'available', NULL, '2025-09-30 00:10:51', '2025-09-30 00:10:51'),
(12, '590', 'angeles', NULL, 'available', NULL, '2025-09-30 00:11:35', '2025-09-30 00:11:35');

-- --------------------------------------------------------

--
-- Table structure for table `driver_profile_backup_20250929`
--

CREATE TABLE `driver_profile_backup_20250929` (
  `account_id` int(10) UNSIGNED NOT NULL,
  `license_no` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `current_status` enum('available','on_trip','off') NOT NULL DEFAULT 'available',
  `hired_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_profile_backup_20250929`
--

INSERT INTO `driver_profile_backup_20250929` (`account_id`, `license_no`, `address`, `notes`, `current_status`, `hired_at`, `created_at`, `updated_at`) VALUES
(2, '123', 'taga san fernando, pampanga', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(4, '123', 'taga ac', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(5, '123', 'somewhere', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(6, '12345', 'taga ac din', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, '123', 'taga san fernando, pampanga', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(4, '123', 'taga ac', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(5, '123', 'somewhere', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(6, '12345', 'taga ac din', NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18'),
(2, NULL, NULL, NULL, 'available', NULL, '2025-09-29 03:09:18', '2025-09-29 03:09:18');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `login_time` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `type` enum('trip_assigned','trip_rejected','admin_decision','obd_alert','system') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `obd_logs`
--

CREATE TABLE `obd_logs` (
  `id` int(11) NOT NULL,
  `speed` varchar(255) NOT NULL,
  `rpm` varchar(255) NOT NULL,
  `engine_load` varchar(255) NOT NULL,
  `throttle` varchar(255) NOT NULL,
  `intake_manifold` varchar(255) NOT NULL,
  `maf` varchar(255) NOT NULL,
  `coolant_temp` varchar(255) NOT NULL,
  `intake_air_temp` varchar(255) NOT NULL,
  `fuel_level` varchar(255) NOT NULL,
  `fuel_type` varchar(255) NOT NULL,
  `ambient_temp` varchar(255) NOT NULL,
  `oil_temp` varchar(255) NOT NULL,
  `plate_no` varchar(255) NOT NULL,
  `latitude` varchar(255) NOT NULL,
  `longitude` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `obd_logs`
--

INSERT INTO `obd_logs` (`id`, `speed`, `rpm`, `engine_load`, `throttle`, `intake_manifold`, `maf`, `coolant_temp`, `intake_air_temp`, `fuel_level`, `fuel_type`, `ambient_temp`, `oil_temp`, `plate_no`, `latitude`, `longitude`) VALUES
(1, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(2, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(3, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(4, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(5, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(6, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(7, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(8, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(9, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(10, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(11, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(12, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(13, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(14, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(15, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(16, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(17, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(18, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(19, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(20, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(21, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(22, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(23, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(24, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(25, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(26, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(27, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(28, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(29, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(30, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(31, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(32, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(33, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(34, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(35, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(36, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(37, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(38, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(39, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(40, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(41, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(42, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(43, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(44, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(45, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(46, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(47, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(48, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(49, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(50, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(51, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(52, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(53, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(54, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(55, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(56, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(57, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(58, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(59, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(60, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(61, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(62, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(63, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(64, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(65, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(66, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(67, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(68, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(69, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(70, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(71, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(72, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(73, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(74, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(75, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(76, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(77, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(78, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(79, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(80, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(81, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(82, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(83, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(84, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(85, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(86, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(87, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(88, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(89, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(90, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(91, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(92, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(93, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(94, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(95, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(96, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(97, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(98, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(99, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(100, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(101, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(102, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(103, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(104, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(105, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(106, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(107, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(108, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(109, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(110, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(111, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(112, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(113, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(114, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(115, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(116, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(117, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(118, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(119, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(120, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(121, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(122, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(123, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(124, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(125, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(126, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(127, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(128, '', '', '', '', '', '', '85', '30', '50', '1', '28', '90', '123456', '15.144500', '120.591800'),
(129, '94', '1004', '90', '8', '36', '33', '98', '55', '78', 'Gasoline', '40', '99', '123456', '15.14786', '120.58948'),
(130, '33', '1693', '79', '94', '87', '45', '108', '50', '68', 'Gasoline', '31', '84', 'ABC123', '15.14561', '120.58382'),
(131, '59', '1591', '32', '51', '42', '41', '80', '46', '12', 'Gasoline', '24', '72', '123456', '15.14129', '120.58909'),
(132, '36', '4648', '88', '0', '27', '6', '79', '36', '33', 'Gasoline', '17', '94', '123456', '15.14541', '120.59'),
(133, '45', '2593', '52', '44', '42', '38', '101', '34', '100', 'Gasoline', '40', '95', 'ABC123', '15.14653', '120.58453'),
(134, '92', '1002', '90', '15', '100', '18', '80', '28', '70', 'Gasoline', '20', '110', 'ABC123', '15.14155', '120.59101'),
(135, '48', '2198', '47', '77', '94', '10', '89', '22', '15', 'Gasoline', '21', '117', '123456', '15.142', '120.58446'),
(136, '40', '3006', '63', '12', '75', '28', '83', '36', '94', 'Gasoline', '20', '118', '123456', '15.14154', '120.58568'),
(137, '95', '1691', '82', '20', '23', '38', '108', '22', '94', 'Gasoline', '22', '69', '123456', '15.14496', '120.59123'),
(138, '41', '738', '90', '68', '92', '35', '82', '27', '89', 'Gasoline', '38', '115', '123456', '15.14828', '120.59307'),
(139, '120', '3663', '96', '93', '41', '50', '96', '53', '38', 'Gasoline', '39', '111', '123456', '15.14198', '120.58788'),
(140, '17', '2454', '100', '50', '23', '35', '105', '49', '64', 'Gasoline', '35', '101', 'ABC123', '15.14911', '120.58751'),
(141, '64', '4210', '86', '22', '96', '23', '85', '43', '63', 'Gasoline', '27', '114', '123456', '15.14085', '120.58632'),
(142, '68', '2634', '84', '94', '65', '8', '107', '33', '91', 'Gasoline', '28', '67', 'ABC123', '15.14564', '120.59033'),
(143, '46', '4227', '88', '49', '43', '43', '73', '57', '51', 'Gasoline', '31', '63', '123456', '15.14366', '120.58735'),
(144, '90', '4915', '78', '48', '41', '46', '88', '45', '14', 'Gasoline', '34', '98', 'ABC123', '15.14184', '120.59343'),
(145, '0', '1979', '95', '2', '90', '2', '102', '37', '79', 'Gasoline', '34', '109', 'ABC123', '15.1438', '120.59094'),
(146, '110', '2436', '81', '34', '11', '20', '77', '58', '58', 'Gasoline', '20', '114', 'ABC123', '15.14187', '120.58503'),
(147, '25', '1973', '77', '67', '90', '49', '71', '35', '26', 'Gasoline', '26', '93', '123456', '15.14983', '120.59249'),
(148, '38', '920', '33', '10', '46', '23', '76', '44', '49', 'Gasoline', '33', '85', '123456', '15.14185', '120.58811'),
(149, '42', '1516', '71', '51', '30', '23', '104', '50', '42', 'Gasoline', '26', '104', 'ABC123', '15.14468', '120.59108'),
(150, '89', '733', '36', '77', '14', '47', '106', '46', '16', 'Gasoline', '40', '82', 'ABC123', '15.14933', '120.58816'),
(151, '15', '3572', '64', '34', '68', '33', '91', '49', '21', 'Gasoline', '16', '97', '123456', '15.14511', '120.58736'),
(152, '33', '4308', '46', '91', '31', '17', '84', '38', '17', 'Gasoline', '17', '79', 'ABC123', '15.14923', '120.58881'),
(153, '30', '2185', '95', '100', '73', '40', '73', '24', '69', 'Gasoline', '32', '74', 'ABC123', '15.14869', '120.58923'),
(154, '68', '3563', '94', '84', '52', '11', '105', '32', '100', 'Gasoline', '24', '100', 'ABC123', '15.14952', '120.58566'),
(155, '111', '3713', '35', '42', '90', '38', '102', '59', '76', 'Gasoline', '32', '81', 'ABC123', '15.14731', '120.59028'),
(156, '82', '3880', '30', '83', '42', '50', '95', '27', '32', 'Gasoline', '36', '68', '123456', '15.14218', '120.59099'),
(157, '18', '3785', '43', '65', '100', '5', '109', '60', '64', 'Gasoline', '25', '97', 'ABC123', '15.14075', '120.58676'),
(158, '104', '3401', '41', '92', '52', '21', '81', '48', '43', 'Gasoline', '30', '91', '123456', '15.14648', '120.58873'),
(159, '104', '3411', '89', '42', '90', '40', '76', '38', '65', 'Gasoline', '17', '106', 'ABC123', '15.1473', '120.59326'),
(160, '87', '969', '51', '95', '72', '22', '89', '26', '52', 'Gasoline', '27', '88', 'ABC123', '15.14276', '120.58537'),
(161, '34', '3187', '29', '6', '60', '30', '83', '58', '70', 'Gasoline', '38', '97', '123456', '15.1416', '120.5917'),
(162, '7', '3309', '80', '25', '48', '33', '87', '44', '53', 'Gasoline', '34', '72', '123456', '15.14134', '120.58547'),
(163, '68', '929', '28', '15', '27', '20', '89', '57', '39', 'Gasoline', '35', '110', 'ABC123', '15.14449', '120.59093'),
(164, '18', '794', '31', '32', '81', '10', '100', '28', '70', 'Gasoline', '30', '87', '123456', '15.1427', '120.59352'),
(165, '68', '3054', '92', '28', '69', '6', '95', '28', '57', 'Gasoline', '34', '66', 'ABC123', '15.14164', '120.58962'),
(166, '71', '2618', '58', '45', '95', '5', '100', '36', '23', 'Gasoline', '40', '95', 'ABC123', '15.1494', '120.58476'),
(167, '70', '2606', '95', '96', '33', '32', '109', '25', '47', 'Gasoline', '36', '96', 'ABC123', '15.14834', '120.58958'),
(168, '25', '1033', '81', '73', '11', '46', '109', '53', '76', 'Gasoline', '23', '102', 'ABC123', '15.14656', '120.59303'),
(169, '67', '1486', '95', '24', '24', '31', '98', '56', '66', 'Gasoline', '22', '108', 'ABC123', '15.14893', '120.59135'),
(170, '73', '1167', '37', '33', '100', '12', '91', '23', '60', 'Gasoline', '22', '75', 'ABC123', '15.14242', '120.58763'),
(171, '112', '3169', '37', '98', '45', '50', '87', '60', '33', 'Gasoline', '30', '91', '123456', '15.1442', '120.59369'),
(172, '60', '2304', '79', '18', '17', '15', '89', '57', '71', 'Gasoline', '20', '78', 'ABC123', '15.14537', '120.58722'),
(173, '47', '907', '40', '76', '96', '26', '97', '47', '15', 'Gasoline', '24', '99', '123456', '15.14683', '120.58938'),
(174, '56', '1441', '70', '23', '40', '25', '79', '26', '11', 'Gasoline', '33', '70', '123456', '15.14641', '120.58768'),
(175, '56', '1329', '45', '48', '81', '14', '89', '22', '87', 'Gasoline', '23', '87', '123456', '15.14819', '120.59111'),
(176, '110', '1583', '92', '63', '20', '5', '95', '28', '53', 'Gasoline', '31', '66', '123456', '15.1481', '120.59109'),
(177, '83', '1374', '52', '25', '11', '16', '81', '57', '76', 'Gasoline', '27', '96', '123456', '15.15017', '120.59195'),
(178, '60', '3602', '27', '89', '99', '43', '103', '56', '81', 'Gasoline', '37', '65', 'ABC123', '15.1489', '120.58587'),
(179, '119', '4423', '92', '44', '43', '15', '91', '36', '83', 'Gasoline', '24', '86', 'ABC123', '15.14462', '120.58755'),
(180, '99', '4092', '58', '37', '56', '27', '74', '36', '82', 'Gasoline', '15', '106', 'ABC123', '15.14427', '120.58485'),
(181, '109', '1601', '31', '5', '85', '15', '91', '45', '46', 'Gasoline', '35', '120', '123456', '15.14905', '120.58748'),
(182, '50', '2202', '74', '28', '73', '44', '95', '56', '41', 'Gasoline', '22', '119', '123456', '15.14965', '120.58779'),
(183, '91', '4432', '84', '84', '57', '17', '110', '20', '84', 'Gasoline', '38', '68', '123456', '15.1441', '120.59169'),
(184, '78', '763', '59', '9', '20', '43', '89', '27', '13', 'Gasoline', '27', '98', 'ABC123', '15.14888', '120.59321'),
(185, '37', '3011', '52', '40', '46', '44', '82', '47', '39', 'Gasoline', '31', '105', 'ABC123', '15.14226', '120.59124'),
(186, '74', '1650', '90', '35', '58', '15', '76', '26', '41', 'Gasoline', '37', '113', 'ABC123', '15.14252', '120.58772'),
(187, '43', '2959', '91', '42', '91', '18', '100', '49', '56', 'Gasoline', '21', '115', 'ABC123', '15.14961', '120.5877'),
(188, '118', '4193', '79', '99', '45', '11', '107', '43', '87', 'Gasoline', '28', '114', 'ABC123', '15.14192', '120.58945'),
(189, '57', '4653', '44', '18', '67', '14', '95', '42', '84', 'Gasoline', '26', '104', 'ABC123', '15.14085', '120.58432'),
(190, '90', '1997', '40', '4', '77', '27', '94', '59', '90', 'Gasoline', '22', '64', '123456', '15.1495', '120.59362'),
(191, '31', '3600', '26', '20', '83', '2', '83', '39', '16', 'Gasoline', '30', '99', '123456', '15.1424', '120.59282'),
(192, '35', '3076', '49', '37', '48', '8', '101', '45', '68', 'Gasoline', '28', '63', '123456', '15.14874', '120.58842'),
(193, '19', '2236', '62', '63', '47', '16', '105', '49', '31', 'Gasoline', '31', '112', '123456', '15.14061', '120.58449'),
(194, '73', '2661', '37', '7', '56', '29', '83', '26', '54', 'Gasoline', '26', '109', '123456', '15.14645', '120.5912'),
(195, '68', '1397', '34', '4', '16', '2', '98', '46', '23', 'Gasoline', '24', '96', '123456', '15.14823', '120.59073'),
(196, '41', '1907', '39', '12', '92', '31', '94', '22', '97', 'Gasoline', '38', '118', 'ABC123', '15.14466', '120.58809'),
(197, '62', '3065', '55', '56', '36', '6', '94', '25', '76', 'Gasoline', '31', '108', '123456', '15.15047', '120.58619'),
(198, '1', '3734', '33', '81', '77', '21', '81', '55', '75', 'Gasoline', '20', '104', '123456', '15.14438', '120.58805'),
(199, '19', '768', '74', '59', '99', '43', '71', '58', '95', 'Gasoline', '33', '74', '123456', '15.14226', '120.59082'),
(200, '5', '3469', '55', '80', '59', '29', '102', '32', '83', 'Gasoline', '21', '107', 'ABC123', '15.14923', '120.58984'),
(201, '76', '3801', '27', '39', '25', '25', '100', '53', '10', 'Gasoline', '23', '107', 'ABC123', '15.1417', '120.58933'),
(202, '47', '2481', '33', '59', '70', '7', '86', '58', '75', 'Gasoline', '17', '118', '123456', '15.14234', '120.59201'),
(203, '93', '3862', '28', '0', '47', '43', '86', '32', '43', 'Gasoline', '22', '117', 'ABC123', '15.14143', '120.58679'),
(204, '66', '3349', '31', '95', '74', '35', '110', '53', '52', 'Gasoline', '29', '82', 'ABC123', '15.14549', '120.58465'),
(205, '60', '2379', '97', '26', '79', '26', '97', '38', '23', 'Gasoline', '34', '100', '123456', '15.14903', '120.5879'),
(206, '98', '1215', '98', '72', '84', '3', '92', '34', '71', 'Gasoline', '17', '115', 'ABC123', '15.14472', '120.59051'),
(207, '28', '2309', '98', '61', '82', '42', '98', '40', '40', 'Gasoline', '20', '111', 'ABC123', '15.14067', '120.58822'),
(208, '22', '885', '76', '99', '18', '37', '102', '22', '58', 'Gasoline', '30', '91', 'ABC123', '15.14322', '120.58755'),
(209, '61', '3158', '45', '16', '48', '32', '103', '58', '64', 'Gasoline', '39', '98', '123456', '15.142', '120.58564'),
(210, '86', '1127', '58', '32', '45', '37', '90', '51', '71', 'Gasoline', '15', '83', 'ABC123', '15.1446', '120.58751'),
(211, '37', '2996', '74', '76', '75', '12', '100', '27', '36', 'Gasoline', '35', '81', 'ABC123', '15.14571', '120.59264'),
(212, '39', '764', '50', '9', '29', '34', '101', '40', '28', 'Gasoline', '29', '74', 'ABC123', '15.14731', '120.589'),
(213, '43', '3427', '58', '61', '45', '34', '91', '20', '25', 'Gasoline', '31', '61', 'ABC123', '15.14062', '120.58666'),
(214, '60', '2468', '99', '32', '25', '49', '92', '22', '79', 'Gasoline', '36', '81', 'ABC123', '15.14202', '120.58574'),
(215, '22', '2742', '68', '51', '12', '15', '91', '51', '34', 'Gasoline', '15', '110', 'ABC123', '15.14282', '120.58967'),
(216, '7', '3525', '40', '85', '56', '35', '89', '60', '17', 'Gasoline', '36', '93', 'ABC123', '15.1411', '120.58497'),
(217, '7', '4899', '88', '33', '89', '41', '81', '21', '28', 'Gasoline', '33', '75', '123456', '15.14416', '120.58469'),
(218, '3', '3509', '77', '31', '59', '13', '77', '25', '54', 'Gasoline', '34', '118', '123456', '15.14845', '120.59037'),
(219, '56', '3979', '74', '34', '64', '12', '72', '51', '13', 'Gasoline', '27', '70', '123456', '15.1411', '120.59132'),
(220, '1', '3730', '76', '37', '33', '20', '82', '21', '80', 'Gasoline', '36', '107', 'ABC123', '15.14329', '120.5926'),
(221, '21', '3991', '89', '2', '97', '20', '90', '20', '52', 'Gasoline', '26', '68', 'ABC123', '15.14376', '120.58384'),
(222, '56', '917', '40', '48', '78', '11', '106', '57', '70', 'Gasoline', '39', '119', 'ABC123', '15.14255', '120.58661'),
(223, '30', '2578', '73', '91', '91', '29', '94', '57', '93', 'Gasoline', '22', '66', '123456', '15.15033', '120.58477'),
(224, '88', '2198', '25', '97', '36', '27', '84', '34', '43', 'Gasoline', '29', '109', 'ABC123', '15.14691', '120.58746'),
(225, '110', '1239', '87', '66', '16', '11', '109', '32', '92', 'Gasoline', '27', '94', 'ABC123', '15.14617', '120.58965'),
(226, '119', '4418', '95', '53', '76', '43', '84', '20', '51', 'Gasoline', '18', '61', 'ABC123', '15.14265', '120.59324'),
(227, '27', '2572', '77', '14', '20', '13', '104', '59', '25', 'Gasoline', '22', '77', 'ABC123', '15.14231', '120.59207'),
(228, '93', '3583', '38', '37', '17', '18', '106', '33', '31', 'Gasoline', '40', '75', '123456', '15.14592', '120.58418'),
(229, '111', '824', '100', '83', '50', '16', '97', '22', '64', 'Gasoline', '35', '103', 'ABC123', '15.14139', '120.58407'),
(230, '94', '993', '20', '83', '46', '30', '73', '28', '63', 'Gasoline', '27', '113', '123456', '15.14415', '120.58874'),
(231, '116', '857', '46', '21', '53', '40', '97', '28', '31', 'Gasoline', '40', '75', 'ABC123', '15.14716', '120.58468'),
(232, '82', '2273', '22', '23', '33', '32', '104', '42', '32', 'Gasoline', '23', '80', 'ABC123', '15.14277', '120.59341'),
(233, '51', '3064', '32', '22', '72', '45', '105', '29', '84', 'Gasoline', '37', '85', '123456', '15.14366', '120.59092'),
(234, '0', '4283', '83', '69', '46', '19', '102', '26', '95', 'Gasoline', '40', '107', '123456', '15.14485', '120.58612'),
(235, '40', '949', '55', '12', '88', '38', '92', '53', '85', 'Gasoline', '27', '107', 'ABC123', '15.1503', '120.58498'),
(236, '114', '3714', '81', '31', '34', '16', '87', '51', '35', 'Gasoline', '26', '93', 'ABC123', '15.14208', '120.59019'),
(237, '7', '738', '53', '65', '20', '7', '86', '22', '20', 'Gasoline', '28', '63', '123456', '15.14773', '120.59203'),
(238, '97', '2764', '36', '45', '82', '9', '90', '33', '14', 'Gasoline', '24', '90', 'ABC123', '15.14482', '120.59274'),
(239, '25', '4084', '57', '80', '45', '47', '79', '36', '25', 'Gasoline', '29', '90', '123456', '15.14785', '120.59153'),
(240, '101', '2961', '70', '9', '27', '25', '104', '31', '36', 'Gasoline', '25', '85', 'ABC123', '15.14898', '120.58443'),
(241, '76', '1356', '84', '61', '77', '41', '75', '42', '50', 'Gasoline', '25', '91', '123456', '15.14993', '120.59169'),
(242, '61', '1927', '90', '95', '53', '41', '96', '20', '99', 'Gasoline', '16', '94', 'ABC123', '15.14239', '120.58917'),
(243, '38', '4573', '23', '59', '57', '44', '76', '57', '57', 'Gasoline', '38', '66', '123456', '15.1415', '120.58493'),
(244, '99', '3295', '25', '3', '27', '22', '95', '45', '11', 'Gasoline', '32', '102', '123456', '15.14772', '120.58442'),
(245, '66', '2507', '35', '24', '91', '30', '94', '27', '22', 'Gasoline', '25', '105', 'ABC123', '15.14536', '120.58957'),
(246, '117', '3621', '100', '55', '37', '10', '103', '25', '15', 'Gasoline', '39', '100', 'ABC123', '15.15038', '120.58815'),
(247, '25', '958', '59', '35', '77', '39', '78', '54', '35', 'Gasoline', '36', '70', 'ABC123', '15.14363', '120.58969'),
(248, '58', '1076', '63', '32', '55', '32', '76', '54', '58', 'Gasoline', '29', '99', '123456', '15.14985', '120.5858'),
(249, '5', '3681', '76', '68', '46', '12', '74', '50', '41', 'Gasoline', '19', '81', '123456', '15.14692', '120.58582'),
(250, '26', '2501', '29', '62', '25', '3', '95', '22', '77', 'Gasoline', '35', '81', '123456', '15.14731', '120.59375'),
(251, '66', '2817', '83', '1', '12', '18', '86', '21', '20', 'Gasoline', '35', '77', '123456', '15.14659', '120.58992');

-- --------------------------------------------------------

--
-- Table structure for table `telemetry_alerts`
--

CREATE TABLE `telemetry_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `sample_id` int(10) UNSIGNED DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `alert_code` varchar(40) DEFAULT NULL,
  `level` enum('info','warn','error','critical') NOT NULL DEFAULT 'warn',
  `message` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `telemetry_samples`
--

CREATE TABLE `telemetry_samples` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `speed_kph` decimal(6,2) DEFAULT NULL,
  `fuel_level_pct` decimal(5,2) DEFAULT NULL,
  `odometer_km` decimal(10,1) DEFAULT NULL,
  `raw_obd` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_obd`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_admin`
--

CREATE TABLE `tms_admin` (
  `a_id` int(11) NOT NULL,
  `a_name` varchar(200) NOT NULL,
  `a_email` varchar(200) NOT NULL,
  `a_pwd` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_admin`
--

INSERT INTO `tms_admin` (`a_id`, `a_name`, `a_email`, `a_pwd`) VALUES
(3, '', 'admin@gmail.com', '$2y$10$fAIUbxhK/sEWluSFpNbTUeMeQYjKoToz9anTnD4YK7dOP9u7acJWO'),
(3, '', 'admin@gmail.com', '$2y$10$fAIUbxhK/sEWluSFpNbTUeMeQYjKoToz9anTnD4YK7dOP9u7acJWO');

-- --------------------------------------------------------

--
-- Table structure for table `tms_audit_log`
--

CREATE TABLE `tms_audit_log` (
  `id` int(11) NOT NULL,
  `actor_type` enum('admin','driver') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `booking_u_id` int(11) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_bookings`
--

CREATE TABLE `tms_bookings` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `booking_type` enum('admin','personal') NOT NULL DEFAULT 'admin',
  `created_by_driver_id` int(10) UNSIGNED DEFAULT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `pickup_point` varchar(255) NOT NULL,
  `dropoff_point` varchar(255) NOT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `dropoff_lat` decimal(10,7) DEFAULT NULL,
  `dropoff_lng` decimal(10,7) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `seats_reserved` tinyint(3) UNSIGNED DEFAULT 1,
  `scheduled_at` datetime DEFAULT NULL,
  `booking_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','accepted','declined','cancelled','completed') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','partial') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_bookings`
--

INSERT INTO `tms_bookings` (`booking_id`, `client_id`, `booking_type`, `created_by_driver_id`, `driver_id`, `vehicle_id`, `pickup_point`, `dropoff_point`, `pickup_lat`, `pickup_lng`, `dropoff_lat`, `dropoff_lng`, `contact_phone`, `seats_reserved`, `scheduled_at`, `booking_created_at`, `status`, `payment_status`, `notes`) VALUES
(1, 2, 'admin', NULL, NULL, NULL, '100 Main St, Town', 'Airport Terminal 1', NULL, NULL, NULL, NULL, '+639171234567', 3, '2025-08-12 14:00:00', '2025-09-12 20:14:29', 'pending', 'unpaid', NULL),
(1, 2, 'admin', NULL, NULL, NULL, '100 Main St, Town', 'Airport Terminal 1', NULL, NULL, NULL, NULL, '+639171234567', 3, '2025-08-12 14:00:00', '2025-09-12 20:14:29', 'pending', 'unpaid', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tms_driver_report`
--

CREATE TABLE `tms_driver_report` (
  `report_id` int(11) NOT NULL,
  `driver_id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED DEFAULT NULL,
  `trip_date` date NOT NULL,
  `shift_start` datetime DEFAULT NULL,
  `shift_end` datetime DEFAULT NULL,
  `odometer_start` int(11) DEFAULT NULL,
  `odometer_end` int(11) DEFAULT NULL,
  `total_km` decimal(8,1) DEFAULT NULL,
  `fuel_used_liters` decimal(8,2) DEFAULT NULL,
  `route_from` varchar(120) DEFAULT NULL,
  `route_to` varchar(120) DEFAULT NULL,
  `pickups` int(11) DEFAULT NULL,
  `dropoffs` int(11) DEFAULT NULL,
  `passengers_moved` int(11) DEFAULT NULL,
  `incident_level` enum('OK','Minor','Major') NOT NULL DEFAULT 'OK',
  `status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_feedback`
--

CREATE TABLE `tms_feedback` (
  `f_id` int(11) NOT NULL,
  `f_uname` varchar(200) NOT NULL,
  `f_content` longtext NOT NULL,
  `f_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_pwd_resets`
--

CREATE TABLE `tms_pwd_resets` (
  `r_id` int(11) NOT NULL,
  `r_email` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_report_media`
--

CREATE TABLE `tms_report_media` (
  `media_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `caption` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_syslogs`
--

CREATE TABLE `tms_syslogs` (
  `l_id` int(11) NOT NULL,
  `u_id` varchar(200) NOT NULL,
  `u_email` varchar(200) NOT NULL,
  `u_ip` varbinary(200) NOT NULL,
  `u_city` varchar(200) NOT NULL,
  `u_country` varchar(200) NOT NULL,
  `pickup_point` mediumtext NOT NULL,
  `dropoff_point` mediumtext NOT NULL,
  `driver_id` mediumtext NOT NULL,
  `booking_date` mediumtext NOT NULL,
  `u_logintime` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tms_user`
--

CREATE TABLE `tms_user` (
  `u_id` int(11) NOT NULL,
  `u_fname` varchar(200) NOT NULL,
  `u_lname` varchar(200) NOT NULL,
  `u_car_pax` mediumtext NOT NULL,
  `u_phone` varchar(32) DEFAULT NULL,
  `u_addr` varchar(200) NOT NULL,
  `u_category` varchar(200) NOT NULL,
  `u_email` varchar(200) NOT NULL,
  `u_pwd` varchar(255) NOT NULL,
  `u_car_type` varchar(200) NOT NULL,
  `u_car_driver` mediumtext NOT NULL,
  `u_car_regno` varchar(200) NOT NULL,
  `u_car_bookdate` varchar(200) NOT NULL,
  `u_car_pickup` varchar(250) NOT NULL,
  `u_car_destination` varchar(250) NOT NULL,
  `u_car_book_status` varchar(200) NOT NULL,
  `u_car_date` mediumtext NOT NULL,
  `u_car_time` mediumtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `u_car_createdat` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_user`
--

INSERT INTO `tms_user` (`u_id`, `u_fname`, `u_lname`, `u_car_pax`, `u_phone`, `u_addr`, `u_category`, `u_email`, `u_pwd`, `u_car_type`, `u_car_driver`, `u_car_regno`, `u_car_bookdate`, `u_car_pickup`, `u_car_destination`, `u_car_book_status`, `u_car_date`, `u_car_time`, `created_at`, `u_car_createdat`) VALUES
(1, 'Alex', 'Turner', '', '09942317653', 'somewhere', 'Driver', '505@mail.com', '$2y$10$HKiK4KxiYJcbevWxQ4LK3e9uMBJ1jEj.Y0qENSp1Vc6Bo1H.Zx7OG', '', '', '', '', '', '', '', '', '', '2025-09-27 05:50:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tms_user_add_driver`
--

CREATE TABLE `tms_user_add_driver` (
  `d_u_id` int(10) UNSIGNED NOT NULL,
  `u_id` int(50) NOT NULL,
  `u_fname` varchar(50) NOT NULL,
  `u_lname` varchar(50) NOT NULL,
  `u_phone` varchar(32) DEFAULT NULL,
  `u_addr` text NOT NULL,
  `u_car_type` text NOT NULL,
  `u_car_regno` text NOT NULL,
  `u_car_bookdate` text NOT NULL,
  `u_car_book_status` text NOT NULL,
  `u_category` text NOT NULL,
  `u_email` text NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `u_pwd` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_user_add_driver`
--

INSERT INTO `tms_user_add_driver` (`d_u_id`, `u_id`, `u_fname`, `u_lname`, `u_phone`, `u_addr`, `u_car_type`, `u_car_regno`, `u_car_bookdate`, `u_car_book_status`, `u_category`, `u_email`, `deleted_at`, `deleted_by`, `u_pwd`, `created_at`, `is_archived`) VALUES
(8, 0, 'Shane', 'Lopez', '09446872447', 'taga san fernando, pampanga', 'Bus', '123', '', 'Available', 'Driver', 'shaaane@mail.com', '2025-09-30 00:04:59', 1, '', '2025-09-12 00:00:00', 0),
(9, 0, 'Test', 'Driver', '09668226441', 'taga ac', '', '123', '', 'Available', 'Driver', 'test@mail.com', '2025-09-30 00:04:55', 1, '', '2025-09-22 00:00:00', 0),
(10, 0, 'Alex', 'Turner', '09942317653', 'somewhere', '', '123', '', 'Available', 'Driver', '505@mail.com', NULL, NULL, '', '2025-09-26 20:53:29', 0),
(13, 0, 'Noah', 'Enguerra', '09123456789', 'taga ac din', '', '12345', '', 'Available', 'Driver', 'noah@mail.com', '2025-09-30 00:05:01', 1, '', '2025-09-28 15:16:43', 0),
(14, 0, 'Keihle', 'Pascual', '09784563214', 'taga idk somewhere friendship', '', '123', '', 'Available', 'Driver', 'kei@mail.com', '2025-09-30 00:05:02', 1, '', '2025-09-29 04:27:49', 0),
(15, 0, 'Rey', 'Cabral', '09877651234', 'angeles', '', '123', '', 'Available', 'Driver', 'r.cabral@gmail.com', NULL, NULL, '', '2025-09-30 00:07:41', 0),
(16, 0, 'Arnold', 'Lagman', '09871234563', 'angeles', '', '456', '', 'Available', 'Driver', 'a.lagman@gmail.com', NULL, NULL, '', '2025-09-30 00:08:30', 0),
(17, 0, 'Nestor', 'Sanchez', '0912345641', 'angeles', '', '789', '', 'Available', 'Driver', 'n.sanchez@gmail.com', NULL, NULL, '', '2025-09-30 00:09:46', 0),
(18, 0, 'Richie', 'Sibal', '0768126543', 'angeles', '', '362', '', 'Available', 'Driver', 'r.sibal@gmail.com', NULL, NULL, '', '2025-09-30 00:10:51', 0),
(19, 0, 'Manuel', 'Valencia', '09236571234', 'angeles', '', '590', '', 'Available', 'Driver', 'm.valencia@gmail.com', NULL, NULL, '', '2025-09-30 00:11:35', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle`
--

CREATE TABLE `tms_vehicle` (
  `v_id` int(10) UNSIGNED NOT NULL,
  `v_name` varchar(200) NOT NULL,
  `v_reg_no` varchar(200) NOT NULL,
  `make_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `v_driver` varchar(200) NOT NULL DEFAULT '',
  `v_category` varchar(200) NOT NULL,
  `driver_user_id` int(11) DEFAULT NULL,
  `v_dpic` varchar(200) NOT NULL DEFAULT '',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `v_status` varchar(200) NOT NULL,
  `default_driver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_vehicle`
--

INSERT INTO `tms_vehicle` (`v_id`, `v_name`, `v_reg_no`, `make_id`, `model_id`, `color`, `v_driver`, `v_category`, `driver_user_id`, `v_dpic`, `deleted_at`, `deleted_by`, `v_status`, `default_driver_id`) VALUES
(1, 'C 180 Avantgarde', '123456', NULL, NULL, '', '', 'Sedan', 1, 'vendor/img/vehicles/veh_1758021325_7528.webp', '2025-09-29 23:41:34', 1, 'Available', 8),
(2, 'Toyota Vios 1.3 E', 'NBM 4276', NULL, NULL, NULL, '', 'Sedan', NULL, 'vendor/img/vehicles/veh_1758925257_7662.png', '2025-09-29 23:37:33', 1, 'Available', 10),
(3, 'Toyota Fortuner G', 'NEE 7103', NULL, NULL, NULL, '', 'SUV', NULL, 'vendor/img/vehicles/veh_1759045781_4022.jpg', '2025-09-29 23:37:29', 1, 'Available', NULL),
(4, '', 'WOW 505', 1, 5, 'Black', '', 'Sedan', NULL, '', '2025-09-29 23:37:27', 1, 'Available', NULL),
(5, '', 'UUU 123', 8, 27, 'Green', '', 'Sedan', NULL, '', '2025-09-29 23:41:36', 1, 'Available', NULL),
(6, '', 'NCJ 9875', 1, 4, 'White', '', 'SUV', NULL, '', NULL, NULL, 'Available', NULL),
(7, '', 'NHI 3023', 1, 5, 'White', '', 'SUV', NULL, '', NULL, NULL, 'Available', NULL),
(8, '', 'CBT 4971', 2, 8, 'Brown', '', 'SUV', NULL, '', NULL, NULL, 'Available', NULL),
(9, '', 'DBF 9903', 1, 2, 'White', '', 'Van', NULL, '', NULL, NULL, 'Available', NULL),
(10, '', 'CCD 2879', 1, 28, 'Gray', '', 'Sedan', NULL, '', NULL, NULL, 'Available', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle_categories`
--

CREATE TABLE `tms_vehicle_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tms_vehicle_categories`
--

INSERT INTO `tms_vehicle_categories` (`id`, `name`, `is_active`, `deleted_at`) VALUES
(1, 'Sedan', 1, NULL),
(2, 'SUV', 1, NULL),
(3, 'Van', 1, NULL),
(10, 'Coaster', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle_categories_backup_yyyymmdd`
--

CREATE TABLE `tms_vehicle_categories_backup_yyyymmdd` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tms_vehicle_categories_backup_yyyymmdd`
--

INSERT INTO `tms_vehicle_categories_backup_yyyymmdd` (`id`, `name`, `is_active`, `deleted_at`) VALUES
(1, 'Bus', 0, '2025-09-20 20:52:51'),
(2, 'Sedan', 1, NULL),
(3, 'SUV', 1, NULL),
(4, 'Van', 1, NULL),
(1, 'Bus', 0, '2025-09-20 20:52:51'),
(2, 'Sedan', 1, NULL),
(3, 'SUV', 1, NULL),
(4, 'Van', 1, NULL),
(0, 'Coaster', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle_makes`
--

CREATE TABLE `tms_vehicle_makes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tms_vehicle_makes`
--

INSERT INTO `tms_vehicle_makes` (`id`, `name`, `is_active`) VALUES
(1, 'Toyota', 1),
(2, 'Suzuki', 1),
(3, 'Nissan', 1),
(4, 'Hyundai', 1),
(5, 'Kia', 1),
(6, 'Isuzu', 1),
(7, 'Mitsubishi', 1),
(8, 'Honda', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle_models`
--

CREATE TABLE `tms_vehicle_models` (
  `id` int(11) NOT NULL,
  `make_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tms_vehicle_models`
--

INSERT INTO `tms_vehicle_models` (`id`, `make_id`, `name`, `is_active`) VALUES
(1, 1, 'Hiace Commuter', 1),
(2, 1, 'Hiace GL Grandia', 1),
(3, 1, 'Hiace Super Grandia', 1),
(4, 1, 'Innova', 1),
(5, 1, 'Avanza', 1),
(6, 1, 'Rush', 1),
(8, 2, 'Ertiga', 1),
(9, 2, 'APV', 1),
(10, 2, 'Dzire', 1),
(11, 2, 'Celerio', 1),
(12, 2, 'Swift', 1),
(13, 2, 'Alto', 1),
(14, 3, 'Urvan / NV350', 1),
(15, 4, 'Starex', 1),
(16, 4, 'Grand Starex', 1),
(17, 4, 'Accent', 1),
(18, 4, 'Reina', 1),
(19, 7, 'Xpander', 1),
(20, 7, 'Montero Sport', 1),
(21, 7, 'Adventure', 1),
(22, 7, 'Mirage G4', 1),
(23, 5, 'Carnival', 1),
(24, 5, 'Rio', 1),
(25, 6, 'Crosswind', 1),
(26, 6, 'MU-X', 1),
(27, 8, 'Civic', 1),
(28, 1, 'Vios', 1);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles_legacy`
--

CREATE TABLE `vehicles_legacy` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `plate_no` varchar(64) NOT NULL,
  `category` enum('bus','sedan','suv','van','other') NOT NULL DEFAULT 'other',
  `seat_capacity` smallint(5) UNSIGNED DEFAULT NULL,
  `status` enum('available','in_use','maintenance','inactive') NOT NULL DEFAULT 'available',
  `picture` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles_legacy`
--

INSERT INTO `vehicles_legacy` (`id`, `name`, `plate_no`, `category`, `seat_capacity`, `status`, `picture`, `created_at`, `updated_at`) VALUES
(1, 'Euro Bond', 'CA7766', 'bus', 50, 'in_use', 'images.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(2, 'Honda Accord', 'CA2077', 'bus', 5, 'in_use', NULL, '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(3, 'Volkswagen Passat', 'CA1690', 'sedan', 5, 'available', 'volkswagen-passat-500.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(4, 'Nissan Rogue', 'CA1001', 'suv', 7, 'available', 'Nissan_Rogue_SV_2021.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(5, 'Subaru Legacy', 'CA7700', 'bus', 5, 'available', NULL, '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(1, 'Euro Bond', 'CA7766', 'bus', 50, 'in_use', 'images.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(2, 'Honda Accord', 'CA2077', 'bus', 5, 'in_use', NULL, '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(3, 'Volkswagen Passat', 'CA1690', 'sedan', 5, 'available', 'volkswagen-passat-500.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(4, 'Nissan Rogue', 'CA1001', 'suv', 7, 'available', 'Nissan_Rogue_SV_2021.jpg', '2025-09-12 20:14:29', '2025-09-12 20:14:29'),
(5, 'Subaru Legacy', 'CA7700', 'bus', 5, 'available', NULL, '2025-09-12 20:14:29', '2025-09-12 20:14:29');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_assignments`
--

CREATE TABLE `vehicle_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `driver_id` int(10) UNSIGNED NOT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `start_at` datetime NOT NULL DEFAULT current_timestamp(),
  `end_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_assignments`
--

INSERT INTO `vehicle_assignments` (`id`, `vehicle_id`, `driver_id`, `assigned_by`, `start_at`, `end_at`) VALUES
(1, 1, 2, 1, '2025-09-27 10:36:01', '2025-09-29 23:37:35'),
(2, 2, 5, 1, '2025-09-27 10:36:01', '2025-09-29 23:37:33'),
(4, 3, 6, 1, '2025-09-28 15:49:41', '2025-09-29 23:37:29'),
(5, 1, 5, 1, '2025-09-29 23:40:53', '2025-09-29 23:41:34'),
(6, 5, 2, 1, '2025-09-29 23:41:11', '2025-09-29 23:41:36'),
(7, 6, 5, 1, '2025-09-30 00:03:37', '2025-09-30 00:11:54'),
(8, 6, 9, 1, '2025-09-30 00:11:54', NULL),
(9, 7, 12, 1, '2025-09-30 00:12:24', NULL),
(10, 8, 8, 1, '2025-09-30 00:12:42', NULL),
(11, 9, 10, 1, '2025-09-30 00:12:54', NULL),
(12, 10, 11, 1, '2025-09-30 00:13:07', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_booking_grid`
-- (See below for the actual view)
--
CREATE TABLE `v_booking_grid` (
`booking_id` int(10) unsigned
,`scheduled_at` datetime
,`created_at` datetime
,`client_name` varchar(120)
,`pax` decimal(10,2)
,`pickup` varchar(255)
,`dropoff` varchar(255)
,`vehicle_reg_no` varchar(200)
,`booking_type` enum('admin','personal')
,`driver_name` varchar(120)
,`status` enum('pending','awaiting_driver','accepted','rejected','cancelled','in_progress','completed')
,`driver_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_driver_current_vehicle`
-- (See below for the actual view)
--
CREATE TABLE `v_driver_current_vehicle` (
`driver_account_id` int(10) unsigned
,`driver_name` varchar(120)
,`v_id` int(10) unsigned
,`v_name` varchar(200)
,`v_reg_no` varchar(200)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_driver_daily_time`
-- (See below for the actual view)
--
CREATE TABLE `v_driver_daily_time` (
`driver_id` int(10) unsigned
,`service_date` date
,`time_in` datetime
,`time_out` datetime
,`total_seconds` decimal(42,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_fleet_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_fleet_summary` (
`total_vehicles` bigint(21)
,`vehicles_available` bigint(21)
,`vehicles_in_use` bigint(21)
,`vehicles_maintenance` bigint(21)
,`vehicles_inactive` bigint(21)
,`trips_today` bigint(21)
,`trips_in_progress` bigint(21)
,`drivers_active_today` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_trips_in_progress_live`
-- (See below for the actual view)
--
CREATE TABLE `v_trips_in_progress_live` (
`trips_in_progress_live` bigint(21)
,`drivers_active_live` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_trip_history`
-- (See below for the actual view)
--
CREATE TABLE `v_trip_history` (
`booking_id` int(10) unsigned
,`booking_type` enum('admin','personal')
,`created_by` int(10) unsigned
,`driver_id` int(10) unsigned
,`vehicle_id` int(10) unsigned
,`pax` tinyint(3) unsigned
,`contact_name` varchar(120)
,`contact_phone` varchar(32)
,`pickup_point` varchar(255)
,`dropoff_point` varchar(255)
,`scheduled_start_at` datetime
,`scheduled_end_at` datetime
,`status` enum('pending','awaiting_driver','accepted','rejected','cancelled','in_progress','completed')
,`payment_status` enum('unpaid','paid','partial')
,`notes` text
,`created_at` datetime
,`updated_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vehicle_current_driver`
-- (See below for the actual view)
--
CREATE TABLE `v_vehicle_current_driver` (
`v_id` int(10) unsigned
,`driver_account_id` int(10) unsigned
,`driver_name` varchar(120)
,`add_driver_id` int(10) unsigned
,`add_driver_fname` varchar(50)
,`add_driver_lname` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vehicle_daily_metrics`
-- (See below for the actual view)
--
CREATE TABLE `v_vehicle_daily_metrics` (
`vehicle_id` int(10) unsigned
,`service_date` date
,`trips` bigint(21)
,`distance_km` decimal(32,2)
,`fuel_used_liters` decimal(32,2)
,`odo_start_km` decimal(10,1)
,`odo_end_km` decimal(10,1)
,`duration_seconds` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vehicle_display`
-- (See below for the actual view)
--
CREATE TABLE `v_vehicle_display` (
`v_id` int(10) unsigned
,`display_name` varchar(221)
,`v_reg_no` varchar(200)
,`v_category` varchar(200)
,`color` varchar(50)
,`v_dpic` varchar(200)
,`make_id` int(11)
,`model_id` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `v_booking_grid`
--
DROP TABLE IF EXISTS `v_booking_grid`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_booking_grid`  AS SELECT `b`.`id` AS `booking_id`, `b`.`scheduled_start_at` AS `scheduled_at`, `b`.`created_at` AS `created_at`, coalesce(nullif(`b`.`contact_name`,''),'') AS `client_name`, cast(`b`.`pax` as decimal(10,2)) AS `pax`, `b`.`pickup_point` AS `pickup`, `b`.`dropoff_point` AS `dropoff`, `v`.`v_reg_no` AS `vehicle_reg_no`, `b`.`booking_type` AS `booking_type`, `a`.`name` AS `driver_name`, `b`.`status` AS `status`, `b`.`driver_id` AS `driver_id` FROM ((`bookings` `b` left join `accounts` `a` on(`a`.`id` = `b`.`driver_id`)) left join `tms_vehicle` `v` on(`v`.`v_id` = `b`.`vehicle_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_driver_current_vehicle`
--
DROP TABLE IF EXISTS `v_driver_current_vehicle`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_driver_current_vehicle`  AS SELECT `a`.`id` AS `driver_account_id`, `a`.`name` AS `driver_name`, `v`.`v_id` AS `v_id`, `v`.`v_name` AS `v_name`, `v`.`v_reg_no` AS `v_reg_no` FROM ((`accounts` `a` left join `vehicle_assignments` `va` on(`va`.`driver_id` = `a`.`id` and `va`.`end_at` is null)) left join `tms_vehicle` `v` on(`v`.`v_id` = `va`.`vehicle_id`)) WHERE `a`.`role` = 'driver' ;

-- --------------------------------------------------------

--
-- Structure for view `v_driver_daily_time`
--
DROP TABLE IF EXISTS `v_driver_daily_time`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_driver_daily_time`  AS SELECT `br`.`driver_id` AS `driver_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) AS `service_date`, min(`br`.`pickup_button_at`) AS `time_in`, max(`br`.`dropoff_button_at`) AS `time_out`, sum(coalesce(`br`.`duration_seconds`,case when `br`.`pickup_button_at` is not null and `br`.`dropoff_button_at` is not null then timestampdiff(SECOND,`br`.`pickup_button_at`,`br`.`dropoff_button_at`) else 0 end)) AS `total_seconds` FROM `booking_runs` AS `br` WHERE `br`.`driver_id` is not null GROUP BY `br`.`driver_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) ;

-- --------------------------------------------------------

--
-- Structure for view `v_fleet_summary`
--
DROP TABLE IF EXISTS `v_fleet_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_fleet_summary`  AS SELECT (select count(0) from `tms_vehicle`) AS `total_vehicles`, (select count(0) from `tms_vehicle` where lcase(`tms_vehicle`.`v_status`) = 'available') AS `vehicles_available`, (select count(0) from `tms_vehicle` where lcase(`tms_vehicle`.`v_status`) in ('in use','in_use')) AS `vehicles_in_use`, (select count(0) from `tms_vehicle` where lcase(`tms_vehicle`.`v_status`) = 'maintenance') AS `vehicles_maintenance`, (select count(0) from `tms_vehicle` where lcase(`tms_vehicle`.`v_status`) = 'inactive') AS `vehicles_inactive`, (select count(0) from `bookings` where cast(`bookings`.`scheduled_start_at` as date) = curdate()) AS `trips_today`, (select count(distinct `br`.`booking_id`) from `booking_runs` `br` where `br`.`pickup_button_at` is not null and `br`.`dropoff_button_at` is null) AS `trips_in_progress`, (select count(distinct `b`.`driver_id`) from `bookings` `b` where `b`.`driver_id` is not null and cast(`b`.`scheduled_start_at` as date) = curdate()) AS `drivers_active_today` ;

-- --------------------------------------------------------

--
-- Structure for view `v_trips_in_progress_live`
--
DROP TABLE IF EXISTS `v_trips_in_progress_live`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_trips_in_progress_live`  AS SELECT count(distinct `br`.`booking_id`) AS `trips_in_progress_live`, count(distinct `br`.`driver_id`) AS `drivers_active_live` FROM `booking_runs` AS `br` WHERE `br`.`pickup_button_at` is not null AND `br`.`dropoff_button_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_trip_history`
--
DROP TABLE IF EXISTS `v_trip_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_trip_history`  AS SELECT `b`.`id` AS `booking_id`, `b`.`booking_type` AS `booking_type`, `b`.`created_by` AS `created_by`, `b`.`driver_id` AS `driver_id`, `b`.`vehicle_id` AS `vehicle_id`, `b`.`pax` AS `pax`, `b`.`contact_name` AS `contact_name`, `b`.`contact_phone` AS `contact_phone`, `b`.`pickup_point` AS `pickup_point`, `b`.`dropoff_point` AS `dropoff_point`, `b`.`scheduled_start_at` AS `scheduled_start_at`, `b`.`scheduled_end_at` AS `scheduled_end_at`, `b`.`status` AS `status`, `b`.`payment_status` AS `payment_status`, `b`.`notes` AS `notes`, `b`.`created_at` AS `created_at`, `b`.`updated_at` AS `updated_at` FROM `bookings` AS `b` ;

-- --------------------------------------------------------

--
-- Structure for view `v_vehicle_current_driver`
--
DROP TABLE IF EXISTS `v_vehicle_current_driver`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vehicle_current_driver`  AS SELECT `v`.`v_id` AS `v_id`, `va`.`driver_id` AS `driver_account_id`, `a`.`name` AS `driver_name`, coalesce(`ad_by_email`.`d_u_id`,`ad_by_legacy`.`d_u_id`) AS `add_driver_id`, coalesce(`ad_by_email`.`u_fname`,`ad_by_legacy`.`u_fname`) AS `add_driver_fname`, coalesce(`ad_by_email`.`u_lname`,`ad_by_legacy`.`u_lname`) AS `add_driver_lname` FROM ((((`tms_vehicle` `v` left join `vehicle_assignments` `va` on(`va`.`vehicle_id` = `v`.`v_id` and `va`.`end_at` is null)) left join `accounts` `a` on(`a`.`id` = `va`.`driver_id`)) left join `tms_user_add_driver` `ad_by_email` on(`a`.`email` is not null and `ad_by_email`.`deleted_at` is null and `ad_by_email`.`u_email` <> '' and lcase(`ad_by_email`.`u_email`) = lcase(`a`.`email`))) left join `tms_user_add_driver` `ad_by_legacy` on(`ad_by_legacy`.`deleted_at` is null and (`ad_by_legacy`.`d_u_id` = `v`.`default_driver_id` or `ad_by_legacy`.`d_u_id` = `v`.`driver_user_id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_vehicle_daily_metrics`
--
DROP TABLE IF EXISTS `v_vehicle_daily_metrics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vehicle_daily_metrics`  AS SELECT `br`.`vehicle_id` AS `vehicle_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) AS `service_date`, count(0) AS `trips`, sum(coalesce(`br`.`distance_km`,0)) AS `distance_km`, sum(coalesce(`br`.`fuel_used_liters`,0)) AS `fuel_used_liters`, min(`br`.`odo_start_km`) AS `odo_start_km`, max(`br`.`odo_end_km`) AS `odo_end_km`, sum(coalesce(`br`.`duration_seconds`,0)) AS `duration_seconds` FROM `booking_runs` AS `br` WHERE `br`.`vehicle_id` is not null GROUP BY `br`.`vehicle_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) ;

-- --------------------------------------------------------

--
-- Structure for view `v_vehicle_display`
--
DROP TABLE IF EXISTS `v_vehicle_display`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vehicle_display`  AS SELECT `v`.`v_id` AS `v_id`, coalesce(nullif(`v`.`v_name`,''),concat(`m`.`name`,' ',`mo`.`name`)) AS `display_name`, `v`.`v_reg_no` AS `v_reg_no`, `v`.`v_category` AS `v_category`, `v`.`color` AS `color`, `v`.`v_dpic` AS `v_dpic`, `v`.`make_id` AS `make_id`, `v`.`model_id` AS `model_id` FROM ((`tms_vehicle` `v` left join `tms_vehicle_makes` `m` on(`m`.`id` = `v`.`make_id`)) left join `tms_vehicle_models` `mo` on(`mo`.`id` = `v`.`model_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_events`
--
ALTER TABLE `booking_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_offers`
--
ALTER TABLE `booking_offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_profile`
--
ALTER TABLE `driver_profile`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `obd_logs`
--
ALTER TABLE `obd_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `telemetry_alerts`
--
ALTER TABLE `telemetry_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `telemetry_samples`
--
ALTER TABLE `telemetry_samples`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tms_user`
--
ALTER TABLE `tms_user`
  ADD PRIMARY KEY (`u_id`);

--
-- Indexes for table `tms_user_add_driver`
--
ALTER TABLE `tms_user_add_driver`
  ADD PRIMARY KEY (`d_u_id`),
  ADD KEY `idx_tuad_email` (`u_email`(190)),
  ADD KEY `idx_tuad_did` (`d_u_id`);

--
-- Indexes for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  ADD PRIMARY KEY (`v_id`),
  ADD KEY `fk_vehicle_make` (`make_id`),
  ADD KEY `fk_vehicle_model` (`model_id`);

--
-- Indexes for table `tms_vehicle_categories`
--
ALTER TABLE `tms_vehicle_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_name` (`name`);

--
-- Indexes for table `tms_vehicle_makes`
--
ALTER TABLE `tms_vehicle_makes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `tms_vehicle_models`
--
ALTER TABLE `tms_vehicle_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_make_model` (`make_id`,`name`);

--
-- Indexes for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_va_active` (`vehicle_id`,`end_at`),
  ADD KEY `idx_va_driver_active` (`driver_id`,`end_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_events`
--
ALTER TABLE `booking_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `booking_offers`
--
ALTER TABLE `booking_offers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `obd_logs`
--
ALTER TABLE `obd_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=252;

--
-- AUTO_INCREMENT for table `telemetry_alerts`
--
ALTER TABLE `telemetry_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `telemetry_samples`
--
ALTER TABLE `telemetry_samples`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_user`
--
ALTER TABLE `tms_user`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tms_user_add_driver`
--
ALTER TABLE `tms_user_add_driver`
  MODIFY `d_u_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  MODIFY `v_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tms_vehicle_categories`
--
ALTER TABLE `tms_vehicle_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tms_vehicle_makes`
--
ALTER TABLE `tms_vehicle_makes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tms_vehicle_models`
--
ALTER TABLE `tms_vehicle_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_profile`
--
ALTER TABLE `driver_profile`
  ADD CONSTRAINT `fk_driver_profile_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  ADD CONSTRAINT `fk_vehicle_make` FOREIGN KEY (`make_id`) REFERENCES `tms_vehicle_makes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vehicle_model` FOREIGN KEY (`model_id`) REFERENCES `tms_vehicle_models` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tms_vehicle_models`
--
ALTER TABLE `tms_vehicle_models`
  ADD CONSTRAINT `fk_model_make` FOREIGN KEY (`make_id`) REFERENCES `tms_vehicle_makes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_models_make` FOREIGN KEY (`make_id`) REFERENCES `tms_vehicle_makes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

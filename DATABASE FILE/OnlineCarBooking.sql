-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2025 at 10:22 AM
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
(2, 'driver', 'Shane Lopez', 'shaaane@mail.com', '$2y$10$MWD3iKYEN5eQOz6HKPkGV.jSQ.s4nIBgu39NRqVFKW4z.ppsMem7G', NULL, 1, '2025-09-12 20:14:29', '2025-09-13 19:58:42');

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
(1, 'admin', 1, NULL, NULL, NULL, 3, NULL, '+639171234567', '100 Main St, Town', 'Airport Terminal 1', NULL, NULL, NULL, NULL, '2025-08-12 14:00:00', NULL, 'completed', 'unpaid', NULL, '2025-09-12 20:14:29', '2025-09-15 15:41:38');

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
(6, 1, 1, 'admin', 'complete_trip', '[]', '2025-09-15 15:41:38');

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
  `hired_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_profile`
--

INSERT INTO `driver_profile` (`account_id`, `license_no`, `address`, `notes`, `current_status`, `hired_at`) VALUES
(2, '123', 'taga san fernando, pampanga', NULL, 'available', NULL);

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
(1, 2, 'admin', NULL, NULL, 3, '100 Main St, Town', 'Airport Terminal 1', NULL, NULL, NULL, NULL, '+639171234567', 3, '2025-08-12 14:00:00', '2025-09-12 20:14:29', 'pending', 'unpaid', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tms_driver_report`
--

CREATE TABLE `tms_driver_report` (
  `report_id` int(11) NOT NULL,
  `driver_id` int(10) UNSIGNED NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
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
  `u_pwd` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tms_user_add_driver`
--

INSERT INTO `tms_user_add_driver` (`d_u_id`, `u_id`, `u_fname`, `u_lname`, `u_phone`, `u_addr`, `u_car_type`, `u_car_regno`, `u_car_bookdate`, `u_car_book_status`, `u_category`, `u_email`, `u_pwd`, `createdat`, `is_archived`) VALUES
(8, 0, 'Shane', 'Lopez', '09446872447', 'taga san fernando, pampanga', 'Bus', '123', '', 'Available', 'Driver', 'shaaane@mail.com', '', '2025-09-12', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tms_vehicle`
--

CREATE TABLE `tms_vehicle` (
  `v_id` int(11) NOT NULL,
  `v_name` varchar(200) NOT NULL,
  `v_reg_no` varchar(200) NOT NULL,
  `v_pass_no` varchar(200) NOT NULL,
  `v_driver` varchar(200) NOT NULL,
  `v_category` varchar(200) NOT NULL,
  `driver_user_id` int(11) DEFAULT NULL,
  `v_dpic` varchar(200) NOT NULL,
  `v_status` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
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
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `name`, `plate_no`, `category`, `seat_capacity`, `status`, `picture`, `created_at`, `updated_at`) VALUES
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

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_appointments_calendar`
-- (See below for the actual view)
--
CREATE TABLE `v_appointments_calendar` (
`booking_id` int(10) unsigned
,`title` text
,`start_at` datetime
,`end_at` datetime
,`status` enum('pending','awaiting_driver','accepted','rejected','cancelled','in_progress','completed')
,`driver_id` int(10) unsigned
,`vehicle_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_booking_grid`
-- (See below for the actual view)
--
CREATE TABLE `v_booking_grid` (
`booking_id` decimal(10,0)
,`scheduled_at` datetime
,`created_at` datetime
,`client_name` varchar(401)
,`pax` double
,`pickup` varchar(255)
,`dropoff` varchar(255)
,`vehicle_reg_no` varchar(200)
,`booking_type` varchar(8)
,`driver_name` longtext
,`status` varchar(15)
,`driver_id` decimal(10,0)
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
,`total_seconds` decimal(32,0)
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
-- Structure for view `v_appointments_calendar`
--
DROP TABLE IF EXISTS `v_appointments_calendar`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_appointments_calendar`  AS SELECT `b`.`id` AS `booking_id`, concat(ucase(`b`.`booking_type`),' • ',`b`.`pickup_point`,' → ',`b`.`dropoff_point`) AS `title`, `b`.`scheduled_start_at` AS `start_at`, `b`.`scheduled_end_at` AS `end_at`, `b`.`status` AS `status`, `b`.`driver_id` AS `driver_id`, `b`.`vehicle_id` AS `vehicle_id` FROM `bookings` AS `b` ;

-- --------------------------------------------------------

--
-- Structure for view `v_booking_grid`
--
DROP TABLE IF EXISTS `v_booking_grid`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_booking_grid`  AS SELECT `b`.`id` AS `booking_id`, `b`.`scheduled_start_at` AS `scheduled_at`, `b`.`created_at` AS `created_at`, coalesce(`b`.`contact_name`,'') AS `client_name`, `b`.`pax` AS `pax`, `b`.`pickup_point` AS `pickup`, `b`.`dropoff_point` AS `dropoff`, `v`.`plate_no` AS `vehicle_reg_no`, `b`.`booking_type` AS `booking_type`, (select `a`.`name` from `accounts` `a` where `a`.`id` = `b`.`driver_id`) AS `driver_name`, `b`.`status` AS `status`, `b`.`driver_id` AS `driver_id` FROM (`bookings` `b` left join `vehicles` `v` on(`v`.`id` = `b`.`vehicle_id`))union all select `u`.`u_id` AS `booking_id`,str_to_date(concat(nullif(`u`.`u_car_date`,''),' ',nullif(`u`.`u_car_time`,'')),'%Y-%m-%d %H:%i:%s') AS `scheduled_at`,case when `u`.`u_car_createdat` regexp '^[0-9]+$' then from_unixtime(`u`.`u_car_createdat`) else NULL end AS `created_at`,trim(concat(coalesce(`u`.`u_fname`,''),' ',coalesce(`u`.`u_lname`,''))) AS `client_name`,nullif(`u`.`u_car_pax`,'') + 0 AS `pax`,`u`.`u_car_pickup` AS `pickup`,`u`.`u_car_destination` AS `dropoff`,`u`.`u_car_regno` AS `vehicle_reg_no`,'admin' AS `booking_type`,`u`.`u_car_driver` AS `driver_name`,case when lcase(`u`.`u_car_book_status`) like '%approved%' or lcase(`u`.`u_car_book_status`) like '%available%' then 'accepted' when lcase(`u`.`u_car_book_status`) like '%on trip%' or lcase(`u`.`u_car_book_status`) like '%in service%' then 'in_progress' when lcase(`u`.`u_car_book_status`) like '%cancel%' then 'cancelled' when lcase(`u`.`u_car_book_status`) like '%complete%' then 'completed' else 'pending' end AS `status`,NULL AS `driver_id` from `tms_user` `u`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_driver_daily_time`
--
DROP TABLE IF EXISTS `v_driver_daily_time`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_driver_daily_time`  AS SELECT `br`.`driver_id` AS `driver_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) AS `service_date`, min(`br`.`pickup_button_at`) AS `time_in`, max(`br`.`dropoff_button_at`) AS `time_out`, sum(`br`.`duration_seconds`) AS `total_seconds` FROM `booking_runs` AS `br` GROUP BY `br`.`driver_id`, cast(coalesce(`br`.`pickup_button_at`,`br`.`dropoff_button_at`) as date) ;

-- --------------------------------------------------------

--
-- Structure for view `v_fleet_summary`
--
DROP TABLE IF EXISTS `v_fleet_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_fleet_summary`  AS SELECT (select count(0) from `vehicles`) AS `total_vehicles`, (select count(0) from `vehicles` where `vehicles`.`status` = 'available') AS `vehicles_available`, (select count(0) from `vehicles` where `vehicles`.`status` = 'in_use') AS `vehicles_in_use`, (select count(0) from `vehicles` where `vehicles`.`status` = 'maintenance') AS `vehicles_maintenance`, (select count(0) from `vehicles` where `vehicles`.`status` = 'inactive') AS `vehicles_inactive`, (select count(0) from `bookings` where cast(`bookings`.`scheduled_start_at` as date) = curdate()) AS `trips_today`, (select count(0) from `bookings` where `bookings`.`status` = 'in_progress') AS `trips_in_progress`, (select count(distinct `bookings`.`driver_id`) from `bookings` where cast(`bookings`.`scheduled_start_at` as date) = curdate() and `bookings`.`status` in ('accepted','in_progress','completed')) AS `drivers_active_today` ;

-- --------------------------------------------------------

--
-- Structure for view `v_trip_history`
--
DROP TABLE IF EXISTS `v_trip_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_trip_history`  AS SELECT `b`.`id` AS `booking_id`, `b`.`booking_type` AS `booking_type`, `b`.`created_by` AS `created_by`, `b`.`driver_id` AS `driver_id`, `b`.`vehicle_id` AS `vehicle_id`, `b`.`pax` AS `pax`, `b`.`contact_name` AS `contact_name`, `b`.`contact_phone` AS `contact_phone`, `b`.`pickup_point` AS `pickup_point`, `b`.`dropoff_point` AS `dropoff_point`, `b`.`scheduled_start_at` AS `scheduled_start_at`, `b`.`scheduled_end_at` AS `scheduled_end_at`, `b`.`status` AS `status`, `b`.`payment_status` AS `payment_status`, `b`.`notes` AS `notes`, `b`.`created_at` AS `created_at`, `b`.`updated_at` AS `updated_at` FROM `bookings` AS `b` WHERE `b`.`status` in ('completed','cancelled') ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_accounts_email` (`email`);

--
-- Indexes for table `auth_logins`
--
ALTER TABLE `auth_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_account` (`account_id`,`login_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_book_driver` (`driver_id`,`status`,`scheduled_start_at`),
  ADD KEY `idx_book_vehicle` (`vehicle_id`,`status`,`scheduled_start_at`),
  ADD KEY `idx_book_creator` (`created_by`,`booking_type`),
  ADD KEY `idx_book_status_time` (`status`,`scheduled_start_at`),
  ADD KEY `fk_book_client` (`client_id`);

--
-- Indexes for table `booking_events`
--
ALTER TABLE `booking_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_be_booking` (`booking_id`,`created_at`),
  ADD KEY `fk_be_actor` (`actor_id`);

--
-- Indexes for table `booking_offers`
--
ALTER TABLE `booking_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_offer` (`booking_id`,`driver_id`),
  ADD KEY `idx_offer_driver` (`driver_id`,`response`),
  ADD KEY `fk_offer_offered_by` (`offered_by`),
  ADD KEY `fk_offer_verified_by` (`verified_by`);

--
-- Indexes for table `booking_runs`
--
ALTER TABLE `booking_runs`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `idx_run_vehicle` (`vehicle_id`),
  ADD KEY `idx_run_driver` (`driver_id`);

--
-- Indexes for table `driver_profile`
--
ALTER TABLE `driver_profile`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_recipient` (`recipient_id`,`is_read`,`created_at`);

--
-- Indexes for table `telemetry_alerts`
--
ALTER TABLE `telemetry_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ta_vehicle` (`vehicle_id`,`created_at`),
  ADD KEY `fk_ta_sample` (`sample_id`),
  ADD KEY `fk_ta_driver` (`driver_id`),
  ADD KEY `fk_ta_booking` (`booking_id`);

--
-- Indexes for table `telemetry_samples`
--
ALTER TABLE `telemetry_samples`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ts_vehicle_time` (`vehicle_id`,`recorded_at`),
  ADD KEY `idx_ts_booking` (`booking_id`),
  ADD KEY `fk_ts_driver` (`driver_id`);

--
-- Indexes for table `tms_admin`
--
ALTER TABLE `tms_admin`
  ADD PRIMARY KEY (`a_id`);

--
-- Indexes for table `tms_audit_log`
--
ALTER TABLE `tms_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tms_bookings`
--
ALTER TABLE `tms_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_driver_status_time` (`driver_id`,`status`,`scheduled_at`),
  ADD KEY `idx_type_status_time` (`booking_type`,`status`,`scheduled_at`),
  ADD KEY `idx_created_by` (`created_by_driver_id`);

--
-- Indexes for table `tms_driver_report`
--
ALTER TABLE `tms_driver_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_trip_date` (`trip_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_driver` (`driver_id`),
  ADD KEY `fk_report_vehicle` (`vehicle_id`);

--
-- Indexes for table `tms_feedback`
--
ALTER TABLE `tms_feedback`
  ADD PRIMARY KEY (`f_id`);

--
-- Indexes for table `tms_pwd_resets`
--
ALTER TABLE `tms_pwd_resets`
  ADD PRIMARY KEY (`r_id`);

--
-- Indexes for table `tms_report_media`
--
ALTER TABLE `tms_report_media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `fk_media_report` (`report_id`);

--
-- Indexes for table `tms_syslogs`
--
ALTER TABLE `tms_syslogs`
  ADD PRIMARY KEY (`l_id`);

--
-- Indexes for table `tms_user`
--
ALTER TABLE `tms_user`
  ADD PRIMARY KEY (`u_id`);

--
-- Indexes for table `tms_user_add_driver`
--
ALTER TABLE `tms_user_add_driver`
  ADD PRIMARY KEY (`d_u_id`);

--
-- Indexes for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  ADD PRIMARY KEY (`v_id`),
  ADD UNIQUE KEY `ux_vehicle_driver_one_to_one` (`driver_user_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicles_plate` (`plate_no`);

--
-- Indexes for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_va_vehicle` (`vehicle_id`,`start_at`,`end_at`),
  ADD KEY `idx_va_driver` (`driver_id`,`start_at`,`end_at`),
  ADD KEY `fk_va_admin` (`assigned_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `auth_logins`
--
ALTER TABLE `auth_logins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_events`
--
ALTER TABLE `booking_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `booking_offers`
--
ALTER TABLE `booking_offers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `tms_admin`
--
ALTER TABLE `tms_admin`
  MODIFY `a_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tms_audit_log`
--
ALTER TABLE `tms_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_bookings`
--
ALTER TABLE `tms_bookings`
  MODIFY `booking_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tms_driver_report`
--
ALTER TABLE `tms_driver_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_feedback`
--
ALTER TABLE `tms_feedback`
  MODIFY `f_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_pwd_resets`
--
ALTER TABLE `tms_pwd_resets`
  MODIFY `r_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_report_media`
--
ALTER TABLE `tms_report_media`
  MODIFY `media_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_syslogs`
--
ALTER TABLE `tms_syslogs`
  MODIFY `l_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_user`
--
ALTER TABLE `tms_user`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tms_user_add_driver`
--
ALTER TABLE `tms_user_add_driver`
  MODIFY `d_u_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  MODIFY `v_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_logins`
--
ALTER TABLE `auth_logins`
  ADD CONSTRAINT `fk_auth_login_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_book_client` FOREIGN KEY (`client_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_creator` FOREIGN KEY (`created_by`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `booking_events`
--
ALTER TABLE `booking_events`
  ADD CONSTRAINT `fk_be_actor` FOREIGN KEY (`actor_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_be_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_offers`
--
ALTER TABLE `booking_offers`
  ADD CONSTRAINT `fk_offer_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_offer_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_offer_offered_by` FOREIGN KEY (`offered_by`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_offer_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `booking_runs`
--
ALTER TABLE `booking_runs`
  ADD CONSTRAINT `fk_run_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_run_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_run_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `driver_profile`
--
ALTER TABLE `driver_profile`
  ADD CONSTRAINT `fk_driver_profile_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `telemetry_alerts`
--
ALTER TABLE `telemetry_alerts`
  ADD CONSTRAINT `fk_ta_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ta_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ta_sample` FOREIGN KEY (`sample_id`) REFERENCES `telemetry_samples` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ta_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `telemetry_samples`
--
ALTER TABLE `telemetry_samples`
  ADD CONSTRAINT `fk_ts_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ts_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ts_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tms_bookings`
--
ALTER TABLE `tms_bookings`
  ADD CONSTRAINT `fk_bookings_driver_creator` FOREIGN KEY (`created_by_driver_id`) REFERENCES `tms_user_add_driver` (`d_u_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tms_driver_report`
--
ALTER TABLE `tms_driver_report`
  ADD CONSTRAINT `fk_report_driver` FOREIGN KEY (`driver_id`) REFERENCES `tms_user_add_driver` (`d_u_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_report_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `tms_vehicle` (`v_id`) ON DELETE SET NULL;

--
-- Constraints for table `tms_report_media`
--
ALTER TABLE `tms_report_media`
  ADD CONSTRAINT `fk_media_report` FOREIGN KEY (`report_id`) REFERENCES `tms_driver_report` (`report_id`) ON DELETE CASCADE;

--
-- Constraints for table `tms_vehicle`
--
ALTER TABLE `tms_vehicle`
  ADD CONSTRAINT `fk_vehicle_driver` FOREIGN KEY (`driver_user_id`) REFERENCES `tms_user` (`u_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vehicle_assignments`
--
ALTER TABLE `vehicle_assignments`
  ADD CONSTRAINT `fk_va_admin` FOREIGN KEY (`assigned_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_va_driver` FOREIGN KEY (`driver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_va_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

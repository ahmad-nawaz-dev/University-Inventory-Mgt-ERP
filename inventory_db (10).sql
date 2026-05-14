-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 02:38 PM
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
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset_tag` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(12,2) DEFAULT NULL,
  `funding_source` varchar(255) DEFAULT NULL,
  `current_value` decimal(12,2) DEFAULT NULL,
  `salvage_value` decimal(12,2) DEFAULT 0.00,
  `useful_life_years` int(11) DEFAULT 5,
  `status` varchar(50) DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `faculty` varchar(255) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `class_location` varchar(255) DEFAULT NULL,
  `condition_status` enum('good','fair','poor') DEFAULT 'good',
  `purchase_request_id` int(11) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_tag`, `name`, `serial_number`, `model`, `brand`, `category_id`, `department`, `purchase_date`, `purchase_cost`, `funding_source`, `current_value`, `salvage_value`, `useful_life_years`, `status`, `created_at`, `updated_at`, `faculty`, `assigned_to_user_id`, `class_location`, `condition_status`, `purchase_request_id`, `assigned_date`, `location_id`) VALUES
(82, 'PRJ-001', 'Epson Projector', '', '', '', 4, 'English', '2024-06-18', 600.00, '', 600.00, 0.00, 5, 'allocated', '2026-02-12 12:55:52', '2026-03-26 05:37:13', 'Engineering', 12, '', 'good', NULL, NULL, NULL),
(86, 'COM-003', 'Apple iMac', '', '', '', 2, 'Mathematics', '2024-10-10', 1500.00, '', 1500.00, 0.00, 5, 'allocated', '2026-02-12 12:55:52', '2026-04-08 12:46:46', 'Science', NULL, '3G3', 'good', NULL, NULL, NULL),
(96, 'LAP-001', 'Asus Zenbook 14', 'FS123', 'UX433F', 'Asus', 1, 'English', '2026-03-12', 50000.00, 'HEC Gurant', 50000.00, 0.00, 5, 'allocated', '2026-03-12 09:45:06', '2026-03-19 06:44:11', 'Business Faculty', 12, '3G1', 'good', NULL, NULL, NULL),
(97, 'ELE-001', 'Car', '', 'GLI', 'Toyota', 78, 'Business Administration', '2026-03-17', 7800000.00, 'HEC Gurant', 7800000.00, 0.00, 5, 'in_repair', '2026-03-17 07:01:56', '2026-04-14 12:00:19', '', 14, '', 'good', NULL, NULL, NULL),
(98, 'DES-001', 'Board Marker', '', '', '', 2, 'Business Administration', '2026-03-18', 30.00, '', 30.00, 0.00, 5, 'allocated', '2026-03-18 10:43:12', '2026-03-18 10:44:12', '', NULL, '', 'good', 10, NULL, NULL),
(99, 'DES-002', 'HDMI Cable', '', '', '', 2, '', '2026-03-19', 3000.00, '', 3000.00, 0.00, 5, 'in_stock', '2026-03-19 06:50:09', '2026-04-10 11:41:28', '', NULL, '', 'good', 9, NULL, NULL),
(100, 'ELE-002', 'Bike', '', '', '', 78, 'Mathematics', '2026-03-19', 150000.00, '', 150000.00, 0.00, 5, 'in_stock', '2026-03-19 07:08:31', '2026-04-14 11:58:56', '', NULL, '', 'good', 11, NULL, NULL),
(101, 'ELE-003', 'Mobile phone', '', '', '', 78, 'Business Administration', '2026-04-06', 44000.00, '', 44000.00, 0.00, 5, 'disposed', '2026-04-06 04:18:51', '2026-04-07 12:54:11', '', NULL, '', 'good', 12, NULL, NULL),
(103, 'ELE-004', 'Generator', '', '', '', 78, 'Business Administration', '2026-04-14', 100000.00, '', 100000.00, 0.00, 5, 'allocated', '2026-04-14 11:25:48', '2026-04-14 11:25:48', '', NULL, '', 'good', 20, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `asset_availability`
--

CREATE TABLE `asset_availability` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `available_from_time` time DEFAULT '00:00:00',
  `available_to_time` time DEFAULT '23:59:59',
  `is_available` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_categories`
--

CREATE TABLE `asset_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_categories`
--

INSERT INTO `asset_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Laptops', 'Portable computers for faculty and staff', '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(2, 'Desktop Computers and Pcs', 'Stationary computers for offices', '2026-02-10 16:25:27', '2026-03-13 03:57:48'),
(3, 'Printers', 'Office printing equipment', '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(4, 'Projectors', 'Audiovisual presentation equipment', '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(11, 'Furniture', 'Desks, chairs, and office furniture', '2026-02-12 12:46:36', '2026-02-12 12:46:36'),
(12, 'Laboratory Equipment', 'Scientific instruments and apparatus', '2026-02-12 12:46:36', '2026-02-12 12:46:36'),
(13, 'Network Equipment', 'Routers, switches, and networking hardware', '2026-02-12 12:46:36', '2026-02-12 12:46:36'),
(78, 'Electronics', 'This is the description of Electronics category which should have multiple things in it', '2026-03-12 09:36:08', '2026-03-12 09:37:09'),
(81, 'NNN', 'nnn', '2026-04-14 09:45:08', '2026-04-14 09:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `asset_disposals`
--

CREATE TABLE `asset_disposals` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `disposal_reason` enum('end_of_life','damage','obsolescence','theft','loss','upgrade','budget_cut') NOT NULL,
  `disposal_method` enum('sale','auction','donation','recycling','scrapped','transfer') DEFAULT 'sale',
  `estimated_value` decimal(10,2) DEFAULT 0.00,
  `disposal_date` date DEFAULT NULL,
  `disposal_cost` decimal(10,2) DEFAULT 0.00,
  `disposal_notes` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by_level1` int(11) DEFAULT NULL,
  `approved_by_level2` int(11) DEFAULT NULL,
  `approved_by_level3` int(11) DEFAULT NULL,
  `status` enum('pending','level1_approved','level2_approved','level3_approved','completed','rejected') DEFAULT 'pending',
  `requested_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_date_level1` timestamp NULL DEFAULT NULL,
  `approved_date_level2` timestamp NULL DEFAULT NULL,
  `approved_date_level3` timestamp NULL DEFAULT NULL,
  `completed_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_disposals`
--

INSERT INTO `asset_disposals` (`id`, `asset_id`, `disposal_reason`, `disposal_method`, `estimated_value`, `disposal_date`, `disposal_cost`, `disposal_notes`, `requested_by`, `approved_by_level1`, `approved_by_level2`, `approved_by_level3`, `status`, `requested_date`, `approved_date_level1`, `approved_date_level2`, `approved_date_level3`, `completed_date`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 82, 'end_of_life', 'donation', 0.00, NULL, 0.00, 'hhukhui', 1, NULL, NULL, NULL, 'rejected', '2026-03-12 10:00:31', NULL, NULL, NULL, NULL, NULL, '2026-03-12 10:00:31', '2026-04-07 12:54:00'),
(2, 101, 'end_of_life', 'sale', 0.00, NULL, 0.00, 'LCD was broken', 1, 1, NULL, NULL, 'completed', '2026-04-07 12:47:04', NULL, NULL, NULL, '2026-04-07 12:54:11', NULL, '2026-04-07 12:47:04', '2026-04-07 12:54:11');

-- --------------------------------------------------------

--
-- Table structure for table `asset_inventory_records`
--

CREATE TABLE `asset_inventory_records` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanned_by` int(11) NOT NULL,
  `status` enum('found','missing','moved','damaged') DEFAULT 'found',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_inventory_sessions`
--

CREATE TABLE `asset_inventory_sessions` (
  `id` int(11) NOT NULL,
  `session_name` varchar(255) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `started_by` int(11) NOT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `total_scanned` int(11) DEFAULT 0,
  `total_missing` int(11) DEFAULT 0,
  `total_found` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_location_history`
--

CREATE TABLE `asset_location_history` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `moved_by` int(11) NOT NULL,
  `moved_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_location_tracking`
--

CREATE TABLE `asset_location_tracking` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `current_location` varchar(255) DEFAULT NULL,
  `previous_location` varchar(255) DEFAULT NULL,
  `scanned_by` int(11) DEFAULT NULL,
  `scan_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `scan_type` enum('check_in','check_out','relocate','verification','missing_report') DEFAULT 'verification',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_location_tracking`
--

INSERT INTO `asset_location_tracking` (`id`, `asset_id`, `qr_code`, `current_location`, `previous_location`, `scanned_by`, `scan_date`, `scan_type`, `latitude`, `longitude`, `ip_address`, `user_agent`, `notes`, `created_at`, `updated_at`) VALUES
(1, 86, NULL, '3G3', NULL, 1, '2026-04-08 12:46:46', 'relocate', NULL, NULL, NULL, NULL, '', '2026-04-08 12:46:46', '2026-04-08 12:46:46'),
(2, 86, NULL, '3G3', NULL, 1, '2026-04-08 12:47:25', 'relocate', NULL, NULL, NULL, NULL, '', '2026-04-08 12:47:25', '2026-04-08 12:47:25'),
(3, 86, NULL, '3G3', NULL, 1, '2026-04-08 12:47:57', 'verification', NULL, NULL, NULL, NULL, '', '2026-04-08 12:47:57', '2026-04-08 12:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance`
--

CREATE TABLE `asset_maintenance` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_type` enum('preventive','corrective','emergency','upgrade') NOT NULL,
  `service_provider` varchar(255) DEFAULT NULL,
  `technician_name` varchar(255) DEFAULT NULL,
  `maintenance_date` date NOT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `maintenance_cost` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `warranty_period_months` int(11) DEFAULT 0,
  `warranty_expiry_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_maintenance`
--

INSERT INTO `asset_maintenance` (`id`, `asset_id`, `maintenance_type`, `service_provider`, `technician_name`, `maintenance_date`, `next_maintenance_date`, `maintenance_cost`, `description`, `status`, `priority`, `warranty_period_months`, `warranty_expiry_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 82, 'emergency', '', 'Ustad Talha', '2026-03-19', '2026-04-11', 20000.00, 'We have to repair this asset as soon as possible', 'completed', 'medium', 0, NULL, 1, '2026-03-18 07:23:30', '2026-03-18 07:24:17'),
(2, 101, 'emergency', 'JNJGHHJKHUI', 'TAlha', '2026-04-06', '2026-04-07', 1000.00, 'ijhijjiokio', 'in_progress', 'low', 0, NULL, 1, '2026-04-06 04:20:50', '2026-04-06 04:21:05'),
(3, 100, 'preventive', 'Honda Motors', 'Ustad Talha', '2026-04-16', '2026-05-14', 1499.98, '...', 'completed', 'medium', 0, NULL, 1, '2026-04-14 11:38:35', '2026-04-14 11:58:56'),
(4, 97, 'emergency', 'Toyota Motors', 'Ustad Talha', '2026-04-16', '2026-04-30', 20000.00, '...', 'in_progress', 'critical', 0, NULL, 1, '2026-04-14 12:00:19', '2026-04-14 12:00:45');

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance_history`
--

CREATE TABLE `asset_maintenance_history` (
  `id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `status_change` enum('pending','in_progress','completed','cancelled') DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_maintenance_history`
--

INSERT INTO `asset_maintenance_history` (`id`, `maintenance_id`, `status_change`, `comments`, `changed_by`, `changed_at`) VALUES
(1, 1, 'pending', 'Maintenance request created', 1, '2026-03-18 07:23:31'),
(2, 1, 'in_progress', '', 1, '2026-03-18 07:23:40'),
(3, 1, 'completed', '', 1, '2026-03-18 07:24:17'),
(4, 2, 'pending', 'Maintenance request created', 1, '2026-04-06 04:20:50'),
(5, 2, 'in_progress', '', 1, '2026-04-06 04:21:05'),
(6, 3, 'pending', 'Maintenance request created', 1, '2026-04-14 11:38:35'),
(7, 3, 'completed', '', 1, '2026-04-14 11:58:56'),
(8, 4, 'pending', 'Maintenance request created', 1, '2026-04-14 12:00:19'),
(9, 4, 'in_progress', '', 14, '2026-04-14 12:00:45');

-- --------------------------------------------------------

--
-- Table structure for table `asset_movement_log`
--

CREATE TABLE `asset_movement_log` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `transfer_id` int(11) DEFAULT NULL,
  `from_department` varchar(255) DEFAULT NULL,
  `to_department` varchar(255) DEFAULT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `movement_type` enum('transfer','allocation','return','relocation') DEFAULT 'transfer',
  `movement_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_movement_log`
--

INSERT INTO `asset_movement_log` (`id`, `asset_id`, `transfer_id`, `from_department`, `to_department`, `from_location`, `to_location`, `from_user_id`, `to_user_id`, `movement_type`, `movement_date`, `notes`, `created_by`, `created_at`) VALUES
(1, 96, 1, 'English', 'Business Administration', NULL, NULL, 11, 7, 'transfer', '2026-03-12 17:15:02', 'Transfer request created', 1, '2026-03-12 17:15:02'),
(2, 82, 4, 'Computer Science', 'English', NULL, NULL, NULL, 12, 'transfer', '2026-03-26 05:36:34', 'Transfer request created', 1, '2026-03-26 05:36:34'),
(3, 98, 5, 'Business Administration', 'Psychology Department', NULL, NULL, 7, 1, 'transfer', '2026-03-26 05:41:34', 'Transfer request created', 1, '2026-03-26 05:41:34'),
(4, 100, 6, 'Business Administration', 'Mathematics', NULL, NULL, 16, NULL, 'transfer', '2026-04-07 11:37:27', 'Transfer request created', 1, '2026-04-07 11:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `asset_reservations`
--

CREATE TABLE `asset_reservations` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `requester_user_id` int(11) NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `reservation_start_datetime` datetime NOT NULL,
  `reservation_end_datetime` datetime NOT NULL,
  `status` enum('pending','approved','rejected','in_use','completed','cancelled') DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_reservations`
--

INSERT INTO `asset_reservations` (`id`, `asset_id`, `requester_user_id`, `department`, `purpose`, `reservation_start_datetime`, `reservation_end_datetime`, `status`, `priority`, `approved_by`, `approved_at`, `rejection_reason`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 96, 1, 'Computer Science', 'Presentation  in class', '2026-03-12 11:58:00', '2026-03-12 12:58:00', 'rejected', 'urgent', NULL, NULL, 'This asset is already booked', 1, '2026-03-12 09:59:18', '2026-03-12 09:59:49'),
(2, 96, 1, 'Psychology Department', 'Very much need', '2026-03-12 12:01:00', '2026-03-12 13:01:00', 'completed', 'high', 1, '2026-03-12 10:01:30', NULL, 1, '2026-03-12 10:01:27', '2026-03-12 10:01:45'),
(3, 99, 1, 'Physics', 'Presentation', '2026-04-07 14:38:00', '2026-04-07 15:38:00', 'completed', 'medium', 1, '2026-04-07 11:39:46', NULL, 1, '2026-04-07 11:39:32', '2026-04-07 11:41:31'),
(4, 99, 1, 'Psychology Department', '.....', '2026-04-07 15:04:00', '2026-04-07 16:04:00', 'completed', 'low', 1, '2026-04-07 12:05:17', NULL, 1, '2026-04-07 12:05:08', '2026-04-07 12:05:57'),
(5, 99, 1, 'Electrical Engineering', '00000', '2026-04-07 15:05:00', '2026-04-07 16:05:00', 'completed', 'low', 1, '2026-04-07 12:33:27', NULL, 1, '2026-04-07 12:33:23', '2026-04-10 11:41:28');

-- --------------------------------------------------------

--
-- Table structure for table `asset_transfers`
--

CREATE TABLE `asset_transfers` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `from_department` varchar(255) DEFAULT NULL,
  `to_department` varchar(255) NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) NOT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `transfer_reason` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by_level1` int(11) DEFAULT NULL,
  `approved_by_level2` int(11) DEFAULT NULL,
  `approved_by_level3` int(11) DEFAULT NULL,
  `status` enum('pending','level1_approved','level2_approved','level3_approved','completed','rejected') DEFAULT 'pending',
  `requested_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_date_level1` timestamp NULL DEFAULT NULL,
  `approved_date_level2` timestamp NULL DEFAULT NULL,
  `approved_date_level3` timestamp NULL DEFAULT NULL,
  `completed_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_transfers`
--

INSERT INTO `asset_transfers` (`id`, `asset_id`, `from_department`, `to_department`, `from_location`, `to_location`, `from_user_id`, `to_user_id`, `transfer_reason`, `requested_by`, `approved_by_level1`, `approved_by_level2`, `approved_by_level3`, `status`, `requested_date`, `approved_date_level1`, `approved_date_level2`, `approved_date_level3`, `completed_date`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 96, 'English', 'Business Administration', NULL, '', 11, 7, 'Urgent base', 1, 1, NULL, NULL, 'level1_approved', '2026-03-12 17:15:02', '2026-03-12 17:15:12', NULL, NULL, NULL, NULL, '2026-03-12 17:15:02', '2026-03-12 17:15:12'),
(4, 82, 'Computer Science', 'English', NULL, '', NULL, 12, 'it\'s very urgent', 1, 1, 1, 1, 'completed', '2026-03-26 05:36:34', '2026-03-26 05:36:53', '2026-03-26 05:37:01', '2026-03-26 05:37:07', '2026-03-26 05:37:13', NULL, '2026-03-26 05:36:34', '2026-03-26 05:37:13'),
(5, 98, 'Business Administration', 'Psychology Department', NULL, '', 7, 1, 'urgent basis', 1, 1, NULL, NULL, 'rejected', '2026-03-26 05:41:34', '2026-04-07 11:53:23', NULL, NULL, NULL, 'Because i don\'t allow it', '2026-03-26 05:41:34', '2026-04-07 11:53:55'),
(6, 100, 'Business Administration', 'Mathematics', NULL, '', 16, NULL, 'Because they need it.', 1, 1, 1, 1, 'completed', '2026-04-07 11:37:26', '2026-04-07 11:37:38', '2026-04-07 11:37:46', '2026-04-07 11:37:54', '2026-04-07 11:38:06', NULL, '2026-04-07 11:37:26', '2026-04-07 11:38:06');

-- --------------------------------------------------------

--
-- Table structure for table `asset_warranties`
--

CREATE TABLE `asset_warranties` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `warranty_provider` varchar(255) DEFAULT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `warranty_expiry_date` date DEFAULT NULL,
  `warranty_terms` text DEFAULT NULL,
  `warranty_cost` decimal(10,2) DEFAULT 0.00,
  `is_extended` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_categories`
--

CREATE TABLE `budget_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_transactions`
--

CREATE TABLE `budget_transactions` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `transaction_type` enum('allocation','expense','refund','adjustment') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_no` varchar(255) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_transactions`
--

INSERT INTO `budget_transactions` (`id`, `budget_id`, `asset_id`, `transaction_type`, `amount`, `description`, `reference_no`, `transaction_date`, `approved_by`, `approved_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 82, 'expense', 100000.00, 'jhuuhj', '', '2026-03-12', 1, '2026-03-12 10:25:35', 'approved', 1, '2026-03-12 10:25:25', '2026-03-19 07:23:13'),
(3, 3, NULL, 'adjustment', 500000.00, 'Second time', '', '2026-04-09', 1, '2026-04-09 11:22:33', 'approved', 1, '2026-04-09 11:22:24', '2026-04-09 11:22:33');

-- --------------------------------------------------------

--
-- Table structure for table `department_budgets`
--

CREATE TABLE `department_budgets` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `budget_year` year(4) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(15,2) DEFAULT 0.00,
  `remaining_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','frozen','closed') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_budgets`
--

INSERT INTO `department_budgets` (`id`, `department_name`, `budget_year`, `allocated_amount`, `spent_amount`, `remaining_amount`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Business Administration', '2026', 500000.00, 100000.00, 400000.00, 'active', 1, '2026-03-12 10:24:18', '2026-04-09 11:32:53'),
(2, 'English', '2026', 50000.00, 0.00, 50000.00, 'active', 1, '2026-03-17 07:23:56', '2026-03-18 11:06:45'),
(3, 'Business Administration', '2027', 500000.00, 0.00, 500000.00, 'active', 1, '2026-04-09 11:20:29', '2026-04-09 11:20:29');

-- --------------------------------------------------------

--
-- Table structure for table `depreciation_records`
--

CREATE TABLE `depreciation_records` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `depreciation_amount` decimal(12,2) DEFAULT NULL,
  `book_value` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposal_auctions`
--

CREATE TABLE `disposal_auctions` (
  `id` int(11) NOT NULL,
  `disposal_id` int(11) NOT NULL,
  `auction_house` varchar(255) DEFAULT NULL,
  `auction_date` date DEFAULT NULL,
  `reserve_price` decimal(10,2) DEFAULT 0.00,
  `final_sale_price` decimal(10,2) DEFAULT 0.00,
  `auction_fee` decimal(10,2) DEFAULT 0.00,
  `auction_notes` text DEFAULT NULL,
  `winning_bidder` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposal_audit_trail`
--

CREATE TABLE `disposal_audit_trail` (
  `id` int(11) NOT NULL,
  `disposal_id` int(11) NOT NULL,
  `action_taken` enum('request_submitted','level1_approved','level2_approved','level3_approved','completed','rejected','sale_recorded','auction_recorded','recycling_recorded') NOT NULL,
  `details` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposal_recycling`
--

CREATE TABLE `disposal_recycling` (
  `id` int(11) NOT NULL,
  `disposal_id` int(11) NOT NULL,
  `recycler_name` varchar(255) DEFAULT NULL,
  `recycler_contact` varchar(255) DEFAULT NULL,
  `recycling_date` date DEFAULT NULL,
  `recycling_fee` decimal(10,2) DEFAULT 0.00,
  `certificate_number` varchar(255) DEFAULT NULL,
  `recycling_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposal_sales`
--

CREATE TABLE `disposal_sales` (
  `id` int(11) NOT NULL,
  `disposal_id` int(11) NOT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_contact` varchar(255) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `sale_date` date DEFAULT NULL,
  `sale_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grn`
--

CREATE TABLE `grn` (
  `id` int(11) NOT NULL,
  `grn_no` varchar(100) DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `inspection_notes` text DEFAULT NULL,
  `item_condition` varchar(100) DEFAULT 'good',
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grn`
--

INSERT INTO `grn` (`id`, `grn_no`, `po_id`, `received_by`, `inspection_notes`, `item_condition`, `received_at`, `created_at`) VALUES
(1, 'GRN-0001', 1, 1, '', 'good', '2026-03-18 07:52:07', '2026-03-18 07:52:07'),
(2, 'GRN-0002', 3, 1, 'This is totaly in good condition', 'good', '2026-03-18 07:54:54', '2026-03-18 07:54:54'),
(3, 'GRN-0003', 4, 16, 'It\'s good', 'good', '2026-03-18 10:43:12', '2026-03-18 10:43:12'),
(4, 'GRN-0004', 5, 9, '', 'good', '2026-03-19 06:50:09', '2026-03-19 06:50:09'),
(5, 'GRN-0005', 6, 1, 'Well Packed', 'good', '2026-03-19 07:08:31', '2026-03-19 07:08:31'),
(6, 'GRN-0006', 7, 1, '', 'good', '2026-04-06 04:18:51', '2026-04-06 04:18:51'),
(7, 'GRN-0007', 8, 1, '', 'good', '2026-04-14 11:25:48', '2026-04-14 11:25:48');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `location_type` enum('central_store','lab','office','classroom','workshop','library') DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `contact_person_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`, `building`, `floor`, `room_number`, `location_type`, `capacity`, `contact_person_id`, `created_at`, `updated_at`) VALUES
(1, '3G3', 'Admin Block 1', '1st', '3G3', 'classroom', 60, 14, '2026-04-08 12:46:20', '2026-04-08 12:46:20');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_no` varchar(100) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_no`, `request_id`, `vendor`, `total_amount`, `expected_delivery_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PO-0001', 2, 'Ahsan Computers', 10000.00, NULL, 'pending', '2026-02-13 11:23:45', '2026-02-13 11:23:45'),
(3, 'PO-0002', 9, 'Bilal Gunj Market', 3000.00, '2026-03-19', 'pending', '2026-03-18 07:54:21', '2026-03-18 07:54:21'),
(4, 'PO-0003', 10, 'City Photo State', 30.00, '2026-03-19', 'pending', '2026-03-18 10:42:35', '2026-03-18 10:42:35'),
(5, 'PO-0004', 9, 'Bilal Electronics', 3000.00, '2026-03-20', 'pending', '2026-03-19 06:49:32', '2026-03-19 06:49:32'),
(6, 'PO-0005', 11, 'Honda Motors Sahiwal', 150000.00, '2026-03-26', 'pending', '2026-03-19 07:07:57', '2026-03-19 07:07:57'),
(7, 'PO-0006', 12, 'Raja Mobile', 44000.00, '2026-04-22', 'pending', '2026-04-06 04:17:40', '2026-04-06 04:17:40'),
(8, 'PO-0007', 20, 'Afzal Electroncs', 100000.00, '2026-04-14', 'pending', '2026-04-14 11:21:20', '2026-04-14 11:21:20');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `request_no` varchar(100) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `estimated_cost` decimal(15,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by_coordinator` int(11) DEFAULT NULL,
  `coordinator_approved_at` timestamp NULL DEFAULT NULL,
  `approved_by_hod` int(11) DEFAULT NULL,
  `hod_approved_at` timestamp NULL DEFAULT NULL,
  `approved_by_dean` int(11) DEFAULT NULL,
  `dean_approved_at` timestamp NULL DEFAULT NULL,
  `deadline_date` date DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `justification` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `request_no`, `item_description`, `department`, `requested_by`, `estimated_cost`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`, `approved_by_coordinator`, `coordinator_approved_at`, `approved_by_hod`, `hod_approved_at`, `approved_by_dean`, `dean_approved_at`, `deadline_date`, `priority`, `justification`) VALUES
(1, 'REQ-001', NULL, 'Computer Science', 1, 0.00, 'pending', NULL, NULL, '2026-02-10 16:25:27', '2026-02-10 16:25:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(2, 'REQ-0001', NULL, 'Computer Science', 1, 0.00, 'approved', NULL, NULL, '2026-02-12 12:55:52', '2026-02-12 12:55:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(3, 'REQ-0002', NULL, 'Business Administration', 1, 0.00, 'rejected', NULL, NULL, '2026-02-12 12:55:52', '2026-04-09 11:14:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(4, 'REQ-0003', NULL, 'Electrical Engineering', 1, 0.00, 'approved', NULL, NULL, '2026-02-12 12:55:52', '2026-02-12 12:55:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(5, 'REQ-0004', NULL, 'Mathematics', 1, 0.00, 'rejected', NULL, NULL, '2026-02-12 12:55:52', '2026-02-12 12:55:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(6, 'REQ-0005', NULL, 'Physics', 1, 0.00, 'approved', NULL, NULL, '2026-02-12 12:55:52', '2026-02-12 12:55:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'medium', NULL),
(7, 'REQ-0006', NULL, 'Business Administration', 8, 0.00, 'rejected', NULL, NULL, '2026-03-12 17:18:49', '2026-04-09 11:14:11', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-19', 'low', 'jjjj'),
(8, 'REQ-0007', NULL, 'Business Administration', 8, 0.00, 'rejected', NULL, NULL, '2026-03-12 17:23:01', '2026-04-09 11:14:05', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-19', 'low', 'jjjj'),
(9, 'REQ-0008', 'HDMI Cable', 'English', 9, 3000.00, 'approved', 1, '2026-03-18 07:53:42', '2026-03-18 07:53:32', '2026-03-18 07:53:42', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20', 'urgent', 'To run projector'),
(10, 'REQ-0009', 'Board Marker', 'Business Administration', 16, 30.00, 'approved', 1, '2026-03-18 10:42:04', '2026-03-18 10:41:51', '2026-03-18 10:42:04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-27', 'medium', 'To do coding on whiteboard'),
(11, 'REQ-0010', 'Bike', 'Business Administration', 14, 150000.00, 'approved', 14, '2026-03-19 07:06:35', '2026-03-19 07:06:24', '2026-03-19 07:06:35', NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-31', 'low', 'To Deliver Exam sheet'),
(12, 'REQ-0011', 'Mobile phone', 'Business Administration', 8, 44000.00, 'approved', 1, '2026-04-06 04:16:38', '2026-04-06 04:16:12', '2026-04-06 04:16:38', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-13', 'low', 'jdqjekjwejfiejfijw'),
(13, 'REQ-0012', 'Projector', 'Business Administration', 16, 10000.00, 'approved', 1, '2026-04-09 12:43:36', '2026-04-09 11:33:59', '2026-04-09 12:43:36', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-16', 'low', 'Very compulsory in class'),
(15, 'REQ-0013', 'UPS', 'Business Administration', 14, 20000.00, 'approved', 1, '2026-04-14 10:34:23', '2026-04-14 10:33:43', '2026-04-14 10:34:23', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-30', 'urgent', 'Because there is very much load-shedding in the campus'),
(20, 'REQ-0014', 'Generator', 'Business Administration', 14, 100000.00, 'approved', 1, '2026-04-14 11:19:10', '2026-04-14 11:18:53', '2026-04-14 11:19:10', NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-15', 'urgent', '......');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_conflicts`
--

CREATE TABLE `reservation_conflicts` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `conflicting_reservation_id` int(11) DEFAULT NULL,
  `conflict_type` enum('time_overlap','asset_unavailable','quota_exceeded') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_access`
--

CREATE TABLE `role_access` (
  `id` int(11) NOT NULL,
  `role_key` varchar(100) DEFAULT NULL,
  `page_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_access`
--

INSERT INTO `role_access` (`id`, `role_key`, `page_id`, `created_at`) VALUES
(887, 'super_admin', 1, '2026-04-14 11:56:27'),
(888, 'super_admin', 31, '2026-04-14 11:56:27'),
(889, 'super_admin', 25, '2026-04-14 11:56:27'),
(890, 'super_admin', 14, '2026-04-14 11:56:27'),
(891, 'super_admin', 2, '2026-04-14 11:56:27'),
(892, 'super_admin', 3, '2026-04-14 11:56:27'),
(893, 'super_admin', 4, '2026-04-14 11:56:27'),
(894, 'super_admin', 46, '2026-04-14 11:56:27'),
(895, 'super_admin', 15, '2026-04-14 11:56:27'),
(896, 'super_admin', 5, '2026-04-14 11:56:27'),
(897, 'super_admin', 6, '2026-04-14 11:56:27'),
(898, 'super_admin', 13, '2026-04-14 11:56:27'),
(899, 'super_admin', 32, '2026-04-14 11:56:27'),
(900, 'super_admin', 26, '2026-04-14 11:56:27'),
(901, 'super_admin', 33, '2026-04-14 11:56:27'),
(902, 'super_admin', 27, '2026-04-14 11:56:27'),
(903, 'super_admin', 16, '2026-04-14 11:56:27'),
(904, 'super_admin', 7, '2026-04-14 11:56:27'),
(905, 'super_admin', 9, '2026-04-14 11:56:27'),
(906, 'super_admin', 8, '2026-04-14 11:56:27'),
(907, 'super_admin', 17, '2026-04-14 11:56:27'),
(908, 'super_admin', 11, '2026-04-14 11:56:27'),
(909, 'super_admin', 12, '2026-04-14 11:56:27'),
(910, 'super_admin', 10, '2026-04-14 11:56:27'),
(911, 'super_admin', 34, '2026-04-14 11:56:27'),
(912, 'super_admin', 28, '2026-04-14 11:56:27'),
(913, 'super_admin', 35, '2026-04-14 11:56:27'),
(914, 'super_admin', 29, '2026-04-14 11:56:27'),
(915, 'super_admin', 18, '2026-04-14 11:56:27'),
(916, 'super_admin', 48, '2026-04-14 11:56:27'),
(917, 'super_admin', 19, '2026-04-14 11:56:27'),
(918, 'super_admin', 36, '2026-04-14 11:56:27'),
(919, 'super_admin', 30, '2026-04-14 11:56:27'),
(920, 'hod', 37, '2026-04-14 11:56:27'),
(921, 'hod', 31, '2026-04-14 11:56:27'),
(922, 'hod', 25, '2026-04-14 11:56:27'),
(923, 'hod', 15, '2026-04-14 11:56:27'),
(924, 'hod', 5, '2026-04-14 11:56:27'),
(925, 'hod', 6, '2026-04-14 11:56:27'),
(926, 'hod', 16, '2026-04-14 11:56:27'),
(927, 'hod', 7, '2026-04-14 11:56:27'),
(928, 'hod', 17, '2026-04-14 11:56:27'),
(929, 'hod', 12, '2026-04-14 11:56:27'),
(930, 'hod', 10, '2026-04-14 11:56:27'),
(931, 'hod', 18, '2026-04-14 11:56:27'),
(932, 'hod', 19, '2026-04-14 11:56:27'),
(933, 'coordinator', 31, '2026-04-14 11:56:27'),
(934, 'coordinator', 25, '2026-04-14 11:56:27'),
(935, 'coordinator', 38, '2026-04-14 11:56:27'),
(936, 'coordinator', 15, '2026-04-14 11:56:27'),
(937, 'coordinator', 5, '2026-04-14 11:56:27'),
(938, 'coordinator', 6, '2026-04-14 11:56:27'),
(939, 'coordinator', 16, '2026-04-14 11:56:27'),
(940, 'coordinator', 7, '2026-04-14 11:56:27'),
(941, 'coordinator', 18, '2026-04-14 11:56:27'),
(942, 'coordinator', 19, '2026-04-14 11:56:27'),
(943, 'store_officer', 31, '2026-04-14 11:56:27'),
(944, 'store_officer', 25, '2026-04-14 11:56:27'),
(945, 'store_officer', 15, '2026-04-14 11:56:27'),
(946, 'store_officer', 5, '2026-04-14 11:56:27'),
(947, 'store_officer', 6, '2026-04-14 11:56:27'),
(948, 'store_officer', 33, '2026-04-14 11:56:27'),
(949, 'store_officer', 27, '2026-04-14 11:56:27'),
(950, 'store_officer', 16, '2026-04-14 11:56:27'),
(951, 'store_officer', 7, '2026-04-14 11:56:27'),
(952, 'store_officer', 9, '2026-04-14 11:56:27'),
(953, 'store_officer', 8, '2026-04-14 11:56:27'),
(954, 'store_officer', 39, '2026-04-14 11:56:27'),
(955, 'store_officer', 35, '2026-04-14 11:56:27'),
(956, 'store_officer', 29, '2026-04-14 11:56:27'),
(957, 'faculty', 40, '2026-04-14 11:56:27'),
(958, 'faculty', 34, '2026-04-14 11:56:27'),
(959, 'faculty', 28, '2026-04-14 11:56:27'),
(960, 'faculty', 18, '2026-04-14 11:56:27'),
(961, 'faculty', 19, '2026-04-14 11:56:27'),
(962, 'clerk', 17, '2026-04-14 11:56:27'),
(963, 'clerk', 11, '2026-04-14 11:56:27'),
(964, 'clerk', 12, '2026-04-14 11:56:27'),
(965, 'clerk', 10, '2026-04-14 11:56:27'),
(966, 'clerk', 34, '2026-04-14 11:56:27'),
(967, 'clerk', 28, '2026-04-14 11:56:27'),
(968, 'clerk', 47, '2026-04-14 11:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `movement_type` enum('in','out','adjustment') DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `reference` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `k` varchar(100) NOT NULL,
  `v` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`k`, `v`, `updated_at`) VALUES
('footer_text', '© 2026 University Inventory System', '2026-02-10 16:25:27'),
('system_logo', 'assets/img/logo.png', '2026-02-10 16:25:27'),
('system_name', 'University Inventory System', '2026-02-10 16:25:27');

-- --------------------------------------------------------

--
-- Table structure for table `sys_pages`
--

CREATE TABLE `sys_pages` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `page_name` varchar(150) DEFAULT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `icon_class` varchar(150) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sys_pages`
--

INSERT INTO `sys_pages` (`id`, `parent_id`, `page_name`, `page_url`, `icon_class`, `sort_order`, `visible`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Dashboard', 'dashboards/super_admin/index.php', 'fas fa-tachometer-alt', 0, 1, '2026-02-10 16:25:27', '2026-02-13 12:02:43'),
(2, 14, 'Manage Pages', 'dashboards/super_admin/manage_pages.php', 'fas fa-cogs', 2, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(3, 14, 'Manage Roles', 'dashboards/super_admin/manage_roles.php', 'fas fa-users-cog', 3, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(4, 14, 'Manage Users', 'dashboards/super_admin/manage_users.php', 'fas fa-users', 4, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(5, 15, 'Asset Categories', 'dashboards/inventory/categories.php', 'fas fa-tags', 5, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(6, 15, 'Assets', 'dashboards/inventory/assets.php', 'fas fa-box', 6, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(7, 16, 'Indent Requests', 'dashboards/procurement/indent_requests.php', 'fas fa-file-invoice', 8, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(8, 16, 'Purchase Orders', 'dashboards/procurement/purchase_orders.php', 'fas fa-shopping-cart', 10, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(9, 16, 'GRN', 'dashboards/procurement/grn.php', 'fas fa-clipboard-check', 9, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(10, 17, 'Stock Summary', 'dashboards/reports/stock_summary.php', 'fas fa-chart-bar', 13, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(11, 17, 'Depreciation Report', 'dashboards/reports/depreciation_report.php', 'fas fa-chart-line', 11, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(12, 17, 'Dead Stock', 'dashboards/reports/deadstock.php', 'fas fa-trash-alt', 12, 1, '2026-02-10 16:25:27', '2026-02-13 11:27:14'),
(13, 15, 'Departments', 'dashboards/university/departments.php', 'fas fa-building', 7, 1, '2026-02-12 12:06:59', '2026-02-13 11:27:14'),
(14, NULL, 'System Management', 'system_management/', 'fas fa-cogs', 1, 1, '2026-02-13 11:08:45', '2026-02-13 11:08:45'),
(15, NULL, 'Inventory', 'inventory/', 'fas fa-box', 2, 1, '2026-02-13 11:08:45', '2026-02-13 11:08:45'),
(16, NULL, 'Procurement', 'procurement/', 'fas fa-shopping-cart', 3, 1, '2026-02-13 11:08:45', '2026-02-13 11:08:45'),
(17, NULL, 'Reports', 'reports/', 'fas fa-chart-bar', 4, 1, '2026-02-13 11:08:45', '2026-02-13 11:08:45'),
(18, NULL, 'University', 'university/', 'fas fa-university', 5, 1, '2026-02-13 11:08:45', '2026-02-13 11:08:45'),
(19, 18, 'Asset Allocation Tracker', 'dashboards/university/asset_allocation.php', 'fas fa-user-tag', 7, 1, '2026-02-13 11:21:58', '2026-02-13 11:27:14'),
(25, 31, 'Maintenance Management', 'dashboards/maintenance/maintenance_requests.php', 'fas fa-tools', 8, 1, '2026-02-13 12:19:56', '2026-02-16 10:38:29'),
(26, 32, 'Asset Transfer Management', 'dashboards/maintenance/asset_transfer.php', 'fas fa-exchange-alt', 9, 1, '2026-02-13 12:22:32', '2026-02-16 10:38:30'),
(27, 33, 'Budget Management', 'dashboards/budget/budget_management.php', 'fas fa-money-bill-wave', 10, 1, '2026-02-16 10:04:48', '2026-02-16 10:38:30'),
(28, 34, 'Asset Reservation System', 'dashboards/reservation/asset_reservation.php', 'fas fa-calendar-check', 11, 1, '2026-02-16 10:12:41', '2026-02-16 10:38:30'),
(29, 35, 'Asset Disposal Management', 'dashboards/disposal/asset_disposal.php', 'fas fa-trash-alt', 12, 1, '2026-02-16 10:23:11', '2026-02-16 10:38:30'),
(30, 36, 'Asset Location Tracker', 'dashboards/locator/asset_locator.php', 'fas fa-map-marker-alt', 13, 1, '2026-02-16 10:27:51', '2026-02-16 10:38:30'),
(31, NULL, 'Maintenance', 'maintenance/', 'fas fa-tools', 1, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(32, NULL, 'Transfers', 'transfers/', 'fas fa-exchange-alt', 2, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(33, NULL, 'Budgets', 'budgets/', 'fas fa-money-bill-wave', 3, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(34, NULL, 'Reservations', 'reservations/', 'fas fa-calendar-check', 4, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(35, NULL, 'Disposals', 'disposals/', 'fas fa-trash-alt', 5, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(36, NULL, 'Location Tracking', 'location_tracking/', 'fas fa-map-marker-alt', 6, 1, '2026-02-16 10:38:29', '2026-02-16 10:38:29'),
(37, NULL, 'HOD Dashboard', 'dashboards/hod_dashboard.php', 'fas fa-tachometer-alt', 1, 1, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(38, NULL, 'Coordinator Dashboard', 'dashboards/coordinator_dashboard.php', 'fas fa-tachometer-alt', 2, 1, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(39, NULL, 'Store Officer Dashboard', 'dashboards/store_officer_dashboard.php', 'fas fa-tachometer-alt', 3, 1, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(40, NULL, 'Faculty Dashboard', 'dashboards/faculty_dashboard.php', 'fas fa-tachometer-alt', 4, 1, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(46, 14, 'Role Access Matrix', 'dashboards/super_admin/manage_access.php', 'fas fa-user-shield', 5, 1, '2026-03-19 09:57:46', '2026-03-19 10:41:05'),
(47, NULL, 'Clerk Dashboard', 'dashboards/clerk_dashboard.php', 'fas fa-tachometer-alt', 5, 1, '2026-03-19 10:13:21', '2026-03-19 10:13:21'),
(48, 18, 'Manage Locations', 'dashboards/university/locations.php', 'fas fa-map-marked-alt', 0, 1, '2026-04-08 12:44:34', '2026-04-08 12:44:34');

-- --------------------------------------------------------

--
-- Table structure for table `sys_roles`
--

CREATE TABLE `sys_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) DEFAULT NULL,
  `role_key` varchar(100) DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sys_roles`
--

INSERT INTO `sys_roles` (`id`, `role_name`, `role_key`, `is_system_role`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'super_admin', 1, '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(2, 'HOD', 'hod', 0, '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(4, 'Faculty', 'faculty', 0, '2026-02-10 16:25:27', '2026-02-10 16:25:27'),
(5, 'Department Coordinator', 'coordinator', 0, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(6, 'Store Officer', 'store_officer', 0, '2026-02-16 11:21:34', '2026-02-16 11:21:34'),
(14, 'Clerk', 'clerk', 0, '2026-03-03 10:25:53', '2026-03-03 10:25:53');

-- --------------------------------------------------------

--
-- Table structure for table `university_departments`
--

CREATE TABLE `university_departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `faculty` varchar(255) DEFAULT NULL,
  `hod_id` int(11) DEFAULT NULL,
  `coordinator_id` int(11) DEFAULT NULL,
  `clerk_id` int(11) DEFAULT NULL,
  `store_officer_id` int(11) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `university_departments`
--

INSERT INTO `university_departments` (`id`, `name`, `faculty`, `hod_id`, `coordinator_id`, `clerk_id`, `store_officer_id`, `contact_email`, `created_at`, `updated_at`) VALUES
(1, 'Computer Science', 'Engineering', NULL, NULL, NULL, NULL, 'cs@university.edu', '2026-02-12 12:00:41', '2026-02-12 12:00:41'),
(2, 'English', 'Linguistic', 9, 10, 11, 12, 'english@university.edu', '2026-02-12 12:00:41', '2026-03-12 16:38:22'),
(3, 'Electrical Engineering', 'Engineering', NULL, NULL, NULL, NULL, 'ee@university.edu', '2026-02-12 12:00:41', '2026-02-12 12:00:41'),
(4, 'Mathematics', 'Science', NULL, NULL, NULL, NULL, 'math@university.edu', '2026-02-12 12:00:41', '2026-02-12 12:00:41'),
(5, 'Physics', 'Science', NULL, NULL, NULL, NULL, 'physics@university.edu', '2026-02-12 12:00:41', '2026-02-12 12:00:41'),
(7, 'Psychology Department', 'Psychology faculty', 1, NULL, NULL, NULL, 'psy@university.edu', '2026-02-12 12:24:21', '2026-02-12 12:24:21'),
(22, 'Business Administration', 'Business Faculty', 14, 15, 7, 8, 'business@gmail.com', '2026-03-12 16:41:09', '2026-03-12 16:41:09'),
(23, 'Bootany', 'Biology', 17, 18, 19, NULL, 'bio@university.edu', '2026-04-09 12:36:41', '2026-04-10 11:38:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `identity_no` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `department` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `password`, `identity_no`, `is_active`, `created_at`, `updated_at`, `department`) VALUES
(1, 'Super Admin User', 'admin@university.edu', 'super_admin', '$2y$10$/pGlr7EAPFTpsnGc1X2s1OFoTCG6IeDS9qL82ciOS9plYTRtQHGiC', 'SA001', 1, '2026-02-10 16:25:27', '2026-03-12 11:07:45', 'Psychology Department'),
(3, 'Dr Waris Ali', 'hod@university.edu', 'hod', '$2y$10$TQPjw3HIT.4.2tLM7PUbKuSRbZIA5PFoIM6vZbpJWfXgABdz3J16O', 'HOD001', 1, '2026-02-16 12:34:06', '2026-04-14 09:57:15', 'English'),
(4, 'Dr Atif Gill', 'coordinator@university.edu', 'coordinator', '$2y$10$U5LZONNS1iYT0lFzqCGG.OvOEF9v7XhnKHqGGQsz1srpPKnh/Ve56', 'COORD001', 1, '2026-02-16 12:34:06', '2026-04-14 09:57:06', 'English'),
(5, 'Mr. Bilal', 'store@university.edu', 'clerk', '$2y$10$T/wpmEIp/Tp2Ufn5EoiyLuA8aJHpQ9uN8p0hTQQFE3XXehHKWwo4e', 'STORE001', 1, '2026-02-16 12:34:06', '2026-04-14 09:57:22', 'English'),
(6, 'Prof. Sadia ', 'faculty@university.edu', 'faculty', '$2y$10$ngFPohA7JTNkjeTsfU8iheHPh4HwYhaqVYC3d0pwRZfFLxpH7H93W', 'FAC001', 1, '2026-02-16 12:34:06', '2026-03-12 16:38:22', 'English'),
(7, 'Bilal ', 'bilal@gmail.com', 'clerk', '$2y$10$7u2waHi3wBjp9aKJiZpKceSFsQldflAYyRtCYbuFuqJXCgP3QYb42', NULL, 1, '2026-03-12 16:34:54', '2026-03-17 07:18:36', 'Business Administration'),
(8, 'Asif', 'asif@gmail.com', 'store_officer', '$2y$10$G0YwCqATTk9wtm/5L4yuHOOJSjSiZadPOlX8NPteH8SCLtnxEML/G', NULL, 1, '2026-03-12 16:34:54', '2026-03-17 07:18:37', 'Business Administration'),
(9, 'Dr Shabbir', 'eng-hod@university.edu', 'hod', '$2y$10$HWe6eRi2S.l4CV66pQoghel9ADQEDz/KU05WRG5P8vg5mm03l.8mW', NULL, 1, '2026-03-12 16:38:21', '2026-03-17 07:21:11', 'English'),
(10, 'Usman Akram', 'eng-coordinator@university.edu', 'coordinator', '$2y$10$g0aTgP4D8hdJEZy0E7kMKuSuf.5JKEJ4yCZgvkHVNrfFW1hLQUEeS', NULL, 1, '2026-03-12 16:38:21', '2026-03-17 07:21:12', 'English'),
(11, 'Wasif', 'wasif@gmail.com', 'clerk', '$2y$10$TlPCXFq3k3bYKag6T04LoeICLYnqSUJ5NCxcsUH2PcL9.DwWgyPaS', NULL, 1, '2026-03-12 16:38:21', '2026-03-17 07:21:12', 'English'),
(12, 'Usama', 'usama@gmail.com', 'store_officer', '$2y$10$ziiGlS1g8r4yEAufkCVZ3eV1ykORCwx0g/iVfdyZrCseyv4kWhn4u', NULL, 1, '2026-03-12 16:38:22', '2026-03-17 07:21:12', 'English'),
(13, 'Prof. Sadia ', 'eng-faculty@university.edu', 'faculty', '$2y$10$xXDbiiWF/p6KI/f3F8n6I.lM0xHdYnxddJ7/A/GPZVevYP06BSEym', NULL, 0, '2026-03-12 16:38:22', '2026-03-12 17:13:45', 'English'),
(14, 'Dr Waris Ali', 'business-hod@university.edu', 'hod', '$2y$10$hvzPzUVXLAtnvTnFK0J.puJBUE8QfQMbgRvmQbutt3Aqj1jca87Ly', NULL, 1, '2026-03-12 16:41:09', '2026-03-17 07:18:36', 'Business Administration'),
(15, 'Dr Atif Gill', 'business-coordinator@university.edu', 'coordinator', '$2y$10$Y5Ry2Zf5NsvxZKK.u6i2Lerd/arUqSwP3TMNuLxE0NAitBjDeVnRG', NULL, 1, '2026-03-12 16:41:09', '2026-03-17 07:18:36', 'Business Administration'),
(16, 'Prof. Umair Waqas', 'umair@university.edu', 'faculty', '$2y$10$5w4R.RxVaxA1xkf.nw30qOPBK0FDIuZwynOTew9esGWDMYm8LYZkq', NULL, 1, '2026-03-12 16:41:09', '2026-03-12 16:41:09', 'Business Administration'),
(17, 'Prof Javed', 'bio-hod@university.edu', 'hod', '$2y$10$Hr1jWDihEU1rfZV0etUUAePNb9tyU2Ee/3EhphfWZEzt3Y7pdz/MG', NULL, 1, '2026-04-10 11:38:50', '2026-04-10 11:38:50', 'Bootany'),
(18, 'Zainab Haneef', 'bio-coordinator@university.edu', 'coordinator', '$2y$10$POMiewrCbgrd0FBEQT4bKe/zOLhoGN5AFbHVS8NupBy53s.b14OUq', NULL, 1, '2026-04-10 11:38:50', '2026-04-14 10:07:56', 'Bootany'),
(19, 'Shaheer', 'shaheer@gmail.com', 'clerk', '$2y$10$drO3xqswz9nZTbVoThDyhu7HYfrH6WNZ93ghRuM0t5WEbMogFJJmq', NULL, 1, '2026-04-10 11:38:50', '2026-04-10 11:38:50', 'Bootany'),
(20, 'Zahra Usman', 'zahra@university.edu', 'faculty', '$2y$10$.ul5NFdDQoQ/zhaaIgu3ZufgynfP39Ue2Xt4nPfIP7dHY8flzxBmW', NULL, 1, '2026-04-10 11:38:51', '2026-04-10 11:38:51', 'Bootany');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `assigned_to_user_id` (`assigned_to_user_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `assets_ibfk_3` (`purchase_request_id`);

--
-- Indexes for table `asset_availability`
--
ALTER TABLE `asset_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_asset_date` (`asset_id`,`available_date`);

--
-- Indexes for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_disposals`
--
ALTER TABLE `asset_disposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by_level1` (`approved_by_level1`),
  ADD KEY `approved_by_level2` (`approved_by_level2`),
  ADD KEY `approved_by_level3` (`approved_by_level3`);

--
-- Indexes for table `asset_inventory_records`
--
ALTER TABLE `asset_inventory_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `scanned_by` (`scanned_by`);

--
-- Indexes for table `asset_inventory_sessions`
--
ALTER TABLE `asset_inventory_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `started_by` (`started_by`),
  ADD KEY `completed_by` (`completed_by`);

--
-- Indexes for table `asset_location_history`
--
ALTER TABLE `asset_location_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `moved_by` (`moved_by`);

--
-- Indexes for table `asset_location_tracking`
--
ALTER TABLE `asset_location_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `scanned_by` (`scanned_by`);

--
-- Indexes for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `asset_maintenance_history`
--
ALTER TABLE `asset_maintenance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_id` (`maintenance_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `asset_movement_log`
--
ALTER TABLE `asset_movement_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `asset_reservations`
--
ALTER TABLE `asset_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `requester_user_id` (`requester_user_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `asset_transfers`
--
ALTER TABLE `asset_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`),
  ADD KEY `approved_by_level1` (`approved_by_level1`),
  ADD KEY `approved_by_level2` (`approved_by_level2`),
  ADD KEY `approved_by_level3` (`approved_by_level3`);

--
-- Indexes for table `asset_warranties`
--
ALTER TABLE `asset_warranties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_categories`
--
ALTER TABLE `budget_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_transactions`
--
ALTER TABLE `budget_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `department_budgets`
--
ALTER TABLE `department_budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dept_year` (`department_name`,`budget_year`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `depreciation_records`
--
ALTER TABLE `depreciation_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `disposal_auctions`
--
ALTER TABLE `disposal_auctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disposal_id` (`disposal_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `disposal_audit_trail`
--
ALTER TABLE `disposal_audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disposal_id` (`disposal_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `disposal_recycling`
--
ALTER TABLE `disposal_recycling`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disposal_id` (`disposal_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `disposal_sales`
--
ALTER TABLE `disposal_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disposal_id` (`disposal_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `grn`
--
ALTER TABLE `grn`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grn_no` (`grn_no`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_person_id` (`contact_person_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_no` (`request_no`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `approved_by_coordinator` (`approved_by_coordinator`),
  ADD KEY `approved_by_hod` (`approved_by_hod`),
  ADD KEY `approved_by_dean` (`approved_by_dean`);

--
-- Indexes for table `reservation_conflicts`
--
ALTER TABLE `reservation_conflicts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `conflicting_reservation_id` (`conflicting_reservation_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `role_access`
--
ALTER TABLE `role_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_page` (`role_key`,`page_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `sys_pages`
--
ALTER TABLE `sys_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_url` (`page_url`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `sys_roles`
--
ALTER TABLE `sys_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_key` (`role_key`);

--
-- Indexes for table `university_departments`
--
ALTER TABLE `university_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `fk_hod` (`hod_id`),
  ADD KEY `fk_coordinator` (`coordinator_id`),
  ADD KEY `fk_dept_clerk` (`clerk_id`),
  ADD KEY `fk_dept_store_officer` (`store_officer_id`);

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
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `asset_availability`
--
ALTER TABLE `asset_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_categories`
--
ALTER TABLE `asset_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `asset_disposals`
--
ALTER TABLE `asset_disposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `asset_inventory_records`
--
ALTER TABLE `asset_inventory_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_inventory_sessions`
--
ALTER TABLE `asset_inventory_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_location_history`
--
ALTER TABLE `asset_location_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_location_tracking`
--
ALTER TABLE `asset_location_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `asset_maintenance_history`
--
ALTER TABLE `asset_maintenance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `asset_movement_log`
--
ALTER TABLE `asset_movement_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `asset_reservations`
--
ALTER TABLE `asset_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `asset_transfers`
--
ALTER TABLE `asset_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `asset_warranties`
--
ALTER TABLE `asset_warranties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_categories`
--
ALTER TABLE `budget_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_transactions`
--
ALTER TABLE `budget_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `department_budgets`
--
ALTER TABLE `department_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `depreciation_records`
--
ALTER TABLE `depreciation_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposal_auctions`
--
ALTER TABLE `disposal_auctions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposal_audit_trail`
--
ALTER TABLE `disposal_audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposal_recycling`
--
ALTER TABLE `disposal_recycling`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposal_sales`
--
ALTER TABLE `disposal_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grn`
--
ALTER TABLE `grn`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `reservation_conflicts`
--
ALTER TABLE `reservation_conflicts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_access`
--
ALTER TABLE `role_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=969;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sys_pages`
--
ALTER TABLE `sys_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `sys_roles`
--
ALTER TABLE `sys_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `university_departments`
--
ALTER TABLE `university_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assets_ibfk_3` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_ibfk_4` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `asset_availability`
--
ALTER TABLE `asset_availability`
  ADD CONSTRAINT `asset_availability_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `asset_disposals`
--
ALTER TABLE `asset_disposals`
  ADD CONSTRAINT `asset_disposals_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_disposals_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_disposals_ibfk_3` FOREIGN KEY (`approved_by_level1`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_disposals_ibfk_4` FOREIGN KEY (`approved_by_level2`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_disposals_ibfk_5` FOREIGN KEY (`approved_by_level3`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_inventory_records`
--
ALTER TABLE `asset_inventory_records`
  ADD CONSTRAINT `asset_inventory_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `asset_inventory_sessions` (`id`),
  ADD CONSTRAINT `asset_inventory_records_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_inventory_records_ibfk_3` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_inventory_sessions`
--
ALTER TABLE `asset_inventory_sessions`
  ADD CONSTRAINT `asset_inventory_sessions_ibfk_1` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_inventory_sessions_ibfk_2` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_location_history`
--
ALTER TABLE `asset_location_history`
  ADD CONSTRAINT `asset_location_history_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_location_history_ibfk_2` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_location_tracking`
--
ALTER TABLE `asset_location_tracking`
  ADD CONSTRAINT `asset_location_tracking_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_location_tracking_ibfk_2` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  ADD CONSTRAINT `asset_maintenance_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_maintenance_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_maintenance_history`
--
ALTER TABLE `asset_maintenance_history`
  ADD CONSTRAINT `asset_maintenance_history_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `asset_maintenance` (`id`),
  ADD CONSTRAINT `asset_maintenance_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_movement_log`
--
ALTER TABLE `asset_movement_log`
  ADD CONSTRAINT `asset_movement_log_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_movement_log_ibfk_2` FOREIGN KEY (`transfer_id`) REFERENCES `asset_transfers` (`id`),
  ADD CONSTRAINT `asset_movement_log_ibfk_3` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_movement_log_ibfk_4` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_movement_log_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_reservations`
--
ALTER TABLE `asset_reservations`
  ADD CONSTRAINT `asset_reservations_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_reservations_ibfk_2` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_reservations_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_reservations_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_transfers`
--
ALTER TABLE `asset_transfers`
  ADD CONSTRAINT `asset_transfers_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_3` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_4` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_5` FOREIGN KEY (`approved_by_level1`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_6` FOREIGN KEY (`approved_by_level2`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `asset_transfers_ibfk_7` FOREIGN KEY (`approved_by_level3`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_warranties`
--
ALTER TABLE `asset_warranties`
  ADD CONSTRAINT `asset_warranties_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `budget_transactions`
--
ALTER TABLE `budget_transactions`
  ADD CONSTRAINT `budget_transactions_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `department_budgets` (`id`),
  ADD CONSTRAINT `budget_transactions_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `budget_transactions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `budget_transactions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `department_budgets`
--
ALTER TABLE `department_budgets`
  ADD CONSTRAINT `department_budgets_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `depreciation_records`
--
ALTER TABLE `depreciation_records`
  ADD CONSTRAINT `depreciation_records_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `disposal_auctions`
--
ALTER TABLE `disposal_auctions`
  ADD CONSTRAINT `disposal_auctions_ibfk_1` FOREIGN KEY (`disposal_id`) REFERENCES `asset_disposals` (`id`),
  ADD CONSTRAINT `disposal_auctions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposal_audit_trail`
--
ALTER TABLE `disposal_audit_trail`
  ADD CONSTRAINT `disposal_audit_trail_ibfk_1` FOREIGN KEY (`disposal_id`) REFERENCES `asset_disposals` (`id`),
  ADD CONSTRAINT `disposal_audit_trail_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposal_recycling`
--
ALTER TABLE `disposal_recycling`
  ADD CONSTRAINT `disposal_recycling_ibfk_1` FOREIGN KEY (`disposal_id`) REFERENCES `asset_disposals` (`id`),
  ADD CONSTRAINT `disposal_recycling_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposal_sales`
--
ALTER TABLE `disposal_sales`
  ADD CONSTRAINT `disposal_sales_ibfk_1` FOREIGN KEY (`disposal_id`) REFERENCES `asset_disposals` (`id`),
  ADD CONSTRAINT `disposal_sales_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `grn`
--
ALTER TABLE `grn`
  ADD CONSTRAINT `grn_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `grn_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`contact_person_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `purchase_requests` (`id`);

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_3` FOREIGN KEY (`approved_by_coordinator`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_4` FOREIGN KEY (`approved_by_hod`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_5` FOREIGN KEY (`approved_by_dean`) REFERENCES `users` (`id`);

--
-- Constraints for table `reservation_conflicts`
--
ALTER TABLE `reservation_conflicts`
  ADD CONSTRAINT `reservation_conflicts_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `asset_reservations` (`id`),
  ADD CONSTRAINT `reservation_conflicts_ibfk_2` FOREIGN KEY (`conflicting_reservation_id`) REFERENCES `asset_reservations` (`id`),
  ADD CONSTRAINT `reservation_conflicts_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `role_access`
--
ALTER TABLE `role_access`
  ADD CONSTRAINT `role_access_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `sys_pages` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sys_pages`
--
ALTER TABLE `sys_pages`
  ADD CONSTRAINT `sys_pages_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `sys_pages` (`id`);

--
-- Constraints for table `university_departments`
--
ALTER TABLE `university_departments`
  ADD CONSTRAINT `fk_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_dept_clerk` FOREIGN KEY (`clerk_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_dept_store_officer` FOREIGN KEY (`store_officer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_hod` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `university_departments_ibfk_1` FOREIGN KEY (`hod_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

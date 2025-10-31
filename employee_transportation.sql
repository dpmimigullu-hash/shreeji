-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 09:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `employee_transportation`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','leave') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `driver_id`, `date`, `status`, `check_in_time`, `check_out_time`, `created_at`) VALUES
(1, 4, '2025-10-22', 'present', NULL, NULL, '2025-10-22 12:26:36'),
(3, 5, '2025-10-22', 'present', NULL, NULL, '2025-10-22 18:58:25'),
(4, 6, '2025-10-22', 'leave', NULL, NULL, '2025-10-22 18:58:36');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','overdue') DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `trip_id`, `amount`, `payment_status`, `payment_date`, `created_at`, `updated_at`) VALUES
(1, 1, 315.00, 'paid', '2025-10-27 15:03:48', '2025-10-23 06:29:30', '2025-10-27 09:33:48'),
(2, 8, 99999999.99, 'pending', NULL, '2025-10-29 19:33:38', '2025-10-29 19:33:38'),
(3, 6, 196550.97, 'pending', NULL, '2025-10-29 19:36:17', '2025-10-29 19:36:17'),
(4, 9, 300.00, 'pending', NULL, '2025-10-29 19:41:38', '2025-10-29 19:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `city`, `state`, `pincode`, `phone`, `email`, `latitude`, `longitude`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Head Office', '123 Main Street, Business District', 'Mumbai', 'Maharashtra', '400001', '+91-22-12345678', 'head@mstransport.com', 19.07600000, 72.87770000, 'active', '2025-10-29 09:23:15', '2025-10-29 09:23:15'),
(2, 'Branch Office - Delhi', '456 Corporate Avenue, Connaught Place', 'Delhi', 'Delhi', '110001', '+91-11-23456789', 'delhi@mstransport.com', 28.70410000, 77.10250000, 'active', '2025-10-29 09:23:15', '2025-10-29 09:23:15'),
(3, 'Branch Office - Bangalore', '789 Tech Park, Whitefield', 'Bangalore', 'Karnataka', '560066', '+91-80-34567890', 'bangalore@mstransport.com', 12.97160000, 77.59460000, 'active', '2025-10-29 09:23:15', '2025-10-29 09:23:15');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `gst` varchar(20) DEFAULT NULL,
  `tan` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `email`, `phone`, `company`, `address`, `city`, `state`, `pincode`, `latitude`, `longitude`, `pan`, `gst`, `tan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'IBM', 'biji@gmail.com', '9826767689', 'IBM', 'wquhqwgqwdhgqw jhkshqhsqhs', 'KOlkata', 'west bengal', '700001', 0.00000000, 0.00000000, NULL, NULL, NULL, 'active', '2025-10-27 15:09:27', '2025-10-27 15:09:27'),
(2, 'wipro', 'bijai@gmail.com', '+919826767689', 'WIPRO', 'wquhqwgqwdhgqw jhkshqhsqhs', 'KOlkata', 'west bengal', '700001', 22.57984260, 88.42870360, NULL, NULL, NULL, 'active', '2025-10-29 14:59:40', '2025-10-29 14:59:40'),
(3, 'Demo Client', 'demo@client.com', '+91-9876543210', 'Demo Corporation', '123 Demo Street, Business District', 'Mumbai', 'Maharashtra', '400001', 19.07600000, 72.87770000, NULL, NULL, NULL, 'active', '2025-10-29 18:53:32', '2025-10-29 18:53:32');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `driver_photo` varchar(255) DEFAULT NULL,
  `kyc_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`kyc_documents`)),
  `address` text DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_ifsc_code` varchar(20) DEFAULT NULL,
  `supervisor_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `fuel_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_locations`
--

CREATE TABLE `driver_locations` (
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gps_tracking`
--

CREATE TABLE `gps_tracking` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_ifsc_code` varchar(20) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_attendance`
--

CREATE TABLE `supervisor_attendance` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','early_departure') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_attendance`
--

INSERT INTO `supervisor_attendance` (`id`, `supervisor_id`, `date`, `status`, `check_in_time`, `check_out_time`, `latitude`, `longitude`, `location_verified`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-10-27', 'present', '12:20:00', '00:00:00', 22.57715200, 88.43100160, 0, '2025-10-27 11:20:41', '2025-10-27 11:20:41');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_branches`
--

CREATE TABLE `supervisor_branches` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_branches`
--

INSERT INTO `supervisor_branches` (`id`, `supervisor_id`, `branch_id`, `assigned_at`) VALUES
(1, 10, 3, '2025-10-30 07:57:32'),
(2, 16, 2, '2025-10-30 08:02:02');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `passenger_count` int(11) NOT NULL,
  `start_location_lat` decimal(10,8) DEFAULT NULL,
  `start_location_lng` decimal(11,8) DEFAULT NULL,
  `end_location_lat` decimal(10,8) DEFAULT NULL,
  `end_location_lng` decimal(11,8) DEFAULT NULL,
  `start_otp` varchar(10) DEFAULT NULL,
  `end_otp` varchar(10) DEFAULT NULL,
  `start_qr_code` varchar(255) DEFAULT NULL,
  `end_qr_code` varchar(255) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_date` date NOT NULL DEFAULT '2024-01-01',
  `client_id` int(11) DEFAULT NULL,
  `trip_type` enum('pickup','drop','pickup_drop') DEFAULT 'pickup',
  `scheduled_pickup_time` time DEFAULT NULL,
  `scheduled_drop_time` time DEFAULT NULL,
  `start_location_name` varchar(255) DEFAULT NULL,
  `end_location_name` varchar(255) DEFAULT NULL,
  `first_passenger_name` varchar(100) DEFAULT NULL,
  `first_passenger_phone` varchar(20) DEFAULT NULL,
  `first_passenger_address` text DEFAULT NULL,
  `last_passenger_name` varchar(100) DEFAULT NULL,
  `last_passenger_phone` varchar(20) DEFAULT NULL,
  `last_passenger_address` text DEFAULT NULL,
  `estimated_duration` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `driver_id`, `vehicle_id`, `supervisor_id`, `passenger_count`, `start_location_lat`, `start_location_lng`, `end_location_lat`, `end_location_lng`, `start_otp`, `end_otp`, `start_qr_code`, `end_qr_code`, `start_time`, `end_time`, `distance`, `status`, `created_at`, `updated_at`, `trip_date`, `client_id`, `trip_type`, `scheduled_pickup_time`, `scheduled_drop_time`, `start_location_name`, `end_location_name`, `first_passenger_name`, `first_passenger_phone`, `first_passenger_address`, `last_passenger_name`, `last_passenger_phone`, `last_passenger_address`, `estimated_duration`) VALUES
(1, 4, 22, 2, 2, 99.99999999, 999.99999999, 99.99999999, 999.99999999, '831396', '413336', 'QR_1612fe824605a8eed98bae88309df9a9', 'QR_671f6a7be12c05ce22503d43657af5e3', '2025-10-22 17:56:36', '2025-10-23 11:59:30', 0.00, 'completed', '2025-10-22 12:26:16', '2025-10-26 15:19:22', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 7, 40, 2, 4, NULL, NULL, NULL, NULL, '905023', '461876', 'QR_c0bcf2f06b8099a149726f51b9e9f8df', 'QR_c0c65206046895e031635e87a920c296', NULL, NULL, NULL, 'scheduled', '2025-10-27 15:25:13', '2025-10-27 15:25:13', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 7, 40, 2, 3, NULL, NULL, NULL, NULL, '878768', '751227', 'QR_43cbb68ddb2e3bf5c010ad97d464b2e3', 'QR_40478a70fbb68785b33c98c1da79fb08', NULL, NULL, NULL, 'scheduled', '2025-10-27 15:33:41', '2025-10-27 15:33:41', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 7, 40, 2, 3, NULL, NULL, NULL, NULL, '354380', '404909', 'QR_7c4451c9f24116a32314ed991d737930', 'QR_c13fbefcd45cb3fcbd935d35bfc91ffb', NULL, NULL, NULL, 'scheduled', '2025-10-27 15:35:52', '2025-10-27 15:35:52', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 7, 40, 2, 3, NULL, NULL, NULL, NULL, '882590', '161790', 'QR_1912d8caf0892c4e797ae9ebbb7ec749', 'QR_82069f7d827667aa8a7633209d450659', NULL, NULL, NULL, 'scheduled', '2025-10-27 15:36:56', '2025-10-27 15:36:56', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 7, 40, 2, 3, NULL, NULL, 22.72001710, 88.48880990, '334772', '186233', 'QR_292b72a3b43a04e0e8228b4400e6f6a2', 'QR_8624876d574e7bcb84966fbd7bb523a7', '2025-10-28 11:59:41', '2025-10-30 01:06:17', 9852.55, 'completed', '2025-10-27 15:38:23', '2025-10-29 19:36:17', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 3, 33, 9, 2, NULL, NULL, 22.73109458, 88.48671974, '904898', '716400', 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=START_904898_3_1761762582', 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=END_716400_3_1761762582', '2025-10-30 00:00:49', '2025-10-30 01:03:38', 9852346.71, 'completed', '2025-10-29 18:29:42', '2025-10-29 19:33:38', '2024-01-01', NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 3, 33, 9, 3, 22.73234740, 88.48561250, 22.73123434, 88.48671528, '216683', '655785', 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=START_216683_3_1761766821', 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=END_655785_3_1761766821', '2025-10-30 01:11:12', '2025-10-30 01:11:38', 0.17, 'completed', '2025-10-29 19:40:21', '2025-10-29 19:41:38', '2025-10-30', 1, 'pickup', '05:00:00', NULL, 'Rabindra Road, Barasat, Kolkata, West Bengal, India', 'Globsyn Business School, PS, Chandni, Bishnupur, Kolkata, West Bengal, India', 'rajib singh', '9026268900', 'Rabindra Road, Barasat, Kolkata, West Bengal, India', 'sunil khanna', '8767899993', 'Globsyn Business School, PS, Chandni, Bishnupur, Kolkata, West Bengal, India', 3.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','supervisor','driver') NOT NULL DEFAULT 'driver',
  `supervisor_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `driver_photo` varchar(255) DEFAULT NULL,
  `kyc_documents` longtext DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_ifsc_code` varchar(20) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `assigned_vehicle_id` int(11) DEFAULT NULL,
  `vehicle_registration_number` varchar(20) DEFAULT NULL,
  `vehicle_chassis_number` varchar(50) DEFAULT NULL,
  `vehicle_engine_number` varchar(50) DEFAULT NULL,
  `vehicle_fuel_type` varchar(10) DEFAULT NULL,
  `vehicle_photo` varchar(255) DEFAULT NULL,
  `pollution_certificate` varchar(255) DEFAULT NULL,
  `pollution_valid_from` date DEFAULT NULL,
  `pollution_valid_till` date DEFAULT NULL,
  `pollution_issued_by` varchar(100) DEFAULT NULL,
  `registration_certificate` varchar(255) DEFAULT NULL,
  `registration_valid_from` date DEFAULT NULL,
  `registration_valid_till` date DEFAULT NULL,
  `registration_issued_by` varchar(100) DEFAULT NULL,
  `road_tax` varchar(255) DEFAULT NULL,
  `road_tax_valid_from` date DEFAULT NULL,
  `road_tax_valid_till` date DEFAULT NULL,
  `road_tax_issued_by` varchar(100) DEFAULT NULL,
  `fast_tag` varchar(255) DEFAULT NULL,
  `fast_tag_valid_from` date DEFAULT NULL,
  `fast_tag_valid_till` date DEFAULT NULL,
  `fast_tag_issued_by` varchar(100) DEFAULT NULL,
  `insurance_policy_number` varchar(50) DEFAULT NULL,
  `insurance_expiry_date` date DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_type` varchar(50) DEFAULT NULL,
  `insurance_document` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `email`, `phone`, `role`, `supervisor_id`, `branch_id`, `license_number`, `license_photo`, `driver_photo`, `kyc_documents`, `bank_name`, `bank_account_number`, `bank_ifsc_code`, `bank_branch`, `assigned_vehicle_id`, `vehicle_registration_number`, `vehicle_chassis_number`, `vehicle_engine_number`, `vehicle_fuel_type`, `vehicle_photo`, `pollution_certificate`, `pollution_valid_from`, `pollution_valid_till`, `pollution_issued_by`, `registration_certificate`, `registration_valid_from`, `registration_valid_till`, `registration_issued_by`, `road_tax`, `road_tax_valid_from`, `road_tax_valid_till`, `road_tax_issued_by`, `fast_tag`, `fast_tag_valid_from`, `fast_tag_valid_till`, `fast_tag_issued_by`, `insurance_policy_number`, `insurance_expiry_date`, `insurance_provider`, `insurance_type`, `insurance_document`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin@ets.com', '9999999999', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-29 14:11:55', '2025-10-29 14:23:57'),
(2, 'bapi', '$2y$10$DQupNMP09N6HIr/sHBN4UOHUPT6tQs7qJzWajMSWjFu64zo.oBPc.', 'bapi mondal', 'ASDGSD@GMAIL.COM', '09826767689', 'driver', 9, NULL, '45678909876', 'uploads/690225fb5b750_520438112_631872409948130_1094216113222396609_n.jpg', 'uploads/690225fb5bf3a_520438112_631872409948130_1094216113222396609_n.jpg', '[\"uploads\\/690225fb5cb8c_bill_1 (4).pdf\"]', 'indian bank', '234567899876', 'r4rewfe43432432', 'KOLKATA', 39, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-29 14:34:35', '2025-10-29 14:43:35'),
(3, 'super1', '$2y$10$fmxNi3iPZ2Hf2pz4ODb2.eZyVEFkQWfCLRzIvHHkZh6SqyeyaVb2O', 'IBM', 'biji@gmail.com', '09826767689', 'driver', 9, NULL, 'l238', NULL, NULL, '[]', 'indian bank', '234567899876', 'r4rewfe43432432', 'KOLKATA', 33, 'EWERWERFWEFWEFWEFEW', '', '', 'diesel', NULL, NULL, '0000-00-00', '0000-00-00', NULL, NULL, '0000-00-00', '0000-00-00', NULL, NULL, '0000-00-00', '0000-00-00', NULL, NULL, '0000-00-00', '0000-00-00', NULL, '', '0000-00-00', '', NULL, NULL, NULL, '2025-10-29 14:36:40', '2025-10-30 11:34:49'),
(9, 'deep', '$2y$10$osjlkYLqcJfRSbNe2tnfeutU.DQpPO53GijhM1FLQnNF4HIqvEr5y', 'deep ghosh', 'baaaaaiji@gmail.com', '09826733689', 'supervisor', NULL, 2, NULL, NULL, 'uploads/supervisor_1761748757_520438112_631872409948130_1094216113222396609_n.jpg', '[\"uploads\\/supervisor_kyc_1761748757_0_bill_1 (3).pdf\"]', 'indian bank', '23456789987699', 'r4rewfe43432432', 'KOLKATA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-29 14:39:17', '2025-10-29 14:39:17'),
(10, 'field', '$2y$10$GDCtyGBwIcJYmu.Siws/9u7TkjS3sNZHCtrw2Edv8GRNUN6fQblGa', 'fiels', 'no@no.com', '09826767689', 'supervisor', NULL, 2, NULL, NULL, 'uploads/supervisor_1761811052_520438112_631872409948130_1094216113222396609_n.jpg', '[\"uploads\\/supervisor_kyc_1761811052_0_bill_1 (6).pdf\"]', 'indian bank', '234567899876', 'r4rewfe43432432', 'KOLKATA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-30 07:57:32', '2025-10-30 07:57:32'),
(16, 'gdsgsgsa', '$2y$10$oB4LP6V6BD92uRgFTZLt9uWPuJ0OhZalkh9q7BhQsOsbhgMWIvJLu', 'fiels1', 'nsso@no.com', '0983326767689', 'supervisor', NULL, 3, NULL, NULL, 'uploads/supervisor_1761811322_520438112_631872409948130_1094216113222396609_n.jpg', '[\"uploads\\/supervisor_kyc_1761811322_0_bill_1 (6).pdf\"]', 'indian bank', '234567899876', 'r4rewfe43432432', 'KOLKATA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-30 08:02:02', '2025-10-30 08:02:02'),
(17, 'driver', '$2y$10$zb1B88ujTxbEPtuEjIgSnebA6hnJjKHB81brMe/oFCHJb//9CJzgq', 'dirver', 'FTYSHAK@FHWIH.COM', '87678998888', 'driver', 9, NULL, 'L238', 'uploads/6903406acb2fa_520438112_631872409948130_1094216113222396609_n.jpg', 'uploads/6903406acbf89_520438112_631872409948130_1094216113222396609_n.jpg', '[\"uploads\\/6903406acdba1_bill_1 (6).pdf\"]', 'hdsadhsa', '45678987654567', 'fgb456789', 'dfghjjnbvcvbn', 92, 'wb26s4656', '76578677', '4567', 'diesel', 'uploads/6903406ace66b_520438112_631872409948130_1094216113222396609_n.jpg', 'uploads/6903406acf1fd_bill_1 (5).pdf', '2025-10-15', '2025-11-08', NULL, 'uploads/6903406ad1e2c_bill_1 (3).pdf', '2025-10-09', '2025-11-08', NULL, 'uploads/6903406ad3049_bill_1 (3).pdf', '2025-10-08', '2025-12-18', NULL, 'uploads/6903406ad467b_bill_1 (2).pdf', '2025-10-24', '2027-10-29', NULL, '234568', '2026-02-21', 'FTYGHKJLHG', NULL, 'uploads/6903406ad51d8_bill_1 (3).pdf', NULL, '2025-10-30 10:39:38', '2025-10-30 10:39:38'),
(18, 'driver2', '$2y$10$yZmsYeauNMXk.D0WYTAVhOk1Vpfmr8aSb/Db89oejeGWGA7h1EIRm', 'driver 2', 'dfgh@gmail.com', '45678987656', 'driver', 9, NULL, 'LG567898', NULL, NULL, NULL, 'indian bank', '234567899876', 'r4rewfe43432432', 'KOLKATA', NULL, 'WB45K0887', '56789', '4567', 'diesel', NULL, NULL, '2025-10-03', '2025-10-31', NULL, NULL, '0000-00-00', '0000-00-00', NULL, NULL, '2025-10-03', '2025-11-08', NULL, NULL, '2025-10-03', '2025-11-08', NULL, '234568', '2026-01-22', 'FTYGHKJLHG', NULL, NULL, NULL, '2025-10-30 20:49:07', '2025-10-30 20:49:07');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `seating_capacity` int(11) NOT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chassis_number` varchar(50) DEFAULT NULL,
  `engine_number` varchar(50) DEFAULT NULL,
  `fuel_type` enum('petrol','diesel','cng') DEFAULT NULL,
  `car_photo` varchar(255) DEFAULT NULL,
  `pollution_certificate` varchar(255) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `make`, `model`, `year`, `license_plate`, `seating_capacity`, `status`, `created_at`, `updated_at`, `chassis_number`, `engine_number`, `fuel_type`, `car_photo`, `pollution_certificate`, `driver_id`, `supervisor_id`, `branch_id`) VALUES
(22, 'Maruti Suzuki', 'Alto K10', 2024, 'AUTO000001', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(23, 'Maruti Suzuki', 'WagonR', 2024, 'AUTO000002', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(24, 'Maruti Suzuki', 'Celerio', 2024, 'AUTO000003', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(25, 'Maruti Suzuki', 'Swift', 2024, 'AUTO000004', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(26, 'Maruti Suzuki', 'Dzire', 2024, 'AUTO000005', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(27, 'Maruti Suzuki', 'Baleno', 2024, 'AUTO000006', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(28, 'Maruti Suzuki', 'Fronx', 2024, 'AUTO000007', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(29, 'Maruti Suzuki', 'Ignis', 2024, 'AUTO000008', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(30, 'Maruti Suzuki', 'Ciaz', 2024, 'AUTO000009', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(31, 'Maruti Suzuki', 'Brezza', 2024, 'AUTO000010', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(32, 'Maruti Suzuki', 'Grand Vitara', 2024, 'AUTO000011', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(33, 'Hyundai', 'Grand i10 Nios', 2024, 'AUTO000012', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(34, 'Hyundai', 'i20', 2024, 'AUTO000013', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(35, 'Hyundai', 'Aura', 2024, 'AUTO000014', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(36, 'Hyundai', 'Exter', 2024, 'AUTO000015', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(37, 'Hyundai', 'Venue', 2024, 'AUTO000016', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(38, 'Hyundai', 'Creta', 2024, 'AUTO000017', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(39, 'Hyundai', 'Verna', 2024, 'AUTO000018', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(40, 'Hyundai', 'Tucson', 2024, 'AUTO000019', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 16:40:47', NULL, NULL, 'petrol', NULL, NULL, 7, 1, 1),
(41, 'Tata', 'Tiago', 2024, 'AUTO000020', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(42, 'Tata', 'Tigor', 2024, 'AUTO000021', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(43, 'Tata', 'Altroz', 2024, 'AUTO000022', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(44, 'Tata', 'Punch', 2024, 'AUTO000023', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(45, 'Tata', 'Nexon', 2024, 'AUTO000024', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(46, 'Tata', 'Harrier', 2024, 'AUTO000025', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(47, 'Mahindra', 'Thar', 2024, 'AUTO000026', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(48, 'Mahindra', 'XUV3XO', 2024, 'AUTO000027', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(49, 'Mahindra', 'XUV300', 2024, 'AUTO000028', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(50, 'Mahindra', 'Bolero Neo', 2024, 'AUTO000029', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(51, 'Toyota', 'Glanza', 2024, 'AUTO000030', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(52, 'Toyota', 'Urban Cruiser Taisor', 2024, 'AUTO000031', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(53, 'Toyota', 'Hyryder', 2024, 'AUTO000032', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(54, 'Kia', 'Sonet', 2024, 'AUTO000033', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(55, 'Kia', 'Seltos', 2024, 'AUTO000034', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(56, 'Renault', 'Kwid', 2024, 'AUTO000035', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(57, 'Renault', 'Kiger', 2024, 'AUTO000036', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(58, 'Nissan', 'Magnite', 2024, 'AUTO000037', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(59, 'Honda', 'Amaze', 2024, 'AUTO000038', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(60, 'Honda', 'City', 2024, 'AUTO000039', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(61, 'Honda', 'Elevate', 2024, 'AUTO000040', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(62, 'Skoda', 'Slavia', 2024, 'AUTO000041', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(63, 'Skoda', 'Kushaq', 2024, 'AUTO000042', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(64, 'Volkswagen', 'Virtus', 2024, 'AUTO000043', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(65, 'Volkswagen', 'Taigun', 2024, 'AUTO000044', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(66, 'MG', 'Astor', 2024, 'AUTO000045', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(67, 'MG', 'Hector', 2024, 'AUTO000046', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(68, 'Isuzu', 'V-Cross', 2024, 'AUTO000047', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(69, 'Force', 'Gurkha', 2024, 'AUTO000048', 4, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(70, 'Maruti Suzuki', 'Ertiga', 2024, 'AUTO000049', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(71, 'Maruti Suzuki', 'Eeco (7-Seater)', 2024, 'AUTO000050', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(72, 'Toyota', 'Rumion', 2024, 'AUTO000051', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(73, 'Toyota', 'Innova Crysta', 2024, 'AUTO000052', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(74, 'Toyota', 'Innova Hycross', 2024, 'AUTO000053', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(75, 'Toyota', 'Fortuner', 2024, 'AUTO000054', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(76, 'Mahindra', 'Bolero', 2024, 'AUTO000055', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(77, 'Mahindra', 'Scorpio-N', 2024, 'AUTO000056', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(78, 'Mahindra', 'Scorpio Classic', 2024, 'AUTO000057', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(79, 'Mahindra', 'XUV700 (7-Seater)', 2024, 'AUTO000058', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(80, 'Mahindra', 'Marazzo', 2024, 'AUTO000059', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(81, 'Mahindra', 'Bolero Neo (7-Seater)', 2024, 'AUTO000060', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(82, 'Hyundai', 'Alcazar', 2024, 'AUTO000061', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(83, 'Kia', 'Carens', 2024, 'AUTO000062', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(84, 'Renault', 'Triber', 2024, 'AUTO000063', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(85, 'Tata', 'Safari', 2024, 'AUTO000064', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(86, 'MG', 'Hector Plus', 2024, 'AUTO000065', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(87, 'MG', 'Gloster', 2024, 'AUTO000066', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(88, 'Skoda', 'Kodiaq', 2024, 'AUTO000067', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(89, 'Jeep', 'Meridian', 2024, 'AUTO000068', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'petrol', NULL, NULL, NULL, 1, 1),
(90, 'Isuzu', 'MU-X', 2024, 'AUTO000069', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(91, 'Force', 'Trax Cruiser (7/9/13 configurations)', 2024, 'AUTO000070', 7, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(92, 'Force', 'Traveller 3350 (13-Seater)', 2024, 'AUTO000071', 13, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(93, 'Force', 'Trax Cruiser (13-Seater)', 2024, 'AUTO000072', 13, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1),
(94, 'Tata', 'Winger (13-Seater)', 2024, 'AUTO000073', 13, 'active', '2025-10-23 07:58:03', '2025-10-23 07:58:03', NULL, NULL, 'diesel', NULL, NULL, NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_driver_assignments`
--

CREATE TABLE `vehicle_driver_assignments` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_logs`
--

CREATE TABLE `whatsapp_logs` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message_type` enum('start_trip','end_trip') NOT NULL,
  `trip_id` int(11) NOT NULL,
  `status` enum('sent','failed','pending') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `whatsapp_logs`
--

INSERT INTO `whatsapp_logs` (`id`, `phone_number`, `message_type`, `trip_id`, `status`, `error_message`, `sent_at`) VALUES
(1, '919026268900', 'start_trip', 9, 'failed', 'Unknown error', '2025-10-29 19:40:24'),
(2, '918767899993', 'end_trip', 9, 'failed', 'Unknown error', '2025-10-29 19:40:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_driver_date` (`driver_id`,`date`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD PRIMARY KEY (`driver_id`);

--
-- Indexes for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supervisor_date` (`supervisor_id`,`date`);

--
-- Indexes for table `supervisor_branches`
--
ALTER TABLE `supervisor_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supervisor_branch` (`supervisor_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `trips_vehicle_fk` (`vehicle_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `vehicle_driver_assignments`
--
ALTER TABLE `vehicle_driver_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_assignment` (`vehicle_id`,`driver_id`,`status`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `whatsapp_logs`
--
ALTER TABLE `whatsapp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trip_id` (`trip_id`),
  ADD KEY `idx_phone_number` (`phone_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supervisor_branches`
--
ALTER TABLE `supervisor_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `vehicle_driver_assignments`
--
ALTER TABLE `vehicle_driver_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `whatsapp_logs`
--
ALTER TABLE `whatsapp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`);

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`id`),
  ADD CONSTRAINT `drivers_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD CONSTRAINT `driver_locations_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gps_tracking`
--
ALTER TABLE `gps_tracking`
  ADD CONSTRAINT `gps_tracking_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`),
  ADD CONSTRAINT `gps_tracking_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD CONSTRAINT `supervisors_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `supervisor_attendance`
--
ALTER TABLE `supervisor_attendance`
  ADD CONSTRAINT `supervisor_attendance_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `supervisor_branches`
--
ALTER TABLE `supervisor_branches`
  ADD CONSTRAINT `supervisor_branches_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supervisor_branches_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vehicle_driver_assignments`
--
ALTER TABLE `vehicle_driver_assignments`
  ADD CONSTRAINT `vehicle_driver_assignments_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `vehicle_driver_assignments_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`),
  ADD CONSTRAINT `vehicle_driver_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

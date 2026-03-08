-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 07, 2026 at 03:35 PM
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
-- Database: `boat_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('agency_dropdown_enabled', '0', '2026-03-06 14:00:03'),
('booking_enabled', '1', '2026-03-06 13:07:44'),
('currency_rate_source_url', 'https://www.xe.com', '2026-03-06 15:18:41'),
('usd_thb_rate', '35.000000', '2026-03-06 15:12:57'),
('usd_thb_rate_fetched_at', '', '2026-03-06 15:12:57');

-- --------------------------------------------------------

--
-- Table structure for table `boats`
--

CREATE TABLE `boats` (
  `id` int(11) NOT NULL,
  `boat_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_booked` tinyint(1) NOT NULL DEFAULT 0,
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boats`
--

INSERT INTO `boats` (`id`, `boat_name`, `description`, `image_path`, `is_active`, `is_booked`, `hourly_rate`, `stock`, `created_at`) VALUES
(1, 'Speedboat A', '', '', 0, 0, 600.00, 1, '2026-03-04 10:19:22'),
(2, 'Speedboat B', '', '', 0, 0, 600.00, 1, '2026-03-04 10:19:22'),
(3, 'Catamaran C', '', 'uploads/boats/boat_69a8412d52bb54.15396219.png', 1, 0, 600.00, 2, '2026-03-04 10:19:22'),
(4, 'Yacht D', '', 'uploads/boats/boat_69a8090f647249.14020504.jpg', 1, 0, 6000.00, 7, '2026-03-04 10:19:22');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `agency` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `boat_id` int(11) DEFAULT NULL,
  `boat` varchar(100) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `time_slot` enum('morning','afternoon') DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `name`, `email`, `phone`, `agency`, `location`, `boat_id`, `boat`, `booking_date`, `time_slot`, `start_time`, `end_time`, `total_price`, `date`, `time`, `status`, `admin_note`, `approved_at`, `is_archived`) VALUES
(1, 'ไฟไก', NULL, '0646715146', NULL, NULL, NULL, 'ไฟกฟไก', '2026-03-04', 'afternoon', '18:00:00', '19:00:00', NULL, '2026-03-04', '18:00:00', 'completed', '', '2026-03-04 18:28:18', 1),
(2, 'ไฟไก', 'kong132547@gmail.com', '0646715146', NULL, NULL, 3, 'Catamaran C', '2026-03-04', 'afternoon', '17:37:00', '18:37:00', NULL, '2026-03-04', '17:37:00', 'completed', '', '2026-03-04 17:33:47', 1),
(3, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', NULL, NULL, 4, 'Yacht D', '2026-03-05', 'afternoon', '18:37:00', '18:38:00', 600.00, '2026-03-05', '18:37:00', 'completed', '', '2026-03-04 18:37:52', 1),
(4, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', NULL, NULL, 1, 'Speedboat A', '2026-03-05', 'afternoon', '18:37:00', '18:38:00', 300.00, '2026-03-05', '18:37:00', 'completed', '', '2026-03-04 18:37:50', 1),
(5, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', NULL, NULL, 1, 'Speedboat A', '2026-03-05', 'afternoon', '18:50:00', '18:51:00', 600.00, '2026-03-05', '18:50:00', 'completed', '', '2026-03-04 18:52:35', 1),
(6, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', NULL, NULL, 1, 'Speedboat A', '2026-03-04', 'afternoon', '20:57:00', '20:58:00', 1200.00, '2026-03-04', '20:57:00', 'completed', '', '2026-03-04 20:57:32', 1),
(7, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', NULL, NULL, 4, 'Yacht D', '2026-03-04', 'afternoon', '20:01:00', '20:02:00', 600.00, '2026-03-04', '20:01:00', 'completed', '', '2026-03-04 20:59:41', 1),
(8, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', 'Agency A', 'Pier C', 4, 'Yacht D', '2026-03-04', 'morning', '08:00:00', '08:01:00', 600.00, '2026-03-04', '08:00:00', 'completed', '', '2026-03-06 19:46:13', 1),
(9, 'dawdawd', 'wd12wwd@gmail.com', '0646715146', 'Agency A', 'Pier C', 3, 'Catamaran C', '2026-03-04', 'morning', '08:00:00', '08:01:00', 600.00, '2026-03-04', '08:00:00', 'completed', '', '2026-03-06 19:46:08', 1),
(10, 'dawdawd', 'wd12wwd@gmail.com', '234234', '', 'Pier A', 3, 'Catamaran C', '0000-00-00', 'morning', '08:00:00', '09:00:00', 1599.00, '0000-00-00', '08:00:00', 'pending', NULL, NULL, 0),
(11, 'dawdawd', 'wd12wwd@gmail.com', '234234', '', 'Pier A', 3, 'Catamaran C', '3333-03-31', 'morning', '08:00:00', '09:00:00', 1599.00, '3333-03-31', '08:00:00', 'completed', 'dwad', '2026-03-07 21:34:43', 1);

-- --------------------------------------------------------

--
-- Table structure for table `booking_agency_options`
--

CREATE TABLE `booking_agency_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_agency_options`
--

INSERT INTO `booking_agency_options` (`id`, `option_name`, `is_active`, `sort_order`) VALUES
(1, 'Direct', 1, 1),
(2, 'Agency A', 1, 2),
(3, 'Agency B', 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `booking_attachments`
--

CREATE TABLE `booking_attachments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_duration_options`
--

CREATE TABLE `booking_duration_options` (
  `id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `minutes` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_duration_options`
--

INSERT INTO `booking_duration_options` (`id`, `label`, `minutes`, `price`, `is_active`, `sort_order`) VALUES
(1, '30 minutes', 1, 300.00, 1, 1),
(2, '1 hour', 1, 600.00, 1, 2),
(3, '2 hours', 1, 1200.00, 1, 3),
(4, '3 hours', 1800, 30006.00, 0, 4),
(5, '4 hours', 2400, 40008.00, 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `booking_history`
--

CREATE TABLE `booking_history` (
  `id` int(11) NOT NULL,
  `source_booking_id` int(11) NOT NULL,
  `boat_id` int(11) DEFAULT NULL,
  `boat` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `agency` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `time_slot` enum('morning','afternoon') DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `admin_note` varchar(255) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_location_options`
--

CREATE TABLE `booking_location_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_location_options`
--

INSERT INTO `booking_location_options` (`id`, `option_name`, `price`, `is_active`, `sort_order`) VALUES
(1, 'Pier A', 999.00, 1, 1),
(2, 'Pier B', 0.00, 1, 2),
(3, 'Pier C', 0.00, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `booking_name_options`
--

CREATE TABLE `booking_name_options` (
  `id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_name_options`
--

INSERT INTO `booking_name_options` (`id`, `option_name`, `is_active`, `sort_order`) VALUES
(1, 'Guest A', 1, 1),
(2, 'Guest B', 1, 2),
(3, 'Guest C', 1, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `boats`
--
ALTER TABLE `boats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_agency_options`
--
ALTER TABLE `booking_agency_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_attachments`
--
ALTER TABLE `booking_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_attachments_booking_id` (`booking_id`);

--
-- Indexes for table `booking_duration_options`
--
ALTER TABLE `booking_duration_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_history`
--
ALTER TABLE `booking_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_location_options`
--
ALTER TABLE `booking_location_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_name_options`
--
ALTER TABLE `booking_name_options`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `boats`
--
ALTER TABLE `boats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `booking_agency_options`
--
ALTER TABLE `booking_agency_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `booking_attachments`
--
ALTER TABLE `booking_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_duration_options`
--
ALTER TABLE `booking_duration_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_history`
--
ALTER TABLE `booking_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `booking_location_options`
--
ALTER TABLE `booking_location_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `booking_name_options`
--
ALTER TABLE `booking_name_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

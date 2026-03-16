-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 07, 2026 at 06:22 AM
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
-- Database: `church`
--

-- --------------------------------------------------------

--
-- Table structure for table `archives`
--

CREATE TABLE `archives` (
  `id` int(11) NOT NULL,
  `reference_type` enum('baptism','confirmation','wedding','funeral','member','volunteer','communion') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `archived_by` int(11) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `baptism_records`
--

CREATE TABLE `baptism_records` (
  `id` int(11) NOT NULL,
  `series_year` year(4) DEFAULT NULL,
  `record_number` varchar(30) DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `place_of_baptism` varchar(255) DEFAULT NULL,
  `baptism_type` enum('weekday','sunday_mass') DEFAULT NULL,
  `time_of_baptism` time DEFAULT NULL,
  `series_id` int(11) NOT NULL,
  `record_no` varchar(50) NOT NULL,
  `child_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `date_of_baptism` date DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_place_of_birth` varchar(255) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `mother_place_of_birth` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `godfather` varchar(150) DEFAULT NULL,
  `godfather_address` varchar(255) DEFAULT NULL,
  `godmother` varchar(150) DEFAULT NULL,
  `godmother_address` varchar(255) DEFAULT NULL,
  `other_sponsors` text DEFAULT NULL,
  `kind_of_marriage` enum('catholic','civil','protestant','aglipay','others') DEFAULT NULL,
  `kind_of_marriage_other` varchar(100) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parents_address` varchar(255) DEFAULT NULL,
  `godfather_name` varchar(180) DEFAULT NULL,
  `godmother_name` varchar(180) DEFAULT NULL,
  `minister_name` varchar(180) DEFAULT NULL,
  `scan_file` varchar(255) DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `baptism_records`
--

INSERT INTO `baptism_records` (`id`, `series_year`, `record_number`, `record_date`, `place_of_baptism`, `baptism_type`, `time_of_baptism`, `series_id`, `record_no`, `child_name`, `date_of_birth`, `place_of_birth`, `date_of_baptism`, `father_name`, `father_place_of_birth`, `mother_name`, `mother_place_of_birth`, `address`, `godfather`, `godfather_address`, `godmother`, `godmother_address`, `other_sponsors`, `kind_of_marriage`, `kind_of_marriage_other`, `minister`, `remarks`, `document_path`, `is_archived`, `created_by`, `created_at`, `updated_at`, `parents_address`, `godfather_name`, `godmother_name`, `minister_name`, `scan_file`, `booking_id`, `updated_by`) VALUES
(3, '2026', NULL, NULL, 'Our Lady of Peace and Good Voyage Parish', 'weekday', '08:00:00', 1, '2026-001', 'Juan Dela Cruz', '2025-12-25', 'Zamboanga City', '2026-02-26', 'Pedro dela Cruz', 'Zamboanga City', 'Maria dela Cruz', 'Zamboanga City', 'Purok 3, Tugbungan, Zamboanga City', 'Jose Reyes', 'Zamboanga City', 'Ana Reyes', 'Zamboanga City', 'Celso Lobregat', 'catholic', '', 'Fr. Juan Santos', 'This is just a test', '/church/uploads/baptism/baptism_2026_2026_001_1772102769.jpg', 0, 1, '2026-02-26 10:46:09', '2026-03-01 12:49:03', NULL, NULL, NULL, NULL, NULL, NULL, 2),
(4, '2026', NULL, NULL, 'Our Lady of Peace and Good Voyage Parish', NULL, NULL, 1, '2026-002', 'Chelsea Macalaya', '2026-01-08', NULL, '2026-02-21', 'Michael Macalaya', NULL, 'Helena Macalaya', NULL, 'Barigon, Tugbungan, Zamboanga City', 'Rolando Vicente', NULL, 'Gloria Makaraig', NULL, NULL, NULL, NULL, 'Fr. Pedro Kalungsod', 'Welcome to the Christian world.', '/church/uploads/baptism/baptism_2026_2026_002_1772161343.jpg', 0, 1, '2026-02-27 03:02:23', '2026-03-03 00:34:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `baptism_series`
--

CREATE TABLE `baptism_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `series_year` year(4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `baptism_series`
--

INSERT INTO `baptism_series` (`id`, `series_year`, `notes`, `created_by`, `created_at`) VALUES
(1, '2026', 'All records starting Jan 01, 2026', 1, '2026-02-26 18:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(30) DEFAULT NULL,
  `type` enum('baptism','wedding','funeral') NOT NULL,
  `status` enum('pending','confirmed','declined','completed') NOT NULL DEFAULT 'pending',
  `requestor_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `lead_time_acknowledged` tinyint(1) DEFAULT 0,
  `document_path` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `confirmed_date` date DEFAULT NULL,
  `confirmed_time` time DEFAULT NULL,
  `staff_notes` text DEFAULT NULL,
  `handled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `reference_number`, `type`, `status`, `requestor_name`, `contact_number`, `address`, `preferred_date`, `preferred_time`, `notes`, `lead_time_acknowledged`, `document_path`, `user_id`, `confirmed_date`, `confirmed_time`, `staff_notes`, `handled_by`, `created_at`, `updated_at`) VALUES
(2, 'B-20260304-B9178', 'baptism', 'completed', 'Ben Daniel Carpio', '09261085689', 'Camanchile', '2026-03-28', '09:00:00', NULL, 0, 'uploads/bookings/doc_1772593561_1f617b7e.png', 5, '2026-03-28', '09:00:00', 'alright', 2, '2026-03-04 03:06:01', '2026-03-04 03:10:06');

-- --------------------------------------------------------

--
-- Table structure for table `booking_details`
--

CREATE TABLE `booking_details` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `field_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_details`
--

INSERT INTO `booking_details` (`id`, `booking_id`, `field_key`, `field_value`) VALUES
(7, 2, 'child_name', 'Natasha Carpio'),
(8, 2, 'date_of_birth', '2026-01-02'),
(9, 2, 'father_name', 'Ben Daniel Carpio'),
(10, 2, 'mother_name', 'Eden Carpio'),
(11, 2, 'godfather_name', 'Benjo Maragisan'),
(12, 2, 'godmother_name', 'Aira Mendoza');

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `date` date NOT NULL,
  `time_schedule` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `collection_type` enum('cash','in-kind') NOT NULL DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collections`
--

INSERT INTO `collections` (`id`, `name`, `date`, `time_schedule`, `amount`, `collection_type`, `notes`, `created_by`, `created_at`) VALUES
(1, 'Sunday Mass Offertory', '2026-02-28', '7:00 AM Mass, 6:00 PM  Novena', 23000.00, 'cash', 'Deposited into the bank account of the church.', 4, '2026-02-28 01:55:07'),
(2, 'Sunday Mass Offertory', '2026-02-28', '7:00 AM Mass, 6:00 PM  Novena', 0.00, 'in-kind', '30 sacks of rice, 120 pcs can goods, 240pcs of coffee, milk, and other drink sachet.', 4, '2026-02-28 01:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `communion_records`
--

CREATE TABLE `communion_records` (
  `id` int(11) NOT NULL,
  `series_year` year(4) DEFAULT NULL,
  `series_id` int(11) NOT NULL,
  `record_no` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_communion` date DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `sponsor` varchar(150) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `baptism_parish` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communion_records`
--

INSERT INTO `communion_records` (`id`, `series_year`, `series_id`, `record_no`, `name`, `date_of_birth`, `date_of_communion`, `father_name`, `mother_name`, `address`, `sponsor`, `minister`, `baptism_date`, `baptism_parish`, `remarks`, `document_path`, `is_archived`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, '2026', 6, '2026-COM-001', 'Noel Gomez', '2013-01-07', '2026-03-01', 'Richard Gomez', 'Elena Gomez', 'Tugbungan, Zamboanga City', 'Bill Gates', 'Fr. Juan Santos', '2013-05-15', 'Our Lady of Peace and Good Voyage Parish', '', '/church/uploads/communion/communion_2026_2026_COM_001_1772854134.jpg', 0, 2, NULL, '2026-03-07 03:28:54', '2026-03-07 03:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `communion_series`
--

CREATE TABLE `communion_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `series_year` year(4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `communion_series`
--

INSERT INTO `communion_series` (`id`, `series_year`, `notes`, `created_by`, `created_at`) VALUES
(1, '2026', '', 2, '2026-03-07 11:24:40');

-- --------------------------------------------------------

--
-- Table structure for table `confirmation_records`
--

CREATE TABLE `confirmation_records` (
  `id` int(11) NOT NULL,
  `series_year` year(4) DEFAULT NULL,
  `record_number` varchar(30) DEFAULT NULL,
  `series_id` int(11) NOT NULL,
  `record_no` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_confirmation` date DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `sponsor` varchar(150) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `record_date` date DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `baptism_parish` varchar(255) DEFAULT NULL,
  `sponsor_name` varchar(180) DEFAULT NULL,
  `minister_name` varchar(180) DEFAULT NULL,
  `scan_file` varchar(255) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `confirmation_records`
--

INSERT INTO `confirmation_records` (`id`, `series_year`, `record_number`, `series_id`, `record_no`, `name`, `date_of_birth`, `date_of_confirmation`, `father_name`, `mother_name`, `address`, `sponsor`, `minister`, `remarks`, `document_path`, `is_archived`, `created_by`, `created_at`, `updated_at`, `record_date`, `baptism_date`, `baptism_parish`, `sponsor_name`, `minister_name`, `scan_file`, `updated_by`) VALUES
(1, '2026', NULL, 5, '2026-C-001', 'Clark Kent', '2013-11-23', '2026-02-04', 'James Kent', 'Macy Kent', 'Guiwan, Zamboanga City', 'Stephen Hawkins', 'Taylor Swift', 'Confirmed', '/church/uploads/confirmation/confirmation_2026_2026_C_001_1772174394.png', 0, 1, '2026-02-27 06:39:54', '2026-02-27 06:39:54', NULL, '2026-02-01', 'Our Lady of Peace and Good Voyage Parish', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `confirmation_series`
--

CREATE TABLE `confirmation_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `series_year` year(4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `confirmation_series`
--

INSERT INTO `confirmation_series` (`id`, `series_year`, `notes`, `created_by`, `created_at`) VALUES
(1, '2026', '', 1, '2026-02-27 14:36:24');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `donor_name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `donation_type` enum('cash','in-kind','sacramental_fee') NOT NULL DEFAULT 'cash',
  `description` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`id`, `donor_name`, `contact_number`, `amount`, `donation_type`, `description`, `date`, `created_by`, `created_at`) VALUES
(1, 'Piolo Pascual', '09456987123', 10000.00, 'cash', 'Sunday offertory donation', '2026-02-08', 4, '2026-02-28 01:49:45'),
(2, 'James Reid', '09351323695', 0.00, 'in-kind', '50 sacks of 5kg rice', '2026-02-28', 4, '2026-02-28 01:50:54');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `duration_hours` tinyint(3) UNSIGNED DEFAULT 1,
  `event_type` enum('wedding','baptism','funeral','communion','other') DEFAULT 'other',
  `time` time DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `name`, `description`, `date`, `end_date`, `duration_hours`, `event_type`, `time`, `created_by`, `booking_id`, `created_at`, `updated_at`) VALUES
(1, 'Love love love', 'Sanaol po', '2026-03-12', '2026-03-12', 2, 'wedding', '13:00:00', 2, NULL, '2026-03-02 06:52:24', '2026-03-02 07:03:08'),
(2, 'R.I.P', 'May the soul rest in peace.', '2026-03-02', NULL, 3, 'funeral', '15:30:00', 2, NULL, '2026-03-02 07:09:22', '2026-03-02 07:09:22'),
(3, 'Baptism — Ben Daniel Carpio', 'Auto-created from booking #2\nStaff notes: alright', '2026-03-28', NULL, 1, 'baptism', '09:00:00', 2, 2, '2026-03-04 03:09:59', '2026-03-04 03:09:59');

-- --------------------------------------------------------

--
-- Table structure for table `funeral_records`
--

CREATE TABLE `funeral_records` (
  `id` int(11) NOT NULL,
  `series_year` year(4) DEFAULT NULL,
  `record_number` varchar(30) DEFAULT NULL,
  `series_id` int(11) NOT NULL,
  `record_no` varchar(50) NOT NULL,
  `deceased_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_death` date DEFAULT NULL,
  `date_of_funeral` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `next_of_kin` varchar(150) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `record_date` date DEFAULT NULL,
  `place_of_burial` varchar(255) DEFAULT NULL,
  `next_of_kin_name` varchar(180) DEFAULT NULL,
  `next_of_kin_contact` varchar(60) DEFAULT NULL,
  `minister_name` varchar(180) DEFAULT NULL,
  `scan_file` varchar(255) DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `funeral_records`
--

INSERT INTO `funeral_records` (`id`, `series_year`, `record_number`, `series_id`, `record_no`, `deceased_name`, `date_of_birth`, `date_of_death`, `date_of_funeral`, `address`, `next_of_kin`, `minister`, `remarks`, `document_path`, `is_archived`, `created_by`, `created_at`, `updated_at`, `record_date`, `place_of_burial`, `next_of_kin_name`, `next_of_kin_contact`, `minister_name`, `scan_file`, `booking_id`, `updated_by`) VALUES
(1, '2026', NULL, 4, '2026-F-001', 'John Doe', '1978-01-24', '2026-02-18', '2026-02-22', 'Tetuan, Zamboanga City', 'Family of John Doe', 'Fr. Pedro Kalungsod', 'R.I.P', '/church/uploads/funeral/funeral_2026_2026_F_001_1772160649.jpg', 0, 1, '2026-02-27 02:50:49', '2026-02-27 02:50:49', NULL, 'Forest Lake', 'Jane Doe', '09123436789', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `funeral_series`
--

CREATE TABLE `funeral_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `series_year` year(4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `funeral_series`
--

INSERT INTO `funeral_series` (`id`, `series_year`, `notes`, `created_by`, `created_at`) VALUES
(1, '2026', '', 1, '2026-02-27 10:47:31');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `membership_status` enum('active','inactive') DEFAULT 'active',
  `joined_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `contact_number`, `email`, `address`, `membership_status`, `joined_date`, `notes`, `is_archived`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Noella Julliafe Samson', '09265605846', 'nfs@gmail.com', 'Johnston, San Jose, Zamboanga City', 'active', '2026-02-02', 'Powerpuff girls', 0, 1, '2026-02-27 16:47:35', '2026-02-27 16:47:35');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  `time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `name`, `reason`, `amount`, `date`, `time`, `notes`, `created_by`, `created_at`) VALUES
(1, 'Badong Makisig', 'Monthly electricity bill', 12500.00, '2026-02-21', NULL, 'Fully paid', 4, '2026-02-28 01:59:43');

-- --------------------------------------------------------

--
-- Table structure for table `record_series`
--

CREATE TABLE `record_series` (
  `id` int(11) NOT NULL,
  `type` enum('baptism','confirmation','wedding','funeral','communion') NOT NULL,
  `year` year(4) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `record_series`
--

INSERT INTO `record_series` (`id`, `type`, `year`, `created_by`, `created_at`) VALUES
(1, 'baptism', '2026', 1, '2026-02-26 10:45:51'),
(2, 'wedding', '2026', 1, '2026-02-26 15:27:14'),
(4, 'funeral', '2026', 1, '2026-02-27 02:47:31'),
(5, 'confirmation', '2026', 1, '2026-02-27 06:37:12'),
(6, 'communion', '2026', 2, '2026-03-07 03:25:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','clergy','finance','parishioner') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin@church.com', '$2y$10$7hlPkM3660zpEr/Ip.zuDenaM896O4aBtU.EswqT0cssOeOtwySYW', 'admin', '2026-02-26 06:25:28'),
(2, 'Clergy', 'clergy@church.com', '$2y$10$6FnKATWm5.GZ7sbB/.JqyeOhGv0rT2S88klHeGfWaocWPZfPCjSSm', 'clergy', '2026-02-26 08:15:54'),
(4, 'Finance', 'finance@church.com', '$2y$10$xjMdnjIDbT4FElStvzBMDOTHTUDyAB3JuYD5QjPH8YjTPdVkEOKQq', 'finance', '2026-02-28 00:48:37'),
(5, 'Benny Caprio', 'ben@gmail.com', '$2y$10$hRrLTzHzHFkHoXIiBCzd/efho9iKu.FPl1c3mRhf6P/uyf6OIOqwC', 'parishioner', '2026-03-03 08:17:14');

-- --------------------------------------------------------

--
-- Table structure for table `volunteers`
--

CREATE TABLE `volunteers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `joined_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteers`
--

INSERT INTO `volunteers` (`id`, `name`, `contact_number`, `email`, `address`, `role`, `joined_date`, `notes`, `is_archived`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Ivan King', '09058316845', 'ivank@gmail.com', 'Canelar, Zamboanga City', 'Choir', '2026-02-06', 'Maestro', 0, 1, '2026-02-27 16:50:21', '2026-02-27 16:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `wedding_records`
--

CREATE TABLE `wedding_records` (
  `id` int(11) NOT NULL,
  `series_year` year(4) DEFAULT NULL,
  `record_number` varchar(30) DEFAULT NULL,
  `series_id` int(11) NOT NULL,
  `record_no` varchar(50) NOT NULL,
  `groom_name` varchar(150) NOT NULL,
  `bride_name` varchar(150) NOT NULL,
  `date_of_wedding` date DEFAULT NULL,
  `groom_address` varchar(255) DEFAULT NULL,
  `bride_address` varchar(255) DEFAULT NULL,
  `principal_sponsor_male` varchar(150) DEFAULT NULL,
  `principal_sponsor_female` varchar(150) DEFAULT NULL,
  `minister` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `record_date` date DEFAULT NULL,
  `groom_dob` date DEFAULT NULL,
  `bride_dob` date DEFAULT NULL,
  `groom_father` varchar(180) DEFAULT NULL,
  `groom_mother` varchar(180) DEFAULT NULL,
  `bride_father` varchar(180) DEFAULT NULL,
  `bride_mother` varchar(180) DEFAULT NULL,
  `witness1_name` varchar(180) DEFAULT NULL,
  `witness2_name` varchar(180) DEFAULT NULL,
  `minister_name` varchar(180) DEFAULT NULL,
  `scan_file` varchar(255) DEFAULT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wedding_records`
--

INSERT INTO `wedding_records` (`id`, `series_year`, `record_number`, `series_id`, `record_no`, `groom_name`, `bride_name`, `date_of_wedding`, `groom_address`, `bride_address`, `principal_sponsor_male`, `principal_sponsor_female`, `minister`, `remarks`, `document_path`, `is_archived`, `created_by`, `created_at`, `updated_at`, `record_date`, `groom_dob`, `bride_dob`, `groom_father`, `groom_mother`, `bride_father`, `bride_mother`, `witness1_name`, `witness2_name`, `minister_name`, `scan_file`, `booking_id`, `updated_by`) VALUES
(3, '2026', NULL, 2, '2026-W-001', 'Poncho Pilato', 'Maria Makiling', '2026-02-23', 'Tugbungan, Zamboanga City', 'Guiwan, Zamboanga City', 'Jose Reyes', 'Ana Reyes', 'Fr. Juan Santos', '', '0', 0, 1, '2026-02-26 15:27:14', '2026-02-26 15:30:47', NULL, '1991-05-01', '1999-07-25', 'Baldo Pilato', 'Pilarita Pilato', 'Pedro Makiling', 'Marites Makiling', 'Kenneth Salagubang', 'Charmagne Salagubang', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `wedding_series`
--

CREATE TABLE `wedding_series` (
  `id` int(10) UNSIGNED NOT NULL,
  `series_year` year(4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wedding_series`
--

INSERT INTO `wedding_series` (`id`, `series_year`, `notes`, `created_by`, `created_at`) VALUES
(1, '2026', '', 1, '2026-02-26 23:19:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archives`
--
ALTER TABLE `archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `archived_by` (`archived_by`);

--
-- Indexes for table `baptism_records`
--
ALTER TABLE `baptism_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_series_year` (`series_year`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `baptism_series`
--
ALTER TABLE `baptism_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_baptism_series_year` (`series_year`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `handled_by` (`handled_by`),
  ADD KEY `idx_reference_number` (`reference_number`);

--
-- Indexes for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `communion_records`
--
ALTER TABLE `communion_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_series_year` (`series_year`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `communion_series`
--
ALTER TABLE `communion_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_communion_series_year` (`series_year`);

--
-- Indexes for table `confirmation_records`
--
ALTER TABLE `confirmation_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_series_year` (`series_year`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `confirmation_series`
--
ALTER TABLE `confirmation_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_confirmation_series_year` (`series_year`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_events_booking` (`booking_id`);

--
-- Indexes for table `funeral_records`
--
ALTER TABLE `funeral_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_series_year` (`series_year`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `funeral_series`
--
ALTER TABLE `funeral_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_funeral_series_year` (`series_year`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `record_series`
--
ALTER TABLE `record_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_series` (`type`,`year`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `wedding_records`
--
ALTER TABLE `wedding_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_series_year` (`series_year`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `wedding_series`
--
ALTER TABLE `wedding_series`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wedding_series_year` (`series_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archives`
--
ALTER TABLE `archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `baptism_records`
--
ALTER TABLE `baptism_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `baptism_series`
--
ALTER TABLE `baptism_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking_details`
--
ALTER TABLE `booking_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `communion_records`
--
ALTER TABLE `communion_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `communion_series`
--
ALTER TABLE `communion_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `confirmation_records`
--
ALTER TABLE `confirmation_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `confirmation_series`
--
ALTER TABLE `confirmation_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `funeral_records`
--
ALTER TABLE `funeral_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `funeral_series`
--
ALTER TABLE `funeral_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `record_series`
--
ALTER TABLE `record_series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `volunteers`
--
ALTER TABLE `volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wedding_records`
--
ALTER TABLE `wedding_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wedding_series`
--
ALTER TABLE `wedding_series`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `archives`
--
ALTER TABLE `archives`
  ADD CONSTRAINT `archives_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `baptism_records`
--
ALTER TABLE `baptism_records`
  ADD CONSTRAINT `baptism_records_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `record_series` (`id`),
  ADD CONSTRAINT `baptism_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD CONSTRAINT `booking_details_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collections`
--
ALTER TABLE `collections`
  ADD CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `communion_records`
--
ALTER TABLE `communion_records`
  ADD CONSTRAINT `communion_records_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `record_series` (`id`),
  ADD CONSTRAINT `communion_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `confirmation_records`
--
ALTER TABLE `confirmation_records`
  ADD CONSTRAINT `confirmation_records_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `record_series` (`id`),
  ADD CONSTRAINT `confirmation_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_events_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `funeral_records`
--
ALTER TABLE `funeral_records`
  ADD CONSTRAINT `funeral_records_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `record_series` (`id`),
  ADD CONSTRAINT `funeral_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `record_series`
--
ALTER TABLE `record_series`
  ADD CONSTRAINT `record_series_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD CONSTRAINT `volunteers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `wedding_records`
--
ALTER TABLE `wedding_records`
  ADD CONSTRAINT `wedding_records_ibfk_1` FOREIGN KEY (`series_id`) REFERENCES `record_series` (`id`),
  ADD CONSTRAINT `wedding_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2026 at 06:24 PM
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
-- Database: `psgrkcw_labsy`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_calendar`
--

CREATE TABLE `academic_calendar` (
  `id` int(11) NOT NULL,
  `calendar_date` date NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `day_order` varchar(10) NOT NULL,
  `type` enum('normal','holiday','exam','cultural','maintenance','other') NOT NULL DEFAULT 'normal',
  `block_reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_calendar`
--

INSERT INTO `academic_calendar` (`id`, `calendar_date`, `day_name`, `day_order`, `type`, `block_reason`, `blocked_by`, `blocked_at`) VALUES
(28, '2026-02-28', 'Saturday', 'Day 7', 'holiday', 'leave', NULL, '2026-02-27 11:59:35'),
(29, '2026-03-01', 'Sunday', 'Day 3', 'holiday', NULL, NULL, NULL),
(30, '2026-03-02', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(31, '2026-03-03', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(32, '2026-03-04', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(33, '2026-03-05', 'Thursday', 'Day 6', 'exam', NULL, NULL, NULL),
(34, '2026-03-06', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(35, '2026-03-07', 'Saturday', 'Day 1', 'holiday', NULL, NULL, NULL),
(36, '2026-03-08', 'Sunday', 'Day 1', 'holiday', NULL, NULL, NULL),
(37, '2026-03-09', 'Monday', 'Day 2', 'normal', NULL, NULL, NULL),
(38, '2026-03-10', 'Tuesday', 'Day 2', 'cultural', NULL, NULL, NULL),
(39, '2026-03-11', 'Wednesday', 'Day 3', 'normal', NULL, NULL, NULL),
(40, '2026-03-12', 'Thursday', 'Day 4', 'normal', NULL, NULL, NULL),
(41, '2026-03-13', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(42, '2026-03-14', 'Saturday', 'Day 5', 'other', 'Farwell', NULL, '2026-03-06 11:19:51'),
(43, '2026-03-15', 'Sunday', 'Day 5', 'holiday', NULL, NULL, NULL),
(44, '2026-03-16', 'Monday', 'Day 6', 'normal', NULL, NULL, NULL),
(45, '2026-03-17', 'Tuesday', 'Day 1', 'normal', NULL, NULL, NULL),
(46, '2026-03-18', 'Wednesday', 'Day 2', 'normal', NULL, NULL, NULL),
(47, '2026-03-19', 'Thursday', 'Day 3', 'normal', NULL, NULL, NULL),
(48, '2026-03-20', 'Friday', 'Day 4', 'normal', NULL, NULL, NULL),
(49, '2026-03-21', 'Saturday', 'Day 4', 'holiday', NULL, NULL, NULL),
(50, '2026-03-22', 'Sunday', 'Day 4', 'holiday', NULL, NULL, NULL),
(51, '2026-03-23', 'Monday', 'Day 5', 'normal', NULL, NULL, NULL),
(52, '2026-03-24', 'Tuesday', 'Day 6', 'normal', NULL, NULL, NULL),
(53, '2026-03-25', 'Wednesday', 'Day 1', 'normal', NULL, NULL, NULL),
(54, '2026-03-26', 'Thursday', 'Day 2', 'normal', NULL, NULL, NULL),
(55, '2026-03-27', 'Friday', 'Day 3', 'normal', NULL, NULL, NULL),
(56, '2026-03-28', 'Saturday', 'Day 3', 'holiday', NULL, NULL, NULL),
(57, '2026-03-29', 'Sunday', 'Day 3', 'holiday', NULL, NULL, NULL),
(58, '2026-03-30', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(59, '2026-03-31', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(60, '2026-01-01', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(61, '2026-01-02', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(62, '2026-01-03', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(63, '2026-01-04', 'Sunday', 'Day 3', 'normal', NULL, NULL, NULL),
(64, '2026-01-05', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(65, '2026-01-06', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(66, '2026-01-07', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(67, '2026-01-08', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(68, '2026-01-09', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(69, '2026-01-10', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(70, '2026-01-11', 'Sunday', 'Day 3', 'normal', NULL, NULL, NULL),
(71, '2026-01-12', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(72, '2026-01-13', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(73, '2026-01-14', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(74, '2026-01-15', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(75, '2026-01-16', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(76, '2026-01-17', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(77, '2026-01-18', 'Sunday', 'Day 3', 'normal', NULL, NULL, NULL),
(78, '2026-01-19', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(79, '2026-01-20', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(80, '2026-01-21', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(81, '2026-01-22', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(82, '2026-01-23', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(83, '2026-01-24', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(84, '2026-01-25', 'Sunday', 'Day 3', 'normal', NULL, NULL, NULL),
(85, '2026-01-26', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(86, '2026-01-27', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(87, '2026-01-28', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(88, '2026-01-29', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(89, '2026-01-30', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(90, '2026-01-31', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(91, '2027-03-01', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(92, '2027-03-02', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(93, '2027-03-03', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(94, '2027-03-04', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(95, '2027-03-05', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(96, '2027-03-06', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(97, '2027-03-07', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(98, '2027-03-08', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(99, '2027-03-09', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(100, '2027-03-10', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(101, '2027-03-11', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(102, '2027-03-12', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(103, '2027-03-13', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(104, '2027-03-14', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(105, '2027-03-15', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(106, '2027-03-16', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(107, '2027-03-17', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(108, '2027-03-18', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(109, '2027-03-19', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(110, '2027-03-20', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(111, '2027-03-21', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(112, '2027-03-22', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(113, '2027-03-23', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(114, '2027-03-24', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(115, '2027-03-25', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(116, '2027-03-26', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(117, '2027-03-27', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(118, '2027-03-28', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(119, '2027-03-29', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(120, '2027-03-30', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(121, '2027-03-31', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(122, '2026-04-12', 'Sunday', 'Day 3', 'normal', NULL, NULL, NULL),
(123, '2026-04-13', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(124, '2026-04-01', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(125, '2026-04-21', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(126, '2026-04-24', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(127, '2027-04-01', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(128, '2027-04-02', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(129, '2027-04-03', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(130, '2027-04-04', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(131, '2027-04-05', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(132, '2027-04-06', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(133, '2027-04-07', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(134, '2027-04-08', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(135, '2027-04-09', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(136, '2027-04-10', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(137, '2027-04-11', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(138, '2027-04-12', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(139, '2027-04-13', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(140, '2027-04-14', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(141, '2027-04-15', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(142, '2027-04-16', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(143, '2027-04-17', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(144, '2027-04-18', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(145, '2027-04-19', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(146, '2027-04-20', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(147, '2027-04-21', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(148, '2027-04-22', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(149, '2027-04-23', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(150, '2027-04-24', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(151, '2027-04-25', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(152, '2027-04-26', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(153, '2027-04-27', 'Tuesday', 'Day 4', 'normal', NULL, NULL, NULL),
(154, '2027-04-28', 'Wednesday', 'Day 5', 'normal', NULL, NULL, NULL),
(155, '2027-04-29', 'Thursday', 'Day 6', 'normal', NULL, NULL, NULL),
(156, '2027-04-30', 'Friday', 'Day 1', 'normal', NULL, NULL, NULL),
(157, '2026-04-02', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(158, '2026-04-03', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(159, '2026-04-04', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(160, '2026-04-05', 'Sunday', 'Day 3', 'holiday', NULL, NULL, NULL),
(161, '2026-04-06', 'Monday', 'Day 5', 'normal', NULL, NULL, NULL),
(162, '2026-04-07', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(163, '2026-04-08', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(164, '2026-04-09', 'Thursday', 'Day 1', 'exam', NULL, NULL, NULL),
(165, '2026-04-10', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(166, '2026-04-11', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(167, '2026-04-14', 'Tuesday', 'Day 3', 'normal', NULL, NULL, NULL),
(168, '2026-04-15', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(169, '2026-04-16', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(170, '2026-04-17', 'Friday', 'Day 2', 'normal', NULL, NULL, NULL),
(171, '2026-04-18', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(172, '2026-04-19', 'Sunday', 'Day 3', 'holiday', NULL, NULL, NULL),
(173, '2026-04-20', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(174, '2026-04-22', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(175, '2026-04-23', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(176, '2026-04-25', 'Saturday', 'Day 3', 'normal', NULL, NULL, NULL),
(177, '2026-04-26', 'Sunday', 'Day 3', 'holiday', NULL, NULL, NULL),
(178, '2026-04-27', 'Monday', 'Day 4', 'normal', NULL, NULL, NULL),
(179, '2026-04-28', 'Tuesday', 'Day 5', 'normal', NULL, NULL, NULL),
(180, '2026-04-29', 'Wednesday', 'Day 6', 'normal', NULL, NULL, NULL),
(181, '2026-04-30', 'Thursday', 'Day 1', 'normal', NULL, NULL, NULL),
(182, '2026-05-02', 'Saturday', 'Day 2', 'normal', NULL, NULL, NULL),
(183, '2026-05-03', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(184, '2026-05-31', 'Sunday', 'Day 2', 'holiday', NULL, NULL, NULL),
(185, '2026-05-04', 'Monday', 'Day 3', 'normal', NULL, NULL, NULL),
(186, '2026-06-12', 'Friday', 'Day 4', 'normal', NULL, NULL, NULL),
(187, '2026-06-26', 'Friday', 'Day 5', 'holiday', 'kk', 31, '2026-06-12 15:33:06');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `time_slot` varchar(20) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `period_number` int(11) DEFAULT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `head_approved` tinyint(1) DEFAULT 0,
  `head_approved_at` datetime DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `has_conflict` tinyint(1) NOT NULL DEFAULT 0,
  `conflict_reason` text DEFAULT NULL,
  `is_instant` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `staff_id`, `lab_id`, `booking_date`, `time_slot`, `start_time`, `end_time`, `period_number`, `purpose`, `status`, `head_approved`, `head_approved_at`, `admin_remarks`, `has_conflict`, `conflict_reason`, `is_instant`, `created_at`) VALUES
(74, 32, 3, '2026-05-04', 'Period 3', '10:10:00', '11:00:00', 3, 'ji', 'approved', 0, NULL, NULL, 0, NULL, 1, '2026-05-02 08:59:58');

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `trg_delete_conflict_request` AFTER DELETE ON `bookings` FOR EACH ROW BEGIN
    DELETE FROM conflict_requests 
    WHERE lab_id = OLD.lab_id 
    AND booking_date = OLD.booking_date 
    AND time_slot = OLD.time_slot;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `conflict_requests`
--

CREATE TABLE `conflict_requests` (
  `id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `time_slot` varchar(20) NOT NULL,
  `requesting_staff_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 30,
  `head_name` varchar(100) DEFAULT NULL,
  `head_email` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`id`, `lab_name`, `description`, `capacity`, `head_name`, `head_email`, `status`, `created_at`) VALUES
(3, 'Lab 01 - M block - PG', '', 67, NULL, NULL, 'active', '2026-03-01 16:29:56'),
(4, 'Lab 02 - M block - UG', '', 67, NULL, NULL, 'active', '2026-03-01 16:30:07'),
(5, 'Lab 03 - M block - MM', '', 67, NULL, NULL, 'active', '2026-03-01 16:30:21'),
(6, 'Lab 04 - M block - CA', '', 67, NULL, NULL, 'active', '2026-03-01 16:30:30'),
(7, 'Lab 05 - SMS block - Lang', '', 69, NULL, NULL, 'active', '2026-03-01 16:30:42'),
(8, 'Lab 08 - P block - Data Analytics', '', 67, NULL, NULL, 'active', '2026-03-01 16:31:01'),
(9, 'Lab 07 - L block - Fitech', '', 67, NULL, NULL, 'active', '2026-03-01 16:31:37'),
(10, 'Lab 09 - P block - Bio Informatics', '', 67, NULL, NULL, 'active', '2026-03-01 16:32:32'),
(11, 'Lab 10 - C block - IT', '', 67, NULL, NULL, 'active', '2026-03-01 16:33:03'),
(12, 'Lab 11 - C block - IT', '', 67, NULL, NULL, 'active', '2026-03-01 16:33:19'),
(13, 'Lab 12 - E block - IT', '', 67, NULL, NULL, 'active', '2026-03-01 16:33:45');

-- --------------------------------------------------------

--
-- Table structure for table `lab_heads`
--

CREATE TABLE `lab_heads` (
  `id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `head_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_heads`
--

INSERT INTO `lab_heads` (`id`, `lab_id`, `head_id`, `created_at`) VALUES
(36, 4, 33, '2026-05-02 08:47:23'),
(37, 5, 33, '2026-05-02 08:47:23'),
(38, 6, 33, '2026-05-02 08:47:23');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(58, 31, 'fd20926089396e815aee04298ae27a6dcf33385c3fcb198bd8d47a8465957690', '2026-06-13 21:45:11', 0, '2026-06-12 16:15:11');

-- --------------------------------------------------------

--
-- Table structure for table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_role` varchar(20) DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'unknown',
  `request_type` enum('login_issue','new_staff') NOT NULL DEFAULT 'login_issue',
  `message` text NOT NULL,
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `day_order` int(11) NOT NULL COMMENT '1-6',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `faculty_name` varchar(100) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `head_email` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `lab_id`, `day_order`, `start_time`, `end_time`, `class_name`, `subject`, `faculty_name`, `semester`, `head_email`, `created_by`, `is_active`, `created_at`) VALUES
(18, 3, 4, '08:10:00', '09:00:00', 'Zoo', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(19, 3, 4, '09:00:00', '09:50:00', 'Zoo', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(20, 3, 4, '10:10:00', '11:00:00', 'PCC', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(21, 3, 4, '11:00:00', '11:50:00', 'PCC', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(22, 3, 4, '11:50:00', '12:40:00', 'PCC', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(23, 3, 4, '12:50:00', '13:40:00', 'III B Com (CA) B', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(24, 3, 4, '13:40:00', '14:30:00', 'III B Com (AM)', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(25, 3, 4, '14:30:00', '15:20:00', 'III B Com (AM)', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(26, 3, 4, '15:40:00', '16:30:00', 'III BBA (RM)', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(27, 3, 4, '16:30:00', '17:20:00', 'III BBA (RM)', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(28, 4, 4, '09:00:00', '09:50:00', 'Professional Certification Course', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(29, 4, 4, '10:10:00', '11:00:00', 'Professional Certification Course', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(30, 4, 4, '11:00:00', '11:50:00', 'Professional Certification Course', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(31, 4, 4, '11:50:00', '12:40:00', 'Professional Certification Course', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(32, 4, 4, '13:40:00', '14:30:00', 'I B Com (CA) A', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(33, 4, 4, '14:30:00', '15:20:00', 'I B Com (CA) A', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(34, 4, 4, '15:40:00', '16:30:00', 'III B Com (CA) B', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05'),
(35, 4, 4, '16:30:00', '17:20:00', 'III B Com (CA) B', '', '', 'Even 2026', 'jananishreepriya.m@gmail.com', NULL, 1, '2026-03-11 16:31:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff','head') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `google_id`, `role`, `status`, `created_at`) VALUES
(31, 'Admin', 'jananishreepriya.07@gmail.com', '$2y$10$xDO8vtigZC.mUjWPI9SWGupxe0nJ9ySaHVI01upSYvBY3wkYBnBEy', '115360843228338971812', 'admin', 'active', '2026-05-02 08:29:47'),
(32, 'Janani Shree Priya M', '23bcs034@psgrkcw.ac.in', '$2y$10$N7ff2vwbxTErF9rAfSAMW.OHEawtMEPT1aL5FKANJg5LRdnoxf5NG', NULL, 'staff', 'active', '2026-05-02 08:47:04'),
(33, 'Janani Shree Priya M', '23bcs034@psgrkcw.ac.in', '$2y$10$s29ew1M7iIfuJzAZZO2fkOqhukcN.snBNdQi9A3hQmNLnfHC9DnHq', NULL, 'head', 'active', '2026-05-02 08:47:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_calendar`
--
ALTER TABLE `academic_calendar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `calendar_date` (`calendar_date`),
  ADD KEY `blocked_by` (`blocked_by`),
  ADD KEY `idx_date` (`calendar_date`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `booking_date` (`booking_date`),
  ADD KEY `idx_lab_date` (`lab_id`,`booking_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_date` (`booking_date`);

--
-- Indexes for table `conflict_requests`
--
ALTER TABLE `conflict_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`lab_id`,`booking_date`,`time_slot`,`status`),
  ADD KEY `requesting_staff_id` (`requesting_staff_id`),
  ADD KEY `idx_conflict_lookup` (`lab_id`,`booking_date`,`time_slot`,`status`),
  ADD KEY `idx_booking_lookup` (`lab_id`,`booking_date`,`time_slot`);

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_heads`
--
ALTER TABLE `lab_heads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lab_head` (`lab_id`,`head_id`),
  ADD KEY `head_id` (`head_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `source` (`source`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_lab_day` (`lab_id`,`day_order`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_role` (`email`,`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_calendar`
--
ALTER TABLE `academic_calendar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `conflict_requests`
--
ALTER TABLE `conflict_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `labs`
--
ALTER TABLE `labs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `lab_heads`
--
ALTER TABLE `lab_heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_calendar`
--
ALTER TABLE `academic_calendar`
  ADD CONSTRAINT `academic_calendar_ibfk_1` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conflict_requests`
--
ALTER TABLE `conflict_requests`
  ADD CONSTRAINT `conflict_requests_ibfk_1` FOREIGN KEY (`requesting_staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lab_heads`
--
ALTER TABLE `lab_heads`
  ADD CONSTRAINT `lab_heads_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_heads_ibfk_2` FOREIGN KEY (`head_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

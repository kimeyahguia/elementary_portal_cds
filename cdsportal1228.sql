-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 28, 2025 at 11:46 AM
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
-- Database: `cdsportal`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `user_id`, `full_name`, `username`, `email_address`, `password`, `date_created`, `address`) VALUES
(1, 1, 'Andrei Ruffy', 'admin1', 'admin@cds.edu.ph', '$2y$10$vBavTCJtqd0Podji2DZKZOf39XX45zz58bqcSGtkIXG4ON0z2KGp.', '2025-11-30 14:52:27', NULL),
(3, 34, 'Lorraine Ortizo', 'admin6', 'admin6@cds.ed.ph', '$2y$10$plPh2qFCSkffA4rEJwL.YeOgMphV4YaRJ5EGk/jwlCVIiLmD37TFK', '2025-11-30 16:39:45', '124 Villaviray Street Barangay 5');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('Academic','Event','Information','Urgent') DEFAULT 'Information',
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `target_audience` enum('all','students','teachers','parents') DEFAULT 'all',
  `date_posted` datetime DEFAULT current_timestamp(),
  `posted_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `content`, `category`, `priority`, `target_audience`, `date_posted`, `posted_by`, `is_active`) VALUES
(1, 'Christmas Party Celebration', 'Join us for our annual Christmas Party on December 20, 2025. Students are encouraged to wear their costumes and participate in various activities.', 'Event', 'high', 'all', '2025-11-17 02:26:17', 1, 1),
(2, 'Report Card Distribution', 'Report cards for the 2nd Quarter will be distributed on November 25, 2025. Parents are requested to check the grades and sign the cards.', 'Academic', 'medium', 'all', '2025-11-17 02:26:17', 1, 1),
(3, 'Faculty Meeting', 'All teachers are required to attend the monthly faculty meeting at 3:00 PM in the Conference Room.', 'Information', 'high', 'teachers', '2025-11-17 02:26:17', 1, 1),
(4, 'Project Presentation', 'Project Presentation will be on Novermber 26', 'Academic', 'high', 'students', '2025-11-26 19:32:38', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `appointment_type` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL,
  `status` enum('pending','approved','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `student_code`, `appointment_type`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(13, '25-00001', 'Tuition Payment', '2025-11-27', '08:00 AM', 'approved', '', '2025-11-23 00:03:11'),
(15, '25-00005', 'Tuition Payment', '2025-12-05', '09:00 AM', 'approved', '', '2025-12-01 21:18:21'),
(16, '25-00001', 'Tuition Payment', '2025-12-12', '08:00 AM', 'approved', '', '2025-12-02 00:20:52'),
(17, '25-00002', 'Tuition Payment', '2025-12-05', '08:00 AM', 'approved', '', '2025-12-02 02:54:43');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `teacher_code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') DEFAULT 'present',
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_code`, `subject_code`, `teacher_code`, `date`, `status`, `remarks`) VALUES
(1, '25-00001', 'MATH', 'TC001', '2025-11-18', 'absent', ''),
(2, '25-00001', 'FIL', 'TC004', '2025-11-19', 'absent', ''),
(3, '25-00001', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(4, '25-00001', 'MATH', 'TC001', '2025-08-04', 'present', NULL),
(5, '25-00001', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(6, '25-00001', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(7, '25-00001', 'MATH', 'TC001', '2025-08-12', 'present', NULL),
(8, '25-00001', 'MATH', 'TC001', '2025-08-18', 'late', NULL),
(9, '25-00001', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(10, '25-00001', 'MATH', 'TC001', '2025-08-25', 'present', NULL),
(11, '25-00001', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(12, '25-00001', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(13, '25-00001', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(14, '25-00001', 'MATH', 'TC001', '2025-09-08', 'present', NULL),
(15, '25-00001', 'MATH', 'TC001', '2025-09-09', 'present', NULL),
(16, '25-00001', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(17, '25-00001', 'MATH', 'TC001', '2025-09-16', 'absent', NULL),
(18, '25-00001', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(19, '25-00001', 'MATH', 'TC001', '2025-09-23', 'present', NULL),
(20, '25-00001', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(21, '25-00001', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(22, '25-00001', 'MATH', 'TC001', '2025-10-06', 'present', NULL),
(23, '25-00001', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(24, '25-00001', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(25, '25-00001', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(26, '25-00001', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(27, '25-00001', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(28, '25-00001', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(29, '25-00001', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(30, '25-00001', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(31, '25-00001', 'MATH', 'TC001', '2025-11-04', 'present', NULL),
(32, '25-00001', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(33, '25-00001', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(34, '25-00001', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(35, '25-00003', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(36, '25-00003', 'MATH', 'TC001', '2025-08-04', 'present', NULL),
(37, '25-00003', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(38, '25-00003', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(39, '25-00003', 'MATH', 'TC001', '2025-08-12', 'present', NULL),
(40, '25-00003', 'MATH', 'TC001', '2025-08-18', 'present', NULL),
(41, '25-00003', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(42, '25-00003', 'MATH', 'TC001', '2025-08-25', 'present', NULL),
(43, '25-00003', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(44, '25-00003', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(45, '25-00003', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(46, '25-00003', 'MATH', 'TC001', '2025-09-08', 'present', NULL),
(47, '25-00003', 'MATH', 'TC001', '2025-09-09', 'present', NULL),
(48, '25-00003', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(49, '25-00003', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(50, '25-00003', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(51, '25-00003', 'MATH', 'TC001', '2025-09-23', 'present', NULL),
(52, '25-00003', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(53, '25-00003', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(54, '25-00003', 'MATH', 'TC001', '2025-10-06', 'present', NULL),
(55, '25-00003', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(56, '25-00003', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(57, '25-00003', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(58, '25-00003', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(59, '25-00003', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(60, '25-00003', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(61, '25-00003', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(62, '25-00003', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(63, '25-00003', 'MATH', 'TC001', '2025-11-04', 'present', NULL),
(64, '25-00003', 'MATH', 'TC001', '2025-11-10', 'excused', NULL),
(65, '25-00003', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(66, '25-00003', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(67, '25-00004', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(68, '25-00004', 'MATH', 'TC001', '2025-08-04', 'late', NULL),
(69, '25-00004', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(70, '25-00004', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(71, '25-00004', 'MATH', 'TC001', '2025-08-12', 'absent', NULL),
(72, '25-00004', 'MATH', 'TC001', '2025-08-18', 'present', NULL),
(73, '25-00004', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(74, '25-00004', 'MATH', 'TC001', '2025-08-25', 'late', NULL),
(75, '25-00004', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(76, '25-00004', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(77, '25-00004', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(78, '25-00004', 'MATH', 'TC001', '2025-09-08', 'absent', NULL),
(79, '25-00004', 'MATH', 'TC001', '2025-09-09', 'present', NULL),
(80, '25-00004', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(81, '25-00004', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(82, '25-00004', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(83, '25-00004', 'MATH', 'TC001', '2025-09-23', 'late', NULL),
(84, '25-00004', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(85, '25-00004', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(86, '25-00004', 'MATH', 'TC001', '2025-10-06', 'present', NULL),
(87, '25-00004', 'MATH', 'TC001', '2025-10-07', 'absent', NULL),
(88, '25-00004', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(89, '25-00004', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(90, '25-00004', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(91, '25-00004', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(92, '25-00004', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(93, '25-00004', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(94, '25-00004', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(95, '25-00004', 'MATH', 'TC001', '2025-11-04', 'present', NULL),
(96, '25-00004', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(97, '25-00004', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(98, '25-00004', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(99, '25-00005', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(100, '25-00005', 'MATH', 'TC001', '2025-08-04', 'absent', NULL),
(101, '25-00005', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(102, '25-00005', 'MATH', 'TC001', '2025-08-11', 'absent', NULL),
(103, '25-00005', 'MATH', 'TC001', '2025-08-12', 'present', NULL),
(104, '25-00005', 'MATH', 'TC001', '2025-08-18', 'late', NULL),
(105, '25-00005', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(106, '25-00005', 'MATH', 'TC001', '2025-08-25', 'absent', NULL),
(107, '25-00005', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(108, '25-00005', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(109, '25-00005', 'MATH', 'TC001', '2025-09-02', 'absent', NULL),
(110, '25-00005', 'MATH', 'TC001', '2025-09-08', 'present', NULL),
(111, '25-00005', 'MATH', 'TC001', '2025-09-09', 'late', NULL),
(112, '25-00005', 'MATH', 'TC001', '2025-09-15', 'absent', NULL),
(113, '25-00005', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(114, '25-00005', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(115, '25-00005', 'MATH', 'TC001', '2025-09-23', 'absent', NULL),
(116, '25-00005', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(117, '25-00005', 'MATH', 'TC001', '2025-09-30', 'late', NULL),
(118, '25-00005', 'MATH', 'TC001', '2025-10-06', 'absent', NULL),
(119, '25-00005', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(120, '25-00005', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(121, '25-00005', 'MATH', 'TC001', '2025-10-14', 'absent', NULL),
(122, '25-00005', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(123, '25-00005', 'MATH', 'TC001', '2025-10-21', 'absent', NULL),
(124, '25-00005', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(125, '25-00005', 'MATH', 'TC001', '2025-10-28', 'late', NULL),
(126, '25-00005', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(127, '25-00005', 'MATH', 'TC001', '2025-11-04', 'absent', NULL),
(128, '25-00005', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(129, '25-00005', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(130, '25-00005', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(131, '25-00006', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(132, '25-00006', 'MATH', 'TC001', '2025-08-04', 'present', NULL),
(133, '25-00006', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(134, '25-00006', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(135, '25-00006', 'MATH', 'TC001', '2025-08-12', 'present', NULL),
(136, '25-00006', 'MATH', 'TC001', '2025-08-18', 'present', NULL),
(137, '25-00006', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(138, '25-00006', 'MATH', 'TC001', '2025-08-25', 'present', NULL),
(139, '25-00006', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(140, '25-00006', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(141, '25-00006', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(142, '25-00006', 'MATH', 'TC001', '2025-09-08', 'late', NULL),
(143, '25-00006', 'MATH', 'TC001', '2025-09-09', 'present', NULL),
(144, '25-00006', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(145, '25-00006', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(146, '25-00006', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(147, '25-00006', 'MATH', 'TC001', '2025-09-23', 'present', NULL),
(148, '25-00006', 'MATH', 'TC001', '2025-09-29', 'absent', NULL),
(149, '25-00006', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(150, '25-00006', 'MATH', 'TC001', '2025-10-06', 'present', NULL),
(151, '25-00006', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(152, '25-00006', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(153, '25-00006', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(154, '25-00006', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(155, '25-00006', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(156, '25-00006', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(157, '25-00006', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(158, '25-00006', 'MATH', 'TC001', '2025-11-03', 'absent', NULL),
(159, '25-00006', 'MATH', 'TC001', '2025-11-04', 'present', NULL),
(160, '25-00006', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(161, '25-00006', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(162, '25-00006', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(163, '25-00007', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(164, '25-00007', 'MATH', 'TC001', '2025-08-04', 'present', NULL),
(165, '25-00007', 'MATH', 'TC001', '2025-08-05', 'present', NULL),
(166, '25-00007', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(167, '25-00007', 'MATH', 'TC001', '2025-08-12', 'present', NULL),
(168, '25-00007', 'MATH', 'TC001', '2025-08-18', 'present', NULL),
(169, '25-00007', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(170, '25-00007', 'MATH', 'TC001', '2025-08-25', 'present', NULL),
(171, '25-00007', 'MATH', 'TC001', '2025-08-26', 'present', NULL),
(172, '25-00007', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(173, '25-00007', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(174, '25-00007', 'MATH', 'TC001', '2025-09-08', 'present', NULL),
(175, '25-00007', 'MATH', 'TC001', '2025-09-09', 'present', NULL),
(176, '25-00007', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(177, '25-00007', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(178, '25-00007', 'MATH', 'TC001', '2025-09-22', 'present', NULL),
(179, '25-00007', 'MATH', 'TC001', '2025-09-23', 'present', NULL),
(180, '25-00007', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(181, '25-00007', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(182, '25-00007', 'MATH', 'TC001', '2025-10-06', 'present', NULL),
(183, '25-00007', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(184, '25-00007', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(185, '25-00007', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(186, '25-00007', 'MATH', 'TC001', '2025-10-20', 'present', NULL),
(187, '25-00007', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(188, '25-00007', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(189, '25-00007', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(190, '25-00007', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(191, '25-00007', 'MATH', 'TC001', '2025-11-04', 'excused', NULL),
(192, '25-00007', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(193, '25-00007', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(194, '25-00007', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(195, '25-00008', 'MATH', 'TC001', '2025-08-01', 'present', NULL),
(196, '25-00008', 'MATH', 'TC001', '2025-08-04', 'present', NULL),
(197, '25-00008', 'MATH', 'TC001', '2025-08-05', 'late', NULL),
(198, '25-00008', 'MATH', 'TC001', '2025-08-11', 'present', NULL),
(199, '25-00008', 'MATH', 'TC001', '2025-08-12', 'absent', NULL),
(200, '25-00008', 'MATH', 'TC001', '2025-08-18', 'present', NULL),
(201, '25-00008', 'MATH', 'TC001', '2025-08-19', 'present', NULL),
(202, '25-00008', 'MATH', 'TC001', '2025-08-25', 'present', NULL),
(203, '25-00008', 'MATH', 'TC001', '2025-08-26', 'late', NULL),
(204, '25-00008', 'MATH', 'TC001', '2025-09-01', 'present', NULL),
(205, '25-00008', 'MATH', 'TC001', '2025-09-02', 'present', NULL),
(206, '25-00008', 'MATH', 'TC001', '2025-09-08', 'present', NULL),
(207, '25-00008', 'MATH', 'TC001', '2025-09-09', 'absent', NULL),
(208, '25-00008', 'MATH', 'TC001', '2025-09-15', 'present', NULL),
(209, '25-00008', 'MATH', 'TC001', '2025-09-16', 'present', NULL),
(210, '25-00008', 'MATH', 'TC001', '2025-09-22', 'late', NULL),
(211, '25-00008', 'MATH', 'TC001', '2025-09-23', 'present', NULL),
(212, '25-00008', 'MATH', 'TC001', '2025-09-29', 'present', NULL),
(213, '25-00008', 'MATH', 'TC001', '2025-09-30', 'present', NULL),
(214, '25-00008', 'MATH', 'TC001', '2025-10-06', 'absent', NULL),
(215, '25-00008', 'MATH', 'TC001', '2025-10-07', 'present', NULL),
(216, '25-00008', 'MATH', 'TC001', '2025-10-13', 'present', NULL),
(217, '25-00008', 'MATH', 'TC001', '2025-10-14', 'present', NULL),
(218, '25-00008', 'MATH', 'TC001', '2025-10-20', 'late', NULL),
(219, '25-00008', 'MATH', 'TC001', '2025-10-21', 'present', NULL),
(220, '25-00008', 'MATH', 'TC001', '2025-10-27', 'present', NULL),
(221, '25-00008', 'MATH', 'TC001', '2025-10-28', 'present', NULL),
(222, '25-00008', 'MATH', 'TC001', '2025-11-03', 'present', NULL),
(223, '25-00008', 'MATH', 'TC001', '2025-11-04', 'absent', NULL),
(224, '25-00008', 'MATH', 'TC001', '2025-11-10', 'present', NULL),
(225, '25-00008', 'MATH', 'TC001', '2025-11-11', 'present', NULL),
(226, '25-00008', 'MATH', 'TC001', '2025-11-17', 'present', NULL),
(227, '25-00006', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(228, '25-00002', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(229, '25-00007', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(230, '25-00005', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(231, '25-00008', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(232, '25-00004', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(233, '25-00001', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(234, '25-00003', 'MATH', 'TC001', '2025-11-28', 'present', ''),
(235, '25-00006', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(236, '25-00002', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(237, '25-00007', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(238, '25-00005', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(239, '25-00008', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(240, '25-00004', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(241, '25-00001', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(242, '25-00003', 'MATH', 'TC001', '2025-11-30', 'present', ''),
(243, '25-00011', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(244, '25-00011', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(245, '25-00011', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(246, '25-00011', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(247, '25-00011', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(248, '25-00011', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(249, '25-00011', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(250, '25-00011', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(251, '25-00011', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(252, '25-00011', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(253, '25-00011', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(254, '25-00011', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(255, '25-00011', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(256, '25-00011', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(257, '25-00011', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(258, '25-00011', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(259, '25-00011', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(260, '25-00011', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(261, '25-00011', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(262, '25-00011', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(263, '25-00011', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(264, '25-00011', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(265, '25-00011', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(266, '25-00011', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(267, '25-00011', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(268, '25-00011', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(269, '25-00011', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(270, '25-00011', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(271, '25-00011', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(272, '25-00011', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(273, '25-00011', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(274, '25-00011', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(275, '25-00011', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(276, '25-00011', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(277, '25-00011', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(278, '25-00012', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(279, '25-00012', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(280, '25-00012', 'MATH', 'TC009', '2025-08-05', 'late', NULL),
(281, '25-00012', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(282, '25-00012', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(283, '25-00012', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(284, '25-00012', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(285, '25-00012', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(286, '25-00012', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(287, '25-00012', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(288, '25-00012', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(289, '25-00012', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(290, '25-00012', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(291, '25-00012', 'MATH', 'TC009', '2025-09-15', 'late', NULL),
(292, '25-00012', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(293, '25-00012', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(294, '25-00012', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(295, '25-00012', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(296, '25-00012', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(297, '25-00012', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(298, '25-00012', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(299, '25-00012', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(300, '25-00012', 'MATH', 'TC009', '2025-10-14', 'absent', NULL),
(301, '25-00012', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(302, '25-00012', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(303, '25-00012', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(304, '25-00012', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(305, '25-00012', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(306, '25-00012', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(307, '25-00012', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(308, '25-00012', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(309, '25-00012', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(310, '25-00012', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(311, '25-00012', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(312, '25-00012', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(313, '25-00013', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(314, '25-00013', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(315, '25-00013', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(316, '25-00013', 'MATH', 'TC009', '2025-08-11', 'late', NULL),
(317, '25-00013', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(318, '25-00013', 'MATH', 'TC009', '2025-08-18', 'absent', NULL),
(319, '25-00013', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(320, '25-00013', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(321, '25-00013', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(322, '25-00013', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(323, '25-00013', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(324, '25-00013', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(325, '25-00013', 'MATH', 'TC009', '2025-09-09', 'late', NULL),
(326, '25-00013', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(327, '25-00013', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(328, '25-00013', 'MATH', 'TC009', '2025-09-22', 'absent', NULL),
(329, '25-00013', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(330, '25-00013', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(331, '25-00013', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(332, '25-00013', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(333, '25-00013', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(334, '25-00013', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(335, '25-00013', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(336, '25-00013', 'MATH', 'TC009', '2025-10-20', 'late', NULL),
(337, '25-00013', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(338, '25-00013', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(339, '25-00013', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(340, '25-00013', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(341, '25-00013', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(342, '25-00013', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(343, '25-00013', 'MATH', 'TC009', '2025-11-11', 'absent', NULL),
(344, '25-00013', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(345, '25-00013', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(346, '25-00013', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(347, '25-00013', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(348, '25-00014', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(349, '25-00014', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(350, '25-00014', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(351, '25-00014', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(352, '25-00014', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(353, '25-00014', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(354, '25-00014', 'MATH', 'TC009', '2025-08-19', 'late', NULL),
(355, '25-00014', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(356, '25-00014', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(357, '25-00014', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(358, '25-00014', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(359, '25-00014', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(360, '25-00014', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(361, '25-00014', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(362, '25-00014', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(363, '25-00014', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(364, '25-00014', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(365, '25-00014', 'MATH', 'TC009', '2025-09-29', 'absent', NULL),
(366, '25-00014', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(367, '25-00014', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(368, '25-00014', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(369, '25-00014', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(370, '25-00014', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(371, '25-00014', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(372, '25-00014', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(373, '25-00014', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(374, '25-00014', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(375, '25-00014', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(376, '25-00014', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(377, '25-00014', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(378, '25-00014', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(379, '25-00014', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(380, '25-00014', 'MATH', 'TC009', '2025-11-18', 'excused', NULL),
(381, '25-00014', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(382, '25-00014', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(383, '25-00015', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(384, '25-00015', 'MATH', 'TC009', '2025-08-04', 'absent', NULL),
(385, '25-00015', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(386, '25-00015', 'MATH', 'TC009', '2025-08-11', 'late', NULL),
(387, '25-00015', 'MATH', 'TC009', '2025-08-12', 'absent', NULL),
(388, '25-00015', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(389, '25-00015', 'MATH', 'TC009', '2025-08-19', 'late', NULL),
(390, '25-00015', 'MATH', 'TC009', '2025-08-25', 'absent', NULL),
(391, '25-00015', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(392, '25-00015', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(393, '25-00015', 'MATH', 'TC009', '2025-09-02', 'absent', NULL),
(394, '25-00015', 'MATH', 'TC009', '2025-09-08', 'late', NULL),
(395, '25-00015', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(396, '25-00015', 'MATH', 'TC009', '2025-09-15', 'absent', NULL),
(397, '25-00015', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(398, '25-00015', 'MATH', 'TC009', '2025-09-22', 'late', NULL),
(399, '25-00015', 'MATH', 'TC009', '2025-09-23', 'absent', NULL),
(400, '25-00015', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(401, '25-00015', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(402, '25-00015', 'MATH', 'TC009', '2025-10-06', 'absent', NULL),
(403, '25-00015', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(404, '25-00015', 'MATH', 'TC009', '2025-10-13', 'late', NULL),
(405, '25-00015', 'MATH', 'TC009', '2025-10-14', 'absent', NULL),
(406, '25-00015', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(407, '25-00015', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(408, '25-00015', 'MATH', 'TC009', '2025-10-27', 'late', NULL),
(409, '25-00015', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(410, '25-00015', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(411, '25-00015', 'MATH', 'TC009', '2025-11-04', 'absent', NULL),
(412, '25-00015', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(413, '25-00015', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(414, '25-00015', 'MATH', 'TC009', '2025-11-17', 'late', NULL),
(415, '25-00015', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(416, '25-00015', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(417, '25-00015', 'MATH', 'TC009', '2025-11-25', 'absent', NULL),
(418, '25-00021', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(419, '25-00021', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(420, '25-00021', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(421, '25-00021', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(422, '25-00021', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(423, '25-00021', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(424, '25-00021', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(425, '25-00021', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(426, '25-00021', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(427, '25-00021', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(428, '25-00021', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(429, '25-00021', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(430, '25-00021', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(431, '25-00021', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(432, '25-00021', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(433, '25-00021', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(434, '25-00021', 'MATH', 'TC009', '2025-09-23', 'excused', NULL),
(435, '25-00021', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(436, '25-00021', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(437, '25-00021', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(438, '25-00021', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(439, '25-00021', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(440, '25-00021', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(441, '25-00021', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(442, '25-00021', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(443, '25-00021', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(444, '25-00021', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(445, '25-00021', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(446, '25-00021', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(447, '25-00021', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(448, '25-00021', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(449, '25-00021', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(450, '25-00021', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(451, '25-00021', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(452, '25-00021', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(453, '25-00022', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(454, '25-00022', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(455, '25-00022', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(456, '25-00022', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(457, '25-00022', 'MATH', 'TC009', '2025-08-12', 'late', NULL),
(458, '25-00022', 'MATH', 'TC009', '2025-08-18', 'present', NULL),
(459, '25-00022', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(460, '25-00022', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(461, '25-00022', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(462, '25-00022', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(463, '25-00022', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(464, '25-00022', 'MATH', 'TC009', '2025-09-08', 'absent', NULL),
(465, '25-00022', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(466, '25-00022', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(467, '25-00022', 'MATH', 'TC009', '2025-09-16', 'present', NULL),
(468, '25-00022', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(469, '25-00022', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(470, '25-00022', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(471, '25-00022', 'MATH', 'TC009', '2025-09-30', 'late', NULL),
(472, '25-00022', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(473, '25-00022', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(474, '25-00022', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(475, '25-00022', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(476, '25-00022', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(477, '25-00022', 'MATH', 'TC009', '2025-10-21', 'absent', NULL),
(478, '25-00022', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(479, '25-00022', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(480, '25-00022', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(481, '25-00022', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(482, '25-00022', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(483, '25-00022', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(484, '25-00022', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(485, '25-00022', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(486, '25-00022', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(487, '25-00022', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(488, '25-00023', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(489, '25-00023', 'MATH', 'TC009', '2025-08-04', 'present', NULL),
(490, '25-00023', 'MATH', 'TC009', '2025-08-05', 'present', NULL),
(491, '25-00023', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(492, '25-00023', 'MATH', 'TC009', '2025-08-12', 'present', NULL),
(493, '25-00023', 'MATH', 'TC009', '2025-08-18', 'late', NULL),
(494, '25-00023', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(495, '25-00023', 'MATH', 'TC009', '2025-08-25', 'present', NULL),
(496, '25-00023', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(497, '25-00023', 'MATH', 'TC009', '2025-09-01', 'present', NULL),
(498, '25-00023', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(499, '25-00023', 'MATH', 'TC009', '2025-09-08', 'present', NULL),
(500, '25-00023', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(501, '25-00023', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(502, '25-00023', 'MATH', 'TC009', '2025-09-16', 'absent', NULL),
(503, '25-00023', 'MATH', 'TC009', '2025-09-22', 'present', NULL),
(504, '25-00023', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(505, '25-00023', 'MATH', 'TC009', '2025-09-29', 'present', NULL),
(506, '25-00023', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(507, '25-00023', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(508, '25-00023', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(509, '25-00023', 'MATH', 'TC009', '2025-10-13', 'present', NULL),
(510, '25-00023', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(511, '25-00023', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(512, '25-00023', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(513, '25-00023', 'MATH', 'TC009', '2025-10-27', 'late', NULL),
(514, '25-00023', 'MATH', 'TC009', '2025-10-28', 'present', NULL),
(515, '25-00023', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(516, '25-00023', 'MATH', 'TC009', '2025-11-04', 'present', NULL),
(517, '25-00023', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(518, '25-00023', 'MATH', 'TC009', '2025-11-11', 'absent', NULL),
(519, '25-00023', 'MATH', 'TC009', '2025-11-17', 'present', NULL),
(520, '25-00023', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(521, '25-00023', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(522, '25-00023', 'MATH', 'TC009', '2025-11-25', 'present', NULL),
(523, '25-00024', 'MATH', 'TC009', '2025-08-01', 'present', NULL),
(524, '25-00024', 'MATH', 'TC009', '2025-08-04', 'absent', NULL),
(525, '25-00024', 'MATH', 'TC009', '2025-08-05', 'late', NULL),
(526, '25-00024', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(527, '25-00024', 'MATH', 'TC009', '2025-08-12', 'absent', NULL),
(528, '25-00024', 'MATH', 'TC009', '2025-08-18', 'late', NULL),
(529, '25-00024', 'MATH', 'TC009', '2025-08-19', 'present', NULL),
(530, '25-00024', 'MATH', 'TC009', '2025-08-25', 'absent', NULL),
(531, '25-00024', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(532, '25-00024', 'MATH', 'TC009', '2025-09-01', 'late', NULL),
(533, '25-00024', 'MATH', 'TC009', '2025-09-02', 'present', NULL),
(534, '25-00024', 'MATH', 'TC009', '2025-09-08', 'absent', NULL),
(535, '25-00024', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(536, '25-00024', 'MATH', 'TC009', '2025-09-15', 'present', NULL),
(537, '25-00024', 'MATH', 'TC009', '2025-09-16', 'absent', NULL),
(538, '25-00024', 'MATH', 'TC009', '2025-09-22', 'late', NULL),
(539, '25-00024', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(540, '25-00024', 'MATH', 'TC009', '2025-09-29', 'absent', NULL),
(541, '25-00024', 'MATH', 'TC009', '2025-09-30', 'present', NULL),
(542, '25-00024', 'MATH', 'TC009', '2025-10-06', 'present', NULL),
(543, '25-00024', 'MATH', 'TC009', '2025-10-07', 'late', NULL),
(544, '25-00024', 'MATH', 'TC009', '2025-10-13', 'absent', NULL),
(545, '25-00024', 'MATH', 'TC009', '2025-10-14', 'present', NULL),
(546, '25-00024', 'MATH', 'TC009', '2025-10-20', 'present', NULL),
(547, '25-00024', 'MATH', 'TC009', '2025-10-21', 'absent', NULL),
(548, '25-00024', 'MATH', 'TC009', '2025-10-27', 'present', NULL),
(549, '25-00024', 'MATH', 'TC009', '2025-10-28', 'late', NULL),
(550, '25-00024', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(551, '25-00024', 'MATH', 'TC009', '2025-11-04', 'absent', NULL),
(552, '25-00024', 'MATH', 'TC009', '2025-11-10', 'present', NULL),
(553, '25-00024', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(554, '25-00024', 'MATH', 'TC009', '2025-11-17', 'late', NULL),
(555, '25-00024', 'MATH', 'TC009', '2025-11-18', 'present', NULL),
(556, '25-00024', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(557, '25-00024', 'MATH', 'TC009', '2025-11-25', 'absent', NULL),
(558, '25-00025', 'MATH', 'TC009', '2025-08-01', 'late', NULL),
(559, '25-00025', 'MATH', 'TC009', '2025-08-04', 'absent', NULL),
(560, '25-00025', 'MATH', 'TC009', '2025-08-05', 'absent', NULL),
(561, '25-00025', 'MATH', 'TC009', '2025-08-11', 'present', NULL),
(562, '25-00025', 'MATH', 'TC009', '2025-08-12', 'late', NULL),
(563, '25-00025', 'MATH', 'TC009', '2025-08-18', 'absent', NULL),
(564, '25-00025', 'MATH', 'TC009', '2025-08-19', 'late', NULL),
(565, '25-00025', 'MATH', 'TC009', '2025-08-25', 'absent', NULL),
(566, '25-00025', 'MATH', 'TC009', '2025-08-26', 'present', NULL),
(567, '25-00025', 'MATH', 'TC009', '2025-09-01', 'absent', NULL),
(568, '25-00025', 'MATH', 'TC009', '2025-09-02', 'late', NULL),
(569, '25-00025', 'MATH', 'TC009', '2025-09-08', 'absent', NULL),
(570, '25-00025', 'MATH', 'TC009', '2025-09-09', 'present', NULL),
(571, '25-00025', 'MATH', 'TC009', '2025-09-15', 'absent', NULL),
(572, '25-00025', 'MATH', 'TC009', '2025-09-16', 'late', NULL),
(573, '25-00025', 'MATH', 'TC009', '2025-09-22', 'absent', NULL),
(574, '25-00025', 'MATH', 'TC009', '2025-09-23', 'present', NULL),
(575, '25-00025', 'MATH', 'TC009', '2025-09-29', 'late', NULL),
(576, '25-00025', 'MATH', 'TC009', '2025-09-30', 'absent', NULL),
(577, '25-00025', 'MATH', 'TC009', '2025-10-06', 'absent', NULL),
(578, '25-00025', 'MATH', 'TC009', '2025-10-07', 'present', NULL),
(579, '25-00025', 'MATH', 'TC009', '2025-10-13', 'late', NULL),
(580, '25-00025', 'MATH', 'TC009', '2025-10-14', 'absent', NULL),
(581, '25-00025', 'MATH', 'TC009', '2025-10-20', 'absent', NULL),
(582, '25-00025', 'MATH', 'TC009', '2025-10-21', 'present', NULL),
(583, '25-00025', 'MATH', 'TC009', '2025-10-27', 'late', NULL),
(584, '25-00025', 'MATH', 'TC009', '2025-10-28', 'absent', NULL),
(585, '25-00025', 'MATH', 'TC009', '2025-11-03', 'present', NULL),
(586, '25-00025', 'MATH', 'TC009', '2025-11-04', 'absent', NULL),
(587, '25-00025', 'MATH', 'TC009', '2025-11-10', 'late', NULL),
(588, '25-00025', 'MATH', 'TC009', '2025-11-11', 'present', NULL),
(589, '25-00025', 'MATH', 'TC009', '2025-11-17', 'absent', NULL),
(590, '25-00025', 'MATH', 'TC009', '2025-11-18', 'late', NULL),
(591, '25-00025', 'MATH', 'TC009', '2025-11-24', 'present', NULL),
(592, '25-00025', 'MATH', 'TC009', '2025-11-25', 'absent', NULL),
(593, '25-00006', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(594, '25-00002', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(595, '25-00007', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(596, '25-00005', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(597, '25-00008', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(598, '25-00004', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(599, '25-00001', 'SCI', 'TC003', '2025-12-02', 'present', ''),
(600, '25-00003', 'SCI', 'TC003', '2025-12-02', 'present', '');

-- --------------------------------------------------------

--
-- Table structure for table `available_dates`
--

CREATE TABLE `available_dates` (
  `id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `max_slots` int(11) DEFAULT 10,
  `current_slots` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `available_dates`
--

INSERT INTO `available_dates` (`id`, `available_date`, `max_slots`, `current_slots`, `status`, `created_at`, `updated_at`) VALUES
(2, '2025-12-01', 10, 2, 'inactive', '2025-11-30 10:33:33', '2025-12-01 16:03:52'),
(3, '2025-12-05', 10, 1, 'active', '2025-12-01 16:04:01', '2025-12-01 16:04:23');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `request_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `purpose` text DEFAULT NULL,
  `copies` int(11) DEFAULT 1,
  `status` enum('requested','processing','approved','ready','claimed','rejected') DEFAULT 'requested',
  `date_requested` datetime DEFAULT current_timestamp(),
  `date_processed` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`request_id`, `student_code`, `document_type`, `purpose`, `copies`, `status`, `date_requested`, `date_processed`, `processed_by`, `notes`) VALUES
(2, '25-00001', 'Certificate of Enrollment', 'For scholarship', 1, 'claimed', '2025-11-18 20:31:25', NULL, NULL, NULL),
(9, '25-00007', 'Good Moral Certificate', 'For Scholarship', 1, 'claimed', '2025-11-22 20:55:54', '2025-12-01 20:16:43', 1, ''),
(16, '25-00005', 'Certificate of Enrollment', 'For scholarship', 1, 'claimed', '2025-12-01 21:17:20', '2025-12-02 00:54:50', 1, ''),
(17, '25-00010', 'Certificate of Enrollment', 'for scholarship', 1, 'processing', '2025-12-02 01:27:47', '2025-12-02 01:28:06', 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `id` int(11) NOT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  `enrollment_type` enum('inquiry','returning_student') NOT NULL DEFAULT 'inquiry',
  `appointment_id` varchar(20) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `parent_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` varchar(50) NOT NULL,
  `grade_level` varchar(50) NOT NULL,
  `current_grade_level` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `appointment_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment`
--

INSERT INTO `enrollment` (`id`, `student_code`, `enrollment_type`, `appointment_id`, `student_name`, `parent_name`, `email`, `phone`, `preferred_date`, `preferred_time`, `grade_level`, `current_grade_level`, `academic_year`, `message`, `status`, `appointment_notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 'inquiry', 'CDS4691', 'Irish Kaye Cuenca', 'Mari Dela Cruz', 'cuenca.irishkaye.d@gmail.com', '09661435915', '2025-12-01', '1:00 PM', 'Grade 1', NULL, NULL, 'none', 'pending', NULL, '2025-11-30 08:40:57', '2025-11-30 08:40:57'),
(2, NULL, 'inquiry', 'CDS5226', 'Irish Kaye Cuenca', 'Mari Dela Cruz', 'cuenca.irishkaye.d@gmail.com', '09661435915', '2025-11-30', '9:00 AM', 'Grade 2', NULL, NULL, 'nothing', 'cancelled', 'a', '2025-11-30 08:43:14', '2025-11-30 16:07:00'),
(3, NULL, 'inquiry', 'CDS1309', 'Naicel Apolona', 'Irish Kaye Cuenca', 'cuenca.irishkaye.d@gmail.com', '09661435915', '2025-12-03', '4:00 PM', 'Grade 5', NULL, NULL, 'None po.', '', 'aa', '2025-11-30 08:53:24', '2025-11-30 16:03:27'),
(4, NULL, 'inquiry', 'CDS7639', 'Naicel Apolona', 'Mari Dela Cruz', '23-74892@g.batstate-u.edu.ph', '09661435915', '2025-12-01', '9:00 AM', 'Preschool', NULL, NULL, 'wala', '', 'aaa', '2025-11-30 09:07:23', '2025-11-30 15:22:20'),
(5, NULL, 'inquiry', 'CDS2143', 'Irish Kaye Cuenca', 'Mari Dela Cruz', 'admin@gmail.com', '12345678', '2025-12-01', '3:00 PM', 'Grade 6', NULL, NULL, 'wala naman', '', 'Cancelled by admin during processing', '2025-11-30 10:00:47', '2025-11-30 15:51:40'),
(6, NULL, 'inquiry', 'CDS6189', 'Irish Kaye Cuenca', 'Mari Dela Cruz', 'kaye101704@gmail.com', '09661435915', '2025-12-01', '9:00 AM', 'Grade 1', NULL, NULL, 'none.', 'completed', '', '2025-11-30 10:35:25', '2025-11-30 14:05:58'),
(7, NULL, 'inquiry', 'CDS6583', 'Bronny James', 'Lebron James', 'bronny@gmail.com', '09123456789', '2025-12-01', '2:00 PM', 'Grade 6', NULL, NULL, 'May varsity po ba?', 'pending', NULL, '2025-12-01 02:30:14', '2025-12-01 02:30:14'),
(10, '25-00010', 'returning_student', 'CDS1273', 'Kevin Durant', 'Sierra Madre', 'sierra.madre@email.com', '09123456789', '2025-12-05', '10:00 AM', '6', '5', '2026-2027', '', 'pending', NULL, '2025-12-01 16:04:23', '2025-12-01 16:04:23');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `teacher_code` varchar(50) NOT NULL,
  `quarter` enum('1st','2nd','3rd','4th') NOT NULL,
  `written_work` decimal(5,2) DEFAULT 0.00 COMMENT '30% weight',
  `performance_task` decimal(5,2) DEFAULT 0.00 COMMENT '50% weight',
  `quarterly_exam` decimal(5,2) DEFAULT 0.00 COMMENT '20% weight',
  `final_grade` decimal(5,2) DEFAULT 0.00 COMMENT 'Computed final grade',
  `remarks` varchar(50) DEFAULT NULL,
  `date_recorded` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`grade_id`, `student_code`, `subject_code`, `teacher_code`, `quarter`, `written_work`, `performance_task`, `quarterly_exam`, `final_grade`, `remarks`, `date_recorded`) VALUES
(1, '25-00001', 'MATH', 'TC001', '1st', 88.00, 85.00, 100.00, 88.90, 'Passed', '2025-11-21 12:22:00'),
(2, '25-00001', 'ENG', 'TC002', '1st', 90.00, 89.00, 91.00, 90.00, 'Passed', '2025-11-17 02:26:17'),
(3, '25-00001', 'SCI', 'TC003', '1st', 87.00, 88.00, 86.00, 87.00, 'Passed', '2025-11-17 02:26:17'),
(4, '25-00001', 'FIL', 'TC004', '1st', 89.00, 92.00, 90.00, 91.00, 'Passed', '2025-11-17 02:26:17'),
(5, '25-00001', 'AP', 'TC005', '1st', 85.00, 86.00, 87.00, 86.00, 'Passed', '2025-11-17 02:26:17'),
(6, '25-00001', 'GMRC', 'TC006', '1st', 92.00, 91.00, 93.00, 92.00, 'Passed', '2025-11-17 02:26:17'),
(7, '25-00001', 'MAPEH', 'TC007', '1st', 90.00, 91.00, 89.00, 90.00, 'Passed', '2025-11-17 02:26:17'),
(8, '25-00001', 'EPP', 'TC008', '1st', 88.00, 89.00, 87.00, 88.00, 'Passed', '2025-11-17 02:26:17'),
(9, '25-00001', 'PENMAN', 'TC002', '1st', 91.00, 90.00, 92.00, 91.00, 'Passed', '2025-11-17 02:26:17'),
(10, '25-00001', 'COMP', 'TC008', '1st', 94.00, 95.00, 93.00, 94.00, 'Passed', '2025-11-17 02:26:17'),
(11, '25-00001', 'MATH', 'TC001', '2nd', 89.00, 87.00, 88.00, 87.80, 'Passed', '2025-11-30 18:33:08'),
(12, '25-00001', 'ENG', 'TC002', '2nd', 91.00, 90.00, 92.00, 91.00, 'Passed', '2025-11-17 02:26:17'),
(13, '25-00001', 'SCI', 'TC003', '2nd', 88.00, 89.00, 87.00, 88.30, 'Passed', '2025-12-02 09:12:22'),
(14, '25-00001', 'FIL', 'TC004', '2nd', 90.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-17 02:26:17'),
(15, '25-00001', 'AP', 'TC005', '2nd', 86.00, 87.00, 88.00, 87.00, 'Passed', '2025-11-17 02:26:17'),
(16, '25-00001', 'GMRC', 'TC006', '2nd', 93.00, 92.00, 94.00, 93.00, 'Passed', '2025-11-17 02:26:17'),
(17, '25-00001', 'MAPEH', 'TC007', '2nd', 91.00, 92.00, 90.00, 91.00, 'Passed', '2025-11-17 02:26:17'),
(18, '25-00001', 'EPP', 'TC008', '2nd', 89.00, 90.00, 88.00, 89.00, 'Passed', '2025-11-17 02:26:17'),
(19, '25-00001', 'PENMAN', 'TC002', '2nd', 92.00, 91.00, 93.00, 92.00, 'Passed', '2025-11-17 02:26:17'),
(20, '25-00001', 'COMP', 'TC008', '2nd', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-17 02:26:17'),
(21, '25-00001', 'MATH', 'TC001', '3rd', 90.00, 92.00, 91.00, 91.20, 'Passed', '2025-12-02 02:37:46'),
(22, '25-00001', 'MATH', 'TC001', '4th', 92.00, 94.00, 91.00, 92.80, 'Passed', '2025-12-02 02:37:59'),
(23, '25-00001', 'FIL', 'TC004', '3rd', 95.00, 95.00, 95.00, 95.00, 'Passed', '2025-11-19 08:43:53'),
(24, '25-00002', 'MATH', 'TC001', '4th', NULL, NULL, NULL, NULL, NULL, '2025-12-02 02:37:59'),
(25, '25-00003', 'MATH', 'TC001', '1st', 93.00, 94.00, 92.00, 93.30, 'Passed', '2025-11-21 12:22:00'),
(26, '25-00003', 'ENG', 'TC002', '1st', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-20 07:03:53'),
(27, '25-00003', 'SCI', 'TC003', '1st', 92.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-20 07:03:53'),
(28, '25-00003', 'FIL', 'TC004', '1st', 94.00, 95.00, 93.00, 94.00, 'Passed', '2025-11-20 07:03:53'),
(29, '25-00003', 'AP', 'TC005', '1st', 91.00, 92.00, 90.00, 91.00, 'Passed', '2025-11-20 07:03:53'),
(30, '25-00003', 'GMRC', 'TC006', '1st', 96.00, 97.00, 95.00, 96.00, 'Passed', '2025-11-20 07:03:53'),
(31, '25-00003', 'MAPEH', 'TC007', '1st', 93.00, 94.00, 92.00, 93.00, 'Passed', '2025-11-20 07:03:53'),
(32, '25-00003', 'EPP', 'TC008', '1st', 92.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-20 07:03:53'),
(33, '25-00003', 'PENMAN', 'TC002', '1st', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-20 07:03:53'),
(34, '25-00003', 'COMP', 'TC008', '1st', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(35, '25-00003', 'MATH', 'TC001', '2nd', 90.00, 91.00, 89.00, 90.30, 'Passed', '2025-11-30 18:33:08'),
(36, '25-00003', 'ENG', 'TC002', '2nd', 92.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-20 07:03:53'),
(37, '25-00003', 'SCI', 'TC003', '2nd', 89.00, 90.00, 88.00, 89.30, 'Passed', '2025-12-02 09:12:22'),
(38, '25-00003', 'FIL', 'TC004', '2nd', 91.00, 92.00, 90.00, 91.00, 'Passed', '2025-11-20 07:03:53'),
(39, '25-00003', 'AP', 'TC005', '2nd', 88.00, 89.00, 87.00, 88.00, 'Passed', '2025-11-20 07:03:53'),
(40, '25-00003', 'GMRC', 'TC006', '2nd', 94.00, 95.00, 93.00, 94.00, 'Passed', '2025-11-20 07:03:53'),
(41, '25-00003', 'MAPEH', 'TC007', '2nd', 90.00, 91.00, 89.00, 90.00, 'Passed', '2025-11-20 07:03:53'),
(42, '25-00003', 'EPP', 'TC008', '2nd', 89.00, 90.00, 88.00, 89.00, 'Passed', '2025-11-20 07:03:53'),
(43, '25-00003', 'PENMAN', 'TC002', '2nd', 92.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-20 07:03:53'),
(44, '25-00003', 'COMP', 'TC008', '2nd', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-20 07:03:53'),
(45, '25-00004', 'MATH', 'TC001', '1st', 78.00, 79.00, 77.00, 78.30, 'Passed', '2025-11-21 12:22:00'),
(46, '25-00004', 'ENG', 'TC002', '1st', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(47, '25-00004', 'SCI', 'TC003', '1st', 77.00, 78.00, 76.00, 77.00, 'Passed', '2025-11-20 07:03:53'),
(48, '25-00004', 'FIL', 'TC004', '1st', 82.00, 83.00, 81.00, 82.00, 'Passed', '2025-11-20 07:03:53'),
(49, '25-00004', 'AP', 'TC005', '1st', 79.00, 80.00, 78.00, 79.00, 'Passed', '2025-11-20 07:03:53'),
(50, '25-00004', 'GMRC', 'TC006', '1st', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(51, '25-00004', 'MAPEH', 'TC007', '1st', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(52, '25-00004', 'EPP', 'TC008', '1st', 81.00, 82.00, 80.00, 81.00, 'Passed', '2025-11-20 07:03:53'),
(53, '25-00004', 'PENMAN', 'TC002', '1st', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(54, '25-00004', 'COMP', 'TC008', '1st', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(55, '25-00004', 'MATH', 'TC001', '2nd', 82.00, 83.00, 81.00, 82.30, 'Passed', '2025-11-30 18:33:08'),
(56, '25-00004', 'ENG', 'TC002', '2nd', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(57, '25-00004', 'SCI', 'TC003', '2nd', 81.00, 82.00, 80.00, 81.30, 'Passed', '2025-12-02 09:12:22'),
(58, '25-00004', 'FIL', 'TC004', '2nd', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(59, '25-00004', 'AP', 'TC005', '2nd', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(60, '25-00004', 'GMRC', 'TC006', '2nd', 88.00, 89.00, 87.00, 88.00, 'Passed', '2025-11-20 07:03:53'),
(61, '25-00004', 'MAPEH', 'TC007', '2nd', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(62, '25-00004', 'EPP', 'TC008', '2nd', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(63, '25-00004', 'PENMAN', 'TC002', '2nd', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(64, '25-00004', 'COMP', 'TC008', '2nd', 87.00, 88.00, 86.00, 87.00, 'Passed', '2025-11-20 07:03:53'),
(65, '25-00005', 'MATH', 'TC001', '1st', 72.00, 73.00, 71.00, 72.30, 'Failed', '2025-11-21 12:22:00'),
(66, '25-00005', 'ENG', 'TC002', '1st', 76.00, 77.00, 75.00, 76.00, 'Passed', '2025-11-20 07:03:53'),
(67, '25-00005', 'SCI', 'TC003', '1st', 70.00, 71.00, 69.00, 70.00, 'Failed', '2025-11-20 07:03:53'),
(68, '25-00005', 'FIL', 'TC004', '1st', 78.00, 79.00, 77.00, 78.00, 'Passed', '2025-11-20 07:03:53'),
(69, '25-00005', 'AP', 'TC005', '1st', 74.00, 75.00, 73.00, 74.00, 'Failed', '2025-11-20 07:03:53'),
(70, '25-00005', 'GMRC', 'TC006', '1st', 82.00, 83.00, 81.00, 82.00, 'Passed', '2025-11-20 07:03:53'),
(71, '25-00005', 'MAPEH', 'TC007', '1st', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(72, '25-00005', 'EPP', 'TC008', '1st', 75.00, 76.00, 74.00, 75.00, 'Passed', '2025-11-20 07:03:53'),
(73, '25-00005', 'PENMAN', 'TC002', '1st', 77.00, 78.00, 76.00, 77.00, 'Passed', '2025-11-20 07:03:53'),
(74, '25-00005', 'COMP', 'TC008', '1st', 79.00, 80.00, 78.00, 79.00, 'Passed', '2025-11-20 07:03:53'),
(75, '25-00005', 'MATH', 'TC001', '2nd', 68.00, 69.00, 67.00, 68.30, 'Failed', '2025-11-30 18:33:08'),
(76, '25-00005', 'ENG', 'TC002', '2nd', 73.00, 74.00, 72.00, 73.00, 'Failed', '2025-11-20 07:03:53'),
(77, '25-00005', 'SCI', 'TC003', '2nd', 66.00, 67.00, 65.00, 66.30, 'Failed', '2025-12-02 09:12:22'),
(78, '25-00005', 'FIL', 'TC004', '2nd', 75.00, 76.00, 74.00, 75.00, 'Passed', '2025-11-20 07:03:53'),
(79, '25-00005', 'AP', 'TC005', '2nd', 71.00, 72.00, 70.00, 71.00, 'Failed', '2025-11-20 07:03:53'),
(80, '25-00005', 'GMRC', 'TC006', '2nd', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(81, '25-00005', 'MAPEH', 'TC007', '2nd', 77.00, 78.00, 76.00, 77.00, 'Passed', '2025-11-20 07:03:53'),
(82, '25-00005', 'EPP', 'TC008', '2nd', 72.00, 73.00, 71.00, 72.00, 'Failed', '2025-11-20 07:03:53'),
(83, '25-00005', 'PENMAN', 'TC002', '2nd', 74.00, 75.00, 73.00, 74.00, 'Failed', '2025-11-20 07:03:53'),
(84, '25-00005', 'COMP', 'TC008', '2nd', 76.00, 77.00, 75.00, 76.00, 'Passed', '2025-11-20 07:03:53'),
(85, '25-00006', 'MATH', 'TC001', '1st', 84.00, 85.00, 83.00, 84.30, 'Passed', '2025-11-21 12:22:00'),
(86, '25-00006', 'ENG', 'TC002', '1st', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(87, '25-00006', 'SCI', 'TC003', '1st', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(88, '25-00006', 'FIL', 'TC004', '1st', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(89, '25-00006', 'AP', 'TC005', '1st', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(90, '25-00006', 'GMRC', 'TC006', '1st', 87.00, 88.00, 86.00, 87.00, 'Passed', '2025-11-20 07:03:53'),
(91, '25-00006', 'MAPEH', 'TC007', '1st', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(92, '25-00006', 'EPP', 'TC008', '1st', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(93, '25-00006', 'PENMAN', 'TC002', '1st', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(94, '25-00006', 'COMP', 'TC008', '1st', 88.00, 89.00, 87.00, 88.00, 'Passed', '2025-11-20 07:03:53'),
(95, '25-00006', 'MATH', 'TC001', '2nd', 84.00, 85.00, 83.00, 84.30, 'Passed', '2025-11-30 18:33:08'),
(96, '25-00006', 'ENG', 'TC002', '2nd', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(97, '25-00006', 'SCI', 'TC003', '2nd', 83.00, 84.00, 82.00, 83.30, 'Passed', '2025-12-02 09:12:22'),
(98, '25-00006', 'FIL', 'TC004', '2nd', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(99, '25-00006', 'AP', 'TC005', '2nd', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(100, '25-00006', 'GMRC', 'TC006', '2nd', 88.00, 89.00, 87.00, 88.00, 'Passed', '2025-11-20 07:03:53'),
(101, '25-00006', 'MAPEH', 'TC007', '2nd', 86.00, 87.00, 85.00, 86.00, 'Passed', '2025-11-20 07:03:53'),
(102, '25-00006', 'EPP', 'TC008', '2nd', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(103, '25-00006', 'PENMAN', 'TC002', '2nd', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(104, '25-00006', 'COMP', 'TC008', '2nd', 89.00, 90.00, 88.00, 89.00, 'Passed', '2025-11-20 07:03:53'),
(105, '25-00007', 'MATH', 'TC001', '1st', 94.00, 95.00, 93.00, 94.30, 'Passed', '2025-11-21 12:22:00'),
(106, '25-00007', 'ENG', 'TC002', '1st', 96.00, 97.00, 95.00, 96.00, 'Passed', '2025-11-20 07:03:53'),
(107, '25-00007', 'SCI', 'TC003', '1st', 93.00, 94.00, 92.00, 93.00, 'Passed', '2025-11-20 07:03:53'),
(108, '25-00007', 'FIL', 'TC004', '1st', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-20 07:03:53'),
(109, '25-00007', 'AP', 'TC005', '1st', 92.00, 93.00, 91.00, 92.00, 'Passed', '2025-11-20 07:03:53'),
(110, '25-00007', 'GMRC', 'TC006', '1st', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(111, '25-00007', 'MAPEH', 'TC007', '1st', 95.00, 96.00, 94.00, 95.00, 'Passed', '2025-11-20 07:03:53'),
(112, '25-00007', 'EPP', 'TC008', '1st', 94.00, 95.00, 93.00, 94.00, 'Passed', '2025-11-20 07:03:53'),
(113, '25-00007', 'PENMAN', 'TC002', '1st', 96.00, 97.00, 95.00, 96.00, 'Passed', '2025-11-20 07:03:53'),
(114, '25-00007', 'COMP', 'TC008', '1st', 98.00, 99.00, 97.00, 98.00, 'Passed', '2025-11-20 07:03:53'),
(115, '25-00007', 'MATH', 'TC001', '2nd', 96.00, 97.00, 95.00, 96.30, 'Passed', '2025-11-30 18:33:08'),
(116, '25-00007', 'ENG', 'TC002', '2nd', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(117, '25-00007', 'SCI', 'TC003', '2nd', 95.00, 96.00, 94.00, 95.30, 'Passed', '2025-12-02 09:12:22'),
(118, '25-00007', 'FIL', 'TC004', '2nd', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(119, '25-00007', 'AP', 'TC005', '2nd', 94.00, 95.00, 93.00, 94.00, 'Passed', '2025-11-20 07:03:53'),
(120, '25-00007', 'GMRC', 'TC006', '2nd', 98.00, 99.00, 97.00, 98.00, 'Passed', '2025-11-20 07:03:53'),
(121, '25-00007', 'MAPEH', 'TC007', '2nd', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(122, '25-00007', 'EPP', 'TC008', '2nd', 96.00, 97.00, 95.00, 96.00, 'Passed', '2025-11-20 07:03:53'),
(123, '25-00007', 'PENMAN', 'TC002', '2nd', 97.00, 98.00, 96.00, 97.00, 'Passed', '2025-11-20 07:03:53'),
(124, '25-00007', 'COMP', 'TC008', '2nd', 99.00, 100.00, 98.00, 99.00, 'Passed', '2025-11-20 07:03:53'),
(125, '25-00008', 'MATH', 'TC001', '1st', 76.00, 77.00, 75.00, 76.30, 'Passed', '2025-11-21 12:22:00'),
(126, '25-00008', 'ENG', 'TC002', '1st', 78.00, 79.00, 77.00, 78.00, 'Passed', '2025-11-20 07:03:53'),
(127, '25-00008', 'SCI', 'TC003', '1st', 74.00, 75.00, 73.00, 74.00, 'Failed', '2025-11-20 07:03:53'),
(128, '25-00008', 'FIL', 'TC004', '1st', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(129, '25-00008', 'AP', 'TC005', '1st', 75.00, 76.00, 74.00, 75.00, 'Passed', '2025-11-20 07:03:53'),
(130, '25-00008', 'GMRC', 'TC006', '1st', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(131, '25-00008', 'MAPEH', 'TC007', '1st', 81.00, 82.00, 80.00, 81.00, 'Passed', '2025-11-20 07:03:53'),
(132, '25-00008', 'EPP', 'TC008', '1st', 77.00, 78.00, 76.00, 77.00, 'Passed', '2025-11-20 07:03:53'),
(133, '25-00008', 'PENMAN', 'TC002', '1st', 79.00, 80.00, 78.00, 79.00, 'Passed', '2025-11-20 07:03:53'),
(134, '25-00008', 'COMP', 'TC008', '1st', 82.00, 83.00, 81.00, 82.00, 'Passed', '2025-11-20 07:03:53'),
(135, '25-00008', 'MATH', 'TC001', '2nd', 78.00, 79.00, 77.00, 78.30, 'Passed', '2025-11-30 18:33:08'),
(136, '25-00008', 'ENG', 'TC002', '2nd', 80.00, 81.00, 79.00, 80.00, 'Passed', '2025-11-20 07:03:53'),
(137, '25-00008', 'SCI', 'TC003', '2nd', 76.00, 77.00, 75.00, 76.30, 'Passed', '2025-12-02 09:12:22'),
(138, '25-00008', 'FIL', 'TC004', '2nd', 82.00, 83.00, 81.00, 82.00, 'Passed', '2025-11-20 07:03:53'),
(139, '25-00008', 'AP', 'TC005', '2nd', 77.00, 78.00, 76.00, 77.00, 'Passed', '2025-11-20 07:03:53'),
(140, '25-00008', 'GMRC', 'TC006', '2nd', 85.00, 86.00, 84.00, 85.00, 'Passed', '2025-11-20 07:03:53'),
(141, '25-00008', 'MAPEH', 'TC007', '2nd', 83.00, 84.00, 82.00, 83.00, 'Passed', '2025-11-20 07:03:53'),
(142, '25-00008', 'EPP', 'TC008', '2nd', 79.00, 80.00, 78.00, 79.00, 'Passed', '2025-11-20 07:03:53'),
(143, '25-00008', 'PENMAN', 'TC002', '2nd', 81.00, 82.00, 80.00, 81.00, 'Passed', '2025-11-20 07:03:53'),
(144, '25-00008', 'COMP', 'TC008', '2nd', 84.00, 85.00, 83.00, 84.00, 'Passed', '2025-11-20 07:03:53'),
(145, '25-00002', 'MATH', 'TC001', '1st', 88.00, 86.00, 89.00, 87.20, 'Passed', '2025-11-21 12:22:00'),
(146, '25-00002', 'MATH', 'TC001', '2nd', 85.00, 85.00, 85.00, 85.00, 'Passed', '2025-11-30 18:33:08'),
(147, '25-00011', 'MATH', 'TC009', '1st', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-20 07:00:00'),
(148, '25-00011', 'ENG', 'TC002', '1st', 90.00, 92.00, 91.00, 91.30, 'Passed', '2025-11-20 07:00:00'),
(149, '25-00011', 'SCI', 'TC003', '1st', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-20 07:00:00'),
(150, '25-00011', 'FIL', 'TC004', '1st', 91.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-20 07:00:00'),
(151, '25-00011', 'AP', 'TC005', '1st', 89.00, 91.00, 90.00, 90.30, 'Passed', '2025-11-20 07:00:00'),
(152, '25-00011', 'GMRC', 'TC006', '1st', 95.00, 96.00, 95.00, 95.30, 'Passed', '2025-11-20 07:00:00'),
(153, '25-00011', 'MAPEH', 'TC007', '1st', 92.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-20 07:00:00'),
(154, '25-00011', 'EPP', 'TC008', '1st', 90.00, 91.00, 90.00, 90.30, 'Passed', '2025-11-20 07:00:00'),
(155, '25-00011', 'PENMAN', 'TC002', '1st', 93.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-20 07:00:00'),
(156, '25-00011', 'COMP', 'TC008', '1st', 96.00, 97.00, 96.00, 96.30, 'Passed', '2025-11-20 07:00:00'),
(157, '25-00011', 'MATH', 'TC009', '2nd', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-30 18:30:00'),
(158, '25-00011', 'ENG', 'TC002', '2nd', 91.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-30 18:30:00'),
(159, '25-00011', 'SCI', 'TC003', '2nd', 94.00, 96.00, 95.00, 95.30, 'Passed', '2025-11-30 18:30:00'),
(160, '25-00011', 'FIL', 'TC004', '2nd', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-30 18:30:00'),
(161, '25-00011', 'AP', 'TC005', '2nd', 90.00, 92.00, 91.00, 91.30, 'Passed', '2025-11-30 18:30:00'),
(162, '25-00011', 'GMRC', 'TC006', '2nd', 96.00, 97.00, 96.00, 96.30, 'Passed', '2025-11-30 18:30:00'),
(163, '25-00011', 'MAPEH', 'TC007', '2nd', 93.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-30 18:30:00'),
(164, '25-00011', 'EPP', 'TC008', '2nd', 91.00, 92.00, 91.00, 91.30, 'Passed', '2025-11-30 18:30:00'),
(165, '25-00011', 'PENMAN', 'TC002', '2nd', 94.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-30 18:30:00'),
(166, '25-00011', 'COMP', 'TC008', '2nd', 97.00, 98.00, 97.00, 97.30, 'Passed', '2025-11-30 18:30:00'),
(167, '25-00011', 'MATH', 'TC009', '3rd', 94.00, 96.00, 95.00, 95.30, 'Passed', '2025-12-02 10:00:00'),
(168, '25-00011', 'ENG', 'TC002', '3rd', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-12-02 10:00:00'),
(169, '25-00011', 'SCI', 'TC003', '3rd', 95.00, 97.00, 96.00, 96.30, 'Passed', '2025-12-02 10:00:00'),
(170, '25-00011', 'FIL', 'TC004', '3rd', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-12-02 10:00:00'),
(171, '25-00011', 'AP', 'TC005', '3rd', 91.00, 93.00, 92.00, 92.30, 'Passed', '2025-12-02 10:00:00'),
(172, '25-00011', 'GMRC', 'TC006', '3rd', 97.00, 98.00, 97.00, 97.30, 'Passed', '2025-12-02 10:00:00'),
(173, '25-00011', 'MAPEH', 'TC007', '3rd', 94.00, 95.00, 94.00, 94.30, 'Passed', '2025-12-02 10:00:00'),
(174, '25-00011', 'EPP', 'TC008', '3rd', 92.00, 93.00, 92.00, 92.30, 'Passed', '2025-12-02 10:00:00'),
(175, '25-00011', 'PENMAN', 'TC002', '3rd', 95.00, 96.00, 95.00, 95.30, 'Passed', '2025-12-02 10:00:00'),
(176, '25-00011', 'COMP', 'TC008', '3rd', 98.00, 99.00, 98.00, 98.30, 'Passed', '2025-12-02 10:00:00'),
(177, '25-00021', 'MATH', 'TC009', '1st', 91.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-20 07:00:00'),
(178, '25-00021', 'ENG', 'TC002', '1st', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-20 07:00:00'),
(179, '25-00021', 'SCI', 'TC003', '1st', 90.00, 92.00, 91.00, 91.30, 'Passed', '2025-11-20 07:00:00'),
(180, '25-00021', 'FIL', 'TC004', '1st', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-20 07:00:00'),
(181, '25-00021', 'AP', 'TC005', '1st', 88.00, 90.00, 89.00, 89.30, 'Passed', '2025-11-20 07:00:00'),
(182, '25-00021', 'GMRC', 'TC006', '1st', 94.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-20 07:00:00'),
(183, '25-00021', 'MAPEH', 'TC007', '1st', 91.00, 92.00, 91.00, 91.30, 'Passed', '2025-11-20 07:00:00'),
(184, '25-00021', 'EPP', 'TC008', '1st', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-11-20 07:00:00'),
(185, '25-00021', 'PENMAN', 'TC002', '1st', 92.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-20 07:00:00'),
(186, '25-00021', 'COMP', 'TC008', '1st', 95.00, 96.00, 95.00, 95.30, 'Passed', '2025-11-20 07:00:00'),
(187, '25-00021', 'MATH', 'TC009', '2nd', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-30 18:30:00'),
(188, '25-00021', 'ENG', 'TC002', '2nd', 94.00, 96.00, 95.00, 95.30, 'Passed', '2025-11-30 18:30:00'),
(189, '25-00021', 'SCI', 'TC003', '2nd', 91.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-30 18:30:00'),
(190, '25-00021', 'FIL', 'TC004', '2nd', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-11-30 18:30:00'),
(191, '25-00021', 'AP', 'TC005', '2nd', 89.00, 91.00, 90.00, 90.30, 'Passed', '2025-11-30 18:30:00'),
(192, '25-00021', 'GMRC', 'TC006', '2nd', 95.00, 96.00, 95.00, 95.30, 'Passed', '2025-11-30 18:30:00'),
(193, '25-00021', 'MAPEH', 'TC007', '2nd', 92.00, 93.00, 92.00, 92.30, 'Passed', '2025-11-30 18:30:00'),
(194, '25-00021', 'EPP', 'TC008', '2nd', 90.00, 91.00, 90.00, 90.30, 'Passed', '2025-11-30 18:30:00'),
(195, '25-00021', 'PENMAN', 'TC002', '2nd', 93.00, 94.00, 93.00, 93.30, 'Passed', '2025-11-30 18:30:00'),
(196, '25-00021', 'COMP', 'TC008', '2nd', 96.00, 97.00, 96.00, 96.30, 'Passed', '2025-11-30 18:30:00'),
(197, '25-00021', 'MATH', 'TC009', '3rd', 93.00, 95.00, 94.00, 94.30, 'Passed', '2025-12-02 10:00:00'),
(198, '25-00021', 'ENG', 'TC002', '3rd', 95.00, 97.00, 96.00, 96.30, 'Passed', '2025-12-02 10:00:00'),
(199, '25-00021', 'SCI', 'TC003', '3rd', 92.00, 94.00, 93.00, 93.30, 'Passed', '2025-12-02 10:00:00'),
(200, '25-00021', 'FIL', 'TC004', '3rd', 94.00, 96.00, 95.00, 95.30, 'Passed', '2025-12-02 10:00:00'),
(201, '25-00021', 'AP', 'TC005', '3rd', 90.00, 92.00, 91.00, 91.30, 'Passed', '2025-12-02 10:00:00'),
(202, '25-00021', 'GMRC', 'TC006', '3rd', 96.00, 97.00, 96.00, 96.30, 'Passed', '2025-12-02 10:00:00'),
(203, '25-00021', 'MAPEH', 'TC007', '3rd', 93.00, 94.00, 93.00, 93.30, 'Passed', '2025-12-02 10:00:00'),
(204, '25-00021', 'EPP', 'TC008', '3rd', 91.00, 92.00, 91.00, 91.30, 'Passed', '2025-12-02 10:00:00'),
(205, '25-00021', 'PENMAN', 'TC002', '3rd', 94.00, 95.00, 94.00, 94.30, 'Passed', '2025-12-02 10:00:00'),
(206, '25-00021', 'COMP', 'TC008', '3rd', 97.00, 98.00, 97.00, 97.30, 'Passed', '2025-12-02 10:00:00'),
(207, '25-00012', 'MATH', 'TC009', '1st', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(208, '25-00012', 'ENG', 'TC002', '1st', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(209, '25-00012', 'SCI', 'TC003', '1st', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-20 07:00:00'),
(210, '25-00012', 'FIL', 'TC004', '1st', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(211, '25-00012', 'AP', 'TC005', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(212, '25-00012', 'GMRC', 'TC006', '1st', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-20 07:00:00'),
(213, '25-00012', 'MAPEH', 'TC007', '1st', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(214, '25-00012', 'EPP', 'TC008', '1st', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(215, '25-00012', 'PENMAN', 'TC002', '1st', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(216, '25-00012', 'COMP', 'TC008', '1st', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-20 07:00:00'),
(217, '25-00012', 'MATH', 'TC009', '2nd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(218, '25-00012', 'ENG', 'TC002', '2nd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(219, '25-00012', 'SCI', 'TC003', '2nd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-30 18:30:00'),
(220, '25-00012', 'FIL', 'TC004', '2nd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(221, '25-00012', 'AP', 'TC005', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(222, '25-00012', 'GMRC', 'TC006', '2nd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-30 18:30:00'),
(223, '25-00012', 'MAPEH', 'TC007', '2nd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(224, '25-00012', 'EPP', 'TC008', '2nd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(225, '25-00012', 'PENMAN', 'TC002', '2nd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(226, '25-00012', 'COMP', 'TC008', '2nd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-11-30 18:30:00'),
(227, '25-00012', 'MATH', 'TC009', '3rd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(228, '25-00012', 'ENG', 'TC002', '3rd', 87.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(229, '25-00012', 'SCI', 'TC003', '3rd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-12-02 10:00:00'),
(230, '25-00012', 'FIL', 'TC004', '3rd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(231, '25-00012', 'AP', 'TC005', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(232, '25-00012', 'GMRC', 'TC006', '3rd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-12-02 10:00:00'),
(233, '25-00012', 'MAPEH', 'TC007', '3rd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(234, '25-00012', 'EPP', 'TC008', '3rd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(235, '25-00012', 'PENMAN', 'TC002', '3rd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(236, '25-00012', 'COMP', 'TC008', '3rd', 90.00, 91.00, 90.00, 90.30, 'Passed', '2025-12-02 10:00:00'),
(237, '25-00013', 'MATH', 'TC009', '1st', 80.00, 82.00, 81.00, 81.30, 'Passed', '2025-11-20 07:00:00'),
(238, '25-00013', 'ENG', 'TC002', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(239, '25-00013', 'SCI', 'TC003', '1st', 79.00, 81.00, 80.00, 80.30, 'Passed', '2025-11-20 07:00:00'),
(240, '25-00013', 'FIL', 'TC004', '1st', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(241, '25-00013', 'AP', 'TC005', '1st', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-20 07:00:00'),
(242, '25-00013', 'GMRC', 'TC006', '1st', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(243, '25-00013', 'MAPEH', 'TC007', '1st', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(244, '25-00013', 'EPP', 'TC008', '1st', 82.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-20 07:00:00'),
(245, '25-00013', 'PENMAN', 'TC002', '1st', 83.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(246, '25-00013', 'COMP', 'TC008', '1st', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(247, '25-00013', 'MATH', 'TC009', '2nd', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-30 18:30:00'),
(248, '25-00013', 'ENG', 'TC002', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(249, '25-00013', 'SCI', 'TC003', '2nd', 80.00, 82.00, 81.00, 81.30, 'Passed', '2025-11-30 18:30:00'),
(250, '25-00013', 'FIL', 'TC004', '2nd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(251, '25-00013', 'AP', 'TC005', '2nd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-30 18:30:00'),
(252, '25-00013', 'GMRC', 'TC006', '2nd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(253, '25-00013', 'MAPEH', 'TC007', '2nd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(254, '25-00013', 'EPP', 'TC008', '2nd', 83.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-30 18:30:00'),
(255, '25-00013', 'PENMAN', 'TC002', '2nd', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(256, '25-00013', 'COMP', 'TC008', '2nd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(257, '25-00013', 'MATH', 'TC009', '3rd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-12-02 10:00:00'),
(258, '25-00013', 'ENG', 'TC002', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(259, '25-00013', 'SCI', 'TC003', '3rd', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-12-02 10:00:00'),
(260, '25-00013', 'FIL', 'TC004', '3rd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(261, '25-00013', 'AP', 'TC005', '3rd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-12-02 10:00:00'),
(262, '25-00013', 'GMRC', 'TC006', '3rd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(263, '25-00013', 'MAPEH', 'TC007', '3rd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(264, '25-00013', 'EPP', 'TC008', '3rd', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-12-02 10:00:00'),
(265, '25-00013', 'PENMAN', 'TC002', '3rd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(266, '25-00013', 'COMP', 'TC008', '3rd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(267, '25-00014', 'MATH', 'TC009', '1st', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(268, '25-00014', 'ENG', 'TC002', '1st', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-20 07:00:00'),
(269, '25-00014', 'SCI', 'TC003', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(270, '25-00014', 'FIL', 'TC004', '1st', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(271, '25-00014', 'AP', 'TC005', '1st', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(272, '25-00014', 'GMRC', 'TC006', '1st', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-20 07:00:00'),
(273, '25-00014', 'MAPEH', 'TC007', '1st', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-20 07:00:00'),
(274, '25-00014', 'EPP', 'TC008', '1st', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(275, '25-00014', 'PENMAN', 'TC002', '1st', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(276, '25-00014', 'COMP', 'TC008', '1st', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-11-20 07:00:00'),
(277, '25-00014', 'MATH', 'TC009', '2nd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(278, '25-00014', 'ENG', 'TC002', '2nd', 87.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-30 18:30:00'),
(279, '25-00014', 'SCI', 'TC003', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(280, '25-00014', 'FIL', 'TC004', '2nd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(281, '25-00014', 'AP', 'TC005', '2nd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(282, '25-00014', 'GMRC', 'TC006', '2nd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-11-30 18:30:00'),
(283, '25-00014', 'MAPEH', 'TC007', '2nd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-30 18:30:00'),
(284, '25-00014', 'EPP', 'TC008', '2nd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(285, '25-00014', 'PENMAN', 'TC002', '2nd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(286, '25-00014', 'COMP', 'TC008', '2nd', 90.00, 91.00, 90.00, 90.30, 'Passed', '2025-11-30 18:30:00'),
(287, '25-00014', 'MATH', 'TC009', '3rd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(288, '25-00014', 'ENG', 'TC002', '3rd', 88.00, 90.00, 89.00, 89.30, 'Passed', '2025-12-02 10:00:00'),
(289, '25-00014', 'SCI', 'TC003', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(290, '25-00014', 'FIL', 'TC004', '3rd', 87.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(291, '25-00014', 'AP', 'TC005', '3rd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(292, '25-00014', 'GMRC', 'TC006', '3rd', 90.00, 91.00, 90.00, 90.30, 'Passed', '2025-12-02 10:00:00'),
(293, '25-00014', 'MAPEH', 'TC007', '3rd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-12-02 10:00:00'),
(294, '25-00014', 'EPP', 'TC008', '3rd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(295, '25-00014', 'PENMAN', 'TC002', '3rd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(296, '25-00014', 'COMP', 'TC008', '3rd', 91.00, 92.00, 91.00, 91.30, 'Passed', '2025-12-02 10:00:00'),
(297, '25-00022', 'MATH', 'TC009', '1st', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-20 07:00:00'),
(298, '25-00022', 'ENG', 'TC002', '1st', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(299, '25-00022', 'SCI', 'TC003', '1st', 80.00, 82.00, 81.00, 81.30, 'Passed', '2025-11-20 07:00:00'),
(300, '25-00022', 'FIL', 'TC004', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(301, '25-00022', 'AP', 'TC005', '1st', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(302, '25-00022', 'GMRC', 'TC006', '1st', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(303, '25-00022', 'MAPEH', 'TC007', '1st', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(304, '25-00022', 'EPP', 'TC008', '1st', 83.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(305, '25-00022', 'PENMAN', 'TC002', '1st', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(306, '25-00022', 'COMP', 'TC008', '1st', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-20 07:00:00'),
(307, '25-00022', 'MATH', 'TC009', '2nd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-30 18:30:00'),
(308, '25-00022', 'ENG', 'TC002', '2nd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(309, '25-00022', 'SCI', 'TC003', '2nd', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-30 18:30:00'),
(310, '25-00022', 'FIL', 'TC004', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(311, '25-00022', 'AP', 'TC005', '2nd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(312, '25-00022', 'GMRC', 'TC006', '2nd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(313, '25-00022', 'MAPEH', 'TC007', '2nd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(314, '25-00022', 'EPP', 'TC008', '2nd', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(315, '25-00022', 'PENMAN', 'TC002', '2nd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(316, '25-00022', 'COMP', 'TC008', '2nd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-30 18:30:00'),
(317, '25-00022', 'MATH', 'TC009', '3rd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-12-02 10:00:00'),
(318, '25-00022', 'ENG', 'TC002', '3rd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(319, '25-00022', 'SCI', 'TC003', '3rd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-12-02 10:00:00'),
(320, '25-00022', 'FIL', 'TC004', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(321, '25-00022', 'AP', 'TC005', '3rd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(322, '25-00022', 'GMRC', 'TC006', '3rd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(323, '25-00022', 'MAPEH', 'TC007', '3rd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(324, '25-00022', 'EPP', 'TC008', '3rd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(325, '25-00022', 'PENMAN', 'TC002', '3rd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(326, '25-00022', 'COMP', 'TC008', '3rd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-12-02 10:00:00'),
(327, '25-00023', 'MATH', 'TC009', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(328, '25-00023', 'ENG', 'TC002', '1st', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(329, '25-00023', 'SCI', 'TC003', '1st', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(330, '25-00023', 'FIL', 'TC004', '1st', 81.00, 83.00, 82.00, 82.30, 'Passed', '2025-11-20 07:00:00'),
(331, '25-00023', 'AP', 'TC005', '1st', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(332, '25-00023', 'GMRC', 'TC006', '1st', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-20 07:00:00'),
(333, '25-00023', 'MAPEH', 'TC007', '1st', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-20 07:00:00'),
(334, '25-00023', 'EPP', 'TC008', '1st', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-20 07:00:00'),
(335, '25-00023', 'PENMAN', 'TC002', '1st', 83.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-20 07:00:00'),
(336, '25-00023', 'COMP', 'TC008', '1st', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-20 07:00:00'),
(337, '25-00023', 'MATH', 'TC009', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(338, '25-00023', 'ENG', 'TC002', '2nd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(339, '25-00023', 'SCI', 'TC003', '2nd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(340, '25-00023', 'FIL', 'TC004', '2nd', 82.00, 84.00, 83.00, 83.30, 'Passed', '2025-11-30 18:30:00'),
(341, '25-00023', 'AP', 'TC005', '2nd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(342, '25-00023', 'GMRC', 'TC006', '2nd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-11-30 18:30:00'),
(343, '25-00023', 'MAPEH', 'TC007', '2nd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-11-30 18:30:00'),
(344, '25-00023', 'EPP', 'TC008', '2nd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-11-30 18:30:00'),
(345, '25-00023', 'PENMAN', 'TC002', '2nd', 84.00, 85.00, 84.00, 84.30, 'Passed', '2025-11-30 18:30:00'),
(346, '25-00023', 'COMP', 'TC008', '2nd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-11-30 18:30:00'),
(347, '25-00023', 'MATH', 'TC009', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(348, '25-00023', 'ENG', 'TC002', '3rd', 84.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(349, '25-00023', 'SCI', 'TC003', '3rd', 86.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(350, '25-00023', 'FIL', 'TC004', '3rd', 83.00, 85.00, 84.00, 84.30, 'Passed', '2025-12-02 10:00:00'),
(351, '25-00023', 'AP', 'TC005', '3rd', 85.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(352, '25-00023', 'GMRC', 'TC006', '3rd', 87.00, 88.00, 87.00, 87.30, 'Passed', '2025-12-02 10:00:00'),
(353, '25-00023', 'MAPEH', 'TC007', '3rd', 88.00, 89.00, 88.00, 88.30, 'Passed', '2025-12-02 10:00:00'),
(354, '25-00023', 'EPP', 'TC008', '3rd', 86.00, 87.00, 86.00, 86.30, 'Passed', '2025-12-02 10:00:00'),
(355, '25-00023', 'PENMAN', 'TC002', '3rd', 85.00, 86.00, 85.00, 85.30, 'Passed', '2025-12-02 10:00:00'),
(356, '25-00023', 'COMP', 'TC008', '3rd', 89.00, 90.00, 89.00, 89.30, 'Passed', '2025-12-02 10:00:00'),
(357, '25-00015', 'MATH', 'TC009', '1st', 70.00, 72.00, 71.00, 71.30, 'Failed', '2025-11-20 07:00:00'),
(358, '25-00015', 'ENG', 'TC002', '1st', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-20 07:00:00'),
(359, '25-00015', 'SCI', 'TC003', '1st', 68.00, 70.00, 69.00, 69.30, 'Failed', '2025-11-20 07:00:00'),
(360, '25-00015', 'FIL', 'TC004', '1st', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-20 07:00:00'),
(361, '25-00015', 'AP', 'TC005', '1st', 72.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-20 07:00:00'),
(362, '25-00015', 'GMRC', 'TC006', '1st', 80.00, 81.00, 80.00, 80.30, 'Passed', '2025-11-20 07:00:00'),
(363, '25-00015', 'MAPEH', 'TC007', '1st', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-11-20 07:00:00'),
(364, '25-00015', 'EPP', 'TC008', '1st', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-20 07:00:00'),
(365, '25-00015', 'PENMAN', 'TC002', '1st', 74.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-20 07:00:00'),
(366, '25-00015', 'COMP', 'TC008', '1st', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-20 07:00:00'),
(367, '25-00015', 'MATH', 'TC009', '2nd', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-30 18:30:00'),
(368, '25-00015', 'ENG', 'TC002', '2nd', 76.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-30 18:30:00'),
(369, '25-00015', 'SCI', 'TC003', '2nd', 71.00, 73.00, 72.00, 72.30, 'Failed', '2025-11-30 18:30:00'),
(370, '25-00015', 'FIL', 'TC004', '2nd', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-30 18:30:00'),
(371, '25-00015', 'AP', 'TC005', '2nd', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-30 18:30:00'),
(372, '25-00015', 'GMRC', 'TC006', '2nd', 81.00, 82.00, 81.00, 81.30, 'Passed', '2025-11-30 18:30:00'),
(373, '25-00015', 'MAPEH', 'TC007', '2nd', 79.00, 80.00, 79.00, 79.30, 'Passed', '2025-11-30 18:30:00'),
(374, '25-00015', 'EPP', 'TC008', '2nd', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-30 18:30:00'),
(375, '25-00015', 'PENMAN', 'TC002', '2nd', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-30 18:30:00'),
(376, '25-00015', 'COMP', 'TC008', '2nd', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-11-30 18:30:00'),
(377, '25-00015', 'MATH', 'TC009', '3rd', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-12-02 10:00:00'),
(378, '25-00015', 'ENG', 'TC002', '3rd', 78.00, 80.00, 79.00, 79.30, 'Passed', '2025-12-02 10:00:00'),
(379, '25-00015', 'SCI', 'TC003', '3rd', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-12-02 10:00:00'),
(380, '25-00015', 'FIL', 'TC004', '3rd', 77.00, 79.00, 78.00, 78.30, 'Passed', '2025-12-02 10:00:00'),
(381, '25-00015', 'AP', 'TC005', '3rd', 76.00, 78.00, 77.00, 77.30, 'Passed', '2025-12-02 10:00:00'),
(382, '25-00015', 'GMRC', 'TC006', '3rd', 82.00, 83.00, 82.00, 82.30, 'Passed', '2025-12-02 10:00:00'),
(383, '25-00015', 'MAPEH', 'TC007', '3rd', 80.00, 81.00, 80.00, 80.30, 'Passed', '2025-12-02 10:00:00'),
(384, '25-00015', 'EPP', 'TC008', '3rd', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-12-02 10:00:00'),
(385, '25-00015', 'PENMAN', 'TC002', '3rd', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-12-02 10:00:00'),
(386, '25-00015', 'COMP', 'TC008', '3rd', 79.00, 80.00, 79.00, 79.30, 'Passed', '2025-12-02 10:00:00'),
(387, '25-00024', 'MATH', 'TC009', '1st', 69.00, 71.00, 70.00, 70.30, 'Failed', '2025-11-20 07:00:00'),
(388, '25-00024', 'ENG', 'TC002', '1st', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-20 07:00:00'),
(389, '25-00024', 'SCI', 'TC003', '1st', 67.00, 69.00, 68.00, 68.30, 'Failed', '2025-11-20 07:00:00'),
(390, '25-00024', 'FIL', 'TC004', '1st', 72.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-20 07:00:00'),
(391, '25-00024', 'AP', 'TC005', '1st', 71.00, 73.00, 72.00, 72.30, 'Failed', '2025-11-20 07:00:00'),
(392, '25-00024', 'GMRC', 'TC006', '1st', 79.00, 80.00, 79.00, 79.30, 'Passed', '2025-11-20 07:00:00'),
(393, '25-00024', 'MAPEH', 'TC007', '1st', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-20 07:00:00'),
(394, '25-00024', 'EPP', 'TC008', '1st', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-20 07:00:00'),
(395, '25-00024', 'PENMAN', 'TC002', '1st', 73.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-20 07:00:00'),
(396, '25-00024', 'COMP', 'TC008', '1st', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-20 07:00:00'),
(397, '25-00024', 'MATH', 'TC009', '2nd', 72.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-30 18:30:00'),
(398, '25-00024', 'ENG', 'TC002', '2nd', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-30 18:30:00'),
(399, '25-00024', 'SCI', 'TC003', '2nd', 70.00, 72.00, 71.00, 71.30, 'Failed', '2025-11-30 18:30:00'),
(400, '25-00024', 'FIL', 'TC004', '2nd', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-30 18:30:00'),
(401, '25-00024', 'AP', 'TC005', '2nd', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-30 18:30:00'),
(402, '25-00024', 'GMRC', 'TC006', '2nd', 80.00, 81.00, 80.00, 80.30, 'Passed', '2025-11-30 18:30:00'),
(403, '25-00024', 'MAPEH', 'TC007', '2nd', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-11-30 18:30:00'),
(404, '25-00024', 'EPP', 'TC008', '2nd', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-30 18:30:00'),
(405, '25-00024', 'PENMAN', 'TC002', '2nd', 74.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-30 18:30:00'),
(406, '25-00024', 'COMP', 'TC008', '2nd', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-30 18:30:00'),
(407, '25-00024', 'MATH', 'TC009', '3rd', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-12-02 10:00:00'),
(408, '25-00024', 'ENG', 'TC002', '3rd', 77.00, 79.00, 78.00, 78.30, 'Passed', '2025-12-02 10:00:00'),
(409, '25-00024', 'SCI', 'TC003', '3rd', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-12-02 10:00:00'),
(410, '25-00024', 'FIL', 'TC004', '3rd', 76.00, 78.00, 77.00, 77.30, 'Passed', '2025-12-02 10:00:00'),
(411, '25-00024', 'AP', 'TC005', '3rd', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-12-02 10:00:00'),
(412, '25-00024', 'GMRC', 'TC006', '3rd', 81.00, 82.00, 81.00, 81.30, 'Passed', '2025-12-02 10:00:00'),
(413, '25-00024', 'MAPEH', 'TC007', '3rd', 79.00, 80.00, 79.00, 79.30, 'Passed', '2025-12-02 10:00:00'),
(414, '25-00024', 'EPP', 'TC008', '3rd', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-12-02 10:00:00'),
(415, '25-00024', 'PENMAN', 'TC002', '3rd', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-12-02 10:00:00'),
(416, '25-00024', 'COMP', 'TC008', '3rd', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-12-02 10:00:00'),
(417, '25-00025', 'MATH', 'TC009', '1st', 66.00, 68.00, 67.00, 67.30, 'Failed', '2025-11-20 07:00:00'),
(418, '25-00025', 'ENG', 'TC002', '1st', 71.00, 73.00, 72.00, 72.30, 'Failed', '2025-11-20 07:00:00'),
(419, '25-00025', 'SCI', 'TC003', '1st', 65.00, 67.00, 66.00, 66.30, 'Failed', '2025-11-20 07:00:00'),
(420, '25-00025', 'FIL', 'TC004', '1st', 70.00, 72.00, 71.00, 71.30, 'Failed', '2025-11-20 07:00:00'),
(421, '25-00025', 'AP', 'TC005', '1st', 69.00, 71.00, 70.00, 70.30, 'Failed', '2025-11-20 07:00:00'),
(422, '25-00025', 'GMRC', 'TC006', '1st', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-11-20 07:00:00'),
(423, '25-00025', 'MAPEH', 'TC007', '1st', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-20 07:00:00'),
(424, '25-00025', 'EPP', 'TC008', '1st', 73.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-20 07:00:00'),
(425, '25-00025', 'PENMAN', 'TC002', '1st', 71.00, 72.00, 71.00, 71.30, 'Failed', '2025-11-20 07:00:00'),
(426, '25-00025', 'COMP', 'TC008', '1st', 74.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-20 07:00:00'),
(427, '25-00025', 'MATH', 'TC009', '2nd', 68.00, 70.00, 69.00, 69.30, 'Failed', '2025-11-30 18:30:00'),
(428, '25-00025', 'ENG', 'TC002', '2nd', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-30 18:30:00'),
(429, '25-00025', 'SCI', 'TC003', '2nd', 67.00, 69.00, 68.00, 68.30, 'Failed', '2025-11-30 18:30:00'),
(430, '25-00025', 'FIL', 'TC004', '2nd', 72.00, 74.00, 73.00, 73.30, 'Failed', '2025-11-30 18:30:00'),
(431, '25-00025', 'AP', 'TC005', '2nd', 71.00, 73.00, 72.00, 72.30, 'Failed', '2025-11-30 18:30:00'),
(432, '25-00025', 'GMRC', 'TC006', '2nd', 78.00, 79.00, 78.00, 78.30, 'Passed', '2025-11-30 18:30:00'),
(433, '25-00025', 'MAPEH', 'TC007', '2nd', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-11-30 18:30:00'),
(434, '25-00025', 'EPP', 'TC008', '2nd', 74.00, 75.00, 74.00, 74.30, 'Failed', '2025-11-30 18:30:00'),
(435, '25-00025', 'PENMAN', 'TC002', '2nd', 72.00, 73.00, 72.00, 72.30, 'Failed', '2025-11-30 18:30:00'),
(436, '25-00025', 'COMP', 'TC008', '2nd', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-11-30 18:30:00'),
(437, '25-00025', 'MATH', 'TC009', '3rd', 72.00, 74.00, 73.00, 73.30, 'Failed', '2025-12-02 10:00:00'),
(438, '25-00025', 'ENG', 'TC002', '3rd', 75.00, 77.00, 76.00, 76.30, 'Passed', '2025-12-02 10:00:00'),
(439, '25-00025', 'SCI', 'TC003', '3rd', 70.00, 72.00, 71.00, 71.30, 'Failed', '2025-12-02 10:00:00'),
(440, '25-00025', 'FIL', 'TC004', '3rd', 74.00, 76.00, 75.00, 75.30, 'Passed', '2025-12-02 10:00:00'),
(441, '25-00025', 'AP', 'TC005', '3rd', 73.00, 75.00, 74.00, 74.30, 'Failed', '2025-12-02 10:00:00'),
(442, '25-00025', 'GMRC', 'TC006', '3rd', 79.00, 80.00, 79.00, 79.30, 'Passed', '2025-12-02 10:00:00'),
(443, '25-00025', 'MAPEH', 'TC007', '3rd', 77.00, 78.00, 77.00, 77.30, 'Passed', '2025-12-02 10:00:00'),
(444, '25-00025', 'EPP', 'TC008', '3rd', 75.00, 76.00, 75.00, 75.30, 'Passed', '2025-12-02 10:00:00'),
(445, '25-00025', 'PENMAN', 'TC002', '3rd', 73.00, 74.00, 73.00, 73.30, 'Failed', '2025-12-02 10:00:00'),
(446, '25-00025', 'COMP', 'TC008', '3rd', 76.00, 77.00, 76.00, 76.30, 'Passed', '2025-12-02 10:00:00'),
(447, '25-00002', 'SCI', 'TC003', '2nd', 70.00, 71.00, 74.00, 71.30, 'Failed', '2025-12-02 09:12:22');

-- --------------------------------------------------------

--
-- Table structure for table `grade_schedule_template`
--

CREATE TABLE `grade_schedule_template` (
  `template_id` int(11) NOT NULL,
  `grade_level` varchar(10) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `slot_id` int(11) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `room_type` varchar(50) DEFAULT 'Regular Classroom'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_schedule_template`
--

INSERT INTO `grade_schedule_template` (`template_id`, `grade_level`, `day_of_week`, `slot_id`, `subject_code`, `room_type`) VALUES
(1, '1', 'Monday', 1, 'GMRC', 'Room 101/102'),
(2, '1', 'Monday', 2, 'MATH', 'Room 101/102'),
(3, '1', 'Monday', 4, 'ENG', 'Room 101/102'),
(4, '1', 'Monday', 5, 'FIL', 'Room 101/102'),
(5, '1', 'Monday', 7, 'MTB', 'Room 101/102'),
(6, '1', 'Monday', 8, 'SCI', 'Room 101/102'),
(7, '1', 'Tuesday', 1, 'MATH', 'Room 101/102'),
(8, '1', 'Tuesday', 2, 'ENG', 'Room 101/102'),
(9, '1', 'Tuesday', 4, 'FIL', 'Room 101/102'),
(10, '1', 'Tuesday', 5, 'AP', 'Room 101/102'),
(11, '1', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(12, '1', 'Tuesday', 8, 'PENMAN', 'Room 101/102'),
(13, '1', 'Wednesday', 1, 'MTB', 'Room 101/102'),
(14, '1', 'Wednesday', 2, 'MATH', 'Room 101/102'),
(15, '1', 'Wednesday', 4, 'SCI', 'Room 101/102'),
(16, '1', 'Wednesday', 5, 'ENG', 'Room 101/102'),
(17, '1', 'Wednesday', 7, 'FIL', 'Room 101/102'),
(18, '1', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(19, '1', 'Thursday', 1, 'MATH', 'Room 101/102'),
(20, '1', 'Thursday', 2, 'AP', 'Room 101/102'),
(21, '1', 'Thursday', 4, 'ENG', 'Room 101/102'),
(22, '1', 'Thursday', 5, 'MTB', 'Room 101/102'),
(23, '1', 'Thursday', 7, 'MAPEH', 'Gymnasium'),
(24, '1', 'Thursday', 8, 'SCI', 'Room 101/102'),
(25, '1', 'Friday', 1, 'FIL', 'Room 101/102'),
(26, '1', 'Friday', 2, 'MATH', 'Room 101/102'),
(27, '1', 'Friday', 4, 'ENG', 'Room 101/102'),
(28, '1', 'Friday', 5, 'GMRC', 'Room 101/102'),
(29, '1', 'Friday', 7, 'PENMAN', 'Room 101/102'),
(30, '1', 'Friday', 8, 'AP', 'Room 101/102'),
(31, '2', 'Monday', 1, 'MATH', 'Room 201/202'),
(32, '2', 'Monday', 2, 'ENG', 'Room 201/202'),
(33, '2', 'Monday', 4, 'FIL', 'Room 201/202'),
(34, '2', 'Monday', 5, 'MTB', 'Room 201/202'),
(35, '2', 'Monday', 7, 'SCI', 'Room 201/202'),
(36, '2', 'Monday', 8, 'GMRC', 'Room 201/202'),
(37, '2', 'Tuesday', 1, 'ENG', 'Room 201/202'),
(38, '2', 'Tuesday', 2, 'MATH', 'Room 201/202'),
(39, '2', 'Tuesday', 4, 'AP', 'Room 201/202'),
(40, '2', 'Tuesday', 5, 'FIL', 'Room 201/202'),
(41, '2', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(42, '2', 'Tuesday', 8, 'MTB', 'Room 201/202'),
(43, '2', 'Wednesday', 1, 'MATH', 'Room 201/202'),
(44, '2', 'Wednesday', 2, 'SCI', 'Room 201/202'),
(45, '2', 'Wednesday', 4, 'ENG', 'Room 201/202'),
(46, '2', 'Wednesday', 5, 'PENMAN', 'Room 201/202'),
(47, '2', 'Wednesday', 7, 'FIL', 'Room 201/202'),
(48, '2', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(49, '2', 'Thursday', 1, 'MTB', 'Room 201/202'),
(50, '2', 'Thursday', 2, 'MATH', 'Room 201/202'),
(51, '2', 'Thursday', 4, 'ENG', 'Room 201/202'),
(52, '2', 'Thursday', 5, 'AP', 'Room 201/202'),
(53, '2', 'Thursday', 7, 'SCI', 'Room 201/202'),
(54, '2', 'Thursday', 8, 'MAPEH', 'Gymnasium'),
(55, '2', 'Friday', 1, 'MATH', 'Room 201/202'),
(56, '2', 'Friday', 2, 'FIL', 'Room 201/202'),
(57, '2', 'Friday', 4, 'ENG', 'Room 201/202'),
(58, '2', 'Friday', 5, 'GMRC', 'Room 201/202'),
(59, '2', 'Friday', 7, 'AP', 'Room 201/202'),
(60, '2', 'Friday', 8, 'PENMAN', 'Room 201/202'),
(61, '3', 'Monday', 1, 'MATH', 'Room 301/302'),
(62, '3', 'Monday', 2, 'ENG', 'Room 301/302'),
(63, '3', 'Monday', 4, 'SCI', 'Room 301/302'),
(64, '3', 'Monday', 5, 'FIL', 'Room 301/302'),
(65, '3', 'Monday', 7, 'MTB', 'Room 301/302'),
(66, '3', 'Monday', 8, 'AP', 'Room 301/302'),
(67, '3', 'Tuesday', 1, 'ENG', 'Room 301/302'),
(68, '3', 'Tuesday', 2, 'MATH', 'Room 301/302'),
(69, '3', 'Tuesday', 4, 'FIL', 'Room 301/302'),
(70, '3', 'Tuesday', 5, 'GMRC', 'Room 301/302'),
(71, '3', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(72, '3', 'Tuesday', 8, 'SCI', 'Room 301/302'),
(73, '3', 'Wednesday', 1, 'MATH', 'Room 301/302'),
(74, '3', 'Wednesday', 2, 'MTB', 'Room 301/302'),
(75, '3', 'Wednesday', 4, 'ENG', 'Room 301/302'),
(76, '3', 'Wednesday', 5, 'AP', 'Room 301/302'),
(77, '3', 'Wednesday', 7, 'FIL', 'Room 301/302'),
(78, '3', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(79, '3', 'Thursday', 1, 'SCI', 'Room 301/302'),
(80, '3', 'Thursday', 2, 'MATH', 'Room 301/302'),
(81, '3', 'Thursday', 4, 'ENG', 'Room 301/302'),
(82, '3', 'Thursday', 5, 'MTB', 'Room 301/302'),
(83, '3', 'Thursday', 7, 'PENMAN', 'Room 301/302'),
(84, '3', 'Thursday', 8, 'MAPEH', 'Gymnasium'),
(85, '3', 'Friday', 1, 'MATH', 'Room 301/302'),
(86, '3', 'Friday', 2, 'FIL', 'Room 301/302'),
(87, '3', 'Friday', 4, 'ENG', 'Room 301/302'),
(88, '3', 'Friday', 5, 'AP', 'Room 301/302'),
(89, '3', 'Friday', 7, 'GMRC', 'Room 301/302'),
(90, '3', 'Friday', 8, 'SCI', 'Room 301/302'),
(91, '4', 'Monday', 1, 'MATH', 'Room 401/402'),
(92, '4', 'Monday', 2, 'ENG', 'Room 401/402'),
(93, '4', 'Monday', 4, 'SCI', 'Room 401/402'),
(94, '4', 'Monday', 5, 'FIL', 'Room 401/402'),
(95, '4', 'Monday', 7, 'EPP', 'Room 401/402'),
(96, '4', 'Monday', 8, 'AP', 'Room 401/402'),
(97, '4', 'Tuesday', 1, 'ENG', 'Room 401/402'),
(98, '4', 'Tuesday', 2, 'MATH', 'Room 401/402'),
(99, '4', 'Tuesday', 4, 'FIL', 'Room 401/402'),
(100, '4', 'Tuesday', 5, 'GMRC', 'Room 401/402'),
(101, '4', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(102, '4', 'Tuesday', 8, 'SCI', 'Room 401/402'),
(103, '4', 'Wednesday', 1, 'MATH', 'Room 401/402'),
(104, '4', 'Wednesday', 2, 'EPP', 'Room 401/402'),
(105, '4', 'Wednesday', 4, 'ENG', 'Room 401/402'),
(106, '4', 'Wednesday', 5, 'AP', 'Room 401/402'),
(107, '4', 'Wednesday', 7, 'FIL', 'Room 401/402'),
(108, '4', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(109, '4', 'Thursday', 1, 'SCI', 'Room 401/402'),
(110, '4', 'Thursday', 2, 'MATH', 'Room 401/402'),
(111, '4', 'Thursday', 4, 'ENG', 'Room 401/402'),
(112, '4', 'Thursday', 5, 'EPP', 'Room 401/402'),
(113, '4', 'Thursday', 7, 'PENMAN', 'Room 401/402'),
(114, '4', 'Thursday', 8, 'MAPEH', 'Gymnasium'),
(115, '4', 'Friday', 1, 'MATH', 'Room 401/402'),
(116, '4', 'Friday', 2, 'FIL', 'Room 401/402'),
(117, '4', 'Friday', 4, 'ENG', 'Room 401/402'),
(118, '4', 'Friday', 5, 'AP', 'Room 401/402'),
(119, '4', 'Friday', 7, 'GMRC', 'Room 401/402'),
(120, '4', 'Friday', 8, 'SCI', 'Room 401/402'),
(121, '5', 'Monday', 1, 'MATH', 'Room 501/502'),
(122, '5', 'Monday', 2, 'ENG', 'Room 501/502'),
(123, '5', 'Monday', 4, 'SCI', 'Room 501/502'),
(124, '5', 'Monday', 5, 'FIL', 'Room 501/502'),
(125, '5', 'Monday', 7, 'EPP', 'Room 501/502'),
(126, '5', 'Monday', 8, 'AP', 'Room 501/502'),
(127, '5', 'Tuesday', 1, 'ENG', 'Room 501/502'),
(128, '5', 'Tuesday', 2, 'MATH', 'Room 501/502'),
(129, '5', 'Tuesday', 4, 'FIL', 'Room 501/502'),
(130, '5', 'Tuesday', 5, 'GMRC', 'Room 501/502'),
(131, '5', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(132, '5', 'Tuesday', 8, 'SCI', 'Room 501/502'),
(133, '5', 'Wednesday', 1, 'MATH', 'Room 501/502'),
(134, '5', 'Wednesday', 2, 'EPP', 'Room 501/502'),
(135, '5', 'Wednesday', 4, 'ENG', 'Room 501/502'),
(136, '5', 'Wednesday', 5, 'AP', 'Room 501/502'),
(137, '5', 'Wednesday', 7, 'FIL', 'Room 501/502'),
(138, '5', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(139, '5', 'Thursday', 1, 'SCI', 'Room 501/502'),
(140, '5', 'Thursday', 2, 'MATH', 'Room 501/502'),
(141, '5', 'Thursday', 4, 'ENG', 'Room 501/502'),
(142, '5', 'Thursday', 5, 'EPP', 'Room 501/502'),
(143, '5', 'Thursday', 7, 'PENMAN', 'Room 501/502'),
(144, '5', 'Thursday', 8, 'MAPEH', 'Gymnasium'),
(145, '5', 'Friday', 1, 'MATH', 'Room 501/502'),
(146, '5', 'Friday', 2, 'FIL', 'Room 501/502'),
(147, '5', 'Friday', 4, 'ENG', 'Room 501/502'),
(148, '5', 'Friday', 5, 'AP', 'Room 501/502'),
(149, '5', 'Friday', 7, 'GMRC', 'Room 501/502'),
(150, '5', 'Friday', 8, 'SCI', 'Room 501/502'),
(151, '6', 'Monday', 1, 'MATH', 'Room 601'),
(152, '6', 'Monday', 2, 'ENG', 'Room 601'),
(153, '6', 'Monday', 4, 'SCI', 'Room 601'),
(154, '6', 'Monday', 5, 'FIL', 'Room 601'),
(155, '6', 'Monday', 7, 'EPP', 'Room 601'),
(156, '6', 'Monday', 8, 'AP', 'Room 601'),
(157, '6', 'Tuesday', 1, 'ENG', 'Room 601'),
(158, '6', 'Tuesday', 2, 'MATH', 'Room 601'),
(159, '6', 'Tuesday', 4, 'FIL', 'Room 601'),
(160, '6', 'Tuesday', 5, 'GMRC', 'Room 601'),
(161, '6', 'Tuesday', 7, 'MAPEH', 'Gymnasium'),
(162, '6', 'Tuesday', 8, 'SCI', 'Room 601'),
(163, '6', 'Wednesday', 1, 'MATH', 'Room 601'),
(164, '6', 'Wednesday', 2, 'EPP', 'Room 601'),
(165, '6', 'Wednesday', 4, 'ENG', 'Room 601'),
(166, '6', 'Wednesday', 5, 'AP', 'Room 601'),
(167, '6', 'Wednesday', 7, 'FIL', 'Room 601'),
(168, '6', 'Wednesday', 8, 'COMP', 'Computer Lab'),
(169, '6', 'Thursday', 1, 'SCI', 'Room 601'),
(170, '6', 'Thursday', 2, 'MATH', 'Room 601'),
(171, '6', 'Thursday', 4, 'ENG', 'Room 601'),
(172, '6', 'Thursday', 5, 'EPP', 'Room 601'),
(173, '6', 'Thursday', 7, 'PENMAN', 'Room 601'),
(174, '6', 'Thursday', 8, 'MAPEH', 'Gymnasium'),
(175, '6', 'Friday', 1, 'MATH', 'Room 601'),
(176, '6', 'Friday', 2, 'FIL', 'Room 601'),
(177, '6', 'Friday', 4, 'ENG', 'Room 601'),
(178, '6', 'Friday', 5, 'AP', 'Room 601'),
(179, '6', 'Friday', 7, 'GMRC', 'Room 601'),
(180, '6', 'Friday', 8, 'SCI', 'Room 601');

-- --------------------------------------------------------

--
-- Table structure for table `highlights`
--

CREATE TABLE `highlights` (
  `highlight_id` int(11) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) NOT NULL,
  `border_color` varchar(50) NOT NULL,
  `icon_color` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `highlights`
--

INSERT INTO `highlights` (`highlight_id`, `icon`, `title`, `description`, `border_color`, `icon_color`) VALUES
(1, 'bi bi-medal-fill', 'Champion - District Meet 2024', 'Students excelled in athletics, badminton, and swimming, earning multiple gold medals.', 'var(--primary-green)', 'var(--primary-green)'),
(2, 'bi bi-pencil-fill', '1st Place - Division Schools Press Conference', 'Campus journalists won top awards in news writing, editorial cartooning, and broadcasting.', 'var(--sage-green)', 'var(--sage-green)'),
(3, 'bi bi-trophy-fill', 'Champion - Inter-School Math Quiz Bee', 'Our mathletes secured the overall championship among 15 competing schools.', 'var(--accent-green)', 'var(--accent-green)'),
(4, 'bi bi-people-fill', 'Best Performing School - District Level', 'Awarded for excellence in student development, academic programs, and community engagement.', 'var(--primary-green)', 'var(--primary-green)'),
(5, 'bi bi-star-fill', 'Excellence Award in Reading Program', 'Recognized for outstanding implementation of early literacy and reading interventions.', 'var(--sage-green)', 'var(--sage-green)'),
(6, 'bi bi-lightbulb-fill', 'Innovation Award - Science & Robotics Expo', 'Students presented top-tier projects in automation, renewable energy, and engineering.', 'var(--accent-green)', 'var(--accent-green)');

-- --------------------------------------------------------

--
-- Table structure for table `master_time_slots`
--

CREATE TABLE `master_time_slots` (
  `slot_id` int(11) NOT NULL,
  `slot_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_type` enum('CLASS','RECESS','LUNCH') NOT NULL,
  `slot_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_time_slots`
--

INSERT INTO `master_time_slots` (`slot_id`, `slot_name`, `start_time`, `end_time`, `slot_type`, `slot_order`) VALUES
(1, 'Period 1', '07:30:00', '08:30:00', 'CLASS', 1),
(2, 'Period 2', '08:30:00', '09:30:00', 'CLASS', 2),
(3, 'Morning Recess', '09:30:00', '09:45:00', 'RECESS', 3),
(4, 'Period 3', '09:45:00', '10:45:00', 'CLASS', 4),
(5, 'Period 4', '10:45:00', '11:45:00', 'CLASS', 5),
(6, 'Lunch Break', '11:45:00', '12:45:00', 'LUNCH', 6),
(7, 'Period 5', '12:45:00', '13:45:00', 'CLASS', 7),
(8, 'Period 6', '13:45:00', '14:45:00', 'CLASS', 8);

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL,
  `parent_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `relationship` enum('Mother','Father','Guardian','Other') DEFAULT 'Guardian',
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `parent_code`, `first_name`, `last_name`, `middle_name`, `relationship`, `email`, `contact_number`, `address`, `occupation`) VALUES
(1, 'P-25-00001', 'Sierra', 'Madre', NULL, 'Mother', 'sierra.madre@email.com', '09123456789', 'Brgy. 99 Nasugbu, Batangas', NULL),
(2, 'P-25-00002', 'Roberto', 'Dela Cruz', NULL, 'Father', 'roberto.dc@email.com', '09234567890', 'Brgy. 88 Nasugbu, Batangas', NULL),
(3, 'P-25-00003', 'Linda', 'Santos', NULL, 'Mother', 'linda.santos@email.com', '09345678901', 'Brgy. 77 Nasugbu, Batangas', NULL),
(4, 'P-25-00004', 'Michael', 'Reyes', NULL, 'Father', 'michael.reyes@email.com', '09456789012', 'Brgy. 66 Nasugbu, Batangas', NULL),
(5, 'P-25-00005', 'Anna', 'Garcia', NULL, 'Mother', 'anna.garcia@email.com', '09567890123', 'Brgy. 55 Nasugbu, Batangas', NULL),
(6, 'P-25-00006', 'Pedro', 'Cruz', NULL, 'Father', 'pedro.cruz@email.com', '09678901234', 'Brgy. 44 Nasugbu, Batangas', NULL),
(7, 'P-25-00007', 'Rosa', 'Fernandez', NULL, 'Mother', 'rosa.fernandez@email.com', '09789012345', 'Brgy. 33 Nasugbu, Batangas', NULL),
(8, 'P-25-00008', 'Carlos', 'Mendoza', NULL, 'Father', 'carlos.mendoza@email.com', '09890123456', 'Brgy. 22 Nasugbu, Batangas', NULL),
(9, 'P-25-00009', 'Juno', 'Cayabyab', NULL, 'Guardian', 'juno@gmail.com', '09123456789', 'brgy. 8', 'seafarer'),
(10, 'P-25-00011', 'Ricardo', 'Torres', NULL, 'Father', 'ricardo.torres@email.com', '09111222333', 'Brgy. 11 Nasugbu, Batangas', 'Engineer'),
(11, 'P-25-00012', 'Carmen', 'Villanueva', NULL, 'Mother', 'carmen.villanueva@email.com', '09222333444', 'Brgy. 12 Nasugbu, Batangas', 'Teacher'),
(12, 'P-25-00013', 'Eduardo', 'Ramos', NULL, 'Father', 'eduardo.ramos@email.com', '09333444555', 'Brgy. 13 Nasugbu, Batangas', 'Businessman'),
(13, 'P-25-00014', 'Gloria', 'Navarro', NULL, 'Mother', 'gloria.navarro@email.com', '09444555666', 'Brgy. 14 Nasugbu, Batangas', 'Nurse'),
(14, 'P-25-00015', 'Fernando', 'Ocampo', NULL, 'Father', 'fernando.ocampo@email.com', '09555666777', 'Brgy. 15 Nasugbu, Batangas', 'Driver'),
(15, 'P-25-00016', 'Josephine', 'Bautista', NULL, 'Mother', 'josephine.bautista@email.com', '09666777888', 'Brgy. 16 Nasugbu, Batangas', 'Accountant'),
(16, 'P-25-00017', 'Antonio', 'Pascual', NULL, 'Father', 'antonio.pascual@email.com', '09777888999', 'Brgy. 17 Nasugbu, Batangas', 'Farmer'),
(17, 'P-25-00018', 'Beatriz', 'Salazar', NULL, 'Mother', 'beatriz.salazar@email.com', '09888999000', 'Brgy. 18 Nasugbu, Batangas', 'Sales Manager'),
(18, 'P-25-00019', 'Vicente', 'Gutierrez', NULL, 'Father', 'vicente.gutierrez@email.com', '09999000111', 'Brgy. 19 Nasugbu, Batangas', 'Mechanic'),
(19, 'P-25-00020', 'Amparo', 'Miranda', NULL, 'Mother', 'amparo.miranda@email.com', '09000111222', 'Brgy. 20 Nasugbu, Batangas', 'Midwife'),
(20, 'P-25-00021', 'Rodrigo', 'Castro', NULL, 'Father', 'rodrigo.castro@email.com', '09111333444', 'Brgy. 21 Nasugbu, Batangas', 'Electrician'),
(21, 'P-25-00022', 'Teresa', 'Moreno', NULL, 'Mother', 'teresa.moreno@email.com', '09222444555', 'Brgy. 22 Nasugbu, Batangas', 'Chef'),
(22, 'P-25-00023', 'Alberto', 'Aguilar', NULL, 'Father', 'alberto.aguilar@email.com', '09333555666', 'Brgy. 23 Nasugbu, Batangas', 'Carpenter'),
(23, 'P-25-00024', 'Pilar', 'Buhay', NULL, 'Mother', 'pilar.rojas@email.com', '09444666777', 'Brgy. 24 Nasugbu, Batangas', 'Pharmacist'),
(24, 'P-25-00025', 'Ernesto', 'Ortega', NULL, 'Father', 'ernesto.ortega@email.com', '09555777888', 'Brgy. 25 Nasugbu, Batangas', 'Security Guard');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `payment_type` enum('tuition','miscellaneous','books','other') DEFAULT 'tuition',
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','check','online') DEFAULT 'cash',
  `receipt_number` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `quarter` varchar(10) DEFAULT NULL,
  `status` enum('paid','pending','cancelled') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_code`, `payment_type`, `amount`, `payment_date`, `payment_method`, `receipt_number`, `school_year`, `quarter`, `status`, `notes`, `recorded_by`) VALUES
(3, '25-00003', 'tuition', 25000.00, '2025-08-01', 'cash', 'RCP-2025-003', '2025-2026', NULL, 'paid', NULL, 1),
(4, '25-00004', 'tuition', 10000.00, '2025-08-15', 'bank_transfer', 'RCP-2025-004', '2025-2026', NULL, 'paid', NULL, 1),
(5, '25-00004', 'tuition', 5000.00, '2025-10-01', 'cash', 'RCP-2025-005', '2025-2026', NULL, 'paid', NULL, 1),
(6, '25-00005', 'tuition', 5000.00, '2025-08-20', 'cash', 'RCP-2025-006', '2025-2026', NULL, 'paid', NULL, 1),
(7, '25-00006', 'tuition', 10000.00, '2025-08-10', 'bank_transfer', 'RCP-2025-007', '2025-2026', NULL, 'paid', NULL, 1),
(8, '25-00006', 'tuition', 10000.00, '2025-10-05', 'bank_transfer', 'RCP-2025-008', '2025-2026', NULL, 'paid', NULL, 1),
(9, '25-00007', 'tuition', 25000.00, '2025-08-05', 'bank_transfer', 'RCP-2025-009', '2025-2026', NULL, 'paid', NULL, 1),
(10, '25-00008', 'tuition', 7000.00, '2025-08-18', 'cash', 'RCP-2025-010', '2025-2026', NULL, 'paid', NULL, 1),
(11, '25-00008', 'tuition', 5000.00, '2025-10-12', 'cash', 'RCP-2025-011', '2025-2026', NULL, 'paid', NULL, 1),
(12, '25-00002', 'tuition', 10000.00, '2025-12-01', 'cash', 'RCP-20251201-6587', '2025-2026', '2nd Quarte', 'paid', '', 1),
(13, '25-00005', 'tuition', 5000.00, '2025-12-01', 'cash', 'RCP-20251201-0494', '2025-2026', '2nd Quarte', 'paid', '', 1),
(14, '25-00004', 'tuition', 5000.00, '2025-12-01', 'cash', 'RCP-20251201-7608', '2025-2026', '2nd Quarte', 'paid', '', 1),
(15, '25-00008', 'tuition', 3000.00, '2025-12-01', 'cash', 'RCP-20251201-6886', '2025-2026', '2nd Quarte', 'paid', '', 1),
(16, '25-00008', 'tuition', 2000.00, '2025-12-01', 'cash', 'RCP-20251201-0903', '2025-2026', '2nd Quarte', 'paid', '', 1),
(17, '25-00023', 'tuition', 12000.00, '2025-12-02', 'cash', 'RCP-20251202-7318', '2025-2026', '2nd Quarte', 'paid', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_reminders`
--

CREATE TABLE `payment_reminders` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reminder_type` enum('email','sms','both') DEFAULT 'email',
  `message` text NOT NULL,
  `sent_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_by` int(11) NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `icon`, `title`, `description`) VALUES
(1, 'bi bi-book-fill', 'Core Subjects', 'Math, Science, English, Social Studies'),
(2, 'bi bi-music-note-beamed', 'Electives', 'Arts, Music, Technology, Foreign Languages'),
(3, 'bi bi-controller', 'Clubs & Activities', 'Sports, Debate, Robotics, Theater Arts');

-- --------------------------------------------------------

--
-- Table structure for table `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL DEFAULT 'Creative Dreams School',
  `school_address` text DEFAULT NULL,
  `school_phone` varchar(50) DEFAULT NULL,
  `school_email` varchar(255) DEFAULT NULL,
  `mission_vision` text NOT NULL,
  `foreword` text NOT NULL,
  `current_school_year` varchar(20) DEFAULT '2024-2025',
  `current_semester` varchar(50) DEFAULT '1st Semester',
  `enrollment_status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_settings`
--

INSERT INTO `school_settings` (`id`, `school_name`, `school_address`, `school_phone`, `school_email`, `mission_vision`, `foreword`, `current_school_year`, `current_semester`, `enrollment_status`, `created_at`, `updated_at`) VALUES
(1, 'Creative Dreams School', 'Brias Street, Barangay 1, Nasugbu, Batangas 4231', '1234567890', 'xx@gmal.com', 'Creative Dreams School believes that Man is endowed with potentialities that must be developed to the full. Creative Dreams School is therefore committed to the integral formation and development of learners by providing a well-rounded education in a nurturing and trusting atmosphere.\r\n\r\nCreative Dreams School envisions the development of persons who:\r\n\r\nAs Christians\r\nabide and live the teachings and examples of Christ.\r\nunderstand, internalize, and practice the ideals of truth, love, and peace.\r\n\r\nAs Human Beings\r\nvalue life and other creations.\r\npromote the well-being of others by sharing.\r\n\r\nAs Learners\r\nare self-confident, capable, and well-rounded.\r\nare creative and appreciative of the arts.\r\nmake intelligent decisions and responsible choices.\r\nare committed to lifelong learning, healthy relationships, effective work, and meaningful service.', 'Creative Dreams School welcomes your child and family. We look forward to having a pleasant association with you.\r\n\r\nThe CDS Pupil’s Handbook was made to provide information about the school, its policies, rules and regulations. Parents and pupils are expected to read this handbook. Enrollment to CDS signifies agreement and compliance to the rules and regulations stated. Ignorance of the rules and regulations contained here does not excuse a pupil from incurring the corresponding sanctions stipulated.\r\n\r\nPolicies, rules and regulations presented here are subject to continuous and future revision/updating based on newly issued DepEd Orders/memos, and school memorandums.\r\n\r\nParents and pupils shall be informed of the changes and updates through bulletin information/meetings during the school year.', '2024-2025', '2nd Semester', 'closed', '2025-11-18 15:51:53', '2025-11-19 09:14:00');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `grade_level` varchar(10) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `adviser_code` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `max_capacity` int(11) DEFAULT 25,
  `current_enrollment` int(11) DEFAULT 0,
  `room_assignment` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `grade_level`, `section_name`, `adviser_code`, `school_year`, `max_capacity`, `current_enrollment`, `room_assignment`, `is_active`, `created_at`) VALUES
(1, '1', 'Diamond', 'TC011', '2025-2026', 25, 0, 'Room 101', 1, '2025-11-17 02:26:17'),
(2, '1', 'Emerald', 'TC002', '2025-2026', 25, 0, 'Room 102', 1, '2025-11-17 02:26:17'),
(3, '2', 'Orion', 'TC003', '2025-2026', 25, 0, 'Room 201', 1, '2025-11-17 02:26:17'),
(4, '2', 'Phoenix', 'TC004', '2025-2026', 25, 0, 'Room 202', 1, '2025-11-17 02:26:17'),
(5, '3', 'Galileo', 'TC005', '2025-2026', 25, 0, 'Room 301', 1, '2025-11-17 02:26:17'),
(6, '3', 'Einstein', 'TC006', '2025-2026', 25, 0, 'Room 302', 1, '2025-11-17 02:26:17'),
(7, '4', 'Serenity', 'TC007', '2025-2026', 25, 0, 'Room 401', 1, '2025-11-17 02:26:17'),
(8, '4', 'Charity', 'TC008', '2025-2026', 25, 0, 'Room 402', 1, '2025-11-17 02:26:17'),
(9, '5', 'Amorsolo', 'TC009', '2025-2026', 25, 10, 'Room 501', 1, '2025-11-17 02:26:17'),
(10, '5', 'Hidalgo', 'TC010', '2025-2026', 25, 5, 'Room 502', 1, '2025-11-17 02:26:17'),
(11, '6', 'Rizal', 'TC001', '2025-2026', 25, 8, 'Room 601', 1, '2025-11-17 02:26:17');

-- --------------------------------------------------------

--
-- Table structure for table `section_schedules`
--

CREATE TABLE `section_schedules` (
  `schedule_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `teacher_code` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_schedules`
--

INSERT INTO `section_schedules` (`schedule_id`, `section_id`, `template_id`, `teacher_code`, `school_year`, `is_active`) VALUES
(1, 1, 1, NULL, '2025-2026', 1),
(2, 1, 2, NULL, '2025-2026', 1),
(3, 1, 3, NULL, '2025-2026', 1),
(4, 1, 4, NULL, '2025-2026', 1),
(5, 1, 5, NULL, '2025-2026', 1),
(6, 1, 6, NULL, '2025-2026', 1),
(7, 1, 7, NULL, '2025-2026', 1),
(8, 1, 8, NULL, '2025-2026', 1),
(9, 1, 9, NULL, '2025-2026', 1),
(10, 1, 10, NULL, '2025-2026', 1),
(11, 1, 11, NULL, '2025-2026', 1),
(12, 1, 12, NULL, '2025-2026', 1),
(13, 1, 13, NULL, '2025-2026', 1),
(14, 1, 14, NULL, '2025-2026', 1),
(15, 1, 15, NULL, '2025-2026', 1),
(16, 1, 16, NULL, '2025-2026', 1),
(17, 1, 17, NULL, '2025-2026', 1),
(18, 1, 18, NULL, '2025-2026', 1),
(19, 1, 19, NULL, '2025-2026', 1),
(20, 1, 20, NULL, '2025-2026', 1),
(21, 1, 21, NULL, '2025-2026', 1),
(22, 1, 22, NULL, '2025-2026', 1),
(23, 1, 23, NULL, '2025-2026', 1),
(24, 1, 24, 'TC007', '2025-2026', 1),
(25, 1, 25, NULL, '2025-2026', 1),
(26, 1, 26, NULL, '2025-2026', 1),
(27, 1, 27, NULL, '2025-2026', 1),
(28, 1, 28, NULL, '2025-2026', 1),
(29, 1, 29, NULL, '2025-2026', 1),
(30, 1, 30, NULL, '2025-2026', 1),
(31, 2, 1, NULL, '2025-2026', 1),
(32, 2, 2, NULL, '2025-2026', 1),
(33, 2, 3, NULL, '2025-2026', 1),
(34, 2, 4, NULL, '2025-2026', 1),
(35, 2, 5, NULL, '2025-2026', 1),
(36, 2, 6, NULL, '2025-2026', 1),
(37, 2, 7, NULL, '2025-2026', 1),
(38, 2, 8, NULL, '2025-2026', 1),
(39, 2, 9, NULL, '2025-2026', 1),
(40, 2, 10, NULL, '2025-2026', 1),
(41, 2, 11, NULL, '2025-2026', 1),
(42, 2, 12, NULL, '2025-2026', 1),
(43, 2, 13, NULL, '2025-2026', 1),
(44, 2, 14, NULL, '2025-2026', 1),
(45, 2, 15, NULL, '2025-2026', 1),
(46, 2, 16, NULL, '2025-2026', 1),
(47, 2, 17, NULL, '2025-2026', 1),
(48, 2, 18, NULL, '2025-2026', 1),
(49, 2, 19, NULL, '2025-2026', 1),
(50, 2, 20, NULL, '2025-2026', 1),
(51, 2, 21, NULL, '2025-2026', 1),
(52, 2, 22, NULL, '2025-2026', 1),
(53, 2, 23, NULL, '2025-2026', 1),
(54, 2, 24, NULL, '2025-2026', 1),
(55, 2, 25, NULL, '2025-2026', 1),
(56, 2, 26, NULL, '2025-2026', 1),
(57, 2, 27, NULL, '2025-2026', 1),
(58, 2, 28, NULL, '2025-2026', 1),
(59, 2, 29, NULL, '2025-2026', 1),
(60, 2, 30, NULL, '2025-2026', 1),
(61, 3, 31, NULL, '2025-2026', 1),
(62, 3, 32, NULL, '2025-2026', 1),
(63, 3, 33, NULL, '2025-2026', 1),
(64, 3, 34, NULL, '2025-2026', 1),
(65, 3, 35, NULL, '2025-2026', 1),
(66, 3, 36, NULL, '2025-2026', 1),
(67, 3, 37, NULL, '2025-2026', 1),
(68, 3, 38, NULL, '2025-2026', 1),
(69, 3, 39, NULL, '2025-2026', 1),
(70, 3, 40, NULL, '2025-2026', 1),
(71, 3, 41, NULL, '2025-2026', 1),
(72, 3, 42, NULL, '2025-2026', 1),
(73, 3, 43, NULL, '2025-2026', 1),
(74, 3, 44, NULL, '2025-2026', 1),
(75, 3, 45, NULL, '2025-2026', 1),
(76, 3, 46, NULL, '2025-2026', 1),
(77, 3, 47, NULL, '2025-2026', 1),
(78, 3, 48, NULL, '2025-2026', 1),
(79, 3, 49, NULL, '2025-2026', 1),
(80, 3, 50, NULL, '2025-2026', 1),
(81, 3, 51, NULL, '2025-2026', 1),
(82, 3, 52, NULL, '2025-2026', 1),
(83, 3, 53, NULL, '2025-2026', 1),
(84, 3, 54, NULL, '2025-2026', 1),
(85, 3, 55, NULL, '2025-2026', 1),
(86, 3, 56, NULL, '2025-2026', 1),
(87, 3, 57, NULL, '2025-2026', 1),
(88, 3, 58, NULL, '2025-2026', 1),
(89, 3, 59, NULL, '2025-2026', 1),
(90, 3, 60, NULL, '2025-2026', 1),
(91, 4, 31, NULL, '2025-2026', 1),
(92, 4, 32, NULL, '2025-2026', 1),
(93, 4, 33, NULL, '2025-2026', 1),
(94, 4, 34, NULL, '2025-2026', 1),
(95, 4, 35, NULL, '2025-2026', 1),
(96, 4, 36, NULL, '2025-2026', 1),
(97, 4, 37, NULL, '2025-2026', 1),
(98, 4, 38, NULL, '2025-2026', 1),
(99, 4, 39, NULL, '2025-2026', 1),
(100, 4, 40, NULL, '2025-2026', 1),
(101, 4, 41, NULL, '2025-2026', 1),
(102, 4, 42, NULL, '2025-2026', 1),
(103, 4, 43, NULL, '2025-2026', 1),
(104, 4, 44, NULL, '2025-2026', 1),
(105, 4, 45, NULL, '2025-2026', 1),
(106, 4, 46, NULL, '2025-2026', 1),
(107, 4, 47, NULL, '2025-2026', 1),
(108, 4, 48, NULL, '2025-2026', 1),
(109, 4, 49, NULL, '2025-2026', 1),
(110, 4, 50, NULL, '2025-2026', 1),
(111, 4, 51, NULL, '2025-2026', 1),
(112, 4, 52, NULL, '2025-2026', 1),
(113, 4, 53, NULL, '2025-2026', 1),
(114, 4, 54, NULL, '2025-2026', 1),
(115, 4, 55, NULL, '2025-2026', 1),
(116, 4, 56, NULL, '2025-2026', 1),
(117, 4, 57, NULL, '2025-2026', 1),
(118, 4, 58, NULL, '2025-2026', 1),
(119, 4, 59, NULL, '2025-2026', 1),
(120, 4, 60, NULL, '2025-2026', 1),
(121, 5, 61, 'TC005', '2025-2026', 1),
(122, 5, 62, NULL, '2025-2026', 1),
(123, 5, 63, NULL, '2025-2026', 1),
(124, 5, 64, NULL, '2025-2026', 1),
(125, 5, 65, NULL, '2025-2026', 1),
(126, 5, 66, NULL, '2025-2026', 1),
(127, 5, 67, NULL, '2025-2026', 1),
(128, 5, 68, NULL, '2025-2026', 1),
(129, 5, 69, NULL, '2025-2026', 1),
(130, 5, 70, NULL, '2025-2026', 1),
(131, 5, 71, NULL, '2025-2026', 1),
(132, 5, 72, NULL, '2025-2026', 1),
(133, 5, 73, NULL, '2025-2026', 1),
(134, 5, 74, NULL, '2025-2026', 1),
(135, 5, 75, NULL, '2025-2026', 1),
(136, 5, 76, NULL, '2025-2026', 1),
(137, 5, 77, NULL, '2025-2026', 1),
(138, 5, 78, NULL, '2025-2026', 1),
(139, 5, 79, NULL, '2025-2026', 1),
(140, 5, 80, NULL, '2025-2026', 1),
(141, 5, 81, NULL, '2025-2026', 1),
(142, 5, 82, NULL, '2025-2026', 1),
(143, 5, 83, NULL, '2025-2026', 1),
(144, 5, 84, NULL, '2025-2026', 1),
(145, 5, 85, NULL, '2025-2026', 1),
(146, 5, 86, NULL, '2025-2026', 1),
(147, 5, 87, NULL, '2025-2026', 1),
(148, 5, 88, NULL, '2025-2026', 1),
(149, 5, 89, NULL, '2025-2026', 1),
(150, 5, 90, NULL, '2025-2026', 1),
(151, 6, 61, NULL, '2025-2026', 1),
(152, 6, 62, NULL, '2025-2026', 1),
(153, 6, 63, NULL, '2025-2026', 1),
(154, 6, 64, NULL, '2025-2026', 1),
(155, 6, 65, NULL, '2025-2026', 1),
(156, 6, 66, NULL, '2025-2026', 1),
(157, 6, 67, NULL, '2025-2026', 1),
(158, 6, 68, NULL, '2025-2026', 1),
(159, 6, 69, NULL, '2025-2026', 1),
(160, 6, 70, NULL, '2025-2026', 1),
(161, 6, 71, NULL, '2025-2026', 1),
(162, 6, 72, NULL, '2025-2026', 1),
(163, 6, 73, NULL, '2025-2026', 1),
(164, 6, 74, NULL, '2025-2026', 1),
(165, 6, 75, NULL, '2025-2026', 1),
(166, 6, 76, NULL, '2025-2026', 1),
(167, 6, 77, NULL, '2025-2026', 1),
(168, 6, 78, NULL, '2025-2026', 1),
(169, 6, 79, NULL, '2025-2026', 1),
(170, 6, 80, NULL, '2025-2026', 1),
(171, 6, 81, NULL, '2025-2026', 1),
(172, 6, 82, NULL, '2025-2026', 1),
(173, 6, 83, NULL, '2025-2026', 1),
(174, 6, 84, NULL, '2025-2026', 1),
(175, 6, 85, NULL, '2025-2026', 1),
(176, 6, 86, NULL, '2025-2026', 1),
(177, 6, 87, NULL, '2025-2026', 1),
(178, 6, 88, NULL, '2025-2026', 1),
(179, 6, 89, NULL, '2025-2026', 1),
(180, 6, 90, NULL, '2025-2026', 1),
(181, 7, 91, NULL, '2025-2026', 1),
(182, 7, 92, NULL, '2025-2026', 1),
(183, 7, 93, NULL, '2025-2026', 1),
(184, 7, 94, NULL, '2025-2026', 1),
(185, 7, 95, NULL, '2025-2026', 1),
(186, 7, 96, NULL, '2025-2026', 1),
(187, 7, 97, NULL, '2025-2026', 1),
(188, 7, 98, NULL, '2025-2026', 1),
(189, 7, 99, NULL, '2025-2026', 1),
(190, 7, 100, NULL, '2025-2026', 1),
(191, 7, 101, NULL, '2025-2026', 1),
(192, 7, 102, NULL, '2025-2026', 1),
(193, 7, 103, NULL, '2025-2026', 1),
(194, 7, 104, NULL, '2025-2026', 1),
(195, 7, 105, NULL, '2025-2026', 1),
(196, 7, 106, NULL, '2025-2026', 1),
(197, 7, 107, NULL, '2025-2026', 1),
(198, 7, 108, NULL, '2025-2026', 1),
(199, 7, 109, NULL, '2025-2026', 1),
(200, 7, 110, NULL, '2025-2026', 1),
(201, 7, 111, NULL, '2025-2026', 1),
(202, 7, 112, NULL, '2025-2026', 1),
(203, 7, 113, NULL, '2025-2026', 1),
(204, 7, 114, NULL, '2025-2026', 1),
(205, 7, 115, NULL, '2025-2026', 1),
(206, 7, 116, NULL, '2025-2026', 1),
(207, 7, 117, NULL, '2025-2026', 1),
(208, 7, 118, NULL, '2025-2026', 1),
(209, 7, 119, NULL, '2025-2026', 1),
(210, 7, 120, NULL, '2025-2026', 1),
(211, 8, 91, NULL, '2025-2026', 1),
(212, 8, 92, NULL, '2025-2026', 1),
(213, 8, 93, NULL, '2025-2026', 1),
(214, 8, 94, NULL, '2025-2026', 1),
(215, 8, 95, NULL, '2025-2026', 1),
(216, 8, 96, NULL, '2025-2026', 1),
(217, 8, 97, NULL, '2025-2026', 1),
(218, 8, 98, NULL, '2025-2026', 1),
(219, 8, 99, NULL, '2025-2026', 1),
(220, 8, 100, NULL, '2025-2026', 1),
(221, 8, 101, NULL, '2025-2026', 1),
(222, 8, 102, NULL, '2025-2026', 1),
(223, 8, 103, NULL, '2025-2026', 1),
(224, 8, 104, NULL, '2025-2026', 1),
(225, 8, 105, NULL, '2025-2026', 1),
(226, 8, 106, NULL, '2025-2026', 1),
(227, 8, 107, NULL, '2025-2026', 1),
(228, 8, 108, NULL, '2025-2026', 1),
(229, 8, 109, NULL, '2025-2026', 1),
(230, 8, 110, NULL, '2025-2026', 1),
(231, 8, 111, NULL, '2025-2026', 1),
(232, 8, 112, NULL, '2025-2026', 1),
(233, 8, 113, NULL, '2025-2026', 1),
(234, 8, 114, NULL, '2025-2026', 1),
(235, 8, 115, NULL, '2025-2026', 1),
(236, 8, 116, NULL, '2025-2026', 1),
(237, 8, 117, NULL, '2025-2026', 1),
(238, 8, 118, NULL, '2025-2026', 1),
(239, 8, 119, NULL, '2025-2026', 1),
(240, 8, 120, NULL, '2025-2026', 1),
(241, 9, 121, NULL, '2025-2026', 1),
(242, 9, 122, NULL, '2025-2026', 1),
(243, 9, 123, NULL, '2025-2026', 1),
(244, 9, 124, NULL, '2025-2026', 1),
(245, 9, 125, NULL, '2025-2026', 1),
(246, 9, 126, NULL, '2025-2026', 1),
(247, 9, 127, NULL, '2025-2026', 1),
(248, 9, 128, 'TC009', '2025-2026', 1),
(249, 9, 129, NULL, '2025-2026', 1),
(250, 9, 130, NULL, '2025-2026', 1),
(251, 9, 131, NULL, '2025-2026', 1),
(252, 9, 132, NULL, '2025-2026', 1),
(253, 9, 133, NULL, '2025-2026', 1),
(254, 9, 134, NULL, '2025-2026', 1),
(255, 9, 135, NULL, '2025-2026', 1),
(256, 9, 136, NULL, '2025-2026', 1),
(257, 9, 137, NULL, '2025-2026', 1),
(258, 9, 138, NULL, '2025-2026', 1),
(259, 9, 139, NULL, '2025-2026', 1),
(260, 9, 140, 'TC009', '2025-2026', 1),
(261, 9, 141, NULL, '2025-2026', 1),
(262, 9, 142, NULL, '2025-2026', 1),
(263, 9, 143, NULL, '2025-2026', 1),
(264, 9, 144, NULL, '2025-2026', 1),
(265, 9, 145, NULL, '2025-2026', 1),
(266, 9, 146, NULL, '2025-2026', 1),
(267, 9, 147, NULL, '2025-2026', 1),
(268, 9, 148, NULL, '2025-2026', 1),
(269, 9, 149, NULL, '2025-2026', 1),
(270, 9, 150, NULL, '2025-2026', 1),
(271, 10, 121, NULL, '2025-2026', 1),
(272, 10, 122, NULL, '2025-2026', 1),
(273, 10, 123, NULL, '2025-2026', 1),
(274, 10, 124, NULL, '2025-2026', 1),
(275, 10, 125, NULL, '2025-2026', 1),
(276, 10, 126, NULL, '2025-2026', 1),
(277, 10, 127, NULL, '2025-2026', 1),
(278, 10, 128, 'TC007', '2025-2026', 1),
(279, 10, 129, NULL, '2025-2026', 1),
(280, 10, 130, NULL, '2025-2026', 1),
(281, 10, 131, NULL, '2025-2026', 1),
(282, 10, 132, NULL, '2025-2026', 1),
(283, 10, 133, NULL, '2025-2026', 1),
(284, 10, 134, NULL, '2025-2026', 1),
(285, 10, 135, NULL, '2025-2026', 1),
(286, 10, 136, NULL, '2025-2026', 1),
(287, 10, 137, NULL, '2025-2026', 1),
(288, 10, 138, NULL, '2025-2026', 1),
(289, 10, 139, NULL, '2025-2026', 1),
(290, 10, 140, NULL, '2025-2026', 1),
(291, 10, 141, NULL, '2025-2026', 1),
(292, 10, 142, NULL, '2025-2026', 1),
(293, 10, 143, NULL, '2025-2026', 1),
(294, 10, 144, NULL, '2025-2026', 1),
(295, 10, 145, NULL, '2025-2026', 1),
(296, 10, 146, NULL, '2025-2026', 1),
(297, 10, 147, NULL, '2025-2026', 1),
(298, 10, 148, NULL, '2025-2026', 1),
(299, 10, 149, NULL, '2025-2026', 1),
(300, 10, 150, NULL, '2025-2026', 1),
(301, 11, 151, 'TC001', '2025-2026', 1),
(302, 11, 152, 'TC002', '2025-2026', 1),
(303, 11, 153, 'TC003', '2025-2026', 1),
(304, 11, 154, 'TC004', '2025-2026', 1),
(305, 11, 155, 'TC008', '2025-2026', 1),
(306, 11, 156, 'TC005', '2025-2026', 1),
(307, 11, 157, 'TC002', '2025-2026', 1),
(308, 11, 158, 'TC001', '2025-2026', 1),
(309, 11, 159, 'TC004', '2025-2026', 1),
(310, 11, 160, 'TC006', '2025-2026', 1),
(311, 11, 161, 'TC007', '2025-2026', 1),
(312, 11, 162, 'TC003', '2025-2026', 1),
(313, 11, 163, 'TC001', '2025-2026', 1),
(314, 11, 164, 'TC008', '2025-2026', 1),
(315, 11, 165, 'TC002', '2025-2026', 1),
(316, 11, 166, 'TC005', '2025-2026', 1),
(317, 11, 167, 'TC004', '2025-2026', 1),
(318, 11, 168, 'TC008', '2025-2026', 1),
(319, 11, 169, 'TC003', '2025-2026', 1),
(320, 11, 170, 'TC001', '2025-2026', 1),
(321, 11, 171, 'TC002', '2025-2026', 1),
(322, 11, 172, 'TC008', '2025-2026', 1),
(323, 11, 173, 'TC002', '2025-2026', 1),
(324, 11, 174, 'TC007', '2025-2026', 1),
(325, 11, 175, 'TC001', '2025-2026', 1),
(326, 11, 176, 'TC004', '2025-2026', 1),
(327, 11, 177, 'TC002', '2025-2026', 1),
(328, 11, 178, 'TC005', '2025-2026', 1),
(329, 11, 179, 'TC006', '2025-2026', 1),
(330, 11, 180, 'TC003', '2025-2026', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive','graduated','transferred') DEFAULT 'active',
  `date_enrolled` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `student_code`, `first_name`, `last_name`, `middle_name`, `section_id`, `parent_id`, `gender`, `birthdate`, `address`, `status`, `date_enrolled`, `profile_picture`) VALUES
(1, 12, '25-00001', 'Andrei', 'Ruffy', NULL, 11, 1, 'Male', '2004-08-31', 'Brgy. 99 Nasugbu, Batangas', 'active', '2025-08-01', 'student_12_1763815863.jpg'),
(2, 13, '25-00002', 'Juan', 'Dela Cruz', NULL, 11, 2, 'Male', NULL, NULL, 'active', '2025-08-01', NULL),
(3, 14, '25-00003', 'Maria', 'Santos', NULL, 11, 3, 'Female', '2013-03-15', NULL, 'active', '2025-08-01', NULL),
(4, 15, '25-00004', 'Carlos', 'Reyes', NULL, 11, 4, 'Male', '2013-05-20', NULL, 'active', '2025-08-01', NULL),
(5, 16, '25-00005', 'Kevin ', 'Garcia', NULL, 11, 5, 'Male', '2013-07-10', NULL, 'active', '2025-08-01', NULL),
(6, 17, '25-00006', 'Miguel', 'Cruz', NULL, 11, 6, 'Male', '2013-09-25', NULL, 'active', '2025-08-01', NULL),
(7, 18, '25-00007', 'Isabella', 'Fernandez', NULL, 11, 7, 'Female', '2013-11-30', NULL, 'active', '2025-08-01', NULL),
(8, 19, '25-00008', 'Diego', 'Mendoza', NULL, 11, 8, 'Male', '2013-02-14', NULL, 'active', '2025-08-01', NULL),
(9, 21, '25-00009', 'Jason', 'Villaflor', NULL, 9, 9, 'Male', '2013-08-22', 'Brgy. Onse', 'active', '2025-11-29', NULL),
(10, 22, '25-00010', 'Kevin', 'Durant', NULL, 10, 1, 'Male', '2015-12-16', NULL, 'active', '2025-12-01', NULL),
(11, 23, '25-00011', 'Gabriel', 'Torres', NULL, 9, 10, 'Male', '2014-01-15', 'Brgy. 11 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(12, 24, '25-00012', 'Sofia', 'Villanueva', NULL, 9, 11, 'Female', '2014-02-20', 'Brgy. 12 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(13, 25, '25-00013', 'Luis', 'Ramos', NULL, 9, 12, 'Male', '2014-03-10', 'Brgy. 13 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(14, 26, '25-00014', 'Adriana', 'Navarro', NULL, 9, 13, 'Female', '2014-04-05', 'Brgy. 14 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(15, 27, '25-00015', 'Rafael', 'Ocampo', NULL, 9, 14, 'Male', '2014-05-12', 'Brgy. 15 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(16, 28, '25-00016', 'Camila', 'Bautista', NULL, 10, 15, 'Female', '2014-06-18', 'Brgy. 16 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(17, 29, '25-00017', 'Mateo', 'Pascual', NULL, 10, 16, 'Male', '2014-07-22', 'Brgy. 17 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(18, 30, '25-00018', 'Valentina', 'Salazar', NULL, 10, 17, 'Female', '2014-08-30', 'Brgy. 18 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(19, 31, '25-00019', 'Santiago', 'Gutierrez', NULL, 10, 18, 'Male', '2014-09-14', 'Brgy. 19 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(20, 32, '25-00020', 'Catalina', 'Miranda', NULL, 10, 19, 'Female', '2014-10-08', 'Brgy. 20 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(21, 33, '25-00021', 'Alejandro', 'Castro', NULL, 9, 20, 'Male', '2014-11-11', 'Brgy. 21 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(22, 34, '25-00022', 'Elena', 'Moreno', NULL, 9, 21, 'Female', '2014-12-05', 'Brgy. 22 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(23, 35, '25-00023', 'Nicolas', 'Aguilar', NULL, 9, 22, 'Male', '2014-01-28', 'Brgy. 23 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(24, 36, '25-00024', 'Mariana', 'Buhay', NULL, 9, 23, 'Female', '2014-02-16', 'Brgy. 24 Nasugbu, Batangas', 'active', '2025-08-01', NULL),
(25, 37, '25-00025', 'Sebastian', 'Ortega', NULL, 9, 24, 'Male', '2014-03-22', 'Brgy. 25 Nasugbu, Batangas', 'active', '2025-08-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_balances`
--

CREATE TABLE `student_balances` (
  `balance_id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `total_fee` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('fully_paid','partially_paid','unpaid') DEFAULT 'unpaid',
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_balances`
--

INSERT INTO `student_balances` (`balance_id`, `student_code`, `school_year`, `total_fee`, `amount_paid`, `balance`, `due_date`, `status`, `last_updated`) VALUES
(1, '25-00001', '2025-2026', 25000.00, 25000.00, 0.00, '2025-12-15', 'fully_paid', '2025-12-02 00:32:39'),
(2, '25-00002', '2025-2026', 25000.00, 10000.00, 15000.00, '2025-12-15', 'partially_paid', '2025-12-02 00:32:22'),
(3, '25-00003', '2025-2026', 25000.00, 25000.00, 0.00, '2025-12-15', 'fully_paid', '2025-11-20 07:03:53'),
(4, '25-00004', '2025-2026', 25000.00, 20000.00, 0.00, '2025-12-15', 'fully_paid', '2025-12-02 01:56:04'),
(5, '25-00005', '2025-2026', 25000.00, 10000.00, 15000.00, '2025-12-15', 'partially_paid', '2025-12-02 01:08:32'),
(6, '25-00006', '2025-2026', 25000.00, 20000.00, 5000.00, '2025-12-15', 'partially_paid', '2025-11-20 07:03:53'),
(7, '25-00007', '2025-2026', 25000.00, 25000.00, 0.00, '2025-12-15', 'fully_paid', '2025-11-20 07:03:53'),
(8, '25-00008', '2025-2026', 25000.00, 17000.00, 8000.00, '2025-12-15', 'partially_paid', '2025-12-02 02:15:34'),
(9, '25-00011', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(10, '25-00012', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(11, '25-00013', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(12, '25-00014', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(13, '25-00015', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(14, '25-00016', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(15, '25-00017', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(16, '25-00018', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(17, '25-00019', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(18, '25-00020', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:00:00'),
(19, '25-00021', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:30:00'),
(20, '25-00022', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:30:00'),
(21, '25-00023', '2025-2026', 25000.00, 12000.00, 13000.00, '2025-12-15', 'partially_paid', '2025-12-02 08:42:30'),
(22, '25-00024', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:30:00'),
(23, '25-00025', '2025-2026', 25000.00, 0.00, 25000.00, '2025-12-15', 'unpaid', '2025-12-02 10:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `grade_levels` varchar(50) NOT NULL COMMENT 'Comma-separated: 1,2,3,4,5,6',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `grade_levels`, `description`, `is_active`) VALUES
(1, 'GMRC', 'GMRC (Good Manners and Right Conduct)', '1,2,3,4,5,6', 'Values education for all grades', 1),
(2, 'SCI', 'Science', '1,2,3,4,5,6', 'Science subject for all grades', 1),
(3, 'ENG', 'English', '1,2,3,4,5,6', 'English language for all grades', 1),
(4, 'FIL', 'Filipino', '1,2,3,4,5,6', 'Filipino language for all grades', 1),
(5, 'AP', 'Araling Panlipunan', '1,2,3,4,5,6', 'Social Studies for all grades', 1),
(6, 'MATH', 'Mathematics', '1,2,3,4,5,6', 'Mathematics for all grades', 1),
(7, 'MTB', 'Mother Tongue', '1,2,3', 'Mother Tongue-Based for Grades 1-3', 1),
(8, 'EPP', 'Edukasyong Pantahanan at Pangkabuhayan', '4,5,6', 'EPP for Grades 4-6', 1),
(9, 'PENMAN', 'Penmanship', '1,2,3,4,5,6', 'Penmanship for all grades', 1),
(10, 'MAPEH', 'MAPEH', '1,2,3,4,5,6', 'Music, Arts, PE, Health', 1),
(11, 'COMP', 'Computer', '1,2,3,4,5,6', 'Computer Education', 1);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `last_updated`, `updated_by`) VALUES
(1, 'current_school_year', '2025-2026', 'Current active school year', '2025-12-02 00:45:33', NULL),
(2, 'current_quarter', '2nd', 'Current active quarter (1st, 2nd, 3rd, 4th)', '2025-12-02 00:45:33', NULL),
(3, 'school_name', 'Creative Dreams School', 'Official school name', '2025-12-01 17:39:08', NULL),
(4, 'school_address', 'Nasugbu, Batangas', 'School address', '2025-12-01 17:39:08', NULL),
(5, 'school_contact', '(02) 1234-5678', 'School contact number', '2025-12-01 17:39:08', NULL),
(6, 'school_email', 'info@creativedreams.edu.ph', 'School email address', '2025-12-01 17:39:08', NULL),
(7, 'passing_grade', '75', 'Minimum passing grade', '2025-11-17 02:26:17', NULL),
(8, 'enrollment_status', 'closed', 'Enrollment status (open/closed)', '2025-12-02 00:45:33', NULL),
(9, 'school_mission', 'Creative Dreams School believes that Man is endowed with potentialities that must be developed to the full. Creative Dreams School is therefore committed to the integral formation and development of learners by providing a well-rounded education in a nurt', 'School mission statement', '2025-12-01 17:39:08', NULL),
(10, 'school_vision', 'Creative Dreams School envisions the development of persons who:\r\n\r\nAs Christians\r\nabide and live the teachings and examples of Christ.\r\nunderstand, internalize, and practice the ideals of truth, love, and peace.\r\n\r\nAs Human Beings\r\nvalue life and other', 'School vision statement', '2025-12-01 17:39:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `teacher_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT 'Elementary Department',
  `position` varchar(100) DEFAULT 'Teacher',
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `status` enum('active','inactive','on_leave') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `teacher_code`, `first_name`, `last_name`, `middle_name`, `department`, `position`, `contact_number`, `address`, `profile_photo`, `date_hired`, `status`) VALUES
(1, 2, 'TC001', 'Teddy', 'McDonald', NULL, 'Elementary Department', 'Head Teacher', '', 'Chicago', 'uploads/teachers/TC001_1763529517.jpg', NULL, 'active'),
(2, 3, 'TC002', 'Maria', 'Lopez', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(3, 4, 'TC003', 'Daniel', 'Reyes', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(4, 5, 'TC004', 'Angela', 'Santos', NULL, 'Elementary Department', 'Teacher', '09123456788', 'nasugbu', NULL, NULL, 'active'),
(5, 6, 'TC005', 'Robert', 'Garcia', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(6, 7, 'TC006', 'Catherine', 'Morales', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(7, 8, 'TC007', 'Patrick', 'Cruz', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(8, 9, 'TC008', 'Elaine', 'Rivera', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(9, 10, 'TC009', 'Marcus', 'Santos', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(10, 11, 'TC010', 'Elena', 'Fernandez', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active'),
(11, 20, 'TC011', 'Seulgi', 'Kang', NULL, 'Elementary Department', 'Teacher', NULL, NULL, NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `testimonial_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date_posted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`testimonial_id`, `name`, `role`, `message`, `date_posted`) VALUES
(1, 'Maria Angel Cruz', 'Senior High Student', 'The school has provided me with excellent academic support and engaging learning resources.', '2025-01-05 00:00:00'),
(2, 'Jonathan Reyes', 'Parent', 'I appreciate how the teachers consistently communicate with us regarding our children’s progress.', '2025-01-10 00:00:00'),
(3, 'Liza Bautista', 'College Instructor', 'The institution promotes a culture of professionalism and continuous improvement among faculty.', '2025-01-12 00:00:00'),
(4, 'Carlo Mendoza', 'Alumni', 'My years here helped shape my discipline and prepared me well for real-world challenges.', '2025-01-15 00:00:00'),
(5, 'Eunice Ramirez', 'Junior High Student', 'The learning environment is safe, inclusive, and encourages us to excel both academically and personally.', '2025-01-18 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student','parent') NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `email`, `status`, `date_created`) VALUES
(1, 'admin1', '1admin', 'admin', 'admin@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(2, 'TC001', 'teacher1', 'teacher', 'tc001@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(3, 'TC002', 'teacher2', 'teacher', 'tc002@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(4, 'TC003', 'teacher3', 'teacher', 'tc003@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(5, 'TC004', 'teacher4', 'teacher', 'tc004@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(6, 'TC005', 'teacher5', 'teacher', 'tc005@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(7, 'TC006', 'teacher6', 'teacher', 'tc006@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(8, 'TC007', 'teacher7', 'teacher', 'tc007@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(9, 'TC008', 'teacher8', 'teacher', 'tc008@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(10, 'TC009', 'teacher9', 'teacher', 'tc009@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(11, 'TC010', 'teacher10', 'teacher', 'tc010@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(12, '25-00001', 'student1', 'student', '25-00001@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(13, '25-00002', 'student2', 'student', '25-00002@cds.edu.ph', 'active', '2025-11-17 02:26:17'),
(14, '25-00003', 'student3', 'student', '25-00003@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(15, '25-00004', 'student4', 'student', '25-00004@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(16, '25-00005', 'student5', 'student', '25-00005@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(17, '25-00006', 'student6', 'student', '25-00006@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(18, '25-00007', 'student7', 'student', '25-00007@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(19, '25-00008', 'student8', 'student', '25-00008@cds.edu.ph', 'active', '2025-11-20 07:03:53'),
(20, 'tc011', 'teacher11', 'teacher', '`tc011@gmail.com', 'active', '2025-11-22 13:03:12'),
(21, '25-00009', '$2y$10$PqyXLge/n5HbwSoIgEWGCuVdGaQkxTCowA1/43Fe8h2jWeK9DjgEG', 'student', '25-00009@cds.edu.ph', 'active', '2025-11-30 00:34:45'),
(22, '25-00010', 'student10', 'student', '25-00010.edu.cds.ph', 'active', '2025-12-01 17:48:21'),
(23, '25-00011', 'student11', 'student', '25-00011@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(24, '25-00012', 'student12', 'student', '25-00012@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(25, '25-00013', 'student13', 'student', '25-00013@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(26, '25-00014', 'student14', 'student', '25-00014@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(27, '25-00015', 'student15', 'student', '25-00015@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(28, '25-00016', 'student16', 'student', '25-00016@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(29, '25-00017', 'student17', 'student', '25-00017@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(30, '25-00018', 'student18', 'student', '25-00018@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(31, '25-00019', 'student19', 'student', '25-00019@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(32, '25-00020', 'student20', 'student', '25-00020@cds.edu.ph', 'active', '2025-12-02 10:00:00'),
(33, '25-00021', 'student21', 'student', '25-00021@cds.edu.ph', 'active', '2025-12-02 10:30:00'),
(34, '25-00022', 'student22', 'student', '25-00022@cds.edu.ph', 'active', '2025-12-02 10:30:00'),
(35, '25-00023', 'student23', 'student', '25-00023@cds.edu.ph', 'active', '2025-12-02 10:30:00'),
(36, '25-00024', 'student24', 'student', '25-00024@cds.edu.ph', 'active', '2025-12-02 10:30:00'),
(37, '25-00025', 'student25', 'student', '25-00025@cds.edu.ph', 'active', '2025-12-02 10:30:00');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_complete_schedules`
-- (See below for the actual view)
--
CREATE TABLE `v_complete_schedules` (
`section_id` int(11)
,`grade_level` varchar(10)
,`section_name` varchar(50)
,`school_year` varchar(20)
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday')
,`slot_name` varchar(50)
,`start_time` time
,`end_time` time
,`slot_type` enum('CLASS','RECESS','LUNCH')
,`slot_order` int(11)
,`subject_code` varchar(50)
,`subject_name` varchar(100)
,`room_type` varchar(50)
,`teacher_code` varchar(50)
,`display_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `v_complete_schedules`
--
DROP TABLE IF EXISTS `v_complete_schedules`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_complete_schedules`  AS SELECT `s`.`section_id` AS `section_id`, `s`.`grade_level` AS `grade_level`, `s`.`section_name` AS `section_name`, `s`.`school_year` AS `school_year`, `gst`.`day_of_week` AS `day_of_week`, `mts`.`slot_name` AS `slot_name`, `mts`.`start_time` AS `start_time`, `mts`.`end_time` AS `end_time`, `mts`.`slot_type` AS `slot_type`, `mts`.`slot_order` AS `slot_order`, `gst`.`subject_code` AS `subject_code`, `sub`.`subject_name` AS `subject_name`, `gst`.`room_type` AS `room_type`, `ss`.`teacher_code` AS `teacher_code`, concat(case `mts`.`slot_type` when 'RECESS' then '☕ Morning Recess' when 'LUNCH' then '🍽️ Lunch Break' else `sub`.`subject_name` end) AS `display_name` FROM ((((`sections` `s` join `grade_schedule_template` `gst` on(`s`.`grade_level` = `gst`.`grade_level`)) join `master_time_slots` `mts` on(`gst`.`slot_id` = `mts`.`slot_id`)) join `subjects` `sub` on(`gst`.`subject_code` = `sub`.`subject_code`)) left join `section_schedules` `ss` on(`s`.`section_id` = `ss`.`section_id` and `gst`.`template_id` = `ss`.`template_id` and `s`.`school_year` = `ss`.`school_year`)) WHERE `s`.`is_active` = 1 ORDER BY `s`.`grade_level` ASC, `s`.`section_name` ASC, field(`gst`.`day_of_week`,'Monday','Tuesday','Wednesday','Thursday','Friday') ASC, `mts`.`slot_order` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email_address` (`email_address`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `student_code` (`student_code`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_attendance` (`student_code`,`subject_code`,`date`),
  ADD KEY `subject_code` (`subject_code`),
  ADD KEY `teacher_code` (`teacher_code`);

--
-- Indexes for table `available_dates`
--
ALTER TABLE `available_dates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `student_code` (`student_code`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `student_code` (`student_code`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `unique_grade` (`student_code`,`subject_code`,`quarter`),
  ADD KEY `subject_code` (`subject_code`),
  ADD KEY `teacher_code` (`teacher_code`);

--
-- Indexes for table `grade_schedule_template`
--
ALTER TABLE `grade_schedule_template`
  ADD PRIMARY KEY (`template_id`),
  ADD UNIQUE KEY `unique_grade_schedule` (`grade_level`,`day_of_week`,`slot_id`),
  ADD KEY `slot_id` (`slot_id`),
  ADD KEY `subject_code` (`subject_code`);

--
-- Indexes for table `highlights`
--
ALTER TABLE `highlights`
  ADD PRIMARY KEY (`highlight_id`);

--
-- Indexes for table `master_time_slots`
--
ALTER TABLE `master_time_slots`
  ADD PRIMARY KEY (`slot_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `parent_code` (`parent_code`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `student_code` (`student_code`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `payment_reminders`
--
ALTER TABLE `payment_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sent_by` (`sent_by`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_sent_date` (`sent_date`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`);

--
-- Indexes for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `unique_section` (`grade_level`,`section_name`,`school_year`),
  ADD KEY `adviser_code` (`adviser_code`);

--
-- Indexes for table `section_schedules`
--
ALTER TABLE `section_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `unique_section_schedule` (`section_id`,`template_id`,`school_year`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `teacher_code` (`teacher_code`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `student_balances`
--
ALTER TABLE `student_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `student_code` (`student_code`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `teacher_code` (`teacher_code`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`testimonial_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=601;

--
-- AUTO_INCREMENT for table `available_dates`
--
ALTER TABLE `available_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=448;

--
-- AUTO_INCREMENT for table `grade_schedule_template`
--
ALTER TABLE `grade_schedule_template`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `highlights`
--
ALTER TABLE `highlights`
  MODIFY `highlight_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `master_time_slots`
--
ALTER TABLE `master_time_slots`
  MODIFY `slot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payment_reminders`
--
ALTER TABLE `payment_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `section_schedules`
--
ALTER TABLE `section_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=512;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `student_balances`
--
ALTER TABLE `student_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `testimonial_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_code`) REFERENCES `subjects` (`subject_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`teacher_code`) REFERENCES `teachers` (`teacher_code`) ON DELETE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_code`) REFERENCES `subjects` (`subject_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`teacher_code`) REFERENCES `teachers` (`teacher_code`) ON DELETE CASCADE;

--
-- Constraints for table `grade_schedule_template`
--
ALTER TABLE `grade_schedule_template`
  ADD CONSTRAINT `grade_schedule_template_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `master_time_slots` (`slot_id`),
  ADD CONSTRAINT `grade_schedule_template_ibfk_2` FOREIGN KEY (`subject_code`) REFERENCES `subjects` (`subject_code`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payment_reminders`
--
ALTER TABLE `payment_reminders`
  ADD CONSTRAINT `payment_reminders_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_reminders_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`adviser_code`) REFERENCES `teachers` (`teacher_code`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `section_schedules`
--
ALTER TABLE `section_schedules`
  ADD CONSTRAINT `section_schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_schedules_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `grade_schedule_template` (`template_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `section_schedules_ibfk_3` FOREIGN KEY (`teacher_code`) REFERENCES `teachers` (`teacher_code`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_balances`
--
ALTER TABLE `student_balances`
  ADD CONSTRAINT `student_balances_ibfk_1` FOREIGN KEY (`student_code`) REFERENCES `students` (`student_code`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

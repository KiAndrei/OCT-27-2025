-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2025 at 06:15 AM
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
-- Database: `lawfirm`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_documents`
--

CREATE TABLE `admin_documents` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `form_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_document_activity`
--

CREATE TABLE `admin_document_activity` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `form_number` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_cases`
--

CREATE TABLE `attorney_cases` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `case_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `next_hearing` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_documents`
--

CREATE TABLE `attorney_documents` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `case_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_document_activity`
--

CREATE TABLE `attorney_document_activity` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attorney_messages`
--

CREATE TABLE `attorney_messages` (
  `id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_type` enum('admin','attorney','client','employee') NOT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed','warning','info') DEFAULT 'success',
  `priority` enum('low','medium','high','critical') DEFAULT 'low',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_id`, `user_name`, `user_type`, `action`, `module`, `description`, `ip_address`, `user_agent`, `status`, `priority`, `timestamp`, `additional_data`) VALUES
(1382, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:04', NULL),
(1383, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:48', NULL),
(1384, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:49', NULL),
(1385, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:50', NULL),
(1386, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:52', NULL),
(1387, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:53', NULL),
(1388, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:57', NULL),
(1389, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:37:57', NULL),
(1390, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:39:54', NULL),
(1391, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:39:57', NULL),
(1392, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:39:58', NULL),
(1393, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:06', NULL),
(1394, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:09', NULL),
(1395, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:10', NULL),
(1396, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:28', NULL),
(1397, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:29', NULL),
(1398, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:30', NULL),
(1399, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:31', NULL),
(1400, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:37', NULL),
(1401, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:41', NULL),
(1402, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:43', NULL),
(1403, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:44', NULL),
(1404, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:47', NULL),
(1405, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:50', NULL),
(1406, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:54', NULL),
(1407, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:40:59', NULL),
(1408, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:41:00', NULL),
(1409, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:41:29', NULL),
(1410, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:42:02', NULL),
(1411, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:42:07', NULL),
(1412, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:42:08', NULL),
(1413, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:42:09', NULL),
(1414, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:42:16', NULL),
(1415, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:42', NULL),
(1416, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:46', NULL),
(1417, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:51', NULL),
(1418, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:52', NULL),
(1419, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:57', NULL),
(1420, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:43:58', NULL),
(1421, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:44:00', NULL),
(1422, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:44:01', NULL),
(1423, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:44:53', NULL),
(1424, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 00:44:58', NULL),
(1425, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:44:58', NULL),
(1426, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:44:58', NULL),
(1427, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:45:01', NULL),
(1428, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:46:41', NULL),
(1429, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:47:08', NULL),
(1430, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:47:20', NULL),
(1431, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 00:47:20', NULL),
(1432, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:47:20', NULL),
(1433, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:48:14', NULL),
(1434, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 00:48:19', NULL),
(1435, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:48:19', NULL),
(1436, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:48:21', NULL),
(1437, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:49:50', NULL),
(1438, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Mario Delmo Refrea (mariorefrea2001@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 00:49:54', NULL),
(1439, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:49:54', NULL),
(1440, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:50:03', NULL),
(1441, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:50:06', NULL),
(1442, 20, 'Mar John Refrea', 'admin', 'Failed Login Attempt', 'Security', 'Failed login attempt from IP: ::1 for email: marjohnrefrea123456@gmail.com (Attempt 1/5)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'warning', 'medium', '2025-09-12 00:50:25', NULL),
(1444, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:50:31', NULL),
(1445, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:50:52', NULL),
(1446, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:53:20', NULL),
(1447, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new employee account: Yuhan Nerfy Sheesh (yuhanerfy@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 00:53:25', NULL),
(1448, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:53:25', NULL),
(1449, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:53:32', NULL),
(1450, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:54:57', NULL),
(1451, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:55:49', NULL),
(1452, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:11', NULL),
(1453, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Mario Delmo Refrea (mariorefrea2001@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 00:56:11', NULL),
(1454, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:11', NULL),
(1455, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:12', NULL),
(1456, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:14', NULL),
(1457, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:23', NULL),
(1458, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Yuhan Nerfy Sheesh (yuhanerfy@gmail.com) - Type: employee', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 00:56:23', NULL),
(1459, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:23', NULL),
(1460, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:33', NULL),
(1461, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 00:56:33', NULL),
(1462, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:33', NULL),
(1463, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:56:35', NULL),
(1464, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 00:59:09', NULL),
(1465, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:03:20', NULL),
(1466, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:05:03', NULL),
(1467, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 01:05:07', NULL),
(1468, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:05:07', NULL),
(1469, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:05:24', NULL),
(1470, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:09:30', NULL),
(1471, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:05', NULL),
(1472, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:12', NULL),
(1473, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:14', NULL),
(1474, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:17', NULL),
(1475, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:56', NULL),
(1476, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:14:56', NULL),
(1477, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:15:03', NULL),
(1478, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:15:54', NULL),
(1479, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:15:54', NULL),
(1480, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:15:57', NULL),
(1481, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:16:58', NULL),
(1482, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:19:10', NULL),
(1483, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 01:19:10', NULL),
(1484, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:19:10', NULL),
(1485, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:19:11', NULL),
(1486, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:20:07', NULL),
(1487, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 01:20:11', NULL),
(1488, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:20:11', NULL),
(1489, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:20:14', NULL),
(1490, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:22:41', NULL),
(1491, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:22:47', NULL),
(1492, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:22:52', NULL),
(1493, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:23:06', NULL),
(1494, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:23:56', NULL),
(1495, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:23:56', NULL),
(1496, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:24:03', NULL),
(1497, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:27:22', NULL),
(1498, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:28:11', NULL),
(1499, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Yuhan Nerfy Sheesh (yuhanerfy@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 01:28:16', NULL),
(1500, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:28:35', NULL),
(1501, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:28:42', NULL),
(1512, 20, 'Mar John Refrea', 'admin', 'Failed Login Attempt', 'Security', 'Failed login attempt from IP: ::1 for email: marjohnrefrea123456@gmail.com (Attempt 1/5)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'warning', 'medium', '2025-09-12 01:29:46', NULL),
(1514, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:29:53', NULL),
(1515, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:29:54', NULL),
(1516, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:30:39', NULL),
(1517, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:31:02', NULL),
(1518, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:31:04', NULL),
(1519, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:31:48', NULL),
(1520, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:33:39', NULL),
(1521, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:33:43', NULL),
(1522, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:33:44', NULL),
(1523, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:34:50', NULL),
(1524, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:34:58', NULL),
(1525, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:01', NULL),
(1526, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Yuhan Nerfy Sheesh (yuhanerfy@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 01:36:01', NULL),
(1527, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:01', NULL),
(1528, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:09', NULL),
(1529, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-12 01:36:09', NULL),
(1530, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:09', NULL),
(1531, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:12', NULL),
(1532, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:30', NULL),
(1533, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 01:36:42', NULL),
(1534, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 06:31:12', NULL),
(1535, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 06:31:17', NULL),
(1536, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:33:46', NULL),
(1537, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:33:50', NULL),
(1538, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:33:57', NULL),
(1539, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:33:59', NULL),
(1540, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:34:01', NULL),
(1541, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:34:09', NULL),
(1542, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:34:17', NULL),
(1543, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:34:36', NULL),
(1544, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:35:49', NULL),
(1545, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:35:50', NULL),
(1546, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:35:56', NULL),
(1547, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:37:48', NULL),
(1548, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:37:49', NULL),
(1549, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:40:13', NULL),
(1550, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:40:14', NULL),
(1551, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:41:01', NULL),
(1552, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-12 16:41:22', NULL),
(1553, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:41:22', NULL),
(1554, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:45:30', NULL),
(1555, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:45:59', NULL),
(1556, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-12 16:48:05', NULL),
(1557, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:38:56', NULL),
(1558, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:38:57', NULL),
(1559, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:38:59', NULL),
(1560, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:39:00', NULL),
(1561, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:42:20', NULL),
(1562, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:42:21', NULL),
(1563, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:42:29', NULL),
(1564, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-13 02:42:29', NULL),
(1565, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:42:29', NULL),
(1566, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:44:37', NULL),
(1567, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:44:59', NULL),
(1568, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:45:26', NULL),
(1569, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:48:00', NULL),
(1570, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:48:01', NULL),
(1571, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:48:52', NULL);
INSERT INTO `audit_trail` (`id`, `user_id`, `user_name`, `user_type`, `action`, `module`, `description`, `ip_address`, `user_agent`, `status`, `priority`, `timestamp`, `additional_data`) VALUES
(1572, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:49:19', NULL),
(1573, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:53:07', NULL),
(1574, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:53:08', NULL),
(1575, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:53:26', NULL),
(1576, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:54:05', NULL),
(1577, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:54:11', NULL),
(1578, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:54:12', NULL),
(1579, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:56:16', NULL),
(1580, 20, 'Mar John Refrea', 'admin', 'Failed Login Attempt', 'Security', 'Failed login attempt from IP: ::1 for email: marjohnrefrea123456@gmail.com (Attempt 1/5)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'warning', 'medium', '2025-09-13 02:59:06', NULL),
(1582, 20, 'Mar John Refrea', 'admin', 'Failed Login Attempt', 'Security', 'Failed login attempt from IP: ::1 for email: marjohnrefrea123456@gmail.com (Attempt 2/5)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'warning', 'medium', '2025-09-13 02:59:11', NULL),
(1584, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:59:17', NULL),
(1585, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 02:59:18', NULL),
(1586, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:01:17', NULL),
(1587, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:02:30', NULL),
(1588, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:03:37', NULL),
(1589, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:04:29', NULL),
(1590, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:09:18', NULL),
(1591, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:11:29', NULL),
(1592, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: Laica Castillo Refrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-13 03:11:34', NULL),
(1593, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:11:34', NULL),
(1594, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:11:37', NULL),
(1595, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:12:24', NULL),
(1598, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:47', NULL),
(1599, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:49', NULL),
(1600, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:51', NULL),
(1601, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:52', NULL),
(1602, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:53', NULL),
(1603, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:54', NULL),
(1604, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:57', NULL),
(1605, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:18:58', NULL),
(1606, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:09', NULL),
(1607, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Santiago, Deym Poosh (baomacky99@gmail.com) - Type: client', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-13 03:19:09', NULL),
(1608, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:09', NULL),
(1609, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:17', NULL),
(1610, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Laica Castillo Refrea (marjohnrefrea1215@gmail.com) - Type: attorney', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-13 03:19:17', NULL),
(1611, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:17', NULL),
(1612, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:25', NULL),
(1613, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: Yuhan, Shoono Deym (yuhanerfy@gmail.com) - Type: client', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-13 03:19:25', NULL),
(1614, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:19:25', NULL),
(1615, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:21:43', NULL),
(1616, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:10', NULL),
(1617, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:21', NULL),
(1618, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_audit', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:22', NULL),
(1619, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:23', NULL),
(1620, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:25', NULL),
(1621, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:26', NULL),
(1622, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:22:33', NULL),
(1623, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:27:03', NULL),
(1624, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:38:10', NULL),
(1625, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:38:15', NULL),
(1626, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:46:20', NULL),
(1627, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:46:21', NULL),
(1628, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:46:22', NULL),
(1629, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:47:04', NULL),
(1630, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:47:06', NULL),
(1631, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:47:12', NULL),
(1632, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:49:05', NULL),
(1633, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new attorney account: LaicaCastilloRefrea (marjohnrefrea1215@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-13 03:49:10', NULL),
(1634, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:49:10', NULL),
(1635, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:49:12', NULL),
(1636, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:52:16', NULL),
(1637, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new employee account: YuhanNerfySheesh (yuhanerfy@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-13 03:52:16', NULL),
(1638, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:52:16', NULL),
(1639, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:52:18', NULL),
(1640, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:57:14', NULL),
(1641, 20, 'Mar John Refrea', 'admin', 'User Delete', 'User Management', 'Deleted user: YuhanNerfySheesh (yuhanerfy@gmail.com) - Type: employee', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'high', '2025-09-13 03:57:14', NULL),
(1642, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:57:14', NULL),
(1643, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: add_user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:58:16', NULL),
(1644, 20, 'Mar John Refrea', 'admin', 'User Create', 'User Management', 'Created new employee account: YuhanNerfySheesh (yuhanerfy@gmail.com)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'medium', '2025-09-13 03:58:21', NULL),
(1645, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:58:21', NULL),
(1646, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:59:06', NULL),
(1647, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 03:59:26', NULL),
(1648, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:01:53', NULL),
(1649, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:01:55', NULL),
(1650, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:01:56', NULL),
(1651, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:01:57', NULL),
(1652, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:01:59', NULL),
(1653, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:00', NULL),
(1654, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:01', NULL),
(1655, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:02', NULL),
(1656, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_clients', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:04', NULL),
(1657, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_managecases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:05', NULL),
(1658, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_usermanagement', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:06', NULL),
(1659, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_schedule', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:07', NULL),
(1660, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_document_generation', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:09', NULL),
(1661, 20, 'Mar John Refrea', 'admin', 'Page Access', 'Page Access', 'Accessed page: admin_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:16', NULL),
(1662, 20, 'Mar John Refrea', 'admin', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:22', NULL),
(1663, 56, 'Bao, Macky Sheesh', 'client', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:31', NULL),
(1664, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_documents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:33', NULL),
(1665, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_cases', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:34', NULL),
(1666, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:37', NULL),
(1667, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_request_form', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:37', NULL),
(1668, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_messages', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:46', NULL),
(1669, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_request_form', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:02:46', NULL),
(1670, 56, 'Bao, Macky Sheesh', 'client', 'Page Access', 'Page Access', 'Accessed page: client_request_form', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:06:15', NULL),
(1671, 56, 'Bao, Macky Sheesh', 'client', 'User Logout', 'Authentication', 'User logged out successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:06:28', NULL),
(1672, 20, 'Mar John Refrea', 'admin', 'User Login', 'Authentication', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'success', 'low', '2025-09-13 04:06:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_schedules`
--

CREATE TABLE `case_schedules` (
  `id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `attorney_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type` enum('Hearing','Appointment','Free Legal Advice') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by_employee_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_attorney_assignments`
--

CREATE TABLE `client_attorney_assignments` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `assignment_reason` text DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Completed','Cancelled') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_attorney_conversations`
--

CREATE TABLE `client_attorney_conversations` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `conversation_status` enum('Active','Completed','Closed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_attorney_messages`
--

CREATE TABLE `client_attorney_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('client','attorney') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_cases`
--

CREATE TABLE `client_cases` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `attorney_id` int(11) DEFAULT NULL,
  `case_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `next_hearing` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_employee_conversations`
--

CREATE TABLE `client_employee_conversations` (
  `id` int(11) NOT NULL,
  `request_form_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `conversation_status` enum('Active','Completed','Closed') DEFAULT 'Active',
  `concern_identified` tinyint(1) DEFAULT 0,
  `concern_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_employee_messages`
--

CREATE TABLE `client_employee_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('client','employee') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_messages`
--

CREATE TABLE `client_messages` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_request_form`
--

CREATE TABLE `client_request_form` (
  `id` int(11) NOT NULL,
  `request_id` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `valid_id_path` varchar(500) NOT NULL,
  `valid_id_filename` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Requested','Submitted','Reviewed','Approved','Rejected','Cancelled') DEFAULT 'Requested',
  `attorney_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_request_comments`
--

CREATE TABLE `document_request_comments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_request_files`
--

CREATE TABLE `document_request_files` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `efiling_history`
--

CREATE TABLE `efiling_history` (
  `id` int(11) NOT NULL,
  `attorney_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `case_number` varchar(100) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_file_name` varchar(255) DEFAULT NULL,
  `stored_file_path` varchar(500) DEFAULT NULL,
  `receiver_email` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Sent','Failed') NOT NULL DEFAULT 'Sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` datetime NOT NULL DEFAULT current_timestamp(),
  `form_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_document_activity`
--

CREATE TABLE `employee_document_activity` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `form_number` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_messages`
--

CREATE TABLE `employee_messages` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_request_reviews`
--

CREATE TABLE `employee_request_reviews` (
  `id` int(11) NOT NULL,
  `request_form_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `action` enum('Approved','Rejected') NOT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('info','success','warning','error') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_form`
--

CREATE TABLE `user_form` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','attorney','client','employee') DEFAULT 'client',
  `login_attempts` int(11) DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `lockout_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_form`
--

INSERT INTO `user_form` (`id`, `name`, `profile_image`, `last_login`, `email`, `phone_number`, `password`, `user_type`, `login_attempts`, `last_failed_login`, `account_locked`, `lockout_until`, `created_at`) VALUES
(20, 'Mar John Refrea', 'uploads/admin/20_1755155087.png', '2025-09-13 12:06:32', 'marjohnrefrea123456@gmail.com', '09283262333', '$2y$10$yrs9n1Z/Nrq1d5XLvNihTOeRiq037s.NGo9wtXMjbNOkqlWyLOOwy', 'admin', 0, NULL, 0, NULL, '2025-08-06 11:26:01'),
(53, 'LaicaCastilloRefrea', NULL, NULL, 'marjohnrefrea1215@gmail.com', '09319297173', '$2y$10$oG5SK79cl3AUYfvWCvoq7uDM9rn7BfvYOQCTPMlqgyQabI235vpt6', 'attorney', 0, NULL, 0, NULL, '2025-09-13 03:49:05'),
(55, 'YuhanNerfySheesh', NULL, NULL, 'yuhanerfy@gmail.com', '09319297173', '$2y$10$q5xYBAG4adqsfhETsAcTaeweEdfa.KNNzjfHxY9JrOVIBlFD.4N6y', 'employee', 0, NULL, 0, NULL, '2025-09-13 03:58:16'),
(56, 'Bao, Macky Sheesh', NULL, '2025-09-13 12:02:31', 'baomacky99@gmail.com', '09283262333', '$2y$10$YYs9Yx/ywgUGk5bVXm/P6.pcUP4/aLTfZJGOGYlbGEXqi7BurLFJ2', 'client', 0, NULL, 0, NULL, '2025-09-13 04:01:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_documents`
--
ALTER TABLE `admin_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `admin_document_activity`
--
ALTER TABLE `admin_document_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `attorney_cases`
--
ALTER TABLE `attorney_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `attorney_documents`
--
ALTER TABLE `attorney_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `attorney_document_activity`
--
ALTER TABLE `attorney_document_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `attorney_messages`
--
ALTER TABLE `attorney_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `case_schedules`
--
ALTER TABLE `case_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by_employee` (`created_by_employee_id`),
  ADD KEY `case_schedules_ibfk_1` (`case_id`),
  ADD KEY `case_schedules_ibfk_2` (`attorney_id`),
  ADD KEY `case_schedules_ibfk_3` (`client_id`);

--
-- Indexes for table `client_attorney_assignments`
--
ALTER TABLE `client_attorney_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `idx_attorney_assignments` (`attorney_id`,`status`);

--
-- Indexes for table `client_attorney_conversations`
--
ALTER TABLE `client_attorney_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `idx_client_attorney_conv` (`client_id`,`conversation_status`);

--
-- Indexes for table `client_attorney_messages`
--
ALTER TABLE `client_attorney_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `sent_at` (`sent_at`),
  ADD KEY `idx_client_attorney_msgs` (`conversation_id`,`sent_at`);

--
-- Indexes for table `client_cases`
--
ALTER TABLE `client_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `attorney_id` (`attorney_id`);

--
-- Indexes for table `client_employee_conversations`
--
ALTER TABLE `client_employee_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_form_id` (`request_form_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_client_employee_conv` (`client_id`,`conversation_status`);

--
-- Indexes for table `client_employee_messages`
--
ALTER TABLE `client_employee_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `sent_at` (`sent_at`),
  ADD KEY `idx_client_employee_msgs` (`conversation_id`,`sent_at`);

--
-- Indexes for table `client_messages`
--
ALTER TABLE `client_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `client_request_form`
--
ALTER TABLE `client_request_form`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_client_request_status` (`status`,`submitted_at`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `document_request_comments`
--
ALTER TABLE `document_request_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `document_request_files`
--
ALTER TABLE `document_request_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `efiling_history`
--
ALTER TABLE `efiling_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attorney_id` (`attorney_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `form_number` (`form_number`);

--
-- Indexes for table `employee_document_activity`
--
ALTER TABLE `employee_document_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `form_number` (`form_number`);

--
-- Indexes for table `employee_messages`
--
ALTER TABLE `employee_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `employee_request_reviews`
--
ALTER TABLE `employee_request_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_form_id` (`request_form_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employee_reviews` (`employee_id`,`reviewed_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_form`
--
ALTER TABLE `user_form`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_documents`
--
ALTER TABLE `admin_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_document_activity`
--
ALTER TABLE `admin_document_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attorney_cases`
--
ALTER TABLE `attorney_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attorney_documents`
--
ALTER TABLE `attorney_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attorney_document_activity`
--
ALTER TABLE `attorney_document_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attorney_messages`
--
ALTER TABLE `attorney_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1673;

--
-- AUTO_INCREMENT for table `case_schedules`
--
ALTER TABLE `case_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `client_attorney_assignments`
--
ALTER TABLE `client_attorney_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `client_attorney_conversations`
--
ALTER TABLE `client_attorney_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `client_attorney_messages`
--
ALTER TABLE `client_attorney_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `client_cases`
--
ALTER TABLE `client_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_employee_conversations`
--
ALTER TABLE `client_employee_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `client_employee_messages`
--
ALTER TABLE `client_employee_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `client_messages`
--
ALTER TABLE `client_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_request_form`
--
ALTER TABLE `client_request_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `document_request_comments`
--
ALTER TABLE `document_request_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_request_files`
--
ALTER TABLE `document_request_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `efiling_history`
--
ALTER TABLE `efiling_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_document_activity`
--
ALTER TABLE `employee_document_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_messages`
--
ALTER TABLE `employee_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_request_reviews`
--
ALTER TABLE `employee_request_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_form`
--
ALTER TABLE `user_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_documents`
--
ALTER TABLE `admin_documents`
  ADD CONSTRAINT `admin_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_document_activity`
--
ALTER TABLE `admin_document_activity`
  ADD CONSTRAINT `admin_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `admin_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attorney_cases`
--
ALTER TABLE `attorney_cases`
  ADD CONSTRAINT `attorney_cases_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attorney_cases_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attorney_documents`
--
ALTER TABLE `attorney_documents`
  ADD CONSTRAINT `attorney_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attorney_documents_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attorney_document_activity`
--
ALTER TABLE `attorney_document_activity`
  ADD CONSTRAINT `attorney_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `attorney_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attorney_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attorney_document_activity_ibfk_3` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attorney_messages`
--
ALTER TABLE `attorney_messages`
  ADD CONSTRAINT `attorney_messages_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attorney_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `case_schedules`
--
ALTER TABLE `case_schedules`
  ADD CONSTRAINT `case_schedules_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_schedules_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_schedules_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_schedules_ibfk_4` FOREIGN KEY (`created_by_employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_attorney_assignments`
--
ALTER TABLE `client_attorney_assignments`
  ADD CONSTRAINT `client_attorney_assignments_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `client_employee_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_assignments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_assignments_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_assignments_ibfk_4` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_attorney_conversations`
--
ALTER TABLE `client_attorney_conversations`
  ADD CONSTRAINT `client_attorney_conversations_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `client_attorney_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_conversations_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_conversations_ibfk_3` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_attorney_messages`
--
ALTER TABLE `client_attorney_messages`
  ADD CONSTRAINT `client_attorney_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `client_attorney_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_attorney_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_cases`
--
ALTER TABLE `client_cases`
  ADD CONSTRAINT `client_cases_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_cases_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_employee_conversations`
--
ALTER TABLE `client_employee_conversations`
  ADD CONSTRAINT `client_employee_conversations_ibfk_1` FOREIGN KEY (`request_form_id`) REFERENCES `client_request_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_employee_conversations_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_employee_conversations_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_employee_messages`
--
ALTER TABLE `client_employee_messages`
  ADD CONSTRAINT `client_employee_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `client_employee_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_employee_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_messages`
--
ALTER TABLE `client_messages`
  ADD CONSTRAINT `client_messages_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_request_form`
--
ALTER TABLE `client_request_form`
  ADD CONSTRAINT `client_request_form_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_request_form_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `user_form` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_request_comments`
--
ALTER TABLE `document_request_comments`
  ADD CONSTRAINT `document_request_comments_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_request_files`
--
ALTER TABLE `document_request_files`
  ADD CONSTRAINT `document_request_files_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_request_files_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `efiling_history`
--
ALTER TABLE `efiling_history`
  ADD CONSTRAINT `efiling_history_ibfk_1` FOREIGN KEY (`attorney_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `efiling_history_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `attorney_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_document_activity`
--
ALTER TABLE `employee_document_activity`
  ADD CONSTRAINT `employee_document_activity_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `employee_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_document_activity_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_messages`
--
ALTER TABLE `employee_messages`
  ADD CONSTRAINT `employee_messages_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_request_reviews`
--
ALTER TABLE `employee_request_reviews`
  ADD CONSTRAINT `employee_request_reviews_ibfk_1` FOREIGN KEY (`request_form_id`) REFERENCES `client_request_form` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_request_reviews_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user_form` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

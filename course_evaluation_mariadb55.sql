-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 22, 2026 at 12:44 PM
-- Server version: 5.5.68-MariaDB - MariaDB Server
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `course_evaluation`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_year`
--

DROP TABLE IF EXISTS `academic_year`;
CREATE TABLE IF NOT EXISTS `academic_year` (
  `academic_year_id` int NOT NULL AUTO_INCREMENT,
  `start_year` int NOT NULL,
  `end_year` int AS ((`start_year` + 1)) PERSISTENT,
  `year_label` varchar(9) COLLATE utf8mb4_unicode_ci AS (concat(`start_year`, '/', (`start_year` + 1))) PERSISTENT,
  `is_active` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`academic_year_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_year`
--

INSERT INTO `academic_year` (`academic_year_id`, `start_year`, `is_active`, `created_at`) VALUES
(1, 2024, 1, '2026-02-13 11:36:36'),
(2, 2023, 0, '2026-02-13 11:36:36');

-- --------------------------------------------------------

--
-- Table structure for table `advisor_levels`
--

DROP TABLE IF EXISTS `advisor_levels`;
CREATE TABLE IF NOT EXISTS `advisor_levels` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `level_id` int NOT NULL,
  `department_id` int NOT NULL,
  `advisor_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  KEY `idx_level` (`level_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_advisor` (`advisor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `advisor_levels`
--

INSERT INTO `advisor_levels` (`t_id`, `level_id`, `department_id`, `advisor_id`, `created_at`) VALUES
(2, 1, 2, 11, '2026-02-13 11:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, LOGOUT, etc.',
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON format',
  `new_values` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON format',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_composite` (`user_id`,`action_type`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-13 12:20:00'),
(2, 2, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-13 12:28:46'),
(3, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-13 12:49:10'),
(4, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-18 22:05:12'),
(5, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 08:06:30'),
(6, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 09:36:56'),
(7, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 09:38:27'),
(8, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 09:40:52'),
(9, 2, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 09:41:51'),
(10, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-19 09:44:46'),
(11, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-24 12:57:11'),
(12, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-24 17:11:10'),
(13, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-24 17:36:16'),
(14, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 09:51:30'),
(15, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 11:29:46'),
(16, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 11:30:18'),
(17, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 11:30:31'),
(18, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 17:26:33'),
(19, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 17:30:37'),
(20, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 17:31:00'),
(21, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-25 17:33:00'),
(22, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-26 15:04:38'),
(23, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-26 15:06:49'),
(24, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-26 15:11:01'),
(25, 24, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-26 15:16:11'),
(26, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-02-26 21:37:01'),
(27, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 21:40:09'),
(28, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 21:43:08'),
(29, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 21:43:57'),
(30, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 21:44:58'),
(31, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 22:00:29'),
(32, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 22:02:55'),
(33, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 22:03:47'),
(34, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 22:04:02'),
(35, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-26 22:09:50'),
(36, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-28 22:13:21'),
(37, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-02 08:12:24'),
(38, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-02 09:37:56'),
(39, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:44:29'),
(40, 2, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:45:46'),
(41, 21, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:47:15'),
(42, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:47:57'),
(43, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:48:24'),
(44, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 18:49:19'),
(45, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 19:06:58'),
(46, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 19:13:52'),
(47, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 19:23:05'),
(48, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-03 19:34:32'),
(49, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:14:04'),
(50, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:30:19'),
(51, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:31:08'),
(52, 4, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:31:45'),
(53, 21, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:32:07'),
(54, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:32:44'),
(55, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:33:51'),
(56, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 11:54:59'),
(57, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 12:32:32'),
(58, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 13:03:30'),
(59, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-16 18:39:01'),
(60, 12, 'LOGIN', 'user_details', 12, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:37:32'),
(61, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:38:18'),
(62, 1, 'LOGIN', 'user_details', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:38:35'),
(63, 1, 'TOKEN_GENERATE', 'evaluation_tokens', NULL, NULL, '{\"count\":3,\"department_id\":2,\"level_id\":1,\"academic_year_id\":\"1\",\"semester_id\":\"2\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:43:01'),
(64, 1, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:43:12'),
(65, 12, 'LOGIN', 'user_details', 12, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 12:43:24'),
(66, 12, 'LOGIN', 'user_details', 12, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:28:28'),
(67, 12, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:31:01'),
(68, 2, 'LOGIN', 'user_details', 2, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:31:27'),
(69, 2, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:39:11'),
(70, 3, 'LOGIN', 'user_details', 3, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:39:24'),
(71, 3, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:40:48'),
(72, 0, 'LOGIN_FAILED', 'user_details', 11, '{\"username\":\"sam@gmail.com\",\"reason\":\"wrong_password\"}', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:41:03'),
(73, 11, 'LOGIN', 'user_details', 11, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:41:08'),
(74, 11, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:42:04'),
(75, 20, 'LOGIN', 'user_details', 20, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:42:19'),
(76, 20, 'LOGOUT', NULL, NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 13:44:31'),
(77, 12, 'LOGIN', 'user_details', 12, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-03-24 22:51:25'),
(78, 12, 'LOGIN', 'user_details', 12, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-04-08 09:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `department_id` int NOT NULL,
  `advisor_user_id` int DEFAULT NULL,
  `year_of_completion` int NOT NULL,
  `programme_id` int NOT NULL,
  `level_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `class_name` (`class_name`),
  KEY `idx_department` (`department_id`),
  KEY `idx_programme` (`programme_id`),
  KEY `idx_level` (`level_id`),
  KEY `idx_advisor_user_id` (`advisor_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`t_id`, `class_name`, `class_code`, `department_id`, `advisor_user_id`, `year_of_completion`, `programme_id`, `level_id`, `created_at`) VALUES
(1, 'BIT28', '', 2, NULL, 2028, 2, 1, '2026-02-13 11:36:39'),
(2, 'BIT27', '', 2, NULL, 2027, 2, 2, '2026-02-13 11:36:39');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` int NOT NULL,
  `level_id` int NOT NULL,
  `semester_id` tinyint NOT NULL,
  `credit_hours` tinyint(3) UNSIGNED NOT NULL DEFAULT '3',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `idx_department` (`department_id`),
  KEY `idx_level` (`level_id`),
  KEY `idx_semester` (`semester_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `name`, `department_id`, `level_id`, `semester_id`, `credit_hours`, `created_at`) VALUES
(1, 'BINT 108', 'Principles of Programming and Problem Solving', 2, 1, 1, 3, '2026-02-13 11:36:39'),
(5, 'BINT 109', 'INTRO TO WEB DESIGN', 2, 2, 2, 3, '2026-02-13 11:36:39');

-- --------------------------------------------------------

--
-- Table structure for table `course_lecturers`
--

DROP TABLE IF EXISTS `course_lecturers`;
CREATE TABLE IF NOT EXISTS `course_lecturers` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `lecturer_user_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `semester_id` int NOT NULL,
  `assigned_by` int NOT NULL COMMENT 'user_id of HOD who made assignment',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`assignment_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_lecturer` (`lecturer_user_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_semester` (`semester_id`),
  KEY `idx_composite` (`course_id`,`academic_year_id`,`semester_id`),
  KEY `idx_assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

DROP TABLE IF EXISTS `department`;
CREATE TABLE IF NOT EXISTS `department` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `hod_id` int NOT NULL DEFAULT '0',
  `dep_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dep_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `dep_code` (`dep_code`),
  KEY `idx_hod_id` (`hod_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`t_id`, `hod_id`, `dep_name`, `dep_code`, `created_at`) VALUES
(1, 4, 'Department Of Transport', 'DOT', '2026-02-13 11:36:37'),
(2, 2, 'ICT', 'ICT', '2026-02-13 11:36:37'),
(3, 5, 'Marine Engineering Department', 'MEE', '2026-02-13 11:36:37'),
(4, 7, 'Electrical Department', 'EEE', '2026-02-13 11:36:37'),
(5, 20, 'test', 'TEE', '2026-02-13 11:36:37'),
(6, 0, 'GRADUATE SCHOOL', 'GRAD001', '2026-02-13 11:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE IF NOT EXISTS `evaluations` (
  `evaluation_id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `semester_id` int NOT NULL,
  `evaluation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`evaluation_id`),
  KEY `idx_token` (`token`),
  KEY `idx_course` (`course_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_semester` (`semester_id`),
  KEY `idx_composite` (`course_id`,`academic_year_id`,`semester_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `token`, `course_id`, `academic_year_id`, `semester_id`, `evaluation_date`) VALUES
(9, 'legacy_token_9_RMUDMSHZOKWI', 5, 1, 2, '2024-11-19 21:43:06'),
(10, '3cfab82ed755ef127a6ffee55059a9337bd7fe53f95cb0f42abc771c54996c97', 1, 1, 2, '2026-03-24 13:29:27');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_questions`
--

DROP TABLE IF EXISTS `evaluation_questions`;
CREATE TABLE IF NOT EXISTS `evaluation_questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) DEFAULT '1',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `display_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_questions`
--

INSERT INTO `evaluation_questions` (`question_id`, `question_text`, `is_required`, `category`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'I was briefed on the course overview and objective', 1, 'Questions', 1, 1, '2026-02-13 11:36:44'),
(2, 'I am able to relate the theory to practical', 1, 'Questions', 2, 1, '2026-02-13 11:36:44'),
(3, 'There is adequate practical content', 1, 'Questions', 3, 1, '2026-02-13 11:36:44'),
(4, 'The lecture helped me to understand the learning materials', 1, 'Questions', 4, 1, '2026-02-13 11:36:44'),
(5, 'I was encouraged to ask questions', 1, 'Questions', 5, 1, '2026-02-13 11:36:44'),
(6, 'How would you rate the equipment (simulator, swimming pool, workshop) used for the practical session?', 1, 'Questions', 6, 1, '2026-02-13 11:36:44'),
(7, 'How would you assess the handouts provided?', 1, 'Questions', 7, 1, '2026-02-13 11:36:44'),
(8, 'Timetable was timely and adhered to', 1, 'Questions', 8, 1, '2026-02-13 11:36:44'),
(9, 'Lecturer was available as scheduled', 1, 'Questions', 9, 1, '2026-02-13 11:36:44'),
(10, 'How would you rate the performance of your class advisor?', 1, 'Questions', 10, 1, '2026-02-13 11:36:44'),
(11, 'How would you rate the assessments conducted', 1, 'Assessment', 11, 1, '2026-02-13 11:36:44'),
(12, 'Classroom environment was conducive to learning.', 1, 'Teaching and Learning Environment', 12, 1, '2026-02-13 11:36:44'),
(13, 'How would you rate other facilities such as washrooms and surroundings?', 1, 'Washroom & Surroundings', 13, 1, '2026-02-13 11:36:44'),
(14, 'Customer Service:  Staff were supportive', 1, 'Registry', 14, 1, '2026-02-13 11:36:44'),
(15, 'Turnaround time: Waiting time was short ', 1, 'Registry', 15, 1, '2026-02-13 11:36:44'),
(16, 'Feedback: received timely feedback on my request', 1, 'Registry', 16, 1, '2026-02-13 11:36:44'),
(17, 'Customer Service:  Staff were supportive', 1, 'Accounts', 17, 1, '2026-02-13 11:36:44'),
(18, 'Turnaround time: Waiting time was short ', 1, 'Accounts', 18, 1, '2026-02-13 11:36:44'),
(19, 'Feedback: received timely feedback on my request', 1, 'Accounts', 19, 1, '2026-02-13 11:36:44'),
(20, 'Customer Service:  Staff were supportive', 1, 'Library', 20, 1, '2026-02-13 11:36:44'),
(21, 'Turnaround time: Waiting time was short ', 1, 'Library', 21, 1, '2026-02-13 11:36:44'),
(22, 'Feedback: received timely feedback on my request', 1, 'Library', 22, 1, '2026-02-13 11:36:44'),
(23, 'Customer Service:  Staff were supportive', 1, 'Administration', 23, 1, '2026-02-13 11:36:44'),
(24, 'Turnaround time: Waiting time was short ', 1, 'Administration', 24, 1, '2026-02-13 11:36:44'),
(25, 'Feedback: received timely feedback on my request', 1, 'Administration', 25, 1, '2026-02-13 11:36:44'),
(26, 'Customer Service:  Staff were supportive', 1, 'Sickbay', 26, 1, '2026-02-13 11:36:44'),
(27, 'Turnaround time: Waiting time was short ', 1, 'Sickbay', 27, 1, '2026-02-13 11:36:44'),
(28, 'Feedback: received timely feedback on my request', 1, 'Sickbay', 28, 1, '2026-02-13 11:36:44'),
(29, 'test question', 1, 'Washroom & Surroundings', 29, 1, '2026-02-13 11:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_tokens`
--

DROP TABLE IF EXISTS `evaluation_tokens`;
CREATE TABLE IF NOT EXISTS `evaluation_tokens` (
  `token_id` int NOT NULL AUTO_INCREMENT,
  `student_user_id` int NOT NULL,
  `course_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `semester_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_student` (`student_user_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_semester` (`semester_id`),
  KEY `idx_is_used` (`is_used`),
  KEY `idx_composite` (`student_user_id`,`course_id`,`academic_year_id`,`semester_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_tokens`
--

INSERT INTO `evaluation_tokens` (`token_id`, `student_user_id`, `course_id`, `academic_year_id`, `semester_id`, `token`, `is_used`, `created_at`, `used_at`) VALUES
(1, 12, 1, 1, 2, '3cfab82ed755ef127a6ffee55059a9337bd7fe53f95cb0f42abc771c54996c97', 1, '2026-03-24 12:43:01', '2026-03-24 13:29:27'),
(2, 18, 1, 1, 2, 'f6741677358ce0fdcfbb8f0d760ac52c09740396c0e98fd6d87d75a9b15d9393', 0, '2026-03-24 12:43:01', NULL),
(3, 19, 1, 1, 2, '7a7d43274d3e548d80ec530d0b3c984634569100855848ddd6e030412b013cdd', 0, '2026-03-24 12:43:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `level`
--

DROP TABLE IF EXISTS `level`;
CREATE TABLE IF NOT EXISTS `level` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_number` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `level_number` (`level_number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level`
--

INSERT INTO `level` (`t_id`, `level_name`, `level_number`, `created_at`) VALUES
(1, 'Level 100', 100, '2026-02-13 11:36:37'),
(2, 'Level 200', 200, '2026-02-13 11:36:37'),
(3, 'Level 300', 300, '2026-02-13 11:36:37'),
(4, 'Level 400', 400, '2026-02-13 11:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username_attempted` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_attempted_at` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programme`
--

DROP TABLE IF EXISTS `programme`;
CREATE TABLE IF NOT EXISTS `programme` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `prog_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prog_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `prog_code` (`prog_code`),
  KEY `idx_department` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme`
--

INSERT INTO `programme` (`t_id`, `prog_code`, `prog_name`, `department_id`, `created_at`) VALUES
(2, 'BIT', 'BSc Information Technology', 2, '2026-02-13 11:36:38'),
(3, 'BCS', 'BSc Computer Science', 2, '2026-02-13 11:36:38'),
(4, 'MEE', 'Bsc Marine Engineering', 3, '2026-02-13 11:36:38');

-- --------------------------------------------------------

--
-- Table structure for table `questions_archive`
--

DROP TABLE IF EXISTS `questions_archive`;
CREATE TABLE IF NOT EXISTS `questions_archive` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_by` int DEFAULT NULL COMMENT 'user_id of admin who archived',
  PRIMARY KEY (`question_id`),
  KEY `idx_category` (`category`),
  KEY `idx_archived_by` (`archived_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

DROP TABLE IF EXISTS `responses`;
CREATE TABLE IF NOT EXISTS `responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_id` int NOT NULL,
  `question_id` int NOT NULL,
  `response_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evaluation` (`evaluation_id`),
  KEY `idx_question` (`question_id`),
  KEY `idx_composite` (`evaluation_id`,`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `evaluation_id`, `question_id`, `response_value`, `created_at`) VALUES
(1, 9, 1, '3', '2026-02-13 11:36:44'),
(2, 9, 2, '3', '2026-02-13 11:36:44'),
(3, 9, 3, '3', '2026-02-13 11:36:44'),
(4, 9, 4, '3', '2026-02-13 11:36:44'),
(5, 9, 5, '3', '2026-02-13 11:36:44'),
(6, 9, 6, '3', '2026-02-13 11:36:44'),
(7, 9, 7, '3', '2026-02-13 11:36:44'),
(8, 9, 8, '3', '2026-02-13 11:36:44'),
(9, 9, 9, '3', '2026-02-13 11:36:44'),
(10, 9, 10, '3', '2026-02-13 11:36:44'),
(11, 9, 11, '5', '2026-02-13 11:36:44'),
(12, 9, 12, '5', '2026-02-13 11:36:44'),
(13, 9, 13, '5', '2026-02-13 11:36:44'),
(14, 9, 14, '1', '2026-02-13 11:36:44'),
(15, 9, 15, '1', '2026-02-13 11:36:44'),
(16, 9, 16, '1', '2026-02-13 11:36:44'),
(17, 9, 17, '1', '2026-02-13 11:36:44'),
(18, 9, 18, '1', '2026-02-13 11:36:44'),
(19, 9, 19, '1', '2026-02-13 11:36:44'),
(20, 9, 20, '3', '2026-02-13 11:36:44'),
(21, 9, 21, '2', '2026-02-13 11:36:44'),
(22, 9, 22, '2', '2026-02-13 11:36:44'),
(23, 9, 23, '3', '2026-02-13 11:36:44'),
(24, 9, 24, '2', '2026-02-13 11:36:44'),
(25, 9, 25, '2', '2026-02-13 11:36:44'),
(26, 9, 26, '2', '2026-02-13 11:36:44'),
(27, 9, 27, '4', '2026-02-13 11:36:44'),
(28, 9, 28, '4', '2026-02-13 11:36:44'),
(29, 10, 17, '1', '2026-03-24 13:29:27'),
(30, 10, 18, '2', '2026-03-24 13:29:27'),
(31, 10, 19, '2', '2026-03-24 13:29:27'),
(32, 10, 23, '3', '2026-03-24 13:29:27'),
(33, 10, 24, '2', '2026-03-24 13:29:27'),
(34, 10, 25, '2', '2026-03-24 13:29:27'),
(35, 10, 11, '3', '2026-03-24 13:29:27'),
(36, 10, 20, '3', '2026-03-24 13:29:27'),
(37, 10, 21, '3', '2026-03-24 13:29:27'),
(38, 10, 22, '2', '2026-03-24 13:29:27'),
(39, 10, 1, '2', '2026-03-24 13:29:27'),
(40, 10, 2, '2', '2026-03-24 13:29:27'),
(41, 10, 3, '2', '2026-03-24 13:29:27'),
(42, 10, 4, '3', '2026-03-24 13:29:27'),
(43, 10, 5, '3', '2026-03-24 13:29:27'),
(44, 10, 6, '3', '2026-03-24 13:29:27'),
(45, 10, 7, '3', '2026-03-24 13:29:27'),
(46, 10, 8, '3', '2026-03-24 13:29:27'),
(47, 10, 9, '3', '2026-03-24 13:29:27'),
(48, 10, 10, '3', '2026-03-24 13:29:27'),
(49, 10, 14, '2', '2026-03-24 13:29:27'),
(50, 10, 15, '1', '2026-03-24 13:29:27'),
(51, 10, 16, '1', '2026-03-24 13:29:27'),
(52, 10, 26, '1', '2026-03-24 13:29:27'),
(53, 10, 27, '2', '2026-03-24 13:29:27'),
(54, 10, 28, '2', '2026-03-24 13:29:27'),
(55, 10, 12, '2', '2026-03-24 13:29:27'),
(56, 10, 13, '2', '2026-03-24 13:29:27'),
(57, 10, 29, '2', '2026-03-24 13:29:27');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `role_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`t_id`),
  UNIQUE KEY `role_id` (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`t_id`, `role_id`, `role_name`, `created_at`) VALUES
(1, 1, 'admin', '2026-02-13 11:36:39'),
(2, 2, 'hod', '2026-02-13 11:36:39'),
(3, 3, 'secretary', '2026-02-13 11:36:39'),
(4, 4, 'advisor', '2026-02-13 11:36:39'),
(5, 5, 'student', '2026-02-13 11:36:39'),
(6, 6, 'quality', '2026-02-24 11:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE IF NOT EXISTS `semesters` (
  `semester_id` int NOT NULL AUTO_INCREMENT,
  `academic_year_id` int NOT NULL,
  `semester_name` enum('First','Second') COLLATE utf8mb4_unicode_ci NOT NULL,
  `semester_value` tinyint(1) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`semester_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`semester_id`, `academic_year_id`, `semester_name`, `semester_value`, `is_active`, `created_at`) VALUES
(1, 1, 'First', 1, 0, '2026-02-13 11:36:37'),
(2, 1, 'Second', 2, 1, '2026-02-13 11:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `user_details`
--

DROP TABLE IF EXISTS `user_details`;
CREATE TABLE IF NOT EXISTS `user_details` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unique_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` int NOT NULL,
  `class_id` int DEFAULT NULL,
  `level_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_class` (`class_id`),
  KEY `idx_level` (`level_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_details`
--

INSERT INTO `user_details` (`user_id`, `role_id`, `f_name`, `l_name`, `username`, `email`, `unique_id`, `password`, `department_id`, `class_id`, `level_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Prosper', 'test', 'admin', 'admin@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 2, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-26 09:25:53'),
(2, 2, 'Nii Adotei', 'Addo', 'Surnii', 'niiadot19@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 2, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(3, 3, 'Selorm', 'Fugar', 'Jselly01', 'ismailabdulaisaiku@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 2, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-19 09:41:34'),
(4, 2, 'S', 'A-N', 'HOD DOT', 'san@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 1, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(5, 2, 'Harry', 'Johnson', 'HOD MEE', 'harry@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 3, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(7, 2, 'Issac', 'Nyarko', 'HOD EE', 'q@gmail.com', NULL, '$2y$10$h9W549aZiR.Y6HxscCr7OOux8CpZLOTiSCd3oEsbgxu2QTraefefi', 4, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(11, 4, 'Samuel', 'Enguah', 'L100 Advisor', 'sam@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 2, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-18 21:38:57'),
(12, 5, 'Jeff', 'Nyarko', NULL, 'jsf@gmail.com', 'RMUDMSHZOKWI', '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 2, 1, 1, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(17, 5, 'Denzel', 'Curry', NULL, 'denzel@gmail.com', 'RMU13CVP90QZ', '$2y$10$AaOAyLJao4SrT2lNdqMRHu1vjI3COiUvr23XNUqseC.YzmsoIUaHS', 2, 2, 2, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(18, 5, 'Hidaya', 'Sulemana', NULL, 'suleman@gmail.com', 'RMUYPD13BVJT', '$2y$10$zKUKBx6y0nEou.603bxVIO0qVCJilttgmgzQEZGetfphYN07Ypb5m', 2, 1, 1, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(19, 5, 'Naila', 'Alhassan', NULL, 'naila@gmail.com', 'RMUZVI0GHLCY', '$2y$10$YLHTEFhhKFaUyaM8y3eWiuhSzxTrC/iYp2w7eUMrefpGLH/dC4gzq', 2, 1, 1, 1, '2026-02-13 11:36:41', '2026-02-13 11:36:41'),
(20, 6, 'test', 'qual', 'quality', 'quality@eval.local', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 5, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-02-24 11:48:02'),
(21, 3, 'test', 'sec', 'meesec', 'y@gmail.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 3, NULL, NULL, 1, '2026-02-13 11:36:41', '2026-03-03 18:46:13'),
(23, 1, 'Admin', 'User', 'admin2', 'admin@test.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, NULL, NULL, 1, '2026-02-26 15:10:23', '2026-02-26 15:10:23'),
(24, 3, 'HOD', 'User', 'hod', 'hod@test.com', NULL, '$2y$10$/8rHe8tJ0jbySZvAlSFcX.lpn.5i353g2CaiBl9FDIs8bp9Opyqda', 5, NULL, NULL, 1, '2026-02-26 15:10:23', '2026-02-26 15:12:34'),
(27, 3, 'Secretary', 'User', 'secretary', 'secretary@test.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, 1, '2026-02-26 15:10:51', '2026-03-03 18:48:42');

-- --------------------------------------------------------

--
-- Structure for view `view_active_period`
--

DROP VIEW IF EXISTS `view_active_period`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_active_period` AS
  SELECT
    `ay`.`academic_year_id` AS `academic_year_id`,
    `ay`.`year_label`       AS `academic_year`,
    `s`.`semester_id`       AS `semester_id`,
    `s`.`semester_name`     AS `semester_name`,
    `s`.`semester_value`    AS `semester_value`
  FROM `academic_year` `ay`
  JOIN `semesters` `s` ON (`ay`.`academic_year_id` = `s`.`academic_year_id`)
  WHERE (`ay`.`is_active` = 1) AND (`s`.`is_active` = 1);

-- --------------------------------------------------------

--
-- Structure for view `view_course_evaluation_stats`
--

DROP VIEW IF EXISTS `view_course_evaluation_stats`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_course_evaluation_stats` AS
  SELECT
    `e`.`course_id`                                          AS `course_id`,
    `c`.`course_code`                                        AS `course_code`,
    `c`.`name`                                               AS `course_name`,
    `c`.`department_id`                                      AS `department_id`,
    `d`.`dep_name`                                           AS `dep_name`,
    `e`.`academic_year_id`                                   AS `academic_year_id`,
    `ay`.`year_label`                                        AS `year_label`,
    `e`.`semester_id`                                        AS `semester_id`,
    `s`.`semester_name`                                      AS `semester_name`,
    COUNT(DISTINCT `e`.`evaluation_id`)                      AS `total_evaluations`,
    `eq`.`question_id`                                       AS `question_id`,
    `eq`.`question_text`                                     AS `question_text`,
    `eq`.`category`                                          AS `category`,
    AVG(CAST(`r`.`response_value` AS DECIMAL(10,2)))         AS `avg_response`,
    STD(CAST(`r`.`response_value` AS DECIMAL(10,2)))         AS `std_response`,
    MIN(CAST(`r`.`response_value` AS DECIMAL(10,2)))         AS `min_response`,
    MAX(CAST(`r`.`response_value` AS DECIMAL(10,2)))         AS `max_response`,
    COUNT(`r`.`id`)                                          AS `response_count`
  FROM `evaluations` `e`
  JOIN `courses` `c`              ON (`e`.`course_id`        = `c`.`id`)
  JOIN `department` `d`           ON (`c`.`department_id`    = `d`.`t_id`)
  JOIN `academic_year` `ay`       ON (`e`.`academic_year_id` = `ay`.`academic_year_id`)
  JOIN `semesters` `s`            ON (`e`.`semester_id`      = `s`.`semester_id`)
  JOIN `responses` `r`            ON (`e`.`evaluation_id`    = `r`.`evaluation_id`)
  JOIN `evaluation_questions` `eq` ON (`r`.`question_id`    = `eq`.`question_id`)
  GROUP BY `e`.`course_id`, `e`.`academic_year_id`, `e`.`semester_id`, `eq`.`question_id`;

-- --------------------------------------------------------

--
-- Structure for view `view_department_courses`
--

DROP VIEW IF EXISTS `view_department_courses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_department_courses` AS
  SELECT
    `d`.`t_id`        AS `department_id`,
    `d`.`dep_name`    AS `dep_name`,
    `d`.`dep_code`    AS `dep_code`,
    `c`.`id`          AS `course_id`,
    `c`.`course_code` AS `course_code`,
    `c`.`name`        AS `course_name`,
    `c`.`level_id`    AS `level_id`,
    `c`.`semester_id` AS `semester_id`,
    `l`.`level_name`  AS `level_name`
  FROM `courses` `c`
  JOIN `department` `d` ON (`c`.`department_id` = `d`.`t_id`)
  JOIN `level` `l`      ON (`c`.`level_id`      = `l`.`t_id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2026 at 08:23 AM
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
-- Database: `powersonic_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `agent_performance`
--

CREATE TABLE `agent_performance` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `report_month` date DEFAULT NULL,
  `total_leads` int(11) DEFAULT 0,
  `ongoing_leads` int(11) DEFAULT 0,
  `won_leads` int(11) DEFAULT 0,
  `lost_leads` int(11) DEFAULT 0,
  `total_amount_won` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `opening_time` time DEFAULT '09:00:00',
  `closing_time` time DEFAULT '22:00:00',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communications`
--

CREATE TABLE `communications` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `communication_type` enum('Phone Call','Site Visit','Offer/Quotation','Email','Meeting') NOT NULL,
  `notes` text NOT NULL,
  `has_attachments` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communications`
--

INSERT INTO `communications` (`id`, `lead_id`, `communication_type`, `notes`, `has_attachments`, `created_by`, `created_at`) VALUES
(1, 1, 'Phone Call', 'Phone calls to customer', 0, 1, '2026-05-23 11:34:50'),
(2, 4, 'Meeting', 'Meeting with Mr. T and this is update', 0, 2, '2026-05-23 13:33:45'),
(3, 4, 'Site Visit', 'kjhkjhk', 0, 2, '2026-05-23 13:34:50'),
(4, 5, 'Site Visit', 'sdsdsd', 0, 1, '2026-05-24 05:11:15');

-- --------------------------------------------------------

--
-- Table structure for table `communication_attachments`
--

CREATE TABLE `communication_attachments` (
  `id` int(11) NOT NULL,
  `communication_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL DEFAULT 'Power Sonic',
  `company_logo` varchar(500) DEFAULT NULL,
  `company_favicon` varchar(500) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_mobile` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_city` varchar(100) DEFAULT NULL,
  `company_state` varchar(100) DEFAULT NULL,
  `company_country` varchar(100) DEFAULT 'Bangladesh',
  `company_postal_code` varchar(20) DEFAULT NULL,
  `company_website` varchar(200) DEFAULT NULL,
  `company_business_hours` text DEFAULT NULL,
  `company_social_facebook` varchar(255) DEFAULT NULL,
  `company_social_linkedin` varchar(255) DEFAULT NULL,
  `company_social_twitter` varchar(255) DEFAULT NULL,
  `company_social_instagram` varchar(255) DEFAULT NULL,
  `company_registration_no` varchar(100) DEFAULT NULL,
  `company_tax_id` varchar(100) DEFAULT NULL,
  `company_bin_no` varchar(100) DEFAULT NULL,
  `bank_name` varchar(200) DEFAULT NULL,
  `bank_account_name` varchar(200) DEFAULT NULL,
  `bank_account_number` varchar(100) DEFAULT NULL,
  `bank_routing_number` varchar(50) DEFAULT NULL,
  `footer_text` varchar(500) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV',
  `currency_symbol` varchar(10) DEFAULT 'BDT',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `timezone` varchar(50) DEFAULT 'Asia/Dhaka',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `company_logo`, `company_favicon`, `company_email`, `company_phone`, `company_mobile`, `company_address`, `company_city`, `company_state`, `company_country`, `company_postal_code`, `company_website`, `company_business_hours`, `company_social_facebook`, `company_social_linkedin`, `company_social_twitter`, `company_social_instagram`, `company_registration_no`, `company_tax_id`, `company_bin_no`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_routing_number`, `footer_text`, `invoice_prefix`, `currency_symbol`, `date_format`, `timezone`, `created_at`, `updated_at`) VALUES
(1, 'Power Sonic Group', 'uploads/company/company_logo_1779603615.jpeg', NULL, 'info@powersonic.com', '+880123456789', '', '', '', '', 'Bangladesh', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Copyright@ 2026 Daffodil Software Limited- Muhammad Rafiqul Alam', 'INV', 'BDT', 'Y-m-d', 'Asia/Dhaka', '2026-05-24 06:04:17', '2026-05-24 06:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `employee_details`
--

CREATE TABLE `employee_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `mobile_alternative` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `nid_number` varchar(50) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `work_location` varchar(100) DEFAULT NULL,
  `reporting_manager` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_routing_number` varchar(50) DEFAULT NULL,
  `salary_amount` decimal(12,2) DEFAULT NULL,
  `salary_currency` varchar(3) DEFAULT 'BDT',
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_linkedin` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_details`
--

INSERT INTO `employee_details` (`id`, `user_id`, `mobile_alternative`, `date_of_birth`, `gender`, `blood_group`, `nationality`, `nid_number`, `tin_number`, `present_address`, `permanent_address`, `city`, `postal_code`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `designation`, `employee_id`, `employment_type`, `work_location`, `reporting_manager`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_routing_number`, `salary_amount`, `salary_currency`, `social_facebook`, `social_linkedin`, `social_twitter`, `bio`, `created_at`, `updated_at`) VALUES
(1, 1, '', '0000-00-00', '', '', 'Bangladeshi', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0.00, 'BDT', '', '', '', '', '2026-05-24 05:39:22', '2026-05-24 05:39:22');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `lead_unique_id` varchar(20) NOT NULL,
  `user_custom_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `area` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(50) DEFAULT 'Bangladesh',
  `district` varchar(100) DEFAULT NULL,
  `police_station` varchar(100) DEFAULT NULL,
  `post_office` varchar(100) DEFAULT NULL,
  `google_map_link` text DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `priority` enum('High','Medium','Low') DEFAULT 'Medium',
  `lead_stage` varchar(50) DEFAULT 'Lead',
  `expected_amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'BDT',
  `probability` int(11) DEFAULT 0,
  `next_followup_date` date DEFAULT NULL,
  `last_contact_date` date DEFAULT NULL,
  `won_date` date DEFAULT NULL,
  `lost_reason` text DEFAULT NULL,
  `status` enum('New','Contacted','Negotiation','Converted','Lost') DEFAULT 'New',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `lead_unique_id`, `user_custom_id`, `name`, `email`, `phone`, `area`, `address`, `country`, `district`, `police_station`, `post_office`, `google_map_link`, `latitude`, `longitude`, `priority`, `lead_stage`, `expected_amount`, `currency`, `probability`, `next_followup_date`, `last_contact_date`, `won_date`, `lost_reason`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PSL-20260523-1951', NULL, 'MUHAMMAD RAFIQUL ALAM', 'rafiqulalam2@gmail.com', '01782382140', 'Dhanmondi', '102, Shukrabad, Dhanmondi\r\nDhaka', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL, 'Low', 'Lead', 150000.00, 'BDT', 40, '2026-06-05', NULL, NULL, NULL, 'Contacted', 1, '2026-05-23 06:35:51', '2026-05-23 12:32:50'),
(2, 'PSL-20260523-1935', NULL, 'MUHAMMAD RAFIQUL ALAM', 'rafiqulalam2@gmail.com', '01782382140', 'Dhanmondi', '', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL, 'Medium', 'Lead', 50000.00, 'BDT', 0, '2026-06-06', NULL, NULL, NULL, 'New', 2, '2026-05-23 12:31:21', '2026-05-23 12:31:21'),
(3, 'PSL-20260523-9155', NULL, 'MUHAMMAD RAFIQUL ALAM', 'rafiqulalam2@gmail.com', '01782382140', 'Dhanmondi', 'hjhgghjg jhg', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL, 'Medium', 'Lead', 50000.00, 'BDT', 0, '0000-00-00', NULL, NULL, NULL, 'New', 2, '2026-05-23 13:26:30', '2026-05-23 13:26:30'),
(4, 'PSL-20260523-4674', NULL, 'MUHAMMAD RAFIQUL ALAM', 'rafiqulalam2@gmail.com', '01782382140', 'Dhanmondi', '102, Shukrabad, Dhanmondi\r\nDhaka', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL, 'Medium', 'Pipeline', 50000.00, 'BDT', 20, '2026-06-06', NULL, NULL, NULL, 'New', 2, '2026-05-23 13:27:36', '2026-05-23 13:39:02'),
(5, 'PSL-20260524-1720', '20260524-DHA-0001', 'MUHAMMAD RAFIQUL ALAM', 'rafiqulalam2@gmail.com', '01782382140', 'Dhanmondi', '102, Shukrabad, Dhanmondi\r\nDhaka', 'Bangladesh', 'Dhaka', 'Gulshan', 'Gulshan-1', 'https://maps.google.com/?q=23.754842791372504,90.37616324827506', '23.754843', '90.376163', 'Medium', 'Lead', 50000.00, 'BDT', 0, '2026-06-06', NULL, NULL, NULL, 'New', 1, '2026-05-24 05:02:11', '2026-05-24 06:21:58');

-- --------------------------------------------------------

--
-- Table structure for table `lead_amount_history`
--

CREATE TABLE `lead_amount_history` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `previous_amount` decimal(10,2) DEFAULT NULL,
  `new_amount` decimal(10,2) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_amount_history`
--

INSERT INTO `lead_amount_history` (`id`, `lead_id`, `previous_amount`, `new_amount`, `changed_by`, `changed_at`) VALUES
(1, 1, 0.00, 150000.00, 1, '2026-05-23 11:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `lead_photos`
--

CREATE TABLE `lead_photos` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `photo_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_photos`
--

INSERT INTO `lead_photos` (`id`, `lead_id`, `photo_path`, `uploaded_at`) VALUES
(1, 1, 'uploads/leads/1/1779518151_Flow Diagram.png', '2026-05-23 06:35:51'),
(2, 1, 'uploads/leads/1/1779518151_julogo.png', '2026-05-23 06:35:51'),
(3, 1, 'uploads/leads/1/1779518151_Daffodil Angel’s Daycare & Pre-School Management System overview (1).png', '2026-05-23 06:35:51'),
(4, 3, 'uploads/leads/3/1779542790_pos.ico', '2026-05-23 13:26:30');

-- --------------------------------------------------------

--
-- Table structure for table `lead_stages`
--

CREATE TABLE `lead_stages` (
  `id` int(11) NOT NULL,
  `stage_name` varchar(50) NOT NULL,
  `stage_order` int(11) NOT NULL,
  `probability_percent` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_stages`
--

INSERT INTO `lead_stages` (`id`, `stage_name`, `stage_order`, `probability_percent`, `is_active`) VALUES
(1, 'Lead', 1, 10, 1),
(2, 'Pipeline', 2, 20, 1),
(3, 'Qualified', 3, 35, 1),
(4, 'Discussion Ongoing', 4, 50, 1),
(5, 'Quotation Submitted', 5, 70, 1),
(6, 'Final Negotiation', 6, 85, 1),
(7, 'Won', 7, 100, 1),
(8, 'Lost', 8, 0, 1),
(9, 'Cancelled', 9, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `lead_id`, `notification_type`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-05', 0, '2026-05-23 11:34:15'),
(2, 1, 1, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-05', 0, '2026-05-23 11:44:23'),
(3, 2, 2, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-06', 1, '2026-05-23 12:31:21'),
(4, 1, 1, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-05', 0, '2026-05-23 12:32:44'),
(5, 1, 1, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-05', 0, '2026-05-23 12:32:50'),
(6, 2, 4, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-06', 1, '2026-05-23 13:27:36'),
(7, 2, 4, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-06', 0, '2026-05-23 13:38:45'),
(8, 2, 4, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-06', 0, '2026-05-23 13:39:02'),
(9, 1, 5, 'followup', 'Follow-up required for MUHAMMAD RAFIQUL ALAM on 2026-06-06', 0, '2026-05-24 06:21:58');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `task_title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `has_attachments` tinyint(1) DEFAULT 0,
  `due_date` date NOT NULL,
  `reminder_days` int(11) DEFAULT 1,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `lead_id`, `task_title`, `description`, `has_attachments`, `due_date`, `reminder_days`, `status`, `assigned_to`, `created_by`, `created_at`) VALUES
(1, 4, 'Meeting', 'sdfsdfsfszf ', 0, '2026-07-01', 2, 'Pending', 2, 2, '2026-05-23 13:34:31');

-- --------------------------------------------------------

--
-- Table structure for table `task_attachments`
--

CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','field_agent') NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `last_login`, `last_ip`, `profile_image`, `phone`, `department`, `join_date`, `created_at`) VALUES
(1, 'admin', '$2y$10$98M1wtzGzzvF.mv3IR.vZeQs0AS/d4BaBf/PibSF8D3TJ.w/8tuD2', 'System Administrator', '', 'admin', 'active', '2026-05-24 10:45:18', '::1', 'uploads/profiles/user_1_1779600845.jpg', '', '', '0000-00-00', '2026-05-23 06:30:11'),
(2, 'fieldagent', '$2y$10$VwEuP/JF8yEocS/pOKSAIuv2MMC1Elg9R6.3RUW4IOBrIMAkSeq1W', 'Field Sales Agent', NULL, 'field_agent', 'active', '2026-05-23 19:39:19', '::1', NULL, NULL, NULL, NULL, '2026-05-23 06:30:11'),
(4, 'Sparsha', '$2y$10$V2CF.4zfWU7L5zQ4gXosTOxZQLwO/wyqJiHiPxPGryurhT3COhkPS', 'Sparsha', 'software20@daffodil-bd.com', 'field_agent', 'active', '2026-05-24 12:22:43', '::1', NULL, '0144', 'Marketing', '2025-10-24', '2026-05-24 05:41:39');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 12:51:11'),
(2, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 12:54:05'),
(3, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 12:54:17'),
(4, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 12:54:42'),
(5, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:20:07'),
(6, 2, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:23:03'),
(7, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:35:18'),
(8, 2, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:37:10'),
(9, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:37:56'),
(10, 2, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:39:19'),
(11, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-23 13:43:55'),
(12, 1, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-24 04:45:18'),
(13, 1, 'User Created', 'Created new user: Sparsha (field_agent)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-24 05:41:39'),
(14, 4, 'Login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', '2026-05-24 06:22:43');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `permission_value` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `role`, `permission_key`, `permission_value`) VALUES
(1, 'admin', 'view_all_leads', 1),
(2, 'admin', 'edit_all_leads', 1),
(3, 'admin', 'delete_leads', 1),
(4, 'admin', 'manage_users', 1),
(5, 'admin', 'view_reports', 1),
(6, 'admin', 'export_data', 1),
(7, 'admin', 'system_settings', 1),
(8, 'field_agent', 'view_all_leads', 0),
(9, 'field_agent', 'edit_all_leads', 0),
(10, 'field_agent', 'delete_leads', 0),
(11, 'field_agent', 'manage_users', 0),
(12, 'field_agent', 'view_reports', 0),
(13, 'field_agent', 'export_data', 0),
(14, 'field_agent', 'system_settings', 0),
(15, 'supervisor', 'view_all_leads', 1),
(16, 'supervisor', 'edit_all_leads', 0),
(17, 'supervisor', 'delete_leads', 0),
(18, 'supervisor', 'manage_users', 0),
(19, 'supervisor', 'view_reports', 1),
(20, 'supervisor', 'export_data', 1),
(21, 'supervisor', 'system_settings', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agent_performance`
--
ALTER TABLE `agent_performance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `communications`
--
ALTER TABLE `communications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `communication_attachments`
--
ALTER TABLE `communication_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `communication_id` (`communication_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_details`
--
ALTER TABLE `employee_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_unique_id` (`lead_unique_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_user_custom_id` (`user_custom_id`),
  ADD KEY `idx_district` (`district`),
  ADD KEY `idx_police_station` (`police_station`);

--
-- Indexes for table `lead_amount_history`
--
ALTER TABLE `lead_amount_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `lead_photos`
--
ALTER TABLE `lead_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `lead_stages`
--
ALTER TABLE `lead_stages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `task_attachments`
--
ALTER TABLE `task_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permission` (`role`,`permission_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agent_performance`
--
ALTER TABLE `agent_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communications`
--
ALTER TABLE `communications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `communication_attachments`
--
ALTER TABLE `communication_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_details`
--
ALTER TABLE `employee_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lead_amount_history`
--
ALTER TABLE `lead_amount_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lead_photos`
--
ALTER TABLE `lead_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lead_stages`
--
ALTER TABLE `lead_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `task_attachments`
--
ALTER TABLE `task_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agent_performance`
--
ALTER TABLE `agent_performance`
  ADD CONSTRAINT `agent_performance_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `communications`
--
ALTER TABLE `communications`
  ADD CONSTRAINT `communications_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `communications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `communication_attachments`
--
ALTER TABLE `communication_attachments`
  ADD CONSTRAINT `communication_attachments_ibfk_1` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `communication_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `employee_details`
--
ALTER TABLE `employee_details`
  ADD CONSTRAINT `employee_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `lead_amount_history`
--
ALTER TABLE `lead_amount_history`
  ADD CONSTRAINT `lead_amount_history_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_amount_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `lead_photos`
--
ALTER TABLE `lead_photos`
  ADD CONSTRAINT `lead_photos_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_attachments`
--
ALTER TABLE `task_attachments`
  ADD CONSTRAINT `task_attachments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 09, 2025 at 11:44 AM
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
-- Database: `massage_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `massage_type_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','no_show') DEFAULT 'pending',
  `total_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `customer_id`, `therapist_id`, `massage_type_id`, `booking_date`, `start_time`, `end_time`, `status`, `total_price`, `notes`, `created_at`, `updated_at`) VALUES
(47, 10, 11, 8, '2025-11-10', '09:00:00', '10:00:00', 'completed', 250.00, '', '2025-11-09 09:59:42', '2025-11-09 10:03:45');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `contact_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `admin_reply_subject` varchar(255) DEFAULT NULL,
  `admin_reply_message` text DEFAULT NULL,
  `email_sent` tinyint(1) DEFAULT 0,
  `replied_by` int(11) DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `is_closed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `massage_types`
--

CREATE TABLE `massage_types` (
  `massage_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `massage_types`
--

INSERT INTO `massage_types` (`massage_type_id`, `name`, `description`, `duration_minutes`, `price`, `image_url`, `is_active`, `created_at`, `updated_at`) VALUES
(8, 'นวดแผนไทย (Thai Massage)', 'เป็นการนวดแบบดั้งเดิมของไทยที่ใช้การกดจุด ยืดเส้น และบิดตัว ไม่ใช้น้ำมัน ช่วยคลายกล้ามเนื้อและเพิ่มความยืดหยุ่น', 60, 250.00, 'service_1762676761_bc4a7d2fcf5ed263.jpg', 1, '2025-11-09 08:15:10', '2025-11-09 02:26:01'),
(9, 'นวดน้ำมัน/นวดอโรมา (Oil/Aromatherapy Massage)', 'ใช้น้ำมันหอมระเหยนวดเบาๆ เพื่อผ่อนคลายและบำรุงผิว', 120, 800.00, 'service_1762677141_73b6d23dbb4abfea.jpg', 1, '2025-11-09 08:16:07', '2025-11-09 02:32:21'),
(10, 'นวดฝ่าเท้า (Foot Massage)', 'เน้นการนวดฝ่าเท้าและน่อง กดจุดสะท้อนเพื่อกระตุ้นอวัยวะต่างๆ', 60, 150.00, 'service_1762677178_6cc70d207f7a0084.jpg', 1, '2025-11-09 08:16:55', '2025-11-09 02:32:58'),
(11, 'นวดไหล่ คอ หลัง (Shoulder/Neck/Back Massage)', 'เน้นบริเวณที่ตึงเครียด', 60, 300.00, 'service_1762677260_bd31f80b7e8309f1.jpg', 1, '2025-11-09 08:17:29', '2025-11-09 02:34:20'),
(12, 'นวดสปอร์ต/กีฬา (Sports Massage)', 'เน้นการฟื้นฟูกล้ามเนื้อหลังออกกำลังกาย นวดแรงกว่าปกติ', 90, 800.00, 'service_1762677289_e6a06826da1fd0bd.jpg', 1, '2025-11-09 08:18:16', '2025-11-09 02:34:49'),
(13, 'นวดหินร้อน (Hot Stone Massage)', 'ใช้หินร้อนวางบนจุดต่างๆ ร่วมกับการนวด ช่วยผ่อนคลายลึก', 120, 1200.00, 'service_1762677309_188a920bc72a967e.jpg', 1, '2025-11-09 08:18:42', '2025-11-09 02:35:09'),
(14, 'นวดสวีดิช (Swedish Massage)', 'นวดแบบตะวันตกที่เน้นการผ่อนคลาย', 90, 800.00, 'service_1762677337_ca5c693b0ebe675a.jpg', 1, '2025-11-09 08:19:12', '2025-11-09 02:35:37'),
(15, 'นวดเพื่อสุขภาพ/บำบัด (Therapeutic Massage)', 'เน้นรักษาอาการปวดเฉพาะจุด', 60, 800.00, '', 0, '2025-11-09 08:19:46', '2025-11-09 02:41:55'),
(16, 'นวดสปาบอล/ลูกประคบ (Herbal Ball Massage)', 'ใช้ลูกประคบสมุนไพรร้อนนวดเพื่อคลายกล้ามเนื้อ', 60, 500.00, 'service_1762677396_ba13a51a73413f3d.jpg', 1, '2025-11-09 08:20:18', '2025-11-09 02:36:36'),
(17, 'นวดศีรษะ (Head Massage)', 'เน้นหนังศีรษะ ขมับ และใบหน้า ช่วยผ่อนคลายและลดความเครียด', 30, 150.00, 'service_1762677421_c60fbc04d4f7bac6.jpg', 1, '2025-11-09 08:20:47', '2025-11-09 02:37:01'),
(18, 'นวดหน้า/เฟเชียล (Facial Massage)', 'นวดใบหน้าเพื่อกระตุ้นการไหลเวียนและดูแลผิว มักรวมในคอร์สทำหน้า', 60, 1000.00, 'service_1762677464_173048b976cb76d2.jpg', 1, '2025-11-09 08:21:11', '2025-11-09 02:37:44'),
(19, 'นวดคนท้อง (Prenatal Massage)', 'นวดสำหรับหญิงตั้งครรภ์ที่ปลอดภัย', 90, 1000.00, 'service_1762677485_b50a5f42640abf9f.jpg', 1, '2025-11-09 08:21:43', '2025-11-09 02:38:05'),
(20, 'นวดลมปราณ/พลังงาน (Energy/Reiki Massage)', 'เน้นการปรับสมดุลพลังงานในร่างกาย', 60, 750.00, '', 0, '2025-11-09 08:22:31', '2025-11-09 02:41:48'),
(21, 'นวดตัวเล็ก (Children Massage)', 'นวดสำหรับเด็กแบบอ่อนโยน', 45, 400.00, 'service_1762677569_d096a23da5ca9ff7.jpg', 1, '2025-11-09 08:22:55', '2025-11-09 02:39:29'),
(22, 'นวดน้ำมันมะพร้าว (Coconut Oil Massage)', 'ใช้น้ำมันมะพร้าวธรรมชาติ', 120, 600.00, 'service_1762677589_cddc7bd68d007d06.jpg', 1, '2025-11-09 08:23:26', '2025-11-09 02:39:49'),
(23, 'นวดดีท็อกซ์/ลดน้ำหนัก (Detox/Slimming Massage)', 'น้นกระตุ้นระบบน้ำเหลืองและเผาผลาญ', 90, 1500.00, 'service_1762677643_428e69c4f5bf0935.jpg', 1, '2025-11-09 08:23:52', '2025-11-09 02:40:43'),
(24, 'นวดไทยดั้งเดิม (Traditional Thai Massage)', 'นวดแบบโบราณที่เข้มข้นกว่านวดไทยทั่วไป', 180, 800.00, '', 0, '2025-11-09 08:24:40', '2025-11-09 02:41:41'),
(25, 'นวดเท้าด้วยไม้ (Thai Foot Massage with Stick)', 'ใช้ไม้กดจุดฝ่าเท้าแบบดั้งเดิม', 60, 400.00, 'service_1762677696_57a6a3f46fb4a3d6.jpg', 1, '2025-11-09 08:25:01', '2025-11-09 02:41:36');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','promptpay','bank_transfer') NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_slip` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `slip_image` varchar(255) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `amount`, `payment_method`, `payment_status`, `payment_slip`, `transaction_id`, `slip_image`, `receipt_file`, `paid_at`, `created_at`) VALUES
(21, 47, 250.00, 'promptpay', 'completed', 'slip_47_1762682395.png', NULL, NULL, 'receipt_1762682591_691066dfeb519.pdf', '2025-11-09 04:01:04', '2025-11-09 09:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `booking_id`, `customer_id`, `therapist_id`, `rating`, `comment`, `created_at`) VALUES
(2, 47, 10, 11, 5, 'สุดยอด', '2025-11-09 10:04:57');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `therapists`
--

CREATE TABLE `therapists` (
  `therapist_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `therapists`
--

INSERT INTO `therapists` (`therapist_id`, `full_name`, `phone`, `specialization`, `image_url`, `is_available`, `created_at`, `updated_at`) VALUES
(11, 'พนักงานทดสอบ พนักงานทดสอบ1', '0999999999', 'นวดแผนไทย (Thai Massage)', 'therapist_1762677860_69105464b51bb.png', 1, '2025-11-09 08:44:20', '2025-11-09 08:44:20'),
(12, 'พนักงานทดสอบ พนักงานทดสอบ2', '8899999996', 'นวดน้ำมัน/นวดอโรมา (Oil/Aromatherapy Massage)', 'therapist_1762677924_691054a4a845a.png', 1, '2025-11-09 08:45:24', '2025-11-09 02:45:37');

-- --------------------------------------------------------

--
-- Table structure for table `therapist_massage_types`
--

CREATE TABLE `therapist_massage_types` (
  `therapist_id` int(11) NOT NULL,
  `massage_type_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `therapist_massage_types`
--

INSERT INTO `therapist_massage_types` (`therapist_id`, `massage_type_id`) VALUES
(11, 8),
(12, 9);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin', 'admin@gmail.com', '00000000', 'admin admin', '0829983666', 'admin', '2025-11-09 08:08:02', '2025-11-09 08:10:21', 1),
(10, 'test1', 'test1@gmail.com', '00000000', 'test1 test1', '0898877660', 'customer', '2025-11-09 09:28:25', '2025-11-09 09:41:41', 1),
(11, 'admin1', 'adminadminadmin@gmail.com', '$2y$10$3kTChkB5P9Pep9.mIBrLBe0KqmGNBWN9U0UB7B6bXc5GTZCNSrbjK', 'adminadmin adminadmin2', '0999878965', 'admin', '2025-11-09 09:30:30', '2025-11-09 09:30:30', 1),
(12, 'test2', 'test2test2@gmail.com', 'test2test2', 'test2 test2', '0987898765', 'customer', '2025-11-09 09:31:05', '2025-11-09 09:31:05', 1);

-- --------------------------------------------------------

--
-- Table structure for table `working_hours`
--

CREATE TABLE `working_hours` (
  `working_hour_id` int(11) NOT NULL,
  `therapist_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `working_hours`
--

INSERT INTO `working_hours` (`working_hour_id`, `therapist_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(91, 11, 'Monday', '09:00:00', '21:00:00'),
(92, 11, 'Wednesday', '09:00:00', '21:00:00'),
(93, 11, 'Friday', '09:00:00', '21:00:00'),
(94, 11, 'Sunday', '09:00:00', '21:00:00'),
(95, 12, 'Tuesday', '09:00:00', '21:00:00'),
(96, 12, 'Thursday', '09:00:00', '21:00:00'),
(97, 12, 'Saturday', '09:00:00', '21:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `massage_type_id` (`massage_type_id`),
  ADD KEY `idx_booking_date` (`booking_date`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_therapist` (`therapist_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `replied_by` (`replied_by`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `massage_types`
--
ALTER TABLE `massage_types`
  ADD PRIMARY KEY (`massage_type_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_booking_review` (`booking_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `therapist_id` (`therapist_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `therapists`
--
ALTER TABLE `therapists`
  ADD PRIMARY KEY (`therapist_id`);

--
-- Indexes for table `therapist_massage_types`
--
ALTER TABLE `therapist_massage_types`
  ADD PRIMARY KEY (`therapist_id`,`massage_type_id`),
  ADD KEY `massage_type_id` (`massage_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `working_hours`
--
ALTER TABLE `working_hours`
  ADD PRIMARY KEY (`working_hour_id`),
  ADD UNIQUE KEY `unique_therapist_day` (`therapist_id`,`day_of_week`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `massage_types`
--
ALTER TABLE `massage_types`
  MODIFY `massage_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `therapists`
--
ALTER TABLE `therapists`
  MODIFY `therapist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `working_hours`
--
ALTER TABLE `working_hours`
  MODIFY `working_hour_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`massage_type_id`) REFERENCES `massage_types` (`massage_type_id`) ON DELETE CASCADE;

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contacts_ibfk_2` FOREIGN KEY (`replied_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;

--
-- Constraints for table `therapist_massage_types`
--
ALTER TABLE `therapist_massage_types`
  ADD CONSTRAINT `therapist_massage_types_ibfk_1` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `therapist_massage_types_ibfk_2` FOREIGN KEY (`massage_type_id`) REFERENCES `massage_types` (`massage_type_id`) ON DELETE CASCADE;

--
-- Constraints for table `working_hours`
--
ALTER TABLE `working_hours`
  ADD CONSTRAINT `working_hours_ibfk_1` FOREIGN KEY (`therapist_id`) REFERENCES `therapists` (`therapist_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

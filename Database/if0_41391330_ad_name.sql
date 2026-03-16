-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql104.infinityfree.com
-- Generation Time: Mar 15, 2026 at 09:31 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41391330_ad_name`
--

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_task_ended`
--

CREATE TABLE `chat_task_ended` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `ended_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_task_ended`
--

INSERT INTO `chat_task_ended` (`id`, `admin_id`, `customer_id`, `ended_at`) VALUES
(2, 1, 2, '2026-03-14 21:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL COMMENT 'รหัสสินค้า',
  `slug` varchar(50) NOT NULL COMMENT 'รหัสอ้างอิง URL',
  `name` varchar(100) NOT NULL COMMENT 'ชื่อสินค้า',
  `category` varchar(100) NOT NULL COMMENT 'หมวดหมู่',
  `price` varchar(50) NOT NULL COMMENT 'ราคา',
  `max_slots` int(11) NOT NULL DEFAULT 0 COMMENT 'รับกี่คน (0 = ไม่จำกัด)',
  `sold_count` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนที่ถือว่าขายแล้ว (รีเซ็ตเป็น 0 ตอนแอดมินกดบันทึกแก้ไข)',
  `image` varchar(255) NOT NULL COMMENT 'รูปภาพ',
  `script_content` text DEFAULT NULL COMMENT 'เนื้อหาสคริปต์หรือลิงก์',
  `description` text DEFAULT NULL COMMENT 'รายละเอียด',
  `features` text DEFAULT NULL COMMENT 'ฟีเจอร์ (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'เวลาอัปเดตล่าสุด'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `slug`, `name`, `category`, `price`, `max_slots`, `sold_count`, `image`, `script_content`, `description`, `features`, `created_at`, `updated_at`) VALUES
(21, 'Gojo รัยหาตัว การันตี', 'Gojo รัยหาตัว การันตี', 'รับหาตัวจนกว่าจะได้', '10฿', 3, 0, 'prod_69b553be07aa73.23758777.png', 'กฟไหกหฟกฟหกฟหกฟห', 'AFK ยืนเฉยๆได้เลย', '[\"dsadsa\"]', '2026-03-14 12:25:34', '2026-03-14 14:44:23');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `points_used` decimal(10,2) NOT NULL DEFAULT 0.00,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `admin_status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `user_id`, `product_id`, `points_used`, `assigned_admin_id`, `admin_status`, `created_at`) VALUES
(1, 2, 21, '10.00', 1, 'accepted', '2026-03-14 12:58:53'),
(2, 2, 21, '10.00', 1, 'accepted', '2026-03-14 14:00:29'),
(3, 1, 21, '10.00', 1, 'pending', '2026-03-14 14:15:38'),
(4, 2, 21, '10.00', 1, 'pending', '2026-03-14 14:18:19'),
(5, 2, 21, '10.00', 1, 'pending', '2026-03-14 14:18:39'),
(6, 2, 21, '10.00', 1, 'pending', '2026-03-14 14:20:14');

-- --------------------------------------------------------

--
-- Table structure for table `ticker`
--

CREATE TABLE `ticker` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticker`
--

INSERT INTO `ticker` (`id`, `content`, `is_active`, `updated_at`) VALUES
(1, '', 0, '2026-03-14 13:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `topups`
--

CREATE TABLE `topups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `slip_image` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL COMMENT 'gbprimepay, qrcode_manual, etc',
  `reference_no` varchar(255) NOT NULL COMMENT 'Reference No / TXN ID',
  `status` enum('pending','approved','rejected','success','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `topups`
--

INSERT INTO `topups` (`id`, `user_id`, `amount`, `slip_image`, `payment_method`, `reference_no`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '10.00', 'slip_69b54281b47aa_1.jpg', '', '', 'approved', '2026-03-14 11:12:01', '2026-03-14 11:16:53'),
(15, 1, '10.00', 'slip_69b5470c49953_1.jpg', '', 'slip_69b5470c49afe_1', 'rejected', '2026-03-14 11:31:24', '2026-03-14 12:58:14'),
(16, 1, '50.00', NULL, '', 'chrg_test_670kj6j1u2w8604httz', 'rejected', '2026-03-14 11:47:55', '2026-03-14 12:58:15'),
(17, 1, '20.00', NULL, '', 'chrg_test_670kjacunm8iz71fpig', 'rejected', '2026-03-14 11:48:13', '2026-03-14 12:58:16'),
(18, 1, '20.00', NULL, '', 'chrg_test_670kmtsro0qhfakygux', 'rejected', '2026-03-14 11:58:17', '2026-03-14 12:58:17'),
(19, 2, '50.00', 'slip_69b55b3a93b5f_2.jpg', '', 'slip_69b55b3a93ee4_2', 'approved', '2026-03-14 12:57:30', '2026-03-14 12:58:06'),
(20, 2, '1000.00', 'slip_69b56f75be97f_2.jpg', '', 'slip_69b56f75beb11_2', 'approved', '2026-03-14 14:23:49', '2026-03-14 14:24:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'รหัสผู้ใช้',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้',
  `email` varchar(100) NOT NULL COMMENT 'อีเมล',
  `avatar` varchar(255) DEFAULT NULL COMMENT 'รูปโปรไฟล์',
  `avatar_position` varchar(30) DEFAULT '50% 50%' COMMENT 'ตำแหน่งรูปในวงกลม',
  `avatar_scale` int(11) DEFAULT 100 COMMENT 'ซูมรูปในวงกลม 80-150',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (เข้ารหัสแล้ว)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สมัคร',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'เข้าสู่ระบบล่าสุด',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'สถานะการใช้งาน (1=ใช้งาน, 0=ระงับ)',
  `availability` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1=ว่าง 2=ไม่ว่าง 3=ไม่อยู่',
  `contact_line` varchar(255) DEFAULT NULL,
  `points` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูลผู้ใช้งาน';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `avatar`, `avatar_position`, `avatar_scale`, `password`, `created_at`, `last_login`, `is_active`, `availability`, `contact_line`, `points`) VALUES
(1, 'EvilMortyKMT', 'BlueWyXXX@gmail.com', 'uploads/avatars/avatar_1_1773488709.gif', '62% 0%', 91, 'GcbI6iiYFkPcAgWTbegoZ2F3K0g2YWpDY1dyaWJQTjlUdVV3cnc9PQ==', '2026-01-30 08:45:22', '2026-03-14 08:12:50', 10, 2, NULL, '0.00'),
(2, 'BlackHoleKMT', 'dsadas@dasdas.com', 'uploads/avatars/avatar_2_1773494274.gif', '57% 0%', 104, 'bFx4AqRnSusovrgMmRKNvzluKzBvL20xTllDK1grdnorZjcwUnc9PQ==', '2026-02-20 19:48:13', '2026-03-14 12:51:28', 1, 1, NULL, '1000.00'),
(3, 'admin', 'admin@godblackhole.com', NULL, '50% 50%', 100, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-03-14 08:58:07', NULL, 1, 1, NULL, '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL COMMENT 'รหัส Session',
  `user_id` int(11) NOT NULL COMMENT 'รหัสผู้ใช้',
  `session_token` varchar(255) NOT NULL COMMENT 'Token Session',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP Address',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User Agent (Browser)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'วันที่หมดอายุ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บข้อมูล Session ผู้ใช้';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_from_to` (`from_user_id`,`to_user_id`),
  ADD KEY `idx_to_from` (`to_user_id`,`from_user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `chat_task_ended`
--
ALTER TABLE `chat_task_ended`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_admin_customer` (`admin_id`,`customer_id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `ticker`
--
ALTER TABLE `ticker`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `topups`
--
ALTER TABLE `topups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_task_ended`
--
ALTER TABLE `chat_task_ended`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสสินค้า', AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ticker`
--
ALTER TABLE `ticker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `topups`
--
ALTER TABLE `topups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสผู้ใช้', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัส Session';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `topups`
--
ALTER TABLE `topups`
  ADD CONSTRAINT `topups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

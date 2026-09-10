-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2026 at 12:44 PM
-- Server version: 10.4.32-MariaDB-log
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `securebank_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `mfa_codes`
--

CREATE TABLE `mfa_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mfa_codes`
--

INSERT INTO `mfa_codes` (`id`, `user_id`, `otp_code`, `expires_at`, `is_used`, `created_at`) VALUES
(3, 3, '240299', '2026-09-07 08:58:30', 1, '2026-09-07 08:56:42'),
(4, 3, '777220', '2026-09-07 08:58:42', 1, '2026-09-07 08:58:30'),
(5, 3, '146775', '2026-09-07 09:01:52', 1, '2026-09-07 08:58:42'),
(6, 3, '817879', '2026-09-07 09:04:03', 1, '2026-09-07 09:01:52'),
(7, 3, '723850', '2026-09-07 09:04:33', 1, '2026-09-07 09:04:03'),
(8, 3, '408161', '2026-09-07 09:06:14', 1, '2026-09-07 09:05:12'),
(10, 3, '424938', '2026-09-07 09:10:23', 1, '2026-09-07 09:09:48'),
(11, 3, '476852', '2026-09-07 09:10:58', 1, '2026-09-07 09:10:23'),
(12, 3, '387526', '2026-09-07 09:23:46', 1, '2026-09-07 09:23:24'),
(13, 4, '827855', '2026-09-09 11:20:58', 1, '2026-09-09 11:20:45'),
(14, 4, '248112', '2026-09-09 11:22:43', 1, '2026-09-09 11:22:21'),
(15, 4, '566542', '2026-09-09 11:48:50', 1, '2026-09-09 11:48:29'),
(16, 4, '992496', '2026-09-09 12:19:02', 1, '2026-09-09 12:17:30'),
(17, 4, '721566', '2026-09-09 12:26:50', 1, '2026-09-09 12:21:43'),
(18, 4, '135564', '2026-09-09 12:27:11', 1, '2026-09-09 12:26:50');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `csrf_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdrawal','transfer') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `transaction_type`, `amount`, `description`, `created_at`) VALUES
(2, 3, 'deposit', 100.00, 'Transfer from ACC-5372860', '2026-09-07 09:09:28'),
(3, 4, 'transfer', 100.00, 'alert(&#039;XSS&#039;)', '2026-09-09 11:50:52'),
(4, 6, 'deposit', 100.00, 'Transfer from ACC-5385204', '2026-09-09 11:50:52'),
(5, 4, 'transfer', 1000.00, 'Tuition', '2026-09-09 12:31:54'),
(6, 3, 'deposit', 1000.00, 'Transfer from ACC-5385204', '2026-09-09 12:31:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_mfa_enabled` tinyint(1) DEFAULT 0,
  `mfa_method` enum('sms','email') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone_number`, `password_hash`, `account_number`, `account_balance`, `created_at`, `is_mfa_enabled`, `mfa_method`) VALUES
(3, 'Sean', 'martinjay1872@gmail.com', '09774246627', '$2y$12$/w0lnYQz3f35lJJcCvmmfOi65Oeq1Y9wX6ZKFOZwYI5nSIHZ4UjPu', 'ACC-5703730', 1100.00, '2026-09-07 08:53:07', 0, 'email'),
(4, 'Martin', '07304438@dwc-legazpi.edu', '+639453217521', '$2y$12$dkqDTCx4bAuHRqYERHj9cO1G0KzvLrGdAddhiZv0y5bnCpMtLd2rm', 'ACC-5385204', 98900.00, '2026-09-09 11:19:43', 1, 'email'),
(6, 'alert(1)', 'martinjay@gmail.com', '09453217522', '$2y$12$V2LKRvCwrCiJU43imQZNIO3Q4eO6okmolzK2iGanJUB5nn0/g4w0a', 'ACC-7835967', 100.00, '2026-09-09 11:46:04', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mfa_codes`
--
ALTER TABLE `mfa_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mfa_codes`
--
ALTER TABLE `mfa_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `mfa_codes`
--
ALTER TABLE `mfa_codes`
  ADD CONSTRAINT `mfa_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 08, 2025 at 02:41 AM
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
-- Database: `bikonomi`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_tbl`
--

CREATE TABLE `account_tbl` (
  `account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_tbl`
--

INSERT INTO `account_tbl` (`account_id`, `username`, `password`, `email`, `created_at`) VALUES
(1, 'Test', '$2y$10$n4ObgUZbTwLE3oeJN0aQxuOh4jTi9bdZx5R29FKDklYKSE9vr1H46', 'Test@gmail.com', '2025-04-01 12:22:19');

-- --------------------------------------------------------

--
-- Table structure for table `bike_tbl`
--

CREATE TABLE `bike_tbl` (
  `bike_id` int(11) NOT NULL,
  `bike_type_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `bike_name` varchar(100) NOT NULL,
  `bike_color` varchar(100) NOT NULL,
  `bike_accessories` varchar(100) NOT NULL,
  `bike_brand` varchar(100) NOT NULL,
  `bike_serial_gps` varchar(100) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bike_tbl`
--

INSERT INTO `bike_tbl` (`bike_id`, `bike_type_id`, `account_id`, `bike_name`, `bike_color`, `bike_accessories`, `bike_brand`, `bike_serial_gps`, `image_path`) VALUES
(1, 1, 1, 'Bike ko', 'Green', 'None', 'Sample Brand', '98723413', 'images/67eb6a4b4f2a7.jpeg'),
(6, 5, 1, 'Bike 4824', 'Green', 'None', 'Sample', '89726383', 'images/67f1c145762ff.jpeg'),
(7, 6, 1, 'Bike 9999', 'Green', 'None', 'Brand-X', '3601737137', 'images/67f1c29bef68a.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `bike_type_tbl`
--

CREATE TABLE `bike_type_tbl` (
  `bike_type_id` int(11) NOT NULL,
  `bike_type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bike_type_tbl`
--

INSERT INTO `bike_type_tbl` (`bike_type_id`, `bike_type_name`) VALUES
(1, 'Mountain Bike'),
(5, 'Sports Bike'),
(6, 'Sample');

-- --------------------------------------------------------

--
-- Table structure for table `payment_tbl`
--

CREATE TABLE `payment_tbl` (
  `payment_id` int(11) NOT NULL,
  `rent_id` int(11) NOT NULL,
  `amount_paid` double NOT NULL,
  `date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_tbl`
--

INSERT INTO `payment_tbl` (`payment_id`, `rent_id`, `amount_paid`, `date`) VALUES
(12, 8, 461.85, '2025-04-06 07:55:01');

-- --------------------------------------------------------

--
-- Table structure for table `rate_tbl`
--

CREATE TABLE `rate_tbl` (
  `rate_id` int(11) NOT NULL,
  `bike_id` int(11) NOT NULL,
  `rate_per_minute` double NOT NULL,
  `date_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_tbl`
--

INSERT INTO `rate_tbl` (`rate_id`, `bike_id`, `rate_per_minute`, `date_time`) VALUES
(1, 1, 5, '2025-04-01 06:23:39'),
(5, 6, 3, '2025-04-06 01:48:21'),
(6, 7, 4, '2025-04-06 01:54:03');

-- --------------------------------------------------------

--
-- Table structure for table `rental_tbl`
--

CREATE TABLE `rental_tbl` (
  `rent_id` int(11) NOT NULL,
  `bike_id` int(11) NOT NULL,
  `rate_id` int(11) NOT NULL,
  `time_limit` time NOT NULL,
  `start_time` datetime NOT NULL,
  `expected_end_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_tbl`
--

INSERT INTO `rental_tbl` (`rent_id`, `bike_id`, `rate_id`, `time_limit`, `start_time`, `expected_end_time`, `end_time`) VALUES
(8, 6, 5, '00:20:00', '2025-04-06 05:00:45', '2025-04-06 05:20:45', '2025-04-06 07:55:01'),
(9, 7, 6, '00:05:00', '2025-04-06 07:54:25', '2025-04-06 07:59:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_tbl`
--

CREATE TABLE `user_tbl` (
  `account_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(5) NOT NULL,
  `contact` varchar(13) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_tbl`
--
ALTER TABLE `account_tbl`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `bike_tbl`
--
ALTER TABLE `bike_tbl`
  ADD PRIMARY KEY (`bike_id`),
  ADD KEY `bike_type_id` (`bike_type_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `bike_type_tbl`
--
ALTER TABLE `bike_type_tbl`
  ADD PRIMARY KEY (`bike_type_id`);

--
-- Indexes for table `payment_tbl`
--
ALTER TABLE `payment_tbl`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `rent_id` (`rent_id`);

--
-- Indexes for table `rate_tbl`
--
ALTER TABLE `rate_tbl`
  ADD PRIMARY KEY (`rate_id`),
  ADD UNIQUE KEY `bike_id` (`bike_id`);

--
-- Indexes for table `rental_tbl`
--
ALTER TABLE `rental_tbl`
  ADD PRIMARY KEY (`rent_id`),
  ADD KEY `bike_id` (`bike_id`),
  ADD KEY `rate_id` (`rate_id`);

--
-- Indexes for table `user_tbl`
--
ALTER TABLE `user_tbl`
  ADD UNIQUE KEY `account_id` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_tbl`
--
ALTER TABLE `account_tbl`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bike_tbl`
--
ALTER TABLE `bike_tbl`
  MODIFY `bike_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bike_type_tbl`
--
ALTER TABLE `bike_type_tbl`
  MODIFY `bike_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_tbl`
--
ALTER TABLE `payment_tbl`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `rate_tbl`
--
ALTER TABLE `rate_tbl`
  MODIFY `rate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rental_tbl`
--
ALTER TABLE `rental_tbl`
  MODIFY `rent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bike_tbl`
--
ALTER TABLE `bike_tbl`
  ADD CONSTRAINT `bike_tbl_ibfk_1` FOREIGN KEY (`bike_type_id`) REFERENCES `bike_type_tbl` (`bike_type_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_tbl`
--
ALTER TABLE `payment_tbl`
  ADD CONSTRAINT `payment_tbl_ibfk_1` FOREIGN KEY (`rent_id`) REFERENCES `rental_tbl` (`rent_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rate_tbl`
--
ALTER TABLE `rate_tbl`
  ADD CONSTRAINT `rate_tbl_ibfk_1` FOREIGN KEY (`bike_id`) REFERENCES `bike_tbl` (`bike_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rental_tbl`
--
ALTER TABLE `rental_tbl`
  ADD CONSTRAINT `rental_tbl_ibfk_2` FOREIGN KEY (`rate_id`) REFERENCES `rate_tbl` (`rate_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `rental_tbl_ibfk_3` FOREIGN KEY (`bike_id`) REFERENCES `rate_tbl` (`bike_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_tbl`
--
ALTER TABLE `user_tbl`
  ADD CONSTRAINT `user_tbl_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `account_tbl` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

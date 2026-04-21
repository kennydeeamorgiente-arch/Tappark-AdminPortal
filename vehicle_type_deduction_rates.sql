-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 06:58 PM
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
-- Database: `merge2`
--

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_type_deduction_rates`
--

CREATE TABLE `vehicle_type_deduction_rates` (
  `rate_id` bigint(20) UNSIGNED NOT NULL,
  `vehicle_type_id` bigint(20) UNSIGNED NOT NULL,
  `deduction_rate` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_type_deduction_rates`
--

INSERT INTO `vehicle_type_deduction_rates` (`rate_id`, `vehicle_type_id`, `deduction_rate`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 3.00, 1, '2026-02-24 17:30:18', '2026-02-24 17:30:18'),
(2, 1, 5.00, 1, '2026-02-24 17:30:18', '2026-02-24 17:30:18'),
(3, 3, 3.00, 1, '2026-02-24 17:30:18', '2026-02-24 17:30:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `vehicle_type_deduction_rates`
--
ALTER TABLE `vehicle_type_deduction_rates`
  ADD PRIMARY KEY (`rate_id`),
  ADD UNIQUE KEY `uq_vehicle_type_rate` (`vehicle_type_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `vehicle_type_deduction_rates`
--
ALTER TABLE `vehicle_type_deduction_rates`
  MODIFY `rate_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `vehicle_type_deduction_rates`
--
ALTER TABLE `vehicle_type_deduction_rates`
  ADD CONSTRAINT `fk_vehicle_type_rate_vehicle_type` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`vehicle_type_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 20, 2026 at 06:26 AM
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
-- Database: `smart_agri`
--

-- --------------------------------------------------------

--
-- Table structure for table `farmlocation`
--

CREATE TABLE `farmlocation` (
  `locationID` int(15) NOT NULL,
  `farmName` varchar(30) NOT NULL,
  `dateAdded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plantinfo`
--

CREATE TABLE `plantinfo` (
  `plantID` int(11) NOT NULL,
  `plantName` varchar(30) DEFAULT NULL,
  `plantVariety` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plantinfo`
--

INSERT INTO `plantinfo` (`plantID`, `plantName`, `plantVariety`) VALUES
(1, 'Garlic', 'Beefsteak');

-- --------------------------------------------------------

--
-- Table structure for table `plantnutrionneed`
--

CREATE TABLE `plantnutrionneed` (
  `nutritionID` int(11) NOT NULL,
  `nutritionSetName` varchar(30) DEFAULT NULL,
  `plantID` int(11) DEFAULT NULL,
  `soilN` int(10) DEFAULT NULL,
  `soilP` int(10) DEFAULT NULL,
  `soilK` int(10) DEFAULT NULL,
  `soilEC` int(10) DEFAULT NULL,
  `soilPH` float DEFAULT NULL,
  `soilT` float DEFAULT NULL,
  `soilM` float DEFAULT NULL,
  `flowRate` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sensordata`
--

CREATE TABLE `sensordata` (
  `SensorDataID` int(15) NOT NULL,
  `SoilSensorID` int(10) DEFAULT NULL,
  `locationID` int(15) NOT NULL,
  `SoilN` int(10) DEFAULT NULL,
  `SoilP` int(10) DEFAULT NULL,
  `SoilK` int(10) DEFAULT NULL,
  `SoilEC` int(10) DEFAULT NULL,
  `SoilPH` float DEFAULT NULL,
  `SoilT` float DEFAULT NULL,
  `SoilMois` float DEFAULT NULL,
  `liquidVolume` float DEFAULT NULL,
  `DateTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sensorinfo`
--

CREATE TABLE `sensorinfo` (
  `soilSensorID` int(15) NOT NULL,
  `sensorName` varchar(50) DEFAULT NULL,
  `dateAdded` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `password_hash`, `email`, `created_at`, `last_login`) VALUES
(1, 'Clarns', '$2y$10$/2.VfK48o0ozkGCSU8xIzeqlbaXvsWUViDe8o.LiN9eV/erNti3Y.', 'clarencejason.legaspi@students.isatu.edu.ph', '2026-01-19 08:06:21', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `farmlocation`
--
ALTER TABLE `farmlocation`
  ADD PRIMARY KEY (`locationID`);

--
-- Indexes for table `plantinfo`
--
ALTER TABLE `plantinfo`
  ADD PRIMARY KEY (`plantID`);

--
-- Indexes for table `plantnutrionneed`
--
ALTER TABLE `plantnutrionneed`
  ADD PRIMARY KEY (`nutritionID`),
  ADD KEY `plantID` (`plantID`);

--
-- Indexes for table `sensordata`
--
ALTER TABLE `sensordata`
  ADD PRIMARY KEY (`SensorDataID`),
  ADD KEY `SoilSensorID` (`SoilSensorID`),
  ADD KEY `sensordata_ibfk_2` (`locationID`);

--
-- Indexes for table `sensorinfo`
--
ALTER TABLE `sensorinfo`
  ADD PRIMARY KEY (`soilSensorID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `plantinfo`
--
ALTER TABLE `plantinfo`
  MODIFY `plantID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `plantnutrionneed`
--
ALTER TABLE `plantnutrionneed`
  MODIFY `nutritionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sensordata`
--
ALTER TABLE `sensordata`
  MODIFY `SensorDataID` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sensorinfo`
--
ALTER TABLE `sensorinfo`
  MODIFY `soilSensorID` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `plantnutrionneed`
--
ALTER TABLE `plantnutrionneed`
  ADD CONSTRAINT `plantnutrionneed_ibfk_1` FOREIGN KEY (`plantID`) REFERENCES `plantinfo` (`plantID`);

--
-- Constraints for table `sensordata`
--
ALTER TABLE `sensordata`
  ADD CONSTRAINT `sensordata_ibfk_1` FOREIGN KEY (`SoilSensorID`) REFERENCES `sensorinfo` (`soilSensorID`),
  ADD CONSTRAINT `sensordata_ibfk_2` FOREIGN KEY (`locationID`) REFERENCES `farmlocation` (`locationID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

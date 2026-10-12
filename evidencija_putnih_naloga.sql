-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 18, 2026 at 01:21 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `evidencija_putnih_naloga`
--

-- --------------------------------------------------------

--
-- Table structure for table `korisnici`
--

CREATE TABLE `korisnici` (
  `id` int(11) NOT NULL,
  `ime` varchar(50) NOT NULL,
  `prezime` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `lozinka` varchar(255) NOT NULL,
  `uloga_id` int(11) NOT NULL,
  `nadredeni_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `korisnici`
--

INSERT INTO `korisnici` (`id`, `ime`, `prezime`, `email`, `lozinka`, `uloga_id`, `nadredeni_id`) VALUES
(1, 'Mateo', 'Didović', 'admin@test.com', '$2y$10$123456789012345678901uqIAeJ5d.P7peh2bfdfvZMdSZrdaCGfi', 1, NULL),
(2, 'Karlo', 'Perić', 'voditelj@test.com', '$2y$10$123456789012345678901uqIAeJ5d.P7peh2bfdfvZMdSZrdaCGfi', 2, NULL),
(4, 'Mihael', 'Horvat', 'mihael@test.com', '$2y$10$ctPsUEadMGxrait.3xBqm.mOHaUVIAM4u0tE8KliJsK4YR0AgOdeq', 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `putni_nalozi`
--

CREATE TABLE `putni_nalozi` (
  `id` int(11) NOT NULL,
  `zaposlenik_id` int(11) NOT NULL,
  `polaziste` varchar(255) NOT NULL DEFAULT '',
  `odrediste` varchar(150) NOT NULL,
  `svrha` text NOT NULL,
  `vrsta_prijevoza` varchar(50) DEFAULT 'Privatno vozilo',
  `datum_polaska` date NOT NULL,
  `vrijeme_polaska` time NOT NULL DEFAULT '08:00:00',
  `datum_povratka` date NOT NULL,
  `vrijeme_povratka` time NOT NULL DEFAULT '16:00:00',
  `pocetna_km` int(11) DEFAULT 0,
  `zavrsna_km` int(11) DEFAULT 0,
  `osiguran_dorucak` tinyint(1) DEFAULT 0,
  `osiguran_rucak` tinyint(1) DEFAULT 0,
  `osiguran_veceri` tinyint(1) DEFAULT 0,
  `akontacija` decimal(10,2) DEFAULT 0.00,
  `putno_izvjesce` text DEFAULT NULL,
  `status` enum('Na čekanju','Odobreno','Odbijeno') DEFAULT 'Na čekanju',
  `odobrio_id` int(11) DEFAULT NULL,
  `datum_kreiranja` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `putni_nalozi`
--

INSERT INTO `putni_nalozi` (`id`, `zaposlenik_id`, `polaziste`, `odrediste`, `svrha`, `vrsta_prijevoza`, `datum_polaska`, `vrijeme_polaska`, `datum_povratka`, `vrijeme_povratka`, `pocetna_km`, `zavrsna_km`, `osiguran_dorucak`, `osiguran_rucak`, `osiguran_veceri`, `akontacija`, `putno_izvjesce`, `status`, `odobrio_id`, `datum_kreiranja`) VALUES
(2, 1, '', 'Kapela', 'Sastanak', 'Službeno vozilo', '2026-09-17', '08:00:00', '2026-09-18', '17:00:00', 197300, 197330, 1, 0, 0, 0.00, 'Bilo je lijepo', 'Odobreno', 1, '2026-09-17 14:35:21'),
(4, 2, 'Batrina', 'Zagreb', 'Put', 'Privatno vozilo', '2026-09-16', '08:00:00', '2026-09-25', '17:00:00', 12300, 13300, 0, 0, 0, 0.00, NULL, 'Odobreno', 1, '2026-09-17 16:03:32');

-- --------------------------------------------------------

--
-- Table structure for table `troskovi_naloga`
--

CREATE TABLE `troskovi_naloga` (
  `id` int(11) NOT NULL,
  `nalog_id` int(11) NOT NULL,
  `vrsta_troska` varchar(100) NOT NULL,
  `iznos` decimal(10,2) NOT NULL,
  `putanja_racuna` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uloge`
--

CREATE TABLE `uloge` (
  `id` int(11) NOT NULL,
  `naziv` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uloge`
--

INSERT INTO `uloge` (`id`, `naziv`) VALUES
(1, 'Admin'),
(2, 'Voditelj'),
(3, 'Zaposlenik');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `korisnici`
--
ALTER TABLE `korisnici`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `uloga_id` (`uloga_id`),
  ADD KEY `nadredeni_id` (`nadredeni_id`);

--
-- Indexes for table `putni_nalozi`
--
ALTER TABLE `putni_nalozi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zaposlenik_id` (`zaposlenik_id`),
  ADD KEY `odobrio_id` (`odobrio_id`);

--
-- Indexes for table `troskovi_naloga`
--
ALTER TABLE `troskovi_naloga`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nalog_id` (`nalog_id`);

--
-- Indexes for table `uloge`
--
ALTER TABLE `uloge`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `korisnici`
--
ALTER TABLE `korisnici`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `putni_nalozi`
--
ALTER TABLE `putni_nalozi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `troskovi_naloga`
--
ALTER TABLE `troskovi_naloga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uloge`
--
ALTER TABLE `uloge`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `korisnici`
--
ALTER TABLE `korisnici`
  ADD CONSTRAINT `korisnici_ibfk_1` FOREIGN KEY (`uloga_id`) REFERENCES `uloge` (`id`),
  ADD CONSTRAINT `korisnici_ibfk_2` FOREIGN KEY (`nadredeni_id`) REFERENCES `korisnici` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `putni_nalozi`
--
ALTER TABLE `putni_nalozi`
  ADD CONSTRAINT `putni_nalozi_ibfk_1` FOREIGN KEY (`zaposlenik_id`) REFERENCES `korisnici` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `putni_nalozi_ibfk_2` FOREIGN KEY (`odobrio_id`) REFERENCES `korisnici` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `troskovi_naloga`
--
ALTER TABLE `troskovi_naloga`
  ADD CONSTRAINT `troskovi_naloga_ibfk_1` FOREIGN KEY (`nalog_id`) REFERENCES `putni_nalozi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Nov 2026 pada 08.45
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `slearning_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `login`
--

-- ===== FIX: impor tabel `login` yang aman terhadap foreign key =====
-- Tabel anggota_kelas.user_id dan pengumpulan_tugas.user_id mereferensikan login.id,
-- sehingga tanpa ini muncul: ERROR 1451 Cannot delete or update a parent row.
-- Pemeriksaan FK dimatikan sementara, tabel lama dibuang, lalu dibuat ulang.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `login`;

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `jenis` enum('waka','guru','murid','admin') NOT NULL,
  `level` enum('super admin','admin','user') NOT NULL DEFAULT 'user',
  `foto` varchar(255) DEFAULT NULL,
  `nis` varchar(30) DEFAULT NULL,
  `status_persetujuan` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `jurusan` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `nis` (`nis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `login`
--

INSERT INTO `login` (`id`, `nama`, `username`, `email`, `foto_profil`, `password`, `jenis`, `level`, `foto`, `nis`, `status_persetujuan`, `jurusan`) VALUES
(1, 'DION RAFAEL', 'WIJAYA', 'yudi@gmail.com', NULL, '$2y$10$D8IUHq28aqbGx6r806n4uu4OJGP9IDtRWoa9FKng0rirs6WDRvIOS', 'murid', 'user', NULL, NULL, 'approved', NULL),
(2, 'Wijayaa', 'Wijayaa', 'dionrafel311@gmail.com', NULL, '$2y$10$wX0.XHfruAW66aHmgmoJ5uJZDO4aQSCCZI4iCjyHauGROAux5bwSu', 'guru', 'admin', NULL, NULL, 'approved', NULL),
(3, 'Ahmad', 'Ahmad', 'Ahmad@gmail.com', 'profil_3_1792381102.jpg', '$2y$10$wX0.XHfruAW66aHmgmoJ5uJZDO4aQSCCZI4iCjyHauGROAux5bwSu', 'guru', 'admin', NULL, NULL, 'approved', NULL),
(4, 'Faruzi', 'Faruzi', 'dadada@gmail.com', NULL, '$2y$10$7t4RtOAoc.EwrSric/M88OVWW4DnRjcEiAfeZndH7FAdoNhn3NZKe', 'murid', 'user', NULL, NULL, 'approved', NULL),
(5, 'dionn', 'dion rafel wijaya', 'marshallkenzo1@gmail.com', NULL, '$2y$10$likYNRxunJvcS6vHR8jNju3GUpUhqEWP3c9ALfXm3PDMLa1yi81zO', 'murid', 'user', NULL, NULL, 'approved', NULL),
(8, 'jawaa', 'murid_224455662', NULL, NULL, '$2y$10$G8Dq/DFp2RuuFKaWX9s4MOYJ/ZOJAsxVUrYNcOWc7O6u8SvQ1ONIm', 'murid', 'user', NULL, '224455662', 'approved', NULL),
(9, 'MARSHALL', 'murid_1234567898', NULL, NULL, '$2y$10$M5A2xlt5fLBS9t1smLCW8OGpD5ieZzccS9ZkCNFGEElgAndsXo7Di', 'murid', 'user', NULL, '1234567898', 'approved', NULL),
(10, 'peler', 'murid_12313123213131113113', NULL, NULL, '$2y$10$SVi0fxyqCxy2vglK8xFCh.WtE.JSpChYE6nS2sjoDj4s1mooRGyWe', 'murid', 'user', NULL, '12313123213131113113', 'approved', 'Rekayasa Perangkat Lunak (RPL)'),
(13, 'Admin', 'Marshall', NULL, NULL, '$2y$10$wX0.XHfruAW66aHmgmoJ5uJZDO4aQSCCZI4iCjyHauGROAux5bwSu', 'waka', 'super admin', NULL, NULL, 'approved', NULL),
(14, 'Super Admin', 'admin', NULL, NULL, '$2y$10$oSvBpizD7YVE6xB2xrvDIeAlf/I4a/bcLiqCCSoDpVbMeqkYuWXqy', 'admin', 'super admin', NULL, NULL, 'approved', NULL),
(15, 'Safira', 'murid_2222222222', NULL, NULL, '$2y$10$COi5GapjrPDMOb9JWknnweNq82uMtEDAAe58MwXPGUdw/wQ0LhG3a', 'murid', 'user', NULL, '2222222222', 'approved', 'Rekayasa Perangkat Lunak (RPL)');

--
-- Indexes for dumped tables
--

-- ===== FIX: index (PRIMARY/UNIQUE) sekarang didefinisikan langsung di dalam
-- CREATE TABLE di atas, supaya foreign key dari anggota_kelas & pengumpulan_tugas
-- tetap valid saat tabel dibuat ulang (mencegah errno 150). Jadi blok
-- "ALTER TABLE login ADD PRIMARY KEY ..." yang lama dihapus.
--

--
-- AUTO_INCREMENT untuk tabel `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

-- ===== FIX: nyalakan kembali pemeriksaan foreign key =====
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 31 Mar 2026 pada 15.45
-- Versi server: 10.4.11-MariaDB
-- Versi PHP: 7.4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_kas`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `murid`
--

CREATE TABLE `murid` (
  `nisn` varchar(10) NOT NULL,
  `password` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(20) NOT NULL,
  `status` enum('Aktif','Non-Aktif') NOT NULL,
  `role` enum('Siswa','Bendahara','Ketua Kelas') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `murid`
--

INSERT INTO `murid` (`nisn`, `password`, `nama`, `kelas`, `status`, `role`) VALUES
('1000000001', '1000000001', 'Hayatul Fattah Kurniawan', 'XI PPLG 2', 'Aktif', 'Bendahara'),
('1000000002', '1000000002', 'Firman Dirga Rafael', 'XI PPLG 2', 'Aktif', 'Ketua Kelas'),
('1000000003', '1000000003', 'Abid Faeyza', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000004', '1000000004', 'Achmad Riziq Al-Azzim', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000005', '1000000005', 'Achmad Sultan', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000006', '1000000006', 'Afifah Husna Al Insyirah', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000007', '1000000007', 'Akbari Pasha', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000008', '1000000008', 'Akhmad Zailani', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000009', '1000000009', 'Akhmad Zaky Mahdy Barjah', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000010', '1000000010', 'Alana Aftaraya', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000011', '1000000011', 'Aldo Aditya', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000012', '1000000012', 'Alif Setiawan', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000013', '1000000013', 'Andhien Rexieta Ektya Antara', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000014', '1000000014', 'Atika Farah Zakia', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000015', '1000000015', 'Aura Amalia', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000016', '1000000016', 'Dava Valentino Lareant', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000017', '1000000017', 'Elang Bara', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000018', '1000000018', 'Fahri Irfan Maulana', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000019', '1000000019', 'Febry Setiawan', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000020', '1000000020', 'Hanif Nurfajri Putra Permana', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000021', '1000000021', 'Krisna Andika Pratama', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000022', '1000000022', 'Ipuy Ngau', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000023', '1000000023', 'Muhammad Askhar', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000024', '1000000024', 'Muhammad Fauzan', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000025', '1000000025', 'Muhammad Luthfi Syauqi', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000026', '1000000026', 'Muhammad Miftahul Muhtadin', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000027', '1000000027', 'Muhammad Nazriel Musyaffa', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000028', '1000000028', 'Nazar Maulid Putra Asriwan', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000029', '1000000029', 'Putra Aqso Dinata', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000030', '1000000030', 'Rakha Fernanda', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000031', '1000000031', 'Wira Ksatria Rahman', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000032', '1000000032', 'Zahran Mirza Al Fattah', 'XI PPLG 2', 'Aktif', 'Siswa'),
('1000000033', '1000000033', 'Zulfi Guruh Saputra', 'XI PPLG 2', 'Aktif', 'Siswa');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `nisn` varchar(10) NOT NULL,
  `tanggal` datetime NOT NULL,
  `tahun` int(11) DEFAULT NULL,
  `bulan` varchar(20) DEFAULT NULL,
  `minggu` int(11) DEFAULT NULL,
  `jenis` enum('Masuk','Keluar') NOT NULL,
  `jumlah` int(8) NOT NULL,
  `keterangan` varchar(150) NOT NULL,
  `dokumentasi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struktur dari tabel `walikelas`
--

CREATE TABLE `walikelas` (
  `nip` varchar(18) NOT NULL,
  `password` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `status` enum('Aktif','Non-Aktif') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `walikelas`
--

INSERT INTO `walikelas` (`nip`, `password`, `nama`, `status`) VALUES
('123456789123456789', '123456789123456789', 'Risti Oktaviani', 'Aktif');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `murid`
--
ALTER TABLE `murid`
  ADD PRIMARY KEY (`nisn`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `nisn` (`nisn`);

--
-- Indeks untuk tabel `walikelas`
--
ALTER TABLE `walikelas`
  ADD PRIMARY KEY (`nip`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`nisn`) REFERENCES `murid` (`nisn`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

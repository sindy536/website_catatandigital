-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 04 Jan 2026 pada 02.46
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
-- Database: `db_catatan_digital`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id_activity` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `aksi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `tanggal_aksi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `activity_logs`
--

INSERT INTO `activity_logs` (`id_activity`, `id_user`, `aksi`, `deskripsi`, `ip_address`, `tanggal_aksi`) VALUES
(1, 1, 'Login', 'User berhasil login', '192.168.1.100', '2025-11-30 08:25:40'),
(2, 1, 'Create Note', 'User membuat catatan baru: Rapat Meeting Hari Ini', '192.168.1.100', '2025-11-30 08:25:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id_kategori` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `warna` varchar(7) DEFAULT '#007AFF',
  `deskripsi` text DEFAULT NULL,
  `tanggal_buat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id_kategori`, `id_user`, `nama_kategori`, `warna`, `deskripsi`, `tanggal_buat`) VALUES
(1, 1, 'Pekerjaan', '#007AFF', 'Catatan yang berkaitan dengan pekerjaan', '2025-11-30 08:25:39'),
(2, 1, 'Pribadi', '#FF9500', 'Catatan pribadi dan ide-ide personal', '2025-11-30 08:25:39'),
(3, 1, 'Pelajaran', '#34c759', 'Catatan untuk keperluan belajar', '2025-11-30 08:25:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notes`
--

CREATE TABLE `notes` (
  `id_catatan` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `konten` longtext NOT NULL,
  `tanggal_buat` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_ubah` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('aktif','arsip') DEFAULT 'aktif',
  `is_penting` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notes`
--

INSERT INTO `notes` (`id_catatan`, `id_user`, `id_kategori`, `judul`, `konten`, `tanggal_buat`, `tanggal_ubah`, `status`, `is_penting`) VALUES
(18, 1, 3, 'hati yang tersakiti', 'adalah pokonyaa', '2026-01-01 14:36:11', '2026-01-02 02:46:33', 'arsip', 0),
(20, 1, 3, 'tugas', 'di kumpul hhari senin', '2026-01-02 02:53:00', '2026-01-02 02:53:00', 'aktif', 0),
(21, 1, 1, 'dsfgh', 'fdhtdsldjjjjjjjjjjdsssssssssssseeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', '2026-01-02 06:56:23', '2026-01-02 06:56:23', 'aktif', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `note_tags`
--

CREATE TABLE `note_tags` (
  `id_note_tag` int(11) NOT NULL,
  `id_catatan` int(11) NOT NULL,
  `id_tag` int(11) NOT NULL,
  `tanggal_tambah` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tags`
--

CREATE TABLE `tags` (
  `id_tag` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nama_tag` varchar(50) NOT NULL,
  `tanggal_buat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tags`
--

INSERT INTO `tags` (`id_tag`, `id_user`, `nama_tag`, `tanggal_buat`) VALUES
(1, 1, 'penting', '2025-11-30 08:25:39'),
(2, 1, 'urgent', '2025-11-30 08:25:39'),
(3, 1, 'project', '2025-11-30 08:25:39'),
(4, 1, 'review', '2025-11-30 08:25:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nomor_telepon` varchar(15) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `tanggal_bergabung` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('aktif','nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_user`, `nama_lengkap`, `email`, `password`, `nomor_telepon`, `foto_profil`, `tanggal_bergabung`, `status`) VALUES
(1, 'SINDI', 'sindi@email.com', '$2y$10$dyCOh56qcSAyc6fr63vgWebYwBojgDfW5k8BrVQng9VsxI8jazFXy', '+62 812-3456-78', '695697145aa70_sindi@email.com.jpg', '2024-01-15 02:00:00', 'aktif'),
(4, 'cindy', 'juli123@gmail.com', '$2y$10$iJ3XqMCGzn6JAb14q0/WOOXpFnIYMgKrPTFqcsoAQafmaJaAZ5HgK', '+629876234', '695695bd342d4_juli123@gmail.com.jpg', '2026-01-01 15:19:49', 'aktif');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id_activity`),
  ADD KEY `idx_activity_user` (`id_user`);

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_kategori`),
  ADD UNIQUE KEY `unique_kategori_user` (`id_user`,`nama_kategori`),
  ADD KEY `idx_categories_user` (`id_user`);

--
-- Indeks untuk tabel `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id_catatan`),
  ADD KEY `idx_notes_user` (`id_user`),
  ADD KEY `idx_notes_kategori` (`id_kategori`),
  ADD KEY `idx_notes_status` (`status`),
  ADD KEY `idx_notes_tanggal` (`tanggal_buat`);

--
-- Indeks untuk tabel `note_tags`
--
ALTER TABLE `note_tags`
  ADD PRIMARY KEY (`id_note_tag`),
  ADD UNIQUE KEY `unique_note_tag` (`id_catatan`,`id_tag`),
  ADD KEY `id_tag` (`id_tag`);

--
-- Indeks untuk tabel `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id_tag`),
  ADD UNIQUE KEY `unique_tag_user` (`id_user`,`nama_tag`),
  ADD KEY `idx_tags_user` (`id_user`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id_activity` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `notes`
--
ALTER TABLE `notes`
  MODIFY `id_catatan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `note_tags`
--
ALTER TABLE `note_tags`
  MODIFY `id_note_tag` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `tags`
--
ALTER TABLE `tags`
  MODIFY `id_tag` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`id_kategori`) REFERENCES `categories` (`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `note_tags`
--
ALTER TABLE `note_tags`
  ADD CONSTRAINT `note_tags_ibfk_1` FOREIGN KEY (`id_catatan`) REFERENCES `notes` (`id_catatan`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_tags_ibfk_2` FOREIGN KEY (`id_tag`) REFERENCES `tags` (`id_tag`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

<?php
// SLearning - Koneksi database terpusat + auto-migrasi skema.
// Membuat database & semua tabel yang dibutuhkan bila belum ada,
// sehingga semua fitur (kelas, tugas, absensi, quiz) langsung berfungsi.
$host = "localhost";
$user = "root";
$password = "";
$database = "slearning_db";

$koneksi = mysqli_connect($host, $user, $password);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($koneksi, "utf8mb4");
mysqli_query($koneksi, "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
mysqli_select_db($koneksi, $database);

// Alias agar file lama yang memakai $conn tetap jalan
$conn = $koneksi;

$__schema = [
    "CREATE TABLE IF NOT EXISTS login (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        jenis ENUM('guru','murid') NOT NULL,
        level ENUM('admin','user') NOT NULL DEFAULT 'user'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS kelas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_kelas VARCHAR(100) NOT NULL,
        kode_gabung VARCHAR(30) NOT NULL UNIQUE,
        guru_id INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS anggota_kelas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kelas_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_kelas_user (kelas_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS tugas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL,
        kelas_id INT NOT NULL,
        judul_tugas VARCHAR(180) NOT NULL,
        deskripsi TEXT NULL,
        deadline DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id),
        KEY kelas_id (kelas_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS pengumpulan_tugas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tugas_id INT NOT NULL,
        user_id INT NOT NULL,
        nama_file VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        nilai INT NULL DEFAULT NULL,
        feedback TEXT NULL,
        dikumpulkan_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tugas_user (tugas_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS absensi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL,
        kelas_id INT NOT NULL,
        judul_absensi VARCHAR(180) NOT NULL,
        tanggal DATE NOT NULL,
        jam_mulai TIME NULL DEFAULT NULL,
        jam_selesai TIME NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id),
        KEY kelas_id (kelas_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS absensi_hadir (
        id INT AUTO_INCREMENT PRIMARY KEY,
        absensi_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('hadir','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
        keterangan VARCHAR(255) NULL DEFAULT NULL,
        waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_absen_user (absensi_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS quiz (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL,
        kelas_id INT NOT NULL,
        judul VARCHAR(180) NOT NULL,
        deskripsi TEXT NULL,
        durasi_menit INT NOT NULL DEFAULT 30,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id),
        KEY kelas_id (kelas_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS quiz_soal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        pertanyaan TEXT NOT NULL,
        opsi_a VARCHAR(255) NOT NULL,
        opsi_b VARCHAR(255) NOT NULL,
        opsi_c VARCHAR(255) NOT NULL,
        opsi_d VARCHAR(255) NOT NULL,
        kunci CHAR(1) NOT NULL DEFAULT 'A',
        KEY quiz_id (quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS quiz_hasil (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        user_id INT NOT NULL,
        benar INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 0,
        skor INT NOT NULL DEFAULT 0,
        dikerjakan_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_quiz_user (quiz_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($__schema as $__sql) {
    @mysqli_query($koneksi, $__sql);
}
// Kolom tambahan untuk tabel lama (aman bila sudah ada)
@mysqli_query($koneksi, "ALTER TABLE pengumpulan_tugas ADD COLUMN IF NOT EXISTS nilai INT NULL DEFAULT NULL");
@mysqli_query($koneksi, "ALTER TABLE pengumpulan_tugas ADD COLUMN IF NOT EXISTS feedback TEXT NULL");
@mysqli_query($koneksi, "ALTER TABLE absensi ADD COLUMN IF NOT EXISTS jam_mulai TIME NULL DEFAULT NULL");
@mysqli_query($koneksi, "ALTER TABLE absensi ADD COLUMN IF NOT EXISTS jam_selesai TIME NULL DEFAULT NULL");
@mysqli_query($koneksi, "ALTER TABLE quiz ADD COLUMN IF NOT EXISTS durasi_menit INT NOT NULL DEFAULT 30");
// MySQL <8 tidak mendukung ADD COLUMN IF NOT EXISTS — fallback diam-diam, jadi cek manual:
foreach (['nilai' => "ALTER TABLE pengumpulan_tugas ADD COLUMN nilai INT NULL DEFAULT NULL",
          'feedback' => "ALTER TABLE pengumpulan_tugas ADD COLUMN feedback TEXT NULL"] as $__col => $__q) {
    $__cek = @mysqli_query($koneksi, "SHOW COLUMNS FROM pengumpulan_tugas LIKE '$__col'");
    if ($__cek && mysqli_num_rows($__cek) === 0) { @mysqli_query($koneksi, $__q); }
}
unset($__schema, $__sql, $__cek, $__q, $__col);

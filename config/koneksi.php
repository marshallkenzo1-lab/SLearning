<?php
// SLearning - Koneksi database terpusat + auto-migrasi skema.
// SATU SATU-NYA sumber kebenaran skema database aplikasi.
// Semua tabel dan kolom dibuat/ditambahkan otomatis di sini pada setiap request,
// sehingga tidak perlu membuat tabel manual lewat phpMyAdmin.
$host = "localhost";
$user = "root";
$password = "";
$database = "slearning_db";

// Waktu aplikasi mengikuti WIB (Batam) - perbaikan no. 8 agar tanggal layar = tanggal nyata.
date_default_timezone_set('Asia/Jakarta');

$koneksi = mysqli_connect($host, $user, $password);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($koneksi, "utf8mb4");
mysqli_query($koneksi, "SET time_zone = '+07:00'"); // sesi MySQL juga ikut WIB
mysqli_query($koneksi, "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
mysqli_select_db($koneksi, $database);

// Alias agar file lama yang memakai $conn tetap jalan
$conn = $koneksi;

// Tahun ajaran berjalan (peringatan Juli pindah tahun ajaran) - untuk kelas baru & migrasi.
$taDefault = ((int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1) . '/' . ((int)date('n') >= 7 ? (int)date('Y') + 1 : (int)date('Y'));

/* =========================
   1. BUAT TABEL BILA BELUM ADA
   ========================= */
$__schema = [
    // Struktur lengkap sesuai login.php (waka/admin + nis + foto + status_persetujuan)
    "CREATE TABLE IF NOT EXISTS login (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        jenis ENUM('waka','guru','murid','admin') NOT NULL,
        level ENUM('super admin','admin','user') NOT NULL DEFAULT 'user',
        nis VARCHAR(30) NULL UNIQUE,
        foto VARCHAR(255) NULL,
        status_persetujuan ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS kelas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_kelas VARCHAR(100) NOT NULL,
        kode_gabung VARCHAR(30) NOT NULL UNIQUE,
        guru_id INT DEFAULT NULL,
        tahun_ajaran VARCHAR(9) NOT NULL DEFAULT '$taDefault',
        arsip TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS anggota_kelas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kelas_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'disetujui',
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
        gambar VARCHAR(255) NULL,
        KEY quiz_id (quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS quiz_hasil (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        user_id INT NOT NULL,
        benar INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 0,
        skor INT NOT NULL DEFAULT 0,
        jawaban TEXT NULL,
        dikerjakan_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_quiz_user (quiz_id, user_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS report_murid (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL,
        murid_id INT NOT NULL,
        alasan TEXT NOT NULL,
        tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY guru_id (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS laporan_pengguna (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pelapor_id INT NOT NULL,
        terlapor_id INT NOT NULL,
        kategori VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        status ENUM('pending','diproses','selesai') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY pelapor_id (pelapor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($__schema as $__sql) {
    @mysqli_query($koneksi, $__sql);
}

/* =========================
   2. TAMBAH KOLOM YANG BELUM ADA (untuk tabel lama)
   Format: tabel => [nama_kolom => definisi]
   ========================= */
$__kolom = [
    'login' => [
        'nis'                => "VARCHAR(30) NULL UNIQUE",
        'foto'               => "VARCHAR(255) NULL",
        'status_persetujuan' => "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'",
    ],
    'kelas' => [
        'tahun_ajaran' => "VARCHAR(9) NOT NULL DEFAULT '$taDefault'",
        'arsip'        => "TINYINT(1) NOT NULL DEFAULT 0",
    ],
    'anggota_kelas' => [
        'status' => "ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'disetujui'",
    ],
    'pengumpulan_tugas' => [
        'nilai'    => "INT NULL DEFAULT NULL",
        'feedback' => "TEXT NULL",
    ],
    'absensi' => [
        'jam_mulai'   => "TIME NULL DEFAULT NULL",
        'jam_selesai' => "TIME NULL DEFAULT NULL",
    ],
    'quiz' => [
        'durasi_menit' => "INT NOT NULL DEFAULT 30",
    ],
    'quiz_soal' => [
        'gambar' => "VARCHAR(255) NULL",
    ],
    'quiz_hasil' => [
        'jawaban' => "TEXT NULL",
    ],
];
foreach ($__kolom as $__tabel => $__definisi) {
    foreach ($__definisi as $__nama => $__def) {
        $__cek = @mysqli_query($koneksi, "SHOW COLUMNS FROM `$__tabel` LIKE '$__nama'");
        if ($__cek && mysqli_num_rows($__cek) === 0) {
            @mysqli_query($koneksi, "ALTER TABLE `$__tabel` ADD COLUMN `$__nama` $__def");
        }
    }
}

/* =========================
   3. PERLEBAR ENUM LAMA (login.jenis & login.level) + email boleh NULL
   ========================= */
 $__jt = @mysqli_query($koneksi, "SHOW COLUMNS FROM login LIKE 'jenis'");
 $__rowJenis = $__jt ? mysqli_fetch_assoc($__jt) : null;
 if ($__rowJenis && stripos($__rowJenis['Type'], "'waka'") === false) {
     @mysqli_query($koneksi, "ALTER TABLE login MODIFY jenis ENUM('waka','guru','murid','admin') NOT NULL");
 }
 $__lt = @mysqli_query($koneksi, "SHOW COLUMNS FROM login LIKE 'level'");
 $__rowLevel = $__lt ? mysqli_fetch_assoc($__lt) : null;
 if ($__rowLevel && stripos($__rowLevel['Type'], 'super') === false) {
     @mysqli_query($koneksi, "ALTER TABLE login MODIFY level ENUM('super admin','admin','user') NOT NULL DEFAULT 'user'");
 }
 $__et = @mysqli_query($koneksi, "SHOW COLUMNS FROM login LIKE 'email'");
 $__rowEmail = $__et ? mysqli_fetch_assoc($__et) : null;
 if ($__rowEmail && strtolower((string)$__rowEmail['Null']) !== 'yes') {
     @mysqli_query($koneksi, "ALTER TABLE login MODIFY email VARCHAR(100) NULL");
 }

unset($__schema, $__sql, $__kolom, $__tabel, $__definisi, $__nama, $__def,
      $__cek, $__jt, $__rowJenis, $__lt, $__rowLevel, $__et, $__rowEmail, $__rowEmail_tmp, $__taDefault, $taDefault);
<?php
// SLearning - Koneksi database terpusat (slearning_db, tabel login)
$host = "localhost";
$user = "root";
$password = "";
$database = "slearning_db";

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Alias agar file lama yang memakai $conn tetap jalan
$conn = $koneksi;

<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != 'login') {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quizizz</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>

<body>

<div class="container mt-4">

    <h2>Quizizz</h2>

    <p>
        Selamat datang,
        <strong><?php echo htmlspecialchars($username); ?></strong>
    </p>

    <hr>

    <h4>Daftar Quizizz</h4>

    <p>Belum ada Quizizz.</p>

</div>

</body>
</html>
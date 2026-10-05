<?php
// SLearning - Quiziz (menggunakan tabel slearning_db.login)
session_start();
require_once __DIR__ . '/config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id, nama, username, email, jenis, level FROM login WHERE id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    header("Location: logout.php");
    exit;
}

// Sinkronkan session
$_SESSION['nama']     = $user['nama'];
$_SESSION['username'] = $user['username'];
$_SESSION['email']    = $user['email'];
$_SESSION['jenis']    = $user['jenis'];
$_SESSION['level']    = $user['level'] ?? 'user';

$nama = $user['nama'];
$username = $user['username'];
$jenis = $user['jenis'];
$level = $user['level'] ?? 'user';

// Arahkan ke dashboard sesuai jenis bila diakses langsung
$dashboard = ($jenis === 'guru') ? 'guru.php' : 'murid.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quiziz — SLearning</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>

<body>

<div class="container mt-4">

    <h2>Quiziz</h2>

    <p>
        Selamat datang,
        <strong><?php echo htmlspecialchars($nama); ?></strong>
        (@<?php echo htmlspecialchars($username); ?> • <?php echo htmlspecialchars($jenis); ?> • <?php echo htmlspecialchars($level); ?>)
    </p>

    <p><a href="<?php echo htmlspecialchars($dashboard); ?>">← Kembali ke dashboard</a> | <a href="logout.php">Keluar</a></p>

    <hr>

    <h4>Daftar Quiziz</h4>

    <p>Belum ada Quiziz.</p>

</div>

</body>
</html>

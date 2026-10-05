<?php
// SLearning - Cek isi tabel slearning_db.login (tanpa menampilkan hash password penuh)
require_once __DIR__ . '/config/koneksi.php';

$sql = "SELECT id, nama, username, email, jenis, level FROM login ORDER BY id ASC";
$result = $koneksi->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Login — SLearning</title>
</head>
<body>

    <h1>Data dari tabel login (slearning_db)</h1>
    <p><a href="login.php">← Login</a> | <a href="murid.php">Murid</a> | <a href="guru.php">Guru</a></p>

    <?php if ($result && $result->num_rows > 0): ?>

        <table border="1" cellpadding="8">
            <tr>
                <th>id</th>
                <th>nama</th>
                <th>username</th>
                <th>email</th>
                <th>jenis</th>
                <th>level</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['jenis']) ?></td>
                    <td><?= htmlspecialchars($row['level']) ?></td>
                </tr>
            <?php endwhile; ?>

        </table>

    <?php else: ?>

        <p>Tidak ada data di tabel login.</p>

    <?php endif; ?>

</body>
</html>

<?php
$koneksi->close();

<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "slearning_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$sql = "SELECT * FROM Nama";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Nama</title>
</head>
<body>

    <h1>Data dari tabel Nama</h1>

    <?php if ($result && $result->num_rows > 0): ?>

        <table border="1" cellpadding="8">
            <tr>
                <?php
                // Menampilkan nama kolom secara otomatis
                $fields = $result->fetch_fields();

                foreach ($fields as $field) {
                    echo "<th>" . htmlspecialchars($field->name) . "</th>";
                }
                ?>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <?php foreach ($row as $value): ?>
                        <td><?= htmlspecialchars($value) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endwhile; ?>

        </table>

    <?php else: ?>

        <p>Tidak ada data.</p>

    <?php endif; ?>

</body>
</html>

<?php
$conn->close();
?>
<?php
// =========================================================
// SLearning - SKAJU Learning | Halaman Ruang Kelas Murid
// =========================================================
session_start();
require_once __DIR__ . '/config/koneksi.php';

// Cek Login Murid
if (!isset($_SESSION['user_id']) || (($_SESSION['jenis'] ?? '') === 'guru')) {
    header("Location: login.php");
    exit;
}

$user_id_session = $_SESSION['user_id'];
$nama_murid      = $_SESSION['nama'] ?? 'Murid';
$inisial         = strtoupper(substr(trim($nama_murid), 0, 1));

// Ambil ID Kelas dari URL
$kelas_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($kelas_id <= 0) {
    header("Location: murid.php");
    exit;
}

// Cek apakah murid benar-benar tergabung di kelas ini
$stmt_cek = mysqli_prepare(
    $koneksi,
    "SELECT k.id, k.nama_kelas, k.kode_gabung, l.nama AS nama_guru
     FROM anggota_kelas ak
     INNER JOIN kelas k ON k.id = ak.kelas_id
     LEFT JOIN login l ON k.guru_id = l.id
     WHERE ak.kelas_id = ? AND ak.user_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt_cek, 'ii', $kelas_id, $user_id_session);
mysqli_stmt_execute($stmt_cek);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
mysqli_stmt_close($stmt_cek);

if (!$kelas) {
    // Jika tidak tergabung, lempar kembali ke dashboard
    header("Location: murid.php");
    exit;
}

// Ambil Tugas yang ada di kelas ini dari database
$daftar_tugas = [];
$stmt_tugas = mysqli_prepare(
    $koneksi,
    "SELECT id, judul_tugas AS judul, deadline, deskripsi 
     FROM tugas 
     WHERE kelas_id = ? 
     ORDER BY id DESC"
);
mysqli_stmt_bind_param($stmt_tugas, 'i', $kelas_id);
mysqli_stmt_execute($stmt_tugas);
$res_tugas = mysqli_stmt_get_result($stmt_tugas);
while ($row_t = mysqli_fetch_assoc($res_tugas)) {
    $daftar_tugas[$row_t['id']] = $row_t;
}
mysqli_stmt_close($stmt_tugas);

// Ambil Status Pengumpulan Tugas oleh Murid ini
$pengumpulan = [];
$stmt_p = mysqli_prepare($koneksi, "SELECT tugas_id, nama_file, file_path, dikumpulkan_at FROM pengumpulan_tugas WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_p, "i", $user_id_session);
mysqli_stmt_execute($stmt_p);
$res_p = mysqli_stmt_get_result($stmt_p);
while ($row_p = mysqli_fetch_assoc($res_p)) {
    $pengumpulan[(int)$row_p['tugas_id']] = $row_p;
}
mysqli_stmt_close($stmt_p);

// Proses Upload Tugas di Halaman Kelas
$pesan_tugas = '';
$tipe_pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kumpulkan_tugas'])) {
    $tugas_id_upload = (int)($_POST['tugas_id'] ?? 0);

    if (isset($daftar_tugas[$tugas_id_upload]) && isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
        $file_tugas = $_FILES['file_tugas'];
        $nama_asli = $file_tugas['name'];
        $ekstensi = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));
        
        $folder = __DIR__ . '/uploads/tugas/';
        if (!is_dir($folder)) { mkdir($folder, 0777, true); }

        $nama_baru = 'tugas_' . $user_id_session . '_' . $tugas_id_upload . '_' . time() . '.' . $ekstensi;
        $lokasi = $folder . $nama_baru;
        $path_db = 'uploads/tugas/' . $nama_baru;

        if (move_uploaded_file($file_tugas['tmp_name'], $lokasi)) {
            // Cek apakah sudah pernah kumpul
            $stmt_c = mysqli_prepare($koneksi, "SELECT id, file_path FROM pengumpulan_tugas WHERE tugas_id = ? AND user_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_c, 'ii', $tugas_id_upload, $user_id_session);
            mysqli_stmt_execute($stmt_c);
            $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
            mysqli_stmt_close($stmt_c);

            if ($lama) {
                if (is_file(__DIR__ . '/' . $lama['file_path'])) { @unlink(__DIR__ . '/' . $lama['file_path']); }
                $stmt_s = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nama_file = ?, file_path = ?, dikumpulkan_at = CURRENT_TIMESTAMP WHERE id = ?");
                mysqli_stmt_bind_param($stmt_s, 'ssi', $nama_asli, $path_db, $lama['id']);
            } else {
                $stmt_s = mysqli_prepare($koneksi, "INSERT INTO pengumpulan_tugas (tugas_id, user_id, nama_file, file_path) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_s, 'iiss', $tugas_id_upload, $user_id_session, $nama_asli, $path_db);
            }
            mysqli_stmt_execute($stmt_s);
            mysqli_stmt_close($stmt_s);

            $pesan_tugas = 'Tugas berhasil dikumpulkan.';
            $tipe_pesan = 'success';
        } else {
            $pesan_tugas = 'Gagal mengunggah file.';
            $tipe_pesan = 'error';
        }
    } else {
        $pesan_tugas = 'Pilih file tugas yang valid terlebih dahulu.';
        $tipe_pesan = 'error';
    }
}

$tugas_dibuka = isset($_GET['tugas']) ? (int)$_GET['tugas'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($kelas['nama_kelas']) ?> — SLearning</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#ffffff;--bg-soft:#f7f7f8;--dark:#0a0a0b;--yellow:#ffc107;--yellow-hover:#ffb300;--yellow-soft:#fff6d6;--text:#0a0a0b;--muted:#6b7280;--border:#e7e7ea;--radius:16px;--shadow:0 16px 40px rgba(0,0,0,.08);}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Open Sans',sans-serif;}
body{background:var(--bg);color:var(--text);line-height:1.6;}
.navbar{position:sticky;top:0;background:rgba(255,255,255,.96);border-bottom:1px solid var(--border);padding:14px 24px;display:flex;justify-content:space-between;align-items:center;z-index:50;}
.brand{font-family:'Poppins',sans-serif;font-weight:700;font-size:18px;display:flex;align-items:center;gap:10px;}
.brand img{width:36px;height:36px;object-fit:contain;}
.container{max-width:1100px;margin:30px auto;padding:0 20px;}
.class-header{background:var(--dark);color:#fff;border-radius:24px;padding:36px;margin-bottom:30px;}
.class-header h1{font-family:'Poppins',sans-serif;font-size:28px;margin-bottom:8px;color:#fff;}
.class-header p{color:#a1a1aa;font-size:14px;}
.card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px;box-shadow:0 4px 12px rgba(0,0,0,0.03);}
.btn{background:var(--yellow);border:none;padding:10px 20px;font-family:'Poppins',sans-serif;font-weight:600;border-radius:8px;cursor:pointer;display:inline-block;text-decoration:none;color:#111;}
.btn:hover{background:var(--yellow-hover);}
.btn-outline{background:#fff;border:1px solid var(--border);color:var(--text);padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none;}
.task-list{display:grid;gap:12px;}
.task-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:inherit;transition:.2s;}
.task-card:hover{border-color:var(--yellow);transform:translateY(-2px);}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:600;}
.alert-success{background:#eaf8ef;color:#16713a;}
.alert-error{background:#fff0f0;color:#b42323;}
</style>
</head>
<body>

<header class="navbar">
    <div class="brand">
        <img src="assets/img/logo-smkn7.png" alt="Logo">
        SLearning • Ruang Kelas
    </div>
    <a href="murid.php" class="btn-outline">← Kembali ke Dashboard</a>
</header>

<main class="container">

    <section class="class-header">
        <small style="color:var(--yellow);font-family:'Poppins',sans-serif;font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.05em;">Ruang Kelas Aktif</small>
        <h1><?= htmlspecialchars($kelas['nama_kelas']) ?></h1>
        <p>Pengajar: <strong><?= htmlspecialchars($kelas['nama_guru'] ?? 'Guru Pengampu') ?></strong> • Kode Kelas: <strong><?= htmlspecialchars($kelas['kode_gabung']) ?></strong></p>
    </section>

    <!-- DAFTAR TUGAS DI KELAS INI -->
    <div class="card" id="tugas">
        <h2 style="font-family:'Poppins',sans-serif;font-size:18px;margin-bottom:12px;">Daftar Tugas Kelas</h2>
        <div class="task-list">
            <?php if (empty($daftar_tugas)): ?>
                <p style="font-size:13px;color:var(--muted);">Belum ada tugas yang dibagikan guru di kelas ini.</p>
            <?php else: ?>
                <?php foreach ($daftar_tugas as $id_t => $tugas): ?>
                    <?php $sudah = isset($pengumpulan[$id_t]); ?>
                    <a href="?id=<?= $kelas_id ?>&tugas=<?= $id_t ?>#pengumpulan" class="task-card">
                        <div>
                            <h3 style="font-family:'Poppins',sans-serif;font-size:14px;margin-bottom:2px;"><?= htmlspecialchars($tugas['judul']) ?></h3>
                            <p style="font-size:12px;color:var(--muted);">Deadline: <?= htmlspecialchars($tugas['deadline']) ?></p>
                        </div>
                        <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:<?= $sudah ? '#eaf8ef' : '#fff6d6' ?>;color:<?= $sudah ? '#16713a' : '#8a6500' ?>;">
                            <?= $sudah ? 'Sudah Kumpul' : 'Belum Kumpul' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORM UPLOAD / PENGUMPULAN TUGAS -->
    <?php if ($tugas_dibuka && isset($daftar_tugas[$tugas_dibuka])): ?>
        <div class="card" id="pengumpulan" style="border-color:var(--yellow);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-family:'Poppins',sans-serif;font-size:18px;"><?= htmlspecialchars($daftar_tugas[$tugas_dibuka]['judul']) ?></h3>
                <a href="kelas.php?id=<?= $kelas_id ?>" class="btn-outline">Tutup</a>
            </div>

            <?php if ($pesan_tugas !== ''): ?>
                <div class="alert <?= $tipe_pesan === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($pesan_tugas) ?>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;color:var(--muted);margin-bottom:14px;background:var(--bg-soft);padding:12px;border-radius:8px;">
                <?= nl2br(htmlspecialchars($daftar_tugas[$tugas_dibuka]['deskripsi'])) ?>
            </p>

            <?php if (isset($pengumpulan[$tugas_dibuka])): ?>
                <div style="font-size:12px;color:#16713a;background:#eaf8ef;padding:10px;border-radius:6px;margin-bottom:14px;">
                    ✓ File tersimpan: <strong><?= htmlspecialchars($pengumpulan[$tugas_dibuka]['nama_file']) ?></strong> (<?= htmlspecialchars($pengumpulan[$tugas_dibuka]['dikumpulkan_at']) ?>)
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="tugas_id" value="<?= $tugas_dibuka ?>">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Upload File Tugas</label>
                <input type="file" name="file_tugas" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:12px;font-size:13px;" required>
                <button type="submit" name="kumpulkan_tugas" class="btn">Kirim Tugas</button>
            </form>
        </div>
    <?php endif; ?>

</main>

</body>
</html>
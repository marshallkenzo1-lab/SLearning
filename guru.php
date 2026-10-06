<?php
// SLearning - SKAJU Learning | Dashboard Guru
session_start();

// Proteksi: wajib login, hanya guru yang boleh akses
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['jenis'] ?? '') === 'murid') {
    header("Location: murid.php");
    exit;
}

// Koneksi Database
require_once __DIR__ . '/config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

// Ambil data guru dari tabel slearning_db.login
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

// Sinkronkan session dengan tabel login
$_SESSION['nama']     = $user['nama'];
$_SESSION['username'] = $user['username'];
$_SESSION['email']    = $user['email'];
$_SESSION['jenis']    = $user['jenis'];
$_SESSION['level']    = $user['level'] ?? 'user';

$nama_guru     = $user['nama'];
$username_guru = $user['username'];
$email_guru    = $user['email'];
$level_guru    = $user['level'] ?? 'user';

// =========================================================
// PROSES TAMBAH KELAS BARU KE DATABASE MYSQL (PERMANEN)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'tambah_kelas') {
    $nama_kelas_baru = trim($_POST['nama_kelas_baru'] ?? '');
    $kode_unik_baru = 'KLS-' . strtoupper(substr(md5(mt_rand() . time()), 0, 5));

    if (!empty($nama_kelas_baru)) {
        $stmt_insert_kelas = mysqli_prepare(
            $koneksi,
            "INSERT INTO kelas (nama_kelas, kode_gabung, guru_id) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt_insert_kelas, "ssi", $nama_kelas_baru, $kode_unik_baru, $user_id);
        mysqli_stmt_execute($stmt_insert_kelas);
        mysqli_stmt_close($stmt_insert_kelas);
    }
    header("Location: guru.php#manajemen-kelas");
    exit;
}

// =========================================================
// PROSES BUAT SESI ABSENSI BARU KE DATABASE MYSQL (PERMANEN)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'buat_absen') {
    $kelas_id_absen = (int)($_POST['kelas_id'] ?? 0);
    $tanggal_absen = $_POST['tanggal_absen'] ?? date('Y-m-d');
    $jam_mulai = $_POST['jam_mulai'] ?? '07:00';
    $jam_selesai = $_POST['jam_selesai'] ?? '09:00';
    $judul_absensi = 'Absensi Pertemuan — ' . $tanggal_absen . ' (' . $jam_mulai . '-' . $jam_selesai . ' WIB)';

    if ($kelas_id_absen > 0) {
        $stmt_absen = mysqli_prepare(
            $koneksi,
            "INSERT INTO absensi (guru_id, kelas_id, judul_absensi, tanggal) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt_absen, "iiss", $user_id, $kelas_id_absen, $judul_absensi, $tanggal_absen);
        mysqli_stmt_execute($stmt_absen);
        mysqli_stmt_close($stmt_absen);
    }
    header("Location: guru.php#menu-absensi");
    exit;
}

// =========================================================
// PROSES TAMBAH TUGAS BARU KE DATABASE MYSQL (PERMANEN)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'tambah') {
    $judul_baru = trim($_POST['judul_tugas'] ?? '');
    $kelas_id_tugas = (int)($_POST['kelas_id'] ?? 0);
    $deadline_baru = str_replace('T', ' ', $_POST['deadline'] ?? '');
    $deskripsi_baru = trim($_POST['deskripsi'] ?? '');

    if (!empty($judul_baru) && $kelas_id_tugas > 0) {
        $stmt_tugas = mysqli_prepare(
            $koneksi,
            "INSERT INTO tugas (guru_id, kelas_id, judul_tugas, deadline, deskripsi) VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt_tugas, "iisss", $user_id, $kelas_id_tugas, $judul_baru, $deadline_baru, $deskripsi_baru);
        mysqli_stmt_execute($stmt_tugas);
        mysqli_stmt_close($stmt_tugas);
    }

    header("Location: guru.php#daftar-tugas");
    exit;
}

// =========================================================
// AMBIL DATA KELAS DARI DATABASE MYSQL
// =========================================================
$list_kelas = [];
$stmt_get_kelas = mysqli_prepare(
    $koneksi,
    "SELECT k.id AS id_kelas, k.nama_kelas, k.kode_gabung, 
            (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id = k.id) AS jml_murid
     FROM kelas k
     WHERE k.guru_id = ?
     ORDER BY k.id DESC"
);
mysqli_stmt_bind_param($stmt_get_kelas, "i", $user_id);
mysqli_stmt_execute($stmt_get_kelas);
$res_kelas = mysqli_stmt_get_result($stmt_get_kelas);
while ($row_k = mysqli_fetch_assoc($res_kelas)) {
    $list_kelas[] = $row_k;
}
mysqli_stmt_close($stmt_get_kelas);

// =========================================================
// AMBIL DATA TUGAS DARI DATABASE MYSQL (REAL-TIME & PERMANEN)
// =========================================================
$list_tugas = [];
$stmt_get_tugas = mysqli_prepare(
    $koneksi,
    "SELECT t.id, t.judul_tugas AS judul, t.deadline, t.deskripsi, k.nama_kelas AS kelas
     FROM tugas t
     JOIN kelas k ON t.kelas_id = k.id
     WHERE t.guru_id = ?
     ORDER BY t.id DESC"
);
mysqli_stmt_bind_param($stmt_get_tugas, "i", $user_id);
mysqli_stmt_execute($stmt_get_tugas);
$res_tugas = mysqli_stmt_get_result($stmt_get_tugas);
while ($row_t = mysqli_fetch_assoc($res_tugas)) {
    $row_t['kumpul'] = '0/36 Kumpul';
    $list_tugas[] = $row_t;
}
mysqli_stmt_close($stmt_get_tugas);

// =========================================================
// AMBIL DATA ABSENSI DARI DATABASE MYSQL (REAL-TIME & PERMANEN)
// =========================================================
$list_absensi = [];
$stmt_get_absen = mysqli_prepare(
    $koneksi,
    "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas AS kelas
     FROM absensi a
     JOIN kelas k ON a.kelas_id = k.id
     WHERE a.guru_id = ?
     ORDER BY a.id DESC"
);
mysqli_stmt_bind_param($stmt_get_absen, "i", $user_id);
mysqli_stmt_execute($stmt_get_absen);
$res_absen = mysqli_stmt_get_result($stmt_get_absen);
while ($row_a = mysqli_fetch_assoc($res_absen)) {
    $list_absensi[] = $row_a;
}
mysqli_stmt_close($stmt_get_absen);

$waktu_sekarang = date('Y-m-d H:i');
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Guru — SLearning</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">

<style>
:root{
    --bg:#ffffff; --bg-soft:#f7f7f8; --dark:#0a0a0b; --surface:#141416;
    --yellow:#ffc107; --yellow-hover:#ffb300; --yellow-soft:#fff6d6;
    --text:#0a0a0b; --muted:#6b7280; --border:#e7e7ea;
    --radius:16px; --radius-lg:22px; --max:1200px;
    --shadow:0 16px 40px rgba(0,0,0,.08); --shadow-sm:0 4px 12px rgba(0,0,0,.04);
    --font-h:'Poppins',sans-serif; --font-b:'Open Sans',sans-serif;
}
*{ margin:0; padding:0; box-sizing:border-box; }
html{ scroll-behavior:smooth; }
body{ font-family:var(--font-b); background:var(--bg); color:var(--text); line-height:1.6; }
a{ text-decoration:none; color:inherit; }
button{ font-family:inherit; cursor:pointer; }
.container{ max-width:var(--max); margin:auto; padding:0 24px; }

/* NAVBAR */
.navbar{ position:sticky; top:0; z-index:50; background:rgba(255,255,255,.94); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); box-shadow:0 8px 30px rgba(0,0,0,.07); }
.nav-inner{ max-width:var(--max); margin:auto; padding:12px 24px; display:flex; align-items:center; gap:25px; }
.brand{ display:flex; align-items:center; gap:12px; }
.brand img{ width:44px; height:44px; object-fit:contain; background:#fff; border-radius:12px; padding:4px; border:1px solid var(--border); }
.brand-text strong{ font-family:var(--font-h); font-size:17px; display:block; line-height: 1.2;}
.brand-text span{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
.nav-links{ margin-left:auto; display:flex; gap:5px; }
.nav-links a{ font-size:14px; font-weight:600; color:#4b5563; padding:10px 13px; border-radius:10px; transition: 0.2s; }
.nav-links a:hover, .nav-links a.active{ background:var(--yellow-soft); color:var(--text); }
.nav-tools { display: flex; align-items: center; gap: 16px; padding-left: 14px; border-left: 1px solid var(--border); }
.profile{ display:flex; align-items:center; gap:10px; cursor: pointer; }
.avatar{ width:40px; height:40px; border-radius:50%; background:var(--dark); color:var(--yellow); display:flex; align-items:center; justify-content:center; font-family:var(--font-h); font-weight:800; }
.profile-info strong{ display:block; font-size:13px; }
.profile-info span{ color:var(--muted); font-size:11px; }

/* DASHBOARD */
.dashboard{ min-height:calc(100vh - 73px); background: radial-gradient(700px 350px at 85% 0%, rgba(255,193,7,.20), transparent), var(--bg); }
.dashboard-content{ position:relative; z-index:1; padding:42px 0 70px; }
.stats{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:32px; }
.stat-card{ background:white; border:1px solid var(--border); border-radius:var(--radius); padding:20px; display:flex; align-items:center; gap:15px; }
.stat-icon{ width:46px; height:46px; border-radius:13px; background:var(--yellow-soft); color:#8a6500; display:flex; align-items:center; justify-content:center; }
.stat-number{ font-family:var(--font-h); font-size:24px; font-weight:800; line-height:1; }
.stat-label{ color:var(--muted); font-size:12px; margin-top:4px; }
.main-grid{ display:grid; grid-template-columns:1.5fr 1fr; gap:24px; margin-bottom:32px; }
.section-head{ display:flex; justify-content:space-between; align-items:end; gap:15px; margin-bottom:16px; }
.section-head h2{ font-family:var(--font-h); font-size:20px; }
.section-head p{ color:var(--muted); font-size:13px; }

/* BUTTONS & FORMS */
.btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:11px 18px; border-radius:11px; border:1px solid transparent; font-family:var(--font-h); font-weight:600; font-size:13px; cursor:pointer; transition:.2s; text-decoration: none; }
.btn-yellow{ background:var(--yellow); color:#171300; }
.btn-yellow:hover{ background:var(--yellow-hover); transform:translateY(-2px); box-shadow:0 8px 22px rgba(255,193,7,.3); }
.btn-logout { background: #fff; border: 1px solid var(--border); color: #b42318; }
.btn-logout:hover { background: #fef2f2; border-color: #f5c2c0; color: #912018; }

.form-card{ background:white; border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:32px; }
.form-group{ display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
.form-group label{ font-size:13px; font-weight:600; font-family:var(--font-h); }
.form-control{ width:100%; padding:12px 16px; border:1px solid var(--border); border-radius:10px; font-family:inherit; font-size:14px; background:var(--bg-soft); outline:none; transition:0.2s; }
.form-control:focus{ border-color:var(--yellow); background:white; }
textarea.form-control{ resize:vertical; min-height:100px; }
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }

/* TOOL & TASK */
.task-list{ display:grid; gap:12px; margin-bottom: 32px; }
.task-card{ background:white; border:1px solid var(--border); border-radius:var(--radius); padding:19px; display:flex; align-items:center; gap:15px; transition:.2s; }
.task-card:hover{ transform:translateY(-3px); border-color: var(--yellow); }
.task-icon{ width:48px; height:48px; border-radius:13px; background:var(--bg-soft); color:var(--dark); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.task-content{ flex:1; }
.task-content h3{ font-family:var(--font-h); font-size:14px; margin-bottom:3px; }
.task-content p{ color:var(--muted); font-size:12px; }
.task-badge { font-size: 10px; padding: 4px 8px; border-radius: 6px; background: var(--yellow-soft); color: #8a6500; font-weight: 700; margin-left: 8px; }

/* KELAS CARD KODE */
.class-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 32px; }
.class-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; position: relative; }
.class-card h4 { font-family: var(--font-h); font-size: 15px; margin-bottom: 4px; }
.class-card p { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
.code-box { background: var(--bg-soft); border: 1px dashed var(--border); border-radius: 8px; padding: 8px; text-align: center; font-family: monospace; font-weight: bold; font-size: 14px; color: var(--dark); letter-spacing: 1px; cursor: pointer; }

/* SIDEBAR */
.sidebar-widget { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 22px; }
.sidebar-widget.dark { background: var(--dark); color: white; border: none; }
.sidebar-widget h3 { font-family: var(--font-h); font-size: 18px; margin-bottom: 6px; }
.sidebar-widget p.subtitle { color: var(--muted); font-size: 12px; margin-bottom: 18px; }
.sidebar-widget.dark p.subtitle { color: #a1a1aa; }
.profile-detail{ background:#1c1c1f; border:1px solid #2a2a2e; padding:16px; border-radius:12px; }
.profile-detail .item{ margin-bottom:12px; }
.profile-detail .label{ font-size:11px; color:#a1a1aa; display:block; margin-bottom:2px; }
.profile-detail .value{ font-size:13px; font-weight:600; color:white; }

@media(max-width:980px){
    .stats{ grid-template-columns:1fr 1fr; }
    .main-grid{ grid-template-columns:1fr; }
    .class-grid{ grid-template-columns:1fr 1fr; }
    .nav-links{ display:none; }
}
</style>
</head>

<body>

<header class="navbar">
    <div class="nav-inner">
        <a href="guru.php" class="brand">
            <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">
            <span class="brand-text"><strong>SLearning</strong><span>GURU • SMKN 7</span></span>
        </a>
        <nav class="nav-links">
            <a href="guru.php" class="active">Beranda</a>
            <a href="#manajemen-kelas">Manajemen Kelas</a>
            <a href="#menu-absensi">Absensi</a>
            <a href="#daftar-tugas">Penilaian</a>
        </nav>
        <div class="nav-tools">
            <div class="profile">
                <div class="avatar"><?php echo strtoupper(substr($nama_guru, 0, 1)); ?></div>
                <div class="profile-info"><strong><?php echo htmlspecialchars($nama_guru); ?></strong><span>Pengajar</span></div>
            </div>
            <a href="logout.php" class="btn btn-logout" onclick="return confirm('Yakin ingin keluar?')">Keluar</a>
        </div>
    </div>
</header>

<main class="dashboard">
<div class="container dashboard-content">

    <section class="stats">
        <div class="stat-card"><div class="stat-icon">📄</div><div><div class="stat-number"><?php echo count($list_tugas); ?></div><div class="stat-label">Tugas Dibuat</div></div></div>
        <div class="stat-card"><div class="stat-icon">📅</div><div><div class="stat-number"><?php echo count($list_absensi); ?></div><div class="stat-label">Sesi Absen Aktif</div></div></div>
        <div class="stat-card"><div class="stat-icon">🏫</div><div><div class="stat-number"><?php echo count($list_kelas); ?></div><div class="stat-label">Kelas Terdaftar</div></div></div>
        <div class="stat-card"><div class="stat-icon">📊</div><div><div class="stat-number">86%</div><div class="stat-label">Partisipasi</div></div></div>
    </section>

    <div class="main-grid">
        <div class="main-column">
            
            <!-- MANAJEMEN KELAS & KODE GABUNG -->
            <section id="manajemen-kelas">
                <div class="section-head">
                    <div>
                        <h2>Manajemen Kelas & Kode Gabung</h2>
                        <p>Buat kelas baru dan dapatkan kode unik permanen untuk dibagikan ke murid.</p>
                    </div>
                </div>

                <form action="guru.php" method="POST" class="form-card" style="margin-bottom: 20px; border-left: 4px solid var(--yellow);">
                    <input type="hidden" name="aksi" value="tambah_kelas">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Buat Kelas Baru</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="nama_kelas_baru" class="form-control" placeholder="Contoh: XI PPLG 3 / XII TKJ 1" required>
                            <button type="submit" class="btn btn-yellow" style="white-space: nowrap;">+ Buat Kelas & Kode</button>
                        </div>
                    </div>
                </form>

                <div class="class-grid">
                    <?php if (empty($list_kelas)): ?>
                        <p style="color: var(--muted); font-size: 13px;">Belum ada kelas yang dibuat. Silakan buat kelas baru di atas.</p>
                    <?php else: ?>
                        <?php foreach ($list_kelas as $kls): ?>
                        <div class="class-card">
                            <h4><?php echo htmlspecialchars($kls['nama_kelas']); ?></h4>
                            <p><?php echo $kls['jml_murid']; ?> Murid Tergabung</p>
                            <div class="code-box" title="Klik untuk salin kode" onclick="navigator.clipboard.writeText('<?php echo $kls['kode_gabung']; ?>'); alert('Kode kelas <?php echo $kls['kode_gabung']; ?> berhasil disalin!');">
                                📋 <?php echo htmlspecialchars($kls['kode_gabung']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- MENU ABSENSI MURID -->
            <section id="menu-absensi">
                <div class="section-head">
                    <div>
                        <h2>Absensi Kehadiran Murid</h2>
                        <p>Buka sesi absen harian agar murid dapat melakukan absensi.</p>
                    </div>
                </div>

                <form action="guru.php" method="POST" class="form-card" style="margin-bottom: 20px;">
                    <input type="hidden" name="aksi" value="buat_absen">
                    <h3 style="font-family: var(--font-h); font-size: 16px; margin-bottom: 14px;">Buka Sesi Absen Pertemuan Baru</h3>
                    
                    <div class="form-group">
                        <label>Pilih Kelas</label>
                        <select name="kelas_id" class="form-control" required>
                            <option value="" disabled selected>-- Pilih Kelas --</option>
                            <?php foreach ($list_kelas as $kls): ?>
                                <option value="<?php echo $kls['id_kelas']; ?>"><?php echo htmlspecialchars($kls['nama_kelas']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Pertemuan</label>
                            <input type="date" name="tanggal_absen" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Jam Mulai - Selesai</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="time" name="jam_mulai" class="form-control" value="07:00" required>
                                <input type="time" name="jam_selesai" class="form-control" value="09:00" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-yellow" style="width: 100%; margin-top: 10px;">Buka Sesi Absen Sekarang</button>
                </form>

                <div class="task-list">
                    <?php if (empty($list_absensi)): ?>
                        <p style="color: var(--muted); font-size: 13px;">Belum ada sesi absensi yang dibuat.</p>
                    <?php else: ?>
                        <?php foreach ($list_absensi as $absen): ?>
                        <div class="task-card">
                            <div class="task-icon">📅</div>
                            <div class="task-content">
                                <h3>
                                    <?php echo htmlspecialchars($absen['judul_absensi']); ?>
                                    <span class="task-badge"><?php echo htmlspecialchars($absen['kelas']); ?></span>
                                </h3>
                                <p>Tanggal: <?php echo htmlspecialchars($absen['tanggal']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- FORM BUAT TUGAS -->
            <section id="buat-tugas" style="margin-top: 30px;">
                <div class="section-head"><div><h2>Buat Tugas / Materi Baru</h2><p>Publikasikan tugas baru ke siswa.</p></div></div>
                
                <form action="guru.php" method="POST" class="form-card">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="form-group">
                        <label>Judul Tugas</label>
                        <input type="text" name="judul_tugas" class="form-control" placeholder="Contoh: Praktik Pemrograman Berbasis Objek" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Kelas</label>
                            <select name="kelas_id" class="form-control" required>
                                <option value="" disabled selected>-- Pilih Kelas --</option>
                                <?php foreach ($list_kelas as $kls): ?>
                                    <option value="<?php echo $kls['id_kelas']; ?>"><?php echo htmlspecialchars($kls['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Batas Waktu (Deadline)</label>
                            <input type="datetime-local" name="deadline" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Instruksi Pengerjaan</label>
                        <textarea name="deskripsi" class="form-control" placeholder="Tuliskan detail tugas..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-yellow" style="width:100%; margin-top:10px;">Publikasikan Tugas</button>
                </form>
            </section>

            <!-- DAFTAR TUGAS TERKIRIM -->
            <section id="daftar-tugas">
                <div class="section-head"><div><h2>Tugas Terkirim</h2><p>Daftar tugas yang berhasil dipublikasikan.</p></div></div>

                <div class="task-list">
                    <?php if (empty($list_tugas)): ?>
                        <p style="color: var(--muted); font-size: 13px;">Belum ada tugas yang dikirimkan.</p>
                    <?php else: ?>
                        <?php foreach ($list_tugas as $tugas) : ?>
                        <div class="task-card">
                            <div class="task-icon">📌</div>
                            <div class="task-content">
                                <h3>
                                    <?php echo htmlspecialchars($tugas['judul'] ?? ''); ?> 
                                    <span class="task-badge"><?php echo htmlspecialchars($tugas['kelas'] ?? ''); ?></span>
                                </h3>
                                <p>Tenggat: <?php echo htmlspecialchars($tugas['deadline'] ?? ''); ?> • <strong><?php echo htmlspecialchars($tugas['kumpul'] ?? '0/36 Kumpul'); ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        </div>

        <aside class="sidebar">
            <div class="sidebar-widget dark">
                <h3>Profil Pengajar</h3>
                <p class="subtitle">Data dari tabel login • slearning_db</p>
                <div class="profile-detail">
                    <div class="item">
                        <span class="label">Nama Lengkap</span>
                        <span class="value"><?php echo htmlspecialchars($nama_guru); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Username</span>
                        <span class="value"><?php echo htmlspecialchars($username_guru); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($email_guru); ?></span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
</main>

</body>
</html>
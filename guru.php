<?php
// SLearning - SKAJU Learning | Dashboard Guru
session_start();

// Inisialisasi array tugas di session jika belum ada
if (!isset($_SESSION['list_tugas'])) {
    $_SESSION['list_tugas'] = [
        [
            'id' => 1,
            'judul' => 'Praktik PBO — Class & Object',
            'kelas' => 'XI PPLG 1',
            'deadline' => '2026-10-05 23:59',
            'deskripsi' => 'Buat program sederhana menggunakan konsep Class dan Object pada Java.',
            'kumpul' => '34/36 Kumpul'
        ],
        [
            'id' => 2,
            'judul' => 'Basis Data — Normalisasi',
            'kelas' => 'XI PPLG 2',
            'deadline' => '2025-01-01 23:59',
            'deskripsi' => 'Pelajari normalisasi tabel hingga bentuk 3NF.',
            'kumpul' => '15/35 Kumpul'
        ]
    ];
}

// Inisialisasi data Kelas & Kode Unik Kelas
if (!isset($_SESSION['list_kelas'])) {
    $_SESSION['list_kelas'] = [
        ['id_kelas' => 1, 'nama_kelas' => 'XI PPLG 1', 'kode_gabung' => 'PPLG1-X7', 'jml_murid' => 36],
        ['id_kelas' => 2, 'nama_kelas' => 'XI PPLG 2', 'kode_gabung' => 'PPLG2-K9', 'jml_murid' => 35],
        ['id_kelas' => 3, 'nama_kelas' => 'XII PPLG', 'kode_gabung' => 'PPLG12-Z2', 'jml_murid' => 30]
    ];
}

// Inisialisasi Data Absensi (Sesi Absen yang dibuat Guru)
if (!isset($_SESSION['list_absensi'])) {
    $_SESSION['list_absensi'] = [
        [
            'id_absen' => 1,
            'kelas' => 'XI PPLG 1',
            'tanggal' => '2026-10-05',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'status_sesi' => 'Dibuka'
        ],
        [
            'id_absen' => 2,
            'kelas' => 'XI PPLG 2',
            'tanggal' => '2026-10-05',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:30',
            'status_sesi' => 'Ditutup'
        ]
    ];
}

// PROSES TAMBAH KELAS BARU (KHUSUS GURU)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'tambah_kelas') {
    $nama_kelas_baru = trim($_POST['nama_kelas_baru'] ?? '');
    $kode_unik_baru = 'KLS-' . strtoupper(substr(md5(mt_rand()), 0, 5));

    if (!empty($nama_kelas_baru)) {
        array_unshift($_SESSION['list_kelas'], [
            'id_kelas' => time(),
            'nama_kelas' => $nama_kelas_baru,
            'kode_gabung' => $kode_unik_baru,
            'jml_murid' => 0
        ]);
    }
    header("Location: guru.php#manajemen-kelas");
    exit;
}

// PROSES BUAT SESI ABSENSI BARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'buat_absen') {
    $kelas_absen = $_POST['kelas_absen'] ?? '';
    $tanggal_absen = $_POST['tanggal_absen'] ?? date('Y-m-d');
    $jam_mulai = $_POST['jam_mulai'] ?? '07:00';
    $jam_selesai = $_POST['jam_selesai'] ?? '09:00';

    if (!empty($kelas_absen)) {
        array_unshift($_SESSION['list_absensi'], [
            'id_absen' => time(),
            'kelas' => $kelas_absen,
            'tanggal' => $tanggal_absen,
            'jam_mulai' => $jam_mulai,
            'jam_selesai' => $jam_selesai,
            'status_sesi' => 'Dibuka'
        ]);
    }
    header("Location: guru.php#menu-absensi");
    exit;
}

// PROSES TAMBAH TUGAS BARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'tambah') {
    $id_baru = time(); 
    $judul_baru = $_POST['judul_tugas'] ?? '';
    $kelas_baru = $_POST['kelas'] ?? '';
    $deadline_baru = str_replace('T', ' ', $_POST['deadline'] ?? '');
    $deskripsi_baru = $_POST['deskripsi'] ?? '';

    $nama_file = $_FILES['lampiran_guru']['name'] ?? '';
    if($nama_file != "") {
        $target_dir = "assets/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        move_uploaded_file($_FILES['lampiran_guru']['tmp_name'], $target_dir . $nama_file);
    }

    array_unshift($_SESSION['list_tugas'], [
        'id' => $id_baru,
        'judul' => $judul_baru,
        'kelas' => $kelas_baru,
        'deadline' => $deadline_baru,
        'deskripsi' => $deskripsi_baru,
        'kumpul' => '0/36 Kumpul'
    ]);

    header("Location: guru.php#daftar-tugas");
    exit;
}

// PROSES HAPUS TUGAS
if (isset($_GET['hapus_id'])) {
    $id_hapus = $_GET['hapus_id'];
    foreach ($_SESSION['list_tugas'] as $key => $tugas) {
        if ($tugas['id'] == $id_hapus) {
            unset($_SESSION['list_tugas'][$key]);
            break;
        }
    }
    $_SESSION['list_tugas'] = array_values($_SESSION['list_tugas']);
    header("Location: guru.php#daftar-tugas");
    exit;
}

// PROSES EDIT TUGAS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi']) && $_POST['aksi'] == 'edit') {
    $id_edit = $_POST['id_tugas'] ?? '';
    foreach ($_SESSION['list_tugas'] as $key => $tugas) {
        if ($tugas['id'] == $id_edit) {
            $_SESSION['list_tugas'][$key]['judul'] = $_POST['judul_tugas'] ?? $tugas['judul'];
            $_SESSION['list_tugas'][$key]['kelas'] = $_POST['kelas'] ?? $tugas['kelas'];
            $_SESSION['list_tugas'][$key]['deadline'] = str_replace('T', ' ', $_POST['deadline'] ?? $tugas['deadline']);
            $_SESSION['list_tugas'][$key]['deskripsi'] = $_POST['deskripsi'] ?? $tugas['deskripsi'];
            break;
        }
    }
    header("Location: guru.php#daftar-tugas");
    exit;
}

<<<<<<< HEAD
$nama_guru = $_SESSION['nama'] ?? 'Budi Santoso, S.Kom';
$nip = $_SESSION['nip'] ?? 'NIP. 19850723 201001 1 015';
$mapel = 'Kejuruan PPLG';
$waktu_sekarang = date('Y-m-d H:i');
=======
// Ambil data guru dari tabel slearning_db.login
require_once __DIR__ . '/config/koneksi.php';

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
    // Session ada tapi user tidak ada di tabel login
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

// Hari ini (tanggal dinamis Indonesia)
$hari_list = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan_list = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$hari_ini = $hari_list[(int)date('w')] . ', ' . date('j') . ' ' . $bulan_list[(int)date('n')] . ' ' . date('Y');
>>>>>>> 2a7e7f6a706ca512e157966b26e09512c45e78a2
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
.btn-sm { min-height: 32px; padding: 6px 12px; font-size: 11px; border-radius: 8px; }
.btn-outline { background: white; border-color: var(--border); color: var(--text); }
.btn-outline:hover { background: var(--bg-soft); }
.btn-danger { background: #feecec; color: #dc2626; }
.btn-danger:hover { background: #fca5a5; color: white; }

.form-card{ background:white; border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:32px; }
.form-group{ display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
.form-group label{ font-size:13px; font-weight:600; font-family:var(--font-h); }
.form-control{ width:100%; padding:12px 16px; border:1px solid var(--border); border-radius:10px; font-family:inherit; font-size:14px; background:var(--bg-soft); outline:none; transition:0.2s; }
.form-control:focus{ border-color:var(--yellow); background:white; }
textarea.form-control{ resize:vertical; min-height:100px; }
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }

/* TOOL & TASK */
.tool-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 32px; }
.tool-btn { background: white; border: 1px solid var(--border); border-radius: 14px; padding: 18px 12px; display: flex; flex-direction: column; align-items: center; gap: 10px; transition: 0.2s; color: var(--text); font-weight: 600; font-size: 13px; cursor:pointer; text-align: center; }
.tool-btn:hover { border-color: var(--yellow); transform: translateY(-3px); box-shadow: var(--shadow-sm); }
.tool-btn svg { color: var(--dark); background: var(--bg-soft); padding: 10px; border-radius: 12px; width: 44px; height: 44px; }
.task-list{ display:grid; gap:12px; margin-bottom: 32px; }
.task-card{ background:white; border:1px solid var(--border); border-radius:var(--radius); padding:19px; display:flex; align-items:center; gap:15px; transition:.2s; }
.task-card:hover{ transform:translateY(-3px); border-color: var(--yellow); }
.task-icon{ width:48px; height:48px; border-radius:13px; background:var(--bg-soft); color:var(--dark); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.task-content{ flex:1; }
.task-content h3{ font-family:var(--font-h); font-size:14px; margin-bottom:3px; }
.task-content p{ color:var(--muted); font-size:12px; }
.task-badge { font-size: 10px; padding: 4px 8px; border-radius: 6px; background: var(--yellow-soft); color: #8a6500; font-weight: 700; margin-left: 8px; }
.task-action{ font-size:12px; font-weight:600; padding:8px 14px; cursor: pointer; font-family: var(--font-h); border-radius:8px; background:var(--bg-soft); border:1px solid var(--border); transition: 0.2s; }
.task-action:hover{ background:var(--yellow); color:#171300; border-color:var(--yellow); }

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

/* MODAL */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 999; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: 0.3s; }
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-content { background: var(--bg); width: 95%; max-width: 900px; max-height: 85vh; border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow); transform: translateY(20px); transition: 0.3s; overflow-y: auto; position: relative; }
.modal-content.sm { max-width: 600px; }
.modal-overlay.active .modal-content { transform: translateY(0); }
.btn-close { position: absolute; top: 20px; right: 20px; background: var(--bg-soft); border: 1px solid var(--border); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.data-table-container { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 20px;}
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table th { background: var(--bg-soft); font-weight: 600; color: var(--muted); font-family: var(--font-h); }
.status-badge{ font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; display:inline-block; }
.badge-green{ background:#dcfce7; color:#15803d; }
.badge-blue{ background:#e0f2fe; color:#0369a1; }
.badge-red{ background:#feecec; color:#dc2626; }
.badge-yellow{ background:var(--yellow-soft); color:#8a6500; }
.nilai-input { width: 60px; padding: 6px; border: 1px solid var(--border); border-radius: 6px; text-align: center; font-weight: bold;}
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
        </div>
    </div>
</header>

<main class="dashboard">
<div class="container dashboard-content">

    <section class="stats">
        <div class="stat-card"><div class="stat-icon">📁</div><div><div class="stat-number"><?php echo count($_SESSION['list_tugas']); ?></div><div class="stat-label">Tugas Dibuat</div></div></div>
        <div class="stat-card"><div class="stat-icon">📋</div><div><div class="stat-number"><?php echo count($_SESSION['list_absensi']); ?></div><div class="stat-label">Sesi Absen Aktif</div></div></div>
        <div class="stat-card"><div class="stat-icon">👥</div><div><div class="stat-number"><?php echo count($_SESSION['list_kelas']); ?></div><div class="stat-label">Kelas Terdaftar</div></div></div>
        <div class="stat-card"><div class="stat-icon">📊</div><div><div class="stat-number">86%</div><div class="stat-label">Partisipasi</div></div></div>
    </section>

    <div class="main-grid">
        <div class="main-column">
            
            <!-- ALAT GURU -->
            <section id="alat-guru">
                <div class="section-head"><div><h2>Alat Mengajar</h2><p>Akses cepat fitur kelas.</p></div></div>
                <div class="tool-grid">
                    <button class="tool-btn" onclick="openModal('modalBukuNilai')">📖 Buku Nilai</button>
                    <button class="tool-btn" onclick="openModal('modalRekapAbsensi')">📋 Rekap Absensi</button>
                    <button class="tool-btn" onclick="openModal('modalPengumuman')">📢 Buat Pengumuman</button>
                    <button class="tool-btn" onclick="openModal('modalEksporData')">📥 Ekspor Data</button>
                </div>
            </section>

            <!-- MANAJEMEN KELAS & KODE GABUNG -->
            <section id="manajemen-kelas">
                <div class="section-head">
                    <div>
                        <h2>Manajemen Kelas & Kode Gabung</h2>
                        <p>Buat kelas baru dan dapatkan kode unik untuk dibagikan ke murid.</p>
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
                    <?php foreach ($_SESSION['list_kelas'] as $kls): ?>
                    <div class="class-card">
                        <h4><?php echo htmlspecialchars($kls['nama_kelas']); ?></h4>
                        <p><?php echo $kls['jml_murid']; ?> Murid Tergabung</p>
                        <div class="code-box" title="Klik untuk salin kode" onclick="navigator.clipboard.writeText('<?php echo $kls['kode_gabung']; ?>'); alert('Kode kelas <?php echo $kls['kode_gabung']; ?> berhasil disalin!');">
                            🔑 <?php echo htmlspecialchars($kls['kode_gabung']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
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

                <!-- Form Buat Sesi Absen -->
                <form action="guru.php" method="POST" class="form-card" style="margin-bottom: 20px;">
                    <input type="hidden" name="aksi" value="buat_absen">
                    <h3 style="font-family: var(--font-h); font-size: 16px; margin-bottom: 14px;">Buka Sesi Absen Pertemuan Baru</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Kelas</label>
                            <select name="kelas_absen" class="form-control" required>
                                <option value="" disabled selected>-- Pilih Kelas --</option>
                                <?php foreach ($_SESSION['list_kelas'] as $kls): ?>
                                    <option value="<?php echo $kls['nama_kelas']; ?>"><?php echo $kls['nama_kelas']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Pertemuan</label>
                            <input type="date" name="tanggal_absen" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="form-control" value="07:00" required>
                        </div>
                        <div class="form-group">
                            <label>Jam Selesai</label>
                            <input type="time" name="jam_selesai" class="form-control" value="09:00" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-yellow" style="width: 100%; margin-top: 10px;">Buka Sesi Absen Sekarang</button>
                </form>

                <!-- Daftar Sesi Absen -->
                <div class="task-list">
                    <?php foreach ($_SESSION['list_absensi'] as $absen): ?>
                    <div class="task-card">
                        <div class="task-icon">📋</div>
                        <div class="task-content">
                            <h3>
                                Absensi Pertemuan — <?php echo htmlspecialchars($absen['kelas']); ?>
                                <span class="task-badge"><?php echo htmlspecialchars($absen['tanggal']); ?></span>
                                <?php if($absen['status_sesi'] == 'Dibuka'): ?>
                                    <span class="status-badge badge-green" style="margin-left: 6px;">Dibuka</span>
                                <?php else: ?>
                                    <span class="status-badge badge-red" style="margin-left: 6px;">Ditutup</span>
                                <?php endif; ?>
                            </h3>
                            <p>Waktu: <?php echo $absen['jam_mulai']; ?> - <?php echo $absen['jam_selesai']; ?> WIB</p>
                        </div>
                        <button class="task-action" onclick="openModal('modalRekapAbsensi')">Cek Rekap Hadir</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- FORM BUAT TUGAS -->
            <section id="buat-tugas" style="margin-top: 30px;">
                <div class="section-head"><div><h2>Buat Tugas / Materi Baru</h2><p>Publikasikan tugas baru ke siswa.</p></div></div>
                
                <form action="guru.php" method="POST" enctype="multipart/form-data" class="form-card">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="form-group">
                        <label>Judul Tugas</label>
                        <input type="text" name="judul_tugas" class="form-control" placeholder="Contoh: Praktik Pemrograman Berbasis Objek" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Kelas</label>
                            <select name="kelas" class="form-control" required>
                                <option value="" disabled selected>-- Pilih Kelas --</option>
                                <?php foreach ($_SESSION['list_kelas'] as $kls): ?>
                                    <option value="<?php echo $kls['nama_kelas']; ?>"><?php echo $kls['nama_kelas']; ?></option>
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
                    <div class="form-group">
                        <label>Lampiran File (Opsional)</label>
                        <input type="file" name="lampiran_guru" class="form-control" accept=".doc,.docx,.xls,.xlsx,.pdf,.zip" style="padding: 9px 16px;">
                    </div>
                    <button type="submit" class="btn btn-yellow" style="width:100%; margin-top:10px;">Publikasikan Tugas</button>
                </form>
            </section>

            <!-- DAFTAR TUGAS TERKIRIM -->
            <section id="daftar-tugas">
                <div class="section-head"><div><h2>Tugas Terkirim</h2><p>Daftar tugas yang berhasil dipublikasikan.</p></div></div>

                <div class="task-list">
                    <?php foreach ($_SESSION['list_tugas'] as $tugas) : 
                        $status_lewat = ($tugas['deadline'] < $waktu_sekarang);
                    ?>
                    <div class="task-card">
                        <div class="task-icon">📄</div>
                        <div class="task-content">
                            <h3>
                                <?php echo htmlspecialchars($tugas['judul'] ?? ''); ?> 
                                <span class="task-badge"><?php echo htmlspecialchars($tugas['kelas'] ?? ''); ?></span>
                                <?php if($status_lewat): ?>
                                    <span class="status-badge badge-red" style="margin-left: 6px;">Ditutup (Lewat Tenggat)</span>
                                <?php else: ?>
                                    <span class="status-badge badge-green" style="margin-left: 6px;">Aktif</span>
                                <?php endif; ?>
                            </h3>
                            <p>Tenggat: <?php echo htmlspecialchars($tugas['deadline'] ?? ''); ?> • <strong style="color:var(--text);"><?php echo htmlspecialchars($tugas['kumpul'] ?? '0/36 Kumpul'); ?></strong></p>
                        </div>
                        <button class="task-action" onclick="bukaDetailTugas(
                            '<?php echo $tugas['id'] ?? ''; ?>', 
                            '<?php echo addslashes($tugas['judul'] ?? ''); ?>', 
                            '<?php echo addslashes($tugas['kelas'] ?? ''); ?>', 
                            '<?php echo addslashes($tugas['deadline'] ?? ''); ?>', 
                            '<?php echo addslashes($tugas['deskripsi'] ?? ''); ?>'
                        )">Lihat & Nilai</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="sidebar">
            <div class="sidebar-widget dark">
                <h3>Profil Pengajar</h3>
<<<<<<< HEAD
                <p class="subtitle">Data Dapodik</p>
                <div class="profile-detail">
                    <div class="item"><span class="label">Nama</span><span class="value"><?php echo $nama_guru; ?></span></div>
                    <div class="item"><span class="label">NIP</span><span class="value"><?php echo $nip; ?></span></div>
                    <div class="item"><span class="label">Mapel</span><span class="value"><?php echo $mapel; ?></span></div>
=======
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
                    <div class="item">
                        <span class="label">Jenis / Level</span>
                        <span class="value"><?php echo htmlspecialchars('guru • ' . $level_guru); ?></span>
                    </div>
>>>>>>> 2a7e7f6a706ca512e157966b26e09512c45e78a2
                </div>
            </div>
        </aside>
    </div>
</div>
</main>

<!-- MODAL DETAIL TUGAS -->
<div class="modal-overlay" id="modalDetailTugas">
    <div class="modal-content" style="max-width: 950px;">
        <button class="btn-close" onclick="closeModal('modalDetailTugas')">✕</button>
        <h2 style="font-family:var(--font-h); font-size:22px; margin-bottom: 20px;">Manajemen Tugas & Konfirmasi Susulan</h2>

        <div class="task-review-box" id="infoTugasBox" style="background:var(--bg-soft); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <div>
                    <h3 id="modalJudulTugas" style="font-family:var(--font-h); font-size:18px;">Judul Tugas</h3>
                    <span id="modalMetaTugas" style="font-size: 12px; color: var(--muted);">Kelas & Tenggat</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm btn-outline" onclick="aktifkanFormEdit()">Edit Tugas</button>
                    <a id="btnHapusTugas" href="#" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus tugas ini?')">Hapus</a>
                </div>
            </div>
            <p id="modalDescTugas" style="font-size: 13px; color: var(--muted);">Deskripsi tugas...</p>
        </div>

        <form id="formEditTugas" action="guru.php" method="POST" class="form-card" style="display: none; margin-bottom: 24px; border-color: var(--yellow);">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_tugas" id="editIdTugas">
            <h3 style="font-family:var(--font-h); font-size:16px; margin-bottom:12px;">Edit Informasi Tugas</h3>
            <div class="form-group">
                <label>Judul Tugas</label>
                <input type="text" name="judul_tugas" id="editJudul" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Pilih Kelas</label>
                    <select name="kelas" id="editKelas" class="form-control" required>
                        <?php foreach ($_SESSION['list_kelas'] as $kls): ?>
                            <option value="<?php echo $kls['nama_kelas']; ?>"><?php echo $kls['nama_kelas']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Batas Waktu</label>
                    <input type="datetime-local" name="deadline" id="editDeadline" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Instruksi Pengerjaan</label>
                <textarea name="deskripsi" id="editDeskripsi" class="form-control" required></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-yellow">Simpan Perubahan</button>
                <button type="button" class="btn btn-outline" onclick="batalEdit()">Batal</button>
            </div>
        </form>

        <h3 style="font-family:var(--font-h); font-size:16px; margin-bottom: 12px;">Daftar Pengumpulan & Permintaan Akses Susulan</h3>
        <div class="data-table-container">
            <table class="data-table">
                <thead><tr><th>Nama Murid</th><th>Status Pengiriman</th><th>Aksi / Konfirmasi Guru</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong>Citra Kirana</strong><br><span style="font-size:11px; color:var(--muted);">Terlambat • Belum mengumpulkan</span></td>
                        <td><span class="status-badge badge-yellow">Menunggu Konfirmasi Akses</span></td>
                        <td>
                            <button class="btn btn-sm btn-yellow" onclick="alert('Akses diberikan! Citra Kirana sekarang bisa mengumpulkan tugas.')">Setujui Akses Susulan</button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Andhini</strong><br><span style="font-size:11px; color:var(--muted);">Tepat Waktu</span></td>
                        <td><span class="status-badge badge-green">Sudah Dikumpulkan</span></td>
                        <td><span style="font-size: 12px; color: var(--muted);">Sudah Dinilai (92)</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL BUKU NILAI -->
<div class="modal-overlay" id="modalBukuNilai"><div class="modal-content"><button class="btn-close" onclick="closeModal('modalBukuNilai')">✕</button><h2>Buku Nilai</h2></div></div>

<!-- MODAL REKAP ABSENSI -->
<div class="modal-overlay" id="modalRekapAbsensi">
    <div class="modal-content">
        <button class="btn-close" onclick="closeModal('modalRekapAbsensi')">✕</button>
        <h2 style="font-family:var(--font-h); font-size:22px; margin-bottom: 6px;">Rekap Kehadiran Murid</h2>
        <p style="color:var(--muted); font-size:13px; margin-bottom: 20px;">Pantau status kehadiran siswa secara berkala.</p>
        <div class="data-table-container">
            <table class="data-table">
                <thead><tr><th>Nama Murid</th><th>Waktu Absen</th><th>Status Kehadiran</th></tr></thead>
                <tbody>
                    <tr><td><strong>Andhini</strong></td><td>07:10 WIB</td><td><span class="status-badge badge-green">Hadir</span></td></tr>
                    <tr><td><strong>Bima Aditya</strong></td><td>07:15 WIB</td><td><span class="status-badge badge-green">Hadir</span></td></tr>
                    <tr><td><strong>Citra Kirana</strong></td><td>-</td><td><span class="status-badge badge-red">Alpa / Belum Absen</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL PENGUMUMAN -->
<div class="modal-overlay" id="modalPengumuman"><div class="modal-content sm"><button class="btn-close" onclick="closeModal('modalPengumuman')">✕</button><h2>Buat Pengumuman</h2></div></div>

<!-- MODAL EKSPOR DATA -->
<div class="modal-overlay" id="modalEksporData"><div class="modal-content sm"><button class="btn-close" onclick="closeModal('modalEksporData')">✕</button><h2>Ekspor Data</h2></div></div>

<script>
    function openModal(modalId) { document.getElementById(modalId).classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); document.body.style.overflow = 'auto'; }

    function bukaDetailTugas(id, judul, kelas, deadline, deskripsi) {
        document.getElementById('modalJudulTugas').innerText = judul;
        document.getElementById('modalMetaTugas').innerHTML = 'Dikirim ke: <strong>' + kelas + '</strong> • Tenggat: ' + deadline;
        document.getElementById('modalDescTugas').innerText = deskripsi;
        
        document.getElementById('btnHapusTugas').href = 'guru.php?hapus_id=' + id;

        document.getElementById('editIdTugas').value = id;
        document.getElementById('editJudul').value = judul;
        document.getElementById('editKelas').value = kelas;
        document.getElementById('editDeadline').value = deadline.replace(' ', 'T');
        document.getElementById('editDeskripsi').value = deskripsi;

        document.getElementById('infoTugasBox').style.display = 'block';
        document.getElementById('formEditTugas').style.display = 'none';

        openModal('modalDetailTugas');
    }

    function aktifkanFormEdit() {
        document.getElementById('infoTugasBox').style.display = 'none';
        document.getElementById('formEditTugas').style.display = 'block';
    }

    function batalEdit() {
        document.getElementById('infoTugasBox').style.display = 'block';
        document.getElementById('formEditTugas').style.display = 'none';
    }

    window.onclick = function(event) {
        let modals = document.getElementsByClassName('modal-overlay');
        for (let i = 0; i < modals.length; i++) { if (event.target == modals[i]) { modals[i].classList.remove('active'); document.body.style.overflow = 'auto'; } }
    }
</script>
</body>
</html>
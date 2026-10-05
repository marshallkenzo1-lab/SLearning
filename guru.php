<?php
// SLearning - SKAJU Learning | Dashboard Guru
session_start();

// Proteksi: wajib login, dan hanya jenis guru
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['jenis'] ?? '') === 'murid') {
    header("Location: murid.php");
    exit;
}

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
    --bg:#ffffff;
    --bg-soft:#f7f7f8;
    --dark:#0a0a0b;
    --surface:#141416;
    --surface-2:#1c1c1f;

    --yellow:#ffc107;
    --yellow-hover:#ffb300;
    --yellow-soft:#fff6d6;

    --text:#0a0a0b;
    --muted:#6b7280;
    --muted-dark:#a1a1aa;

    --border:#e7e7ea;

    --radius:16px;
    --radius-lg:22px;

    --max:1200px;

    --shadow:0 16px 40px rgba(0,0,0,.08);
    --shadow-sm:0 4px 12px rgba(0,0,0,.04);

    --font-h:'Poppins',sans-serif;
    --font-b:'Open Sans',sans-serif;
}

*{ margin:0; padding:0; box-sizing:border-box; }
html{ scroll-behavior:smooth; }
body{
    font-family:var(--font-b);
    background:var(--bg);
    color:var(--text);
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
}
a{ text-decoration:none; color:inherit; }
button{ font-family:inherit; cursor:pointer; }
.container{ max-width:var(--max); margin:auto; padding:0 24px; }

/* ================= NAVBAR ================= */
.navbar{
    position:sticky; top:0; z-index:50;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
    box-shadow:0 8px 30px rgba(0,0,0,.07);
}
.nav-inner{
    max-width:var(--max); margin:auto; padding:12px 24px;
    display:flex; align-items:center; gap:25px;
}
.brand{ display:flex; align-items:center; gap:12px; }
.brand img{
    width:44px; height:44px; object-fit:contain;
    background:#fff; border-radius:12px; padding:4px;
    border:1px solid var(--border);
}
.brand-text strong{ font-family:var(--font-h); font-size:17px; display:block; line-height: 1.2;}
.brand-text span{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }

.nav-links{ margin-left:auto; display:flex; gap:5px; }
.nav-links a{
    font-size:14px; font-weight:600; color:#4b5563;
    padding:10px 13px; border-radius:10px; transition: 0.2s;
}
.nav-links a:hover, .nav-links a.active{ background:var(--yellow-soft); color:var(--text); }

.nav-tools { display: flex; align-items: center; gap: 16px; padding-left: 14px; border-left: 1px solid var(--border); }
.notif-btn { position: relative; color: var(--muted); background: none; border: none; padding: 6px; border-radius: 8px; transition: 0.2s; }
.notif-btn:hover { background: var(--bg-soft); color: var(--text); }
.notif-dot { position: absolute; top: 4px; right: 6px; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; border: 2px solid white; }

.profile{ display:flex; align-items:center; gap:10px; cursor: pointer; }
.avatar{
    width:40px; height:40px; border-radius:50%;
    background:var(--dark); color:var(--yellow);
    display:flex; align-items:center; justify-content:center;
    font-family:var(--font-h); font-weight:800;
}
.profile-info{ line-height:1.2; }
.profile-info strong{ display:block; font-size:13px; }
.profile-info span{ color:var(--muted); font-size:11px; }

/* ================= HERO ================= */
.dashboard{
    min-height:calc(100vh - 73px);
    background: radial-gradient(700px 350px at 85% 0%, rgba(255,193,7,.20), transparent), var(--bg);
}
.dashboard::before{
    content:""; position:fixed; inset:0; pointer-events:none;
    background-image: linear-gradient(#eeeeef 1px,transparent 1px), linear-gradient(90deg,#eeeeef 1px,transparent 1px);
    background-size:44px 44px;
    mask-image:radial-gradient(700px 500px at 50% 0%, black, transparent); z-index:0;
}
.dashboard-content{ position:relative; z-index:1; padding:42px 0 70px; }

.welcome{
    background:var(--dark); color:white;
    border-radius:24px; padding:34px;
    display:flex; justify-content:space-between; align-items:center;
    gap:25px; position:relative; overflow:hidden; margin-bottom:24px;
    box-shadow: var(--shadow);
}
.welcome::after{
    content:""; position:absolute; width:330px; height:330px;
    right:-100px; top:-140px;
    background:radial-gradient(circle, rgba(255,193,7,.38), transparent 70%);
}
.welcome-text, .welcome-button{ position:relative; z-index:2; }
.welcome small{ color:var(--yellow); font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
.welcome h1{ font-family:var(--font-h); font-size:clamp(25px,4vw,34px); margin:6px 0 8px; }
.welcome p{ color:#b9b9bf; font-size:14px; }

/* ================= BUTTONS & INPUTS ================= */
.btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    min-height:44px; padding:11px 18px; border-radius:11px;
    border:1px solid transparent; font-family:var(--font-h); font-weight:600;
    font-size:13px; cursor:pointer; transition:.2s; text-decoration: none;
}
.btn-yellow{ background:var(--yellow); color:#171300; }
.btn-yellow:hover{ background:var(--yellow-hover); transform:translateY(-2px); box-shadow:0 8px 22px rgba(255,193,7,.3); }
.btn-outline{ background:white; border-color:var(--border); color:var(--text); }
.btn-outline:hover{ background:var(--bg-soft); }
.btn-logout{ background:#fff; border:1px solid var(--border); color:#b42318; }
.btn-logout:hover{ background:#fef2f2; border-color:#f5c2c0; color:#912018; }

.search-wrapper { position: relative; display: flex; align-items: center; }
.search-wrapper svg { position: absolute; left: 14px; color: var(--muted); }
.search-input { 
    padding: 10px 16px 10px 42px; border: 1px solid var(--border); 
    border-radius: 10px; font-size: 13px; background: var(--bg-soft); 
    outline: none; transition: 0.2s; width: 250px; font-family: inherit;
}
.search-input:focus { background: white; border-color: var(--yellow); box-shadow: 0 0 0 3px var(--yellow-soft); }

/* ================= STATS ================= */
.stats{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:32px; }
.stat-card{
    background:white; border:1px solid var(--border); border-radius:var(--radius);
    padding:20px; display:flex; align-items:center; gap:15px; transition:.2s;
}
.stat-card:hover{ transform:translateY(-3px); box-shadow:var(--shadow-sm); }
.stat-icon{
    width:46px; height:46px; border-radius:13px;
    background:var(--yellow-soft); color:#8a6500;
    display:flex; align-items:center; justify-content:center;
}
.stat-number{ font-family:var(--font-h); font-size:24px; font-weight:800; line-height:1; }
.stat-label{ color:var(--muted); font-size:12px; margin-top:4px; }

/* ================= LAYOUT GRID ================= */
.main-grid{ display:grid; grid-template-columns:1.5fr 1fr; gap:24px; margin-bottom:32px; }
.section-head{ display:flex; justify-content:space-between; align-items:end; gap:15px; margin-bottom:16px; }
.section-head h2{ font-family:var(--font-h); font-size:20px; }
.section-head p{ color:var(--muted); font-size:13px; }

/* ================= QUICK TOOLS (NEW) ================= */
.tool-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 32px; }
.tool-btn { 
    background: white; border: 1px solid var(--border); border-radius: 14px; 
    padding: 18px 12px; display: flex; flex-direction: column; align-items: center; 
    gap: 10px; transition: 0.2s; color: var(--text); font-weight: 600; font-size: 13px;
    text-align: center;
}
.tool-btn:hover { border-color: var(--yellow); transform: translateY(-3px); box-shadow: var(--shadow-sm); }
.tool-btn svg { color: var(--dark); background: var(--bg-soft); padding: 10px; border-radius: 12px; width: 44px; height: 44px; transition: 0.2s; }
.tool-btn:hover svg { background: var(--yellow); }

/* ================= FORMS ================= */
.form-card{
    background:white; border:1px solid var(--border);
    border-radius:var(--radius); padding:24px; margin-bottom:32px;
}
.form-group{ display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
.form-group label{ font-size:13px; font-weight:600; font-family:var(--font-h); }
.form-control{
    width:100%; padding:12px 16px; border:1px solid var(--border);
    border-radius:10px; font-family:inherit; font-size:14px;
    background:var(--bg-soft); outline:none; transition:0.2s;
}
.form-control:focus{ border-color:var(--yellow); background:white; }
textarea.form-control{ resize:vertical; min-height:100px; }
.form-row{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }

/* ================= TASK LIST ================= */
.task-list{ display:grid; gap:12px; }
.task-card{
    background:white; border:1px solid var(--border); border-radius:var(--radius);
    padding:19px; display:flex; align-items:center; gap:15px; transition:.2s;
}
.task-card:hover{ transform:translateY(-3px); box-shadow:var(--shadow-sm); border-color: var(--yellow); }
.task-icon{
    width:48px; height:48px; border-radius:13px;
    background:var(--bg-soft); color:var(--dark);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.task-content{ flex:1; }
.task-content h3{ font-family:var(--font-h); font-size:14px; margin-bottom:3px; }
.task-content p{ color:var(--muted); font-size:12px; }
.task-badge { font-size: 11px; padding: 4px 8px; border-radius: 6px; background: var(--yellow-soft); color: #8a6500; font-weight: 700; margin-left: 8px; }
.task-action{
    font-size:12px; font-weight:600; padding:8px 14px;
    border-radius:8px; background:var(--bg-soft); border:1px solid var(--border);
}
.task-action:hover{ background:var(--yellow); color:#171300; border-color:var(--yellow); }

/* ================= SIDEBAR WIDGETS ================= */
.sidebar-widget {
    background: white; border: 1px solid var(--border);
    border-radius: var(--radius); padding: 24px; margin-bottom: 22px;
}
.sidebar-widget.dark { background: var(--dark); color: white; border: none; }
.sidebar-widget h3 { font-family: var(--font-h); font-size: 18px; margin-bottom: 6px; }
.sidebar-widget p.subtitle { color: var(--muted); font-size: 12px; margin-bottom: 18px; }
.sidebar-widget.dark p.subtitle { color: #a1a1aa; }

.profile-detail{ background:#1c1c1f; border:1px solid #2a2a2e; padding:16px; border-radius:12px; }
.profile-detail .item{ margin-bottom:12px; }
.profile-detail .item:last-child{ margin-bottom:0; }
.profile-detail .label{ font-size:11px; color:#a1a1aa; display:block; margin-bottom:2px; }
.profile-detail .value{ font-size:13px; font-weight:600; color:white; }

/* SCHEDULE LIST (NEW) */
.schedule-list { display: flex; flex-direction: column; gap: 16px; }
.schedule-item { display: flex; gap: 14px; position: relative; }
.schedule-item::before { content: ""; position: absolute; left: 19px; top: 40px; bottom: -16px; width: 2px; background: var(--border); }
.schedule-item:last-child::before { display: none; }
.time-box { 
    background: var(--yellow-soft); color: #8a6500; font-weight: 700; font-size: 11px;
    width: 40px; height: 40px; border-radius: 10px; display: flex; flex-direction: column; 
    align-items: center; justify-content: center; flex-shrink: 0; line-height: 1.2; z-index: 2;
}
.time-box.done { background: var(--bg-soft); color: var(--muted-dark); }
.schedule-info { padding-top: 2px; }
.schedule-info strong { display: block; font-size: 13px; color: var(--text); }
.schedule-info span { font-size: 12px; color: var(--muted); }

/* ACTIVITY LIST */
.activity-list { display: flex; flex-direction: column; }
.activity-item{
    display:flex; align-items:center; gap:14px;
    padding:14px 0; border-bottom:1px solid var(--border); justify-content: space-between;
}
.activity-item:last-child{ border-bottom:none; padding-bottom: 0; }
.activity-info{ flex:1; }
.activity-info strong{ font-size:13px; display:block; color:var(--text); }
.activity-info span{ font-size:11px; color:var(--muted); }
.status-badge{ font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; }
.badge-blue{ background:#e0f2fe; color:#0369a1; }
.badge-green{ background:#dcfce7; color:#15803d; }

/* ================= FOOTER ================= */
footer{ background:#08080a; color:#a1a1aa; padding:25px 0; border-top:4px solid var(--yellow); }
.footer-inner{ display:flex; justify-content:space-between; gap:15px; flex-wrap:wrap; font-size:12px; }
footer strong{ color:white; }

/* ================= MOBILE ================= */
@media(max-width:980px){
    .stats{ grid-template-columns:1fr 1fr; }
    .main-grid{ grid-template-columns:1fr; }
    .tool-grid { grid-template-columns: repeat(2, 1fr); }
    .nav-links{ display:none; }
}
@media(max-width:650px){
    .nav-inner{ padding:12px 16px; gap:12px; }
    .nav-tools{ padding-left:0; border-left:none; gap:10px; margin-left:auto; }
    .profile-info{ display:none; }
    .btn-logout{ padding:9px 12px; font-size:12px; }
    .container{ padding:0 16px; }
    .welcome{ padding:25px; flex-direction:column; align-items:flex-start; }
    .welcome h1 { font-size: 22px; }
    .stats{ grid-template-columns:1fr 1fr; gap:10px; }
    .stat-card{ padding:15px; }
    .stat-number{ font-size:20px; }
    .stat-icon{ width:40px; height:40px; }
    .form-row{ grid-template-columns:1fr; }
    .section-head { flex-direction: column; align-items: flex-start; }
    .search-input { width: 100%; margin-top: 10px; }
    .task-card { flex-direction: column; align-items: flex-start; }
}
</style>
</head>

<body>

<!-- ================= NAVBAR ================= -->
<header class="navbar">
    <div class="nav-inner">
        <a href="guru.php" class="brand">
            <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">
            <span class="brand-text">
                <strong>SLearning</strong>
                <span>Guru • SMKN 7</span>
            </span>
        </a>

        <nav class="nav-links">
            <a href="guru.php" class="active">Beranda</a>
            <a href="#buat-tugas">Manajemen Kelas</a>
            <a href="#daftar-tugas">Penilaian</a>
        </nav>

        <div class="nav-tools">
            <button class="notif-btn">
                <span class="notif-dot"></span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            </button>
            <div class="profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($nama_guru, 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <strong><?php echo htmlspecialchars($nama_guru); ?></strong>
                    <span>Pengajar</span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-logout" onclick="return confirm('Yakin ingin keluar?')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar
            </a>
        </div>
    </div>
</header>

<!-- ================= DASHBOARD ================= -->
<main class="dashboard">
<div class="container dashboard-content">

    <!-- WELCOME -->
    <section class="welcome">
        <div class="welcome-text">
            <small>Dashboard Guru — <?php echo $hari_ini; ?></small>
            <h1>Halo, <?php echo htmlspecialchars($nama_guru); ?> 👋</h1>
            <p>Siap untuk mengajar hari ini? Cek jadwal kelas dan tugas siswa yang menunggu penilaian.</p>
        </div>
        <div class="welcome-button">
            <a href="#buat-tugas" class="btn btn-yellow">
                + Buat Tugas
            </a>
        </div>
    </section>

    <!-- STATISTIK -->
    <section class="stats">
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
            </div>
            <div>
                <div class="stat-number">12</div>
                <div class="stat-label">Tugas Dibuat</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <div>
                <div class="stat-number">28</div>
                <div class="stat-label">Perlu Dinilai</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="stat-number">3</div>
                <div class="stat-label">Kelas Aktif</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div>
                <div class="stat-number">86%</div>
                <div class="stat-label">Tingkat Partisipasi</div>
            </div>
        </div>
    </section>

    <!-- KONTEN UTAMA -->
    <div class="main-grid">
        
        <!-- KOLOM KIRI -->
        <div class="main-column">
            
            <!-- ALAT GURU (NEW TOOLKIT) -->
            <section id="alat-guru">
                <div class="section-head">
                    <div>
                        <h2>Alat Mengajar</h2>
                        <p>Akses cepat ke fitur operasional kelas.</p>
                    </div>
                </div>
                <div class="tool-grid">
                    <a href="#" class="tool-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Buku Nilai
                    </a>
                    <a href="#" class="tool-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                        Rekap Absensi
                    </a>
                    <a href="#" class="tool-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                        Pengumuman
                    </a>
                    <a href="#" class="tool-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Ekspor Data
                    </a>
                </div>
            </section>

            <!-- FORM BUAT TUGAS -->
            <section id="buat-tugas">
                <div class="section-head">
                    <div>
                        <h2>Buat Tugas / Materi</h2>
                        <p>Publikasikan tugas baru ke siswa kelas Anda.</p>
                    </div>
                </div>
                
                <form action="#" method="POST" class="form-card">
                    <div class="form-group">
                        <label for="judul">Judul Tugas</label>
                        <input type="text" id="judul" name="judul" class="form-control" placeholder="Contoh: Praktik Pemrograman Berbasis Objek" required>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Instruksi Pengerjaan</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" placeholder="Tuliskan detail tugas, lampiran, dan format file yang diterima..." required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="kelas">Pilih Kelas</label>
                            <select id="kelas" name="kelas" class="form-control">
                                <option value="XI PPLG 1">XI PPLG 1 (36 Siswa)</option>
                                <option value="XI PPLG 2">XI PPLG 2 (35 Siswa)</option>
                                <option value="XII PPLG">XII PPLG (32 Siswa)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="deadline">Batas Waktu</label>
                            <input type="datetime-local" id="deadline" name="deadline" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-yellow" style="width:100%; margin-top:10px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Publikasikan Sekarang
                    </button>
                </form>
            </section>

            <!-- DAFTAR TUGAS -->
            <section id="daftar-tugas">
                <div class="section-head">
                    <div>
                        <h2>Daftar Tugas Aktif</h2>
                        <p>Kelola dan pantau progres tugas.</p>
                    </div>
                    <!-- SEARCH BAR -->
                    <div class="search-wrapper">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="search-input" placeholder="Cari tugas...">
                    </div>
                </div>

                <div class="task-list">
                    <div class="task-card">
                        <div class="task-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v5h5M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="9 17 14 12 19 17"/></svg>
                        </div>
                        <div class="task-content">
                            <h3>PBO — Class & Object <span class="task-badge">XI PPLG 1</span></h3>
                            <p>Tenggat: Hari ini, 23:59 WIB • 32/36 Siswa Mengumpulkan</p>
                        </div>
                        <a href="#" class="task-action">Nilai (4 Baru)</a>
                    </div>

                    <div class="task-card">
                        <div class="task-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v5h5M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="9 17 14 12 19 17"/></svg>
                        </div>
                        <div class="task-content">
                            <h3>Basis Data — Normalisasi <span class="task-badge">XI PPLG 2</span></h3>
                            <p>Tenggat: 2 Oktober 2026 • 35/35 Siswa Mengumpulkan</p>
                        </div>
                        <a href="#" class="task-action" style="background:#e7f7ee; color:#15803d; border-color:#dcfce7;">Semua Dinilai</a>
                    </div>
                </div>
            </section>
        </div>

        <!-- KOLOM KANAN (Sidebar) -->
        <aside class="sidebar">
            
            <!-- INFORMASI AKUN -->
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
                    <div class="item">
                        <span class="label">Jenis / Level</span>
                        <span class="value"><?php echo htmlspecialchars('guru • ' . $level_guru); ?></span>
                    </div>
                </div>
            </div>

            <!-- JADWAL HARI INI (NEW) -->
            <div class="sidebar-widget">
                <h3>Jadwal Hari Ini</h3>
                <p class="subtitle">Senin, Jam ke 1 - 8</p>
                
                <div class="schedule-list">
                    <div class="schedule-item">
                        <div class="time-box done">07<br>30</div>
                        <div class="schedule-info">
                            <strong>Upacara Bendera</strong>
                            <span>Lapangan Utama</span>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="time-box">08<br>15</div>
                        <div class="schedule-info">
                            <strong>XI PPLG 1 - Teori PBO</strong>
                            <span>Ruang Teori 3 • Sedang Berlangsung</span>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="time-box" style="background:var(--bg-soft); color:var(--text);">10<br>30</div>
                        <div class="schedule-info">
                            <strong>Istirahat</strong>
                            <span>Kantin / Ruang Guru</span>
                        </div>
                    </div>
                    <div class="schedule-item">
                        <div class="time-box">11<br>00</div>
                        <div class="schedule-info">
                            <strong>XI PPLG 2 - Praktik Lab</strong>
                            <span>Lab Komputer 1</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PENGUMPULAN SISWA -->
            <div class="sidebar-widget">
                <div class="section-head" style="margin-bottom:12px;">
                    <div>
                        <h3>Aktivitas Siswa</h3>
                        <p class="subtitle" style="margin-bottom:0;">Pengumpulan terbaru</p>
                    </div>
                    <a href="#" style="font-size:12px; color:var(--yellow); font-weight:700;">Semua</a>
                </div>

                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong>Andhini (20260001)</strong>
                            <span>PBO — Class & Object</span>
                        </div>
                        <span class="status-badge badge-blue">Perlu Nilai</span>
                    </div>
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong>Bima Aditya (20260002)</strong>
                            <span>PBO — Class & Object</span>
                        </div>
                        <span class="status-badge badge-blue">Perlu Nilai</span>
                    </div>
                    <div class="activity-item">
                        <div class="activity-info">
                            <strong>Citra Kirana (20260003)</strong>
                            <span>Basis Data — Normalisasi</span>
                        </div>
                        <span class="status-badge badge-green">Dinilai (92)</span>
                    </div>
                </div>
            </div>

        </aside>

    </div>
</div>
</main>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="container">
        <div class="footer-inner">
            <span>
                © <?php echo date('Y'); ?> <strong>SLearning</strong> — SMK Negeri 7 Batam
            </span>
            <span>Sistem Informasi Manajemen Pembelajaran (Guru)</span>
        </div>
    </div>
</footer>

</body>
</html>
<?php
/**
 * SLearning - Dashboard Waka / Superadmin
 * Fitur: Broadcast Pengumuman, Pantau Report, Statistik, Grafik Tugas, Filter Murid, Profil
 * UI: Tema Hijau Sage & Soft Yellow
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   KONEKSI DATABASE
   ========================= */
$kandidatKoneksi = [
    __DIR__ . '/koneksi.php',
    __DIR__ . '/config/koneksi.php',
    __DIR__ . '/config.php',
    __DIR__ . '/includes/koneksi.php',
    __DIR__ . '/db/koneksi.php'
];
foreach ($kandidatKoneksi as $fileKoneksi) {
    if (is_file($fileKoneksi)) {
        require_once $fileKoneksi;
        if (isset($koneksi) && $koneksi instanceof mysqli) {
            break;
        }
    }
}
if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    http_response_code(500);
    die('Koneksi database tidak ditemukan.');
}
mysqli_set_charset($koneksi, 'utf8mb4');

/* =========================
   FUNGSI HELPER
   ========================= */
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
function flash_waka(string $pesan, string $tipe = 'success'): void {
    $_SESSION['flash_waka'] = ['pesan' => $pesan, 'tipe' => $tipe];
}
function tanggal_id($tanggal): string {
    if (!$tanggal) return '-';
    $waktu = strtotime($tanggal);
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('j', $waktu).' '.$bulan[(int)date('n', $waktu)].' '.date('Y', $waktu).' - '.date('H:i', $waktu);
}
function tanggal_sekarang(): string {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('j').' '.$bulan[(int)date('n')].' '.date('Y');
}

/* =========================
   AUTENTIKASI
   ========================= */
if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}
if (($_SESSION['jenis'] ?? '') === 'murid') { header('Location: murid.php'); exit; }
if (($_SESSION['jenis'] ?? '') === 'guru') { header('Location: guru.php'); exit; }

$waka_id = (int)$_SESSION['user_id'];
$nama_waka = $_SESSION['nama'] ?? 'Wakasek';

// Ambil Data Profil Waka saat ini
$q_waka = mysqli_query($koneksi, "SELECT id, nama, username, email FROM login WHERE id = $waka_id LIMIT 1");
$data_waka = $q_waka ? mysqli_fetch_assoc($q_waka) : ['nama' => 'Wakasek', 'email' => ''];

/* =========================
   PROSES FORM (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. BUAT PENGUMUMAN BARU
    if (isset($_POST['buat_pengumuman'])) {
        $judul = $_POST['judul'] ?? '';
        $isi = $_POST['isi'] ?? '';
        $target = $_POST['target'] ?? 'semua';
        
        $pola_validasi = '/^[a-zA-Z\s\r\n]+$/';
        if (!preg_match($pola_validasi, $judul) || !preg_match($pola_validasi, $isi)) {
            flash_waka('GAGAL: Judul dan Isi pesan TIDAK BOLEH mengandung Angka (0-9) ataupun Simbol (!@#$.,dll). Hanya huruf dan spasi yang diizinkan!', 'error');
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO pengumuman (judul, isi, target) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $judul, $isi, $target);
            if (mysqli_stmt_execute($stmt)) {
                flash_waka('Pengumuman resmi berhasil disiarkan ke '.$target.'.', 'success');
            } else {
                flash_waka('Terjadi kesalahan saat menyimpan pengumuman.', 'error');
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: waka.php?page=pengumuman"); exit;
    }
    
    // 2. HAPUS PENGUMUMAN
    if (isset($_POST['hapus_pengumuman'])) {
        $id_pengumuman = (int)$_POST['id_pengumuman'];
        mysqli_query($koneksi, "DELETE FROM pengumuman WHERE id = $id_pengumuman");
        flash_waka('Pengumuman berhasil ditarik/dihapus.', 'success');
        header("Location: waka.php?page=pengumuman"); exit;
    }
    
    // 3. TANDAI REPORT SELESAI
    if (isset($_POST['selesaikan_report'])) {
        $id_report = (int)$_POST['id_report'];
        mysqli_query($koneksi, "DELETE FROM report_murid WHERE id = $id_report");
        flash_waka('Laporan masalah telah diselesaikan dan dihapus dari daftar.', 'success');
        header("Location: waka.php?page=report"); exit;
    }

    // 4. UPDATE PROFIL WAKA
    if (isset($_POST['update_profil'])) {
        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');
        $pass_lama = $_POST['password_lama'] ?? '';
        $pass_baru = $_POST['password_baru'] ?? '';

        if ($nama_baru !== '' && $email_baru !== '') {
            $q_cek = mysqli_query($koneksi, "SELECT password FROM login WHERE id = $waka_id LIMIT 1");
            $res_cek = $q_cek ? mysqli_fetch_assoc($q_cek) : null;
            $update_pass_sql = "";
            $params = [$nama_baru, $email_baru];
            $types = "ss";

            if (!empty($pass_baru)) {
                if ($res_cek && password_verify($pass_lama, $res_cek['password'])) {
                    $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
                    $update_pass_sql = ", password = ?";
                    $params[] = $hashed_pass;
                    $types .= "s";
                } else {
                    flash_waka('Password lama salah! Gagal memperbarui sandi.', 'error');
                    header("Location: waka.php?page=profil"); exit;
                }
            }
            $params[] = $waka_id;
            $types .= "i";

            $upd = mysqli_prepare($koneksi, "UPDATE login SET nama = ?, email = ? ".$update_pass_sql." WHERE id = ?");
            mysqli_stmt_bind_param($upd, $types, ...$params);
            if (mysqli_stmt_execute($upd)) {
                $_SESSION['nama'] = $nama_baru;
                flash_waka('Profil berhasil diperbarui.', 'success');
            } else {
                flash_waka('Gagal memperbarui profil.', 'error');
            }
            mysqli_stmt_close($upd);
        } else {
            flash_waka('Nama dan Email tidak boleh kosong.', 'error');
        }
        header("Location: waka.php?page=profil"); exit;
    }
}

/* =========================
   PENGAMBILAN DATA (GET)
   ========================= */
$page = $_GET['page'] ?? 'beranda';
$flash = $_SESSION['flash_waka'] ?? null;
unset($_SESSION['flash_waka']);

// Statistik Singkat
$stat = [
    'guru' => mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM login WHERE jenis='guru'"))['c'] ?? 0,
    'murid' => mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM login WHERE jenis='murid'"))['c'] ?? 0,
    'kelas' => mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM kelas"))['c'] ?? 0,
    'report' => mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM report_murid"))['c'] ?? 0
];

// Data Grafik Pengerjaan Tugas
$stats_tugas = [
    'minggu' => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0],
    'bulan'  => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0],
    'semester' => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0]
];

if ($page === 'pengguna') {
    function getTaskStats($koneksi, $interval) {
        $q = "SELECT 
                SUM(CASE WHEN t.deadline IS NULL OR pt.dikumpulkan_at <= t.deadline THEN 1 ELSE 0 END) as tepat,
                SUM(CASE WHEN t.deadline IS NOT NULL AND pt.dikumpulkan_at > t.deadline THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN pt.nilai IS NOT NULL AND pt.nilai != '' AND pt.nilai > 0 THEN 1 ELSE 0 END) as dinilai
              FROM pengumpulan_tugas pt
              JOIN tugas t ON pt.tugas_id = t.id
              WHERE pt.dikumpulkan_at >= DATE_SUB(CURDATE(), INTERVAL $interval)";
        $res = @mysqli_query($koneksi, $q);
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            return ['tepat' => (int)$row['tepat'], 'telat' => (int)$row['telat'], 'dinilai' => (int)$row['dinilai']];
        }
        return ['tepat' => 0, 'telat' => 0, 'dinilai' => 0];
    }
    
    $stats_tugas['minggu'] = getTaskStats($koneksi, '1 WEEK');
    $stats_tugas['bulan'] = getTaskStats($koneksi, '1 MONTH');
    $stats_tugas['semester'] = getTaskStats($koneksi, '6 MONTH');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Waka - SLearning</title>
<!-- CHART.JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root {
    --waka-bg: #F4F7F5; 
    --waka-card: #FFFFFF;
    --sage-dark: #2F402F; 
    --sage-base: #577357;
    --sage-muted: #6D8C6D;
    --sage-light: #E8F0E8;
    --sage-border: #D1E0D1;
    --yellow-soft: #FFF8D6;
    --yellow-border: #F2E7B5;
    --yellow-accent: #E5C05C;
    --waka-text: var(--sage-dark);
    --line: var(--sage-border);
}

* { box-sizing:border-box; margin:0; padding:0; } 
body { background:var(--waka-bg); color:var(--waka-text); font-family:'Plus Jakarta Sans',sans-serif; padding-bottom:60px; }

/* HEADER & NAVIGASI */
.header { background:linear-gradient(180deg, var(--yellow-soft), #fff); border-bottom:1px solid var(--yellow-border); position:sticky; top:0; z-index:10; }
.header-top { max-width:1150px; margin:auto; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; }
.logo { font-size:22px; font-weight:800; color:var(--sage-dark); text-decoration:none; display:flex; align-items:center; gap:8px; }
.logo span { font-size:11px; background:var(--sage-dark); padding:4px 10px; border-radius:30px; color:var(--yellow-soft); letter-spacing:0.5px; }

/* DROPDOWN MENU PROFIL */
.dropdown { position: relative; display: inline-block; }
.dropdown-toggle { background: #fff; border: 1px solid var(--yellow-border); padding: 8px 12px; border-radius: 30px; color: var(--sage-dark); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.dropdown-toggle:hover { background: var(--yellow-soft); }
.dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: #fff; min-width: 180px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-radius: 12px; border: 1px solid var(--sage-border); z-index: 100; overflow: hidden; }
.dropdown-menu.show { display: block; }
.dropdown-menu a { color: var(--sage-dark); padding: 14px 18px; text-decoration: none; display: block; font-size: 13px; font-weight: 700; border-bottom: 1px solid var(--sage-light); }
.dropdown-menu a:hover { background-color: var(--sage-light); }
.dropdown-menu a.logout-btn { color: #C62828; }
.dropdown-menu a.logout-btn:hover { background-color: #FFEBEE; }

.nav { max-width:1150px; margin:auto; padding:0 16px; display:flex; gap:5px; overflow-x:auto; }
.nav a { padding:13px 20px; font-size:13px; font-weight:700; color:var(--sage-muted); text-decoration:none; white-space:nowrap; border-radius:10px 10px 0 0; transition:0.3s; }
.nav a.active, .nav a:hover { background:#fff; color:var(--sage-dark); border-bottom:3px solid var(--sage-base); }
.container { max-width:1100px; margin:30px auto; padding:0 22px; }

/* ALERT & CARD */
.alert { padding:16px 20px; border-radius:12px; margin-bottom:24px; font-weight:600; display:flex; align-items:center; gap:12px; font-size:14px; }
.alert.success { background:var(--sage-light); color:var(--sage-dark); border:1px solid var(--sage-muted); }
.alert.error { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; }
.card { background:var(--waka-card); border:1px solid var(--line); border-radius:20px; padding:32px; margin-bottom:24px; box-shadow:0 8px 24px rgba(109,140,109,0.06); }
label { display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:var(--sage-dark); }
input, select, textarea { width:100%; padding:16px; border:1px solid var(--sage-border); border-radius:12px; background:var(--waka-bg); font-family:inherit; font-size:14px; margin-bottom:20px; transition:0.3s; }
input:focus, textarea:focus, select:focus { outline:none; border-color:var(--sage-base); background:#fff; box-shadow:0 0 0 4px rgba(109,140,109,0.1); }
.btn { background:var(--sage-base); color:#fff; border:none; padding:16px 28px; border-radius:30px; font-size:14px; font-weight:800; cursor:pointer; transition:0.3s; box-shadow:0 4px 12px rgba(87,115,87,0.2); }
.btn:hover { background:var(--sage-dark); transform:translateY(-2px); }
.btn-hapus { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; padding:10px 18px; border-radius:30px; font-size:12px; font-weight:800; cursor:pointer; transition:0.2s; }
.btn-hapus:hover { background:#FFCDD2; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* HERO WAKA */
.waka-hero { background: linear-gradient(135deg, var(--sage-base) 0%, var(--sage-dark) 100%); border-radius: 24px; padding: 48px 40px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: 0 16px 40px rgba(47, 64, 47, 0.2); position: relative; overflow: hidden; }
.waka-hero::before { content: ''; position: absolute; right: -5%; top: -30%; width: 350px; height: 350px; background: var(--yellow-soft); border-radius: 50%; opacity: 0.15; }
.waka-hero::after { content: ''; position: absolute; left: -10%; bottom: -40%; width: 250px; height: 250px; background: #fff; border-radius: 50%; opacity: 0.05; }
.waka-hero-content { position: relative; z-index: 2; max-width: 600px; }
.waka-hero h1 { color: var(--yellow-soft); font-size: 34px; margin: 0 0 12px 0; font-weight: 800; letter-spacing: -0.5px; }
.waka-hero p { font-size: 15px; opacity: 0.9; margin: 0; line-height: 1.6; color: var(--sage-light); }
.waka-hero-btn { background: var(--yellow-soft); color: var(--sage-dark); padding: 12px 24px; border-radius: 30px; text-decoration: none; font-weight: 800; font-size: 14px; display: inline-block; margin-top: 24px; transition: 0.3s; border: 2px solid transparent; }
.waka-hero-btn:hover { background: #fff; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.waka-hero-icon { font-size: 100px; position: relative; z-index: 2; opacity: 0.9; margin-right: 40px; }
.waka-date-badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 700; margin-bottom: 16px; backdrop-filter: blur(4px); }

.grid-stat { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
.stat-box { background: #fff; border: 1px solid var(--sage-border); border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; transition: 0.3s; box-shadow: 0 4px 15px rgba(109,140,109,0.05); cursor: default; }
.stat-box:hover { transform: translateY(-5px); border-color: var(--sage-muted); box-shadow: 0 12px 25px rgba(109,140,109,0.12); }
.stat-icon { width: 64px; height: 64px; background: var(--yellow-soft); color: var(--sage-dark); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 28px; border: 1px dashed var(--yellow-accent); }
.stat-box h3 { margin: 0; font-size: 32px; color: var(--sage-dark); font-weight: 800; }
.stat-box p { margin: 4px 0 0 0; font-size: 13px; color: var(--sage-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

/* BERANDA GRID (SPLIT VIEW) */
.beranda-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px; }
.panel-section { background: #fff; border-radius: 20px; border: 1px solid var(--sage-border); padding: 24px; box-shadow: 0 4px 15px rgba(109,140,109,0.05); }
.panel-title { font-size: 16px; font-weight: 800; color: var(--sage-dark); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.panel-title span { background: var(--yellow-soft); padding: 4px 8px; border-radius: 6px; font-size: 14px; }

.quick-action-list { display: flex; flex-direction: column; gap: 12px; }
.quick-action-item { display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 14px; border: 1px solid var(--sage-light); text-decoration: none; transition: 0.3s; background: var(--waka-bg); }
.quick-action-item:hover { background: #fff; border-color: var(--sage-base); transform: translateX(6px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.quick-action-item .qa-icon { width: 44px; height: 44px; border-radius: 12px; background: #fff; border: 1px solid var(--sage-border); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.quick-action-item .qa-text h4 { margin: 0 0 4px 0; font-size: 14.5px; color: var(--sage-dark); font-weight: 800; }
.quick-action-item .qa-text p { margin: 0; font-size: 12px; color: var(--sage-muted); font-weight: 500; }

.mini-feed-item { padding: 14px 0; border-bottom: 1px dashed var(--sage-border); }
.mini-feed-item:last-child { border-bottom: none; padding-bottom: 0; }
.mini-feed-item h5 { margin: 0 0 6px 0; font-size: 14px; color: var(--sage-dark); font-weight: 700; }
.mini-feed-item p { margin: 0; font-size: 12px; color: var(--sage-muted); line-height: 1.5; }

/* PENGUMUMAN */
.announcement-card { background: #FFFFFF; border: 1px solid var(--yellow-border); border-radius: 20px; padding: 28px; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(229, 192, 92, 0.08); position: relative; overflow: hidden; }
.announcement-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 8px; background: var(--yellow-accent); }
.announcement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.announcement-title { font-size: 19px; font-weight: 800; color: var(--sage-dark); margin: 0; }
.announcement-meta { font-size: 12px; color: var(--sage-muted); font-weight: 700; display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.announcement-body { font-size: 14.5px; color: var(--sage-dark); line-height: 1.7; background: var(--yellow-soft); padding: 20px; border-radius: 12px; border: 1px dashed var(--yellow-border); margin-bottom: 16px; }

/* BADGES & TABLE */
.badge-target { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.bg-semua { background: var(--sage-light); color: var(--sage-dark); border: 1px solid var(--sage-border); }
.bg-guru { background: #E3F2FD; color: #1565C0; border: 1px solid #BBDEFB; }
.bg-murid { background: #F3E5F5; color: #6A1B9A; border: 1px solid #E1BEE7; }

.chart-container { position: relative; height: 320px; width: 100%; margin-top: 20px; }
.filter-dropdown { padding: 10px 16px; border-radius: 20px; background: #fff; color: var(--sage-dark); border: 1px solid var(--sage-border); font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.3s; margin-bottom: 0; width: auto; }
.filter-dropdown:hover { border-color: var(--sage-base); }

/* CUSTOM PENCARIAN MURID */
.search-bar-wrapper { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 250px; margin-bottom: 0; padding: 12px 20px; border-radius: 50px; background: #fff; border: 1px solid var(--sage-border); font-size: 14px; outline: none; }
.search-input:focus { border-color: var(--sage-base); box-shadow: 0 0 0 3px rgba(109,140,109,0.1); }
.search-select { width: 320px; margin-bottom: 0; padding: 12px 20px; border-radius: 50px; background: #fff; border: 1px solid var(--sage-border); font-size: 14px; font-weight: 700; color: var(--sage-dark); cursor: pointer; outline: none; }

@media (max-width: 768px) {
    .waka-hero { flex-direction: column-reverse; text-align: center; padding: 32px 24px; }
    .waka-hero-icon { margin: 0 0 20px 0; font-size: 80px; }
    .waka-hero h1 { font-size: 28px; }
    .form-grid { grid-template-columns: 1fr; }
    .beranda-grid { grid-template-columns: 1fr; }
    .search-select { width: 100%; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="waka.php" class="logo">
      SLearning <span>WAKA / SUPERADMIN</span>
    </a>
    <div style="display:flex; align-items:center; gap:16px;">
      <span style="font-weight:700; font-size:14px; color:var(--sage-muted);">Hi, <?= e($nama_waka) ?></span>
      
      <!-- DROPDOWN MENU PROFIL & KELUAR -->
      <div class="dropdown">
        <button class="dropdown-toggle" onclick="toggleWakaMenu()" title="Pengaturan Akun">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
        </button>
        <div id="wakaMenu" class="dropdown-menu">
            <a href="waka.php?page=profil">⚙️ Pengaturan Profil</a>
            <a href="logout.php" class="logout-btn" onclick="return confirm('Keluar dari panel Waka?')">🚪 Keluar Akun</a>
        </div>
      </div>
    </div>
  </div>
  <nav class="nav">
    <a href="waka.php?page=beranda" class="<?= $page==='beranda'?'active':'' ?>">Beranda</a>
    <a href="waka.php?page=pengumuman" class="<?= $page==='pengumuman'?'active':'' ?>">Informasi & Pengumuman</a>
    <a href="waka.php?page=report" class="<?= $page==='report'?'active':'' ?>">Laporan Masalah (<?= $stat['report'] ?>)</a>
    <a href="waka.php?page=pengguna" class="<?= $page==='pengguna'?'active':'' ?>">Data Pengguna</a>
  </nav>
</header>

<main class="container">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['tipe'] ?>">
      <?= $flash['tipe'] === 'success' ? '✅' : '❌' ?> <?= e($flash['pesan']) ?>
    </div>
  <?php endif; ?>

  <!-- ================= HALAMAN BERANDA ================= -->
  <?php if ($page === 'beranda'): ?>
    
    <div class="waka-hero">
      <div class="waka-hero-content">
        <div class="waka-date-badge">📅 <?= tanggal_sekarang() ?></div>
        <h1>Selamat Datang, <?= e($nama_waka) ?>!</h1>
        <p>Pusat kendali ekosistem SLearning. Pantau aktivitas kelas, data pengguna, tindak lanjuti laporan masalah, dan siarkan informasi resmi kepada seluruh warga sekolah secara real-time.</p>
        <a href="waka.php?page=pengumuman" class="waka-hero-btn">📢 Siarkan Informasi Baru</a>
      </div>
      <div class="waka-hero-icon">🏫</div>
    </div>

    <!-- 4 KOTAK STATISTIK -->
    <div class="grid-stat">
      <div class="stat-box">
        <div class="stat-icon">👨‍🏫</div>
        <div><h3><?= $stat['guru'] ?></h3><p>Total Guru</p></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon">👩‍🎓</div>
        <div><h3><?= $stat['murid'] ?></h3><p>Total Murid</p></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon">📚</div>
        <div><h3><?= $stat['kelas'] ?></h3><p>Kelas Aktif</p></div>
      </div>
      <div class="stat-box" style="<?= $stat['report'] > 0 ? 'border-color:#FFCDD2; background:#FFF9F9;' : '' ?>">
        <div class="stat-icon" style="<?= $stat['report'] > 0 ? 'background:#FFEBEE; border-color:#FFCDD2;' : '' ?>">🚩</div>
        <div>
          <h3 style="<?= $stat['report'] > 0 ? 'color:#C62828;' : '' ?>"><?= $stat['report'] ?></h3>
          <p style="<?= $stat['report'] > 0 ? 'color:#C62828;' : '' ?>">Laporan Report</p>
        </div>
      </div>
    </div>

    <!-- PANEL BERANDA LANJUTAN (Split View) -->
    <div class="beranda-grid">
        <!-- Kolom 1: Akses Cepat -->
        <div class="panel-section">
            <h3 class="panel-title"><span>⚡</span> Navigasi Cepat</h3>
            <div class="quick-action-list">
                <a href="waka.php?page=report" class="quick-action-item">
                    <div class="qa-icon">🚩</div>
                    <div class="qa-text">
                        <h4>Tinjau Laporan Masalah</h4>
                        <p>Cek keluhan atau pelanggaran murid dari para guru.</p>
                    </div>
                </a>
                <a href="waka.php?page=pengguna" class="quick-action-item">
                    <div class="qa-icon">👥</div>
                    <div class="qa-text">
                        <h4>Kelola Data Pengguna</h4>
                        <p>Lihat rekapitulasi data guru, murid, serta filter jurusan.</p>
                    </div>
                </a>
                <a href="waka.php?page=profil" class="quick-action-item">
                    <div class="qa-icon">⚙️</div>
                    <div class="qa-text">
                        <h4>Pengaturan Keamanan</h4>
                        <p>Perbarui identitas dan ubah kata sandi akun administrator.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Kolom 2: Mini Feed (Aktivitas Terakhir) -->
        <div class="panel-section">
            <h3 class="panel-title"><span>📋</span> Sekilas Informasi</h3>
            
            <div style="margin-bottom: 20px;">
                <h4 style="font-size:12px; color:var(--sage-muted); text-transform:uppercase; margin-bottom:10px; border-bottom:1px solid var(--sage-border); padding-bottom:6px;">Pengumuman Terakhir</h4>
                <?php
                $q_minifeed_info = mysqli_query($koneksi, "SELECT judul, waktu FROM pengumuman ORDER BY waktu DESC LIMIT 2");
                if (mysqli_num_rows($q_minifeed_info) > 0) {
                    while($finfo = mysqli_fetch_assoc($q_minifeed_info)) {
                        echo '<div class="mini-feed-item">
                                <h5>'.e($finfo['judul']).'</h5>
                                <p>📅 '.tanggal_id($finfo['waktu']).'</p>
                              </div>';
                    }
                } else {
                    echo '<p style="font-size:13px; color:var(--sage-muted); font-style:italic;">Belum ada pengumuman disiarkan.</p>';
                }
                ?>
            </div>

            <div>
                <h4 style="font-size:12px; color:var(--sage-muted); text-transform:uppercase; margin-bottom:10px; border-bottom:1px solid var(--sage-border); padding-bottom:6px;">Laporan Masuk (Report)</h4>
                <?php
                $q_minifeed_report = mysqli_query($koneksi, "SELECT r.tanggal, g.nama as guru, m.nama as murid FROM report_murid r JOIN login g ON r.guru_id = g.id JOIN login m ON r.murid_id = m.id ORDER BY r.tanggal DESC LIMIT 2");
                if (mysqli_num_rows($q_minifeed_report) > 0) {
                    while($frep = mysqli_fetch_assoc($q_minifeed_report)) {
                        echo '<div class="mini-feed-item">
                                <h5>🚩 Laporan dari '.e($frep['guru']).'</h5>
                                <p>Terkait murid: <strong style="color:#C62828;">'.e($frep['murid']).'</strong> ('.date('d/m/Y', strtotime($frep['tanggal'])).')</p>
                              </div>';
                    }
                } else {
                    echo '<p style="font-size:13px; color:var(--sage-muted); font-style:italic;">Tidak ada laporan masuk. Semua aman terkendali.</p>';
                }
                ?>
            </div>
        </div>
    </div>

  <!-- ================= HALAMAN PENGUMUMAN ================= -->
  <?php elseif ($page === 'pengumuman'): ?>
    <div class="card">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800;">📢 Buat Informasi & Pengumuman Baru</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Informasi ini akan langsung muncul di halaman beranda Guru dan Murid.</p>
      
      <form method="POST">
        <label>Judul Informasi (Hanya Huruf & Spasi)</label>
        <input type="text" name="judul" placeholder="contoh  PEMBERITAHUAN LIBUR SEMESTER" required>
        
        <label>Isi Informasi / Pesan (Hanya Huruf & Spasi)</label>
        <textarea name="isi" rows="4" placeholder="contoh  diberitahukan..." required></textarea>
        
        <label>Target Penerima Informasi</label>
        <select name="target" required>
          <option value="semua">Semua (Guru & Murid)</option>
          <option value="guru">Khusus Guru Saja</option>
          <option value="murid">Khusus Murid Saja</option>
        </select>
        
        <button type="submit" name="buat_pengumuman" class="btn" style="width: 100%; margin-top: 10px;">🚀 Siarkan Informasi Sekarang</button>
      </form>
    </div>

    <h3 style="margin:36px 0 20px; color:var(--sage-dark); font-weight:800;">Riwayat Informasi Terkirim</h3>
    <?php
    $q_pengumuman = mysqli_query($koneksi, "SELECT * FROM pengumuman ORDER BY waktu DESC");
    if (mysqli_num_rows($q_pengumuman) == 0):
    ?>
      <div class="card" style="text-align:center; color:var(--sage-muted); font-weight:700;">Belum ada informasi.</div>
    <?php else: while ($p = mysqli_fetch_assoc($q_pengumuman)): 
        $kelas_badge = 'bg-semua'; $teks_badge = 'GURU & MURID';
        if ($p['target'] === 'guru') { $kelas_badge = 'bg-guru'; $teks_badge = 'KHUSUS GURU'; }
        if ($p['target'] === 'murid') { $kelas_badge = 'bg-murid'; $teks_badge = 'KHUSUS MURID'; }
    ?>
      <div class="announcement-card">
        <div class="announcement-header">
          <div>
            <h4 class="announcement-title"><?= e($p['judul']) ?></h4>
            <div class="announcement-meta"><span>📅 <?= tanggal_id($p['waktu']) ?></span><span class="badge-target <?= $kelas_badge ?>"><?= $teks_badge ?></span></div>
          </div>
          <form method="POST" onsubmit="return confirm('Tarik / Hapus pengumuman ini?');">
            <input type="hidden" name="id_pengumuman" value="<?= $p['id'] ?>">
            <button type="submit" name="hapus_pengumuman" class="btn-hapus">Tarik Info</button>
          </form>
        </div>
        <div class="announcement-body"><?= nl2br(e($p['isi'])) ?></div>
      </div>
    <?php endwhile; endif; ?>

  <!-- ================= HALAMAN REPORT ================= -->
  <?php elseif ($page === 'report'): ?>
    <div class="card">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800;">🚩 Laporan Masalah / Pelanggaran</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Daftar keluhan atau laporan pelanggaran yang dikirim oleh Guru terhadap Murid tertentu.</p>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
          <thead>
            <tr style="background:var(--sage-light); color:var(--sage-dark);">
              <th style="padding:16px; border-radius:12px 0 0 12px;">Tanggal</th>
              <th style="padding:16px;">Pelapor (Guru)</th>
              <th style="padding:16px;">Terlapor (Murid)</th>
              <th style="padding:16px;">Alasan Report</th>
              <th style="padding:16px; text-align:center; border-radius:0 12px 12px 0;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_report = mysqli_query($koneksi, "SELECT r.id, r.tanggal, r.alasan, g.nama AS nama_guru, m.nama AS nama_murid FROM report_murid r JOIN login g ON r.guru_id = g.id JOIN login m ON r.murid_id = m.id ORDER BY r.tanggal DESC");
            if (mysqli_num_rows($q_report) == 0):
            ?>
              <tr><td colspan="5" style="padding:30px; text-align:center; color:var(--sage-muted); font-weight:700;">✨ Tidak ada laporan saat ini. Semua aman terkendali!</td></tr>
            <?php else: while ($r = mysqli_fetch_assoc($q_report)): ?>
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:16px; color:var(--sage-muted); font-weight:600;"><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
                <td style="padding:16px; font-weight:800; color:var(--sage-dark);"><?= e($r['nama_guru']) ?></td>
                <td style="padding:16px; font-weight:800; color:#C62828;"><?= e($r['nama_murid']) ?></td>
                <td style="padding:16px; max-width:300px; line-height:1.6; color:var(--sage-dark);"><?= e($r['alasan']) ?></td>
                <td style="padding:16px; text-align:center;">
                  <form method="POST" onsubmit="return confirm('Tandai masalah ini sudah diselesaikan? Laporan akan dihapus.');">
                    <input type="hidden" name="id_report" value="<?= $r['id'] ?>"><button type="submit" name="selesaikan_report" class="btn-hapus" style="background:#E8F5E9; color:#2E7D32; border-color:#C8E6C9;">✔ Selesai</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- ================= HALAMAN DATA PENGGUNA (GURU & MURID) ================= -->
  <?php elseif ($page === 'pengguna'): ?>
    
    <!-- GRAFIK STATISTIK TUGAS -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; gap: 16px;">
            <div>
                <p style="text-transform: uppercase; font-size: 13px; font-weight: 700; color: var(--sage-muted); margin-bottom: 4px; letter-spacing: 1px;">Statistik Aktivitas Siswa</p>
                <h2 style="margin-top: 0; color: #4A6E88; font-weight: 800; font-size: 24px;">GRAFIK PENGERJAAN TUGAS</h2>
            </div>
            
            <select id="filterPeriode" class="filter-dropdown">
                <option value="minggu">1 Minggu Terakhir</option>
                <option value="bulan" selected>1 Bulan Terakhir</option>
                <option value="semester">1 Semester (6 Bulan) Terakhir</option>
            </select>
        </div>
        
        <div class="chart-container">
            <canvas id="tugasChart"></canvas>
        </div>
    </div>

    <!-- TABEL REKAP GURU -->
    <div class="card" style="border-top: 4px solid #1565C0;">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800; display:flex; align-items:center; gap:8px;">👨‍🏫 Data Guru Slearning</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Daftar seluruh tenaga pendidik yang memiliki akses pengajar.</p>
      
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
          <thead>
            <tr style="background:#E3F2FD; color:#0D47A1;">
              <th style="padding:16px; border-radius:12px 0 0 12px;">Nama Lengkap</th>
              <th style="padding:16px;">Username</th>
              <th style="padding:16px; border-radius:0 12px 12px 0;">Email</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_guru = mysqli_query($koneksi, "SELECT * FROM login WHERE jenis = 'guru' ORDER BY nama ASC");
            if (mysqli_num_rows($q_guru) == 0):
            ?>
              <tr><td colspan="3" style="padding:30px; text-align:center; color:var(--sage-muted); font-weight:700;">Belum ada data guru.</td></tr>
            <?php else: while ($g = mysqli_fetch_assoc($q_guru)): ?>
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:16px; font-weight:800; color:var(--sage-dark);"><?= e($g['nama']) ?></td>
                <td style="padding:16px; color:var(--sage-muted); font-weight:600;"><?= e($g['username']) ?></td>
                <td style="padding:16px; color:var(--sage-muted);"><?= e($g['email'] ?: '-') ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TABEL REKAP MURID -->
    <div class="card" style="border-top: 4px solid #6A1B9A;">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800; display:flex; align-items:center; gap:8px;">👩‍🎓 Data Murid Slearning</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:24px;">Daftar seluruh siswa terdaftar. Gunakan kolom pencarian untuk menyaring data.</p>
      
      <!-- FITUR FILTER MURID DENGAN NAMA JURUSAN PANJANG -->
      <div class="search-bar-wrapper">
          <input type="text" id="cariMurid" class="search-input" placeholder="🔍 Cari Nama atau NIS murid..." onkeyup="filterTabelMurid()">
          
          <select id="cariJurusan" class="search-select" onchange="filterTabelMurid()">
              <option value="">Semua Jurusan</option>
              <option value="Rekayasa Perangkat Lunak">Rekayasa Perangkat Lunak</option>
              <option value="Teknik Instalasi Tenaga Listrik">Teknik Instalasi Tenaga Listrik</option>
              <option value="Teknik Jaringan Komputer dan Telekomunikasi">Teknik Jaringan Komputer dan Telekomunikasi</option>
              <option value="Desain Komunikasi Visual">Desain Komunikasi Visual</option>
              <option value="Manajemen Perkantoran">Manajemen Perkantoran</option>
              <option value="Bisnis Ritel">Bisnis Ritel</option>
          </select>
      </div>

      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;" id="tabelDataMurid">
          <thead>
            <tr style="background:#F3E5F5; color:#4A148C;">
              <th style="padding:16px; border-radius:12px 0 0 12px;">Nama Lengkap</th>
              <th style="padding:16px;">NIS</th>
              <th style="padding:16px;">Username</th>
              <th style="padding:16px;">Email</th>
              <th style="padding:16px; border-radius:0 12px 12px 0;">Jurusan</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Mengambil semua data login murid, diproteksi jika kolom nis/jurusan belum dibuat
            $q_murid = mysqli_query($koneksi, "SELECT * FROM login WHERE jenis = 'murid' ORDER BY nama ASC");
            if (mysqli_num_rows($q_murid) == 0):
            ?>
              <tr><td colspan="5" style="padding:30px; text-align:center; color:var(--sage-muted); font-weight:700;">Belum ada data murid.</td></tr>
            <?php else: while ($m = mysqli_fetch_assoc($q_murid)): 
                // Fallback aman jika kolom belum ditambahkan di database
                $nis = isset($m['nis']) && !empty($m['nis']) ? $m['nis'] : '-';
                $jurusan = isset($m['jurusan']) && !empty($m['jurusan']) ? $m['jurusan'] : '-';
            ?>
              <tr style="border-bottom:1px solid var(--line);" class="row-murid">
                <td style="padding:16px; font-weight:800; color:var(--sage-dark);"><?= e($m['nama']) ?></td>
                <td style="padding:16px; color:var(--sage-muted); font-weight:700;"><?= e($nis) ?></td>
                <td style="padding:16px; color:var(--sage-muted); font-weight:600;"><?= e($m['username']) ?></td>
                <td style="padding:16px; color:var(--sage-muted);"><?= e($m['email'] ?: '-') ?></td>
                <td style="padding:16px; font-weight:800; color:#6A1B9A;"><?= e($jurusan) ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SCRIPT GRAFIK & FILTER MURID -->
    <script>
        // === LOGIKA FILTER DATA MURID (REALTIME) ===
        function filterTabelMurid() {
            let input = document.getElementById("cariMurid").value.toLowerCase();
            let selectJurusan = document.getElementById("cariJurusan").value.toLowerCase();
            
            let table = document.getElementById("tabelDataMurid");
            let rows = table.getElementsByClassName("row-murid");

            for (let i = 0; i < rows.length; i++) {
                let tdNama = rows[i].getElementsByTagName("td")[0];
                let tdNIS = rows[i].getElementsByTagName("td")[1];
                let tdJurusan = rows[i].getElementsByTagName("td")[4];

                if (tdNama || tdNIS || tdJurusan) {
                    let txtNama = tdNama.textContent || tdNama.innerText;
                    let txtNIS = tdNIS.textContent || tdNIS.innerText;
                    let txtJurusan = tdJurusan ? (tdJurusan.textContent || tdJurusan.innerText) : "";

                    let matchTeks = txtNama.toLowerCase().indexOf(input) > -1 || txtNIS.toLowerCase().indexOf(input) > -1;
                    let matchJurusan = selectJurusan === "" || txtJurusan.toLowerCase().indexOf(selectJurusan) > -1;

                    if (matchTeks && matchJurusan) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }

        // === LOGIKA GRAFIK TUGAS ===
        document.addEventListener("DOMContentLoaded", function() {
            const dataStatistik = <?= json_encode($stats_tugas) ?>;
            const ctx = document.getElementById('tugasChart').getContext('2d');
            
            const defaultData = [dataStatistik['bulan'].tepat, dataStatistik['bulan'].telat, dataStatistik['bulan'].dinilai];
            const tugasChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Tepat Waktu', 'Terlambat', 'Sudah Dinilai'],
                    datasets: [{ label: 'Jumlah Tugas/Murid', data: defaultData, backgroundColor: ['#F88484', '#F5D675', '#5D8ACA'], borderRadius: 4, barPercentage: 0.6 }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(47, 64, 47, 0.9)', titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 14 }, bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 13 }, padding: 12 }
                    },
                    scales: {
                        x: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#E8F0E8' }, ticks: { font: { family: "'Plus Jakarta Sans', sans-serif" }, color: '#6D8C6D', stepSize: 1 } },
                        y: { grid: { display: false }, ticks: { font: { family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' }, color: '#2F402F' } }
                    }
                }
            });

            document.getElementById('filterPeriode').addEventListener('change', function() {
                const p = this.value;
                tugasChart.data.datasets[0].data = [dataStatistik[p].tepat, dataStatistik[p].telat, dataStatistik[p].dinilai];
                tugasChart.update();
            });
        });
    </script>

  <!-- ================= HALAMAN PROFIL WAKA ================= -->
  <?php elseif ($page === 'profil'): ?>
    <div class="card">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800;">⚙️ Pengaturan Profil Waka</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Perbarui nama, email, dan kata sandi untuk akun administrator Anda.</p>
      
      <form action="waka.php?page=profil" method="POST">
        <input type="hidden" name="update_profil" value="1">
        
        <div class="form-grid">
          <div><label>Nama Lengkap</label><input type="text" name="nama" value="<?= e($data_waka['nama']) ?>" required></div>
          <div><label>Alamat Email</label><input type="email" name="email" value="<?= e($data_waka['email']) ?>" required></div>
        </div>
        
        <div class="form-grid">
          <div><label>Password Lama</label><input type="password" name="password_lama" placeholder="Masukkan jika ingin mengubah password..."></div>
          <div><label>Password Baru</label><input type="password" name="password_baru" placeholder="Minimal 6 karakter..."></div>
        </div>
        
        <button type="submit" class="btn" style="margin-top:10px;">💾 Simpan Perubahan Profil</button>
      </form>
    </div>

  <?php endif; ?>
</main>

<script>
function toggleWakaMenu() { document.getElementById("wakaMenu").classList.toggle("show"); }
window.onclick = function(event) {
    if (!event.target.closest('.dropdown')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
        }
    }
}
</script>

</body>
</html>
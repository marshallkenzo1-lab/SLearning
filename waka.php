<?php
/**
 * SLearning - Dashboard Waka / Superadmin
 * Fitur: Broadcast Pengumuman (Validasi Tanpa Angka & Simbol), Pantau Report, Statistik
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

/* =========================
   PROSES FORM (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. BUAT PENGUMUMAN BARU DENGAN VALIDASI KETAT
    if (isset($_POST['buat_pengumuman'])) {
        $judul = $_POST['judul'] ?? '';
        $isi = $_POST['isi'] ?? '';
        $target = $_POST['target'] ?? 'semua';
        
        // VALIDASI: Hanya boleh HURUF (a-z, A-Z), SPASI, dan ENTER (\n \r)
        // Angka dan Simbol (titik, koma, tanda tanya, dll) akan ditolak!
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
    
    // 3. TANDAI REPORT SELESAI (HAPUS REPORT)
    if (isset($_POST['selesaikan_report'])) {
        $id_report = (int)$_POST['id_report'];
        mysqli_query($koneksi, "DELETE FROM report_murid WHERE id = $id_report");
        flash_waka('Laporan masalah telah diselesaikan dan dihapus dari daftar.', 'success');
        header("Location: waka.php?page=report"); exit;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Waka - SLearning</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root {
    /* TEMA: HIJAU SAGE & SOFT YELLOW */
    --waka-bg: #F4F7F5; /* Background body */
    --waka-card: #FFFFFF;
    
    /* Palet Sage Green */
    --sage-dark: #2F402F; 
    --sage-base: #577357;
    --sage-muted: #6D8C6D;
    --sage-light: #E8F0E8;
    --sage-border: #D1E0D1;

    /* Palet Soft Yellow */
    --yellow-soft: #FFF8D6;
    --yellow-border: #F2E7B5;
    --yellow-accent: #E5C05C;

    --waka-text: var(--sage-dark);
    --waka-accent: var(--sage-base);
    --line: var(--sage-border);
}

* { box-sizing:border-box; margin:0; padding:0; } 
body { background:var(--waka-bg); color:var(--waka-text); font-family:'Plus Jakarta Sans',sans-serif; padding-bottom:60px; }

/* HEADER & NAVIGASI */
.header { background:linear-gradient(180deg, var(--yellow-soft), #fff); border-bottom:1px solid var(--yellow-border); position:sticky; top:0; z-index:10; }
.header-top { max-width:1150px; margin:auto; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; }
.logo { font-size:22px; font-weight:800; color:var(--sage-dark); text-decoration:none; display:flex; align-items:center; gap:8px; }
.logo span { font-size:11px; background:var(--sage-dark); padding:4px 10px; border-radius:30px; color:var(--yellow-soft); letter-spacing:0.5px; }
.nav { max-width:1150px; margin:auto; padding:0 16px; display:flex; gap:5px; overflow-x:auto; }
.nav a { padding:13px 20px; font-size:13px; font-weight:700; color:var(--sage-muted); text-decoration:none; white-space:nowrap; border-radius:10px 10px 0 0; transition:0.3s; }
.nav a.active, .nav a:hover { background:#fff; color:var(--sage-dark); border-bottom:3px solid var(--sage-base); }
.container { max-width:1100px; margin:30px auto; padding:0 22px; }

/* ALERT & NOTIF */
.alert { padding:16px 20px; border-radius:12px; margin-bottom:24px; font-weight:600; display:flex; align-items:center; gap:12px; font-size:14px; }
.alert.success { background:var(--sage-light); color:var(--sage-dark); border:1px solid var(--sage-muted); }
.alert.error { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; }

/* CARD & TOMBOL UMUM */
.card { background:var(--waka-card); border:1px solid var(--line); border-radius:20px; padding:32px; margin-bottom:24px; box-shadow:0 8px 24px rgba(109,140,109,0.06); }
label { display:block; font-size:13px; font-weight:700; margin-bottom:8px; color:var(--sage-dark); }
input, select, textarea { width:100%; padding:16px; border:1px solid var(--sage-border); border-radius:12px; background:var(--waka-bg); font-family:inherit; font-size:14px; margin-bottom:20px; transition:0.3s; }
input:focus, textarea:focus { outline:none; border-color:var(--sage-base); background:#fff; box-shadow:0 0 0 4px rgba(109,140,109,0.1); }
.btn { background:var(--sage-base); color:#fff; border:none; padding:16px 28px; border-radius:30px; font-size:14px; font-weight:800; cursor:pointer; transition:0.3s; box-shadow:0 4px 12px rgba(87,115,87,0.2); }
.btn:hover { background:var(--sage-dark); transform:translateY(-2px); }
.btn-hapus { background:#FFEBEE; color:#C62828; border:1px solid #FFCDD2; padding:10px 18px; border-radius:30px; font-size:12px; font-weight:800; cursor:pointer; transition:0.2s; }
.btn-hapus:hover { background:#FFCDD2; }

/* ==========================================================================
   UI KHUSUS: BERANDA WAKA (SAGE GREEN & SOFT YELLOW)
   ========================================================================== */
.waka-hero {
    background: linear-gradient(135deg, var(--sage-base) 0%, var(--sage-dark) 100%);
    border-radius: 24px;
    padding: 48px 40px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    box-shadow: 0 16px 40px rgba(47, 64, 47, 0.2);
    position: relative;
    overflow: hidden;
}
.waka-hero::before { /* Aksen dekoratif melingkar kuning lembut */
    content: ''; position: absolute; right: -5%; top: -30%;
    width: 350px; height: 350px; background: var(--yellow-soft);
    border-radius: 50%; opacity: 0.15;
}
.waka-hero::after { /* Aksen dekoratif kedua */
    content: ''; position: absolute; left: -10%; bottom: -40%;
    width: 250px; height: 250px; background: #fff;
    border-radius: 50%; opacity: 0.05;
}
.waka-hero-content { position: relative; z-index: 2; max-width: 600px; }
.waka-hero h1 { color: var(--yellow-soft); font-size: 34px; margin: 0 0 12px 0; font-weight: 800; letter-spacing: -0.5px; }
.waka-hero p { font-size: 15px; opacity: 0.9; margin: 0; line-height: 1.6; color: var(--sage-light); }
.waka-hero-btn { 
    background: var(--yellow-soft); color: var(--sage-dark); 
    padding: 12px 24px; border-radius: 30px; text-decoration: none; 
    font-weight: 800; font-size: 14px; display: inline-block; 
    margin-top: 24px; transition: 0.3s; border: 2px solid transparent;
}
.waka-hero-btn:hover { background: #fff; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.waka-hero-icon { font-size: 100px; position: relative; z-index: 2; opacity: 0.9; margin-right: 40px; }

/* Grid Statistik Interaktif */
.grid-stat { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
.stat-box { 
    background: #fff; border: 1px solid var(--sage-border); 
    border-radius: 20px; padding: 24px; display: flex; 
    align-items: center; gap: 20px; transition: 0.3s; 
    box-shadow: 0 4px 15px rgba(109,140,109,0.05); cursor: default;
}
.stat-box:hover { transform: translateY(-5px); border-color: var(--sage-muted); box-shadow: 0 12px 25px rgba(109,140,109,0.12); }
.stat-icon { 
    width: 64px; height: 64px; background: var(--yellow-soft); 
    color: var(--sage-dark); border-radius: 18px; display: flex; 
    align-items: center; justify-content: center; font-size: 28px; 
    border: 1px dashed var(--yellow-accent);
}
.stat-box h3 { margin: 0; font-size: 32px; color: var(--sage-dark); font-weight: 800; }
.stat-box p { margin: 4px 0 0 0; font-size: 13px; color: var(--sage-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}

/* Quick Actions Section */
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.quick-btn {
    background: var(--sage-light); border: 1px solid var(--sage-border);
    padding: 16px; border-radius: 16px; text-decoration: none;
    color: var(--sage-dark); font-weight: 800; font-size: 14px;
    display: flex; align-items: center; gap: 12px; transition: 0.3s;
}
.quick-btn:hover { background: var(--yellow-soft); border-color: var(--yellow-border); transform: translateX(5px); }


/* UI KHUSUS PENGUMUMAN */
.announcement-card { background: #FFFFFF; border: 1px solid var(--yellow-border); border-radius: 20px; padding: 28px; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(229, 192, 92, 0.08); position: relative; overflow: hidden; }
.announcement-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 8px; background: var(--yellow-accent); }
.announcement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.announcement-title { font-size: 19px; font-weight: 800; color: var(--sage-dark); margin: 0; }
.announcement-meta { font-size: 12px; color: var(--sage-muted); font-weight: 700; display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.announcement-body { font-size: 14.5px; color: var(--sage-dark); line-height: 1.7; background: var(--yellow-soft); padding: 20px; border-radius: 12px; border: 1px dashed var(--yellow-border); margin-bottom: 16px; }
.badge-target { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.bg-semua { background: var(--sage-light); color: var(--sage-dark); border: 1px solid var(--sage-border); }
.bg-guru { background: #E3F2FD; color: #1565C0; border: 1px solid #BBDEFB; }
.bg-murid { background: #F3E5F5; color: #6A1B9A; border: 1px solid #E1BEE7; }

@media (max-width: 768px) {
    .waka-hero { flex-direction: column-reverse; text-align: center; padding: 32px 24px; }
    .waka-hero-icon { margin: 0 0 20px 0; font-size: 80px; }
    .waka-hero h1 { font-size: 28px; }
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
      <a href="logout.php" onclick="return confirm('Keluar dari panel Waka?')" style="background:#fff; border:1px solid var(--yellow-border); padding:10px 20px; border-radius:30px; font-size:12px; font-weight:800; color:#C62828; text-decoration:none; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">Keluar</a>
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
    
    <!-- HERO SECTION SAGE GREEN & SOFT YELLOW -->
    <div class="waka-hero">
      <div class="waka-hero-content">
        <h1>Selamat Datang, <?= e($nama_waka) ?>!</h1>
        <p>Kelola ekosistem SLearning dari satu tempat. Pantau perkembangan kelas, data pengguna, tindak lanjuti laporan guru, hingga siarkan pengumuman resmi tanpa hambatan.</p>
        <a href="waka.php?page=pengumuman" class="waka-hero-btn">📢 Siarkan Informasi Baru</a>
      </div>
      <div class="waka-hero-icon">
        🏫
      </div>
    </div>

    <!-- STATISTIK INTERAKTIF -->
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

    <!-- AKSES CEPAT -->
    <h3 style="font-size:18px; color:var(--sage-dark); margin-bottom:16px;">⚡ Akses Cepat</h3>
    <div class="quick-actions">
        <a href="waka.php?page=report" class="quick-btn">
            🔍 Cek Daftar Report
        </a>
        <a href="waka.php?page=pengguna" class="quick-btn">
            👥 Kelola Data Pengguna
        </a>
    </div>


  <!-- ================= HALAMAN PENGUMUMAN ================= -->
  <?php elseif ($page === 'pengumuman'): ?>
    <div class="card">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800;">📢 Buat Informasi & Pengumuman Baru</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Informasi ini akan langsung muncul di halaman beranda Guru dan Murid. <strong>Perhatian: Angka dan simbol dilarang keras di formulir ini!</strong></p>
      
      <form method="POST">
        <label>Judul Informasi (Hanya Huruf & Spasi)</label>
        <input type="text" name="judul" placeholder="contoh  PEMBERITAHUAN LIBUR SEMESTER" required>
        
        <label>Isi Informasi / Pesan (Hanya Huruf & Spasi)</label>
        <textarea name="isi" rows="4" placeholder="contoh  diberitahukan kepada seluruh siswa dan guru bahwa libur semester akan dimulai minggu depan harap persiapkan diri dengan baik terima kasih" required></textarea>
        
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
      <div class="card" style="text-align:center; color:var(--sage-muted); font-weight:700;">Belum ada informasi yang disiarkan.</div>
    <?php else: while ($p = mysqli_fetch_assoc($q_pengumuman)): 
        $kelas_badge = 'bg-semua';
        $teks_badge = 'GURU & MURID';
        if ($p['target'] === 'guru') { $kelas_badge = 'bg-guru'; $teks_badge = 'KHUSUS GURU'; }
        if ($p['target'] === 'murid') { $kelas_badge = 'bg-murid'; $teks_badge = 'KHUSUS MURID'; }
    ?>
      <div class="announcement-card">
        <div class="announcement-header">
          <div>
            <h4 class="announcement-title"><?= e($p['judul']) ?></h4>
            <div class="announcement-meta">
              <span>📅 <?= tanggal_id($p['waktu']) ?></span>
              <span class="badge-target <?= $kelas_badge ?>"><?= $teks_badge ?></span>
            </div>
          </div>
          <form method="POST" onsubmit="return confirm('Tarik / Hapus pengumuman ini?');">
            <input type="hidden" name="id_pengumuman" value="<?= $p['id'] ?>">
            <button type="submit" name="hapus_pengumuman" class="btn-hapus">Tarik Info</button>
          </form>
        </div>
        <div class="announcement-body">
          <?= nl2br(e($p['isi'])) ?>
        </div>
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
            $q_report = mysqli_query($koneksi, "
                SELECT r.id, r.tanggal, r.alasan, 
                       g.nama AS nama_guru, m.nama AS nama_murid 
                FROM report_murid r 
                JOIN login g ON r.guru_id = g.id 
                JOIN login m ON r.murid_id = m.id 
                ORDER BY r.tanggal DESC
            ");
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
                    <input type="hidden" name="id_report" value="<?= $r['id'] ?>">
                    <button type="submit" name="selesaikan_report" class="btn-hapus" style="background:#E8F5E9; color:#2E7D32; border-color:#C8E6C9;">✔ Selesai</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>


  <!-- ================= HALAMAN DATA PENGGUNA ================= -->
  <?php elseif ($page === 'pengguna'): ?>
    <div class="card">
      <h2 style="margin-top:0; color:var(--sage-dark); font-weight:800;">👥 Rekap Seluruh Pengguna</h2>
      <p style="color:var(--sage-muted); font-size:13px; margin-bottom:28px;">Daftar seluruh akun yang terdaftar di SLearning.</p>
      
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
          <thead>
            <tr style="background:var(--sage-light); color:var(--sage-dark);">
              <th style="padding:16px; border-radius:12px 0 0 12px;">Nama Lengkap</th>
              <th style="padding:16px;">Username</th>
              <th style="padding:16px;">Email</th>
              <th style="padding:16px; border-radius:0 12px 12px 0;">Jenis Akun</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_user = mysqli_query($koneksi, "SELECT nama, username, email, jenis FROM login WHERE jenis IN ('guru', 'murid') ORDER BY jenis ASC, nama ASC");
            if (mysqli_num_rows($q_user) == 0):
            ?>
              <tr><td colspan="4" style="padding:30px; text-align:center; color:var(--sage-muted); font-weight:700;">Belum ada pengguna terdaftar.</td></tr>
            <?php else: while ($u = mysqli_fetch_assoc($q_user)): ?>
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:16px; font-weight:800; color:var(--sage-dark);"><?= e($u['nama']) ?></td>
                <td style="padding:16px; color:var(--sage-muted); font-weight:600;"><?= e($u['username']) ?></td>
                <td style="padding:16px; color:var(--sage-muted);"><?= e($u['email'] ?: '-') ?></td>
                <td style="padding:16px;">
                  <?php if($u['jenis'] === 'guru'): ?>
                    <span class="badge-target bg-guru">GURU</span>
                  <?php else: ?>
                    <span class="badge-target bg-murid">MURID</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>
</main>
</body>
</html>
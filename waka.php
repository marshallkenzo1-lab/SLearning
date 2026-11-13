<?php
/**
 * SLearning - Dashboard Waka / Superadmin
 * Fitur: Broadcast Pengumuman, Pantau Report, Statistik, Grafik Tugas, Filter Murid, Profil, Notifikasi Report Baru
 * UI: Tema Hijau Sage & Soft Yellow (Re-design & Bug Fix)
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
   MEMASTIKAN TABEL PENGUMUMAN ADA (AUTO-CREATE JIKA BELUM ADA)
   Mencegah Fatal Error mysqli_num_rows()
   ========================= */
mysqli_query($koneksi, "
CREATE TABLE IF NOT EXISTS pengumuman (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    isi TEXT NOT NULL,
    target ENUM('semua','guru','murid') DEFAULT 'semua',
    waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

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
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('j', $waktu).' '.$bulan[(int)date('n', $waktu)].' '.date('Y', $waktu);
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
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $target = $_POST['target'] ?? 'semua';
        
        if (empty($judul) || empty($isi)) {
            flash_waka('Judul dan isi pengumuman tidak boleh kosong!', 'error');
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO pengumuman (judul, isi, target) VALUES (?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sss", $judul, $isi, $target);
                if (mysqli_stmt_execute($stmt)) {
                    flash_waka('Pengumuman resmi berhasil disiarkan ke '.$target.'.', 'success');
                } else {
                    flash_waka('Terjadi kesalahan saat menyimpan pengumuman.', 'error');
                }
                mysqli_stmt_close($stmt);
            } else {
                flash_waka('Gagal menyiapkan query database.', 'error');
            }
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

        if ($nama_baru !== '') {
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
            flash_waka('Nama tidak boleh kosong.', 'error');
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

// Hitung Laporan Baru (Cek apakah tabel report_murid ada)
$q_cek_report = mysqli_query($koneksi, "SHOW TABLES LIKE 'report_murid'");
$total_laporan_baru = 0;
if (mysqli_num_rows($q_cek_report) > 0) {
    $res_report = mysqli_query($koneksi, "SELECT COUNT(*) c FROM report_murid");
    if ($res_report) {
        $total_laporan_baru = mysqli_fetch_assoc($res_report)['c'] ?? 0;
    }
}

// Statistik Singkat
$stat = [
    'guru' => @mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM login WHERE jenis='guru'"))['c'] ?? 0,
    'murid' => @mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM login WHERE jenis='murid'"))['c'] ?? 0,
    'kelas' => @mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM kelas"))['c'] ?? 0,
    'report' => $total_laporan_baru
];

// Data Grafik Pengerjaan Tugas
$stats_tugas = [
    'minggu' => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0],
    'bulan'  => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0],
    'semester' => ['tepat' => 0, 'telat' => 0, 'dinilai' => 0]
];

if ($page === 'pengguna') {
    function getTaskStats($koneksi, $interval) {
        // Cek apakah tabel ada
        $q_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'pengumpulan_tugas'");
        if (mysqli_num_rows($q_cek) > 0) {
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
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');
:root {
    --waka-bg: #f9fbf9; 
    --waka-card: #FFFFFF;
    --sage-dark: #1E2D24; 
    --sage-base: #3A5A40;
    --sage-muted: #588157;
    --sage-light: #A3B18A;
    --sage-pale: #DAD7CD;
    --yellow-soft: #FFF3B0;
    --yellow-border: #E0E1DD;
    --waka-text: var(--sage-dark);
}

* { box-sizing:border-box; margin:0; padding:0; } 
body { background:var(--waka-bg); color:var(--waka-text); font-family:'Outfit', sans-serif; padding-bottom:60px; -webkit-font-smoothing: antialiased; }

/* HEADER & NAVIGASI */
.header { background:#fff; border-bottom:1px solid rgba(0,0,0,0.06); position:sticky; top:0; z-index:20; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
.header-top { max-width:1200px; margin:auto; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; }
.logo { font-size:24px; font-weight:800; color:var(--sage-dark); text-decoration:none; display:flex; align-items:center; gap:10px; }
.logo span { font-size:10px; background:var(--sage-dark); padding:4px 10px; border-radius:30px; color:var(--yellow-soft); letter-spacing:1px; text-transform: uppercase; }

/* DROPDOWN MENU PROFIL */
.dropdown { position: relative; display: inline-block; }
.dropdown-toggle { background: #f4f5f4; border: none; padding: 10px; border-radius: 50%; color: var(--sage-dark); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.dropdown-toggle:hover { background: var(--sage-pale); }
.dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: #fff; min-width: 200px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border-radius: 16px; border: 1px solid rgba(0,0,0,0.04); z-index: 100; overflow: hidden; padding: 8px; }
.dropdown-menu.show { display: block; animation: dropFade 0.2s ease; }
@keyframes dropFade { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.dropdown-menu a { color: var(--sage-dark); padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; border-radius: 10px; transition: 0.2s; }
.dropdown-menu a:hover { background-color: var(--waka-bg); color: var(--sage-base); }
.dropdown-menu a.logout-btn { color: #d32f2f; margin-top: 4px; }
.dropdown-menu a.logout-btn:hover { background-color: #ffebee; }

/* NOTIFIKASI BELL */
.notification-bell { position: relative; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: #fff3e0; color: #ef6c00; text-decoration: none; transition: 0.2s; }
.notification-bell:hover { background: #ffe0b2; transform: scale(1.05); }
.notification-badge { position: absolute; top: -2px; right: -2px; background: #d32f2f; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; border: 2px solid #fff; }

.nav { max-width:1200px; margin:auto; padding:0 24px; display:flex; gap:24px; overflow-x:auto; }
.nav a { padding:16px 0; font-size:14px; font-weight:600; color: #8e9e90; text-decoration:none; white-space:nowrap; position: relative; transition:0.3s; }
.nav a:hover { color: var(--sage-base); }
.nav a.active { color: var(--sage-dark); font-weight: 700; }
.nav a.active::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: var(--sage-base); border-radius: 3px 3px 0 0; }

.container { max-width:1150px; margin:40px auto; padding:0 24px; }

/* ALERT & CARD */
.alert { padding:16px 20px; border-radius:14px; margin-bottom:30px; font-weight:500; display:flex; align-items:center; gap:12px; font-size:14px; animation: slideIn 0.4s ease; }
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.alert.success { background:#edf7ed; color:#1e4620; border:1px solid #c8e6c9; }
.alert.error { background:#fdeded; color:#5f2120; border:1px solid #ffcdd2; }

.card { background:var(--waka-card); border:1px solid rgba(0,0,0,0.05); border-radius:24px; padding:36px; margin-bottom:30px; box-shadow:0 10px 40px rgba(0,0,0,0.02); transition: 0.3s; }
.card:hover { box-shadow:0 15px 50px rgba(0,0,0,0.04); }

/* FORMS */
label { display:block; font-size:13px; font-weight:600; margin-bottom:8px; color: var(--sage-muted); letter-spacing: 0.5px; }
input, select, textarea { width:100%; padding:14px 18px; border:1.5px solid var(--yellow-border); border-radius:14px; background:#fafafa; font-family:inherit; font-size:15px; margin-bottom:24px; transition:0.3s; color: var(--sage-dark); }
input:focus, textarea:focus, select:focus { outline:none; border-color:var(--sage-light); background:#fff; box-shadow:0 0 0 4px rgba(163, 177, 138, 0.15); }
.btn { background:var(--sage-base); color:#fff; border:none; padding:16px 32px; border-radius:14px; font-size:15px; font-weight:600; cursor:pointer; transition:0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(58, 90, 64, 0.2); }
.btn:hover { background:var(--sage-dark); transform:translateY(-2px); box-shadow: 0 8px 25px rgba(30, 45, 36, 0.25); }
.btn-hapus { background:#fff; color:#d32f2f; border:1px solid #ffcdd2; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; display: inline-flex; align-items: center; gap: 6px; }
.btn-hapus:hover { background:#ffebee; border-color: #ef9a9a; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

/* HERO WAKA (REDESIGN) */
.waka-hero { background: var(--sage-dark); border-radius: 28px; padding: 50px 48px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; position: relative; overflow: hidden; box-shadow: 0 20px 50px rgba(30, 45, 36, 0.15); }
.waka-hero::before { content: ''; position: absolute; right: -5%; top: -50%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(163,177,138,0.2) 0%, transparent 70%); border-radius: 50%; }
.waka-hero::after { content: ''; position: absolute; left: -10%; bottom: -50%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,243,176,0.1) 0%, transparent 70%); border-radius: 50%; }
.waka-hero-content { position: relative; z-index: 2; max-width: 650px; }
.waka-hero h1 { color: #fff; font-size: 38px; margin: 0 0 16px 0; font-weight: 700; letter-spacing: -0.5px; }
.waka-hero h1 span { color: var(--yellow-soft); }
.waka-hero p { font-size: 16px; opacity: 0.8; margin: 0 0 28px 0; line-height: 1.6; font-weight: 300; }
.waka-hero-btn { background: #fff; color: var(--sage-dark); padding: 14px 28px; border-radius: 14px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
.waka-hero-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
.waka-hero-date { position: absolute; top: 32px; right: 40px; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 30px; font-size: 12px; font-weight: 500; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 6px; }

/* 4 STATISTIK CARDS (REDESIGN) */
.grid-stat { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 40px; }
.stat-box { background: #fff; border: 1px solid rgba(0,0,0,0.04); border-radius: 20px; padding: 28px 24px; display: flex; flex-direction: column; transition: 0.3s; box-shadow: 0 8px 30px rgba(0,0,0,0.02); cursor: default; position: relative; overflow: hidden; }
.stat-box:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.05); }
.stat-box::after { content: ''; position: absolute; top: 0; right: 0; width: 80px; height: 80px; background: var(--sage-pale); opacity: 0.2; border-radius: 0 0 0 100%; transition: 0.3s; }
.stat-box:hover::after { transform: scale(1.2); }
.stat-icon { width: 48px; height: 48px; background: #f0f4f0; color: var(--sage-base); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 20px; }
.stat-box.danger .stat-icon { background: #ffebee; color: #d32f2f; }
.stat-box h3 { margin: 0 0 4px 0; font-size: 36px; color: var(--sage-dark); font-weight: 700; line-height: 1; }
.stat-box p { margin: 0; font-size: 13px; color: var(--sage-muted); font-weight: 500; }

/* BERANDA GRID - 2 COLUMN (REDESIGN) */
.beranda-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; }
.panel-section { background: #fff; border-radius: 24px; border: 1px solid rgba(0,0,0,0.04); padding: 32px; box-shadow: 0 8px 30px rgba(0,0,0,0.02); }
.panel-title { font-size: 18px; font-weight: 700; color: var(--sage-dark); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
.panel-title a { font-size: 13px; color: var(--sage-muted); font-weight: 500; text-decoration: none; padding: 6px 12px; background: #f4f5f4; border-radius: 8px; transition: 0.2s; }
.panel-title a:hover { background: var(--sage-pale); color: var(--sage-dark); }

/* LIST AKSES CEPAT */
.quick-action-list { display: flex; flex-direction: column; gap: 16px; }
.quick-action-item { display: flex; align-items: center; gap: 16px; padding: 20px; border-radius: 16px; border: 1px solid transparent; background: #f9faf9; text-decoration: none; transition: 0.3s; }
.quick-action-item:hover { background: #fff; border-color: var(--sage-light); transform: translateX(8px); box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
.quick-action-item .qa-icon { width: 48px; height: 48px; border-radius: 14px; background: #fff; color: var(--sage-base); display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
.quick-action-item .qa-text { flex: 1; }
.quick-action-item .qa-text h4 { margin: 0 0 6px 0; font-size: 15px; color: var(--sage-dark); font-weight: 600; }
.quick-action-item .qa-text p { margin: 0; font-size: 13px; color: #8e9e90; font-weight: 400; line-height: 1.4; }

/* MINI FEED (INFO & REPORT) */
.mini-feed-item { padding: 16px 0; border-bottom: 1px solid rgba(0,0,0,0.04); display: flex; gap: 16px; align-items: flex-start; }
.mini-feed-item:last-child { border-bottom: none; padding-bottom: 0; }
.mini-feed-icon { width: 40px; height: 40px; border-radius: 12px; background: #e8f5e9; color: #2e7d32; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.mini-feed-icon.report { background: #ffebee; color: #c62828; }
.mini-feed-content { flex: 1; }
.mini-feed-content h5 { margin: 0 0 6px 0; font-size: 14px; color: var(--sage-dark); font-weight: 600; line-height: 1.4; }
.mini-feed-content p { margin: 0; font-size: 12px; color: #8e9e90; }
.mini-feed-content p strong { color: var(--sage-dark); font-weight: 500; }

/* DESAIN PENGUMUMAN (REDESIGN) */
.announcement-card { background: #fff; border: 1px solid rgba(0,0,0,0.04); border-radius: 20px; padding: 32px; margin-bottom: 24px; box-shadow: 0 8px 30px rgba(0,0,0,0.02); display: flex; gap: 24px; transition: 0.3s; }
.announcement-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.05); transform: translateY(-2px); }
.announcement-icon { width: 56px; height: 56px; background: #fdf6e3; border: 1px solid #fce8b2; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
.announcement-content { flex: 1; }
.announcement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.announcement-title { font-size: 18px; font-weight: 700; color: var(--sage-dark); margin: 0 0 8px 0; }
.announcement-meta { font-size: 12px; color: var(--sage-muted); font-weight: 500; display: flex; align-items: center; gap: 12px; }
.badge-target { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.5px; }
.bg-semua { background: #e8f5e9; color: #2e7d32; }
.bg-guru { background: #e3f2fd; color: #1565c0; }
.bg-murid { background: #f3e5f5; color: #6a1b9a; }
.announcement-body { font-size: 15px; color: #4a5d4e; line-height: 1.6; margin-top: 16px; background: #f9faf9; padding: 20px; border-radius: 12px; }

/* TABEL MODERN */
.table-container { overflow-x: auto; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); }
table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; background: #fff; }
th { padding: 18px 24px; background: #f9faf9; color: var(--sage-muted); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.05); }
td { padding: 18px 24px; color: var(--sage-dark); border-bottom: 1px solid rgba(0,0,0,0.03); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fcfdfc; }

/* CUSTOM PENCARIAN MURID */
.search-bar-wrapper { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 250px; margin-bottom: 0; border-radius: 14px; }
.search-select { width: 300px; margin-bottom: 0; border-radius: 14px; cursor: pointer; }

@media (max-width: 768px) {
    .waka-hero { padding: 40px 32px; text-align: center; }
    .waka-hero-date { position: relative; top: 0; right: 0; display: inline-flex; margin-bottom: 20px; }
    .waka-hero-content { margin: 0 auto; }
    .form-grid { grid-template-columns: 1fr; }
    .beranda-grid { grid-template-columns: 1fr; }
    .search-select { width: 100%; }
    .announcement-card { flex-direction: column; gap: 16px; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="waka.php" class="logo">
      SLearning <span>WAKA PANEL</span>
    </a>
    <div style="display:flex; align-items:center; gap:20px;">
      
      <!-- IKON BEL NOTIFIKASI Laporan Masuk -->
      <a href="waka.php?page=report" class="notification-bell" title="Laporan Masalah Baru">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <?php if($total_laporan_baru > 0): ?>
          <span class="notification-badge"><?= $total_laporan_baru ?></span>
        <?php endif; ?>
      </a>

      <div style="display: flex; flex-direction: column; align-items: flex-end;">
          <span style="font-weight:600; font-size:14px; color:var(--sage-dark); line-height:1.2;">Hi, <?= e($nama_waka) ?></span>
          <span style="font-size:11px; color:var(--sage-muted);">Administrator</span>
      </div>
      
      <!-- DROPDOWN MENU PROFIL & KELUAR -->
      <div class="dropdown">
        <button class="dropdown-toggle" onclick="toggleWakaMenu()" title="Pengaturan Akun">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </button>
        <div id="wakaMenu" class="dropdown-menu">
            <a href="waka.php?page=profil">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Pengaturan Profil
            </a>
            <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin keluar dari panel admin?')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Keluar Akun
            </a>
        </div>
      </div>
    </div>
  </div>
  <nav class="nav">
    <a href="waka.php?page=beranda" class="<?= $page==='beranda'?'active':'' ?>">Beranda</a>
    <a href="waka.php?page=pengumuman" class="<?= $page==='pengumuman'?'active':'' ?>">Pengumuman</a>
    <a href="waka.php?page=report" class="<?= $page==='report'?'active':'' ?>">Laporan Masalah <?= $stat['report'] > 0 ? "<span style='background:#d32f2f; color:#fff; border-radius:10px; padding:2px 6px; font-size:10px;'>$stat[report]</span>" : '' ?></a>
    <a href="waka.php?page=pengguna" class="<?= $page==='pengguna'?'active':'' ?>">Data Pengguna</a>
  </nav>
</header>

<main class="container">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['tipe'] ?>">
      <?= $flash['tipe'] === 'success' ? '✅' : '⚠️' ?> <?= e($flash['pesan']) ?>
    </div>
  <?php endif; ?>

  <!-- ================= HALAMAN BERANDA ================= -->
  <?php if ($page === 'beranda'): ?>
    
    <div class="waka-hero">
        <div class="waka-hero-date">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <?= tanggal_sekarang() ?>
        </div>
      <div class="waka-hero-content">
        <h1>Selamat Datang, <span><?= e($nama_waka) ?></span></h1>
        <p>Anda berada di pusat kendali SLearning. Kelola data siswa dan guru, tindak lanjuti laporan masalah, serta sebarkan pengumuman penting secara instan ke seluruh warga sekolah.</p>
        <a href="waka.php?page=pengumuman" class="waka-hero-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            Buat Pengumuman Baru
        </a>
      </div>
    </div>

    <!-- 4 KOTAK STATISTIK -->
    <div class="grid-stat">
      <div class="stat-box">
        <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </div>
        <div><h3><?= $stat['guru'] ?></h3><p>Total Guru Terdaftar</p></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div><h3><?= $stat['murid'] ?></h3><p>Total Murid Aktif</p></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        </div>
        <div><h3><?= $stat['kelas'] ?></h3><p>Ruang Kelas Berjalan</p></div>
      </div>
      <div class="stat-box <?= $stat['report'] > 0 ? 'danger' : '' ?>" style="<?= $stat['report'] > 0 ? 'border-color:#ffcdd2;' : '' ?>">
        <div class="stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
        </div>
        <div>
          <h3 style="<?= $stat['report'] > 0 ? 'color:#c62828;' : '' ?>"><?= $stat['report'] ?></h3>
          <p style="<?= $stat['report'] > 0 ? 'color:#c62828;' : '' ?>">Laporan Masalah Baru</p>
        </div>
      </div>
    </div>

    <!-- PANEL BERANDA LANJUTAN (Split View) -->
    <div class="beranda-grid">
        <!-- Kolom 1: Akses Cepat -->
        <div class="panel-section">
            <div class="panel-title">
                Akses Cepat
            </div>
            <div class="quick-action-list">
                <a href="waka.php?page=report" class="quick-action-item">
                    <div class="qa-icon" style="color: #ef6c00;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                    <div class="qa-text">
                        <h4>Tinjau Laporan Masalah</h4>
                        <p>Cek keluhan pelanggaran dari guru atau siswa.</p>
                    </div>
                </a>
                <a href="waka.php?page=pengguna" class="quick-action-item">
                    <div class="qa-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div class="qa-text">
                        <h4>Manajemen Pengguna</h4>
                        <p>Lihat rekap data guru, murid, dan performa akademik.</p>
                    </div>
                </a>
                <a href="waka.php?page=profil" class="quick-action-item">
                    <div class="qa-icon" style="color: #6a1b9a;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                    <div class="qa-text">
                        <h4>Pengaturan Keamanan</h4>
                        <p>Perbarui identitas dan ubah kata sandi administrator.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Kolom 2: Mini Feed (Aktivitas Terakhir) -->
        <div class="panel-section">
            <div class="panel-title">
                Pembaruan Terkini
                <a href="waka.php?page=pengumuman">Lihat Semua</a>
            </div>
            
            <div style="margin-bottom: 24px;">
                <p style="font-size:12px; color:var(--sage-light); font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Pengumuman Sistem</p>
                <?php
                // Cek aman untuk pengumuman
                $q_cek_pengumuman = mysqli_query($koneksi, "SHOW TABLES LIKE 'pengumuman'");
                if (mysqli_num_rows($q_cek_pengumuman) > 0) {
                    $q_minifeed_info = mysqli_query($koneksi, "SELECT judul, waktu FROM pengumuman ORDER BY waktu DESC LIMIT 2");
                    if ($q_minifeed_info && mysqli_num_rows($q_minifeed_info) > 0) {
                        while($finfo = mysqli_fetch_assoc($q_minifeed_info)) {
                            echo '<div class="mini-feed-item">
                                    <div class="mini-feed-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    </div>
                                    <div class="mini-feed-content">
                                        <h5>'.e($finfo['judul']).'</h5>
                                        <p>Disiarkan pada '.tanggal_id($finfo['waktu']).'</p>
                                    </div>
                                  </div>';
                        }
                    } else {
                        echo '<div style="padding:16px; background:#f9faf9; border-radius:12px; text-align:center; font-size:13px; color:var(--sage-light);">Belum ada pengumuman disiarkan.</div>';
                    }
                } else {
                    echo '<div style="padding:16px; background:#f9faf9; border-radius:12px; text-align:center; font-size:13px; color:var(--sage-light);">Tabel pengumuman sedang dipersiapkan...</div>';
                }
                ?>
            </div>

            <div>
                <p style="font-size:12px; color:var(--sage-light); font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Laporan Terakhir</p>
                <?php
                if (mysqli_num_rows($q_cek_report) > 0) {
                    $q_minifeed_report = mysqli_query($koneksi, "SELECT r.tanggal, 
                                                                (SELECT nama FROM login WHERE id = r.guru_id) as pelapor, 
                                                                (SELECT nama FROM login WHERE id = r.murid_id) as terlapor 
                                                                FROM report_murid r ORDER BY r.tanggal DESC LIMIT 2");
                    if ($q_minifeed_report && mysqli_num_rows($q_minifeed_report) > 0) {
                        while($frep = mysqli_fetch_assoc($q_minifeed_report)) {
                            $nama_pelapor = $frep['pelapor'] ?? 'Seseorang';
                            $nama_terlapor = $frep['terlapor'] ?? 'Pengguna Lain';
                            echo '<div class="mini-feed-item">
                                    <div class="mini-feed-icon report">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                    </div>
                                    <div class="mini-feed-content">
                                        <h5>Laporan dari '.e($nama_pelapor).'</h5>
                                        <p>Terkait <strong style="color:#c62828;">'.e($nama_terlapor).'</strong> pada '.date('d/m', strtotime($frep['tanggal'])).'</p>
                                    </div>
                                  </div>';
                        }
                    } else {
                        echo '<div style="padding:16px; background:#f9faf9; border-radius:12px; text-align:center; font-size:13px; color:var(--sage-light);">Tidak ada laporan. Semua aman.</div>';
                    }
                } else {
                     echo '<div style="padding:16px; background:#f9faf9; border-radius:12px; text-align:center; font-size:13px; color:var(--sage-light);">Modul pelaporan sedang disiapkan...</div>';
                }
                ?>
            </div>
        </div>
    </div>

  <!-- ================= HALAMAN PENGUMUMAN ================= -->
  <?php elseif ($page === 'pengumuman'): ?>
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
        <div>
            <h2 style="margin:0 0 8px 0; color:var(--sage-dark); font-weight:800; font-size:28px;">Pengumuman Sistem</h2>
            <p style="color:var(--sage-muted); font-size:15px; margin:0;">Siarkan informasi penting ke beranda Guru dan Siswa.</p>
        </div>
    </div>

    <div class="card" style="border-top: 4px solid var(--sage-base);">
      <form method="POST">
        <div class="form-grid">
            <div>
                <label>Judul Informasi</label>
                <input type="text" name="judul" placeholder="Contoh: PEMBERITAHUAN UJIAN AKHIR" required>
            </div>
            <div>
                <label>Target Penerima</label>
                <select name="target" required>
                <option value="semua">📢 Semua (Guru & Murid)</option>
                <option value="guru">👨‍🏫 Khusus Guru Saja</option>
                <option value="murid">👩‍🎓 Khusus Murid Saja</option>
                </select>
            </div>
        </div>
        
        <label>Isi Pesan / Keterangan</label>
        <textarea name="isi" rows="5" placeholder="Ketik detail pengumuman di sini dengan jelas..." required></textarea>
        
        <div style="display:flex; justify-content:flex-end;">
            <button type="submit" name="buat_pengumuman" class="btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"></path><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Siarkan Pengumuman
            </button>
        </div>
      </form>
    </div>

    <h3 style="margin:40px 0 20px; color:var(--sage-dark); font-weight:700; font-size:20px;">Riwayat Informasi Terkirim</h3>
    
    <div style="display: flex; flex-direction: column; gap: 0;">
    <?php
    $q_cek_pengumuman = mysqli_query($koneksi, "SHOW TABLES LIKE 'pengumuman'");
    if (mysqli_num_rows($q_cek_pengumuman) > 0) {
        $q_pengumuman = mysqli_query($koneksi, "SELECT * FROM pengumuman ORDER BY waktu DESC");
        if (mysqli_num_rows($q_pengumuman) == 0):
        ?>
        <div class="card" style="text-align:center; padding: 60px 20px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color:var(--sage-pale); margin-bottom:16px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <p style="color:var(--sage-muted); font-weight:500; font-size:16px; margin:0;">Belum ada riwayat pengumuman.</p>
        </div>
        <?php else: while ($p = mysqli_fetch_assoc($q_pengumuman)): 
            $kelas_badge = 'bg-semua'; $teks_badge = 'GURU & MURID';
            if ($p['target'] === 'guru') { $kelas_badge = 'bg-guru'; $teks_badge = 'KHUSUS GURU'; }
            if ($p['target'] === 'murid') { $kelas_badge = 'bg-murid'; $teks_badge = 'KHUSUS MURID'; }
        ?>
        <div class="announcement-card">
            <div class="announcement-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div class="announcement-content">
                <div class="announcement-header">
                    <div>
                        <h4 class="announcement-title"><?= e($p['judul']) ?></h4>
                        <div class="announcement-meta">
                            <span style="display:flex; align-items:center; gap:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> <?= tanggal_id($p['waktu']) ?></span>
                            • 
                            <span class="badge-target <?= $kelas_badge ?>"><?= $teks_badge ?></span>
                        </div>
                    </div>
                    <form method="POST" onsubmit="return confirm('Tarik / Hapus pengumuman ini dari beranda pengguna?');">
                        <input type="hidden" name="id_pengumuman" value="<?= $p['id'] ?>">
                        <button type="submit" name="hapus_pengumuman" class="btn-hapus" title="Tarik Pengumuman">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            Tarik Info
                        </button>
                    </form>
                </div>
                <div class="announcement-body">
                    <?= nl2br(e($p['isi'])) ?>
                </div>
            </div>
        </div>
        <?php endwhile; endif; 
    } else {
        echo '<div class="card" style="text-align:center; padding: 40px;">Sistem pengumuman belum aktif sepenuhnya.</div>';
    }
    ?>
    </div>

  <!-- ================= HALAMAN REPORT ================= -->
  <?php elseif ($page === 'report'): ?>
    
    <div style="margin-bottom:24px;">
        <h2 style="margin:0 0 8px 0; color:var(--sage-dark); font-weight:800; font-size:28px;">Laporan Masalah</h2>
        <p style="color:var(--sage-muted); font-size:15px; margin:0;">Pantau dan selesaikan keluhan dari pengguna aplikasi.</p>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; border-top: 4px solid #ef5350;">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Pihak Pelapor</th>
              <th>Pihak Terlapor</th>
              <th>Rincian Laporan</th>
              <th style="text-align:center;">Tindakan</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_cek_report = mysqli_query($koneksi, "SHOW TABLES LIKE 'report_murid'");
            if (mysqli_num_rows($q_cek_report) > 0) {
                $q_report = mysqli_query($koneksi, "
                    SELECT r.id, r.tanggal, r.alasan, 
                    (SELECT nama FROM login WHERE id = r.guru_id) as nama_pelapor, 
                    (SELECT jenis FROM login WHERE id = r.guru_id) as role_pelapor,
                    (SELECT nama FROM login WHERE id = r.murid_id) as nama_terlapor,
                    (SELECT jenis FROM login WHERE id = r.murid_id) as role_terlapor
                    FROM report_murid r 
                    ORDER BY r.tanggal DESC
                ");
                
                if (!$q_report || mysqli_num_rows($q_report) == 0):
                ?>
                <tr><td colspan="5" style="padding:60px 20px; text-align:center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color:var(--sage-pale); margin-bottom:16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <p style="color:var(--sage-muted); font-weight:500; font-size:16px; margin:0;">✨ Tidak ada laporan. Ekosistem aman terkendali.</p>
                </td></tr>
                <?php else: while ($r = mysqli_fetch_assoc($q_report)): 
                    $nama_pelapor = $r['nama_pelapor'] ?? 'Pengguna Terhapus';
                    $role_pelapor = $r['role_pelapor'] ?? '-';
                    $nama_terlapor = $r['nama_terlapor'] ?? 'Pengguna Terhapus';
                    $role_terlapor = $r['role_terlapor'] ?? '-';
                ?>
                <tr>
                    <td style="color:var(--sage-muted); font-weight:500;">
                        <?= date('d/m/Y', strtotime($r['tanggal'])) ?><br>
                        <span style="font-size:11px;"><?= date('H:i', strtotime($r['tanggal'])) ?> WIB</span>
                    </td>
                    <td>
                        <strong style="color:var(--sage-dark); display:block; margin-bottom:4px;"><?= e($nama_pelapor) ?></strong>
                        <span style="font-size:10px; padding:3px 8px; background:#f0f4f0; color:var(--sage-muted); border-radius:6px; font-weight:600; letter-spacing:0.5px;"><?= strtoupper(e($role_pelapor)) ?></span>
                    </td>
                    <td>
                        <strong style="color:#d32f2f; display:block; margin-bottom:4px;"><?= e($nama_terlapor) ?></strong>
                        <span style="font-size:10px; padding:3px 8px; background:#ffebee; color:#c62828; border-radius:6px; font-weight:600; letter-spacing:0.5px;"><?= strtoupper(e($role_terlapor)) ?></span>
                    </td>
                    <td style="max-width:280px; line-height:1.6;">
                        <?= e($r['alasan']) ?>
                    </td>
                    <td style="text-align:center;">
                    <form method="POST" onsubmit="return confirm('Tandai masalah ini sudah diselesaikan? Laporan akan dihapus.');">
                        <input type="hidden" name="id_report" value="<?= $r['id'] ?>">
                        <button type="submit" name="selesaikan_report" class="btn-hapus" style="background:#edf7ed; color:#2e7d32; border-color:#c8e6c9;" title="Tandai Selesai">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Selesai
                        </button>
                    </form>
                    </td>
                </tr>
                <?php endwhile; endif; 
            } else {
                echo '<tr><td colspan="5" style="padding:40px; text-align:center;">Fitur Laporan belum tersedia di database.</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- ================= HALAMAN DATA PENGGUNA ================= -->
  <?php elseif ($page === 'pengguna'): ?>
    
    <div style="margin-bottom:24px;">
        <h2 style="margin:0 0 8px 0; color:var(--sage-dark); font-weight:800; font-size:28px;">Manajemen Pengguna</h2>
        <p style="color:var(--sage-muted); font-size:15px; margin:0;">Analisis aktivitas dan kelola data warga sekolah.</p>
    </div>

    <!-- GRAFIK STATISTIK TUGAS -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 30px; gap: 16px;">
            <div>
                <h3 style="margin: 0 0 4px 0; color: var(--sage-dark); font-weight: 700; font-size: 18px;">Statistik Aktivitas Belajar</h3>
                <p style="font-size: 13px; color: var(--sage-muted); margin:0;">Grafik tingkat penyelesaian tugas siswa.</p>
            </div>
            
            <select id="filterPeriode" class="search-select" style="width: auto; padding: 10px 16px;">
                <option value="minggu">1 Minggu Terakhir</option>
                <option value="bulan" selected>1 Bulan Terakhir</option>
                <option value="semester">1 Semester Terakhir</option>
            </select>
        </div>
        
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="tugasChart"></canvas>
        </div>
    </div>

    <!-- TABEL REKAP GURU -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 40px; border-top: 4px solid #1976d2;">
      <div style="padding: 24px 32px; background: #fff; border-bottom: 1px solid rgba(0,0,0,0.05);">
          <h3 style="margin: 0; font-size: 18px; color: var(--sage-dark); font-weight: 700; display: flex; align-items: center; gap: 8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1976d2" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              Data Guru Pendamping
          </h3>
      </div>
      <div class="table-container" style="border-radius: 0; border: none;">
        <table>
          <thead>
            <tr style="background: #f8fbff;">
              <th>Nama Lengkap</th>
              <th>Username ID</th>
              <th>Kontak Email</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_guru = mysqli_query($koneksi, "SELECT * FROM login WHERE jenis = 'guru' ORDER BY nama ASC");
            if (mysqli_num_rows($q_guru) == 0):
            ?>
              <tr><td colspan="3" style="padding:40px; text-align:center; color:var(--sage-muted);">Belum ada data guru.</td></tr>
            <?php else: while ($g = mysqli_fetch_assoc($q_guru)): ?>
              <tr>
                <td style="font-weight:600;"><?= e($g['nama']) ?></td>
                <td style="color:var(--sage-muted); font-family:monospace; font-size:14px;"><?= e($g['username']) ?></td>
                <td style="color:var(--sage-muted);"><?= e($g['email'] ?: 'Tidak ada email') ?></td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TABEL REKAP MURID -->
    <div class="card" style="padding: 0; overflow: hidden; border-top: 4px solid #7b1fa2;">
      <div style="padding: 24px 32px; background: #fff; border-bottom: 1px solid rgba(0,0,0,0.05);">
          <h3 style="margin: 0 0 20px 0; font-size: 18px; color: var(--sage-dark); font-weight: 700; display: flex; align-items: center; gap: 8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7b1fa2" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              Direktori Siswa
          </h3>
          
          <div class="search-bar-wrapper">
              <input type="text" id="cariMurid" class="search-input" placeholder="🔍 Ketik nama atau NIS siswa..." onkeyup="filterTabelMurid()">
              
              <select id="cariJurusan" class="search-select" onchange="filterTabelMurid()">
                  <option value="">Semua Jurusan</option>
                  <option value="Rekayasa Perangkat Lunak">Rekayasa Perangkat Lunak</option>
                  <option value="Teknik Komputer dan Jaringan ">Teknik Komputer dan Jaringan </option>
                  <option value="Teknik Instalasi Tenaga Listrik">Teknik Instalasi Tenaga Listrik</option>
                  <option value="Teknik Jaringan Komputer dan Telekomunikasi">Teknik Jaringan Komputer dan Telekomunikasi</option>
                  <option value="Desain Komunikasi Visual">Desain Komunikasi Visual</option>
                  <option value="Manajemen Perkantoran">Manajemen Perkantoran</option>
                  <option value="Bisnis Ritel">Bisnis Ritel</option>
              </select>
          </div>
      </div>

      <div class="table-container" style="border-radius: 0; border: none;">
        <table id="tabelDataMurid">
          <thead>
            <tr style="background: #fcf4ff;">
              <th>Nama & NIS</th>
              <th>Username</th>
              <th>Jurusan</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q_murid = mysqli_query($koneksi, "SELECT * FROM login WHERE jenis = 'murid' ORDER BY nama ASC");
            if (mysqli_num_rows($q_murid) == 0):
            ?>
              <tr><td colspan="3" style="padding:40px; text-align:center; color:var(--sage-muted);">Belum ada data murid.</td></tr>
            <?php else: while ($m = mysqli_fetch_assoc($q_murid)): 
                $nis = isset($m['nis']) && !empty($m['nis']) ? $m['nis'] : '-';
                $jurusan = isset($m['jurusan']) && !empty($m['jurusan']) ? $m['jurusan'] : '-';
            ?>
              <tr class="row-murid">
                <td>
                    <strong style="color:var(--sage-dark); display:block; margin-bottom:4px;"><?= e($m['nama']) ?></strong>
                    <span style="font-size:12px; color:var(--sage-muted); font-family:monospace;"><?= e($nis) ?></span>
                </td>
                <td style="color:var(--sage-muted); font-family:monospace; font-size:14px;"><?= e($m['username']) ?></td>
                <td>
                    <span style="font-size:11px; padding:4px 10px; background:#f3e5f5; color:#6a1b9a; border-radius:20px; font-weight:600;"><?= e($jurusan) ?></span>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SCRIPT GRAFIK & FILTER MURID -->
    <script>
        function filterTabelMurid() {
            let input = document.getElementById("cariMurid").value.toLowerCase();
            let selectJurusan = document.getElementById("cariJurusan").value.toLowerCase();
            
            let table = document.getElementById("tabelDataMurid");
            let rows = table.getElementsByClassName("row-murid");

            for (let i = 0; i < rows.length; i++) {
                let tdNamaNIS = rows[i].getElementsByTagName("td")[0];
                let tdJurusan = rows[i].getElementsByTagName("td")[2];

                if (tdNamaNIS || tdJurusan) {
                    let txtNamaNIS = tdNamaNIS.textContent || tdNamaNIS.innerText;
                    let txtJurusan = tdJurusan ? (tdJurusan.textContent || tdJurusan.innerText) : "";

                    let matchTeks = txtNamaNIS.toLowerCase().indexOf(input) > -1;
                    let matchJurusan = selectJurusan === "" || txtJurusan.toLowerCase().indexOf(selectJurusan) > -1;

                    if (matchTeks && matchJurusan) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const dataStatistik = <?= json_encode($stats_tugas) ?>;
            const ctx = document.getElementById('tugasChart').getContext('2d');
            
            Chart.defaults.font.family = "'Outfit', sans-serif";
            Chart.defaults.color = '#8e9e90';
            
            const defaultData = [dataStatistik['bulan'].tepat, dataStatistik['bulan'].telat, dataStatistik['bulan'].dinilai];
            const tugasChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Selesai Tepat Waktu', 'Terlambat Kumpul', 'Telah Dinilai Guru'],
                    datasets: [{ 
                        data: defaultData, 
                        backgroundColor: ['#81c784', '#ffb74d', '#64b5f6'],
                        hoverBackgroundColor: ['#66bb6a', '#ffa726', '#42a5f5'],
                        borderRadius: 8, 
                        barPercentage: 0.5 
                    }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { 
                            backgroundColor: '#1e2d24', titleFont: { size: 14, weight: '600' }, 
                            bodyFont: { size: 14 }, padding: 16, cornerRadius: 12, displayColors: false
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f0f4f0' }, border: {display: false}, ticks: { stepSize: 1, font: {weight: '500'} } },
                        y: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: '600', size: 13 }, color: '#3a5a40' } }
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
    
    <div style="margin-bottom:24px;">
        <h2 style="margin:0 0 8px 0; color:var(--sage-dark); font-weight:800; font-size:28px;">Pengaturan Keamanan</h2>
        <p style="color:var(--sage-muted); font-size:15px; margin:0;">Kelola kredensial dan identitas akun Waka Anda.</p>
    </div>

    <div class="card" style="max-width: 700px; padding: 40px;">
      
      <form action="waka.php?page=profil" method="POST">
        <input type="hidden" name="update_profil" value="1">
        
        <h4 style="font-size: 15px; margin-bottom: 20px; color: var(--sage-dark); font-weight: 700; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Informasi Dasar
        </h4>
        
        <div class="form-grid" style="margin-bottom: 32px;">
            <div>
                <label>Nama Tampilan Profil</label>
                <input type="text" name="nama" value="<?= e($data_waka['nama']) ?>" required>
            </div>
            <div>
                <label>Email Pemulihan</label>
                <input type="email" name="email" value="<?= e($data_waka['email']) ?>" placeholder="email@sekolah.com">
            </div>
        </div>
        
        <hr style="border: none; border-top: 1px solid rgba(0,0,0,0.05); margin-bottom: 32px;">
        
        <h4 style="font-size: 15px; margin-bottom: 20px; color: var(--sage-dark); font-weight: 700; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Ubah Kata Sandi <span style="font-weight: 400; font-size: 12px; color: var(--sage-light);">(Opsional)</span>
        </h4>
        
        <div class="form-grid">
            <div>
                <label>Sandi Saat Ini</label>
                <input type="password" name="password_lama" placeholder="Ketik sandi saat ini...">
            </div>
            <div>
                <label>Sandi Baru</label>
                <input type="password" name="password_baru" placeholder="Minimal 8 karakter...">
            </div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; gap: 16px; margin-top: 20px;">
            <button type="reset" class="btn-hapus" style="border: none;">Batal Edit</button>
            <button type="submit" class="btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Simpan Perubahan
            </button>
        </div>
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

<script src="assets/js/sl-dialog.js"></script>
</body>
</html>
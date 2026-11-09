<?php
// SLearning — Dashboard Wakil Kepala Sekolah (Super Admin)
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['jenis'] !== 'waka') { 
    header("Location: login.php"); 
    exit; 
}

$host = "localhost"; $dbname = "slearning_db"; $dbuser = "root"; $dbpass = "";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    
    // Pastikan tabel pengumuman tersedia
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengumuman (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        judul VARCHAR(255) NOT NULL, 
        isi TEXT NOT NULL, 
        target ENUM('semua', 'guru', 'murid') DEFAULT 'semua',
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) { die("Koneksi database gagal."); }

$user_id = (int)$_SESSION['user_id'];
$nama_waka = $_SESSION['nama'] ?? 'Wakil Kepala Sekolah';
$msg = ""; $msg_type = "success";

// --- Proses Form Pengumuman ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'buat_pengumuman') {
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $target = $_POST['target'] ?? 'semua';
    
    if($judul !== '' && $isi !== '') {
        $stmt = $pdo->prepare("INSERT INTO pengumuman (judul, isi, target) VALUES (?, ?, ?)");
        if($stmt->execute([$judul, $isi, $target])){
            $msg = "Informasi/pengumuman berhasil disiarkan ke target: " . strtoupper($target) . "!";
        } else {
            $msg = "Gagal membuat pengumuman."; $msg_type = "error";
        }
    } else {
        $msg = "Judul dan isi pengumuman wajib diisi!"; $msg_type = "error";
    }
}

if (isset($_GET['hapus_pengumuman'])) {
    $id_h = (int)$_GET['hapus_pengumuman'];
    $pdo->prepare("DELETE FROM pengumuman WHERE id = ?")->execute([$id_h]);
    header("Location: waka.php"); exit;
}

// --- Statistik Lengkap Waka ---
$stat_guru = $pdo->query("SELECT COUNT(*) FROM login WHERE jenis = 'guru'")->fetchColumn();
$stat_murid = $pdo->query("SELECT COUNT(*) FROM login WHERE jenis = 'murid'")->fetchColumn();
$stat_kelas = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
$stat_tugas = $pdo->query("SELECT COUNT(*) FROM tugas")->fetchColumn();
$stat_quiz = $pdo->query("SELECT COUNT(*) FROM quiz")->fetchColumn();

// Ambil daftar kelas aktif untuk informasi detail
$kelas_list = $pdo->query("SELECT * FROM kelas ORDER BY id DESC LIMIT 5")->fetchAll();

// --- Ambil Data Pengumuman ---
$pengumuman_list = $pdo->query("SELECT * FROM pengumuman ORDER BY waktu DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Waka — Slearning SMKN 7 Batam</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root { 
    --nav-yellow: #FFF8D6; --nav-yellow-dark: #F2E7B5; --sage-body: #F4F7F5; --sage-50: #E8F0E8; --sage-100: #D1E0D1;
    --sage-300: #9FBA9F; --sage-500: #6D8C6D; --sage-600: #577357; --sage-800: #2F402F; --sage-900: #1D261D;
    --bg-card: #FFFFFF; --shadow-sm: 0 4px 16px rgba(109, 140, 109, 0.06); --shadow-md: 0 8px 24px rgba(109, 140, 109, 0.1);
    --radius-sm: 12px; --radius-md: 16px; --radius-lg: 24px;
}
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; padding-bottom: 60px; position: relative; }
.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 32px 16px; flex-wrap: wrap; gap: 12px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); letter-spacing: -0.5px; }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }
.header-icons { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.header-icons a.icon-btn { color: var(--sage-600); transition: all 0.3s ease; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 8px 14px; border-radius: 50px; border: 1px solid var(--sage-100); font-size: 13px; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.02); gap: 6px; }
.header-icons a.icon-btn:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); }
.container { max-width: 1200px; margin: 0 auto; padding: 40px 32px; }

.beranda-hero { background: linear-gradient(135deg, #FFFFFF 0%, var(--sage-50) 100%); border-radius: var(--radius-lg); padding: 36px 40px; border: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: var(--shadow-sm); }
.beranda-hero h1 { font-size: 24px; font-weight: 800; color: var(--sage-900); margin-bottom: 8px; }
.beranda-hero p { font-size: 14px; color: var(--sage-600); font-weight: 500; max-width: 650px; line-height: 1.6; }
.badge-super { display:inline-block; background: var(--nav-yellow); color: var(--sage-900); padding: 5px 14px; border-radius: 50px; font-size: 11.5px; font-weight: 800; margin-bottom: 12px; border: 1px solid #E2D38A; text-transform: uppercase; letter-spacing: 0.5px; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; margin-bottom: 36px; }
.stat-card { background: #FFFFFF; border-radius: var(--radius-md); padding: 22px; border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 16px; transition: 0.3s; }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--sage-300); }
.stat-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--nav-yellow); display: flex; align-items: center; justify-content: center; color: var(--sage-800); font-size: 22px; flex-shrink: 0; border: 1px solid #E2D38A; }
.stat-info h3 { font-size: 22px; font-weight: 800; color: var(--sage-900); margin-bottom: 2px; }
.stat-info p { font-size: 12.5px; color: var(--sage-600); font-weight: 600; }

.dashboard-grid { display: grid; grid-template-columns: 1.1fr 1fr; gap: 28px; margin-bottom: 40px; align-items: start; }
.panel { background: #FFFFFF; border-radius: var(--radius-md); border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); padding: 28px; }
.panel-header { font-size: 17px; font-weight: 800; color: var(--sage-800); margin-bottom: 20px; border-bottom: 2px solid var(--sage-50); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
.input-elegant { width: 100%; background: var(--sage-body); border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 13px 16px; font-size: 13.5px; margin-bottom: 16px; outline: none; color: var(--sage-900); font-family: inherit; transition: 0.2s; }
.input-elegant:focus { border-color: var(--sage-500); background: #FFFFFF; box-shadow: 0 0 0 3px rgba(109,140,109,0.12); }
.btn-primary { background: var(--sage-500); color: #FFFFFF; border: none; padding: 13px 24px; border-radius: 50px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; justify-content: center; width: 100%; transition: all 0.3s; box-shadow: 0 4px 12px rgba(109,140,109,0.2); }
.btn-primary:hover { background: var(--sage-600); transform: translateY(-1px); }

.list-container { display: flex; flex-direction: column; gap: 14px; max-height: 500px; overflow-y: auto; padding-right: 4px; }
.item-card { padding: 16px 18px; border: 1px solid var(--sage-100); border-radius: var(--radius-sm); background: var(--sage-body); position: relative; transition: 0.2s; }
.item-card:hover { border-color: var(--sage-300); background: #FCFDFD; }
.item-card h4 { font-size: 14.5px; font-weight: 800; color: var(--sage-900); margin-bottom: 6px; padding-right: 60px; }
.item-card p { font-size: 13px; color: var(--sage-600); line-height: 1.5; margin-bottom: 10px; }
.badge-target { font-size: 10.5px; background: var(--nav-yellow); padding: 3px 10px; border-radius: 6px; font-weight: 800; color: var(--sage-800); border: 1px solid #E2D38A; text-transform: uppercase; }
.btn-del { position: absolute; top: 16px; right: 16px; color: #D9534F; font-size: 11.5px; text-decoration: none; font-weight: 700; background: #FDF2F2; padding: 4px 10px; border-radius: 6px; border: 1px solid #F5C6CB; transition: 0.2s; }
.btn-del:hover { background: #D9534F; color: #FFF; }

.section-title { font-size: 19px; font-weight: 800; color: var(--sage-900); margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
.table-wrapper { width: 100%; overflow-x: auto; background: #FFFFFF; border-radius: var(--radius-md); border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); padding: 8px; margin-bottom: 30px; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
.data-table th { background: var(--sage-50); color: var(--sage-800); padding: 12px 16px; font-weight: 800; border-bottom: 1px solid var(--sage-100); }
.data-table td { padding: 12px 16px; color: var(--sage-900); border-bottom: 1px solid var(--sage-body); font-weight: 500; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(232, 240, 232, 0.4); }

@media(max-width: 992px) {
    .dashboard-grid { grid-template-columns: 1fr; }
    .beranda-hero { flex-direction: column; align-items: flex-start; gap: 16px; }
    .container { padding: 20px 16px; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="waka.php" class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      SLearning <span style="font-weight: 500; font-size: 15px; color: var(--sage-600); margin-left: 6px;">| Panel Pimpinan Waka</span>
    </a>
    <div class="header-icons">
      <a href="info_guru.php" class="icon-btn" title="Kelola & Lihat Informasi Guru">👨‍🏫 Bagian Guru</a>
      <a href="info_murid.php" class="icon-btn" title="Kelola & Lihat Informasi Murid">🧑‍🎓 Bagian Murid</a>
      <a href="informasi_waka.php" target="_blank" class="icon-btn">👁️ Profil Waka</a>
      <a href="login.php?logout=1" onclick="return confirm('Keluar dari panel Waka?')" class="icon-btn" style="color: #D9534F; background: #FDF2F2; border-color: #F5C6CB;">Keluar 🚪</a>
    </div>
  </div>
</header>

<main class="container">
    <?php if($msg): ?>
        <div style="background: <?= $msg_type==='error'?'#FDF2F2':'#E8F5E9' ?>; color: <?= $msg_type==='error'?'#D9534F':'#2E7D32' ?>; padding: 16px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; font-weight: 700; border: 1px solid <?= $msg_type==='error'?'#F5C6CB':'#C3DDBC' ?>; box-shadow: var(--shadow-sm);">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- HERO BANNER -->
    <div class="beranda-hero">
      <div>
        <span class="badge-super">Hak Akses: Super Admin / Waka</span>
        <h1 style="color: var(--sage-900);">Selamat Datang, Bapak/Ibu <?= htmlspecialchars($nama_waka) ?> 👋</h1>
        <p>Pusat kontrol eksekutif SLearning SMKN 7 Batam. Pantau statistik partisipasi akademis secara langsung dan siarkan pengumuman resmi ke portal seluruh guru serta siswa.</p>
      </div>
    </div>

    <!-- STATISTIK SEKOLAH -->
    <div class="stats-grid">
      <div class="stat-card">
          <div class="stat-icon">👨‍🏫</div>
          <div class="stat-info">
              <h3><?= number_format($stat_guru) ?></h3>
              <p>Guru Pengajar</p>
          </div>
      </div>
      <div class="stat-card">
          <div class="stat-icon">🧑‍🎓</div>
          <div class="stat-info">
              <h3><?= number_format($stat_murid) ?></h3>
              <p>Siswa / Murid Aktif</p>
          </div>
      </div>
      <div class="stat-card">
          <div class="stat-icon">🏫</div>
          <div class="stat-info">
              <h3><?= number_format($stat_kelas) ?></h3>
              <p>Kelas Terdaftar</p>
          </div>
      </div>
      <div class="stat-card">
          <div class="stat-icon">📚</div>
          <div class="stat-info">
              <h3><?= number_format($stat_tugas) ?></h3>
              <p>Tugas & Evaluasi</p>
          </div>
      </div>
      <div class="stat-card">
          <div class="stat-icon">📝</div>
          <div class="stat-info">
              <h3><?= number_format($stat_quiz) ?></h3>
              <p>Bank Kuis Aktif</p>
          </div>
      </div>
    </div>

    <!-- PANEL UTAMA: BROADCAST PENGUMUMAN & RIWAYAT -->
    <div class="dashboard-grid">
        <!-- FORM PENGUMUMAN / INFORMASI -->
        <div class="panel">
            <div class="panel-header">
                <span>📢 Buat Pengumuman Sekolah</span>
            </div>
            <form method="POST">
                <input type="hidden" name="aksi" value="buat_pengumuman">
                
                <label style="font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 6px; display:block;">Target Penerima Informasi</label>
                <select name="target" class="input-elegant">
                    <option value="semua">🌐 Semua Warga Sekolah (Guru & Murid)</option>
                    <option value="guru">👨‍🏫 Khusus Dewan Guru Saja</option>
                    <option value="murid">🧑‍🎓 Khusus Siswa / Murid Saja</option>
                </select>

                <label style="font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 6px; display:block;">Judul Pengumuman</label>
                <input type="text" name="judul" class="input-elegant" placeholder="Contoh: Himbauan Pelaksanaan Ujian / Kegiatan Sekolah..." required>

                <label style="font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 6px; display:block;">Isi Pesan / Informasi Detail</label>
                <textarea name="isi" class="input-elegant" style="min-height: 140px; resize: vertical;" placeholder="Tuliskan detail informasi atau instruksi lengkap di sini..." required></textarea>

                <button type="submit" class="btn-primary">Kirim & Siarkan Informasi Sekarang</button>
            </form>
        </div>

        <!-- RIWAYAT PENGUMUMAN -->
        <div class="panel">
            <div class="panel-header">
                <span>📋 Riwayat Informasi Disiarkan</span>
                <span style="font-size: 12px; color: var(--sage-600); font-weight: 600;">(<?= count($pengumuman_list) ?> Pesan)</span>
            </div>
            <div class="list-container">
                <?php if(empty($pengumuman_list)): ?>
                    <p style="text-align: center; color: var(--sage-600); font-size: 13.5px; padding: 40px 20px;">Belum ada pengumuman yang disiarkan ke sistem.</p>
                <?php else: foreach($pengumuman_list as $p): ?>
                    <div class="item-card">
                        <a href="waka.php?hapus_pengumuman=<?= $p['id'] ?>" onclick="return confirm('Yakin ingin menghapus pengumuman ini?')" class="btn-del">Hapus</a>
                        <h4><?= htmlspecialchars($p['judul']) ?></h4>
                        <p><?= nl2br(htmlspecialchars($p['isi'])) ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                            <span class="badge-target">Target: <?= strtoupper($p['target']) ?></span>
                            <span style="font-size: 11px; color: var(--sage-600); font-weight: 600;">📅 <?= date('d M Y, H:i', strtotime($p['waktu'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- INFORMASI TAMBAHAN: MONITORING KELAS -->
    <div style="margin-top: 20px;">
        <h2 class="section-title">🏫 Monitoring Kelas Terbaru di SLearning</h2>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Nama Kelas</th>
                        <th>Keterangan / Mata Pelajaran</th>
                        <th>Aksi Sistem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($kelas_list)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--sage-600); padding: 30px;">Belum ada data kelas yang terdaftar dalam database.</td>
                        </tr>
                    <?php else: foreach($kelas_list as $index => $kls): ?>
                        <tr>
                            <td><strong>#<?= $kls['id'] ?></strong></td>
                            <td><?= htmlspecialchars($kls['nama_kelas'] ?? $kls['nama'] ?? 'Kelas Pembelajaran') ?></td>
                            <td><?= htmlspecialchars($kls['deskripsi'] ?? 'Kelas aktif dalam platform Slearning SMKN 7 Batam') ?></td>
                            <td><span style="font-size: 11.5px; background: var(--sage-50); color: var(--sage-800); padding: 4px 10px; border-radius: 6px; font-weight: 700; border: 1px solid var(--sage-100);">Terverifikasi</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</body>
</html>gt
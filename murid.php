<?php
// SLearning — Dashboard Murid (FULL VERSION - Live Quiz & Lobi)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (($_SESSION['jenis'] ?? '') === 'guru') { header("Location: guru.php"); exit; }

$uid = (int)$_SESSION['user_id'];

// AUTO-MIGRASI: Tambahkan kolom 'foto' ke tabel login jika belum ada
$cek_foto = mysqli_query($koneksi, "SHOW COLUMNS FROM login LIKE 'foto'");
if($cek_foto && mysqli_num_rows($cek_foto) === 0) {
    mysqli_query($koneksi, "ALTER TABLE login ADD COLUMN foto VARCHAR(255) NULL");
}

$s = mysqli_prepare($koneksi, "SELECT id, nama, username, email, jenis, level, foto, password FROM login WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($s, "i", $uid); mysqli_stmt_execute($s);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
if (!$user) { header("Location: logout.php"); exit; }

foreach (['nama','username','email','jenis','level','foto'] as $k) $_SESSION[$k] = $user[$k] ?? '';
$nama_murid = $user['nama']; 
$username = $user['username'];
$foto_profil = $user['foto'] ?? '';

function set_flash_notification($pesan, $tipe = 'success', $judul = 'Berhasil!') {
    $_SESSION['swal_msg'] = ['title' => $judul, 'text' => $pesan, 'icon' => $tipe];
}
function render_flash_notification() {
    if (isset($_SESSION['swal_msg'])) {
        $m = $_SESSION['swal_msg'];
        unset($_SESSION['swal_msg']);
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
              <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ title: '{$m['title']}', text: '{$m['text']}', icon: '{$m['icon']}', confirmButtonText: 'OK', confirmButtonColor: '#6D8C6D' });
                });
              </script>";
    }
}

$page = $_GET['page'] ?? 'beranda';
$quiz_id_get = (int)($_GET['quiz_id'] ?? 0);

// =========================================================================
// API RINGAN: Pengecekan Status Kuis & Kehadiran Lobi (Mencegah Server Down)
// =========================================================================
if ($page === 'api_cek_kuis' && $quiz_id_get > 0) {
    header('Content-Type: application/json');
    // 1. Catat/Perbarui kehadiran murid di lobi 
    mysqli_query($koneksi, "INSERT INTO quiz_lobby (quiz_id, user_id) VALUES ($quiz_id_get, $uid) ON DUPLICATE KEY UPDATE waktu=CURRENT_TIMESTAMP");
    // 2. Cek status kuis
    $q_api = mysqli_query($koneksi, "SELECT status FROM quiz WHERE id = $quiz_id_get");
    $status_api = $q_api ? (mysqli_fetch_assoc($q_api)['status'] ?? 'menunggu') : 'menunggu';
    echo json_encode(['status' => $status_api]);
    exit; 
}
// =========================================================================

// ---------- PROSES AKSI POST ----------

// 1. Proses Update Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama_baru = trim($_POST['nama']); $email_baru = trim($_POST['email']);
    $pass_lama = $_POST['password_lama']; $pass_baru = $_POST['password_baru'];
    $update_sql = "UPDATE login SET nama=?, email=?"; $params = [$nama_baru, $email_baru]; $types = "ss";

    if (!empty($pass_baru)) {
        if (password_verify($pass_lama, $user['password'])) {
            $update_sql .= ", password=?"; $params[] = password_hash($pass_baru, PASSWORD_DEFAULT); $types .= "s";
        } else {
            set_flash_notification('Password lama yang dimasukkan salah!', 'error', 'Gagal Simpan');
            header("Location: murid.php?page=profil"); exit;
        }
    }

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $dir = 'uploads/profil/'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            $path_upload = $dir . 'profil_' . $uid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $path_upload)) {
                $update_sql .= ", foto=?"; $params[] = $path_upload; $types .= "s";
            }
        } else { set_flash_notification('Format foto harus JPG/PNG/GIF.', 'warning', 'Format Tidak Didukung'); }
    }

    $update_sql .= " WHERE id=?"; $params[] = $uid; $types .= "i";
    $upd = mysqli_prepare($koneksi, $update_sql);
    mysqli_stmt_bind_param($upd, $types, ...$params);
    if (mysqli_stmt_execute($upd)) { set_flash_notification('Profil berhasil diperbarui.', 'success', 'Tersimpan'); } 
    else { set_flash_notification('Terjadi kesalahan saat menyimpan profil.', 'error', 'Gagal'); }
    mysqli_stmt_close($upd); header("Location: murid.php?page=profil"); exit;
}

// 2. Proses Gabung Kelas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gabung_kelas'])) {
    $kode = strtoupper(trim($_POST['kode_gabung'] ?? ''));
    if ($kode==='') { set_flash_notification('Masukkan kode.', 'error'); } 
    else {
        $s = mysqli_prepare($koneksi, "SELECT id, nama_kelas FROM kelas WHERE kode_gabung=? LIMIT 1");
        mysqli_stmt_bind_param($s,'s',$kode); mysqli_stmt_execute($s);
        $kls = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
        if (!$kls) { set_flash_notification('Kode kelas tidak ditemukan.', 'error', 'Gagal'); } 
        else {
            $kelas_id = $kls['id'];
            $s = mysqli_prepare($koneksi, "SELECT id FROM anggota_kelas WHERE kelas_id=? AND user_id=? LIMIT 1");
            mysqli_stmt_bind_param($s,'ii',$kelas_id,$uid); mysqli_stmt_execute($s);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($s))) { set_flash_notification('Sudah tergabung.', 'warning', 'Info'); } 
            else {
                $ins = mysqli_prepare($koneksi, "INSERT INTO anggota_kelas (kelas_id, user_id) VALUES (?,?)");
                mysqli_stmt_bind_param($ins,'ii',$kelas_id,$uid);
                if (mysqli_stmt_execute($ins)) { set_flash_notification('Berhasil gabung kelas.', 'success'); }
                mysqli_stmt_close($ins);
            }
            mysqli_stmt_close($s);
        }
    }
    header('Location: murid.php?page=kelas'); exit;
}

// 3. Proses Isi Absen
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['isi_absen'])) {
    $aid = (int)($_POST['absensi_id'] ?? 0); $status = $_POST['status'] ?? 'hadir';
    $s = mysqli_prepare($koneksi, "SELECT a.id FROM absensi a JOIN anggota_kelas ak ON ak.kelas_id=a.kelas_id WHERE a.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$aid,$uid); mysqli_stmt_execute($s);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($s))) {
        $ins = mysqli_prepare($koneksi, "INSERT INTO absensi_hadir (absensi_id, user_id, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), waktu=CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($ins,'iis',$aid,$uid,$status); mysqli_stmt_execute($ins); mysqli_stmt_close($ins);
        set_flash_notification('Kehadiran dicatat.', 'success');
    }
    mysqli_stmt_close($s); header('Location: murid.php?page=absensi'); exit;
}

// 4. Proses Simpan Hasil Quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz_hasil'])) {
    $q_id_submit = (int)$_POST['quiz_id']; $benar = (int)$_POST['benar']; $total = (int)$_POST['total']; $skor_poin = (int)$_POST['skor_total'];
    $cek = mysqli_query($koneksi, "SELECT id FROM quiz_hasil WHERE quiz_id = $q_id_submit AND user_id = $uid");
    if(mysqli_num_rows($cek) === 0) {
        $ins = mysqli_prepare($koneksi, "INSERT INTO quiz_hasil (quiz_id, user_id, benar, total, skor) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "iiiii", $q_id_submit, $uid, $benar, $total, $skor_poin);
        mysqli_stmt_execute($ins); mysqli_stmt_close($ins);
        set_flash_notification("Skor Kecepatan Anda: $skor_poin Poin.", 'success', 'Kuis Selesai!');
    }
    header("Location: murid.php?page=kerjakan_quiz&quiz_id=".$q_id_submit); exit;
}

// Data Pendukung Dasbor
$daftar_kelas = [];
$s = mysqli_prepare($koneksi, "SELECT k.id, k.nama_kelas, k.kode_gabung, (SELECT nama FROM login l WHERE l.id=k.guru_id) AS nama_guru FROM anggota_kelas ak JOIN kelas k ON k.id=ak.kelas_id WHERE ak.user_id=? ORDER BY k.id DESC");
mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
$rr = mysqli_stmt_get_result($s); while ($r=mysqli_fetch_assoc($rr)) $daftar_kelas[]=$r;
mysqli_stmt_close($s);

$in_kelas = array_column($daftar_kelas,'id') ? implode(',', array_map('intval',array_column($daftar_kelas,'id'))) : '0';
$stat_tugas_aktif = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM tugas WHERE kelas_id IN ($in_kelas)"))['jml'] ?? 0;
$stat_quiz = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM quiz WHERE kelas_id IN ($in_kelas)"))['jml'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Murid — SLearning</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root{--nav-yellow:#FFF8D6;--nav-yellow-dark:#F2E7B5;--sage-body:#F4F7F5;--sage-50:#E8F0E8;--sage-100:#D1E0D1;--sage-500:#6D8C6D;--sage-600:#577357;--sage-800:#2F402F;--sage-900:#1D261D;--radius-sm:12px;--radius-md:16px;--radius-lg:24px; --bg-card: #FFFFFF; --shadow-sm: 0 4px 12px rgba(109, 140, 109, 0.05);}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; padding-bottom: 60px; position: relative; overflow-x: hidden; }
body::before { content: ''; position: fixed; top: -10%; left: -5%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(255, 248, 214, 0.8) 0%, rgba(255, 248, 214, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
body::after { content: ''; position: fixed; bottom: -10%; right: -5%; width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(109, 140, 109, 0.15) 0%, rgba(109, 140, 109, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }

.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); position: relative; z-index: 10; padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 32px 16px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); letter-spacing: -0.5px; }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }
.header-icons { display: flex; align-items: center; gap: 24px; }
.header-icons a { color: var(--sage-600); transition: all 0.3s ease; position: relative; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 10px; border-radius: 50%; border: 1px solid var(--sage-50); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.header-icons a:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); border-color: var(--sage-100); }
.header-icons svg { width: 20px; height: 20px; }
.avatar-header-sm { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 2px solid var(--sage-100); background: var(--sage-500); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; margin: -4px; }

.nav-tabs { display: flex; justify-content: center; gap: 8px; max-width: 1200px; margin: 0 auto; padding: 0 32px; }
.nav-tab { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 28px; text-decoration: none; color: var(--sage-600); font-size: 13px; font-weight: 600; transition: all 0.3s ease; position: relative; border-radius: var(--radius-sm) var(--radius-sm) 0 0; }
.nav-tab svg { width: 20px; height: 20px; stroke-width: 2.2; }
.nav-tab:hover { color: var(--sage-800); background: rgba(255, 255, 255, 0.6); }
.nav-tab.active { color: var(--sage-900); background: #FFFFFF; }
.nav-tab.active::after { content: ''; position: absolute; bottom: 0; left: 15%; width: 70%; height: 3px; background: var(--sage-500); border-radius: 4px 4px 0 0; }

.container { max-width: 1100px; margin: 0 auto; padding: 40px 32px; position: relative; z-index: 1; }
.card { background: #fff; border: 1px solid var(--sage-50); border-radius: var(--radius-lg); padding: 36px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
.btn-yellow { background: var(--sage-500); color: #fff; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 50px; padding: 11px 18px; font-size: 13px; font-weight: 800; cursor: pointer; text-decoration: none; transition: 0.3s; box-shadow: 0 4px 12px rgba(109, 140, 109, 0.3); }
.btn-yellow:hover { background: var(--sage-600); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(109, 140, 109, 0.4); }
.form-control { width: 100%; padding: 14px 16px; border: 1px solid var(--sage-100); background: var(--sage-body); border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; outline: none; transition: 0.3s; }
.form-control:focus { border-color: var(--sage-500); background: #fff; box-shadow: 0 0 0 4px rgba(109, 140, 109, 0.1); }
.class-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 16px; }
.class-card { background: var(--bg-card); border: 1px solid var(--sage-50); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm); transition: all 0.4s ease; display: flex; flex-direction: column; }
.class-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(109, 140, 109, 0.12); border-color: var(--sage-100); }

/* Styling Quiz */
.quiz-container { max-width: 800px; margin: 0 auto; text-align: center; }
.quiz-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: var(--sage-50); padding: 12px 24px; border-radius: 50px; font-weight: 800; color: var(--sage-800); }
.timer-badge { display: inline-flex; align-items: center; gap: 8px; background: #E07A5F; color: #fff; padding: 8px 16px; border-radius: 50px; font-size: 14px; transition: 0.3s; }
.timer-badge.reading { background: #CD7F32; }
.score-badge { font-weight: 900; color: #CD7F32; font-size: 18px; display: flex; align-items: center; gap: 6px; }
.soal-text { font-size: 22px; font-weight: 800; color: var(--sage-900); margin-bottom: 30px; line-height: 1.4; }
.opsi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.btn-opsi { background: #fff; border: 2px solid var(--sage-100); border-radius: 12px; padding: 20px; font-size: 16px; font-weight: 700; color: var(--sage-800); cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 12px; text-align: left; }
.btn-opsi:disabled { cursor: not-allowed; }
.btn-opsi:not(:disabled):hover { border-color: var(--sage-500); background: var(--sage-50); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.opsi-label { display: inline-flex; justify-content: center; align-items: center; width: 32px; height: 32px; border-radius: 8px; background: var(--sage-100); color: var(--sage-800); font-weight: 800; }

.feedback-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 9999; color: white; animation: popIn 0.3s forwards; }
.feedback-overlay.correct { background-color: #2E7D32; }
.feedback-overlay.wrong { background-color: #C62828; }
.feedback-overlay h1 { font-size: 70px; font-weight: 900; margin-bottom: 10px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
.feedback-overlay p { font-size: 22px; font-weight: 700; background: rgba(0,0,0,0.2); padding: 12px 24px; border-radius: 50px; text-align: center; }
.feedback-overlay .correct-ans-box { background: #fff; color: #C62828; padding: 12px 24px; border-radius: 12px; font-size: 18px; font-weight: 800; margin-top: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
@media (max-width: 768px) { .header-top { flex-wrap: wrap; gap: 16px; } .nav-tabs { overflow-x: auto; justify-content: flex-start; padding: 0 16px; } .opsi-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="murid.php?page=beranda" class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      Slearning <span style="font-size: 14px; font-weight: 600; color: var(--sage-500); margin-left: 8px; background: var(--sage-50); padding: 4px 10px; border-radius: 50px;">Siswa</span>
    </a>
    
    <?php if($page !== 'kerjakan_quiz'): ?>
    <div class="header-icons">
      <a href="murid.php?page=profil" title="Profil Saya">
        <?php if($foto_profil && file_exists($foto_profil)): ?><img src="<?= e($foto_profil) ?>" class="avatar-header-sm">
        <?php else: ?><div class="avatar-header-sm"><?= strtoupper(substr($nama_murid, 0, 1)) ?></div><?php endif; ?>
      </a>
      <a href="logout.php" onclick="return confirm('Keluar dari Slearning?')" title="Keluar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <?php if($page !== 'kerjakan_quiz'): ?>
  <nav class="nav-tabs">
    <a href="murid.php?page=beranda" class="nav-tab <?= $page==='beranda'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> <span>Beranda</span></a>
    <a href="murid.php?page=kelas" class="nav-tab <?= $page==='kelas'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> <span>Kelas</span></a>
    <a href="murid.php?page=absensi" class="nav-tab <?= $page==='absensi'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg> <span>Absensi</span></a>
    <a href="murid.php?page=tugas" class="nav-tab <?= $page==='tugas'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> <span>Tugas</span></a>
    <a href="murid.php?page=quiz" class="nav-tab <?= ($page==='quiz' || $page==='kerjakan_quiz')?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="14" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="18"/><line x1="9" y1="10" x2="9.01" y2="10"/></svg> <span>Quiz</span></a>
  </nav>
  <?php endif; ?>
</header>

<main class="container">
  <?php switch($page): case 'kelas': ?>
      <div class="card" style="border-left:4px solid var(--sage-500)">
          <h2 style="font-size:20px;margin-bottom:6px;font-weight:800;color:var(--sage-800);">Manajemen & Kode Gabung Kelas</h2>
          <p style="font-size:13px;color:var(--sage-600);margin-bottom:16px">Masukkan kode kelas dari guru untuk bergabung.</p>
          <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap">
              <input type="text" name="kode_gabung" class="form-control" placeholder="KLS-XXX" required style="flex:1;min-width:200px;text-transform:uppercase">
              <button type="submit" name="gabung_kelas" class="btn-yellow">Gabung Kelas</button>
          </form>
      </div>

      <h3 style="font-size:18px;margin:30px 0 16px;font-weight:800;color:var(--sage-800);display:flex;align-items:center;gap:10px;"><span style="display:block;width:8px;height:24px;background:var(--sage-500);border-radius:4px;"></span> Daftar Kelas Saya</h3>
      <?php if (empty($daftar_kelas)): ?>
          <div style="padding:40px 20px;background:#fff;border:1px dashed var(--sage-300);border-radius:var(--radius-lg);text-align:center;font-size:14px;color:var(--sage-600)">Belum bergabung ke kelas mana pun.</div>
      <?php else: ?>
          <div class="class-grid">
              <?php foreach ($daftar_kelas as $kls): ?>
              <article class="class-card">
                  <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                      <div style="width:48px;height:48px;background:var(--sage-50);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--sage-600); border: 1px solid var(--sage-100);">
                          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                      </div>
                      <div>
                          <h4 style="font-size:17px;font-weight:800;color:var(--sage-800);margin-bottom:4px"><?= e($kls['nama_kelas']) ?></h4>
                          <p style="font-size:12.5px;color:var(--sage-600);font-weight:600;">Guru: <?= e($kls['nama_guru'] ?? 'Guru Pengampu') ?></p>
                      </div>
                  </div>
                  <div style="background:var(--nav-yellow);border:1px dashed #E2D38A;padding:8px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:700;display:inline-block;color:var(--sage-800);font-family:monospace; margin-top:auto;">Kode: <?= e($kls['kode_gabung']) ?></div>
              </article>
              <?php endforeach; ?>
          </div>
      <?php endif; ?>

  <?php break; case 'profil': ?>
      <div class="card" style="max-width: 600px; margin: 0 auto;">
          <h2 style="font-size:20px;margin-bottom:6px;font-weight:800;color:var(--sage-800);">Pengaturan Profil</h2>
          <form action="murid.php?page=profil" method="POST" enctype="multipart/form-data" style="margin-top:24px;">
              <input type="hidden" name="update_profil" value="1">
              <div style="text-align:center; margin-bottom:32px;">
                  <?php if($foto_profil && file_exists($foto_profil)): ?><img src="<?= e($foto_profil) ?>" style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:4px solid var(--sage-500); margin-bottom:12px;">
                  <?php else: ?><div style="width:110px; height:110px; border-radius:50%; background:var(--sage-500); color:#fff; display:flex; align-items:center; justify-content:center; font-size:40px; font-weight:900; margin:0 auto 12px auto;"><?= strtoupper(substr($nama_murid, 0, 1)) ?></div><?php endif; ?>
                  <div><label style="font-size:13px; font-weight:700; color:var(--sage-800); cursor:pointer; background:var(--sage-50); padding:10px 20px; border-radius:50px; border:1px solid var(--sage-100); display:inline-block;">Pilih Foto Baru (JPG/PNG)<input type="file" name="foto" accept="image/png, image/jpeg, image/jpg" style="display:none;"></label></div>
              </div>
              <div style="margin-bottom:20px;"><label style="font-size:13px; font-weight:700; display:block; margin-bottom:8px;">Nama Lengkap</label><input type="text" name="nama" class="form-control" value="<?= e($nama_murid) ?>" required></div>
              <div style="margin-bottom:32px;"><label style="font-size:13px; font-weight:700; display:block; margin-bottom:8px;">Alamat Email</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
              <hr style="border:0; border-top:1px solid var(--sage-100); margin-bottom:24px;">
              <h3 style="font-size:16px; margin-bottom:20px; font-weight:800; color:var(--sage-800);">Ubah Kata Sandi</h3>
              <div style="margin-bottom:20px;"><label style="font-size:13px; font-weight:700; display:block; margin-bottom:8px;">Password Lama</label><input type="password" name="password_lama" class="form-control" placeholder="Kosongkan jika tidak diubah"></div>
              <div style="margin-bottom:32px;"><label style="font-size:13px; font-weight:700; display:block; margin-bottom:8px;">Password Baru</label><input type="password" name="password_baru" class="form-control" placeholder="Masukkan password baru"></div>
              <button type="submit" class="btn-yellow" style="width:100%; padding:14px;">Simpan Perubahan</button>
          </form>
      </div>

  <?php break; case 'absensi': ?>
      <div class="card">
          <h2 style="font-size:20px;margin-bottom:6px;font-weight:800;color:var(--sage-800);">Absensi Kehadiran Murid</h2>
          <?php 
          $daftar_absen = []; $q = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas AS kelas, ah.status FROM absensi a JOIN kelas k ON k.id=a.kelas_id LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=$uid WHERE a.kelas_id IN ($in_kelas) ORDER BY a.tanggal DESC LIMIT 10");
          while($q && $r=mysqli_fetch_assoc($q)) $daftar_absen[] = $r;
          if(empty($daftar_absen)): ?>
              <div style="padding:40px 20px;text-align:center;font-size:14px;color:var(--sage-600);border:1px dashed var(--sage-300);border-radius:var(--radius-lg);margin-top:20px;">Belum ada sesi absensi aktif.</div>
          <?php else: foreach($daftar_absen as $ab): ?>
              <div style="background:#FAFCFA;padding:20px 24px;border-radius:var(--radius-md);margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid var(--sage-100);">
                  <div><strong style="font-size:16px;color:var(--sage-800);margin-bottom:4px;display:block;"><?= e($ab['judul_absensi']) ?></strong><div style="font-size:13px;color:var(--sage-600);font-weight:600;">📍 <?= e($ab['kelas']) ?> &bull; 📅 <?= e(tanggal_id($ab['tanggal'], false)) ?></div></div>
                  <?php if($ab['status']): ?>
                      <span style="background:#E8F5E9;color:#2E7D32;border:1px solid #C8E6C9;padding:8px 16px;border-radius:50px;font-size:12.5px;font-weight:800;">Sudah Absen (<?= ucfirst($ab['status']) ?>)</span>
                  <?php else: ?>
                      <form method="POST" style="display:flex;gap:8px;align-items:center;">
                          <input type="hidden" name="absensi_id" value="<?= $ab['id'] ?>">
                          <select name="status" class="form-control" style="padding:10px;font-size:13px;width:100px;"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select>
                          <button type="submit" name="isi_absen" class="btn-yellow" style="padding:10px 16px;">Kirim Absen</button>
                      </form>
                  <?php endif; ?>
              </div>
          <?php endforeach; endif; ?>
      </div>

  <?php break; case 'tugas': ?>
      <div class="card">
          <h2 style="font-size:20px;margin-bottom:6px;font-weight:800;color:var(--sage-800);">Daftar Tugas Murid</h2>
          <?php
          $daftar_tugas = []; $q = mysqli_query($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, k.nama_kelas AS mapel, pt.nama_file FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=$uid WHERE t.kelas_id IN ($in_kelas) ORDER BY t.deadline ASC LIMIT 10");
          while($q && $r=mysqli_fetch_assoc($q)) $daftar_tugas[] = $r;
          if(empty($daftar_tugas)): ?>
              <div style="padding:40px 20px;text-align:center;font-size:14px;color:var(--sage-600);border:1px dashed var(--sage-300);border-radius:var(--radius-lg);margin-top:20px;">Belum ada tugas tersedia.</div>
          <?php else: foreach($daftar_tugas as $t): ?>
              <div style="background:#FAFCFA;padding:20px 24px;border-radius:var(--radius-md);margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid var(--sage-100);">
                  <div><strong style="font-size:16px;color:var(--sage-800);margin-bottom:4px;display:block;">📄 <?= e($t['judul']) ?></strong><div style="font-size:13px;color:var(--sage-600);font-weight:600;">📍 <?= e($t['mapel']) ?> &bull; ⏳ Deadline: <?= $t['deadline'] ? e(tanggal_id($t['deadline'])) : 'Tanpa Batas' ?></div></div>
                  <?php if($t['nama_file']): ?><span style="background:#E8F5E9;color:#2E7D32;border:1px solid #C8E6C9;padding:8px 16px;border-radius:50px;font-size:12.5px;font-weight:800;">Sudah Dikumpul</span>
                  <?php else: ?><span style="background:#FFF8D6;color:#856404;border:1px solid #E2D38A;padding:8px 16px;border-radius:50px;font-size:12.5px;font-weight:800;">Belum Kumpul</span><?php endif; ?>
              </div>
          <?php endforeach; endif; ?>
      </div>

  <?php break; case 'quiz': ?>
      <div class="card">
          <h2 style="font-size:20px;margin-bottom:6px;font-weight:800;color:var(--sage-800);">Live Quiz Slearning</h2>
          <?php 
          $daftar_kuis = [];
          if ($in_kelas !== '0') {
              $qk = mysqli_query($koneksi, "SELECT q.id, q.judul, q.status, k.nama_kelas, (SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id = q.id) AS total_soal, qh.skor FROM quiz q JOIN kelas k ON q.kelas_id = k.id LEFT JOIN quiz_hasil qh ON qh.quiz_id = q.id AND qh.user_id = $uid WHERE q.kelas_id IN ($in_kelas) ORDER BY q.id DESC LIMIT 10");
              while ($qk && $r = mysqli_fetch_assoc($qk)) { $daftar_kuis[] = $r; }
          }
          if(empty($daftar_kuis)): ?>
              <div style="padding:40px 20px;text-align:center;font-size:14px;color:var(--sage-600);border:1px dashed var(--sage-300);border-radius:var(--radius-lg);margin-top:20px;">Belum ada kuis baru.</div>
          <?php else: ?>
              <div class="class-grid">
                  <?php foreach($daftar_kuis as $q): $is_done = isset($q['skor']); $status_kuis = $q['status']; ?>
                  <article class="class-card">
                      <div style="margin-bottom:16px;">
                          <h4 style="font-size:17px;font-weight:800;color:var(--sage-800);margin-bottom:6px"><?= e($q['judul']) ?></h4>
                          <p style="font-size:12.5px;color:var(--sage-600);font-weight:600;">📍 <?= e($q['nama_kelas']) ?> &bull; 📝 <?= (int)$q['total_soal'] ?> Soal</p>
                      </div>
                      <div style="margin-top:auto;">
                          <?php if($is_done): ?><div style="background:#FAFCFA; border:1px solid var(--sage-100); padding:12px; border-radius:var(--radius-sm); font-size:14px; text-align:center; font-weight:800; color:var(--sage-800);">🏆 Poin Kamu: <?= (int)$q['skor'] ?></div>
                          <?php elseif($status_kuis === 'selesai'): ?><div style="background:#FFF3F0; border:1px solid #FFCDD2; color:#C62828; padding:12px; border-radius:var(--radius-sm); font-size:13px; text-align:center; font-weight:700;">Kuis Ditutup</div>
                          <?php else: ?>
                              <a href="murid.php?page=kerjakan_quiz&quiz_id=<?= $q['id'] ?>" class="btn-yellow" style="width:100%; display:block; text-align:center; padding:14px; font-size:13px;"><?= $status_kuis === 'menunggu' ? '⏳ Masuk ke Lobi' : '▶️ Mulai Kuis!' ?></a>
                          <?php endif; ?>
                      </div>
                  </article>
                  <?php endforeach; ?>
              </div>
          <?php endif; ?>
      </div>

  <?php break; case 'kerjakan_quiz': ?>
      <?php
      if ($quiz_id_get <= 0) { echo "<p>Kuis tidak valid.</p>"; break; }
      $q_info = mysqli_query($koneksi, "SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON q.kelas_id=k.id WHERE q.id=$quiz_id_get LIMIT 1");
      $kuis = $q_info ? mysqli_fetch_assoc($q_info) : null;
      if (!$kuis) { echo "<p>Kuis tidak ditemukan.</p>"; break; }

      $q_hasil = mysqli_query($koneksi, "SELECT skor FROM quiz_hasil WHERE quiz_id=$quiz_id_get AND user_id=$uid");
      if (mysqli_num_rows($q_hasil) > 0) {
          $skor = mysqli_fetch_assoc($q_hasil)['skor'];
          echo "<div class='card quiz-container' style='padding:60px 20px;'><h2 style='font-size:26px; margin-bottom:12px; font-weight:900;'>Kuis Selesai! 🎉</h2>";
          echo "<p style='font-size:15px; color:var(--sage-600); margin-bottom:20px; font-weight:600;'>Lihat perolehan Poin Kecepatan kamu.</p>";
          echo "<div style='font-size:70px; font-weight:900; color:#CD7F32; margin: 30px 0; text-shadow: 0 4px 12px rgba(205,127,50,0.2);'>🏆 $skor</div>";
          echo "<a href='murid.php?page=quiz' class='btn-yellow' style='padding:14px 28px;'>Kembali ke Daftar Kuis</a></div>";
          break;
      }

      $status_kuis = $kuis['status'] ?? 'menunggu';

      // JIKA STATUS MENUNGGU (LOBI AJAX)
      if ($status_kuis === 'menunggu'):
      ?>
          <div class="card quiz-container" style="padding: 60px 20px; background: linear-gradient(135deg, #FFF8D6 0%, #FFFFFF 100%); border: 1px solid var(--nav-yellow-dark); box-shadow:var(--shadow-lg);">
              <div style="margin-bottom: 24px;">
                  <?php if($foto_profil && file_exists($foto_profil)): ?><img src="<?= e($foto_profil) ?>" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border: 4px solid var(--sage-500); animation: bounce 2s infinite; box-shadow:0 8px 24px rgba(109,140,109,0.3);">
                  <?php else: ?><div style="width:120px; height:120px; border-radius:50%; background:var(--sage-500); color:#fff; display:flex; align-items:center; justify-content:center; font-size:48px; font-weight:900; margin:0 auto; border: 4px solid #fff; box-shadow: 0 8px 24px rgba(109,140,109,0.3); animation: bounce 2s infinite;"><?= strtoupper(substr($nama_murid, 0, 1)) ?></div><?php endif; ?>
              </div>
              <h2 style="font-size: 28px; color: var(--sage-900); margin-bottom:6px; font-weight: 900; letter-spacing:-0.5px;"><?= e($nama_murid) ?></h2>
              <p style="font-size: 15px; color: var(--sage-600); margin-bottom: 40px; font-weight: 800; opacity: 0.8;">@<?= e($username) ?></p>
              <h3 style="font-size: 20px; margin-bottom:12px; font-weight:800; color:var(--sage-800);">Anda sudah di Lobi Tunggu</h3>
              <p style="font-size: 15px; color:var(--sage-600); margin-bottom: 30px; font-weight:500;">Menunggu guru memulai kuis <strong><?= e($kuis['judul']) ?></strong>. Layar ini akan beralih otomatis.</p>
              
              <div style="background:var(--sage-500); display:inline-flex; align-items:center; gap:10px; padding:14px 32px; border-radius:50px; font-weight:800; color:#fff; font-size:15px; animation: pulse 2s infinite; box-shadow:0 4px 16px rgba(109,140,109,0.4);">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Menunggu Host...
              </div>
          </div>
          
          <!-- SCRIPT AJAX: Cek status & Ping Kehadiran ke guru.php -->
          <script>
          setInterval(() => {
              fetch('murid.php?page=api_cek_kuis&quiz_id=<?= $quiz_id_get ?>')
              .then(response => response.json())
              .then(data => {
                  if (data.status === 'mulai' || data.status === 'selesai') {
                      window.location.reload(); 
                  }
              })
              .catch(err => console.error("Gagal mengecek status kuis", err));
          }, 3000); 
          </script>
      
      <?php
      // JIKA STATUS MULAI
      elseif ($status_kuis === 'mulai'):
          $list_soal = []; $qs = mysqli_query($koneksi, "SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci FROM quiz_soal WHERE quiz_id=$quiz_id_get ORDER BY id ASC");
          while($qs && $row = mysqli_fetch_assoc($qs)) { $list_soal[] = $row; }
          if(empty($list_soal)) { echo "<div class='card quiz-container'><p>Belum ada soal.</p></div>"; break; }
      ?>
          <div class="quiz-container">
              <div class="quiz-header">
                  <span id="indikator-soal" style="font-size:15px;">Soal 1 / <?= count($list_soal) ?></span>
                  <div class="timer-badge reading" id="timer-display">⏳ Bersiap...</div>
                  <div class="score-badge">🏆 <span id="skor-ui">0</span></div>
              </div>
              <div class="card" style="padding: 40px;">
                  <div class="soal-text" id="teks-soal">Memuat pertanyaan...</div>
                  <div class="opsi-grid">
                      <button class="btn-opsi" id="btn-a" disabled><span class="opsi-label">A</span> <span id="teks-a">...</span></button>
                      <button class="btn-opsi" id="btn-b" disabled><span class="opsi-label">B</span> <span id="teks-b">...</span></button>
                      <button class="btn-opsi" id="btn-c" disabled><span class="opsi-label">C</span> <span id="teks-c">...</span></button>
                      <button class="btn-opsi" id="btn-d" disabled><span class="opsi-label">D</span> <span id="teks-d">...</span></button>
                  </div>
              </div>
          </div>

          <form id="form-submit-quiz" method="POST" style="display:none;">
              <input type="hidden" name="submit_quiz_hasil" value="1">
              <input type="hidden" name="quiz_id" value="<?= $quiz_id_get ?>">
              <input type="hidden" name="benar" id="input-benar" value="0"><input type="hidden" name="total" value="<?= count($list_soal) ?>"><input type="hidden" name="skor_total" id="input-skor" value="0">
          </form>

          <script>
          const soalList = <?php echo json_encode($list_soal); ?>; const totalSoal = soalList.length;
          let currentSoal = 0; let skorBenar = 0; let skorTotalPoin = 0; let timerInterval; let waktuJawab = 0;

          function renderSoal() {
              if (currentSoal >= totalSoal) {
                  document.getElementById('input-benar').value = skorBenar; document.getElementById('input-skor').value = skorTotalPoin;
                  document.getElementById('form-submit-quiz').submit(); return;
              }
              let soal = soalList[currentSoal];
              document.getElementById('indikator-soal').innerText = `Soal ${currentSoal + 1} / ${totalSoal}`;
              document.getElementById('teks-soal').innerText = soal.pertanyaan;
              document.getElementById('teks-a').innerText = soal.opsi_a; document.getElementById('teks-b').innerText = soal.opsi_b;
              document.getElementById('teks-c').innerText = soal.opsi_c; document.getElementById('teks-d').innerText = soal.opsi_d;

              kunciTombol(true);
              let waktuBaca = 3; let timerUI = document.getElementById('timer-display'); timerUI.className = "timer-badge reading"; timerUI.innerHTML = `👀 Baca Soal: ${waktuBaca}s`;
              timerInterval = setInterval(() => {
                  waktuBaca--; timerUI.innerHTML = `👀 Baca Soal: ${waktuBaca}s`;
                  if (waktuBaca <= 0) { clearInterval(timerInterval); mulaiJawab(soal); }
              }, 1000);
          }

          function mulaiJawab(soal) {
              kunciTombol(false); setupPilihanJawaban(soal.kunci);
              waktuJawab = 5; let timerUI = document.getElementById('timer-display'); timerUI.className = "timer-badge"; timerUI.innerHTML = `⏱️ Menjawab: ${waktuJawab}s`;
              timerInterval = setInterval(() => {
                  waktuJawab--; timerUI.innerHTML = `⏱️ Menjawab: ${waktuJawab}s`;
                  if (waktuJawab <= 0) { clearInterval(timerInterval); tampilkanFeedback(false, 0, 'Waktu Habis! ⏰'); }
              }, 1000);
          }

          function kunciTombol(isLocked) {
              ['btn-a', 'btn-b', 'btn-c', 'btn-d'].forEach(id => {
                  let btn = document.getElementById(id); btn.disabled = isLocked; btn.style.opacity = isLocked ? '0.4' : '1';
                  if (!isLocked) { let newBtn = btn.cloneNode(true); btn.parentNode.replaceChild(newBtn, btn); }
              });
          }

          function setupPilihanJawaban(kunciBenar) {
              document.getElementById('btn-a').addEventListener('click', () => prosesJawaban('A', kunciBenar)); document.getElementById('btn-b').addEventListener('click', () => prosesJawaban('B', kunciBenar));
              document.getElementById('btn-c').addEventListener('click', () => prosesJawaban('C', kunciBenar)); document.getElementById('btn-d').addEventListener('click', () => prosesJawaban('D', kunciBenar));
          }

          function prosesJawaban(jawabanSiswa, kunciBenar) {
              clearInterval(timerInterval); kunciTombol(true); 
              let isCorrect = (jawabanSiswa === kunciBenar);
              if (isCorrect) {
                  skorBenar++; let poinDapat = Math.round((waktuJawab / 5) * 1000); if (poinDapat < 100) poinDapat = 100; 
                  skorTotalPoin += poinDapat; document.getElementById('skor-ui').innerText = skorTotalPoin;
                  tampilkanFeedback(true, poinDapat, 'BENAR! 🎉');
              } else { tampilkanFeedback(false, 0, 'SALAH! 😢'); }
          }

          function tampilkanFeedback(isCorrect, poin, pesan) {
              let overlay = document.createElement('div'); overlay.className = isCorrect ? 'feedback-overlay correct' : 'feedback-overlay wrong';
              let kunciHuruf = soalList[currentSoal].kunci; let teksKunci = '';
              if(kunciHuruf === 'A') teksKunci = soalList[currentSoal].opsi_a; if(kunciHuruf === 'B') teksKunci = soalList[currentSoal].opsi_b;
              if(kunciHuruf === 'C') teksKunci = soalList[currentSoal].opsi_c; if(kunciHuruf === 'D') teksKunci = soalList[currentSoal].opsi_d;

              if (isCorrect) { overlay.innerHTML = `<h1>${pesan}</h1><p>+${poin} Poin Kecepatan</p>`; } 
              else { overlay.innerHTML = `<h1>${pesan}</h1><p style="font-size:16px;">Jawaban yang benar adalah:</p><div class="correct-ans-box">${kunciHuruf}. ${teksKunci}</div>`; }
              document.body.appendChild(overlay);
              setTimeout(() => { document.body.removeChild(overlay); currentSoal++; renderSoal(); }, 2000);
          }
          window.onload = renderSoal;
          </script>
      <?php elseif ($status_kuis === 'selesai'): ?>
          <div class="card quiz-container" style="padding: 60px 20px;">
              <h2 style="font-size: 28px; margin-bottom:10px; color:#C62828;">Yah, Kuis Sudah Ditutup! 😢</h2>
              <p style="font-size: 16px; color:var(--sage-600); margin-bottom: 20px;">Guru telah mengakhiri sesi kuis ini.</p>
              <a href='murid.php?page=quiz' class='btn-yellow'>Kembali ke Daftar Kuis</a>
          </div>
      <?php endif; ?>

  <?php break; default: ?>
      <div class="card" style="background:linear-gradient(135deg,var(--sage-50) 0%,#FFFFFF 100%); border:1px solid var(--sage-100); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
          <div><small style="color:var(--sage-600);font-weight:800;text-transform:uppercase;letter-spacing:1px;">Dashboard Siswa</small><h1 style="font-size:28px;color:var(--sage-800);margin:8px 0;font-weight:800;letter-spacing:-0.5px;">Halo, <?= e($nama_murid) ?> 👋</h1><p style="font-size:14.5px;color:var(--sage-600);line-height:1.6;max-width:550px;font-weight:500;">Selamat datang di SLearning SMK Negeri 7 Batam.</p></div>
      </div>
      
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:20px;margin-bottom:36px;">
          <div class="card" style="padding:24px;display:flex;align-items:center;gap:18px;margin-bottom:0;">
              <div style="width:56px;height:56px;border-radius:16px;background:var(--nav-yellow);display:flex;align-items:center;justify-content:center;color:var(--sage-800);font-size:24px;">📝</div>
              <div><h3 style="font-size:24px;font-weight:800;color:var(--sage-800);margin-bottom:2px;"><?= (int)$stat_tugas_aktif ?></h3><p style="font-size:13px;color:var(--sage-600);font-weight:600;">Tugas Aktif</p></div>
          </div>
          <div class="card" style="padding:24px;display:flex;align-items:center;gap:18px;margin-bottom:0;">
              <div style="width:56px;height:56px;border-radius:16px;background:var(--nav-yellow);display:flex;align-items:center;justify-content:center;color:var(--sage-800);font-size:24px;">🎮</div>
              <div><h3 style="font-size:24px;font-weight:800;color:var(--sage-800);margin-bottom:2px;"><?= (int)$stat_quiz ?></h3><p style="font-size:13px;color:var(--sage-600);font-weight:600;">Quiz Tersedia</p></div>
          </div>
      </div>
  <?php endswitch; ?>
</main>
<?php render_flash_notification(); ?>
</body>
</html>
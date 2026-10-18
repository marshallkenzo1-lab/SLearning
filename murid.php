<?php
// SLearning — Dashboard Murid (Single File Multi-Page Terintegrasi Penuh)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (($_SESSION['jenis'] ?? '') === 'guru') { header("Location: guru.php"); exit; }

$uid = (int)$_SESSION['user_id'];
$s = mysqli_prepare($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($s, "i", $uid); mysqli_stmt_execute($s);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
if (!$user) { header("Location: logout.php"); exit; }
foreach (['nama','username','email','jenis','level'] as $k) $_SESSION[$k] = $user[$k] ?? '';
$nama_murid = $user['nama']; $username = $user['username'];

// Helper Flash & Pop-up Notifikasi SweetAlert
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
                    Swal.fire({
                        title: '{$m['title']}',
                        text: '{$m['text']}',
                        icon: '{$m['icon']}',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#6D8C6D'
                    });
                });
              </script>";
    }
}

// Tangkap Halaman Aktif dari URL (?page=...)
$page = $_GET['page'] ?? 'beranda';

// 1. PROSES GABUNG KELAS & NOTIFIKASI OTOMATIS
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gabung_kelas'])) {
    $kode = strtoupper(trim($_POST['kode_gabung'] ?? ''));
    if ($kode==='') {
        set_flash_notification('Masukkan kode gabung kelas terlebih dahulu.', 'error', 'Perhatian');
    } else {
        $s = mysqli_prepare($koneksi, "SELECT id, nama_kelas FROM kelas WHERE kode_gabung=? LIMIT 1");
        mysqli_stmt_bind_param($s,'s',$kode); mysqli_stmt_execute($s);
        $kls = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
        if (!$kls) {
            set_flash_notification('Kode kelas tidak ditemukan. Cek kembali kode dari guru.', 'error', 'Gagal');
        } else {
            $kelas_id = $kls['id'];
            $s = mysqli_prepare($koneksi, "SELECT id FROM anggota_kelas WHERE kelas_id=? AND user_id=? LIMIT 1");
            mysqli_stmt_bind_param($s,'ii',$kelas_id,$uid); mysqli_stmt_execute($s);
            $ada = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if ($ada) {
                set_flash_notification('Kamu sudah tergabung di kelas tersebut.', 'warning', 'Informasi');
            } else {
                $s = mysqli_prepare($koneksi, "INSERT INTO anggota_kelas (kelas_id, user_id) VALUES (?,?)");
                mysqli_stmt_bind_param($s,'ii',$kelas_id,$uid);
                if (mysqli_stmt_execute($s)) {
                    $cek_tugas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM tugas WHERE kelas_id = $kelas_id"))['jml'] ?? 0;
                    $cek_absen = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM absensi WHERE kelas_id = $kelas_id"))['jml'] ?? 0;
                    
                    $info_tambahan = "";
                    if ($cek_tugas > 0 || $cek_absen > 0) {
                        $info_tambahan = " Di kelas ini sudah ada " . ($cek_tugas > 0 ? "$cek_tugas tugas" : "") . ($cek_tugas > 0 && $cek_absen > 0 ? " dan " : "") . ($cek_absen > 0 ? "$cek_absen sesi absensi" : "") . " yang siap dikerjakan!";
                    }
                    set_flash_notification('Berhasil bergabung ke kelas ' . $kls['nama_kelas'] . '.' . $info_tambahan, 'success', 'Berhasil Masuk Kelas!');
                } else {
                    set_flash_notification('Gagal bergabung ke kelas.', 'error', 'Kesalahan');
                }
                mysqli_stmt_close($s);
            }
        }
    }
    header('Location: murid.php?page=kelas'); exit;
}

// 2. PROSES ISI ABSENSI
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['isi_absen'])) {
    $aid = (int)($_POST['absensi_id'] ?? 0);
    $status = $_POST['status'] ?? 'hadir';
    $ket = trim($_POST['keterangan'] ?? '');
    $s = mysqli_prepare($koneksi, "SELECT a.id FROM absensi a JOIN anggota_kelas ak ON ak.kelas_id=a.kelas_id WHERE a.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$aid,$uid); mysqli_stmt_execute($s);
    $ok = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($ok) {
        $s = mysqli_prepare($koneksi, "INSERT INTO absensi_hadir (absensi_id, user_id, status, keterangan) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), keterangan=VALUES(keterangan), waktu=CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($s,'iiss',$aid,$uid,$status,$ket);
        mysqli_stmt_execute($s); mysqli_stmt_close($s);
        set_flash_notification('Kehadiran berhasil dicatat.', 'success', 'Absen Berhasil');
    }
    header('Location: murid.php?page=absensi'); exit;
}

// 3. PROSES UPLOAD / KUMPULKAN TUGAS
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['kumpulkan_tugas'])) {
    $tid = (int)($_POST['tugas_id'] ?? 0);
    $s = mysqli_prepare($koneksi, "SELECT t.id FROM tugas t JOIN anggota_kelas ak ON ak.kelas_id=t.kelas_id WHERE t.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$tid,$uid); mysqli_stmt_execute($s);
    $valid = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    
    if (!$valid) {
        set_flash_notification('Tugas tidak ditemukan atau akses ditolak.', 'error', 'Gagal');
    } elseif (!isset($_FILES['file_tugas']) || $_FILES['file_tugas']['error']!==UPLOAD_ERR_OK) {
        set_flash_notification('Pilih file tugas terlebih dahulu.', 'error', 'Perhatian');
    } else {
        $f = $_FILES['file_tugas']; $maks = 10*1024*1024;
        $allow = ['pdf','doc','docx','ppt','pptx','zip','rar','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        
        if ($f['size'] > $maks) {
            set_flash_notification('Ukuran file maksimal 10 MB.', 'error', 'File Terlalu Besar');
        } elseif (!in_array($ext, $allow, true)) {
            set_flash_notification('Format file tidak diizinkan. Gunakan PDF, DOC, PPT, ZIP, RAR, atau Gambar.', 'error', 'Format Salah');
        } else {
            $dir = __DIR__.'/uploads/tugas/'; 
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $baru = 'tugas_'.$uid.'_'.$tid.'_'.time().'.'.$ext; 
            $lok = $dir.$baru; 
            $dbp = 'uploads/tugas/'.$baru;
            
            if (move_uploaded_file($f['tmp_name'], $lok)) {
                $s = mysqli_prepare($koneksi, "SELECT id, file_path FROM pengumpulan_tugas WHERE tugas_id=? AND user_id=? LIMIT 1");
                mysqli_stmt_bind_param($s,'ii',$tid,$uid); mysqli_stmt_execute($s);
                $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
                
                if ($lama) {
                    if (is_file(__DIR__.'/'.$lama['file_path'])) @unlink(__DIR__.'/'.$lama['file_path']);
                    $s = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nama_file=?, file_path=?, nilai=NULL, feedback=NULL, dikumpulkan_at=CURRENT_TIMESTAMP WHERE id=?");
                    mysqli_stmt_bind_param($s,'ssi',$f['name'],$dbp,$lama['id']);
                } else {
                    $s = mysqli_prepare($koneksi, "INSERT INTO pengumpulan_tugas (tugas_id, user_id, nama_file, file_path) VALUES (?,?,?,?)");
                    mysqli_stmt_bind_param($s,'iiss',$tid,$uid,$f['name'],$dbp);
                }
                
                if (mysqli_stmt_execute($s)) {
                    set_flash_notification('Tugas berhasil dikumpulkan!', 'success', 'Berhasil');
                } else {
                    set_flash_notification('Gagal Database: ' . mysqli_stmt_error($s), 'error', 'Kesalahan Sistem');
                }
                mysqli_stmt_close($s);
            } else {
                set_flash_notification('Gagal mengupload file ke server. Periksa folder uploads/tugas/.', 'error', 'Gagal');
            }
        }
    }
    header('Location: murid.php?page=tugas&detail_tugas='.$tid); exit;
}

// Data Pendukung & Statistik
$daftar_kelas = [];
$s = mysqli_prepare($koneksi, "SELECT k.id, k.nama_kelas, k.kode_gabung, (SELECT nama FROM login l WHERE l.id=k.guru_id) AS nama_guru FROM anggota_kelas ak JOIN kelas k ON k.id=ak.kelas_id WHERE ak.user_id=? ORDER BY k.id DESC");
mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
$rr = mysqli_stmt_get_result($s); while ($r=mysqli_fetch_assoc($rr)) $daftar_kelas[]=$r;
mysqli_stmt_close($s);
$kelas_ids = array_column($daftar_kelas,'id');
$in_kelas = $kelas_ids ? implode(',', array_map('intval',$kelas_ids)) : '0';

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
:root{--nav-yellow:#FFF8D6;--nav-yellow-dark:#F2E7B5;--sage-body:#F4F7F5;--sage-50:#E8F0E8;--sage-100:#D1E0D1;--sage-500:#6D8C6D;--sage-600:#577357;--sage-800:#2F402F;--sage-900:#1D261D;--radius-md:16px;--radius-lg:24px}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}
body{background:var(--sage-body);color:var(--sage-900);min-height:100vh;padding-bottom:40px}
.navbar{background:linear-gradient(180deg,var(--nav-yellow),#FFFDF6);border-bottom:1px solid var(--nav-yellow-dark);position:sticky;top:0;z-index:100}
.nav-inner{max-width:1200px;margin:auto;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px}
.brand{display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--sage-800)}
.brand img{width:40px;height:40px}
.nav-links{display:flex;gap:6px;flex-wrap:wrap}
.nav-links a{padding:10px 14px;border-radius:10px;color:var(--sage-600);text-decoration:none;font-size:13px;font-weight:700;transition:.2s}
.nav-links a:hover,.nav-links a.active{background:#fff;color:var(--sage-900);box-shadow:0 4px 12px rgba(109,140,109,.05)}
.container{max-width:1200px;margin:30px auto;padding:0 28px}
.card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-lg);padding:24px;box-shadow:0 4px 12px rgba(109,140,109,.05);margin-bottom:20px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:50px;padding:11px 18px;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none}
.btn-yellow{background:var(--sage-500);color:#fff}
.form-control{width:100%;padding:12px 14px;border:1px solid var(--sage-100);border-radius:10px;font-size:13px;outline:none}
.class-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:16px}
.class-card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);padding:22px;box-shadow:0 4px 12px rgba(109,140,109,.05)}
</style>
</head>
<body>

<header class="navbar"><div class="nav-inner">
  <a href="murid.php?page=beranda" class="brand">
    <img src="assets/img/logo-smkn7.png" alt="Logo SMKN 7">
    <div>
      <strong style="font-size:18px;display:block">SLearning</strong>
      <span style="font-size:10px;color:var(--sage-600);font-weight:600">SMKN 7 Batam</span>
    </div>
  </a>
  <nav class="nav-links">
    <a href="murid.php?page=beranda" class="<?= $page==='beranda'?'active':'' ?>"><?= svg_icon('home', 16) ?> Beranda</a>
    <a href="murid.php?page=kelas" class="<?= in_array($page,['kelas','detail_kelas'])?'active':'' ?>"><?= svg_icon('book', 16) ?> Kelas</a>
    <a href="murid.php?page=absensi" class="<?= $page==='absensi'?'active':'' ?>"><?= svg_icon('calendar', 16) ?> Absensi</a>
    <a href="murid.php?page=tugas" class="<?= in_array($page,['tugas','detail_tugas'])?'active':'' ?>"><?= svg_icon('clipboard', 16) ?> Tugas</a>
    <a href="murid.php?page=quiz" class="<?= $page==='quiz'?'active':'' ?>"><?= svg_icon('help', 16) ?> Quiz</a>
  </nav>
  <div>
    <span style="font-size:12px;font-weight:700;margin-right:10px;color:var(--sage-800)"><?= e($nama_murid) ?></span>
    <a href="logout.php" class="btn" style="background:#FFF3F0;color:#C35D4A;padding:8px 14px;font-size:11px"><?= svg_icon('logout', 14) ?> Keluar</a>
  </div>
</div></header>

<main class="container">
  <?php 
  switch($page): 

      case 'kelas': 
  ?>
      <div class="card" style="border-left:4px solid var(--sage-500)">
          <h2 style="font-size:19px;margin-bottom:6px">Manajemen & Kode Gabung Kelas</h2>
          <p style="font-size:12px;color:var(--sage-600);margin-bottom:14px">Masukkan kode kelas yang diberikan oleh guru untuk bergabung.</p>
          <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap">
              <input type="text" name="kode_gabung" class="form-control" placeholder="cth: KLS-AB12C" required style="flex:1;min-width:200px;text-transform:uppercase">
              <button type="submit" name="gabung_kelas" class="btn btn-yellow"><?= svg_icon('plus', 16) ?> Gabung Kelas</button>
          </form>
      </div>

      <h3 style="font-size:17px;margin:20px 0 12px">Daftar Kelas Saya (<?= count($daftar_kelas) ?>)</h3>
      <?php if (empty($daftar_kelas)): ?>
          <div style="padding:32px;background:#fff;border:1px dashed var(--sage-100);border-radius:var(--radius-md);text-align:center;font-size:13px;color:var(--sage-600)">Belum bergabung ke kelas mana pun. Masukkan kode dari guru di atas.</div>
      <?php else: ?>
          <div class="class-grid">
              <?php foreach ($daftar_kelas as $kls): ?>
              <article class="class-card">
                  <h4 style="font-size:16px;font-weight:800;color:var(--sage-800);margin-bottom:6px"><?= e($kls['nama_kelas']) ?></h4>
                  <p style="font-size:12px;color:var(--sage-600);margin-bottom:12px">Pengajar: <?= e($kls['nama_guru'] ?? 'Guru Pengampu') ?></p>
                  <div style="background:var(--nav-yellow);padding:6px 10px;border-radius:8px;font-size:11px;font-weight:800;display:inline-block;margin-bottom:12px">Kode: <?= e($kls['kode_gabung']) ?></div>
                  <div><a href="murid.php?page=detail_kelas&id=<?= (int)$kls['id'] ?>" class="btn btn-yellow" style="width:100%;font-size:11px;padding:8px">Buka Ruang Kelas <?= svg_icon('arrow-right', 14) ?></a></div>
              </article>
              <?php endforeach; ?>
          </div>
      <?php endif; ?>

  <?php 
      break; 
      case 'detail_kelas':
          $kid = (int)($_GET['id'] ?? 0);
          $s = mysqli_prepare($koneksi, "SELECT k.*, (SELECT nama FROM login l WHERE l.id=k.guru_id) AS nama_guru FROM kelas k JOIN anggota_kelas ak ON ak.kelas_id=k.id WHERE k.id=? AND ak.user_id=? LIMIT 1");
          mysqli_stmt_bind_param($s, 'ii', $kid, $uid); mysqli_stmt_execute($s);
          $kelas_detail = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
  ?>
      <a href="murid.php?page=kelas" class="btn" style="background:#fff;border:1px solid var(--sage-100);color:var(--sage-800);margin-bottom:16px"><?= svg_icon('arrow-left', 14) ?> Kembali ke Daftar Kelas</a>
      <?php if(!$kelas_detail): ?>
          <div class="card"><p style="color:red">Kelas tidak ditemukan atau Anda tidak memiliki akses ke kelas ini.</p></div>
      <?php else: ?>
          <div class="card" style="background:linear-gradient(135deg,var(--sage-50),#fff)">
              <h2 style="font-size:22px;color:var(--sage-800);margin-bottom:6px"><?= e($kelas_detail['nama_kelas']) ?></h2>
              <p style="font-size:13px;color:var(--sage-600)">Pengajar: <strong><?= e($kelas_detail['nama_guru'] ?? 'Guru Pengampu') ?></strong> • Kode Gabung: <strong><?= e($kelas_detail['kode_gabung']) ?></strong></p>
          </div>
          <div class="card">
              <h3 style="font-size:17px;margin-bottom:12px">Tugas di Kelas Ini</h3>
              <?php
              $t_kelas = mysqli_query($koneksi, "SELECT t.*, pt.nama_file FROM tugas t LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=$uid WHERE t.kelas_id=$kid ORDER BY t.id DESC");
              if(mysqli_num_rows($t_kelas) === 0):
          ?>
              <p style="font-size:13px;color:var(--sage-600)">Belum ada tugas untuk kelas ini.</p>
              <?php else: while($tk = mysqli_fetch_assoc($t_kelas)): ?>
                  <div style="background:#fafafa;padding:14px;border-radius:10px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                      <div>
                          <strong style="font-size:14px"><?= e($tk['judul_tugas']) ?></strong>
                          <div style="font-size:12px;color:var(--sage-600)">Deadline: <?= $tk['deadline'] ? e(tanggal_id($tk['deadline'])) : 'Tanpa Batas' ?></div>
                      </div>
                      <div style="display:flex;align-items:center;gap:10px">
                          <span style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;background:<?= !empty($tk['nama_file'])?'#E8F5E9':'#FFF8D6' ?>;color:<?= !empty($tk['nama_file'])?'#2E7D32':'#77651F' ?>">
                              <?= !empty($tk['nama_file']) ? 'Sudah Dikumpul' : 'Belum Kumpul' ?>
                          </span>
                          <a href="murid.php?page=tugas&detail_tugas=<?= $tk['id'] ?>" class="btn btn-yellow" style="padding:6px 12px;font-size:11px">Kelola Tugas</a>
                      </div>
                  </div>
              <?php endwhile; endif; ?>
          </div>
      <?php endif; ?>

  <?php break; case 'absensi': ?>
      <div class="card">
          <h2 style="font-size:19px;margin-bottom:6px">Absensi Kehadiran Murid</h2>
          <p style="font-size:12px;color:var(--sage-600);margin-bottom:14px">Pilih status kehadiran pada sesi yang sedang dibuka oleh guru.</p>
          <?php 
          $daftar_absen = [];
          $q = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas AS kelas, ah.status FROM absensi a JOIN kelas k ON k.id=a.kelas_id LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=$uid WHERE a.kelas_id IN ($in_kelas) ORDER BY a.tanggal DESC LIMIT 10");
          while($q && $r=mysqli_fetch_assoc($q)) $daftar_absen[] = $r;
          ?>
          <?php if(empty($daftar_absen)): ?>
              <div style="padding:24px;text-align:center;font-size:13px;color:var(--sage-600)">Belum ada sesi absensi aktif dari kelas yang diikuti.</div>
          <?php else: foreach($daftar_absen as $ab): ?>
              <div style="background:#fafafa;padding:16px;border-radius:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                  <div>
                      <strong style="font-size:14px;color:var(--sage-800)"><?= e($ab['judul_absensi']) ?></strong>
                      <div style="font-size:12px;color:var(--sage-600)"><?= e($ab['kelas']) ?> • <?= e(tanggal_id($ab['tanggal'], false)) ?></div>
                  </div>
                  <?php if($ab['status']): ?>
                      <span style="background:#E8F5E9;color:#2E7D32;padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700">Sudah Absen (<?= ucfirst($ab['status']) ?>)</span>
                  <?php else: ?>
                      <form method="POST" style="display:flex;gap:6px">
                          <input type="hidden" name="absensi_id" value="<?= $ab['id'] ?>">
                          <select name="status" class="form-control" style="padding:6px 10px;font-size:12px"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select>
                          <button type="submit" name="isi_absen" class="btn btn-yellow" style="padding:6px 14px;font-size:11px">Kirim</button>
                      </form>
                  <?php endif; ?>
              </div>
          <?php endforeach; endif; ?>
      </div>

  <?php break; case 'tugas': 
      $detail_tugas_id = (int)($_GET['detail_tugas'] ?? 0);
  ?>
      <?php if($detail_tugas_id > 0): 
          $s = mysqli_prepare($koneksi, "SELECT t.*, k.nama_kelas AS mapel, pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=? WHERE t.id=? LIMIT 1");
          mysqli_stmt_bind_param($s,'ii',$uid,$detail_tugas_id); mysqli_stmt_execute($s);
          $td = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
      ?>
          <a href="murid.php?page=tugas" class="btn" style="background:#fff;border:1px solid var(--sage-100);color:var(--sage-800);margin-bottom:16px"><?= svg_icon('arrow-left', 14) ?> Kembali ke Daftar Tugas</a>
          <?php if(!$td): ?><div class="card"><p style="color:red">Tugas tidak ditemukan.</p></div>
          <?php else: ?>
              <div class="card">
                  <h2 style="font-size:19px;margin-bottom:4px"><?= e($td['judul_tugas']) ?></h2>
                  <p style="font-size:12px;color:var(--sage-600);margin-bottom:12px"><?= e($td['mapel']) ?> • Deadline: <?= $td['deadline'] ? e(tanggal_id($td['deadline'])) : 'Tanpa batas' ?></p>
                  <?php if(!empty($td['deskripsi'])): ?>
                      <div style="background:#fafafa;padding:14px;border-radius:10px;font-size:13px;margin-bottom:14px;line-height:1.6"><?= nl2br(e($td['deskripsi'])) ?></div>
                  <?php endif; ?>

                  <?php if(!empty($td['nama_file'])): ?>
                      <div style="background:#E8F5E9;color:#2E7D32;padding:12px;border-radius:10px;font-size:12px;margin-bottom:14px">
                          Terkumpul: <strong><?= e($td['nama_file']) ?></strong> (<?= e(tanggal_id($td['dikumpulkan_at'])) ?>)
                          <?php if($td['nilai']!==null) echo '<br>Nilai: <strong>'.(int)$td['nilai'].'</strong>'; ?>
                          <?php if(!empty($td['feedback'])) echo '<br>Catatan Guru: '.e($td['feedback']); ?>
                      </div>
                  <?php endif; ?>

                  <form method="POST" enctype="multipart/form-data" style="background:#fafafa;border:1px dashed var(--sage-100);padding:18px;border-radius:12px">
                      <input type="hidden" name="tugas_id" value="<?= $td['id'] ?>">
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:8px"><?= !empty($td['nama_file']) ? 'Ganti File Tugas' : 'Pilih File Tugas' ?></label>
                      <input type="file" name="file_tugas" class="form-control" required style="background:#fff;margin-bottom:10px">
                      <p style="font-size:11px;color:var(--sage-600);margin-bottom:12px">Maksimal 10 MB • PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR, JPG, PNG.</p>
                      <button type="submit" name="kumpulkan_tugas" class="btn btn-yellow"><?= svg_icon('upload', 14) ?> <?= !empty($td['nama_file']) ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas' ?></button>
                  </form>
              </div>
          <?php endif; ?>
      <?php else: ?>
          <div class="card">
              <h2 style="font-size:19px;margin-bottom:6px">Daftar Tugas Murid</h2>
              <p style="font-size:12px;color:var(--sage-600);margin-bottom:14px">Kerjakan dan kumpulkan tugas dari kelas yang Anda ikuti.</p>
              <?php
              $daftar_tugas = [];
              $q = mysqli_query($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, k.nama_kelas AS mapel, pt.nama_file FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=$uid WHERE t.kelas_id IN ($in_kelas) ORDER BY t.deadline ASC LIMIT 10");
              while($q && $r=mysqli_fetch_assoc($q)) $daftar_tugas[] = $r;
              ?>
              <?php if(empty($daftar_tugas)): ?>
                  <div style="padding:24px;text-align:center;font-size:13px;color:var(--sage-600)">Belum ada tugas tersedia.</div>
              <?php else: foreach($daftar_tugas as $t): ?>
                  <div style="background:#fafafa;padding:16px;border-radius:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                      <div>
                          <strong style="font-size:14px;color:var(--sage-800)"><?= e($t['judul']) ?></strong>
                          <div style="font-size:12px;color:var(--sage-600)"><?= e($t['mapel']) ?> • Deadline: <?= $t['deadline'] ? e(tanggal_id($t['deadline'])) : 'Tanpa Batas' ?></div>
                      </div>
                      <div style="display:flex;align-items:center;gap:10px">
                          <span style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;background:<?= !empty($t['nama_file'])?'#E8F5E9':'#FFF8D6' ?>;color:<?= !empty($t['nama_file'])?'#2E7D32':'#77651F' ?>">
                              <?= !empty($t['nama_file']) ? 'Sudah Dikumpul' : 'Belum Kumpul' ?>
                          </span>
                          <a href="murid.php?page=tugas&detail_tugas=<?= $t['id'] ?>" class="btn btn-yellow" style="padding:6px 12px;font-size:11px">Buka</a>
                      </div>
                  </div>
              <?php endforeach; endif; ?>
          </div>
      <?php endif; ?>

  <?php break; case 'quiz': ?>
      <div class="card">
          <h2 style="font-size:19px;margin-bottom:6px">Quiz / Evaluasi Pembelajaran</h2>
          <p style="font-size:12px;color:var(--sage-600);margin-bottom:14px">Kuis interaktif dari pengajar kelas.</p>
          <div style="padding:24px;text-align:center;font-size:13px;color:var(--sage-600)">Belum ada kuis baru yang dipublikasikan.</div>
      </div>

  <?php break; default: // Beranda ?>
      <div class="card" style="background:linear-gradient(135deg,var(--sage-50),#fff)">
          <small style="color:var(--sage-600);font-weight:800;text-transform:uppercase">Dashboard Murid</small>
          <h1 style="font-size:26px;color:var(--sage-800);margin:8px 0">Halo, <?= e($nama_murid) ?></h1>
          <p style="font-size:14px;color:var(--sage-600);line-height:1.6">Selamat datang di SLearning SMK Negeri 7 Batam. Pilih menu navigasi di atas untuk berpindah halaman secara instan.</p>
      </div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">
          <div class="card">
              <h3 style="font-size:16px;margin-bottom:4px">Tugas Aktif</h3>
              <p style="font-size:22px;font-weight:800;color:var(--sage-800)"><?= (int)$stat_tugas_aktif ?></p>
          </div>
          <div class="card">
              <h3 style="font-size:16px;margin-bottom:4px">Quiz Tersedia</h3>
              <p style="font-size:22px;font-weight:800;color:var(--sage-800)"><?= (int)$stat_quiz ?></p>
          </div>
      </div>
  <?php endswitch; ?>
</main>

<?php 
// Eksekusi pop-up notifikasi SweetAlert
render_flash_notification(); 
?>
</body>
</html>
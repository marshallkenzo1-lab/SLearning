<?php
// SLearning — Dashboard Murid (DB-driven penuh)
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

function murid_flash($m,$t='success'){ $_SESSION['m_msg']=$m; $_SESSION['m_type']=$t; }
$pesan_kelas = $_SESSION['m_kelas'] ?? ''; $tipe_kelas = $_SESSION['m_kelas_t'] ?? '';
$pesan_tugas = $_SESSION['m_tugas'] ?? ''; $tipe_tugas = $_SESSION['m_tugas_t'] ?? '';
$pesan_absen = $_SESSION['m_absen'] ?? ''; $tipe_absen = $_SESSION['m_absen_t'] ?? '';
unset($_SESSION['m_kelas'],$_SESSION['m_kelas_t'],$_SESSION['m_tugas'],$_SESSION['m_tugas_t'],$_SESSION['m_absen'],$_SESSION['m_absen_t']);

// Gabung kelas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['gabung_kelas'])) {
    $kode = strtoupper(trim($_POST['kode_gabung'] ?? ''));
    if ($kode==='') { $_SESSION['m_kelas']='Masukkan kode gabung kelas terlebih dahulu.'; $_SESSION['m_kelas_t']='error'; }
    else {
        $s = mysqli_prepare($koneksi, "SELECT id, nama_kelas FROM kelas WHERE kode_gabung=? LIMIT 1");
        mysqli_stmt_bind_param($s,'s',$kode); mysqli_stmt_execute($s);
        $kls = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
        if (!$kls) { $_SESSION['m_kelas']='Kode kelas tidak ditemukan. Cek kembali kode dari guru.'; $_SESSION['m_kelas_t']='error'; }
        else {
            $s = mysqli_prepare($koneksi, "SELECT id FROM anggota_kelas WHERE kelas_id=? AND user_id=? LIMIT 1");
            mysqli_stmt_bind_param($s,'ii',$kls['id'],$uid); mysqli_stmt_execute($s);
            $ada = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if ($ada) { $_SESSION['m_kelas']='Kamu sudah tergabung di kelas tersebut.'; $_SESSION['m_kelas_t']='error'; }
            else {
                $s = mysqli_prepare($koneksi, "INSERT INTO anggota_kelas (kelas_id, user_id) VALUES (?,?)");
                mysqli_stmt_bind_param($s,'ii',$kls['id'],$uid);
                if (mysqli_stmt_execute($s)) { $_SESSION['m_kelas']='Berhasil bergabung ke kelas '.$kls['nama_kelas'].'.'; $_SESSION['m_kelas_t']='success'; }
                else { $_SESSION['m_kelas']='Gagal bergabung ke kelas.'; $_SESSION['m_kelas_t']='error'; }
                mysqli_stmt_close($s);
            }
        }
    }
    header('Location: murid.php#kelas'); exit;
}

// Isi absen
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['isi_absen'])) {
    $aid = (int)($_POST['absensi_id'] ?? 0);
    $status = $_POST['status'] ?? 'hadir';
    if (!in_array($status,['hadir','izin','sakit','alpa'],true)) $status='hadir';
    $ket = trim($_POST['keterangan'] ?? '');
    // pastikan sesi milik kelas yang diikuti
    $s = mysqli_prepare($koneksi, "SELECT a.id FROM absensi a JOIN anggota_kelas ak ON ak.kelas_id=a.kelas_id WHERE a.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$aid,$uid); mysqli_stmt_execute($s);
    $ok = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if (!$ok) { $_SESSION['m_absen']='Sesi absensi tidak valid.'; $_SESSION['m_absen_t']='error'; }
    else {
        $s = mysqli_prepare($koneksi, "INSERT INTO absensi_hadir (absensi_id, user_id, status, keterangan) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), keterangan=VALUES(keterangan), waktu=CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($s,'iiss',$aid,$uid,$status,$ket);
        if (mysqli_stmt_execute($s)) { $_SESSION['m_absen']='Kehadiran tersimpan ('.ucfirst($status).').'; $_SESSION['m_absen_t']='success'; }
        else { $_SESSION['m_absen']='Gagal menyimpan kehadiran.'; $_SESSION['m_absen_t']='error'; }
        mysqli_stmt_close($s);
    }
    header('Location: murid.php#absensi'); exit;
}

// Upload tugas
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['kumpulkan_tugas'])) {
    $tid = (int)($_POST['tugas_id'] ?? 0);
    $s = mysqli_prepare($koneksi, "SELECT t.id FROM tugas t JOIN anggota_kelas ak ON ak.kelas_id=t.kelas_id WHERE t.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$tid,$uid); mysqli_stmt_execute($s);
    $valid = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if (!$valid) { $_SESSION['m_tugas']='Tugas tidak ditemukan.'; $_SESSION['m_tugas_t']='error'; header('Location: murid.php#tugas'); exit; }
    if (!isset($_FILES['file_tugas']) || $_FILES['file_tugas']['error']!==UPLOAD_ERR_OK) { $_SESSION['m_tugas']='Pilih file terlebih dahulu.'; $_SESSION['m_tugas_t']='error'; header('Location: murid.php?tugas='.$tid.'#pengumpulan'); exit; }
    $f = $_FILES['file_tugas']; $maks = 10*1024*1024;
    $allow = ['pdf','doc','docx','ppt','pptx','zip','rar','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($f['size']>$maks) { $_SESSION['m_tugas']='Ukuran file maksimal 10 MB.'; $_SESSION['m_tugas_t']='error'; header('Location: murid.php?tugas='.$tid.'#pengumpulan'); exit; }
    if (!in_array($ext,$allow,true)) { $_SESSION['m_tugas']='Format tidak diizinkan. Gunakan PDF, DOC, PPT, ZIP, RAR, atau gambar.'; $_SESSION['m_tugas_t']='error'; header('Location: murid.php?tugas='.$tid.'#pengumpulan'); exit; }
    $dir = __DIR__.'/uploads/tugas/'; if (!is_dir($dir)) mkdir($dir,0777,true);
    $baru = 'tugas_'.$uid.'_'.$tid.'_'.time().'.'.$ext; $lok = $dir.$baru; $dbp = 'uploads/tugas/'.$baru;
    if (!move_uploaded_file($f['tmp_name'],$lok)) { $_SESSION['m_tugas']='File gagal diupload.'; $_SESSION['m_tugas_t']='error'; header('Location: murid.php?tugas='.$tid.'#pengumpulan'); exit; }
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
    if (mysqli_stmt_execute($s)) { $_SESSION['m_tugas']='Tugas berhasil dikumpulkan.'; $_SESSION['m_tugas_t']='success'; }
    else { if (is_file($lok)) @unlink($lok); $_SESSION['m_tugas']='Gagal menyimpan ke database.'; $_SESSION['m_tugas_t']='error'; }
    mysqli_stmt_close($s);
    header('Location: murid.php?tugas='.$tid.'#pengumpulan'); exit;
}

// Data kelas saya
$daftar_kelas = [];
$s = mysqli_prepare($koneksi, "SELECT k.id, k.nama_kelas, k.kode_gabung, (SELECT nama FROM login l WHERE l.id=k.guru_id) AS nama_guru FROM anggota_kelas ak JOIN kelas k ON k.id=ak.kelas_id WHERE ak.user_id=? ORDER BY k.id DESC");
mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
$rr = mysqli_stmt_get_result($s); while ($r=mysqli_fetch_assoc($rr)) $daftar_kelas[]=$r;
mysqli_stmt_close($s);
$kelas_ids = array_column($daftar_kelas,'id');
$in_kelas = $kelas_ids ? implode(',', array_map('intval',$kelas_ids)) : '0';

// Tugas dari kelas saya
$daftar_tugas = [];
$q = mysqli_query($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, t.deskripsi, k.nama_kelas AS mapel, k.id AS kelas_id,
    pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback
    FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=$uid
    WHERE t.kelas_id IN ($in_kelas) ORDER BY t.deadline IS NULL, t.deadline ASC, t.id DESC LIMIT 12");
while ($q && $r=mysqli_fetch_assoc($q)) $daftar_tugas[$r['id']]=$r;

// Quiz dari kelas saya
$daftar_quiz = [];
$q = mysqli_query($koneksi, "SELECT q.id, q.judul, q.deskripsi, q.durasi_menit, k.nama_kelas AS mapel,
    (SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jml_soal,
    qh.skor, qh.benar, qh.total, qh.dikerjakan_at
    FROM quiz q JOIN kelas k ON k.id=q.kelas_id LEFT JOIN quiz_hasil qh ON qh.quiz_id=q.id AND qh.user_id=$uid
    WHERE q.kelas_id IN ($in_kelas) ORDER BY q.id DESC LIMIT 9");
while ($q && $r=mysqli_fetch_assoc($q)) $daftar_quiz[]=$r;

// Absensi terbuka di kelas saya
$daftar_absen = [];
$q = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas AS kelas, ah.status, ah.waktu
    FROM absensi a JOIN kelas k ON k.id=a.kelas_id LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=$uid
    WHERE a.kelas_id IN ($in_kelas) ORDER BY a.tanggal DESC, a.id DESC LIMIT 8");
while ($q && $r=mysqli_fetch_assoc($q)) $daftar_absen[]=$r;

// Statistik
$stat_tugas_aktif = 0; $stat_selesai = 0;
foreach ($daftar_tugas as $t) { if (!empty($t['nama_file'])) $stat_selesai++; else $stat_tugas_aktif++; }
$stat_quiz = 0; foreach ($daftar_quiz as $z) if (empty($z['dikerjakan_at'])) $stat_quiz++;
$q = mysqli_query($koneksi, "SELECT (SELECT ROUND(AVG(nilai)) FROM pengumpulan_tugas WHERE user_id=$uid AND nilai IS NOT NULL) AS r_tugas, (SELECT ROUND(AVG(skor)) FROM quiz_hasil WHERE user_id=$uid) AS r_quiz");
$rr = $q ? mysqli_fetch_assoc($q) : ['r_tugas'=>null,'r_quiz'=>null];
$nilai_avg = null;
if ($rr['r_tugas']!==null && $rr['r_quiz']!==null) $nilai_avg = round(((int)$rr['r_tugas']+(int)$rr['r_quiz'])/2);
elseif ($rr['r_tugas']!==null) $nilai_avg = (int)$rr['r_tugas'];
elseif ($rr['r_quiz']!==null) $nilai_avg = (int)$rr['r_quiz'];

// Aktivitas terbaru
$aktivitas = [];
$q = mysqli_query($koneksi, "(SELECT CONCAT('Mengumpulkan tugas: ', t.judul_tugas) AS judul, pt.dikumpulkan_at AS waktu, IF(pt.nilai IS NULL,'Menunggu penilaian',CONCAT('Nilai: ',pt.nilai)) AS sub, 'clipboard' AS ikon FROM pengumpulan_tugas pt JOIN tugas t ON t.id=pt.tugas_id WHERE pt.user_id=$uid)
    UNION ALL (SELECT CONCAT('Mengerjakan quiz: ', q.judul) AS judul, qh.dikerjakan_at AS waktu, CONCAT('Skor: ',qh.skor,' (',qh.benar,'/',qh.total,' benar)') AS sub, 'help' AS ikon FROM quiz_hasil qh JOIN quiz q ON q.id=qh.quiz_id WHERE qh.user_id=$uid)
    UNION ALL (SELECT CONCAT('Absensi: ', a.judul_absensi) AS judul, ah.waktu AS waktu, CONCAT('Status: ',ah.status) AS sub, 'calendar' AS ikon FROM absensi_hadir ah JOIN absensi a ON a.id=ah.absensi_id WHERE ah.user_id=$uid)
    ORDER BY waktu DESC LIMIT 6");
while ($q && $r=mysqli_fetch_assoc($q)) $aktivitas[]=$r;

$tugas_dibuka = isset($_GET['tugas']) ? (int)$_GET['tugas'] : 0;
if ($tugas_dibuka && !isset($daftar_tugas[$tugas_dibuka])) {
    // mungkin tugas di luar limit — ambil manual bila milik kelas saya
    $s = mysqli_prepare($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, t.deskripsi, k.nama_kelas AS mapel, pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=? WHERE t.id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$uid,$tugas_dibuka); mysqli_stmt_execute($s);
    $satu = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($satu) $daftar_tugas[$tugas_dibuka]=$satu; else $tugas_dibuka=0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Dashboard Murid SLearning SMK Negeri 7 Batam">
<title>Dashboard Murid — SLearning</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root{--nav-yellow:#FFF8D6;--nav-yellow-dark:#F2E7B5;--sage-body:#F4F7F5;--sage-50:#E8F0E8;--sage-100:#D1E0D1;--sage-300:#9FBA9F;--sage-500:#6D8C6D;--sage-600:#577357;--sage-800:#2F402F;--sage-900:#1D261D;--bg-card:#fff;--shadow-sm:0 4px 12px rgba(109,140,109,.05);--shadow-md:0 8px 24px rgba(109,140,109,.08);--shadow-lg:0 16px 40px rgba(109,140,109,.12);--radius-sm:12px;--radius-md:16px;--radius-lg:24px}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}html{scroll-behavior:smooth;scroll-padding-top:20px}body{background:var(--sage-body);color:var(--sage-900);min-height:100vh;padding-bottom:40px;overflow-x:hidden}body:before{content:'';position:fixed;top:-10%;left:-5%;width:50vw;height:50vw;background:radial-gradient(circle,rgba(255,248,214,.8),transparent 70%);border-radius:50%;pointer-events:none;z-index:-1}body:after{content:'';position:fixed;bottom:-10%;right:-5%;width:60vw;height:60vw;background:radial-gradient(circle,rgba(109,140,109,.15),transparent 70%);border-radius:50%;pointer-events:none;z-index:-1}
.navbar{background:linear-gradient(180deg,var(--nav-yellow),#FFFDF6);position:relative;z-index:10;border-bottom:1px solid var(--nav-yellow-dark);box-shadow:0 2px 10px rgba(0,0,0,.02)}.nav-inner{max-width:1200px;margin:auto;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap}.brand{display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--sage-800)}.brand img{width:42px;height:42px;object-fit:contain}.brand-text{display:flex;flex-direction:column;gap:2px}.brand-text strong{font-size:21px;font-weight:800;letter-spacing:-.5px}.brand-text span{font-size:10px;color:var(--sage-600);font-weight:600}.nav-links{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.nav-links a{padding:10px 12px;border-radius:10px;color:var(--sage-600);text-decoration:none;font-size:12px;font-weight:700;transition:.2s}.nav-links a:hover,.nav-links a.active{background:#fff;color:var(--sage-900);box-shadow:var(--shadow-sm)}.nav-right{display:flex;align-items:center;gap:12px}.profile{display:flex;align-items:center;gap:9px;text-decoration:none;color:var(--sage-800)}.profile-info{display:flex;flex-direction:column}.profile-info strong{font-size:12px}.profile-info span{font-size:11px;color:var(--sage-600)}.avatar{width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--sage-50);border:1px solid var(--sage-100);color:var(--sage-800);font-size:13px;font-weight:800}.burger{display:none;background:#fff;border:1px solid var(--sage-100);border-radius:10px;padding:9px;color:var(--sage-800)}
.container{max-width:1200px;margin:0 auto;padding:30px 28px;position:relative;z-index:1}.page-inner{padding-top:32px}.welcome,.beranda-hero{background:linear-gradient(135deg,var(--sage-50),#fff);border:1px solid var(--sage-100);border-radius:var(--radius-lg);padding:34px;display:flex;justify-content:space-between;align-items:center;gap:24px;margin-bottom:26px;box-shadow:var(--shadow-sm)}.welcome small{color:var(--sage-600);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px}.welcome h1{font-size:28px;font-weight:800;color:var(--sage-800);margin:8px 0}.welcome p{font-size:14px;color:var(--sage-600);line-height:1.7;max-width:620px}.stats,.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:30px}.stat-card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);padding:20px;box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:15px}.stat-icon{width:48px;height:48px;flex-shrink:0;border-radius:14px;background:var(--nav-yellow);display:flex;align-items:center;justify-content:center;color:var(--sage-800)}.stat-icon svg{width:22px;height:22px}.stat-number{font-size:24px;font-weight:800;color:var(--sage-800)}.stat-label{font-size:12px;font-weight:700;color:var(--sage-600);margin-top:2px}.section{margin:0 0 30px;scroll-margin-top:18px}.section-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:16px;flex-wrap:wrap}.section-head h2{font-size:19px;font-weight:800;color:var(--sage-800);margin-bottom:5px}.section-head p,.hint{font-size:12px;color:var(--sage-600);line-height:1.6}.card,.card-manage{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow-sm);margin-bottom:16px}.card h2{color:var(--sage-800)}.main-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:24px}.main-grid>div{min-width:0}.class-grid,.quiz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.class-card,.quiz-card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);padding:22px;box-shadow:var(--shadow-sm);transition:.2s}.class-card:hover,.quiz-card:hover,.task-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}.class-card h4,.quiz-card h3{font-size:16px;font-weight:800;color:var(--sage-800);margin-bottom:7px}.class-card .sub,.quiz-card p{font-size:12px;color:var(--sage-600);line-height:1.6}.code-box{display:inline-flex;gap:7px;align-items:center;background:var(--nav-yellow);border:1px dashed #E2D38A;border-radius:10px;padding:8px 11px;margin-top:13px;font-size:12px;font-weight:800;color:var(--sage-800)}.task-list{display:flex;flex-direction:column;gap:12px}.task-list>a{text-decoration:none;color:inherit}.task-card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);padding:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;box-shadow:var(--shadow-sm);transition:.2s}.task-icon{width:44px;height:44px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:13px;background:var(--sage-50);color:var(--sage-600)}.task-content{flex:1;min-width:160px}.task-content h3{font-size:14px;color:var(--sage-800);font-weight:800;margin-bottom:5px}.task-content p{font-size:12px;color:var(--sage-600);line-height:1.6}.pill{display:inline-flex;align-items:center;gap:5px;padding:6px 10px;border-radius:30px;font-size:10px;font-weight:800;white-space:nowrap}.pill.ok{background:#E8F5E9;color:#2E7D32}.pill.wait{background:#FFF8D6;color:#77651F}.pill.red{background:#FFEBEE;color:#C62828}.quiz-top,.quiz-bottom{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.quiz-top{margin-bottom:14px}.quiz-label,.quiz-time{font-size:10px;font-weight:800;color:var(--sage-600)}.quiz-card h3{margin-bottom:8px}.quiz-card p{margin-bottom:18px}.quiz-bottom{padding-top:12px;border-top:1px solid var(--sage-50);font-size:11px;color:var(--sage-600)}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:50px;padding:11px 18px;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer;transition:.2s}.btn:hover{transform:translateY(-1px)}.btn-yellow{background:var(--sage-500);color:#fff;box-shadow:0 4px 12px rgba(109,140,109,.2)}.btn-yellow:hover{background:var(--sage-600)}.btn-ghost{background:#fff;border:1px solid var(--sage-100);color:var(--sage-800)}.btn-danger{background:#FFF3F0;border:1px solid #F4E3E0;color:#C35D4A}.btn-sm{padding:8px 12px;font-size:11px}.btn-block{width:100%}.form-control{width:100%;min-width:0;padding:12px 14px;border:1px solid var(--sage-100);border-radius:10px;background:#fff;color:var(--sage-900);font-size:13px;outline:none}.form-control:focus{border-color:var(--sage-500);box-shadow:0 0 0 3px rgba(109,140,109,.12)}.alert{padding:14px 17px;border-radius:12px;font-size:12px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}.alert.success{background:var(--sage-50);color:var(--sage-800);border-left:4px solid var(--sage-500)}.alert.error{background:#FFF3F0;color:#C35D4A;border-left:4px solid #E07A5F}.empty{padding:32px 20px;background:#fff;border:1px dashed var(--sage-300);border-radius:var(--radius-md);color:var(--sage-600);display:flex;align-items:center;justify-content:center;gap:10px;text-align:center;font-size:12px}.activity-card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);overflow:hidden}.activity-item{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--sage-50)}.activity-item:last-child{border-bottom:0}.activity-ic{width:36px;height:36px;border-radius:11px;background:var(--sage-50);color:var(--sage-600);display:flex;align-items:center;justify-content:center}.activity-item strong{display:block;font-size:12px;color:var(--sage-800);margin-bottom:4px}.activity-item span{font-size:11px;color:var(--sage-600)}.footer-inner{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--sage-600);border-top:1px solid var(--sage-100);padding-top:18px}.footer-inner a{color:var(--sage-600);text-decoration:none;font-weight:700}.sidebar-widget{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-md);padding:20px}.sidebar-widget.dark{background:var(--sage-800);color:#fff}.sidebar-widget h3{font-size:15px;margin-bottom:6px}.sidebar-widget .subtitle{font-size:11px;color:var(--sage-600);margin-bottom:15px}.sidebar-widget.dark .subtitle{color:#D1E0D1}.quick-menu{display:grid;gap:8px}.quick-menu a{display:flex;align-items:center;gap:9px;padding:10px;border-radius:9px;text-decoration:none;color:inherit;font-size:12px;font-weight:700}.quick-menu a:hover{background:rgba(255,255,255,.1)}.mini{max-width:1200px;margin:auto;padding:0 28px}
@media(max-width:900px){.nav-inner{gap:14px}.nav-links{order:3;width:100%;justify-content:center}.nav-right{margin-left:auto}.stats,.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.welcome{align-items:flex-start;flex-direction:column}}
@media(max-width:620px){.nav-inner{padding:12px 16px}.brand img{width:36px;height:36px}.brand-text strong{font-size:18px}.burger{display:block;margin-left:auto}.nav-links{display:none;order:4;width:100%;align-items:stretch;flex-direction:column}.nav-links.open{display:flex}.nav-links a{padding:11px 13px}.nav-right{display:none;order:5;width:100%;justify-content:space-between}.nav-right.open{display:flex}.profile-info{display:flex}.container{padding:22px 16px}.mini{padding:0 16px}.welcome{padding:24px}.welcome h1{font-size:23px}.stats,.stats-grid{grid-template-columns:1fr 1fr;gap:10px}.stat-card{padding:14px;gap:10px}.stat-icon{width:40px;height:40px}.stat-number{font-size:21px}.stat-label{font-size:10px}.task-card{align-items:flex-start}.task-card>.pill{margin-left:58px}.card{padding:18px}.footer-inner{font-size:10px}}
</style>
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="murid.php" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam"><span class="brand-text"><strong>SLearning</strong><span>SKAJU Learning • SMKN 7</span></span></a>
  <nav class="nav-links" id="navLinks"><a href="murid.php" class="active">Dashboard</a><a href="#kelas">Kelas</a><a href="#tugas">Tugas</a><a href="#quiz">Quiziz</a><a href="#absensi">Absensi</a><a href="#aktivitas">Aktivitas</a></nav>
  <div class="nav-right" id="navRight">
    <a href="profil.php" class="profile" title="Profil Saya"><div class="avatar"><?= e(inisial_nama($nama_murid)) ?></div><div class="profile-info"><strong><?= e($nama_murid) ?></strong><span>@<?= e($username) ?></span></div></a>
    <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin keluar?')" title="Keluar"><?= svg_icon('logout',15) ?> Keluar</a>
  </div>
  <button class="burger" id="burger" aria-label="Buka menu" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>

<main class="page page-grid-bg"><div class="container page-inner">
  <section class="welcome"><div><small>Dashboard Murid</small><h1>Halo, <?= e($nama_murid) ?></h1><p>Selamat datang di SLearning. Cek tugas, isi absensi, dan kerjakan Quiziz tepat waktu.</p></div>
    <div style="position:relative;z-index:2;display:flex;gap:10px;flex-wrap:wrap"><a href="#tugas" class="btn btn-yellow">Lihat Tugas <?= svg_icon('arrow-right',16) ?></a><a href="quiziz.php" class="btn btn-ghost" style="background:#fff"><?= svg_icon('help',16) ?> Buka Quiziz</a></div></section>

  <section class="stats">
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('clipboard',20) ?></div><div><div class="stat-number"><?= (int)$stat_tugas_aktif ?></div><div class="stat-label">Tugas Aktif</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('help',20) ?></div><div><div class="stat-number"><?= (int)$stat_quiz ?></div><div class="stat-label">Quiziz Tersedia</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('check-circle',20) ?></div><div><div class="stat-number"><?= (int)$stat_selesai ?></div><div class="stat-label">Tugas Selesai</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('award',20) ?></div><div><div class="stat-number"><?= $nilai_avg!==null ? (int)$nilai_avg : '–' ?></div><div class="stat-label">Nilai Rata-rata</div></div></div>
  </section>

  <!-- KELAS -->
  <section class="section" id="kelas">
    <?php if ($pesan_kelas!==''): ?><div class="alert <?= $tipe_kelas==='success'?'success':'error' ?>"><?= svg_icon($tipe_kelas==='success'?'check-circle':'info',18) ?><span><?= e($pesan_kelas) ?></span></div><?php endif; ?>
    <div class="card accent" style="border-left:4px solid var(--yellow)">
      <h2 style="font-size:19px;margin-bottom:4px">Gabung Kelas</h2>
      <p class="hint" style="margin-bottom:14px">Masukkan kode kelas dari guru untuk bergabung.</p>
      <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap"><input type="text" name="kode_gabung" class="form-control" placeholder="cth: KLS-AB12C" maxlength="30" autocomplete="off" required style="flex:1;min-width:200px;height:52px;text-transform:uppercase"><button type="submit" name="gabung_kelas" class="btn btn-yellow"><?= svg_icon('plus',16) ?> Gabung Kelas</button></form>
    </div>
    <h3 style="font-size:17px;margin:16px 0 12px">Kelas Saya (<?= count($daftar_kelas) ?>)</h3>
    <?php if (empty($daftar_kelas)): ?><div class="empty"><?= svg_icon('book',18) ?><span>Belum bergabung ke kelas mana pun. Masukkan kode dari guru di atas.</span></div>
    <?php else: ?><div class="class-grid">
      <?php foreach ($daftar_kelas as $kls): ?>
      <article class="class-card"><div class="stat-icon" style="margin-bottom:12px"><?= svg_icon('book',20) ?></div>
        <h4><?= e($kls['nama_kelas']) ?></h4><p class="sub">Pengajar: <?= e($kls['nama_guru'] ?? 'Guru Pengampu') ?></p>
        <span class="code-box"><?= svg_icon('key',14) ?> <?= e($kls['kode_gabung']) ?></span>
        <div style="margin-top:12px"><a href="kelas.php?id=<?= (int)$kls['id'] ?>" class="btn btn-yellow btn-sm btn-block">Buka Halaman Kelas <?= svg_icon('arrow-right',14) ?></a></div>
      </article>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>

  <div class="main-grid"><div>
    <!-- TUGAS -->
    <section class="section" id="tugas">
      <div class="section-head"><div><h2>Tugas Terbaru</h2><p>Dari seluruh kelas yang kamu ikuti. Jangan lewati deadline.</p></div><a href="#tugas" class="btn btn-ghost btn-sm">Lihat Semua</a></div>
      <?php if (empty($daftar_tugas)): ?><div class="empty"><?= svg_icon('clipboard',18) ?><span><?php if (empty($daftar_kelas)) echo 'Gabung kelas dahulu untuk melihat tugas.'; else echo 'Belum ada tugas dari guru.'; ?></span></div>
      <?php else: ?><div class="task-list">
        <?php foreach ($daftar_tugas as $id_t => $t): $done = !empty($t['nama_file']); ?>
        <a href="?tugas=<?= (int)$id_t ?>#pengumpulan" style="display:block"><article class="task-card">
          <div class="task-icon"><?= svg_icon('file',20) ?></div>
          <div class="task-content"><h3><?= e($t['judul']) ?></h3><p><?= e($t['mapel']) ?> • Deadline <?= $t['deadline'] ? e(tanggal_id($t['deadline'])) : 'tanpa batas' ?><?php if (isset($t['nilai']) && $t['nilai']!==null) echo ' • Nilai: <strong>'.(int)$t['nilai'].'</strong>'; ?></p>
          <span class="link-more" style="font-size:12px;margin-top:4px"><?= $done ? 'Lihat pengumpulan' : 'Buka dan kumpulkan' ?> <?= svg_icon('arrow-right',13) ?></span></div>
          <span class="pill <?= $done?'ok':'wait' ?>"><?= $done?'Sudah Kumpul':'Belum Kumpul' ?></span>
        </article></a>
        <?php endforeach; ?>
      </div><?php endif; ?>

      <?php if ($tugas_dibuka && isset($daftar_tugas[$tugas_dibuka])): $td = $daftar_tugas[$tugas_dibuka]; ?>
      <div class="card" id="pengumpulan" style="margin-top:16px;border-color:var(--yellow-border)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px"><div><h3 style="font-size:18px"><?= e($td['judul']) ?></h3><p class="hint"><?= e($td['mapel']) ?> • Deadline <?= $td['deadline'] ? e(tanggal_id($td['deadline'])) : 'tanpa batas' ?></p></div><a href="murid.php#tugas" class="btn btn-ghost btn-sm"><?= svg_icon('x',15) ?> Tutup</a></div>
        <?php if ($pesan_tugas!==''): ?><div class="alert <?= $tipe_tugas==='success'?'success':'error' ?>"><?= svg_icon($tipe_tugas==='success'?'check-circle':'info',18) ?><span><?= e($pesan_tugas) ?></span></div><?php endif; ?>
        <?php if (!empty($td['deskripsi'])): ?><p style="font-size:13.5px;color:#4b5563;background:var(--bg-soft);border-radius:12px;padding:14px;margin-bottom:14px"><?= nl2br(e($td['deskripsi'])) ?></p><?php endif; ?>
        <?php if (!empty($td['nama_file'])): ?><div class="alert success"><?= svg_icon('check-circle',18) ?><span>Terkumpul: <strong><?= e($td['nama_file']) ?></strong> (<?= e(tanggal_id($td['dikumpulkan_at'])) ?>)<?php if ($td['nilai']!==null) echo ' • Nilai: <strong>'.(int)$td['nilai'].'</strong>'; ?><?php if (!empty($td['feedback'])) echo '<br>Umpan balik guru: '.e($td['feedback']); ?></span></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" style="background:#fafafa;border:1px dashed #d5d5d8;border-radius:14px;padding:18px">
          <input type="hidden" name="tugas_id" value="<?= (int)$tugas_dibuka ?>">
          <label style="display:block;font-family:var(--font-h);font-size:12.5px;font-weight:700;margin-bottom:8px"><?= !empty($td['nama_file']) ? 'Ganti file tugas' : 'Pilih file tugas' ?></label>
          <input type="file" name="file_tugas" required class="form-control" style="background:#fff;margin-bottom:10px">
          <p class="hint" style="margin-bottom:12px">Maksimal 10 MB • PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR, JPG, JPEG, PNG.</p>
          <button type="submit" name="kumpulkan_tugas" class="btn btn-yellow"><?= svg_icon('upload',16) ?> <?= !empty($td['nama_file']) ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas' ?></button>
        </form>
      </div>
      <?php endif; ?>
    </section>

    <!-- ABSENSI -->
    <section class="section" id="absensi">
      <div class="section-head"><div><h2>Absensi Kehadiran</h2><p>Isi kehadiran pada sesi yang dibuka guru.</p></div></div>
      <?php if ($pesan_absen!==''): ?><div class="alert <?= $tipe_absen==='success'?'success':'error' ?>"><?= svg_icon($tipe_absen==='success'?'check-circle':'info',18) ?><span><?= e($pesan_absen) ?></span></div><?php endif; ?>
      <?php if (empty($daftar_absen)): ?><div class="empty"><?= svg_icon('calendar',18) ?><span><?php if (empty($daftar_kelas)) echo 'Gabung kelas dahulu untuk melihat absensi.'; else echo 'Belum ada sesi absensi yang dibuka guru.'; ?></span></div>
      <?php else: ?><div class="task-list">
        <?php foreach ($daftar_absen as $ab): ?>
        <div class="task-card"><div class="task-icon"><?= svg_icon('calendar',20) ?></div>
          <div class="task-content"><h3><?= e($ab['judul_absensi']) ?></h3><p><?= e($ab['kelas']) ?> • <?= e(tanggal_id($ab['tanggal'], false)) ?></p>
            <?php if ($ab['status']): ?><span class="pill <?= $ab['status']==='hadir'?'ok':($ab['status']==='alpa'?'red':'wait') ?>" style="margin-top:6px"><?= e(ucfirst($ab['status'])) ?> • <?= e(tanggal_id($ab['waktu'])) ?></span>
            <?php else: ?><form method="POST" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><input type="hidden" name="absensi_id" value="<?= (int)$ab['id'] ?>">
              <select name="status" class="form-control" style="max-width:130px;padding:8px 10px"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select>
              <input type="text" name="keterangan" class="form-control" placeholder="Keterangan (opsional)" style="flex:1;min-width:140px;padding:8px 10px">
              <button type="submit" name="isi_absen" class="btn btn-yellow btn-sm"><?= svg_icon('check',14) ?> Absen</button></form>
            <?php endif; ?></div>
          <?php if ($ab['status']): ?><span class="pill <?= $ab['status']==='hadir'?'ok':'wait' ?>"><?= svg_icon('check',13) ?> Selesai</span><?php else: ?><span class="pill wait">Perlu absen</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </section>
  </div>

  <aside>
    <div class="sidebar-widget dark"><h3>Akses Cepat</h3><p class="subtitle">Fitur SLearning dalam sekali klik.</p>
      <div class="quick-menu">
        <a href="#tugas"><?= svg_icon('clipboard',18) ?> Pengumpulan Tugas</a>
        <a href="#quiz"><?= svg_icon('help',18) ?> Quiziz</a>
        <a href="#absensi"><?= svg_icon('calendar',18) ?> Absensi Saya</a>
        <a href="profil.php"><?= svg_icon('user',18) ?> Profil Saya</a>
      </div></div>
    <div class="sidebar-widget"><h3>Profil</h3><p class="subtitle">Data akun kamu</p>
      <div style="display:flex;gap:12px;align-items:center"><div class="avatar"><?= e(inisial_nama($nama_murid)) ?></div><div><strong style="font-size:14px"><?= e($nama_murid) ?></strong><br><span class="hint">@<?= e($username) ?> • <?= e($user['email']) ?></span></div></div>
      <a href="profil.php" class="btn btn-ghost btn-sm btn-block" style="margin-top:14px"><?= svg_icon('edit',14) ?> Kelola Profil</a></div>
  </aside></div>

  <!-- QUIZ -->
  <section class="section" id="quiz">
    <div class="section-head"><div><h2>Quiziz Tersedia</h2><p>Kerjakan quiz dari guru sebelum kehabisan waktu.</p></div><a href="quiziz.php" class="btn btn-ghost btn-sm">Semua Quiz <?= svg_icon('arrow-right',14) ?></a></div>
    <?php if (empty($daftar_quiz)): ?><div class="empty"><?= svg_icon('help',18) ?><span><?php if (empty($daftar_kelas)) echo 'Gabung kelas dahulu untuk melihat quiz.'; else echo 'Belum ada quiz dari guru.'; ?></span></div>
    <?php else: ?><div class="quiz-grid">
      <?php foreach ($daftar_quiz as $z): $done = !empty($z['dikerjakan_at']); ?>
      <article class="quiz-card"><div class="quiz-top"><span class="quiz-label"><?= e(mb_strtoupper(mb_substr($z['mapel'],0,14))) ?></span><span class="quiz-time"><?= svg_icon('clock',13) ?> <?= (int)$z['durasi_menit'] ?> mnt</span></div>
        <h3><?= e($z['judul']) ?></h3><p><?= $z['deskripsi'] ? e(mb_strimwidth($z['deskripsi'],0,90,'...')) : ((int)$z['jml_soal']).' soal pilihan ganda.' ?></p>
        <div class="quiz-bottom"><span><?= (int)$z['jml_soal'] ?> soal<?= $done ? ' • Skor <strong>'.(int)$z['skor'].'</strong>' : '' ?></span>
        <?php if ($done): ?><span class="pill ok">Selesai</span><?php else: ?><a class="link-more" href="quiziz.php?id=<?= (int)$z['id'] ?>">Kerjakan <?= svg_icon('arrow-right',14) ?></a><?php endif; ?></div>
        <?php if ($done): ?><a href="quiziz.php?id=<?= (int)$z['id'] ?>&hasil=1" class="btn btn-ghost btn-sm btn-block" style="margin-top:10px">Lihat Hasil</a><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>

  <!-- AKTIVITAS -->
  <section class="section" id="aktivitas">
    <div class="section-head"><div><h2>Aktivitas Terbaru</h2><p>Riwayat belajar kamu, terisi otomatis.</p></div></div>
    <?php if (empty($aktivitas)): ?><div class="empty"><?= svg_icon('activity',18) ?><span>Belum ada aktivitas. Kumpulkan tugas atau kerjakan quiz untuk memulai.</span></div>
    <?php else: ?><div class="activity-card">
      <?php foreach ($aktivitas as $a): ?><div class="activity-item"><span class="activity-ic"><?= svg_icon($a['ikon'] ?? 'activity',17) ?></span><div><strong><?= e($a['judul']) ?></strong><span><?= e(tanggal_id($a['waktu'])) ?> • <?= e($a['sub']) ?></span></div></div><?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</div></main>

<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — SMK Negeri 7 Batam • Dashboard Murid</span><span><a href="kelas.php">Ruang Kelas</a> • <a href="logout.php">Keluar</a></span></div></div></footer>
<script>
(function(){var b=document.getElementById('burger'),l=document.getElementById('navLinks'),r=document.getElementById('navRight');if(b){b.addEventListener('click',function(){var o=l.classList.toggle('open');if(r)r.classList.toggle('open',o);b.setAttribute('aria-expanded',o?'true':'false');});}})();
</script>
</body>
</html>

<?php
/**
 * SLearning - Dashboard Murid
 * Versi Terintegrasi dengan Pengumuman / Broadcast Waka
 * Desain Navbar Konsisten dengan Dashboard Guru
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
    die('Koneksi database $koneksi tidak ditemukan. Pastikan file koneksi.php tersedia.');
}
mysqli_set_charset($koneksi, 'utf8mb4');

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('tanggal_id')) {
    function tanggal_id($tanggal, $jam = true): string {
        if (!$tanggal) return '-';
        $waktu = strtotime($tanggal);
        if (!$waktu) return e($tanggal);
        $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $hasil = date('j', $waktu).' '.$bulan[(int)date('n', $waktu)].' '.date('Y', $waktu);
        return $jam ? $hasil.' '.date('H:i', $waktu) : $hasil;
    }
}
function flash_murid(string $pesan, string $tipe = 'success', string $judul = 'Informasi'): void {
    $_SESSION['flash_murid'] = ['pesan'=>$pesan, 'tipe'=>$tipe, 'judul'=>$judul];
}
function stmt_row(mysqli $db, string $sql, string $types = '', array $params = []): ?array {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) return null;
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
function stmt_rows(mysqli $db, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) return [];
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

/* =========================
   AUTENTIKASI
   ========================= */
if (empty($_SESSION['user_id'])) {
    if (isset($_GET['gabung'])) {
        $_SESSION['kode_gabung_pending'] = strtoupper(trim((string)$_GET['gabung']));
    }
    header('Location: login.php');
    exit;
}
if (($_SESSION['jenis'] ?? '') === 'guru') { header('Location: guru.php'); exit; }
if (($_SESSION['jenis'] ?? '') === 'waka') { header('Location: waka.php'); exit; }

$uid = (int)$_SESSION['user_id'];
$user = stmt_row($koneksi, "SELECT id,nama,username,email,jenis,level,password,foto FROM login WHERE id=? LIMIT 1", 'i', [$uid]);
if (!$user || ($user['jenis'] ?? '') !== 'murid') {
    session_unset(); session_destroy();
    header('Location: login.php'); exit;
}
foreach (['nama','username','email','jenis','level','foto'] as $kolom) {
    $_SESSION[$kolom] = $user[$kolom] ?? '';
}
$nama_murid = $user['nama'];
$foto_profil = $user['foto'] ?? '';

/* =========================
   PROSES FORM (Ubah Profil, Gabung Kelas, Tugas, Dll)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profil'])) {
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_murid('Nama dan email yang valid wajib diisi.', 'error');
        } else {
            $sql = "UPDATE login SET nama=?, email=?";
            $types = 'ss'; $params = [$nama, $email];
            $passLama = $_POST['password_lama'] ?? '';
            $passBaru = $_POST['password_baru'] ?? '';
            
            if ($passBaru !== '') {
                if (!password_verify($passLama, $user['password'])) {
                    flash_murid('Password lama tidak sesuai.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
                if (strlen($passBaru) < 8) {
                    flash_murid('Password baru minimal 8 karakter.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
                $sql .= ', password=?'; $types .= 's';
                $params[] = password_hash($passBaru, PASSWORD_DEFAULT);
            }
            
            // Proses Upload Foto Profil Baru
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['foto'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    flash_murid('Format foto harus JPG, PNG, atau WEBP.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
                
                if ($f['size'] > 2 * 1024 * 1024) {
                    flash_murid('Ukuran foto maksimal 2 MB.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
                
                $dir = __DIR__ . '/uploads/profil/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                
                $namaFileBaru = 'profil_murid_' . $uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $namaFileBaru)) {
                    $pathFotoDB = 'uploads/profil/' . $namaFileBaru;
                    $sql .= ', foto=?';
                    $types .= 's';
                    $params[] = $pathFotoDB;
                    
                    // Hapus foto lama jika ada dan bukan bawaan default
                    if (!empty($user['foto']) && is_file(__DIR__ . '/' . $user['foto'])) {
                        @unlink(__DIR__ . '/' . $user['foto']);
                    }
                } else {
                    flash_murid('Gagal mengunggah foto profil.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
            }

            $sql .= ' WHERE id=?'; $types .= 'i'; $params[] = $uid;
            
            $st = mysqli_prepare($koneksi, $sql);
            if ($st) {
                mysqli_stmt_bind_param($st, $types, ...$params);
                $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
                flash_murid($ok ? 'Profil berhasil diperbarui.' : 'Profil gagal diperbarui.', $ok ? 'success' : 'error');
            }
        }
        header('Location: murid.php?page=profil'); exit;
    }

    if (isset($_POST['gabung_kelas'])) {
        $kode = strtoupper(trim($_POST['kode_gabung'] ?? ''));
        $kelas = $kode !== '' ? stmt_row($koneksi, "SELECT id,nama_kelas FROM kelas WHERE kode_gabung=? LIMIT 1", 's', [$kode]) : null;
        if (!$kelas) {
            flash_murid('Kode kelas tidak ditemukan.', 'error');
        } else {
            $kid = (int)$kelas['id'];
            $anggota = stmt_row($koneksi, "SELECT id,status FROM anggota_kelas WHERE kelas_id=? AND user_id=? LIMIT 1", 'ii', [$kid,$uid]);
            if ($anggota) {
                if (($anggota['status'] ?? 'disetujui') === 'disetujui') {
                    flash_murid('Kamu sudah tergabung di kelas ini.', 'info');
                } else {
                    mysqli_query($koneksi, "UPDATE anggota_kelas SET status='pending' WHERE id=".$anggota['id']);
                    flash_murid('Permintaan bergabung dikirim ulang.', 'success');
                }
            } else {
                mysqli_query($koneksi, "INSERT INTO anggota_kelas (kelas_id,user_id,status) VALUES ($kid, $uid, 'pending')");
                flash_murid('Permintaan bergabung dikirim. Tunggu persetujuan guru.', 'success');
            }
            header('Location: murid.php?page=detail_kelas&id='.$kid); exit;
        }
        header('Location: murid.php?page=kelas'); exit;
    }

    if (isset($_POST['isi_absen'])) {
        $aid = (int)($_POST['absensi_id'] ?? 0);
        $statusAbsen = $_POST['status'] ?? 'hadir';
        if (!in_array($statusAbsen, ['hadir','izin','sakit'], true)) $statusAbsen = 'hadir';
        $valid = stmt_row($koneksi, "SELECT a.id FROM absensi a JOIN anggota_kelas ak ON ak.kelas_id=a.kelas_id WHERE a.id=? AND ak.user_id=? AND COALESCE(ak.status,'disetujui')='disetujui' LIMIT 1", 'ii', [$aid,$uid]);
        if ($valid) {
            $st = mysqli_prepare($koneksi, "INSERT INTO absensi_hadir (absensi_id,user_id,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), waktu=CURRENT_TIMESTAMP");
            mysqli_stmt_bind_param($st, 'iis', $aid, $uid, $statusAbsen);
            $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
            flash_murid($ok ? 'Kehadiran berhasil dicatat.' : 'Absensi gagal disimpan.', $ok ? 'success' : 'error');
        }
        header('Location: murid.php?page=absensi'); exit;
    }

    if (isset($_POST['kumpulkan_tugas'])) {
        $tid = (int)($_POST['tugas_id'] ?? 0);
        $valid = stmt_row($koneksi, "SELECT t.id FROM tugas t JOIN anggota_kelas ak ON ak.kelas_id=t.kelas_id WHERE t.id=? AND ak.user_id=? AND COALESCE(ak.status,'disetujui')='disetujui' LIMIT 1", 'ii', [$tid,$uid]);
        if (!$valid) {
            flash_murid('Tugas tidak ditemukan atau akses ditolak.', 'error');
        } elseif (!isset($_FILES['file_tugas']) || $_FILES['file_tugas']['error'] !== UPLOAD_ERR_OK) {
            flash_murid('Pilih file tugas terlebih dahulu.', 'error');
        } else {
            $f = $_FILES['file_tugas'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($f['size'] > 10*1024*1024) {
                flash_murid('File maksimal 10 MB.', 'error');
            } else {
                $folder = __DIR__.'/uploads/tugas/';
                if (!is_dir($folder)) mkdir($folder, 0755, true);
                $fileBaru = 'tugas_'.$uid.'_'.$tid.'_'.bin2hex(random_bytes(5)).'.'.$ext;
                $pathDatabase = 'uploads/tugas/'.$fileBaru;
                if (move_uploaded_file($f['tmp_name'], $folder.$fileBaru)) {
                    $lama = stmt_row($koneksi, "SELECT id FROM pengumpulan_tugas WHERE tugas_id=? AND user_id=? LIMIT 1", 'ii', [$tid,$uid]);
                    if ($lama) {
                        $st = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nama_file=?,file_path=?,nilai=NULL,feedback=NULL,dikumpulkan_at=CURRENT_TIMESTAMP WHERE id=?");
                        mysqli_stmt_bind_param($st, 'ssi', $f['name'], $pathDatabase, $lama['id']);
                    } else {
                        $st = mysqli_prepare($koneksi, "INSERT INTO pengumpulan_tugas (tugas_id,user_id,nama_file,file_path) VALUES (?,?,?,?)");
                        mysqli_stmt_bind_param($st, 'iiss', $tid, $uid, $f['name'], $pathDatabase);
                    }
                    $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
                    flash_murid($ok ? 'Tugas berhasil dikumpulkan.' : 'Gagal menyimpan tugas.', $ok ? 'success' : 'error');
                }
            }
        }
        header('Location: murid.php?page=tugas'); exit;
    }
}

/* =========================
   DATA DASHBOARD & NOTIFIKASI GABUNGAN
   ========================= */
$kelasSaya = stmt_rows($koneksi, "SELECT k.id,k.nama_kelas,k.kode_gabung,k.guru_id,k.tahun_ajaran,COALESCE(ak.status,'disetujui') status_izin,l.nama nama_guru FROM anggota_kelas ak JOIN kelas k ON k.id=ak.kelas_id LEFT JOIN login l ON l.id=k.guru_id WHERE ak.user_id=? ORDER BY k.id DESC", 'i', [$uid]);
$kelasDisetujui = [];
foreach ($kelasSaya as $k) if (($k['status_izin'] ?? 'disetujui') === 'disetujui') $kelasDisetujui[] = (int)$k['id'];
$inKelas = $kelasDisetujui ? implode(',', $kelasDisetujui) : '0';

$statTugas = 0; $statQuiz = 0;
if ($inKelas !== '0') {
    $statTugas = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) jml FROM tugas WHERE kelas_id IN ($inKelas)"))['jml'] ?? 0);
    $statQuiz = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) jml FROM quiz WHERE kelas_id IN ($inKelas)"))['jml'] ?? 0);
}

$notifikasi = [];
// 1. Ambil Notifikasi Tugas & Kuis dari kelas
if ($inKelas !== '0') {
    $r = mysqli_query($koneksi, "SELECT judul_tugas as judul, 'tugas' as tipe, created_at as tanggal, '' as isi FROM tugas WHERE kelas_id IN ($inKelas) UNION SELECT judul, 'quiz' as tipe, created_at as tanggal, '' as isi FROM quiz WHERE kelas_id IN ($inKelas)");
    if ($r) {
        while ($x = mysqli_fetch_assoc($r)) $notifikasi[] = $x;
    }
}

// 2. Ambil Pengumuman / Broadcast dari Waka
try {
    $r_waka = mysqli_query($koneksi, "SELECT judul, 'pengumuman' as tipe, waktu as tanggal, isi FROM pengumuman WHERE target IN ('semua', 'murid')");
    if ($r_waka) {
        while ($p = mysqli_fetch_assoc($r_waka)) {
            $notifikasi[] = $p;
        }
    }
} catch (mysqli_sql_exception $ex) {
    // Tabel pengumuman belum ada / strukturnya beda: lewati, jangan matikan halaman.
}

// 3. Urutkan semua notifikasi berdasarkan tanggal paling baru (DESC)
usort($notifikasi, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

$page = $_GET['page'] ?? 'beranda';
$quizId = (int)($_GET['quiz_id'] ?? 0);
$flash = $_SESSION['flash_murid'] ?? null;
unset($_SESSION['flash_murid']);

// Fungsi inisial nama jika foto profil tidak ada
function inisial_nama($nama) {
    $parts = explode(' ', trim($nama));
    $inisial = '';
    foreach ($parts as $p) {
        if (!empty($p)) {
            $inisial .= strtoupper($p[0]);
            if (strlen($inisial) >= 2) break;
        }
    }
    return $inisial ?: '?';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SLearning | Dashboard Murid</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root { 
    --nav-yellow: #FFF8D6; --nav-yellow-dark: #F2E7B5; 
    --sage-body: #F4F7F5; --sage-50: #E8F0E8; --sage-100: #D1E0D1;
    --sage-300: #9FBA9F; --sage-500: #6D8C6D; --sage-600: #577357; 
    --sage-800: #2F402F; --sage-900: #1D261D; --bg-card: #FFFFFF;
    --shadow-sm: 0 4px 12px rgba(109, 140, 109, 0.05);
    --shadow-md: 0 8px 24px rgba(109, 140, 109, 0.08);
    --shadow-lg: 0 16px 40px rgba(109, 140, 109, 0.12);
    --radius-sm: 12px; --radius-md: 16px; --radius-lg: 24px;
    
    /* Variabel lama untuk elemen spesifik di murid */
    --yellow: #fff8d6; --sage: #6d8c6d; --sage-dark: #577357; 
    --sage-light: #e8f0e8; --ink: #263326; --muted: #687968; --line: #dce7dc;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--sage-body); color: var(--sage-900); font-family: 'Plus Jakarta Sans', Arial, sans-serif; min-height: 100vh; }
a { color: inherit; text-decoration: none; }

/* ================= Navbar Mirip Guru ================= */
.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); position: relative; z-index: 10; padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 32px 16px; }
.logo-container { display: flex; align-items: center; gap: 16px; }

/* Avatar Murid */
.murid-avatar-link {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: transform 0.2s ease;
}
.murid-avatar-link:hover {
    transform: scale(1.05);
}
.murid-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px; /* Agak kotak seperti profil */
    object-fit: cover;
    border: 2px solid var(--sage-300);
    background-color: var(--sage-50);
}
.murid-avatar-placeholder {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background-color: var(--sage-100);
    color: var(--sage-800);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 16px;
    border: 2px solid var(--sage-300);
}

.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); letter-spacing: -0.5px; }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }

.header-icons { display: flex; align-items: center; gap: 24px; }
.header-icons a { color: var(--sage-600); transition: all 0.3s ease; position: relative; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 10px; border-radius: 50%; border: 1px solid var(--sage-50); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.header-icons a:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); border-color: var(--sage-100); }
.header-icons svg { width: 20px; height: 20px; }
.notif-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #E07A5F; border-radius: 50%; border: 2px solid #FFFFFF; }

.nav-tabs { display: flex; justify-content: flex-start; gap: 8px; max-width: 1200px; margin: 0 auto; padding: 0 32px; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
.nav-tabs::-webkit-scrollbar { display: none; }
/* Hapus icon dan ubah padding/style agar text saja */
.nav-tab { 
    display: flex; 
    align-items: center; 
    padding: 14px 20px; 
    text-decoration: none; 
    color: var(--sage-600); 
    font-size: 14px; 
    font-weight: 700; 
    transition: all 0.3s ease; 
    position: relative; 
    border-radius: var(--radius-sm) var(--radius-sm) 0 0; 
}
.nav-tab svg { display: none; } /* Sembunyikan ikon */
.nav-tab:hover { color: var(--sage-800); background: rgba(255, 255, 255, 0.6); }
.nav-tab.active { color: var(--sage-900); background: #FFFFFF; }
.nav-tab.active::after { content: ''; position: absolute; bottom: 0; left: 15%; width: 70%; height: 3px; background: var(--sage-500); border-radius: 4px 4px 0 0; }
.nav-tab.tab-warning { color: #D84315; }
.nav-tab.tab-warning.active { color: #C62828; }
.nav-tab.tab-warning.active::after { background: #C62828; }

.dropdown { position: relative; }
.dropdown-content { display: none; position: absolute; right: 0; top: 55px; background-color: #FFFFFF; min-width: 180px; box-shadow: var(--shadow-md); border-radius: var(--radius-sm); border: 1px solid var(--sage-100); z-index: 100; overflow: hidden; }
.dropdown-content a { color: var(--sage-800); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 700; border-bottom: 1px solid var(--sage-50); border-radius: 0; box-shadow: none; }
.dropdown-content a:hover { background-color: var(--sage-50); transform: none; }
.dropdown-content .badge-notif { background: #E07A5F; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; }
.show-dropdown { display: block; }

/* Modal Panduan */
.modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(29, 38, 29, 0.6); align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-content { background-color: #fff; padding: 30px; border-radius: var(--radius-md); max-width: 500px; width: 90%; box-shadow: var(--shadow-lg); animation: modalFadeIn 0.3s ease-out; }
@keyframes modalFadeIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px dashed var(--sage-100); padding-bottom: 16px; }
.modal-header h3 { color: var(--sage-900); font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px; margin: 0; }
.close-btn { background: var(--sage-50); border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.close-btn:hover { background: #E07A5F; color: #fff; }
.modal-body ul { list-style: none; padding-left: 0; margin: 0; }
.modal-body li { margin-bottom: 14px; font-size: 13.5px; color: var(--sage-600); line-height: 1.6; padding-left: 28px; position: relative; }
.modal-body li::before { content: '✅'; position: absolute; left: 0; font-size: 14px; top: 2px; }
.modal-body li strong { color: var(--sage-900); font-weight: 800; }

/* ================= Body Styling ================= */
.container{max-width:1100px;margin:30px auto;padding:0 22px 60px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:25px;margin-bottom:18px;box-shadow:0 5px 18px #6d8c6d0c}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.stat{display:flex;gap:15px;align-items:center}.stat-icon{font-size:25px;background:var(--yellow);padding:15px;border-radius:14px}.muted{color:var(--muted);font-size:13px}
.btn{display:inline-block;background:var(--sage);color:#fff;border:0;border-radius:30px;padding:11px 17px;font-weight:800;font-size:13px;cursor:pointer}.btn:hover{background:var(--sage-dark)}.btn-light{background:#fff;color:var(--sage-dark);border:1px solid var(--line)}
input,select,textarea{font:inherit;width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fbfdfb;margin:6px 0 14px; outline: none; transition: all 0.3s ease;}
input:focus,select:focus,textarea:focus { border-color: var(--sage); box-shadow: 0 0 0 4px rgba(109, 140, 109, 0.1); }
label{font-size:13px;font-weight:700}
table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid var(--line);font-size:13px}
.alert{padding:13px 16px;border-radius:12px;margin-bottom:18px;background:#e8f5e9;color:#28652d}.alert.error{background:#ffefed;color:#a43b30}
.badge{display:inline-block;padding:5px 10px;border-radius:30px;background:var(--yellow);font-size:11px;font-weight:800}
.notif-item { padding: 14px 0; border-bottom: 1px solid var(--line); }
.notif-item:last-child { border-bottom: none; }
.badge-pengumuman { background: #FFECB3 !important; color: #E65100 !important; border: 1px solid #FFE082; }
.isi-pengumuman { margin-top: 8px; font-size: 13px; color: var(--ink); line-height: 1.6; padding-left: 12px; border-left: 3px solid #FFCA28; background: #FFFAEC; padding: 10px 12px; border-radius: 0 8px 8px 0; }
@media(max-width:768px){.header-top{padding:14px 20px;flex-wrap:wrap;gap:16px}.nav-tabs{padding:0 16px}.container{padding:0 13px}.card{padding:18px}}
</style>
</head>
<body>
<header class="header">
  <div class="header-top">
    <div class="logo-container">
      <a href="murid.php?page=profil" class="murid-avatar-link" title="Pengaturan Profil">
        <?php if (!empty($foto_profil) && file_exists(__DIR__ . '/' . $foto_profil)): ?>
            <img src="<?= e($foto_profil) ?>" alt="Profil <?= e($nama_murid) ?>" class="murid-avatar">
        <?php else: ?>
            <div class="murid-avatar-placeholder"><?= e(inisial_nama($nama_murid)) ?></div>
        <?php endif; ?>
      </a>
      <a href="murid.php?page=beranda" class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        Slearning <span style="font-size: 12px; background: var(--sage-100); padding: 4px 8px; border-radius: 20px; color: var(--sage-800); margin-left: 4px;">Murid</span>
      </a>
    </div>
    
    <div class="header-icons">
      <a href="#" onclick="bukaPanduan(event)" title="Buku Panduan Penggunaan">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      </a>

      <div class="dropdown">
        <a href="#" onclick="toggleDropdown(event)" title="Menu Lanjutan">
          <?php if(!empty($notifikasi)): ?><div class="notif-dot"></div><?php endif; ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
        </a>
        
        <div id="menuLanjutan" class="dropdown-content">
          <a href="murid.php?page=notif">
            Notifikasi 
            <?php if(!empty($notifikasi)): ?><span class="badge-notif">Baru</span><?php endif; ?>
          </a>
          <a href="murid.php?page=profil">Pengaturan Profil</a>
          <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari Slearning?')" style="color: #E07A5F;">Keluar Akun</a>
        </div>
      </div>
    </div>
  </div>

  <nav class="nav-tabs">
    <a href="murid.php?page=beranda" class="nav-tab <?= $page==='beranda'?'active':'' ?>">Beranda</a>
    <a href="murid.php?page=kelas" class="nav-tab <?= in_array($page, ['kelas','detail_kelas']) ?'active':'' ?>">Kelas</a>
    <a href="murid.php?page=absensi" class="nav-tab <?= $page==='absensi'?'active':'' ?>">Absensi</a>
    <a href="murid.php?page=tugas" class="nav-tab <?= $page==='tugas'?'active':'' ?>">Tugas</a>
    <a href="murid.php?page=quiz" class="nav-tab <?= in_array($page, ['quiz','kerjakan_quiz']) ?'active':'' ?>">Quiz</a>
    <a href="murid.php?page=laporan" class="nav-tab tab-warning <?= $page==='laporan'?'active':'' ?>">Lapor</a>
  </nav>
</header>
<main class="container">
<?php if ($flash): ?>
  <div class="alert <?= ($flash['tipe'] ?? '')==='error'?'error':'' ?>"><?= e($flash['pesan']) ?></div>
<?php endif; ?>

<!-- ================= HALAMAN KELAS ================= -->
<?php if ($page === 'kelas'): ?>
  <section class="card">
    <h2>Kelas Saya</h2>
    <p class="muted">Masukkan kode gabung dari guru untuk meminta izin masuk kelas.</p>
    <form method="post">
      <label>Kode Gabung</label><input name="kode_gabung" placeholder="Masukkan kode kelas" required>
      <button class="btn" name="gabung_kelas">Gabung Kelas</button>
    </form>
  </section>
  <div class="grid">
  <?php if (!$kelasSaya): ?><div class="card">Kamu belum bergabung dengan kelas mana pun.</div><?php endif; ?>
  <?php foreach ($kelasSaya as $k): ?>
    <div class="card">
      <span class="badge"><?= e(ucfirst($k['status_izin'] ?? 'disetujui')) ?></span>
      <h3><?= e($k['nama_kelas']) ?></h3><p class="muted">Guru: <?= e($k['nama_guru'] ?? '-') ?><?= !empty($k['tahun_ajaran']) ? ' • TA '.e($k['tahun_ajaran']) : '' ?></p>
      <a class="btn" href="murid.php?page=detail_kelas&id=<?= (int)$k['id'] ?>">Lihat Kelas</a>
    </div>
  <?php endforeach; ?>
  </div>

<!-- ================= HALAMAN DETAIL KELAS ================= -->
<?php elseif ($page === 'detail_kelas'): ?>
  <?php
  $kid=(int)($_GET['id']??0);
  $detail=stmt_row($koneksi,"SELECT k.*,l.nama nama_guru,COALESCE(ak.status,'disetujui') status_izin FROM kelas k JOIN anggota_kelas ak ON ak.kelas_id=k.id LEFT JOIN login l ON l.id=k.guru_id WHERE k.id=? AND ak.user_id=?",'ii',[$kid,$uid]);
  ?>
  <div class="card">
  <?php if (!$detail): ?><h2>Kelas tidak ditemukan</h2>
  <?php elseif ($detail['status_izin']==='pending'): ?>
    <h2>⏳ Sedang Menunggu Izin Guru</h2><p>Permintaan bergabung ke kelas <b><?= e($detail['nama_kelas']) ?></b> sudah dikirim. Tunggu persetujuan guru.</p>
    <a class="btn btn-light" href="murid.php?page=kelas">Kembali</a>
  <?php elseif ($detail['status_izin']==='ditolak'): ?>
    <h2>Permintaan Ditolak</h2><p>Guru belum menyetujui permintaan masuk kelas ini.</p><a class="btn" href="murid.php?page=kelas">Kembali</a>
  <?php else: ?>
    <h2><?= e($detail['nama_kelas']) ?></h2><p class="muted">Guru pengampu: <?= e($detail['nama_guru']??'-') ?></p>
    <h3>Tugas di kelas</h3>
    <?php $daftar=stmt_rows($koneksi,"SELECT id,judul_tugas,deadline FROM tugas WHERE kelas_id=? ORDER BY id DESC",'i',[$kid]); ?>
    <?php if (!$daftar): ?><p class="muted">Belum ada tugas.</p><?php else: foreach($daftar as $t): ?>
      <p><?= e($t['judul_tugas']) ?> — <?= e(tanggal_id($t['deadline']??'',false)) ?></p>
    <?php endforeach; endif; ?>
  <?php endif; ?>
  </div>

<!-- ================= HALAMAN ABSENSI ================= -->
<?php elseif ($page === 'absensi'): ?>
  <div class="card"><h2>Absensi Kehadiran</h2>
  <?php $absen=$inKelas!=='0'?stmt_rows($koneksi,"SELECT a.id,a.judul_absensi,a.tanggal,k.nama_kelas,ah.status FROM absensi a JOIN kelas k ON k.id=a.kelas_id LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=? WHERE a.kelas_id IN ($inKelas) ORDER BY a.tanggal DESC",'i',[$uid]):[]; ?>
  <?php if (!$absen): ?><p class="muted">Belum ada sesi absensi.</p><?php else: foreach($absen as $a): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--line)"><b><?= e($a['judul_absensi']) ?></b><p class="muted"><?= e($a['nama_kelas']) ?> · <?= e(tanggal_id($a['tanggal'])) ?></p>
    <?php if ($a['status']): ?><span class="badge">Sudah absen: <?= e($a['status']) ?></span><?php else: ?>
      <form method="post"><input type="hidden" name="absensi_id" value="<?= (int)$a['id'] ?>"><select name="status"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select><button class="btn" name="isi_absen">Kirim Absen</button></form>
    <?php endif; ?></div>
  <?php endforeach; endif; ?></div>

<!-- ================= HALAMAN TUGAS ================= -->
<?php elseif ($page === 'tugas'): ?>
  <div class="card"><h2>Daftar Tugas</h2>
  <?php $tugas=$inKelas!=='0'?stmt_rows($koneksi,"SELECT t.id,t.judul_tugas,t.deadline,k.nama_kelas,pt.nama_file FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=? WHERE t.kelas_id IN ($inKelas) ORDER BY t.deadline ASC",'i',[$uid]):[]; ?>
  <?php if (!$tugas): ?><p class="muted">Belum ada tugas tersedia.</p><?php else: foreach($tugas as $t): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--line)"><b><?= e($t['judul_tugas']) ?></b><p class="muted"><?= e($t['nama_kelas']) ?> · Deadline: <?= e(tanggal_id($t['deadline']??'',false)) ?></p>
    <span class="badge"><?= $t['nama_file']?'Sudah dikumpulkan':'Belum dikumpulkan' ?></span>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="tugas_id" value="<?= (int)$t['id'] ?>"><input type="file" name="file_tugas" required><button class="btn" name="kumpulkan_tugas">Kumpulkan Tugas</button></form></div>
  <?php endforeach; endif; ?></div>

<!-- ================= HALAMAN QUIZ ================= -->
<?php elseif ($page === 'quiz'): ?>
  <div class="card"><h2>Quiz Tersedia</h2>
  <?php $quiz=$inKelas!=='0'?stmt_rows($koneksi,"SELECT q.id,q.judul,k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.kelas_id IN ($inKelas) ORDER BY q.id DESC"):[]; ?>
  <?php if (!$quiz): ?><p class="muted">Belum ada quiz tersedia.</p><?php else: foreach($quiz as $q): ?>
    <div style="padding:12px 0;border-bottom:1px solid var(--line)"><b><?= e($q['judul']) ?></b><p class="muted"><?= e($q['nama_kelas']) ?></p><a class="btn" href="murid.php?page=kerjakan_quiz&quiz_id=<?= (int)$q['id'] ?>">Buka Quiz</a></div>
  <?php endforeach; endif; ?></div>

<!-- ================= HALAMAN NOTIFIKASI ================= -->
<?php elseif ($page === 'notif' || $page === 'notifikasi'): ?>
  <div class="card">
      <h2>Pemberitahuan & Pengumuman Sekolah</h2>
      <p class="muted" style="margin-bottom: 20px;">Daftar tugas, aktivitas kuis, dan pengumuman siaran langsung dari sekolah.</p>
      
      <?php if (!$notifikasi): ?>
          <p class="muted">Belum ada pemberitahuan baru.</p>
      <?php else: foreach($notifikasi as $n): 
          $is_pengumuman = ($n['tipe'] === 'pengumuman');
      ?>
          <div class="notif-item">
            <p style="margin: 0 0 6px 0;">
              <span class="badge <?= $is_pengumuman ? 'badge-pengumuman' : '' ?>">
                  <?= $is_pengumuman ? '📢 PENGUMUMAN' : e(strtoupper($n['tipe'])) ?>
              </span> 
              <b style="margin-left: 4px;"><?= e($n['judul']) ?></b> 
              <span class="muted" style="font-size:11px; margin-left: 8px;">• <?= e(tanggal_id($n['tanggal'])) ?></span>
            </p>
            
            <?php if ($is_pengumuman && !empty($n['isi'])): ?>
                <div class="isi-pengumuman">
                    <?= nl2br(e($n['isi'])) ?>
                </div>
            <?php endif; ?>
          </div>
      <?php endforeach; endif; ?>
  </div>

<!-- ================= HALAMAN PROFIL ================= -->
<?php elseif ($page === 'profil'): ?>
  <div class="card"><h2>Pengaturan Profil</h2>
  <form method="post" enctype="multipart/form-data">
    <div style="display:flex; align-items:center; gap:20px; margin-bottom: 20px;">
        <?php if (!empty($foto_profil) && file_exists(__DIR__ . '/' . $foto_profil)): ?>
            <img src="<?= e($foto_profil) ?>" alt="Foto Profil" style="width: 80px; height: 80px; border-radius: 20px; object-fit: cover; border: 2px solid var(--sage-300);">
        <?php else: ?>
            <div style="width: 80px; height: 80px; border-radius: 20px; background: var(--sage-100); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; color:var(--sage-800); border: 2px solid var(--sage-300);">
                <?= e(inisial_nama($nama_murid)) ?>
            </div>
        <?php endif; ?>
        <div>
            <label style="margin-bottom:4px; display:block;">Ubah Foto Profil</label>
            <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" style="margin:0; padding:8px; width:auto; border:none; background:transparent;">
            <p class="muted" style="margin:4px 0 0 0; font-size:11px;">Format: JPG, PNG, WEBP (Maks: 2MB)</p>
        </div>
    </div>
    
    <label>Nama</label><input name="nama" value="<?= e($user['nama']) ?>" required>
    <label>Email</label><input type="email" name="email" value="<?= e($user['email']) ?>" required>
    
    <hr style="border:0; border-top:1px dashed var(--line); margin: 24px 0;">
    <h3 style="font-size:15px; margin-bottom:12px;">Ubah Kata Sandi</h3>
    <label>Password Lama (isi jika ingin mengganti password)</label><input type="password" name="password_lama">
    <label>Password Baru</label><input type="password" name="password_baru" minlength="8">
    <button class="btn" name="update_profil">Simpan Perubahan</button>
  </form></div>

<!-- ================= HALAMAN LAPORAN ================= -->
<?php elseif ($page === 'laporan'): ?>
  <div class="card"><h2>🚩 Lapor Pelanggaran</h2>
  <form method="post">
    <label>Pengguna yang dilaporkan</label>
    <select name="terlapor_id" required><option value="">Pilih pengguna</option>
    <?php foreach(stmt_rows($koneksi,"SELECT id,nama,jenis FROM login WHERE jenis IN ('guru','murid') AND id<>? ORDER BY nama",'i',[$uid]) as $x): ?>
      <option value="<?= (int)$x['id'] ?>"><?= e($x['nama']) ?> (<?= e($x['jenis']) ?>)</option>
    <?php endforeach; ?></select>
    <label>Kategori</label><select name="kategori" required><option value="">Pilih kategori</option><option>Perkataan Kasar / Bullying</option><option>Pelecehan / Tindak Kekerasan</option><option>Kecurangan / Menyontek</option><option>Sikap Tidak Profesional</option><option>Lainnya</option></select>
    <label>Keterangan</label><textarea name="deskripsi" rows="4" required></textarea>
    <button class="btn" name="kirim_laporan" style="background: #C62828;">Kirim Laporan</button>
  </form>
  </div>

<!-- ================= HALAMAN BERANDA ================= -->
<?php else: ?>
  <div class="card">
      <h1 style="margin-bottom: 8px;">Halo, <?= e($nama_murid) ?> 👋</h1>
      <p class="muted">Selamat datang di SLearning SMK Negeri 7 Batam.</p>
  </div>
  
  <div class="grid">
    <div class="card stat"><span class="stat-icon">📝</span><div><h2><?= $statTugas ?></h2><p class="muted">Tugas Aktif</p></div></div>
    <div class="card stat"><span class="stat-icon">🎮</span><div><h2><?= $statQuiz ?></h2><p class="muted">Quiz Tersedia</p></div></div>
    <div class="card stat"><span class="stat-icon">🏫</span><div><h2><?= count($kelasSaya) ?></h2><p class="muted">Kelas Diikuti</p></div></div>
  </div>
  
  <div class="card">
      <h2>Aktivitas & Pengumuman Terbaru</h2>
      <?php if (!$notifikasi): ?>
          <p class="muted">Belum ada aktivitas atau pengumuman baru.</p>
      <?php else: 
          $tampil_beranda = array_slice($notifikasi, 0, 5); // Tampilkan 5 pemberitahuan teratas
          foreach($tampil_beranda as $n): 
              $is_pengumuman = ($n['tipe'] === 'pengumuman');
      ?>
          <div class="notif-item">
            <p style="margin: 0 0 6px 0;">
                <span class="badge <?= $is_pengumuman ? 'badge-pengumuman' : '' ?>">
                    <?= $is_pengumuman ? '📢 PENGUMUMAN' : e(strtoupper($n['tipe'])) ?>
                </span> 
                <b style="margin-left: 4px;"><?= e($n['judul']) ?></b> 
                <span class="muted" style="font-size: 11px; margin-left: 6px;">• <?= e(tanggal_id($n['tanggal'])) ?></span>
            </p>
            <?php if ($is_pengumuman && !empty($n['isi'])): ?>
                <p style="margin: 0; font-size: 12.5px; color: var(--muted); padding-left: 4px;">
                    <?= nl2br(e(strlen($n['isi']) > 120 ? substr($n['isi'], 0, 120).'...' : $n['isi'])) ?>
                </p>
            <?php endif; ?>
          </div>
      <?php endforeach; endif; ?>
      
      <?php if(count($notifikasi) > 5): ?>
          <div style="margin-top: 16px; text-align: center;">
              <a href="murid.php?page=notif" class="btn btn-light" style="width: 100%;">Lihat Semua Pemberitahuan</a>
          </div>
      <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ================= MODAL BUKU PANDUAN ================= -->
<div id="modalPanduan" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3>
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        Panduan Siswa
      </h3>
      <button onclick="tutupPanduan()" class="close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <ul>
        <li><strong>Beranda:</strong> Halaman utama berisi ringkasan tugas aktif, quiz, dan pengumuman sekolah.</li>
        <li><strong>Kelas:</strong> Tempat untuk meminta izin bergabung kelas menggunakan kode dari guru.</li>
        <li><strong>Absensi:</strong> Fitur untuk mengisi kehadiran (Hadir/Izin/Sakit) pada sesi absensi yang telah dibuka.</li>
        <li><strong>Tugas:</strong> Panel untuk melihat instruksi tugas yang diberikan dan mengunggah file jawaban.</li>
        <li><strong>Quiz:</strong> Mengerjakan kuis pilihan ganda yang disiapkan oleh guru pengampu.</li>
        <li><strong>Lapor:</strong> Mengirimkan laporan pelanggaran tata tertib langsung ke pihak sekolah/waka.</li>
      </ul>
    </div>
  </div>
</div>
</main>

<script>
function toggleDropdown(e) {
    e.preventDefault();
    document.getElementById("menuLanjutan").classList.toggle("show-dropdown");
}
function bukaPanduan(e) {
    e.preventDefault();
    document.getElementById('modalPanduan').style.display = 'flex';
}
function tutupPanduan() {
    document.getElementById('modalPanduan').style.display = 'none';
}
window.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        let dropdown = document.getElementById("menuLanjutan");
        if (dropdown && dropdown.classList.contains('show-dropdown')) {
            dropdown.classList.remove('show-dropdown');
        }
    }
    let modal = document.getElementById('modalPanduan');
    if (e.target === modal) {
        tutupPanduan();
    }
});
</script>
</body>
</html>
<?php
// SLearning — Dashboard Guru (FULL VERSION - Clean & Minimalist Class Card)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['jenis'] ?? '') === 'murid') { header("Location: login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

// AUTO-MIGRASI: Buat tabel lobi kuis jika belum ada
$cek_lobby = mysqli_query($koneksi, "SHOW TABLES LIKE 'quiz_lobby'");
if(mysqli_num_rows($cek_lobby) === 0) {
    mysqli_query($koneksi, "CREATE TABLE quiz_lobby (
        quiz_id INT NOT NULL, 
        user_id INT NOT NULL, 
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
        PRIMARY KEY(quiz_id, user_id)
    )");
}

// AUTO-MIGRASI: Tambahkan kolom foto_profil di tabel login jika belum ada
$cek_foto = mysqli_query($koneksi, "SHOW COLUMNS FROM login LIKE 'foto_profil'");
if(mysqli_num_rows($cek_foto) === 0) {
    mysqli_query($koneksi, "ALTER TABLE login ADD foto_profil VARCHAR(255) NULL AFTER email");
}

// =========================================================================
// API GURU: Tarik jumlah murid di lobi secara Real-Time
// =========================================================================
if (isset($_GET['page']) && $_GET['page'] === 'api_cek_lobby') {
    $qid = (int)$_GET['quiz_id'];
    mysqli_query($koneksi, "DELETE FROM quiz_lobby WHERE waktu < (NOW() - INTERVAL 10 SECOND)");
    $q_jml = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM quiz_lobby WHERE quiz_id = $qid");
    $jml = $q_jml ? mysqli_fetch_assoc($q_jml)['jml'] : 0;
    header('Content-Type: application/json');
    echo json_encode(['jumlah' => (int)$jml]);
    exit;
}

// =========================================================================
// API GURU: Cek Notifikasi Tugas Baru (Real-Time)
// =========================================================================
if (isset($_GET['page']) && $_GET['page'] === 'api_cek_notif') {
    $q_notif_api = mysqli_query($koneksi, "
        SELECT COUNT(pt.id) AS jml_baru 
        FROM pengumpulan_tugas pt 
        JOIN tugas t ON pt.tugas_id = t.id 
        WHERE t.guru_id = $user_id AND (pt.nilai IS NULL OR pt.nilai = 0 OR pt.nilai = '')
    ");
    $jml_baru = $q_notif_api ? mysqli_fetch_assoc($q_notif_api)['jml_baru'] : 0;
    header('Content-Type: application/json');
    echo json_encode(['jumlah_notif' => (int)$jml_baru]);
    exit;
}
// =========================================================================

$page = $_GET['page'] ?? 'beranda';
$detail_kelas_id = (int)($_GET['id'] ?? 0);
$quiz_id_get = (int)($_GET['quiz_id'] ?? 0);

function flash_set($msg, $type='success'){ $_SESSION['flash_msg']=$msg; $_SESSION['flash_type']=$type; }
$flash_msg = $_SESSION['flash_msg'] ?? ''; $flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// ---------- PROSES AKSI POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    $target_page = $_POST['redirect_page'] ?? 'beranda';

    // Update Profil & Avatar
    if ($aksi === 'update_profil') {
        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');
        $pass_lama = $_POST['password_lama'] ?? '';
        $pass_baru = $_POST['password_baru'] ?? '';

        if ($nama_baru !== '' && $email_baru !== '') {
            $q_cek = mysqli_query($koneksi, "SELECT password, foto_profil FROM login WHERE id = $user_id LIMIT 1");
            $res_cek = $q_cek ? mysqli_fetch_assoc($q_cek) : null;
            
            $set_sql = "nama = ?, email = ?";
            $params = [$nama_baru, $email_baru];
            $types = "ss";

            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $dir = __DIR__ . '/uploads/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $filename = 'profil_' . $user_id . '_' . time() . '.' . $ext;
                    
                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $dir . $filename)) {
                        $set_sql .= ", foto_profil = ?";
                        $params[] = $filename;
                        $types .= "s";
                        
                        if(!empty($res_cek['foto_profil']) && file_exists($dir . $res_cek['foto_profil'])) {
                            unlink($dir . $res_cek['foto_profil']);
                        }
                    }
                } else {
                    flash_set('Format foto harus JPG, JPEG, PNG, atau GIF!', 'error'); header("Location: guru.php?page=profil"); exit;
                }
            }

            if (!empty($pass_baru)) {
                if ($res_cek && password_verify($pass_lama, $res_cek['password'])) {
                    $set_sql .= ", password = ?";
                    $params[] = password_hash($pass_baru, PASSWORD_DEFAULT); $types .= "s";
                } else {
                    flash_set('Password lama salah!', 'error'); header("Location: guru.php?page=profil"); exit;
                }
            }

            $params[] = $user_id; $types .= "i";
            
            $upd = mysqli_prepare($koneksi, "UPDATE login SET $set_sql WHERE id = ?");
            mysqli_stmt_bind_param($upd, $types, ...$params);
            if (mysqli_stmt_execute($upd)) { $_SESSION['nama'] = $nama_baru; flash_set('Profil dan pengaturan berhasil diperbarui.'); }
            mysqli_stmt_close($upd);
        }
        header("Location: guru.php?page=profil"); exit;
    }

    if ($aksi === 'tambah_kelas') {
        $nama = trim($_POST['nama_kelas_baru'] ?? '');
        if ($nama !== '') {
            $kode = 'KLS-'.strtoupper(substr(md5(mt_rand().time()),0,5));
            $s = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, kode_gabung, guru_id) VALUES (?,?,?)");
            mysqli_stmt_bind_param($s, "ssi", $nama, $kode, $user_id);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); flash_set('Kelas "'.$nama.'" dibuat.');
        }
        header("Location: guru.php?page=".$target_page); exit;
    }
    if ($aksi === 'hapus_kelas') {
        $id = (int)($_POST['kelas_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM kelas WHERE id=$id AND guru_id=$user_id");
        flash_set('Kelas dihapus.'); header("Location: guru.php?page=".$target_page); exit;
    }

    if ($aksi === 'buat_absen') {
        $kelas_id = (int)($_POST['kelas_id'] ?? 0); $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $judul = trim($_POST['judul_absensi'] ?? '') ?: ('Absensi — '.$tanggal);
        if ($kelas_id > 0) {
            $s = mysqli_prepare($koneksi, "INSERT INTO absensi (guru_id, kelas_id, judul_absensi, tanggal) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($s, "iiss", $user_id, $kelas_id, $judul, $tanggal);
            mysqli_stmt_execute($s); mysqli_stmt_close($s); flash_set('Absensi dibuka.');
        }
        header("Location: guru.php?page=absensi"); exit;
    }
    if ($aksi === 'hapus_absen') {
        $id = (int)($_POST['absen_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM absensi_hadir WHERE absensi_id=$id");
        mysqli_query($koneksi, "DELETE FROM absensi WHERE id=$id AND guru_id=$user_id");
        flash_set('Absensi dihapus.'); header("Location: guru.php?page=absensi"); exit;
    }

    if ($aksi === 'tambah_tugas') {
        $judul = trim($_POST['judul_tugas'] ?? ''); $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $deadline = str_replace('T',' ',trim($_POST['deadline'] ?? '')); $desk = trim($_POST['deskripsi'] ?? '');
        if ($judul !== '' && $kelas_id > 0) {
            if ($deadline === '') $deadline = null;
            $st = mysqli_prepare($koneksi, "INSERT INTO tugas (guru_id, kelas_id, judul_tugas, deadline, deskripsi) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, "iisss", $user_id, $kelas_id, $judul, $deadline, $desk);
            mysqli_stmt_execute($st); mysqli_stmt_close($st); flash_set('Tugas dipublikasikan.');
        }
        header("Location: guru.php?page=tugas"); exit;
    }
    if ($aksi === 'hapus_tugas') {
        $id = (int)($_POST['tugas_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM pengumpulan_tugas WHERE tugas_id=$id");
        mysqli_query($koneksi, "DELETE FROM tugas WHERE id=$id AND guru_id=$user_id");
        flash_set('Tugas dihapus.'); header("Location: guru.php?page=tugas"); exit;
    }
    if ($aksi === 'update_deadline') {
        $tugas_id = (int)($_POST['tugas_id'] ?? 0);
        $deadline_baru = str_replace('T',' ',trim($_POST['deadline'] ?? ''));
        if (empty($deadline_baru)) $deadline_baru = null;
        $upd_dl = mysqli_prepare($koneksi, "UPDATE tugas SET deadline = ? WHERE id = ? AND guru_id = ?");
        mysqli_stmt_bind_param($upd_dl, "sii", $deadline_baru, $tugas_id, $user_id);
        mysqli_stmt_execute($upd_dl); mysqli_stmt_close($upd_dl); flash_set('Deadline diperbarui.');
        header("Location: guru.php?page=detail_tugas&id=".$tugas_id); exit;
    }
    if ($aksi === 'beri_nilai') {
        $pengumpulan_id = (int)($_POST['pengumpulan_id'] ?? 0); $nilai = (int)($_POST['nilai'] ?? 0); $feedback = trim($_POST['feedback'] ?? '');
        $tugas_id = (int)($_POST['tugas_id'] ?? 0);
        $up_n = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nilai = ?, feedback = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_n, "isi", $nilai, $feedback, $pengumpulan_id);
        mysqli_stmt_execute($up_n); mysqli_stmt_close($up_n); flash_set('Nilai disimpan.');
        header("Location: guru.php?page=detail_tugas&id=".$tugas_id); exit;
    }

    if ($aksi === 'buat_kuis') {
        $judul_kuis = trim($_POST['judul_kuis'] ?? 'Kuis Baru');
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $mode_kuis = 'Live Quiz'; 
        $durasi = 0; 
        if ($judul_kuis !== '' && $kelas_id > 0) {
            $sq = mysqli_prepare($koneksi, "INSERT INTO quiz (guru_id, kelas_id, judul, mode_kuis, durasi_menit, status) VALUES (?, ?, ?, ?, ?, 'menunggu')");
            if($sq){
                mysqli_stmt_bind_param($sq, "iissi", $user_id, $kelas_id, $judul_kuis, $mode_kuis, $durasi);
                mysqli_stmt_execute($sq);
                $new_quiz_id = mysqli_insert_id($koneksi); 
                mysqli_stmt_close($sq);
                flash_set('Kuis berhasil dibuat! Silakan mulai menambahkan soal di bawah ini.');
                header("Location: guru.php?page=kelola_soal&quiz_id=" . $new_quiz_id); exit;
            }
        }
    }
    if ($aksi === 'hapus_kuis') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM quiz_lobby WHERE quiz_id = $quiz_id");
        mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE quiz_id = $quiz_id");
        mysqli_query($koneksi, "DELETE FROM quiz_hasil WHERE quiz_id = $quiz_id");
        $dq = mysqli_prepare($koneksi, "DELETE FROM quiz WHERE id = ? AND guru_id = ?");
        if($dq){
            mysqli_stmt_bind_param($dq, "ii", $quiz_id, $user_id);
            mysqli_stmt_execute($dq);
            mysqli_stmt_close($dq);
            flash_set('Kuis beserta soal & hasilnya berhasil dihapus dari riwayat.');
        }
        header("Location: guru.php?page=quiz"); exit;
    }
    if ($aksi === 'tambah_soal') {
        $q_id = (int)$_POST['quiz_id'];
        $pertanyaan = trim($_POST['pertanyaan']);
        $opsi_a = trim($_POST['opsi_a']); $opsi_b = trim($_POST['opsi_b']); $opsi_c = trim($_POST['opsi_c']); $opsi_d = trim($_POST['opsi_d']);
        $kunci = $_POST['kunci'] ?? 'A';
        if(!empty($pertanyaan) && !empty($opsi_a)) {
            $ins = mysqli_prepare($koneksi, "INSERT INTO quiz_soal (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "issssss", $q_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $kunci);
            mysqli_stmt_execute($ins); mysqli_stmt_close($ins); flash_set('Soal berhasil ditambahkan ke dalam kuis.');
        }
        header("Location: guru.php?page=kelola_soal&quiz_id=".$q_id); exit;
    }
    if ($aksi === 'hapus_soal') {
        $q_id = (int)$_POST['quiz_id']; $s_id = (int)$_POST['soal_id'];
        mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE id = $s_id AND quiz_id = $q_id");
        flash_set('Soal berhasil dihapus.'); header("Location: guru.php?page=kelola_soal&quiz_id=".$q_id); exit;
    }
    if ($aksi === 'ubah_status_kuis') {
        $q_id = (int)$_POST['quiz_id']; $status_baru = $_POST['status_baru'];
        $upd_status = mysqli_prepare($koneksi, "UPDATE quiz SET status = ? WHERE id = ? AND guru_id = ?");
        if ($upd_status) {
            mysqli_stmt_bind_param($upd_status, "sii", $status_baru, $q_id, $user_id);
            mysqli_stmt_execute($upd_status); mysqli_stmt_close($upd_status);
            if ($status_baru === 'mulai') { flash_set('Kuis telah dimulai! Siswa sekarang dapat mengerjakan soal secara serentak.'); }
            else if ($status_baru === 'selesai') { flash_set('Kuis telah diakhiri.'); }
        }
        header("Location: guru.php?page=live_monitor&quiz_id=".$q_id); exit;
    }
}

$q_user = mysqli_query($koneksi, "SELECT id, nama, username, email, jenis, level, foto_profil FROM login WHERE id = $user_id LIMIT 1");
$user = $q_user ? mysqli_fetch_assoc($q_user) : ['nama' => 'Guru', 'username' => 'guru', 'email' => '', 'foto_profil' => ''];
$nama_guru = $user['nama'] ?? 'Guru';
$email_guru = $user['email'] ?? '';
$foto_profil = $user['foto_profil'] ?? '';

$list_kelas = [];
$r_kls = mysqli_query($koneksi, "SELECT id AS id_kelas, nama_kelas, kode_gabung, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=k.id) AS jml_murid FROM kelas k WHERE k.guru_id=$user_id ORDER BY k.id DESC");
if($r_kls) { while ($r = mysqli_fetch_assoc($r_kls)) $list_kelas[] = $r; }
$jml_kelas = count($list_kelas);
$jml_tugas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM tugas WHERE guru_id=$user_id"))['c'] ?? 0;
$jml_absen = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM absensi WHERE guru_id=$user_id"))['c'] ?? 0;

$notif_tugas_belum_dinilai = []; 
$q_notif = mysqli_query($koneksi, "SELECT pt.id, pt.dikumpulkan_at, l.nama AS nama_murid, t.judul_tugas, k.nama_kelas, t.kelas_id, pt.tugas_id FROM pengumpulan_tugas pt LEFT JOIN login l ON pt.user_id = l.id JOIN tugas t ON pt.tugas_id = t.id JOIN kelas k ON t.kelas_id = k.id WHERE t.guru_id = $user_id AND (pt.nilai IS NULL OR pt.nilai = 0 OR pt.nilai = '') ORDER BY pt.dikumpulkan_at DESC LIMIT 5");
if($q_notif) { while ($rn = mysqli_fetch_assoc($q_notif)) { $notif_tugas_belum_dinilai[] = $rn; } }

$tgl_hari_ini = date('Y-m-d'); $absen_hari_ini = [];
if ($page === 'beranda') {
    $q_absen_hari = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, k.nama_kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid, (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS total_hadir FROM absensi a JOIN kelas k ON a.kelas_id = k.id WHERE a.guru_id = $user_id AND a.tanggal = '$tgl_hari_ini'");
    if($q_absen_hari) { while ($ra = mysqli_fetch_assoc($q_absen_hari)) { $absen_hari_ini[] = $ra; } }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if($page === 'live_monitor'): 
    $q_status_cek = mysqli_query($koneksi, "SELECT status FROM quiz WHERE id = $quiz_id_get");
    $s_cek = $q_status_cek ? mysqli_fetch_assoc($q_status_cek)['status'] : '';
    if($s_cek === 'mulai'):
?>
<meta http-equiv="refresh" content="5">
<?php endif; endif; ?>
<title>Dashboard Guru — Slearning</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root { 
    --nav-yellow: #FFF8D6; --nav-yellow-dark: #F2E7B5; --sage-body: #F4F7F5; --sage-50: #E8F0E8; --sage-100: #D1E0D1;
    --sage-300: #9FBA9F; --sage-500: #6D8C6D; --sage-600: #577357; --sage-800: #2F402F; --sage-900: #1D261D;
    --bg-card: #FFFFFF; --shadow-sm: 0 4px 12px rgba(109, 140, 109, 0.05); --shadow-md: 0 8px 24px rgba(109, 140, 109, 0.08);
    --shadow-lg: 0 16px 40px rgba(109, 140, 109, 0.12); --radius-sm: 12px; --radius-md: 16px; --radius-lg: 24px;
}
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; padding-bottom: 60px; position: relative; overflow-x: hidden; }
body::before { content: ''; position: fixed; top: -10%; left: -5%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(255, 248, 214, 0.8) 0%, rgba(255, 248, 214, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
body::after { content: ''; position: fixed; bottom: -10%; right: -5%; width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(109, 140, 109, 0.15) 0%, rgba(109, 140, 109, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); position: relative; z-index: 10; padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 32px 16px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); letter-spacing: -0.5px; }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }
.header-icons { display: flex; align-items: center; gap: 20px; }
.header-icons a.icon-btn { color: var(--sage-600); transition: all 0.3s ease; position: relative; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 10px; border-radius: 50%; border: 1px solid var(--sage-50); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.header-icons a.icon-btn:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); border-color: var(--sage-100); }
.header-icons svg { width: 20px; height: 20px; }
.notif-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #E07A5F; border-radius: 50%; border: 2px solid #FFFFFF; }
.nav-tabs { display: flex; justify-content: center; gap: 8px; max-width: 1200px; margin: 0 auto; padding: 0 32px; }
.nav-tab { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 28px; text-decoration: none; color: var(--sage-600); font-size: 13px; font-weight: 600; transition: all 0.3s ease; position: relative; border-radius: var(--radius-sm) var(--radius-sm) 0 0; }
.nav-tab svg { width: 20px; height: 20px; stroke-width: 2.2; }
.nav-tab:hover { color: var(--sage-800); background: rgba(255, 255, 255, 0.6); }
.nav-tab.active { color: var(--sage-900); background: #FFFFFF; }
.nav-tab.active::after { content: ''; position: absolute; bottom: 0; left: 15%; width: 70%; height: 3px; background: var(--sage-500); border-radius: 4px 4px 0 0; }
.container { max-width: 1100px; margin: 0 auto; padding: 40px 32px; position: relative; z-index: 1; }

.alert { padding: 16px 24px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
.alert.success { background: var(--sage-50); color: var(--sage-800); border-left: 4px solid var(--sage-500); }
.alert.error { background: #FFF3F0; color: #E07A5F; border-left: 4px solid #E07A5F; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.form-label { display: block; font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 8px; }

.card-manage { background: var(--bg-card); border-radius: var(--radius-lg); padding: 36px; margin-bottom: 48px; border: 1px solid var(--sage-50); box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
.card-manage h2 { font-size: 20px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px; letter-spacing: -0.3px; }
.card-manage p { font-size: 14px; color: var(--sage-600); font-weight: 500; }
.btn-primary { background: var(--sage-500); color: #FFFFFF; border: none; padding: 12px 28px; border-radius: 50px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(109, 140, 109, 0.3); text-decoration: none; }
.btn-primary:hover { background: var(--sage-600); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(109, 140, 109, 0.4); }
.btn-primary svg { width: 18px; height: 18px; }
.btn-outline { background: #FFFFFF; color: var(--sage-600); border: 1px solid var(--sage-300); padding: 12px 28px; border-radius: 50px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; text-decoration: none; }
.btn-outline:hover { background: var(--sage-50); color: var(--sage-800); }
.input-elegant { width: 100%; background: var(--sage-body); border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 16px 20px; font-size: 14px; font-weight: 500; color: var(--sage-900); outline: none; transition: all 0.3s ease; }
.input-elegant:focus { border-color: var(--sage-500); background: #FFFFFF; box-shadow: 0 0 0 4px rgba(109, 140, 109, 0.1); }
.section-title { font-size: 18px; font-weight: 800; color: var(--sage-800); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.section-title::before { content: ''; display: block; width: 8px; height: 24px; background: var(--sage-500); border-radius: 4px; }
.class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }

/* TAMPILAN KARTU KELAS SUPER BERSIH (MINIMALIS) */
.class-card { background: var(--bg-card); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); border: 1px solid var(--sage-50); transition: all 0.3s ease; position: relative; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
.class-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--sage-100); }
.class-card-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.class-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--sage-50); color: var(--sage-600); border: 1px solid var(--sage-100); }
.class-info h4 { font-size: 16.5px; font-weight: 800; color: var(--sage-800); margin-bottom: 4px; }
.class-info .murid-count { font-size: 12.5px; color: var(--sage-600); font-weight: 500; display: flex; align-items: center; gap: 5px; }

/* KODE GABUNG BERSIH & RAPI (TIDAK RUSUH) */
.kode-badge { background: #FAFCFA; border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 16px; width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.kode-text { font-size: 13px; font-weight: 700; color: var(--sage-800); display: flex; align-items: center; gap: 6px; font-family: monospace; }
.kode-actions { display: flex; gap: 4px; }
.btn-action-clean { background: #FFFFFF; border: 1px solid var(--sage-100); color: var(--sage-700); padding: 6px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.btn-action-clean:hover { background: var(--sage-50); color: var(--sage-900); border-color: var(--sage-300); }

.btn-card-action { display: flex; gap: 8px; margin-top: auto; }
.btn-hapus { background: transparent; color: #E07A5F; border: 1px solid #F4E3E0; padding: 10px 14px; border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; transition: 0.2s; }
.btn-hapus:hover { background: #FFF3F0; }
.btn-masuk-kelas { background: var(--sage-500); color: #fff; border: none; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 12.5px; font-weight: 700; flex: 1; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
.btn-masuk-kelas:hover { background: var(--sage-600); }
.empty-state { text-align: center; padding: 40px 20px; color: var(--sage-600); background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--sage-300); }

/* CSS BERANDA */
.beranda-hero { background: linear-gradient(135deg, var(--sage-50) 0%, #FFFFFF 100%); border-radius: var(--radius-lg); padding: 40px; border: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: var(--shadow-sm); }
.beranda-hero h1 { font-size: 26px; font-weight: 800; color: var(--sage-800); margin-bottom: 8px; }
.beranda-hero p { font-size: 14.5px; color: var(--sage-600); font-weight: 500; max-width: 550px; line-height: 1.6; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 36px; }
.stat-card { background: #FFFFFF; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 18px; }
.stat-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--nav-yellow); display: flex; align-items: center; justify-content: center; color: var(--sage-800); font-size: 24px; font-weight: 800; }
.dashboard-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px; }
.panel { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); padding: 24px; }
.panel-header { font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.panel-item { padding: 16px; border: 1px solid var(--sage-50); border-radius: var(--radius-sm); background: var(--sage-body); display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:10px; margin-bottom:12px;}
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.quick-btn { background: #FFFFFF; border: 1px solid var(--sage-100); border-radius: var(--radius-md); padding: 20px; text-decoration: none; color: var(--sage-800); font-weight: 700; display: flex; align-items: center; gap: 14px; transition: 0.3s; box-shadow: var(--shadow-sm); }
.quick-btn:hover { background: var(--sage-50); border-color: var(--sage-300); transform: translateY(-2px); }

/* CSS DETAIL TUGAS & ABSENSI */
.task-section-box { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-100); margin-bottom: 24px; overflow: hidden; box-shadow: var(--shadow-sm); }
.task-section-header { background: var(--sage-50); padding: 20px 24px; border-bottom: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.student-row-list { list-style: none; padding: 0; margin: 0; }
.student-row-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--sage-50); flex-wrap: wrap; gap: 16px; }
.student-row-item:last-child { border-bottom: none; }
.badge-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 50px; }
.badge-hijau { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.badge-merah { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }
.badge-kuning { background: #FFF8D6; color: #856404; border: 1px solid #E2D38A; }

/* CSS QUIZ BUILDER */
.quiz-builder-container { display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; align-items: start; }
.builder-main { display: flex; flex-direction: column; gap: 24px; }
.builder-sidebar { position: sticky; top: 20px; background: #fff; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); }
.question-block { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-100); padding: 28px; box-shadow: var(--shadow-sm); }
.option-row { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; background: var(--sage-body); border-radius: var(--radius-sm); padding: 12px 16px; border: 1px solid transparent; }
.existing-question { background: #FAFCFA; border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 20px; margin-bottom: 16px; }
.table-leaderboard { width: 100%; border-collapse: collapse; background: #fff; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--sage-50); }
.table-leaderboard th { background: var(--sage-50); padding: 16px; text-align: left; font-size: 14px; font-weight: 800; color: var(--sage-800); border-bottom: 2px solid var(--sage-100); }
.table-leaderboard td { padding: 16px; border-bottom: 1px solid var(--sage-50); font-size: 14px; color: var(--sage-900); font-weight: 600; }
@media (max-width: 900px) { .quiz-builder-container { grid-template-columns: 1fr; } .builder-sidebar { position: static; } }
@media (max-width: 768px) { 
    .header-top { flex-wrap: wrap; gap: 16px; } 
    .nav-tabs { overflow-x: auto; justify-content: flex-start; padding: 0 16px; } 
    .form-grid { grid-template-columns: 1fr; } 
    .beranda-hero { flex-direction: column; align-items: flex-start; gap: 20px; } 
    .dashboard-panels { grid-template-columns: 1fr; } 
}

/* CSS DROPDOWN NOTIFIKASI */
.notif-container { position: relative; }
.notif-dropdown { position: absolute; top: 55px; right: 0; width: 340px; background: #fff; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); border: 1px solid var(--sage-100); z-index: 1000; display: none; flex-direction: column; overflow: hidden; transform-origin: top right; animation: scaleIn 0.2s ease-out; }
.notif-dropdown.show { display: flex; }
@keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.notif-header { padding: 16px; border-bottom: 1px solid var(--sage-50); background: var(--sage-50); display: flex; justify-content: space-between; align-items: center; }
.notif-header h4 { font-size: 14px; margin: 0; color: var(--sage-900); font-weight: 800; }
.notif-body { max-height: 350px; overflow-y: auto; }
.notif-item { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border-bottom: 1px solid var(--sage-50); text-decoration: none; transition: background 0.2s; }
.notif-item:hover { background: #FAFCFA; }
.notif-item:last-child { border-bottom: none; }
.notif-icon { width: 36px; height: 36px; background: var(--nav-yellow); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; border: 1px solid #E2D38A; }
.notif-footer { padding: 12px; text-align: center; border-top: 1px solid var(--sage-50); background: #fff; }
.notif-footer a { font-size: 12px; font-weight: 700; color: var(--sage-600); text-decoration: none; transition: 0.2s; }
.notif-footer a:hover { color: var(--sage-800); text-decoration: underline; }
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="guru.php?page=beranda" class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      Slearning
    </a>
    <div class="header-icons">
      
      <!-- DROPDOWN NOTIFIKASI MULAI -->
      <div class="notif-container" id="notif-wrapper">
          <a href="#" title="Notifikasi" class="icon-btn" onclick="toggleNotif(event)">
            <?php if(!empty($notif_tugas_belum_dinilai)): ?><div class="notif-dot"></div><?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </a>
          
          <div class="notif-dropdown" id="dropdown-notif">
              <div class="notif-header">
                  <h4>Notifikasi Tugas Baru</h4>
                  <span style="font-size: 11px; background: #E07A5F; color: #fff; padding: 2px 8px; border-radius: 50px; font-weight: 800;"><?= count($notif_tugas_belum_dinilai ?? []) ?> Baru</span>
              </div>
              <div class="notif-body">
                  <?php if(empty($notif_tugas_belum_dinilai)): ?>
                      <div style="padding: 30px 16px; text-align: center; color: var(--sage-600); font-size: 13px;">Belum ada tugas baru yang terkumpul.</div>
                  <?php else: ?>
                      <?php foreach($notif_tugas_belum_dinilai as $nt): ?>
                          <a href="guru.php?page=detail_tugas&id=<?= $nt['tugas_id'] ?>" class="notif-item">
                              <div class="notif-icon">📄</div>
                              <div>
                                  <strong style="display: block; font-size: 14px; color: var(--sage-900);"><?= e($nt['nama_murid']) ?></strong>
                                  <span style="display: block; font-size: 12px; color: var(--sage-600); margin-top: 4px; line-height: 1.4;">Mengumpulkan tugas: <strong><?= e($nt['judul_tugas']) ?></strong></span>
                                  <span style="display: block; font-size: 11px; color: #9ca3af; margin-top: 6px;">📅 <?= date('d M Y, H:i', strtotime($nt['dikumpulkan_at'])) ?></span>
                              </div>
                          </a>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
              <div class="notif-footer">
                  <a href="guru.php?page=beranda">Lihat Semua di Dashboard Beranda →</a>
              </div>
          </div>
      </div>
      <!-- DROPDOWN NOTIFIKASI SELESAI -->
      
      <a href="guru.php?page=profil" title="Profil Anda" style="padding:0; border:none; overflow:hidden; width:44px; height:44px; border-radius:50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 2px solid var(--sage-100); transition: 0.3s;">
        <?php if(!empty($foto_profil)): ?>
            <img src="uploads/<?= e($foto_profil) ?>" alt="Profil" style="width:100%; height:100%; object-fit:cover;">
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 22px; height: 22px; color: var(--sage-600);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?php endif; ?>
      </a>

      <a href="logout.php" onclick="return confirm('Keluar dari Slearning?')" title="Keluar" class="icon-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>

  <nav class="nav-tabs">
    <a href="guru.php?page=beranda" class="nav-tab <?= $page==='beranda'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> <span>Beranda</span></a>
    <a href="guru.php?page=kelas" class="nav-tab <?= ($page==='kelas' || $page==='detail_kelas' || $page==='detail_tugas')?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> <span>Kelas</span></a>
    <a href="guru.php?page=absensi" class="nav-tab <?= ($page==='absensi' || $page==='detail_absensi')?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg> <span>Absensi</span></a>
    <a href="guru.php?page=tugas" class="nav-tab <?= $page==='tugas'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> <span>Tugas</span></a>
    <a href="guru.php?page=quiz" class="nav-tab <?= ($page==='quiz' || $page==='kelola_soal' || $page==='live_monitor')?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="14" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="18"/><line x1="9" y1="10" x2="9.01" y2="10"/></svg> <span>Quiz</span></a>
  </nav>
</header>

<main class="container">
  <?php if ($flash_msg): ?>
    <div class="alert <?= $flash_type==='error'?'error':'success' ?>"><?= $flash_type==='error' ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' ?> <?= e($flash_msg) ?></div>
  <?php endif; ?>

  <!-- ================= HALAMAN BERANDA ================= -->
  <?php if ($page === 'beranda'): ?>
    <div class="beranda-hero">
      <div>
        <h1>Halo, <?= e($nama_guru) ?> 👋</h1>
        <p>Selamat datang di panel pengajar Slearning. Pantau pemberitahuan tugas yang siap dinilai dan kehadiran murid hari ini.</p>
      </div>
      <a href="guru.php?page=kelas" class="btn-primary">Kelola Kelas</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-icon">🏫</div><div class="stat-info"><h3><?= $jml_kelas ?></h3><p>Total Kelas Aktif</p></div></div>
      <div class="stat-card"><div class="stat-icon">📄</div><div class="stat-info"><h3><?= $jml_tugas ?></h3><p>Tugas Dipublikasi</p></div></div>
      <div class="stat-card"><div class="stat-icon">📅</div><div class="stat-info"><h3><?= $jml_absen ?></h3><p>Sesi Absensi Terbuat</p></div></div>
    </div>

    <div class="dashboard-panels">
        <div class="panel">
            <div class="panel-header"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#E07A5F" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Tugas Perlu Dinilai</div>
            <?php if (empty($notif_tugas_belum_dinilai)): ?>
                <div class="empty-state" style="padding: 20px;"><p>Belum ada tugas baru yang perlu dinilai.</p></div>
            <?php else: ?>
                <div class="panel-list">
                    <?php foreach($notif_tugas_belum_dinilai as $nt): ?>
                        <div class="panel-item">
                            <div><h4><?= e($nt['nama_murid'] ?? 'Siswa Tidak Diketahui') ?></h4><p><?= e($nt['judul_tugas']) ?> - <?= e($nt['nama_kelas']) ?></p></div>
                            <a href="guru.php?page=detail_tugas&id=<?= $nt['tugas_id'] ?>" class="btn-primary" style="padding: 8px 12px; font-size: 12px;">Beri Nilai</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Absensi Murid Hari Ini</div>
            <?php if (empty($absen_hari_ini)): ?>
                <div class="empty-state" style="padding: 20px;"><p>Tidak ada sesi absensi untuk hari ini.</p></div>
            <?php else: ?>
                <div class="panel-list">
                    <?php foreach($absen_hari_ini as $ah): ?>
                        <div class="panel-item">
                            <div><h4><?= e($ah['judul_absensi']) ?></h4><p>Kelas: <?= e($ah['nama_kelas']) ?></p></div>
                            <div style="text-align: right;"><span style="font-weight: 800; font-size: 16px; color: var(--sage-800);"><?= (int)$ah['total_hadir'] ?> / <?= (int)$ah['total_murid'] ?></span><p>Hadir</p></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="section-title">Akses Pintar</h3>
    <div class="quick-actions">
      <a href="guru.php?page=kelas" class="quick-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> Daftar Kelas & Nilai</a>
      <a href="guru.php?page=absensi" class="quick-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Buka Sesi Absensi</a>
      <a href="guru.php?page=tugas" class="quick-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Buat & Unggah Tugas</a>
      <a href="guru.php?page=profil" class="quick-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Pengaturan Profil</a>
    </div>

  <!-- ================= HALAMAN KELAS ================= -->
  <?php elseif ($page === 'kelas'): ?>
    <div class="card-manage">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div><h2>Manajemen Kelas & Kode Gabung</h2><p>Kelola kelas pengajaran Anda dan bagikan kode unik kepada murid.</p></div>
        <button type="submit" form="form-tambah-kelas" class="btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg> Buat Kelas Baru</button>
      </div>
      <form id="form-tambah-kelas" action="guru.php?page=kelas" method="POST">
        <input type="hidden" name="aksi" value="tambah_kelas">
        <input type="hidden" name="redirect_page" value="kelas">
        <input type="text" name="nama_kelas_baru" class="input-elegant" placeholder="Ketik nama kelas baru, contoh: XII Rekayasa Perangkat Lunak 1..." required>
      </form>
    </div>

    <h3 class="section-title">Daftar Kelas Aktif (<?= count($list_kelas) ?>)</h3>
    <?php if (empty($list_kelas)): ?>
      <div class="empty-state"><p>Belum ada kelas yang terdaftar.</p></div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_kelas as $kls): 
            $protokol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_url = $protokol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $link_gabung = $base_url . "/murid.php?kode=" . urlencode($kls['kode_gabung']);
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($link_gabung);
        ?>
        <div class="class-card">
          <div class="class-card-header">
            <div class="class-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="class-info">
              <h4><?= e($kls['nama_kelas']) ?></h4>
              <span class="murid-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <?= (int)$kls['jml_murid'] ?> Murid Tergabung</span>
            </div>
          </div>
          
          <!-- TAMPILAN CLEAN & MINIMALIS -->
          <div class="kode-badge">
            <div class="kode-text">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
              <span id="kode-<?= $kls['id_kelas'] ?>"><?= e($kls['kode_gabung']) ?></span>
            </div>
            <div class="kode-actions">
                <button type="button" class="btn-action-clean" onclick="salinKode('<?= e($kls['kode_gabung']) ?>', this)" title="Salin Kode">📋 Kode</button>
                <button type="button" class="btn-action-clean" onclick="salinKode('<?= e($link_gabung) ?>', this)" title="Salin Link">🔗 Link</button>
                <button type="button" class="btn-action-clean" style="background: var(--sage-800); color: #fff; border-color: var(--sage-800);" onclick="window.open('<?= $qr_url ?>', '_blank', 'width=400,height=400')" title="Lihat QR Code">📱 QR</button>
            </div>
          </div>

          <div class="btn-card-action">
            <a href="guru.php?page=detail_kelas&id=<?= (int)$kls['id_kelas'] ?>" class="btn-masuk-kelas">Masuk Kelas</a>
            <form method="POST" onsubmit="return confirm('Hapus kelas ini?')" style="display:inline;">
              <input type="hidden" name="aksi" value="hapus_kelas">
              <input type="hidden" name="redirect_page" value="kelas">
              <input type="hidden" name="kelas_id" value="<?= (int)$kls['id_kelas'] ?>">
              <button type="submit" class="btn-hapus">Hapus</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <script>
      function salinKode(kode, btn) {
          navigator.clipboard.writeText(kode).then(() => {
              let originalText = btn.innerHTML;
              btn.innerHTML = '✓';
              btn.style.background = '#2E7D32';
              btn.style.color = '#fff';
              btn.style.borderColor = '#2E7D32';
              setTimeout(() => {
                  btn.innerHTML = originalText;
                  if(originalText.includes('QR')) {
                      btn.style.background = 'var(--sage-800)';
                      btn.style.color = '#fff';
                      btn.style.borderColor = 'var(--sage-800)';
                  } else {
                      btn.style.background = '#FFFFFF';
                      btn.style.color = 'var(--sage-700)';
                      btn.style.borderColor = 'var(--sage-100)';
                  }
              }, 1500);
          }).catch(err => {
              alert('Gagal menyalin: ' + err);
          });
      }
      </script>
    <?php endif; ?>

  <!-- ================= HALAMAN DETAIL KELAS (SHORTCUT TUGAS) ================= -->
  <?php elseif ($page === 'detail_kelas'): ?>
    <?php
    $q_dk = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id = $detail_kelas_id AND guru_id = $user_id LIMIT 1");
    $kelas_ini = $q_dk ? mysqli_fetch_assoc($q_dk) : null;
    if (!$kelas_ini): ?>
      <div class="empty-state"><p>Kelas tidak ditemukan atau Anda tidak memiliki akses.</p></div>
    <?php else: 
        $protokol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $base_url = $protokol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $link_gabung = $base_url . "/murid.php?kode=" . urlencode($kelas_ini['kode_gabung']);
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($link_gabung);
    ?>
      <div class="card-manage">
        <a href="guru.php?page=kelas" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:13px; margin-bottom:12px;">← Kembali ke Daftar Kelas</a>
        <h2>Daftar Tugas Kelas: <?= e($kelas_ini['nama_kelas']) ?></h2>
        
        <div style="margin-top: 14px; background: #FAFCFA; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--sage-100); max-width: 480px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="font-size: 13.5px; font-weight: 700; color: var(--sage-800); font-family: monospace;">
                Kode: <span id="kode-detail-<?= $kelas_ini['id'] ?>"><?= e($kelas_ini['kode_gabung']) ?></span>
            </div>
            <div class="kode-actions">
                <button type="button" class="btn-action-clean" onclick="salinKode('<?= e($kelas_ini['kode_gabung']) ?>', this)">📋 Kode</button>
                <button type="button" class="btn-action-clean" onclick="salinKode('<?= e($link_gabung) ?>', this)">🔗 Link</button>
                <button type="button" class="btn-action-clean" style="background: var(--sage-800); color: #fff; border-color: var(--sage-800);" onclick="window.open('<?= $qr_url ?>', '_blank', 'width=400,height=400')">📱 QR</button>
            </div>
        </div>
      </div>

      <?php
      $q_tugas_kelas = mysqli_query($koneksi, "SELECT * FROM tugas WHERE kelas_id = $detail_kelas_id ORDER BY id DESC");
      if(mysqli_num_rows($q_tugas_kelas) === 0): ?>
          <div class="empty-state"><p>Belum ada tugas yang dibuat untuk kelas ini.</p></div>
      <?php else: ?>
          <div class="class-grid">
          <?php while($tugas = mysqli_fetch_assoc($q_tugas_kelas)): 
              $tugas_id = $tugas['id'];
              $is_expired = false; $teks_deadline = 'Tanpa Batas Waktu';
              if(!empty($tugas['deadline'])) {
                  $teks_deadline = date('d M Y, H:i', strtotime($tugas['deadline']));
                  if(strtotime($tugas['deadline']) < time()) { $is_expired = true; }
              }
              $tgl_publis = isset($tugas['created_at']) ? date('d M Y, H:i', strtotime($tugas['created_at'])) : '-';
          ?>
              <div class="class-card" style="display:flex; flex-direction:column;">
                  <h3 style="font-size: 17px; font-weight: 800; color: var(--sage-900); margin-bottom: 8px;">📄 <?= e($tugas['judul_tugas']) ?></h3>
                  <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 4px;">📅 Dipublis: <strong><?= $tgl_publis ?></strong></p>
                  <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 24px;">
                      ⏳ Deadline: <strong style="color: <?= $is_expired ? '#C62828' : 'var(--sage-800)' ?>;"><?= $teks_deadline ?></strong> 
                      <?= $is_expired ? '<span style="color: #C62828; font-weight: bold; margin-left: 6px;">(Terkunci)</span>' : '' ?>
                  </p>
                  <a href="guru.php?page=detail_tugas&id=<?= $tugas_id ?>" class="btn-primary" style="margin-top:auto; justify-content:center;">Buka Detail & Penilaian →</a>
              </div>
          <?php endwhile; ?>
          </div>
      <?php endif; ?>

      <script>
      function salinKode(kode, btn) {
          navigator.clipboard.writeText(kode).then(() => {
              let originalText = btn.innerHTML;
              btn.innerHTML = '✓';
              btn.style.background = '#2E7D32';
              btn.style.color = '#fff';
              btn.style.borderColor = '#2E7D32';
              setTimeout(() => { 
                  btn.innerHTML = originalText; 
                  if(originalText.includes('QR')) {
                      btn.style.background = 'var(--sage-800)'; btn.style.color = '#fff'; btn.style.borderColor = 'var(--sage-800)';
                  } else {
                      btn.style.background = '#FFFFFF'; btn.style.color = 'var(--sage-700)'; btn.style.borderColor = 'var(--sage-100)'; 
                  }
              }, 1500);
          }).catch(err => { alert('Gagal menyalin: ' + err); });
      }
      </script>
    <?php endif; ?>

  <!-- ================= HALAMAN DETAIL TUGAS ================= -->
  <?php elseif ($page === 'detail_tugas'): ?>
    <?php
    $tugas_id = (int)($_GET['id'] ?? 0);
    $q_tugas = mysqli_query($koneksi, "SELECT t.*, k.nama_kelas FROM tugas t JOIN kelas k ON t.kelas_id = k.id WHERE t.id = $tugas_id AND t.guru_id = $user_id LIMIT 1");
    $tugas = $q_tugas ? mysqli_fetch_assoc($q_tugas) : null;
    
    if (!$tugas): ?>
        <div class="empty-state"><p>Tugas tidak ditemukan atau Anda tidak memiliki akses.</p></div>
    <?php else: 
        $is_expired = false; $teks_deadline = 'Tanpa Batas Waktu';
        if(!empty($tugas['deadline'])) {
            $teks_deadline = date('d M Y, H:i', strtotime($tugas['deadline']));
            if(strtotime($tugas['deadline']) < time()) { $is_expired = true; }
        }
        $tgl_publis = isset($tugas['created_at']) ? date('d M Y, H:i', strtotime($tugas['created_at'])) : '-';
    ?>
        <div class="card-manage">
            <a href="guru.php?page=detail_kelas&id=<?= $tugas['kelas_id'] ?>" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:13px; margin-bottom:12px;">← Kembali ke Daftar Tugas Kelas</a>
            <h2>Tugas: <?= e($tugas['judul_tugas']) ?></h2>
            <p>Kelas: <strong><?= e($tugas['nama_kelas']) ?></strong> &nbsp;&bull;&nbsp; Dipublis: <strong><?= $tgl_publis ?></strong></p>
            <div style="margin-top: 16px; padding: 16px; background: var(--sage-body); border-radius: var(--radius-sm); border: 1px dashed var(--sage-300);">
                <strong style="display:block; font-size:13px; margin-bottom:6px;">Instruksi/Deskripsi Tugas:</strong>
                <p style="font-size:14px; color:var(--sage-800); white-space:pre-wrap; line-height: 1.6;"><?= e($tugas['deskripsi']) ?></p>
            </div>
        </div>

        <div class="task-section-box">
            <div class="task-section-header">
                <div>
                    <h3 style="font-size: 17px; font-weight: 800; color: var(--sage-900); margin-bottom: 4px;">Status Pengerjaan Murid</h3>
                    <p style="font-size: 13px; color: var(--sage-600);">
                        Deadline: <strong style="color: <?= $is_expired ? '#C62828' : 'var(--sage-800)' ?>;"><?= $teks_deadline ?></strong>
                    </p>
                </div>
                <form action="guru.php?page=detail_tugas&id=<?= $tugas_id ?>" method="POST" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="aksi" value="update_deadline">
                    <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
                    <input type="datetime-local" name="deadline" class="input-elegant" style="padding: 8px 12px; font-size: 12px; width: auto;" value="<?= !empty($tugas['deadline']) ? date('Y-m-d\TH:i', strtotime($tugas['deadline'])) : '' ?>">
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 12px; background: var(--sage-800); box-shadow: none;">Ubah Deadline</button>
                </form>
            </div>

            <ul class="student-row-list">
                <?php
                $q_status_murid = mysqli_query($koneksi, "
                    SELECT l.id AS user_id, l.nama AS nama_murid, 
                           pt.id AS pengumpulan_id, pt.file_path, pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback 
                    FROM anggota_kelas ak JOIN login l ON ak.user_id = l.id LEFT JOIN pengumpulan_tugas pt ON l.id = pt.user_id AND pt.tugas_id = $tugas_id 
                    WHERE ak.kelas_id = ".$tugas['kelas_id']." ORDER BY l.nama ASC
                ");

                if(mysqli_num_rows($q_status_murid) === 0):
                ?>
                    <li class="student-row-item" style="justify-content: center; color: var(--sage-600);">Belum ada murid.</li>
                <?php 
                else:
                    while($sm = mysqli_fetch_assoc($q_status_murid)):
                        $sudah_mengerjakan = !empty($sm['pengumpulan_id']);
                ?>
                    <li class="student-row-item">
                        <div style="flex: 1; min-width: 240px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <strong style="font-size: 14.5px; color: var(--sage-900);"><?= e($sm['nama_murid']) ?></strong>
                                <?php if($sudah_mengerjakan): ?>
                                    <span class="badge-status badge-hijau"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Sudah Mengumpulkan</span>
                                <?php else: ?>
                                    <span class="badge-status badge-merah"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Belum Mengumpulkan</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($sudah_mengerjakan): 
                                $file_path = $sm['file_path'] ?? ''; $nama_file = $sm['nama_file'] ?? ''; $link_file = '';
                                if (!empty($file_path)) { $link_file = (strpos($file_path, 'uploads/') === false) ? 'uploads/' . ltrim($file_path, '/') : $file_path; } 
                                elseif (!empty($nama_file)) { $link_file = 'uploads/' . $nama_file; }

                                $is_office = false; $link_online = '';
                                if(!empty($link_file)) {
                                    $ext = strtolower(pathinfo($link_file, PATHINFO_EXTENSION));
                                    if(in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx'])) {
                                        $is_office = true;
                                        $protokol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                        $base_d = dirname($_SERVER['PHP_SELF']);
                                        $base_d = ($base_d === '/' || $base_d === '\\') ? '' : $base_d;
                                        $url_penuh = $protokol . "://" . $_SERVER['HTTP_HOST'] . $base_d . "/" . $link_file;
                                        $link_online = "https://docs.google.com/viewer?url=" . urlencode($url_penuh);
                                    }
                                }
                            ?>
                                <div style="display: flex; align-items: center; gap: 12px; margin-top: 4px; flex-wrap: wrap;">
                                    <?php if(!empty($link_file)): ?>
                                        <?php if($is_office): ?>
                                            <a href="<?= e($link_file) ?>" download style="font-size: 12px; color: var(--sage-600); text-decoration: underline; font-weight: 600;">📥 Download</a>
                                            <a href="<?= e($link_online) ?>" target="_blank" style="font-size: 12px; color: #2563eb; text-decoration: underline; font-weight: 600;" title="Hanya bisa dibuka jika web sudah Online/Hosting">👁️ Lihat Online</a>
                                        <?php else: ?>
                                            <a href="<?= e($link_file) ?>" target="_blank" style="font-size: 12px; color: var(--sage-600); text-decoration: underline; font-weight: 600;">Lihat Lampiran</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <span style="font-size: 11px; color: #9ca3af;">Dikumpul: <?= e($sm['dikumpulkan_at']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($sudah_mengerjakan): ?>
                            <form action="guru.php?page=detail_tugas&id=<?= $tugas_id ?>" method="POST" style="display: flex; gap: 8px; align-items: center; background: #FAFCFA; padding: 12px; border-radius: 12px; border: 1px solid var(--sage-100);">
                                <input type="hidden" name="aksi" value="beri_nilai">
                                <input type="hidden" name="pengumpulan_id" value="<?= (int)$sm['pengumpulan_id'] ?>">
                                <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
                                <div><label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Nilai</label><input type="number" name="nilai" value="<?= $sm['nilai'] ?>" placeholder="0-100" class="input-elegant" style="width: 75px; padding: 6px 10px; background: #fff;" required></div>
                                <div><label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Feedback</label><input type="text" name="feedback" value="<?= e($sm['feedback'] ?? '') ?>" placeholder="Catatan..." class="input-elegant" style="width: 130px; padding: 6px 10px; background: #fff;"></div>
                                <button type="submit" class="btn-primary" style="padding: 8px 14px; font-size: 12px; margin-top: 15px; box-shadow: none;">Simpan</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endwhile; endif; ?>
            </ul>
        </div>
    <?php endif; ?>

  <!-- ================= HALAMAN ABSENSI ================= -->
  <?php elseif ($page === 'absensi'): ?>
    <div class="card-manage">
      <h2>Buka Sesi Absensi</h2>
      <p>Catat kehadiran siswa berdasarkan kelas dan tanggal pertemuan hari ini atau mendatang.</p>
      <form action="guru.php?page=absensi" method="POST" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="buat_absen">
        <div class="form-grid">
          <div><label class="form-label">Judul Sesi Absensi</label><input type="text" name="judul_absensi" class="input-elegant" placeholder="Contoh: Pertemuan 1 - Jaringan Dasar" required></div>
          <div>
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required style="cursor:pointer;">
              <option value="" disabled selected>-- Tentukan Kelas --</option>
              <?php foreach ($list_kelas as $kls): ?>
                <option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-bottom: 24px;">
          <label class="form-label">Tanggal Pelaksanaan</label>
          <input type="date" name="tanggal" class="input-elegant" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required style="max-width: 300px;">
        </div>
        <button type="submit" class="btn-primary">+ Buka Sesi Absensi</button>
      </form>
    </div>

    <h3 class="section-title">Riwayat Sesi Absensi Aktif</h3>
    <?php
    $list_absen_sesi = [];
    $qa = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, a.kelas_id, k.nama_kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid, (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id AND ah.status='hadir') AS total_hadir FROM absensi a JOIN kelas k ON k.id=a.kelas_id WHERE a.guru_id=$user_id ORDER BY a.id DESC");
    if($qa) { while ($ra = mysqli_fetch_assoc($qa)) $list_absen_sesi[] = $ra; }
    ?>
    <?php if(empty($list_absen_sesi)): ?>
      <div class="empty-state"><p>Belum ada sesi absensi yang dibuka.</p></div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_absen_sesi as $ab): ?>
        <div class="class-card">
          <h4 style="font-size:16px; font-weight:800; color:var(--sage-800); margin-bottom:6px;"><?= e($ab['judul_absensi']) ?></h4>
          <p style="font-size:13.5px; color:var(--sage-600); font-weight:600; margin-bottom:4px;">📍 Kelas: <?= e($ab['nama_kelas']) ?></p>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:20px;">📅 Tanggal: <?= e($ab['tanggal']) ?> &nbsp;|&nbsp; 🙋‍♂️ Hadir: <strong><?= (int)$ab['total_hadir'] ?>/<?= (int)$ab['total_murid'] ?></strong></p>
          
          <div style="display:flex; gap:10px; margin-top:auto;">
              <a href="guru.php?page=detail_absensi&id=<?= $ab['id'] ?>" class="btn-outline" style="flex:1; justify-content:center; font-size:12px; padding: 10px;">Lihat Detail</a>
              <form method="POST" onsubmit="return confirm('Hapus sesi absensi ini?')" style="display:flex;">
                <input type="hidden" name="aksi" value="hapus_absen">
                <input type="hidden" name="absen_id" value="<?= (int)$ab['id'] ?>">
                <button type="submit" class="btn-hapus" style="padding:10px 14px;" title="Hapus Sesi"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
              </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- ================= HALAMAN DETAIL ABSENSI ================= -->
  <?php elseif ($page === 'detail_absensi'): ?>
    <?php
    $absen_id = (int)($_GET['id'] ?? 0);
    $q_absen = mysqli_query($koneksi, "SELECT a.*, k.nama_kelas FROM absensi a JOIN kelas k ON a.kelas_id = k.id WHERE a.id = $absen_id AND a.guru_id = $user_id LIMIT 1");
    $absen_info = $q_absen ? mysqli_fetch_assoc($q_absen) : null;
    if (!$absen_info): ?>
        <div class="empty-state"><p>Sesi absensi tidak ditemukan atau Anda tidak memiliki akses.</p></div>
    <?php else: ?>
        <div class="card-manage">
            <a href="guru.php?page=absensi" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:13px; margin-bottom:12px;">← Kembali ke Daftar Absensi</a>
            <h2>Detail Kehadiran: <?= e($absen_info['judul_absensi']) ?></h2>
            <p>Kelas: <strong><?= e($absen_info['nama_kelas']) ?></strong> &nbsp;&bull;&nbsp; Tanggal Pelaksanaan: <strong><?= e($absen_info['tanggal']) ?></strong></p>
        </div>

        <div class="task-section-box">
            <div class="task-section-header">
                <h3 style="font-size: 17px; font-weight: 800; color: var(--sage-900);">Daftar Murid & Status Absen</h3>
            </div>

            <ul class="student-row-list">
                <?php
                $q_mrd = mysqli_query($koneksi, "
                    SELECT l.nama, ah.status 
                    FROM anggota_kelas ak 
                    JOIN login l ON ak.user_id = l.id 
                    LEFT JOIN absensi_hadir ah ON ah.absensi_id = ".$absen_id." AND ah.user_id = l.id 
                    WHERE ak.kelas_id = ".$absen_info['kelas_id']." 
                    ORDER BY l.nama ASC
                ");
                if(mysqli_num_rows($q_mrd) === 0): ?>
                    <li class="student-row-item" style="justify-content:center; color:var(--sage-600);">Belum ada murid yang bergabung di kelas ini.</li>
                <?php else:
                    while($mrd = mysqli_fetch_assoc($q_mrd)):
                        $stat = $mrd['status'] ? ucfirst($mrd['status']) : 'Belum Absen (Alpha)';
                        $badge_class = '';
                        if(strtolower($stat) == 'hadir') { $badge_class = 'badge-hijau'; }
                        elseif(strtolower($stat) == 'izin' || strtolower($stat) == 'sakit') { $badge_class = 'badge-kuning'; }
                        else { $badge_class = 'badge-merah'; }
                ?>
                    <li class="student-row-item">
                        <strong style="font-size: 14.5px; color: var(--sage-900);"><?= e($mrd['nama']) ?></strong>
                        <span class="badge-status <?= $badge_class ?>"><?= $stat ?></span>
                    </li>
                <?php endwhile; endif; ?>
            </ul>
        </div>
    <?php endif; ?>

  <!-- ================= HALAMAN TUGAS ================= -->
  <?php elseif ($page === 'tugas'): ?>
    <div class="card-manage">
      <h2>Buat & Publikasikan Tugas</h2>
      <p>Unggah materi atau berikan instruksi tugas baru kepada siswa.</p>
      <form action="guru.php?page=tugas" method="POST" enctype="multipart/form-data" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="tambah_tugas">
        <div class="form-grid">
          <div><label class="form-label">Judul Tugas</label><input type="text" name="judul_tugas" class="input-elegant" required placeholder="Contoh: Praktik Algoritma"></div>
          <div>
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required style="cursor:pointer;">
              <option value="" disabled selected>-- Tentukan Kelas --</option>
              <?php foreach ($list_kelas as $kls): ?>
                <option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-grid">
          <div><label class="form-label">Batas Waktu (Deadline - Ke Depan)</label><input type="datetime-local" name="deadline" class="input-elegant" min="<?= date('Y-m-d\TH:i') ?>"></div>
          <div><label class="form-label">Lampiran Berkas (Opsional)</label><input type="file" name="file_tugas" class="input-elegant" style="padding: 12px; background:#fff;"></div>
        </div>
        <div style="margin-bottom: 24px;">
          <label class="form-label">Instruksi Pengerjaan</label>
          <textarea name="deskripsi" class="input-elegant" style="min-height: 120px; resize: vertical;" placeholder="Tulis instruksi..."></textarea>
        </div>
        <button type="submit" class="btn-primary">Publikasikan Tugas Baru</button>
      </form>
    </div>

    <h3 class="section-title">Daftar Tugas Terkirim</h3>
    <?php 
    $list_tugas = [];
    $q = mysqli_query($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, k.nama_kelas AS kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=t.kelas_id) AS jml_target, (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id) AS jml_kumpul FROM tugas t JOIN kelas k ON t.kelas_id=k.id WHERE t.guru_id=$user_id ORDER BY t.id DESC");
    if($q) { while ($r = mysqli_fetch_assoc($q)) $list_tugas[] = $r; }
    ?>
    <?php if (empty($list_tugas)): ?>
      <div class="empty-state"><p>Belum ada tugas yang dipublikasikan.</p></div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_tugas as $tg): ?>
        <div class="class-card">
          <h4 style="font-size:16px; font-weight:800; color:var(--sage-800); margin-bottom:6px;"><?= e($tg['judul']) ?></h4>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:4px;">📍 Kelas: <?= e($tg['kelas']) ?></p>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:20px;">⏳ Deadline: <?= e($tg['deadline'] ?: 'Tanpa Batas') ?> &nbsp;|&nbsp; 📥 Kumpul: <strong><?= (int)$tg['jml_kumpul'] ?>/<?= (int)$tg['jml_target'] ?></strong></p>
          <form method="POST" onsubmit="return confirm('Hapus tugas ini?')" style="margin-top:auto;">
            <input type="hidden" name="aksi" value="hapus_tugas">
            <input type="hidden" name="tugas_id" value="<?= (int)$tg['id'] ?>">
            <button type="submit" class="btn-hapus" style="width:100%;">Hapus Tugas</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <!-- ================= HALAMAN PROFIL ================= -->
  <?php elseif ($page === 'profil'): ?>
    <div class="card-manage">
      <h2>Pengaturan Profil & Keamanan Akun</h2>
      <p>Perbarui informasi identitas akun pengajar, unggah foto profil, serta ubah kata sandi.</p>
      
      <form action="guru.php?page=profil" method="POST" enctype="multipart/form-data" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="update_profil">

        <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 24px; padding: 20px; background: var(--sage-body); border-radius: var(--radius-sm); border: 1px dashed var(--sage-300);">
            <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: #fff; border: 2px solid var(--sage-100); flex-shrink: 0; display:flex; align-items:center; justify-content:center;">
                <?php if(!empty($foto_profil)): ?>
                    <img src="uploads/<?= e($foto_profil) ?>" alt="Profil" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px; height:40px; color:var(--sage-300);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php endif; ?>
            </div>
            <div style="flex: 1;">
                <label class="form-label" style="margin-bottom: 4px;">Ubah Foto Profil</label>
                <input type="file" name="foto_profil" accept="image/png, image/jpeg, image/jpg, image/gif" class="input-elegant" style="padding: 10px; background: #fff;">
                <p style="font-size: 12px; color: var(--sage-600); margin-top: 6px;">Format yang didukung: JPG, JPEG, PNG, GIF. (Opsional)</p>
            </div>
        </div>

        <div class="form-grid">
          <div><label class="form-label">Nama Lengkap</label><input type="text" name="nama" class="input-elegant" value="<?= e($nama_guru) ?>" required></div>
          <div><label class="form-label">Alamat Email</label><input type="email" name="email" class="input-elegant" value="<?= e($email_guru) ?>" required></div>
        </div>
        <div class="form-grid">
          <div><label class="form-label">Password Lama</label><input type="password" name="password_lama" class="input-elegant" placeholder="Isi hanya jika ingin mengubah password..."></div>
          <div><label class="form-label">Password Baru</label><input type="password" name="password_baru" class="input-elegant" placeholder="Isi hanya jika ingin mengubah password..."></div>
        </div>
        <button type="submit" class="btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Simpan Perubahan Profil</button>
      </form>
    </div>

  <!-- ================= HALAMAN KUIS UTAMA ================= -->
  <?php elseif ($page === 'quiz'): ?>
    <div class="card-manage">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
          <div>
              <h2>Modul Live Quiz Pengajar</h2>
              <p>Buat kuis interaktif yang akan dikerjakan siswa secara *real-time* di dalam kelas.</p>
          </div>
      </div>
      <hr style="border:0; border-top:1px solid var(--sage-100); margin: 0 0 24px 0;">
      
      <h3 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 16px;">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> Buat Kuis Baru
      </h3>
      
      <form action="guru.php?page=quiz" method="POST">
        <input type="hidden" name="aksi" value="buat_kuis">
        <div class="form-grid">
            <div>
                <label class="form-label">Judul Kuis</label>
                <input type="text" name="judul_kuis" class="input-elegant" required placeholder="Contoh: Kuis Sistem Basis Data">
            </div>
            <div>
                <label class="form-label">Pilih Kelas Sasaran</label>
                <select name="kelas_id" class="input-elegant" required>
                    <option value="" disabled selected>-- Tentukan Kelas --</option>
                    <?php foreach ($list_kelas as $kls): ?>
                        <option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom: 24px; padding: 16px; background: #FFF8D6; border: 1px dashed #E2D38A; border-radius: 8px; font-size: 13.5px; color: var(--sage-900); display: flex; gap: 12px; align-items: flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#E07A5F" stroke-width="2" width="24" height="24" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
                <strong style="display:block; margin-bottom:4px; font-size:14px; color:var(--sage-800);">Sistem Waktu Baru Diterapkan (Live Mode)</strong>
                Durasi keseluruhan telah ditiadakan. Kuis akan menggunakan <strong>timer per soal</strong> di layar murid:<br>
                ⏳ <strong>3 Detik</strong> awal untuk membaca soal (tombol terkunci).<br>
                ✅ <strong>5 Detik</strong> untuk memilih jawaban, lalu otomatis lanjut ke soal berikutnya.
            </div>
        </div>

        <button type="submit" class="btn-primary">Selanjutnya: Buat Soal Kuis →</button>
      </form>
    </div>

    <h3 class="section-title">Riwayat & Daftar Kuis Anda</h3>
    <?php 
      $list_kuis = [];
      $qk = mysqli_query($koneksi, "SELECT q.id, q.judul, k.nama_kelas AS kelas, q.durasi_menit, (SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id = q.id) AS total_soal, q.status FROM quiz q JOIN kelas k ON q.kelas_id = k.id WHERE q.guru_id = $user_id ORDER BY q.id DESC");
      if($qk) { while ($rk = mysqli_fetch_assoc($qk)) { $list_kuis[] = $rk; } }
    ?>
    <?php if (empty($list_kuis)): ?>
        <div class="empty-state"><p>Belum ada kuis yang dibuat.</p></div>
    <?php else: ?>
        <div class="class-grid">
            <?php foreach ($list_kuis as $quiz): 
                $status_k = $quiz['status'] ?? 'menunggu';
            ?>
            <div class="class-card">
                <h4 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px;"><?= e($quiz['judul']) ?></h4>
                <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 4px;">📍 Kelas: <strong><?= e($quiz['kelas']) ?></strong></p>
                <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 20px;">
                   ⏱️ Waktu: 8 Detik / Soal | 📝 Soal: <strong><?= (int)$quiz['total_soal'] ?></strong>
                   <br><br>
                   <span class="badge-status <?= $status_k === 'menunggu' ? 'badge-merah' : 'badge-hijau' ?>">Status: <?= strtoupper($status_k) ?></span>
                </p>

                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto;">
                    <a href="guru.php?page=kelola_soal&quiz_id=<?= $quiz['id'] ?>" class="btn-primary" style="justify-content: center; font-size: 12px; padding: 10px; background: var(--sage-500); width: 100%;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kelola Pertanyaan
                    </a>
                    <a href="guru.php?page=live_monitor&quiz_id=<?= $quiz['id'] ?>" class="btn-primary" style="justify-content: center; font-size: 12px; padding: 10px; background: #E07A5F; margin-top: 4px; border: 1px solid #D66D52;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Lihat Peserta & Mulai Live
                    </a>
                    <form method="POST" onsubmit="return confirm('Yakin menghapus kuis ini dari riwayat?')" style="margin-top: 6px;">
                        <input type="hidden" name="aksi" value="hapus_kuis">
                        <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
                        <button type="submit" class="btn-hapus" style="width: 100%;">Hapus Kuis</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

  <!-- ================= HALAMAN BUILDER: KELOLA SOAL ================= -->
  <?php elseif ($page === 'kelola_soal' && $quiz_id_get > 0): ?>
    <?php
    $q_info = mysqli_query($koneksi, "SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON q.kelas_id=k.id WHERE q.id=$quiz_id_get AND q.guru_id=$user_id LIMIT 1");
    $quiz_info = $q_info ? mysqli_fetch_assoc($q_info) : null;

    if(!$quiz_info): ?>
        <div class="empty-state"><p>Data kuis tidak ditemukan atau Anda tidak berhak mengaksesnya.</p></div>
    <?php else: 
        $list_soal = [];
        $qs = mysqli_query($koneksi, "SELECT * FROM quiz_soal WHERE quiz_id=$quiz_id_get ORDER BY id ASC");
        if($qs) { while($row = mysqli_fetch_assoc($qs)) { $list_soal[] = $row; } }
        $total_soal = count($list_soal);
    ?>
    
    <div style="margin-bottom: 24px;">
        <a href="guru.php?page=quiz" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:14px; margin-bottom:12px;">← Kembali ke Dashboard Kuis</a>
        <h2 style="font-size: 24px; font-weight: 800; color: var(--sage-800);">Tulis Pertanyaan: <?= e($quiz_info['judul']) ?></h2>
    </div>

    <div class="quiz-builder-container">
        <div class="builder-main">
            <form action="guru.php?page=kelola_soal&quiz_id=<?= $quiz_id_get ?>" method="POST" class="question-block">
                <input type="hidden" name="aksi" value="tambah_soal">
                <input type="hidden" name="quiz_id" value="<?= $quiz_id_get ?>">
                
                <div class="question-block-header">
                    <span>+</span> Buat Pertanyaan Baru
                </div>

                <div style="margin-bottom: 24px;">
                    <label class="form-label" style="color: #E07A5F;">Pertanyaan *</label>
                    <textarea name="pertanyaan" class="input-elegant" style="min-height: 120px; resize: vertical;" placeholder="Tuliskan pertanyaan kuis di sini..." required></textarea>
                </div>

                <label class="form-label" style="margin-bottom: 12px;">Pilihan Jawaban (Pilih *Radio Button* untuk menandai jawaban benar)</label>
                
                <div class="option-row">
                    <input type="radio" name="kunci" value="A" required title="Tandai sebagai jawaban benar">
                    <span class="opt-label">A</span>
                    <input type="text" name="opsi_a" placeholder="Masukkan jawaban A..." required>
                </div>
                <div class="option-row">
                    <input type="radio" name="kunci" value="B" title="Tandai sebagai jawaban benar">
                    <span class="opt-label">B</span>
                    <input type="text" name="opsi_b" placeholder="Masukkan jawaban B..." required>
                </div>
                <div class="option-row">
                    <input type="radio" name="kunci" value="C" title="Tandai sebagai jawaban benar">
                    <span class="opt-label">C</span>
                    <input type="text" name="opsi_c" placeholder="Masukkan jawaban C..." required>
                </div>
                <div class="option-row">
                    <input type="radio" name="kunci" value="D" title="Tandai sebagai jawaban benar">
                    <span class="opt-label">D</span>
                    <input type="text" name="opsi_d" placeholder="Masukkan jawaban D..." required>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 16px; padding: 14px;">+ Simpan Pertanyaan ke Kuis</button>
            </form>

            <?php if($total_soal > 0): ?>
                <h3 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-top: 16px; margin-bottom: 16px;">Daftar Pertanyaan Tersimpan (<?= $total_soal ?>)</h3>
                <?php foreach($list_soal as $index => $soal): ?>
                    <div class="existing-question">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h5><?= ($index+1) ?>. <?= nl2br(e($soal['pertanyaan'])) ?></h5>
                            <form method="POST" onsubmit="return confirm('Hapus pertanyaan ini?')" style="margin-left: 16px;">
                                <input type="hidden" name="aksi" value="hapus_soal">
                                <input type="hidden" name="quiz_id" value="<?= $quiz_id_get ?>">
                                <input type="hidden" name="soal_id" value="<?= $soal['id'] ?>">
                                <button type="submit" style="background:none; border:none; color:#E07A5F; cursor:pointer;" title="Hapus Soal">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                        <div style="margin-top: 12px;">
                            <div class="existing-option <?= $soal['kunci']==='A'?'correct':'' ?>"><?= $soal['kunci']==='A'?'✅':'⚪' ?> A. <?= e($soal['opsi_a']) ?></div>
                            <div class="existing-option <?= $soal['kunci']==='B'?'correct':'' ?>"><?= $soal['kunci']==='B'?'✅':'⚪' ?> B. <?= e($soal['opsi_b']) ?></div>
                            <div class="existing-option <?= $soal['kunci']==='C'?'correct':'' ?>"><?= $soal['kunci']==='C'?'✅':'⚪' ?> C. <?= e($soal['opsi_c']) ?></div>
                            <div class="existing-option <?= $soal['kunci']==='D'?'correct':'' ?>"><?= $soal['kunci']==='D'?'✅':'⚪' ?> D. <?= e($soal['opsi_d']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="builder-sidebar">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 20px;">Pengaturan Kuis</h3>
            
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                <div><p>Mata Pelajaran (Kelas)</p><h4><?= e($quiz_info['nama_kelas']) ?></h4></div>
            </div>
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <div><p>Sistem Waktu</p><h4>8 Detik / Soal (Live)</h4></div>
            </div>
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <div><p>Total Pertanyaan</p><h4><?= $total_soal ?> Pertanyaan</h4></div>
            </div>

            <a href="guru.php?page=quiz" class="btn-outline" style="width: 100%; justify-content: center; margin-top: 32px;">Selesai & Tutup</a>
        </div>
    </div>
    <?php endif; ?>

  <!-- ================= HALAMAN LOBI & LIVE MONITOR ================= -->
  <?php elseif ($page === 'live_monitor' && $quiz_id_get > 0): ?>
    <?php
    $q_info = mysqli_query($koneksi, "SELECT q.*, k.nama_kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=q.kelas_id) as total_murid_kelas FROM quiz q JOIN kelas k ON q.kelas_id=k.id WHERE q.id=$quiz_id_get AND q.guru_id=$user_id LIMIT 1");
    $quiz_info = $q_info ? mysqli_fetch_assoc($q_info) : null;
    
    if(!$quiz_info): ?>
        <div class="empty-state"><p>Data kuis tidak ditemukan atau Anda tidak berhak mengaksesnya.</p></div>
    <?php else: 
        $status_saat_ini = @$quiz_info['status'] ?: 'menunggu'; 
    ?>
        <div style="margin-bottom: 24px; display:flex; justify-content: space-between; align-items:center;">
            <div>
                <a href="guru.php?page=quiz" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:14px; margin-bottom:12px;">← Kembali ke Dashboard Kuis</a>
                <h2 style="font-size: 24px; font-weight: 800; color: var(--sage-800);">Live Monitor: <?= e($quiz_info['judul']) ?></h2>
            </div>
            <div>
                <span class="badge-status <?= $status_saat_ini === 'mulai' ? 'badge-hijau' : 'badge-merah' ?>" style="font-size: 14px; padding: 8px 16px;">Status: <?= strtoupper($status_saat_ini) ?></span>
            </div>
        </div>

        <?php if ($status_saat_ini === 'menunggu'): ?>
            <div class="card-manage" style="text-align: center; padding: 40px 20px;">
                <svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="var(--sage-500)" stroke-width="2" style="margin-bottom: 20px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <h2 style="font-size: 28px;">Menunggu Siswa...</h2>
                <p style="font-size: 16px; margin-bottom: 30px;">Kuis ini untuk <strong><?= e($quiz_info['nama_kelas']) ?></strong>. Total Siswa di kelas: <strong><?= (int)$quiz_info['total_murid_kelas'] ?> Siswa</strong>.</p>
                
                <div style="background:var(--sage-50); border: 2px solid var(--sage-100); padding:30px; text-align:center; border-radius:var(--radius-lg); margin: 0 auto 30px auto; max-width: 400px; box-shadow: 0 4px 12px rgba(109,140,109,0.1);">
                    <p style="font-size:14px; font-weight:700; color:var(--sage-600); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Live Lobi Kuis</p>
                    <h3 style="font-size:56px; color:var(--sage-800); font-weight:900; line-height:1; margin-bottom:8px; transition: transform 0.2s;" id="angka-menunggu">0</h3>
                    <p style="font-weight:700; color:var(--sage-800); font-size:16px;">Murid Siap Mengerjakan</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="aksi" value="ubah_status_kuis">
                    <input type="hidden" name="quiz_id" value="<?= $quiz_id_get ?>">
                    <input type="hidden" name="status_baru" value="mulai">
                    <button type="submit" class="btn-primary" style="font-size: 16px; padding: 16px 32px; background: #E07A5F;">▶️ Mulai Kuis Serentak Sekarang!</button>
                </form>

                <script>
                const quizId = <?= $quiz_id_get ?>; 
                setInterval(() => {
                    fetch('guru.php?page=api_cek_lobby&quiz_id=' + quizId)
                    .then(res => res.json())
                    .then(data => {
                        const el = document.getElementById('angka-menunggu');
                        if (el && el.innerText != data.jumlah) {
                            el.innerText = data.jumlah;
                            el.style.transform = 'scale(1.1)';
                            setTimeout(() => { el.style.transform = 'scale(1)'; }, 200);
                        }
                    })
                    .catch(err => console.error("Gagal menarik data lobi", err));
                }, 2500); 
                </script>
            </div>
        <?php else: ?>
            <div class="card-manage">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--sage-800);">Papan Peringkat Real-Time</h3>
                    <?php if($status_saat_ini === 'mulai'): ?>
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengakhiri kuis ini sekarang?')">
                        <input type="hidden" name="aksi" value="ubah_status_kuis">
                        <input type="hidden" name="quiz_id" value="<?= $quiz_id_get ?>">
                        <input type="hidden" name="status_baru" value="selesai">
                        <button type="submit" class="btn-primary" style="background: var(--sage-800); box-shadow: none;">⏹️ Akhiri Kuis</button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <?php if($status_saat_ini === 'selesai'): ?>
                    <div class="alert success" style="margin-top: 16px;">Kuis telah diakhiri. Berikut adalah hasil akhir para murid.</div>
                <?php else: ?>
                    <p style="font-size: 13px; color: var(--sage-600); margin-top: 6px;">Halaman ini akan diperbarui otomatis setiap 5 detik.</p>
                <?php endif; ?>

                <?php 
                $q_leaderboard = mysqli_query($koneksi, "
                    SELECT l.nama, qh.benar, qh.total, qh.skor 
                    FROM quiz_hasil qh 
                    JOIN login l ON qh.user_id = l.id 
                    WHERE qh.quiz_id = $quiz_id_get 
                    ORDER BY qh.skor DESC, qh.dikerjakan_at ASC
                ");
                ?>
                
                <table class="table-leaderboard">
                    <thead>
                        <tr>
                            <th width="80">Peringkat</th>
                            <th>Nama Murid</th>
                            <th style="text-align:center;">Jumlah Benar</th>
                            <th style="text-align:center;">Jumlah Salah</th>
                            <th style="text-align:center;">Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($q_leaderboard) === 0): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 30px;">Belum ada murid yang mulai mengerjakan (atau selesai).</td></tr>
                        <?php else: $rank = 1; while ($row = mysqli_fetch_assoc($q_leaderboard)): 
                            $jml_salah = (int)$row['total'] - (int)$row['benar'];
                            $rank_class = '';
                            if($rank == 1) $rank_class = 'rank-1'; else if($rank == 2) $rank_class = 'rank-2'; else if($rank == 3) $rank_class = 'rank-3';
                        ?>
                            <tr>
                                <td style="text-align:center;"><span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span></td>
                                <td><?= e($row['nama']) ?></td>
                                <td style="text-align:center; color: #2E7D32; font-weight:800;"><?= (int)$row['benar'] ?></td>
                                <td style="text-align:center; color: #C62828; font-weight:800;"><?= $jml_salah ?></td>
                                <td style="text-align:center; font-size:16px; font-weight:800; color:var(--sage-800);"><?= (int)$row['skor'] ?></td>
                            </tr>
                        <?php $rank++; endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

  <?php endif; ?>

</main>

<!-- ================= AREA SCRIPT NOTIFIKASI REAL-TIME & DROPDOWN ================= -->
<audio id="audioNotif" src="assets/audio/ting.mp3" preload="auto"></audio>

<div id="toastNotif" style="position:fixed; bottom:-100px; right:32px; background:#fff; border-left:4px solid var(--sage-500); padding:16px 24px; box-shadow:var(--shadow-lg); border-radius:var(--radius-sm); z-index:9999; transition:all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); display:flex; align-items:center; gap:16px;">
    <div style="background:var(--sage-50); padding:10px; border-radius:50%; display:flex; color:var(--sage-600);">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <div>
        <strong style="display:block; font-size:15px; color:var(--sage-900); margin-bottom:2px;">Tugas Baru Masuk!</strong>
        <span style="font-size:13px; color:var(--sage-600);">Ada tugas yang baru dikumpulkan. Refresh atau cek notifikasi.</span>
    </div>
</div>

<script>
let lastNotifCount = <?= count($notif_tugas_belum_dinilai ?? []) ?>; 

setInterval(() => {
    fetch('guru.php?page=api_cek_notif')
    .then(res => res.json())
    .then(data => {
        if (data.jumlah_notif > lastNotifCount) {
            const audio = document.getElementById('audioNotif');
            audio.play().catch(e => console.log('Autoplay audio dicegah oleh browser.'));
            
            const toast = document.getElementById('toastNotif');
            toast.style.bottom = '32px';
            
            setTimeout(() => { toast.style.bottom = '-100px'; }, 6000);
            
            let bell = document.querySelector('.header-icons a[title="Notifikasi"]');
            if(bell && !bell.querySelector('.notif-dot')) {
                bell.innerHTML += '<div class="notif-dot"></div>';
            }
            
            lastNotifCount = data.jumlah_notif; 
        }
    })
    .catch(err => console.error("Gagal cek notifikasi:", err));
}, 5000);

function toggleNotif(e) {
    e.preventDefault();
    document.getElementById('dropdown-notif').classList.toggle('show');
}

window.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notif-wrapper');
    const dropdown = document.getElementById('dropdown-notif');
    if (wrapper && !wrapper.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});
</script>
</body>
</html>
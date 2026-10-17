<?php
// SLearning — Dashboard Guru (FULL VERSION - Tidak ada yang di-cut)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['jenis'] ?? '') === 'murid') { header("Location: login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

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

    // Update Profil
    if ($aksi === 'update_profil') {
        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');
        $pass_lama = $_POST['password_lama'] ?? '';
        $pass_baru = $_POST['password_baru'] ?? '';

        if ($nama_baru !== '' && $email_baru !== '') {
            $q_cek = mysqli_query($koneksi, "SELECT password FROM login WHERE id = $user_id LIMIT 1");
            $res_cek = $q_cek ? mysqli_fetch_assoc($q_cek) : null;
            $update_pass_sql = ""; $params = [$nama_baru, $email_baru]; $types = "ss";

            if (!empty($pass_baru)) {
                if ($res_cek && password_verify($pass_lama, $res_cek['password'])) {
                    $update_pass_sql = ", password = ?";
                    $params[] = password_hash($pass_baru, PASSWORD_DEFAULT); $types .= "s";
                } else {
                    flash_set('Password lama salah!', 'error'); header("Location: guru.php?page=profil"); exit;
                }
            }
            $params[] = $user_id; $types .= "i";
            $upd = mysqli_prepare($koneksi, "UPDATE login SET nama = ?, email = ? ".$update_pass_sql." WHERE id = ?");
            mysqli_stmt_bind_param($upd, $types, ...$params);
            if (mysqli_stmt_execute($upd)) { $_SESSION['nama'] = $nama_baru; flash_set('Profil diperbarui.'); }
            mysqli_stmt_close($upd);
        }
        header("Location: guru.php?page=profil"); exit;
    }

    // Aksi Kelas
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

    // Aksi Absensi
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
        mysqli_query($koneksi, "DELETE FROM absensi WHERE id=$id AND guru_id=$user_id");
        flash_set('Absensi dihapus.'); header("Location: guru.php?page=absensi"); exit;
    }

    // Aksi Tugas
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
        header("Location: guru.php?page=detail_kelas&id=".$detail_kelas_id); exit;
    }
    if ($aksi === 'beri_nilai') {
        $pengumpulan_id = (int)($_POST['pengumpulan_id'] ?? 0); $nilai = (int)($_POST['nilai'] ?? 0); $feedback = trim($_POST['feedback'] ?? '');
        $up_n = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nilai = ?, feedback = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_n, "isi", $nilai, $feedback, $pengumpulan_id);
        mysqli_stmt_execute($up_n); mysqli_stmt_close($up_n); flash_set('Nilai disimpan.');
        header("Location: guru.php?page=detail_kelas&id=".$detail_kelas_id); exit;
    }

    // ================= AKSI KUIS =================
    if ($aksi === 'buat_kuis') {
        $judul_kuis = trim($_POST['judul_kuis'] ?? 'Kuis Baru');
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $mode_kuis = 'Live Quiz'; 
        $durasi = (int)($_POST['durasi'] ?? 60);

        if ($judul_kuis !== '' && $kelas_id > 0) {
            $sq = mysqli_prepare($koneksi, "INSERT INTO quiz (guru_id, kelas_id, judul, mode_kuis, durasi_menit) VALUES (?, ?, ?, ?, ?)");
            if($sq){
                mysqli_stmt_bind_param($sq, "iissi", $user_id, $kelas_id, $judul_kuis, $mode_kuis, $durasi);
                mysqli_stmt_execute($sq);
                $new_quiz_id = mysqli_insert_id($koneksi); 
                mysqli_stmt_close($sq);
                
                flash_set('Kuis berhasil dibuat! Silakan mulai menambahkan soal di bawah ini.');
                header("Location: guru.php?page=kelola_soal&quiz_id=" . $new_quiz_id); exit;
            } else {
                flash_set('Gagal membuat kuis. Pastikan kolom database sudah sesuai.', 'error');
                header("Location: guru.php?page=quiz"); exit;
            }
        }
    }

    if ($aksi === 'hapus_kuis') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
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
        $opsi_a = trim($_POST['opsi_a']);
        $opsi_b = trim($_POST['opsi_b']);
        $opsi_c = trim($_POST['opsi_c']);
        $opsi_d = trim($_POST['opsi_d']);
        $kunci = $_POST['kunci'] ?? 'A';

        if(!empty($pertanyaan) && !empty($opsi_a)) {
            $ins = mysqli_prepare($koneksi, "INSERT INTO quiz_soal (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "issssss", $q_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $kunci);
            if(mysqli_stmt_execute($ins)) {
                flash_set('Soal berhasil ditambahkan ke dalam kuis.');
            } else {
                flash_set('Gagal menyimpan soal.', 'error');
            }
            mysqli_stmt_close($ins);
        }
        header("Location: guru.php?page=kelola_soal&quiz_id=".$q_id); exit;
    }

    if ($aksi === 'hapus_soal') {
        $q_id = (int)$_POST['quiz_id'];
        $s_id = (int)$_POST['soal_id'];
        mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE id = $s_id AND quiz_id = $q_id");
        flash_set('Soal berhasil dihapus.');
        header("Location: guru.php?page=kelola_soal&quiz_id=".$q_id); exit;
    }
}

// Ambil Data User
$q_user = mysqli_query($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id = $user_id LIMIT 1");
$user = $q_user ? mysqli_fetch_assoc($q_user) : ['nama' => 'Guru', 'username' => 'guru', 'email' => ''];
$nama_guru = $user['nama'] ?? 'Guru';
$email_guru = $user['email'] ?? '';

// Data Pendukung Dasbor
$list_kelas = [];
$r_kls = mysqli_query($koneksi, "SELECT id AS id_kelas, nama_kelas, kode_gabung, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=k.id) AS jml_murid FROM kelas k WHERE k.guru_id=$user_id ORDER BY k.id DESC");
if($r_kls) { while ($r = mysqli_fetch_assoc($r_kls)) $list_kelas[] = $r; }
$jml_kelas = count($list_kelas);

$jml_tugas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM tugas WHERE guru_id=$user_id"))['c'] ?? 0;
$jml_absen = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM absensi WHERE guru_id=$user_id"))['c'] ?? 0;

$cek_tb_quiz = mysqli_query($koneksi, "SHOW TABLES LIKE 'quiz'");
$jml_quiz = ($cek_tb_quiz && mysqli_num_rows($cek_tb_quiz) > 0) ? (mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM quiz WHERE guru_id=$user_id"))['c'] ?? 0) : 0;

$notif_tugas_belum_dinilai = []; $notif_error = null; $tgl_hari_ini = date('Y-m-d'); $absen_hari_ini = [];
if ($page === 'beranda') {
    $q_notif = mysqli_query($koneksi, "SELECT pt.id, pt.dikumpulkan_at, l.nama AS nama_murid, t.judul_tugas, k.nama_kelas, t.kelas_id FROM pengumpulan_tugas pt LEFT JOIN login l ON pt.user_id = l.id JOIN tugas t ON pt.tugas_id = t.id JOIN kelas k ON t.kelas_id = k.id WHERE t.guru_id = $user_id AND (pt.nilai IS NULL OR pt.nilai = 0 OR pt.nilai = '') ORDER BY pt.dikumpulkan_at DESC LIMIT 5");
    if($q_notif) { while ($rn = mysqli_fetch_assoc($q_notif)) { $notif_tugas_belum_dinilai[] = $rn; } } else { $notif_error = mysqli_error($koneksi); }
    $q_absen_hari = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, k.nama_kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid, (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS total_hadir FROM absensi a JOIN kelas k ON a.kelas_id = k.id WHERE a.guru_id = $user_id AND a.tanggal = '$tgl_hari_ini'");
    if($q_absen_hari) { while ($ra = mysqli_fetch_assoc($q_absen_hari)) { $absen_hari_ini[] = $ra; } }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
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
.header-icons { display: flex; align-items: center; gap: 24px; }
.header-icons a { color: var(--sage-600); transition: all 0.3s ease; position: relative; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 10px; border-radius: 50%; border: 1px solid var(--sage-50); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.header-icons a:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); border-color: var(--sage-100); }
.header-icons svg { width: 20px; height: 20px; }
.notif-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #E07A5F; border-radius: 50%; border: 2px solid #FFFFFF; }
.nav-tabs { display: flex; justify-content: center; gap: 8px; max-width: 1200px; margin: 0 auto; padding: 0 32px; }
.nav-tab { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 28px; text-decoration: none; color: var(--sage-600); font-size: 13px; font-weight: 600; transition: all 0.3s ease; position: relative; border-radius: var(--radius-sm) var(--radius-sm) 0 0; }
.nav-tab svg { width: 20px; height: 20px; stroke-width: 2.2; }
.nav-tab:hover { color: var(--sage-800); background: rgba(255, 255, 255, 0.6); }
.nav-tab.active { color: var(--sage-900); background: #FFFFFF; }
.nav-tab.active::after { content: ''; position: absolute; bottom: 0; left: 15%; width: 70%; height: 3px; background: var(--sage-500); border-radius: 4px 4px 0 0; }
.container { max-width: 1100px; margin: 0 auto; padding: 40px 32px; position: relative; z-index: 1; }
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
.class-card { background: var(--bg-card); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm); border: 1px solid var(--sage-50); transition: all 0.4s ease; position: relative; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
.class-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--sage-100); }
.class-card-header { display: flex; align-items: center; gap: 18px; margin-bottom: 16px; }
.class-icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--sage-50); color: var(--sage-600); border: 1px solid var(--sage-100); }
.class-icon svg { width: 24px; height: 24px; stroke-width: 2; }
.class-info h4 { font-size: 17px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px; }
.class-info .murid-count { font-size: 13px; color: var(--sage-600); font-weight: 500; display: flex; align-items: center; gap: 6px; }
.kode-badge { display: inline-flex; align-items: center; gap: 10px; background: var(--nav-yellow); border: 1px dashed #E2D38A; border-radius: var(--radius-sm); padding: 8px 14px; font-family: monospace; font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 20px; }
.btn-card-action { display: flex; gap: 10px; margin-top: auto; }
.btn-hapus { background: transparent; color: #E07A5F; border: 1px solid #F4E3E0; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
.btn-hapus:hover { background: #FFF3F0; }
.btn-masuk-kelas { background: var(--sage-500); color: #fff; border: none; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; flex: 1; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
.btn-masuk-kelas:hover { background: var(--sage-600); }
.empty-state { text-align: center; padding: 40px 20px; color: var(--sage-600); background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--sage-300); }
.empty-state svg { width: 56px; height: 56px; margin-bottom: 16px; opacity: 0.5; }
.alert { padding: 16px 24px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
.alert.success { background: var(--sage-50); color: var(--sage-800); border-left: 4px solid var(--sage-500); }
.alert.error { background: #FFF3F0; color: #E07A5F; border-left: 4px solid #E07A5F; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.form-label { display: block; font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 8px; }

/* CSS BERANDA */
.beranda-hero { background: linear-gradient(135deg, var(--sage-50) 0%, #FFFFFF 100%); border-radius: var(--radius-lg); padding: 40px; border: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: var(--shadow-sm); }
.beranda-hero h1 { font-size: 26px; font-weight: 800; color: var(--sage-800); margin-bottom: 8px; }
.beranda-hero p { font-size: 14.5px; color: var(--sage-600); font-weight: 500; max-width: 550px; line-height: 1.6; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 36px; }
.stat-card { background: #FFFFFF; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 18px; }
.stat-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--nav-yellow); display: flex; align-items: center; justify-content: center; color: var(--sage-800); font-size: 24px; font-weight: 800; }
.stat-info h3 { font-size: 24px; font-weight: 800; color: var(--sage-800); }
.stat-info p { font-size: 13px; color: var(--sage-600); font-weight: 600; }
.dashboard-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px; }
.panel { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); padding: 24px; }
.panel-header { font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.panel-list { display: flex; flex-direction: column; gap: 12px; }
.panel-item { padding: 16px; border: 1px solid var(--sage-50); border-radius: var(--radius-sm); background: var(--sage-body); display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:10px; }
.panel-item h4 { font-size: 14px; font-weight: 700; color: var(--sage-800); margin-bottom: 4px; }
.panel-item p { font-size: 12px; color: var(--sage-600); }
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.quick-btn { background: #FFFFFF; border: 1px solid var(--sage-100); border-radius: var(--radius-md); padding: 20px; text-decoration: none; color: var(--sage-800); font-weight: 700; display: flex; align-items: center; gap: 14px; transition: 0.3s; box-shadow: var(--shadow-sm); }
.quick-btn:hover { background: var(--sage-50); border-color: var(--sage-300); transform: translateY(-2px); }
.quick-btn svg { width: 22px; height: 22px; color: var(--sage-500); }

/* CSS DETAIL TUGAS */
.task-section-box { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-100); margin-bottom: 24px; overflow: hidden; box-shadow: var(--shadow-sm); }
.task-section-header { background: var(--sage-50); padding: 20px 24px; border-bottom: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.student-row-list { list-style: none; padding: 0; margin: 0; }
.student-row-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--sage-50); flex-wrap: wrap; gap: 16px; }
.student-row-item:last-child { border-bottom: none; }
.badge-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 50px; }
.badge-hijau { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.badge-merah { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }
.form-penilaian-inline { display: flex; gap: 8px; align-items: center; background: #FAFCFA; padding: 12px; border-radius: 12px; border: 1px solid var(--sage-100); }

/* CSS QUIZ BUILDER */
.quiz-builder-container { display: grid; grid-template-columns: 2.2fr 1fr; gap: 24px; align-items: start; }
.builder-main { display: flex; flex-direction: column; gap: 24px; }
.builder-sidebar { position: sticky; top: 20px; background: #fff; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); }
.question-block { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-100); padding: 28px; box-shadow: var(--shadow-sm); }
.question-block-header { font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.question-block-header span { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--sage-100); color: var(--sage-800); border-radius: 8px; font-size: 14px; }
.option-row { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; background: var(--sage-body); border-radius: var(--radius-sm); padding: 12px 16px; border: 1px solid transparent; transition: 0.3s; }
.option-row:hover { border-color: var(--sage-300); background: #fff; }
.option-row input[type="radio"] { width: 22px; height: 22px; accent-color: var(--sage-500); cursor: pointer; flex-shrink: 0; }
.option-row .opt-label { font-size: 15px; font-weight: 800; color: var(--sage-600); width: 24px; text-align: center; }
.option-row input[type="text"] { flex: 1; border: none; background: transparent; outline: none; font-size: 14px; font-weight: 500; color: var(--sage-900); }
.existing-question { background: #FAFCFA; border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 20px; margin-bottom: 16px; }
.existing-question h5 { font-size: 15px; font-weight: 700; color: var(--sage-800); margin-bottom: 12px; }
.existing-option { font-size: 13.5px; color: var(--sage-600); margin-bottom: 6px; padding-left: 12px; display: flex; gap: 8px; }
.existing-option.correct { color: #2E7D32; font-weight: 700; }
.sidebar-info-item { display: flex; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid var(--sage-50); }
.sidebar-info-item:last-child { border-bottom: none; }
.sidebar-info-item svg { width: 24px; height: 24px; color: var(--sage-500); flex-shrink: 0; }
.sidebar-info-item div p { font-size: 12px; color: var(--sage-600); margin-bottom: 2px; }
.sidebar-info-item div h4 { font-size: 14px; font-weight: 700; color: var(--sage-800); }

@media (max-width: 900px) {
    .quiz-builder-container { grid-template-columns: 1fr; }
    .builder-sidebar { position: static; }
}
@media (max-width: 768px) {
    .header-top { flex-wrap: wrap; gap: 16px; }
    .nav-tabs { overflow-x: auto; justify-content: flex-start; padding: 0 16px; }
    .form-grid { grid-template-columns: 1fr; }
    .beranda-hero { flex-direction: column; align-items: flex-start; gap: 20px; }
    .dashboard-panels { grid-template-columns: 1fr; }
}
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
      <a href="#" title="Notifikasi">
        <?php if(!empty($notif_tugas_belum_dinilai)): ?><div class="notif-dot"></div><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </a>
      <a href="guru.php?page=profil" title="Profil">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </a>
      <a href="logout.php" onclick="return confirm('Keluar dari Slearning?')" title="Keluar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>

  <nav class="nav-tabs">
    <a href="guru.php?page=beranda" class="nav-tab <?= $page==='beranda'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> <span>Beranda</span></a>
    <a href="guru.php?page=kelas" class="nav-tab <?= $page==='kelas' || $page==='detail_kelas'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> <span>Kelas</span></a>
    <a href="guru.php?page=absensi" class="nav-tab <?= $page==='absensi'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg> <span>Absensi</span></a>
    <a href="guru.php?page=tugas" class="nav-tab <?= $page==='tugas'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> <span>Tugas</span></a>
    <a href="guru.php?page=quiz" class="nav-tab <?= ($page==='quiz' || $page==='kelola_soal')?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="14" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="18"/><line x1="9" y1="10" x2="9.01" y2="10"/></svg> <span>Quiz</span></a>
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
        <!-- Panel Tugas Belum Dinilai -->
        <div class="panel">
            <div class="panel-header"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#E07A5F" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Tugas Perlu Dinilai</div>
            <?php if ($notif_error): ?>
                <div class="empty-state" style="padding: 20px; border-color: red;"><p style="color:red; font-size: 13px;">Gagal mengambil data dari database.</p><p style="color:red; font-size: 11px; margin-top:5px; font-family:monospace;"><?= $notif_error ?></p></div>
            <?php elseif (empty($notif_tugas_belum_dinilai)): ?>
                <div class="empty-state" style="padding: 20px;"><p>Belum ada tugas baru yang perlu dinilai.</p></div>
            <?php else: ?>
                <div class="panel-list">
                    <?php foreach($notif_tugas_belum_dinilai as $nt): ?>
                        <div class="panel-item">
                            <div><h4><?= e($nt['nama_murid'] ?? 'Siswa Tidak Diketahui') ?></h4><p><?= e($nt['judul_tugas']) ?> - <?= e($nt['nama_kelas']) ?></p></div>
                            <a href="guru.php?page=detail_kelas&id=<?= $nt['kelas_id'] ?>" class="btn-primary" style="padding: 8px 12px; font-size: 12px;">Beri Nilai</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel Pantauan Absensi Hari Ini -->
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
        <?php foreach ($list_kelas as $kls): ?>
        <div class="class-card">
          <div class="class-card-header">
            <div class="class-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="class-info">
              <h4><?= e($kls['nama_kelas']) ?></h4>
              <span class="murid-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <?= (int)$kls['jml_murid'] ?> Murid Tergabung</span>
            </div>
          </div>
          <div class="kode-badge" title="Kode Gabung Kelas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> <?= e($kls['kode_gabung']) ?></div>
          <div class="btn-card-action">
            <a href="guru.php?page=detail_kelas&id=<?= (int)$kls['id_kelas'] ?>" class="btn-masuk-kelas">Masuk & Nilai</a>
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
    <?php endif; ?>

  <!-- ================= HALAMAN DETAIL KELAS & PENILAIAN TUGAS ================= -->
  <?php elseif ($page === 'detail_kelas'): ?>
    <?php
    $q_dk = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id = $detail_kelas_id AND guru_id = $user_id LIMIT 1");
    $kelas_ini = $q_dk ? mysqli_fetch_assoc($q_dk) : null;
    if (!$kelas_ini): ?>
      <div class="empty-state"><p>Kelas tidak ditemukan atau Anda tidak memiliki akses.</p></div>
    <?php else: ?>
      <div class="card-manage">
        <a href="guru.php?page=kelas" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:13px; margin-bottom:12px;">← Kembali ke Daftar Kelas</a>
        <h2>Pantauan Tugas & Status Pengerjaan Murid: <?= e($kelas_ini['nama_kelas']) ?></h2>
        <p>Kode Gabung: <strong><?= e($kelas_ini['kode_gabung']) ?></strong> &bull; Murid yang melewati deadline akan kehilangan akses kumpul sampai Anda memperpanjang waktunya.</p>
      </div>

      <?php
      $q_tugas_kelas = mysqli_query($koneksi, "SELECT * FROM tugas WHERE kelas_id = $detail_kelas_id ORDER BY id DESC");
      if(mysqli_num_rows($q_tugas_kelas) === 0): ?>
          <div class="empty-state"><p>Belum ada tugas yang dibuat untuk kelas ini.</p></div>
      <?php else: 
          while($tugas = mysqli_fetch_assoc($q_tugas_kelas)): 
              $tugas_id = $tugas['id'];
              $is_expired = false; $teks_deadline = 'Tanpa Batas Waktu';
              if(!empty($tugas['deadline'])) {
                  $teks_deadline = date('d M Y, H:i', strtotime($tugas['deadline']));
                  if(strtotime($tugas['deadline']) < time()) { $is_expired = true; }
              }
      ?>
          <div class="task-section-box">
              <div class="task-section-header">
                  <div>
                      <h3 style="font-size: 17px; font-weight: 800; color: var(--sage-900); margin-bottom: 4px;">📄 <?= e($tugas['judul_tugas']) ?></h3>
                      <p style="font-size: 13px; color: var(--sage-600);">
                          Deadline: <strong style="color: <?= $is_expired ? '#C62828' : 'var(--sage-800)' ?>;"><?= $teks_deadline ?></strong> 
                          <?= $is_expired ? '<span style="color: #C62828; font-weight: bold; margin-left: 6px;">(Terkunci untuk Murid)</span>' : '' ?>
                      </p>
                  </div>
                  <form action="guru.php?page=detail_kelas&id=<?= $detail_kelas_id ?>" method="POST" style="display: flex; gap: 8px; align-items: center;">
                      <input type="hidden" name="aksi" value="update_deadline">
                      <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
                      <input type="datetime-local" name="deadline" class="input-elegant" style="padding: 8px 12px; font-size: 12px; width: auto;">
                      <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 12px; background: var(--sage-800); box-shadow: none;">Ubah / Buka Akses</button>
                  </form>
              </div>

              <ul class="student-row-list">
                  <?php
                  $q_status_murid = mysqli_query($koneksi, "
                      SELECT l.id AS user_id, l.nama AS nama_murid, 
                             pt.id AS pengumpulan_id, pt.file_path, pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback 
                      FROM anggota_kelas ak JOIN login l ON ak.user_id = l.id LEFT JOIN pengumpulan_tugas pt ON l.id = pt.user_id AND pt.tugas_id = $tugas_id 
                      WHERE ak.kelas_id = $detail_kelas_id ORDER BY l.nama ASC
                  ");

                  if(mysqli_num_rows($q_status_murid) === 0):
                  ?>
                      <li class="student-row-item" style="justify-content: center; color: var(--sage-600);">Belum ada murid yang bergabung di kelas ini.</li>
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
                                      <span class="badge-status badge-hijau" title="Murid sudah mengumpulkan tugas"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Sudah Mengerjakan</span>
                                  <?php else: ?>
                                      <span class="badge-status badge-merah" title="Murid belum mengumpulkan tugas"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Belum Mengerjakan</span>
                                  <?php endif; ?>
                              </div>
                              <?php if($sudah_mengerjakan): 
                                  $file_path = $sm['file_path'] ?? ''; $nama_file = $sm['nama_file'] ?? ''; $link_file = '';
                                  if (!empty($file_path)) { $link_file = (strpos($file_path, 'uploads/') === false) ? 'uploads/' . ltrim($file_path, '/') : $file_path; } 
                                  elseif (!empty($nama_file)) { $link_file = 'uploads/' . $nama_file; }
                              ?>
                                  <div style="display: flex; align-items: center; gap: 12px; margin-top: 4px;">
                                      <?php if(!empty($link_file)): ?>
                                          <a href="<?= e($link_file) ?>" target="_blank" style="font-size: 12px; color: var(--sage-600); text-decoration: underline; font-weight: 600;">Lihat File Lampiran</a>
                                      <?php endif; ?>
                                      <span style="font-size: 11px; color: #9ca3af;">Dikumpul: <?= e($sm['dikumpulkan_at']) ?></span>
                                  </div>
                              <?php endif; ?>
                          </div>

                          <?php if($sudah_mengerjakan): ?>
                              <form action="guru.php?page=detail_kelas&id=<?= $detail_kelas_id ?>" method="POST" class="form-penilaian-inline">
                                  <input type="hidden" name="aksi" value="beri_nilai">
                                  <input type="hidden" name="pengumpulan_id" value="<?= (int)$sm['pengumpulan_id'] ?>">
                                  <div><label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Nilai</label><input type="number" name="nilai" value="<?= $sm['nilai'] ?>" placeholder="0-100" class="input-elegant" style="width: 75px; padding: 6px 10px; background: #fff;" required></div>
                                  <div><label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Feedback</label><input type="text" name="feedback" value="<?= e($sm['feedback'] ?? '') ?>" placeholder="Catatan..." class="input-elegant" style="width: 130px; padding: 6px 10px; background: #fff;"></div>
                                  <button type="submit" class="btn-primary" style="padding: 8px 14px; font-size: 12px; margin-top: 15px; box-shadow: none;">Simpan</button>
                              </form>
                          <?php endif; ?>
                      </li>
                  <?php endwhile; endif; ?>
              </ul>
          </div>
      <?php endwhile; endif; ?>
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
    $qa = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid, (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS total_hadir FROM absensi a JOIN kelas k ON k.id=a.kelas_id WHERE a.guru_id=$user_id ORDER BY a.id DESC");
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
          <form method="POST" onsubmit="return confirm('Hapus sesi absensi ini?')" style="margin-top:auto;">
            <input type="hidden" name="aksi" value="hapus_absen">
            <input type="hidden" name="absen_id" value="<?= (int)$ab['id'] ?>">
            <button type="submit" class="btn-hapus" style="width:100%;">Hapus Sesi Absensi</button>
          </form>
        </div>
        <?php endforeach; ?>
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
          <div><label class="form-label">Judul Tugas</label><input type="text" name="judul_tugas" class="input-elegant" required placeholder="Contoh: Praktik Algoritma Pemrograman"></div>
          <div>
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required style="cursor:pointer;">
              <option value="" disabled selected>-- Tentukan Kelas Tujuan --</option>
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
          <textarea name="deskripsi" class="input-elegant" style="min-height: 120px; resize: vertical;" placeholder="Tulis instruksi tugas secara rinci di sini..."></textarea>
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
      <p>Perbarui informasi identitas akun pengajar serta ubah kata sandi.</p>
      <form action="guru.php?page=profil" method="POST" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="update_profil">
        <div class="form-grid">
          <div><label class="form-label">Nama Lengkap</label><input type="text" name="nama" class="input-elegant" value="<?= e($nama_guru) ?>" required></div>
          <div><label class="form-label">Alamat Email</label><input type="email" name="email" class="input-elegant" value="<?= e($email_guru) ?>" required></div>
        </div>
        <div class="form-grid">
          <div><label class="form-label">Password Lama</label><input type="password" name="password_lama" class="input-elegant" placeholder="Password lama..."></div>
          <div><label class="form-label">Password Baru</label><input type="password" name="password_baru" class="input-elegant" placeholder="Password baru..."></div>
        </div>
        <button type="submit" class="btn-primary">Simpan Perubahan Profil</button>
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
        <div style="margin-bottom: 24px;">
            <label class="form-label">Total Durasi Pengerjaan Kuis (Menit)</label>
            <input type="number" name="durasi" class="input-elegant" placeholder="Contoh: 45" style="max-width: 300px;" required>
        </div>
        <button type="submit" class="btn-primary">Selanjutnya: Buat Soal Kuis →</button>
      </form>
    </div>

    <!-- DAFTAR KUIS -->
    <h3 class="section-title">Riwayat & Daftar Kuis Anda</h3>
    <?php 
      $list_kuis = [];
      $qk = mysqli_query($koneksi, "SELECT q.id, q.judul, k.nama_kelas AS kelas, q.durasi_menit, (SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id = q.id) AS total_soal FROM quiz q JOIN kelas k ON q.kelas_id = k.id WHERE q.guru_id = $user_id ORDER BY q.id DESC");
      if($qk) { while ($rk = mysqli_fetch_assoc($qk)) { $list_kuis[] = $rk; } }
    ?>
    <?php if (empty($list_kuis)): ?>
        <div class="empty-state"><p>Belum ada kuis yang dibuat.</p></div>
    <?php else: ?>
        <div class="class-grid">
            <?php foreach ($list_kuis as $quiz): ?>
            <div class="class-card">
                <h4 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px;"><?= e($quiz['judul']) ?></h4>
                <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 4px;">📍 Kelas: <strong><?= e($quiz['kelas']) ?></strong></p>
                <p style="font-size: 13px; color: var(--sage-600); margin-bottom: 20px;">
                   ⏱️ Durasi: <?= (int)$quiz['durasi_menit'] ?> Menit | 📝 Total Soal: <strong><?= (int)$quiz['total_soal'] ?></strong>
                </p>

                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto;">
                    <a href="guru.php?page=kelola_soal&quiz_id=<?= $quiz['id'] ?>" class="btn-primary" style="justify-content: center; font-size: 12px; padding: 10px; background: var(--sage-500); width: 100%;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kelola Pertanyaan
                    </a>
                    <a href="guru.php?page=live_quiz&quiz_id=<?= $quiz['id'] ?>" class="btn-primary" style="justify-content: center; font-size: 12px; padding: 10px; background: #E07A5F; margin-top: 4px; border: 1px solid #D66D52;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> Mulai Live Quiz Sekarang
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
        <!-- Area Kiri: Form Buat Soal & List Soal -->
        <div class="builder-main">
            <!-- Form Tambah Soal Baru -->
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

            <!-- List Pertanyaan yang sudah dibuat -->
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

        <!-- Area Kanan: Sidebar Info Pengaturan Kuis -->
        <div class="builder-sidebar">
            <h3 style="font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 20px;">Pengaturan Kuis</h3>
            
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                <div><p>Mata Pelajaran (Kelas)</p><h4><?= e($quiz_info['nama_kelas']) ?></h4></div>
            </div>
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <div><p>Durasi Pengerjaan</p><h4><?= (int)$quiz_info['durasi_menit'] ?> Menit</h4></div>
            </div>
            <div class="sidebar-info-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <div><p>Total Pertanyaan</p><h4><?= $total_soal ?> Pertanyaan</h4></div>
            </div>

            <a href="guru.php?page=quiz" class="btn-outline" style="width: 100%; justify-content: center; margin-top: 32px;">Selesai & Tutup</a>
        </div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</main>
</body>
</html>
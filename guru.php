<?php
// SLearning — Dashboard Guru (fungsional penuh)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (($_SESSION['jenis'] ?? '') === 'murid') { header("Location: murid.php"); exit; }

$user_id = (int)$_SESSION['user_id'];
$stmt = mysqli_prepare($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$user) { header("Location: logout.php"); exit; }
foreach (['nama','username','email','jenis','level'] as $k) $_SESSION[$k] = $user[$k] ?? $_SESSION[$k] ?? '';
$nama_guru = $user['nama'];

function flash_set($msg, $type='success'){ $_SESSION['flash_msg']=$msg; $_SESSION['flash_type']=$type; }
$flash_msg = $_SESSION['flash_msg'] ?? ''; $flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

function guru_redirect($anchor=''){ header("Location: guru.php".$anchor); exit; }
function kode_kelas_baru(){ return 'KLS-'.strtoupper(substr(md5(mt_rand().time().rand()),0,5)); }

// ---------- AKSI POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    if ($aksi === 'tambah_kelas') {
        $nama = trim($_POST['nama_kelas_baru'] ?? '');
        if ($nama !== '') {
            $kode = kode_kelas_baru();
            $s = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, kode_gabung, guru_id) VALUES (?,?,?)");
            mysqli_stmt_bind_param($s, "ssi", $nama, $kode, $user_id);
            if (mysqli_stmt_execute($s)) flash_set('Kelas "'.$nama.'" dibuat. Kode: '.$kode.'.');
            else flash_set('Gagal membuat kelas.', 'error');
            mysqli_stmt_close($s);
        } else flash_set('Nama kelas wajib diisi.', 'error');
        guru_redirect('#manajemen-kelas');
    }
    if ($aksi === 'hapus_kelas') {
        $id = (int)($_POST['kelas_id'] ?? 0);
        // pastikan milik guru ini
        $c = mysqli_prepare($koneksi, "SELECT id FROM kelas WHERE id=? AND guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($c, "ii", $id, $user_id); mysqli_stmt_execute($c);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($c))) {
            // hapus berantai manual
            mysqli_query($koneksi, "DELETE ah FROM absensi_hadir ah INNER JOIN absensi a ON a.id=ah.absensi_id WHERE a.kelas_id=$id");
            mysqli_query($koneksi, "DELETE FROM absensi WHERE kelas_id=$id AND guru_id=$user_id");
            mysqli_query($koneksi, "DELETE pt FROM pengumpulan_tugas pt INNER JOIN tugas t ON t.id=pt.tugas_id WHERE t.kelas_id=$id");
            mysqli_query($koneksi, "DELETE FROM tugas WHERE kelas_id=$id AND guru_id=$user_id");
            mysqli_query($koneksi, "DELETE qh FROM quiz_hasil qh INNER JOIN quiz q ON q.id=qh.quiz_id WHERE q.kelas_id=$id");
            mysqli_query($koneksi, "DELETE qs FROM quiz_soal qs INNER JOIN quiz q ON q.id=qs.quiz_id WHERE q.kelas_id=$id");
            mysqli_query($koneksi, "DELETE FROM quiz WHERE kelas_id=$id AND guru_id=$user_id");
            mysqli_query($koneksi, "DELETE FROM anggota_kelas WHERE kelas_id=$id");
            mysqli_query($koneksi, "DELETE FROM kelas WHERE id=$id AND guru_id=$user_id");
            flash_set('Kelas beserta seluruh isinya dihapus.');
        } else flash_set('Kelas tidak ditemukan.', 'error');
        mysqli_stmt_close($c);
        guru_redirect('#manajemen-kelas');
    }
    if ($aksi === 'buat_absen') {
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $tanggal = $_POST['tanggal_absen'] ?? date('Y-m-d');
        $jm = $_POST['jam_mulai'] ?? '07:00'; $js = $_POST['jam_selesai'] ?? '09:00';
        $judul = trim($_POST['judul_absen'] ?? '');
        if ($judul === '') $judul = 'Absensi Pertemuan — '.$tanggal.' ('.$jm.'–'.$js.' WIB)';
        if ($kelas_id > 0) {
            $s = mysqli_prepare($koneksi, "INSERT INTO absensi (guru_id, kelas_id, judul_absensi, tanggal, jam_mulai, jam_selesai) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "iissss", $user_id, $kelas_id, $judul, $tanggal, $jm, $js);
            if (mysqli_stmt_execute($s)) flash_set('Sesi absensi dibuka. Murid kelas terkait kini bisa mengisi kehadiran.');
            else flash_set('Gagal membuka sesi absensi.', 'error');
            mysqli_stmt_close($s);
        } else flash_set('Pilih kelas terlebih dahulu.', 'error');
        guru_redirect('#menu-absensi');
    }
    if ($aksi === 'hapus_absen') {
        $id = (int)($_POST['absen_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM absensi_hadir WHERE absensi_id=$id");
        $s = mysqli_prepare($koneksi, "DELETE FROM absensi WHERE id=? AND guru_id=?");
        mysqli_stmt_bind_param($s, "ii", $id, $user_id); mysqli_stmt_execute($s); mysqli_stmt_close($s);
        flash_set('Sesi absensi dihapus.');
        guru_redirect('#menu-absensi');
    }
    if ($aksi === 'tambah_tugas') {
        $judul = trim($_POST['judul_tugas'] ?? ''); $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $deadline = str_replace('T',' ', trim($_POST['deadline'] ?? '')); $desk = trim($_POST['deskripsi'] ?? '');
        if ($judul !== '' && $kelas_id > 0) {
            if ($deadline === '') $deadline = null;
            $s = mysqli_prepare($koneksi, "INSERT INTO tugas (guru_id, kelas_id, judul_tugas, deadline, deskripsi) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "iisss", $user_id, $kelas_id, $judul, $deadline, $desk);
            if (mysqli_stmt_execute($s)) flash_set('Tugas "'.$judul.'" dipublikasikan ke kelas.');
            else flash_set('Gagal mempublikasikan tugas: '.mysqli_error($koneksi), 'error');
            mysqli_stmt_close($s);
        } else flash_set('Judul dan kelas wajib diisi.', 'error');
        guru_redirect('#daftar-tugas');
    }
    if ($aksi === 'hapus_tugas') {
        $id = (int)($_POST['tugas_id'] ?? 0);
        // hapus file fisik pengumpulan
        $q = mysqli_query($koneksi, "SELECT file_path FROM pengumpulan_tugas WHERE tugas_id=$id");
        while ($q && $r = mysqli_fetch_assoc($q)) { $f = __DIR__.'/'.$r['file_path']; if (is_file($f)) @unlink($f); }
        mysqli_query($koneksi, "DELETE FROM pengumpulan_tugas WHERE tugas_id=$id");
        $s = mysqli_prepare($koneksi, "DELETE FROM tugas WHERE id=? AND guru_id=?");
        mysqli_stmt_bind_param($s, "ii", $id, $user_id); mysqli_stmt_execute($s); mysqli_stmt_close($s);
        flash_set('Tugas dihapus beserta seluruh pengumpulannya.');
        guru_redirect('#daftar-tugas');
    }
    if ($aksi === 'nilai_tugas') {
        $pid = (int)($_POST['pengumpulan_id'] ?? 0);
        $nilai = ($_POST['nilai'] ?? '') === '' ? null : max(0, min(100, (int)$_POST['nilai']));
        $fb = trim($_POST['feedback'] ?? '');
        $s = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas pt INNER JOIN tugas t ON t.id=pt.tugas_id SET pt.nilai=?, pt.feedback=? WHERE pt.id=? AND t.guru_id=?");
        mysqli_stmt_bind_param($s, "issi", $nilai, $fb, $pid, $user_id);
        if (mysqli_stmt_execute($s)) flash_set('Nilai tersimpan.');
        else flash_set('Gagal menyimpan nilai.', 'error');
        mysqli_stmt_close($s);
        guru_redirect('#daftar-tugas');
    }
    if ($aksi === 'buat_quiz') {
        $judul = trim($_POST['judul_quiz'] ?? ''); $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $dur = max(5, min(180, (int)($_POST['durasi'] ?? 30))); $desk = trim($_POST['deskripsi_quiz'] ?? '');
        if ($judul !== '' && $kelas_id > 0) {
            $s = mysqli_prepare($koneksi, "INSERT INTO quiz (guru_id, kelas_id, judul, deskripsi, durasi_menit) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "iissi", $user_id, $kelas_id, $judul, $desk, $dur);
            if (mysqli_stmt_execute($s)) flash_set('Quiz "'.$judul.'" dibuat. Tambahkan soal pada kartu quiz.');
            else flash_set('Gagal membuat quiz.', 'error');
            mysqli_stmt_close($s);
        } else flash_set('Judul quiz dan kelas wajib diisi.', 'error');
        guru_redirect('#menu-quiz');
    }
    if ($aksi === 'hapus_quiz') {
        $id = (int)($_POST['quiz_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM quiz_hasil WHERE quiz_id=$id");
        mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE quiz_id=$id");
        $s = mysqli_prepare($koneksi, "DELETE FROM quiz WHERE id=? AND guru_id=?");
        mysqli_stmt_bind_param($s, "ii", $id, $user_id); mysqli_stmt_execute($s); mysqli_stmt_close($s);
        flash_set('Quiz dihapus.');
        guru_redirect('#menu-quiz');
    }
    if ($aksi === 'tambah_soal') {
        $qid = (int)($_POST['quiz_id'] ?? 0);
        $tanya = trim($_POST['pertanyaan'] ?? '');
        $a = trim($_POST['opsi_a'] ?? ''); $b = trim($_POST['opsi_b'] ?? '');
        $c = trim($_POST['opsi_c'] ?? ''); $d = trim($_POST['opsi_d'] ?? '');
        $kunci = strtoupper(trim($_POST['kunci'] ?? 'A'));
        if (!in_array($kunci, ['A','B','C','D'], true)) $kunci = 'A';
        // pastikan quiz milik guru
        $ck = mysqli_prepare($koneksi, "SELECT id FROM quiz WHERE id=? AND guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($ck, "ii", $qid, $user_id); mysqli_stmt_execute($ck);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($ck)) && $tanya !== '' && $a !== '' && $b !== '' && $c !== '' && $d !== '') {
            $s = mysqli_prepare($koneksi, "INSERT INTO quiz_soal (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci) VALUES (?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "issssss", $qid, $tanya, $a, $b, $c, $d, $kunci);
            if (mysqli_stmt_execute($s)) flash_set('Soal ditambahkan ke quiz.');
            else flash_set('Gagal menambah soal.', 'error');
            mysqli_stmt_close($s);
        } else flash_set('Lengkapi pertanyaan dan semua opsi.', 'error');
        mysqli_stmt_close($ck);
        guru_redirect('#menu-quiz');
    }
    if ($aksi === 'hapus_soal') {
        $sid = (int)($_POST['soal_id'] ?? 0);
        mysqli_query($koneksi, "DELETE qs FROM quiz_soal qs INNER JOIN quiz q ON q.id=qs.quiz_id WHERE qs.id=$sid AND q.guru_id=$user_id");
        flash_set('Soal dihapus.');
        guru_redirect('#menu-quiz');
    }
}

// ---------- AMBIL DATA ----------
$list_kelas = [];
$s = mysqli_prepare($koneksi, "SELECT k.id AS id_kelas, k.nama_kelas, k.kode_gabung, k.created_at,
    (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=k.id) AS jml_murid FROM kelas k WHERE k.guru_id=? ORDER BY k.id DESC");
mysqli_stmt_bind_param($s, "i", $user_id); mysqli_stmt_execute($s);
$r = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($r)) $list_kelas[] = $row;
mysqli_stmt_close($s);

$list_tugas = [];
$s = mysqli_prepare($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, t.deskripsi, t.created_at, k.nama_kelas AS kelas, k.id AS kelas_id,
    (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=t.kelas_id) AS jml_target,
    (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id) AS jml_kumpul,
    (SELECT ROUND(AVG(pt.nilai)) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id AND pt.nilai IS NOT NULL) AS rata
    FROM tugas t JOIN kelas k ON t.kelas_id=k.id WHERE t.guru_id=? ORDER BY t.id DESC");
mysqli_stmt_bind_param($s, "i", $user_id); mysqli_stmt_execute($s);
$r = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($r)) $list_tugas[] = $row;
mysqli_stmt_close($s);

$list_absensi = [];
$s = mysqli_prepare($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, a.jam_mulai, a.jam_selesai, k.nama_kelas AS kelas,
    (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS target,
    (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS hadir_total
    FROM absensi a JOIN kelas k ON a.kelas_id=k.id WHERE a.guru_id=? ORDER BY a.id DESC");
mysqli_stmt_bind_param($s, "i", $user_id); mysqli_stmt_execute($s);
$r = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($r)) $list_absensi[] = $row;
mysqli_stmt_close($s);

$list_quiz = [];
$s = mysqli_prepare($koneksi, "SELECT q.id, q.judul, q.deskripsi, q.durasi_menit, q.created_at, k.nama_kelas AS kelas,
    (SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jml_soal,
    (SELECT COUNT(*) FROM quiz_hasil qh WHERE qh.quiz_id=q.id) AS jml_peserta,
    (SELECT ROUND(AVG(qh.skor)) FROM quiz_hasil qh WHERE qh.quiz_id=q.id) AS rata
    FROM quiz q JOIN kelas k ON q.kelas_id=k.id WHERE q.guru_id=? ORDER BY q.id DESC");
mysqli_stmt_bind_param($s, "i", $user_id); mysqli_stmt_execute($s);
$r = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($r)) $list_quiz[] = $row;
mysqli_stmt_close($s);

// detail: anggota kelas terpilih
$lihat_kelas = (int)($_GET['lihat_kelas'] ?? 0);
$anggota = [];
if ($lihat_kelas > 0) {
    $s = mysqli_prepare($koneksi, "SELECT l.nama, l.username, ak.joined_at FROM anggota_kelas ak JOIN login l ON l.id=ak.user_id JOIN kelas k ON k.id=ak.kelas_id WHERE ak.kelas_id=? AND k.guru_id=? ORDER BY ak.joined_at ASC");
    mysqli_stmt_bind_param($s, "ii", $lihat_kelas, $user_id); mysqli_stmt_execute($s);
    $rr = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($rr)) $anggota[] = $row;
    mysqli_stmt_close($s);
}
// detail: rekap absensi
$rekap_absen = (int)($_GET['rekap_absen'] ?? 0);
$rekap_rows = [];
if ($rekap_absen > 0) {
    $s = mysqli_prepare($koneksi, "SELECT l.nama, l.username, ah.status, ah.keterangan, ah.waktu FROM anggota_kelas ak JOIN login l ON l.id=ak.user_id LEFT JOIN absensi_hadir ah ON ah.user_id=l.id AND ah.absensi_id=? JOIN absensi a ON a.id=? AND a.kelas_id=ak.kelas_id WHERE a.guru_id=? ORDER BY l.nama ASC");
    mysqli_stmt_bind_param($s, "iii", $rekap_absen, $rekap_absen, $user_id); mysqli_stmt_execute($s);
    $rr = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($rr)) $rekap_rows[] = $row;
    mysqli_stmt_close($s);
}
// detail: pengumpul tugas
$nilai_tugas = (int)($_GET['nilai_tugas'] ?? 0);
$pengumpul = []; $info_tugas = null;
if ($nilai_tugas > 0) {
    $s = mysqli_prepare($koneksi, "SELECT t.id, t.judul_tugas, k.nama_kelas FROM tugas t JOIN kelas k ON k.id=t.kelas_id WHERE t.id=? AND t.guru_id=? LIMIT 1");
    mysqli_stmt_bind_param($s, "ii", $nilai_tugas, $user_id); mysqli_stmt_execute($s);
    $info_tugas = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($info_tugas) {
        $s = mysqli_prepare($koneksi, "SELECT pt.id AS pid, l.nama, l.username, pt.nama_file, pt.file_path, pt.dikumpulkan_at, pt.nilai, pt.feedback FROM anggota_kelas ak JOIN login l ON l.id=ak.user_id JOIN tugas t ON t.kelas_id=ak.kelas_id AND t.id=? LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=l.id WHERE t.guru_id=? ORDER BY l.nama ASC");
        mysqli_stmt_bind_param($s, "ii", $nilai_tugas, $user_id); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($rr)) $pengumpul[] = $row;
        mysqli_stmt_close($s);
    }
}
// detail: kelola soal + hasil quiz
$kelola_quiz = (int)($_GET['kelola_quiz'] ?? 0);
$soal_list = []; $hasil_list = []; $info_quiz = null;
if ($kelola_quiz > 0) {
    $s = mysqli_prepare($koneksi, "SELECT q.id, q.judul, k.nama_kelas, q.durasi_menit FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.id=? AND q.guru_id=? LIMIT 1");
    mysqli_stmt_bind_param($s, "ii", $kelola_quiz, $user_id); mysqli_stmt_execute($s);
    $info_quiz = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($info_quiz) {
        $s = mysqli_prepare($koneksi, "SELECT * FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($s, "i", $kelola_quiz); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($rr)) $soal_list[] = $row;
        mysqli_stmt_close($s);
        $s = mysqli_prepare($koneksi, "SELECT l.nama, l.username, qh.benar, qh.total, qh.skor, qh.dikerjakan_at FROM quiz_hasil qh JOIN login l ON l.id=qh.user_id WHERE qh.quiz_id=? ORDER BY qh.skor DESC");
        mysqli_stmt_bind_param($s, "i", $kelola_quiz); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); while ($row = mysqli_fetch_assoc($rr)) $hasil_list[] = $row;
        mysqli_stmt_close($s);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Guru — SLearning</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="guru.php" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam"><span class="brand-text"><strong>SLearning</strong><span>Guru • SMKN 7</span></span></a>
  <nav class="nav-links" id="navLinks">
    <a href="guru.php" class="active">Beranda</a>
    <a href="#manajemen-kelas">Kelas</a>
    <a href="#menu-absensi">Absensi</a>
    <a href="#daftar-tugas">Tugas</a>
    <a href="#menu-quiz">Quiziz</a>
  </nav>
  <div class="nav-right" id="navRight">
    <div class="profile"><div class="avatar"><?= e(inisial_nama($nama_guru)) ?></div><div class="profile-info"><strong><?= e($nama_guru) ?></strong><span>Pengajar</span></div></div>
    <a href="profil.php" class="btn btn-ghost btn-sm"><?= svg_icon('user',15) ?> Profil</a>
    <a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin keluar?')"><?= svg_icon('logout',15) ?> Keluar</a>
  </div>
  <button class="burger" id="burger" aria-label="Buka menu" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>

<main class="page page-grid-bg"><div class="container page-inner">
  <section class="welcome"><div><small>Dashboard Guru</small><h1>Halo, <?= e($nama_guru) ?></h1><p>Kelola kelas, absensi, tugas beserta penilaian, dan Quiziz dalam satu tempat. Semua tersimpan permanen di database.</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:2"><a class="btn btn-yellow" href="#buat-tugas"><?= svg_icon('plus',16) ?> Buat Tugas</a><a class="btn btn-ghost" href="quiziz.php" style="background:#fff"><?= svg_icon('help',16) ?> Kelola Quiziz</a></div></section>

  <?php if ($flash_msg): ?><div class="alert <?= $flash_type==='error'?'error':'success' ?>"><?= svg_icon($flash_type==='error'?'info':'check-circle',18) ?><span><?= e($flash_msg) ?></span></div><?php endif; ?>

  <section class="stats">
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('book',20) ?></div><div><div class="stat-number"><?= count($list_kelas) ?></div><div class="stat-label">Kelas Terdaftar</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('clipboard',20) ?></div><div><div class="stat-number"><?= count($list_tugas) ?></div><div class="stat-label">Tugas Dibuat</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('help',20) ?></div><div><div class="stat-number"><?= count($list_quiz) ?></div><div class="stat-label">Quiz Dibuat</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('calendar',20) ?></div><div><div class="stat-number"><?= count($list_absensi) ?></div><div class="stat-label">Sesi Absensi</div></div></div>
  </section>

  <div class="main-grid"><div>
    <!-- KELAS -->
    <section class="section" id="manajemen-kelas">
      <div class="section-head"><div><span class="eyebrow">Manajemen Kelas</span><h2>Kelas &amp; Kode Gabung</h2><p>Bagikan kode ke murid agar mereka bisa bergabung.</p></div></div>
      <form action="guru.php#manajemen-kelas" method="POST" class="form-card accent">
        <input type="hidden" name="aksi" value="tambah_kelas">
        <div class="form-group" style="margin-bottom:0"><label for="nama_kelas_baru">Buat kelas baru</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap"><input type="text" name="nama_kelas_baru" id="nama_kelas_baru" class="form-control" placeholder="cth: XI PPLG 3" required style="flex:1;min-width:200px"><button type="submit" class="btn btn-yellow"><?= svg_icon('plus',16) ?> Buat Kelas</button></div>
        </div>
      </form>
      <?php if (empty($list_kelas)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada kelas. Buat kelas pertama Anda di atas.</span></div>
      <?php else: ?><div class="class-grid">
        <?php foreach ($list_kelas as $kls): ?>
        <div class="class-card"><h4><?= e($kls['nama_kelas']) ?></h4><p class="sub"><?= (int)$kls['jml_murid'] ?> murid tergabung</p>
          <div class="code-box" title="Klik untuk salin" onclick="navigator.clipboard.writeText('<?= e($kls['kode_gabung']) ?>');alert('Kode <?= e($kls['kode_gabung']) ?> disalin.');"><?= svg_icon('copy',15) ?> <?= e($kls['kode_gabung']) ?></div>
          <div class="class-actions"><a class="btn btn-ghost btn-sm" style="flex:1" href="?lihat_kelas=<?= (int)$kls['id_kelas'] ?>#manajemen-kelas"><?= svg_icon('users',15) ?> Anggota</a>
          <form method="POST" onsubmit="return confirm('Hapus kelas ini beserta semua tugas, absensi, dan quiz di dalamnya?')" style="flex:1"><input type="hidden" name="aksi" value="hapus_kelas"><input type="hidden" name="kelas_id" value="<?= (int)$kls['id_kelas'] ?>"><button class="btn btn-danger btn-sm" style="width:100%" type="submit"><?= svg_icon('trash',15) ?> Hapus</button></form></div>
        </div>
        <?php endforeach; ?>
      </div><?php endif; ?>
      <?php if ($lihat_kelas > 0): ?><div class="card" style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px"><h3 style="font-size:16px">Anggota kelas</h3><a class="btn btn-ghost btn-sm" href="guru.php#manajemen-kelas"><?= svg_icon('x',15) ?> Tutup</a></div>
        <?php if (empty($anggota)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada murid bergabung. Bagikan kode kelas.</span></div>
        <?php else: ?><div class="table-wrap"><table class="data"><tr><th>Nama</th><th>Username</th><th>Bergabung</th></tr>
        <?php foreach ($anggota as $a): ?><tr><td><strong><?= e($a['nama']) ?></strong></td><td>@<?= e($a['username']) ?></td><td><?= e(tanggal_id($a['joined_at'])) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
      </div><?php endif; ?>
    </section>

    <!-- ABSENSI -->
    <section class="section" id="menu-absensi">
      <div class="section-head"><div><span class="eyebrow">Kehadiran</span><h2>Absensi Murid</h2><p>Buka sesi agar murid bisa mengisi kehadiran.</p></div></div>
      <form action="guru.php#menu-absensi" method="POST" class="form-card">
        <input type="hidden" name="aksi" value="buat_absen">
        <h3 style="font-size:15.5px;margin-bottom:12px">Buka sesi absensi baru</h3>
        <div class="form-group"><label>Judul sesi (opsional)</label><input type="text" name="judul_absen" class="form-control" placeholder="cth: Pertemuan 5 — Jaringan Dasar"></div>
        <div class="form-group"><label>Pilih kelas</label><select name="kelas_id" class="form-control" required><option value="" disabled selected>-- Pilih kelas --</option><?php foreach ($list_kelas as $kls): ?><option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option><?php endforeach; ?></select></div>
        <div class="form-row"><div class="form-group"><label>Tanggal</label><input type="date" name="tanggal_absen" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label>Jam mulai – selesai</label><div style="display:flex;gap:8px"><input type="time" name="jam_mulai" class="form-control" value="07:00" required><input type="time" name="jam_selesai" class="form-control" value="09:00" required></div></div></div>
        <button type="submit" class="btn btn-yellow btn-block"><?= svg_icon('calendar',16) ?> Buka Sesi Absensi</button>
      </form>
      <div class="task-list">
        <?php if (empty($list_absensi)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada sesi absensi.</span></div>
        <?php else: foreach ($list_absensi as $ab): ?>
        <div class="task-card"><div class="task-icon"><?= svg_icon('calendar',20) ?></div>
          <div class="task-content"><h3><?= e($ab['judul_absensi']) ?></h3><p><?= e($ab['kelas']) ?> • <?= e(tanggal_id($ab['tanggal'], false)) ?><?= $ab['jam_mulai'] ? ' • '.e(substr($ab['jam_mulai'],0,5)).'–'.e(substr($ab['jam_selesai']??'',0,5)) : '' ?> • <strong><?= (int)$ab['hadir_total'] ?>/<?= (int)$ab['target'] ?> hadir</strong></p>
          <div class="task-meta"><a class="link-more" href="?rekap_absen=<?= (int)$ab['id'] ?>#menu-absensi"><?= svg_icon('eye',14) ?> Lihat rekap</a></div></div>
          <form method="POST" onsubmit="return confirm('Hapus sesi absensi ini?')"><input type="hidden" name="aksi" value="hapus_absen"><input type="hidden" name="absen_id" value="<?= (int)$ab['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><?= svg_icon('trash',14) ?></button></form>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <?php if ($rekap_absen > 0): ?><div class="card" style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h3 style="font-size:16px">Rekap kehadiran</h3><a class="btn btn-ghost btn-sm" href="guru.php#menu-absensi"><?= svg_icon('x',15) ?> Tutup</a></div>
        <?php if (empty($rekap_rows)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Tidak ada murid di kelas sesi ini.</span></div>
        <?php else: ?><div class="table-wrap"><table class="data"><tr><th>Nama</th><th>Status</th><th>Waktu</th></tr>
        <?php foreach ($rekap_rows as $rw): ?><tr><td><strong><?= e($rw['nama']) ?></strong><br><span style="color:var(--muted);font-size:12px">@<?= e($rw['username']) ?></span></td>
        <td><?php if (!$rw['status']): ?><span class="pill muted">Belum absen</span><?php else: ?><span class="pill <?= $rw['status']==='hadir'?'ok':($rw['status']==='alpa'?'red':'wait') ?>"><?= e(ucfirst($rw['status'])) ?></span><?php if ($rw['keterangan']) echo '<br><span style="font-size:12px;color:var(--muted)">'.e($rw['keterangan']).'</span>'; ?><?php endif; ?></td>
        <td style="font-size:12px"><?= $rw['waktu'] ? e(tanggal_id($rw['waktu'])) : '-' ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
      </div><?php endif; ?>
    </section>

    <!-- TUGAS -->
    <section class="section" id="buat-tugas">
      <div class="section-head"><div><span class="eyebrow">Penugasan</span><h2>Buat Tugas Baru</h2><p>Publikasikan tugas ke kelas.</p></div></div>
      <form action="guru.php#daftar-tugas" method="POST" class="form-card">
        <input type="hidden" name="aksi" value="tambah_tugas">
        <div class="form-group"><label>Judul tugas</label><input type="text" name="judul_tugas" class="form-control" placeholder="cth: Praktik PBO — Class & Object" required></div>
        <div class="form-row"><div class="form-group"><label>Pilih kelas</label><select name="kelas_id" class="form-control" required><option value="" disabled selected>-- Pilih kelas --</option><?php foreach ($list_kelas as $kls): ?><option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Batas waktu (deadline)</label><input type="datetime-local" name="deadline" class="form-control"></div></div>
        <div class="form-group"><label>Instruksi pengerjaan</label><textarea name="deskripsi" class="form-control" placeholder="Tuliskan detail tugas, format file, dan kriteria penilaian..."></textarea></div>
        <button type="submit" class="btn btn-yellow btn-block"><?= svg_icon('send',16) ?> Publikasikan Tugas</button>
      </form>
    </section>

    <section class="section" id="daftar-tugas">
      <div class="section-head"><div><h2>Tugas Terkirim</h2><p>Jumlah kumpul dan rata-rata nilai terhitung otomatis.</p></div></div>
      <div class="task-list">
        <?php if (empty($list_tugas)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada tugas yang dipublikasikan.</span></div>
        <?php else: foreach ($list_tugas as $tg): ?>
        <div class="task-card"><div class="task-icon"><?= svg_icon('clipboard',20) ?></div>
          <div class="task-content"><h3><?= e($tg['judul']) ?> <span class="pill blue"><?= e($tg['kelas']) ?></span></h3>
          <p>Deadline: <?= $tg['deadline'] ? e(tanggal_id($tg['deadline'])) : 'Tanpa deadline' ?> • <strong><?= (int)$tg['jml_kumpul'] ?>/<?= (int)$tg['jml_target'] ?> kumpul</strong><?= $tg['rata']!==null ? ' • Rata-rata: <strong>'.(int)$tg['rata'].'</strong>' : '' ?></p>
          <div class="task-meta"><a class="link-more" href="?nilai_tugas=<?= (int)$tg['id'] ?>#daftar-tugas"><?= svg_icon('award',14) ?> Nilai &amp; pengumpul</a></div></div>
          <form method="POST" onsubmit="return confirm('Hapus tugas ini beserta pengumpulannya?')"><input type="hidden" name="aksi" value="hapus_tugas"><input type="hidden" name="tugas_id" value="<?= (int)$tg['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><?= svg_icon('trash',14) ?></button></form>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <?php if ($nilai_tugas > 0 && $info_tugas): ?><div class="card" style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px"><h3 style="font-size:16px">Penilaian: <?= e($info_tugas['judul_tugas']) ?></h3><a class="btn btn-ghost btn-sm" href="guru.php#daftar-tugas"><?= svg_icon('x',15) ?> Tutup</a></div>
        <p class="hint" style="margin-bottom:14px">Kelas <?= e($info_tugas['nama_kelas']) ?> • Beri nilai 0–100 dan umpan balik.</p>
        <?php if (empty($pengumpul)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada murid di kelas ini.</span></div>
        <?php else: ?><div class="table-wrap"><table class="data"><tr><th>Murid</th><th>File</th><th>Nilai</th></tr>
        <?php foreach ($pengumpul as $p): ?><tr>
          <td><strong><?= e($p['nama']) ?></strong><br><span style="color:var(--muted);font-size:12px">@<?= e($p['username']) ?></span></td>
          <td style="font-size:12.5px"><?php if ($p['pid']): ?><a href="<?= e($p['file_path']) ?>" target="_blank" rel="noopener" class="link-more"><?= svg_icon('file',14) ?> <?= e($p['nama_file']) ?></a><br><span style="color:var(--muted)"><?= e(tanggal_id($p['dikumpulkan_at'])) ?></span><?php else: ?><span class="pill wait">Belum kumpul</span><?php endif; ?></td>
          <td><?php if ($p['pid']): ?><form method="POST" action="guru.php#daftar-tugas" style="display:grid;gap:8px;min-width:200px"><input type="hidden" name="aksi" value="nilai_tugas"><input type="hidden" name="pengumpulan_id" value="<?= (int)$p['pid'] ?>">
            <div style="display:flex;gap:8px"><input type="number" name="nilai" class="form-control" min="0" max="100" placeholder="0–100" value="<?= $p['nilai']!==null ? (int)$p['nilai'] : '' ?>" style="max-width:110px"><button class="btn btn-yellow btn-sm" type="submit">Simpan</button></div>
            <input type="text" name="feedback" class="form-control" placeholder="Umpan balik (opsional)" value="<?= e($p['feedback'] ?? '') ?>"></form>
            <?php if ($p['nilai']!==null): ?><span class="pill ok" style="margin-top:6px">Nilai: <?= (int)$p['nilai'] ?></span><?php endif; ?>
          <?php else: ?><span style="color:var(--muted);font-size:12px">—</span><?php endif; ?></td>
        </tr><?php endforeach; ?></table></div><?php endif; ?>
      </div><?php endif; ?>
    </section>

    <!-- QUIZ -->
    <section class="section" id="menu-quiz">
      <div class="section-head"><div><span class="eyebrow">Evaluasi</span><h2>Quiziz</h2><p>Buat quiz, tambah soal pilihan ganda, pantau hasil.</p></div><a class="btn btn-ghost btn-sm" href="quiziz.php"><?= svg_icon('eye',15) ?> Pratinjau murid</a></div>
      <form action="guru.php#menu-quiz" method="POST" class="form-card">
        <input type="hidden" name="aksi" value="buat_quiz">
        <h3 style="font-size:15.5px;margin-bottom:12px">Buat quiz baru</h3>
        <div class="form-group"><label>Judul quiz</label><input type="text" name="judul_quiz" class="form-control" placeholder="cth: PBO — Class & Object" required></div>
        <div class="form-row"><div class="form-group"><label>Kelas</label><select name="kelas_id" class="form-control" required><option value="" disabled selected>-- Pilih kelas --</option><?php foreach ($list_kelas as $kls): ?><option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Durasi (menit)</label><input type="number" name="durasi" class="form-control" value="30" min="5" max="180"></div></div>
        <div class="form-group"><label>Deskripsi</label><textarea name="deskripsi_quiz" class="form-control" placeholder="Petunjuk pengerjaan quiz..."></textarea></div>
        <button class="btn btn-yellow btn-block" type="submit"><?= svg_icon('plus',16) ?> Buat Quiz</button>
      </form>
      <div class="task-list">
        <?php if (empty($list_quiz)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada quiz. Buat quiz pertama Anda di atas.</span></div>
        <?php else: foreach ($list_quiz as $qz): ?>
        <div class="task-card"><div class="task-icon dark"><?= svg_icon('help',20) ?></div>
          <div class="task-content"><h3><?= e($qz['judul']) ?> <span class="pill blue"><?= e($qz['kelas']) ?></span></h3>
          <p><?= (int)$qz['jml_soal'] ?> soal • <?= (int)$qz['jml_peserta'] ?> peserta • <?= (int)$qz['durasi_menit'] ?> mnt<?= $qz['rata']!==null ? ' • Rata-rata: <strong>'.(int)$qz['rata'].'</strong>' : '' ?></p>
          <div class="task-meta"><a class="link-more" href="?kelola_quiz=<?= (int)$qz['id'] ?>#menu-quiz"><?= svg_icon('edit',14) ?> Kelola soal &amp; hasil</a></div></div>
          <form method="POST" onsubmit="return confirm('Hapus quiz ini beserta soal dan hasilnya?')"><input type="hidden" name="aksi" value="hapus_quiz"><input type="hidden" name="quiz_id" value="<?= (int)$qz['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><?= svg_icon('trash',14) ?></button></form>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <?php if ($kelola_quiz > 0 && $info_quiz): ?><div class="card" style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h3 style="font-size:16px">Kelola: <?= e($info_quiz['judul']) ?></h3><a class="btn btn-ghost btn-sm" href="guru.php#menu-quiz"><?= svg_icon('x',15) ?> Tutup</a></div>
        <p class="hint" style="margin-bottom:14px">Kelas <?= e($info_quiz['nama_kelas']) ?> • <?= (int)$info_quiz['durasi_menit'] ?> menit • <?= count($soal_list) ?> soal</p>
        <h4 style="font-size:14px;margin-bottom:10px">Tambah soal pilihan ganda</h4>
        <form method="POST" action="guru.php#menu-quiz" class="form-card" style="background:var(--bg-soft)">
          <input type="hidden" name="aksi" value="tambah_soal"><input type="hidden" name="quiz_id" value="<?= (int)$kelola_quiz ?>">
          <div class="form-group"><label>Pertanyaan</label><textarea name="pertanyaan" class="form-control" required placeholder="Tulis pertanyaan..."></textarea></div>
          <div class="form-row"><div class="form-group"><label>Opsi A</label><input name="opsi_a" class="form-control" required></div><div class="form-group"><label>Opsi B</label><input name="opsi_b" class="form-control" required></div></div>
          <div class="form-row"><div class="form-group"><label>Opsi C</label><input name="opsi_c" class="form-control" required></div><div class="form-group"><label>Opsi D</label><input name="opsi_d" class="form-control" required></div></div>
          <div class="form-group"><label>Kunci jawaban</label><select name="kunci" class="form-control" style="max-width:200px"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
          <button class="btn btn-dark" type="submit"><?= svg_icon('plus',15) ?> Tambah Soal</button>
        </form>
        <h4 style="font-size:14px;margin:14px 0 10px">Daftar soal (<?= count($soal_list) ?>)</h4>
        <?php if (empty($soal_list)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada soal.</span></div>
        <?php else: foreach ($soal_list as $i => $so): ?><div class="soal"><div style="display:flex;justify-content:space-between;gap:10px"><strong style="font-size:13.5px"><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?></strong>
          <form method="POST" onsubmit="return confirm('Hapus soal ini?')"><input type="hidden" name="aksi" value="hapus_soal"><input type="hidden" name="soal_id" value="<?= (int)$so['id'] ?>"><button class="btn btn-danger btn-sm" type="submit"><?= svg_icon('trash',14) ?></button></form></div>
          <div style="font-size:12.5px;color:var(--muted);margin-top:8px">A. <?= e($so['opsi_a']) ?> • B. <?= e($so['opsi_b']) ?> • C. <?= e($so['opsi_c']) ?> • D. <?= e($so['opsi_d']) ?> • <strong style="color:var(--green-tx)">Kunci: <?= e($so['kunci']) ?></strong></div></div>
        <?php endforeach; endif; ?>
        <h4 style="font-size:14px;margin:16px 0 10px">Hasil peserta (<?= count($hasil_list) ?>)</h4>
        <?php if (empty($hasil_list)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada murid mengerjakan.</span></div>
        <?php else: ?><div class="table-wrap"><table class="data"><tr><th>Murid</th><th>Benar</th><th>Skor</th><th>Waktu</th></tr>
        <?php foreach ($hasil_list as $h): ?><tr><td><strong><?= e($h['nama']) ?></strong><br><span style="font-size:12px;color:var(--muted)">@<?= e($h['username']) ?></span></td><td><?= (int)$h['benar'] ?>/<?= (int)$h['total'] ?></td><td><span class="pill <?= (int)$h['skor']>=75?'ok':((int)$h['skor']>=60?'wait':'red') ?>"><?= (int)$h['skor'] ?></span></td><td style="font-size:12px"><?= e(tanggal_id($h['dikerjakan_at'])) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
      </div><?php endif; ?>
    </section>
  </div>

  <aside><div class="sidebar-widget dark"><h3>Profil Pengajar</h3><p class="subtitle">Data akun dari database</p>
      <div class="profile-detail"><div><span class="label">Nama lengkap</span><span class="value"><?= e($user['nama']) ?></span></div>
      <div><span class="label">Username</span><span class="value">@<?= e($user['username']) ?></span></div>
      <div><span class="label">Email</span><span class="value"><?= e($user['email']) ?></span></div>
      <div><span class="label">Peran</span><span class="value"><?= e(ucfirst($user['jenis'])) ?> • <?= e($user['level'] ?? 'user') ?></span></div></div>
      <div style="display:grid;gap:8px;margin-top:14px"><a class="btn btn-yellow btn-sm" href="quiziz.php"><?= svg_icon('help',15) ?> Buka Quiziz</a><a class="btn btn-ghost btn-sm" style="background:#fff" href="cek-db.php"><?= svg_icon('activity',15) ?> Status Sistem</a></div></div>
    <div class="sidebar-widget"><h3>Panduan cepat</h3><p class="subtitle">Alur kerja yang disarankan</p>
      <div style="display:grid;gap:10px;font-size:13px;color:var(--muted)">
        <div style="display:flex;gap:10px"><span class="activity-ic"><?= svg_icon('book',17) ?></span><span><strong style="color:var(--text)">1. Buat kelas</strong><br>Bagikan kode ke murid.</span></div>
        <div style="display:flex;gap:10px"><span class="activity-ic"><?= svg_icon('calendar',17) ?></span><span><strong style="color:var(--text)">2. Buka absensi</strong><br>Murid mengisi kehadiran.</span></div>
        <div style="display:flex;gap:10px"><span class="activity-ic"><?= svg_icon('clipboard',17) ?></span><span><strong style="color:var(--text)">3. Publikasikan tugas</strong><br>Nilai pengumpulan yang masuk.</span></div>
        <div style="display:flex;gap:10px"><span class="activity-ic"><?= svg_icon('help',17) ?></span><span><strong style="color:var(--text)">4. Buat quiz + soal</strong><br>Nilai keluar otomatis.</span></div>
      </div></div>
  </aside></div>
</div></main>

<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — SMK Negeri 7 Batam • Dashboard Guru</span><span><a href="murid.php">Pratinjau murid</a> • <a href="logout.php">Keluar</a></span></div></div></footer>
<script>
(function(){var b=document.getElementById('burger'),l=document.getElementById('navLinks'),r=document.getElementById('navRight');if(b){b.addEventListener('click',function(){var o=l.classList.toggle('open');if(r)r.classList.toggle('open',o);b.setAttribute('aria-expanded',o?'true':'false');});}})();
</script>
</body>
</html>

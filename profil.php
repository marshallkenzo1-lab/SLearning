<?php
// SLearning — Profil pengguna (edit nama/email + ganti password)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];
$s = mysqli_prepare($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
if (!$user) { header("Location: logout.php"); exit; }
$jenis = $user['jenis']; $dashboard = $jenis==='guru' ? 'guru.php' : 'murid.php';

$msg=''; $type='success';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi']) && $_POST['aksi']==='update_profil') {
    $nama = trim($_POST['nama'] ?? ''); $email = trim($_POST['email'] ?? '');
    if ($nama==='' || $email==='') { $msg='Nama dan email wajib diisi.'; $type='error'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $msg='Format email tidak valid.'; $type='error'; }
    else {
        $s = mysqli_prepare($koneksi, "SELECT id FROM login WHERE email=? AND id<>? LIMIT 1");
        mysqli_stmt_bind_param($s,'si',$email,$uid); mysqli_stmt_execute($s);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($s))) { $msg='Email sudah dipakai akun lain.'; $type='error'; }
        else {
            $s2 = mysqli_prepare($koneksi, "UPDATE login SET nama=?, email=? WHERE id=?");
            mysqli_stmt_bind_param($s2,'ssi',$nama,$email,$uid);
            if (mysqli_stmt_execute($s2)) { $msg='Profil diperbarui.'; $user['nama']=$nama; $user['email']=$email; $_SESSION['nama']=$nama; $_SESSION['email']=$email; }
            else { $msg='Gagal memperbarui profil.'; $type='error'; }
            mysqli_stmt_close($s2);
        }
        mysqli_stmt_close($s);
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi']) && $_POST['aksi']==='ganti_password') {
    $lama = $_POST['password_lama'] ?? ''; $baru = $_POST['password_baru'] ?? ''; $konf = $_POST['password_konf'] ?? '';
    $s = mysqli_prepare($koneksi, "SELECT password FROM login WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if (!$row || !password_verify($lama, $row['password'])) { $msg='Password lama salah.'; $type='error'; }
    elseif (strlen($baru)<8) { $msg='Password baru minimal 8 karakter.'; $type='error'; }
    elseif ($baru!==$konf) { $msg='Konfirmasi password tidak cocok.'; $type='error'; }
    else {
        $hash = password_hash($baru, PASSWORD_DEFAULT);
        $s = mysqli_prepare($koneksi, "UPDATE login SET password=? WHERE id=?");
        mysqli_stmt_bind_param($s,'si',$hash,$uid);
        if (mysqli_stmt_execute($s)) { $msg='Password berhasil diganti.'; $type='success'; }
        else { $msg='Gagal mengganti password.'; $type='error'; }
        mysqli_stmt_close($s);
    }
}

// ringkasan
$ringkas = [];
if ($jenis==='guru') {
    $q = mysqli_query($koneksi, "SELECT (SELECT COUNT(*) FROM kelas WHERE guru_id=$uid) AS kelas, (SELECT COUNT(*) FROM tugas WHERE guru_id=$uid) AS tugas, (SELECT COUNT(*) FROM quiz WHERE guru_id=$uid) AS quiz, (SELECT COUNT(*) FROM absensi WHERE guru_id=$uid) AS absen");
    $ringkas = $q ? mysqli_fetch_assoc($q) : [];
} else {
    $q = mysqli_query($koneksi, "SELECT (SELECT COUNT(*) FROM anggota_kelas WHERE user_id=$uid) AS kelas, (SELECT COUNT(*) FROM pengumpulan_tugas WHERE user_id=$uid) AS kumpul, (SELECT COUNT(*) FROM quiz_hasil WHERE user_id=$uid) AS quiz, (SELECT COUNT(*) FROM absensi_hadir WHERE user_id=$uid) AS absen");
    $ringkas = $q ? mysqli_fetch_assoc($q) : [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya — SLearning</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="<?= e($dashboard) ?>" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo"><span class="brand-text"><strong>SLearning</strong><span>Profil • SMKN 7</span></span></a>
  <div class="nav-right" id="navRight"><a href="<?= e($dashboard) ?>" class="btn btn-ghost btn-sm"><?= svg_icon('arrow-left',15) ?> Dashboard</a><a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin keluar?')"><?= svg_icon('logout',15) ?> Keluar</a></div>
  <button class="burger" id="burger" aria-label="Buka menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>
<main class="page page-grid-bg"><div class="container page-inner" style="max-width:900px">
  <section class="welcome"><div style="display:flex;gap:16px;align-items:center"><div class="avatar" style="width:64px;height:64px;font-size:22px"><?= e(inisial_nama($user['nama'])) ?></div>
    <div><small>Profil <?= e(ucfirst($jenis)) ?></small><h1 style="font-size:28px"><?= e($user['nama']) ?></h1><p>@<?= e($user['username']) ?> • <?= e($user['email']) ?> • <?= e($user['level'] ?? 'user') ?></p></div></div></section>
  <?php if($msg!==''): ?><div class="alert <?= $type==='error'?'error':'success' ?>"><?= svg_icon($type==='error'?'info':'check-circle',18) ?><span><?= e($msg) ?></span></div><?php endif; ?>
  <div class="stats">
    <?php if($jenis==='guru'): ?>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('book',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['kelas']??0) ?></div><div class="stat-label">Kelas</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('clipboard',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['tugas']??0) ?></div><div class="stat-label">Tugas</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('help',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['quiz']??0) ?></div><div class="stat-label">Quiz</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('calendar',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['absen']??0) ?></div><div class="stat-label">Absensi</div></div></div>
    <?php else: ?>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('book',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['kelas']??0) ?></div><div class="stat-label">Kelas Diikuti</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('clipboard',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['kumpul']??0) ?></div><div class="stat-label">Tugas Dikumpul</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('help',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['quiz']??0) ?></div><div class="stat-label">Quiz Dikerjakan</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('calendar',20) ?></div><div><div class="stat-number"><?= (int)($ringkas['absen']??0) ?></div><div class="stat-label">Absensi Terisi</div></div></div>
    <?php endif; ?>
  </div>
  <div class="main-grid" style="grid-template-columns:1fr 1fr">
    <form method="POST" class="form-card"><input type="hidden" name="aksi" value="update_profil">
      <h3 style="font-size:17px;margin-bottom:4px">Data diri</h3><p class="hint" style="margin-bottom:14px">Username tidak dapat diubah. Hubungi admin bila perlu.</p>
      <div class="form-group"><label>Username</label><input class="form-control" value="@<?= e($user['username']) ?>" disabled></div>
      <div class="form-group"><label for="nama">Nama lengkap</label><input id="nama" name="nama" class="form-control" value="<?= e($user['nama']) ?>" required></div>
      <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
      <button class="btn btn-yellow btn-block" type="submit"><?= svg_icon('check',16) ?> Simpan Perubahan</button></form>
    <form method="POST" class="form-card"><input type="hidden" name="aksi" value="ganti_password">
      <h3 style="font-size:17px;margin-bottom:4px">Ganti password</h3><p class="hint" style="margin-bottom:14px">Minimal 8 karakter. Jangan bagikan ke siapa pun.</p>
      <div class="form-group"><label for="pl">Password lama</label><input id="pl" name="password_lama" type="password" class="form-control" required autocomplete="current-password"></div>
      <div class="form-group"><label for="pb">Password baru</label><input id="pb" name="password_baru" type="password" class="form-control" required autocomplete="new-password"></div>
      <div class="form-group"><label for="pk">Konfirmasi password baru</label><input id="pk" name="password_konf" type="password" class="form-control" required autocomplete="new-password"></div>
      <button class="btn btn-dark btn-block" type="submit"><?= svg_icon('lock',16) ?> Ganti Password</button></form>
  </div>
</div></main>
<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — Profil</span><span><a href="<?= e($dashboard) ?>">Dashboard</a></span></div></div></footer>
<script>(function(){var b=document.getElementById('burger'),r=document.getElementById('navRight');if(b&&r){b.addEventListener('click',function(){r.classList.toggle('open');});}})();</script>
</body>
</html>

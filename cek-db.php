<?php
// SLearning — Status Sistem (diagnostik, tanpa data sensitif)
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

$tables = ['login','kelas','anggota_kelas','tugas','pengumpulan_tugas','absensi','absensi_hadir','quiz','quiz_soal','quiz_hasil'];
$info = [];
foreach ($tables as $t) {
    $q = @mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM `$t`");
    $info[$t] = $q ? (int)mysqli_fetch_assoc($q)['c'] : null;
}
$users = @mysqli_query($koneksi, "SELECT jenis, COUNT(*) AS c FROM login GROUP BY jenis");
$by_jenis = ['guru'=>0,'murid'=>0];
while ($users && $r=mysqli_fetch_assoc($users)) $by_jenis[$r['jenis']]=(int)$r['c'];
$db_ok = $koneksi ? true : false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Sistem — SLearning</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="index.php" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo"><span class="brand-text"><strong>SLearning</strong><span>Status Sistem</span></span></a>
  <div class="nav-right" id="navRight"><a href="index.php" class="btn btn-ghost btn-sm"><?= svg_icon('home',15) ?> Beranda</a><a href="login.php" class="btn btn-yellow btn-sm"><?= svg_icon('login',15) ?> Masuk</a></div>
  <button class="burger" id="burger" aria-label="Buka menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>
<main class="page page-grid-bg"><div class="container page-inner" style="max-width:900px">
  <span class="eyebrow">Diagnostik</span>
  <h1 style="font-size:30px;margin-bottom:6px">Status Sistem SLearning</h1>
  <p class="hint" style="margin-bottom:20px">Halaman ini hanya menampilkan jumlah baris per tabel, tanpa data pribadi.</p>
  <div class="stats" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('shield',20) ?></div><div><div class="stat-number" style="font-size:18px"><?= $db_ok ? 'Online' : 'Gagal' ?></div><div class="stat-label">Database</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('users',20) ?></div><div><div class="stat-number"><?= array_sum($by_jenis) ?></div><div class="stat-label">Total Akun</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('user',20) ?></div><div><div class="stat-number"><?= (int)$by_jenis['guru'] ?></div><div class="stat-label">Guru</div></div></div>
    <div class="stat-card"><div class="stat-icon"><?= svg_icon('book',20) ?></div><div><div class="stat-number"><?= (int)$by_jenis['murid'] ?></div><div class="stat-label">Murid</div></div></div>
  </div>
  <div class="card">
    <h3 style="font-size:17px;margin-bottom:12px">Tabel database (slearning_db)</h3>
    <div class="table-wrap"><table class="data"><tr><th>Tabel</th><th>Fungsi</th><th>Jumlah baris</th><th>Status</th></tr>
    <?php
    $desc = ['login'=>'Akun guru & murid','kelas'=>'Ruang kelas','anggota_kelas'=>'Keanggotaan kelas','tugas'=>'Tugas dari guru','pengumpulan_tugas'=>'File kumpul + nilai','absensi'=>'Sesi absensi','absensi_hadir'=>'Kehadiran murid','quiz'=>'Quiz dari guru','quiz_soal'=>'Soal pilihan ganda','quiz_hasil'=>'Hasil & skor murid'];
    foreach ($tables as $t): $c = $info[$t]; ?>
      <tr><td><strong><?= e($t) ?></strong></td><td><?= e($desc[$t]) ?></td><td><?= $c===null?'—':(int)$c ?></td><td><?php if($c===null): ?><span class="pill red">Hilang</span><?php else: ?><span class="pill ok">Siap</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </table></div>
    <div class="alert info" style="margin-top:14px;margin-bottom:0"><?= svg_icon('info',18) ?><span>PHP <?= e(PHP_VERSION) ?> • MySQL via mysqli • Seluruh tabel dibuat otomatis oleh <strong>config/koneksi.php</strong> bila belum ada.</span></div>
  </div>
  <div class="card" style="margin-top:14px"><h3 style="font-size:16px;margin-bottom:8px">Akun demo di server ini</h3>
    <p class="hint">Gunakan akun berikut bila baru pertama kali mencoba (password sesuai yang didaftarkan; bila lupa, daftar akun baru di halaman login):</p>
    <div class="table-wrap" style="margin-top:10px"><table class="data"><tr><th>Username</th><th>Peran</th><th>Aksi</th></tr>
    <?php $q=@mysqli_query($koneksi,"SELECT username,jenis FROM login ORDER BY id ASC LIMIT 10"); while($q&&$r=mysqli_fetch_assoc($q)): ?>
      <tr><td>@<?= e($r['username']) ?></td><td><span class="pill <?= $r['jenis']==='guru'?'blue':'wait' ?>"><?= e($r['jenis']) ?></span></td><td><a class="link-more" href="login.php">Masuk <?= svg_icon('arrow-right',13) ?></a></td></tr>
    <?php endwhile; ?></table></div>
  </div>
</div></main>
<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — Status Sistem</span><span><a href="index.php">Beranda</a></span></div></div></footer>
<script>(function(){var b=document.getElementById('burger'),r=document.getElementById('navRight');if(b&&r){b.addEventListener('click',function(){r.classList.toggle('open');});}})();</script>
</body>
</html>

<?php
// SLearning — Ruang Kelas (murid): tugas + quiz + absensi dalam satu kelas
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['jenis'] ?? '') === 'guru')) { header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];
$nama_murid = $_SESSION['nama'] ?? 'Murid';
$kelas_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($kelas_id <= 0) { header("Location: murid.php"); exit; }

$s = mysqli_prepare($koneksi, "SELECT k.id, k.nama_kelas, k.kode_gabung, l.nama AS nama_guru FROM anggota_kelas ak INNER JOIN kelas k ON k.id=ak.kelas_id LEFT JOIN login l ON l.id=k.guru_id WHERE ak.kelas_id=? AND ak.user_id=? LIMIT 1");
mysqli_stmt_bind_param($s,'ii',$kelas_id,$uid); mysqli_stmt_execute($s);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
if (!$kelas) { header("Location: murid.php"); exit; }

$pesan=''; $tipe='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['kumpulkan_tugas'])) {
    $tid=(int)($_POST['tugas_id']??0);
    $s=mysqli_prepare($koneksi,"SELECT id FROM tugas WHERE id=? AND kelas_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$tid,$kelas_id); mysqli_stmt_execute($s);
    $valid=mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($valid && isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error']===UPLOAD_ERR_OK) {
        $f=$_FILES['file_tugas']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        $allow=['pdf','doc','docx','ppt','pptx','zip','rar','jpg','jpeg','png'];
        if ($f['size']<=10*1024*1024 && in_array($ext,$allow,true)) {
            $dir=__DIR__.'/uploads/tugas/'; if(!is_dir($dir)) mkdir($dir,0777,true);
            $baru='tugas_'.$uid.'_'.$tid.'_'.time().'.'.$ext; $lok=$dir.$baru; $dbp='uploads/tugas/'.$baru;
            if (move_uploaded_file($f['tmp_name'],$lok)) {
                $s=mysqli_prepare($koneksi,"SELECT id,file_path FROM pengumpulan_tugas WHERE tugas_id=? AND user_id=? LIMIT 1");
                mysqli_stmt_bind_param($s,'ii',$tid,$uid); mysqli_stmt_execute($s);
                $lama=mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
                if ($lama) {
                    if (is_file(__DIR__.'/'.$lama['file_path'])) @unlink(__DIR__.'/'.$lama['file_path']);
                    $s=mysqli_prepare($koneksi,"UPDATE pengumpulan_tugas SET nama_file=?,file_path=?,nilai=NULL,feedback=NULL,dikumpulkan_at=CURRENT_TIMESTAMP WHERE id=?");
                    mysqli_stmt_bind_param($s,'ssi',$f['name'],$dbp,$lama['id']);
                } else {
                    $s=mysqli_prepare($koneksi,"INSERT INTO pengumpulan_tugas (tugas_id,user_id,nama_file,file_path) VALUES (?,?,?,?)");
                    mysqli_stmt_bind_param($s,'iiss',$tid,$uid,$f['name'],$dbp);
                }
                if (mysqli_stmt_execute($s)) { $pesan='Tugas berhasil dikumpulkan.'; $tipe='success'; }
                else { $pesan='Gagal menyimpan ke database.'; $tipe='error'; if(is_file($lok))@unlink($lok); }
                mysqli_stmt_close($s);
            } else { $pesan='Gagal mengunggah file.'; $tipe='error'; }
        } else { $pesan='File maksimal 10 MB dengan format PDF/DOC/PPT/ZIP/gambar.'; $tipe='error'; }
    } else { $pesan='Pilih file tugas yang valid terlebih dahulu.'; $tipe='error'; }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['isi_absen'])) {
    $aid=(int)($_POST['absensi_id']??0); $st=$_POST['status']??'hadir';
    if(!in_array($st,['hadir','izin','sakit','alpa'],true))$st='hadir';
    $ket=trim($_POST['keterangan']??'');
    $s=mysqli_prepare($koneksi,"SELECT id FROM absensi WHERE id=? AND kelas_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$aid,$kelas_id); mysqli_stmt_execute($s);
    if(mysqli_fetch_assoc(mysqli_stmt_get_result($s))){
        $s2=mysqli_prepare($koneksi,"INSERT INTO absensi_hadir (absensi_id,user_id,status,keterangan) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),keterangan=VALUES(keterangan),waktu=CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($s2,'iiss',$aid,$uid,$st,$ket);
        if(mysqli_stmt_execute($s2)){$pesan='Kehadiran tersimpan.';$tipe='success';} else {$pesan='Gagal menyimpan kehadiran.';$tipe='error';}
        mysqli_stmt_close($s2);
    }
    mysqli_stmt_close($s);
}

$tugas=[]; $s=mysqli_prepare($koneksi,"SELECT t.id,t.judul_tugas AS judul,t.deadline,t.deskripsi,pt.nama_file,pt.dikumpulkan_at,pt.nilai,pt.feedback FROM tugas t LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=? WHERE t.kelas_id=? ORDER BY t.id DESC");
mysqli_stmt_bind_param($s,'ii',$uid,$kelas_id); mysqli_stmt_execute($s);
$rr=mysqli_stmt_get_result($s); while($r=mysqli_fetch_assoc($rr))$tugas[$r['id']]=$r; mysqli_stmt_close($s);

$quiz=[]; $q=mysqli_query($koneksi,"SELECT q.id,q.judul,q.deskripsi,q.durasi_menit,(SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jml,qh.skor,qh.dikerjakan_at FROM quiz q LEFT JOIN quiz_hasil qh ON qh.quiz_id=q.id AND qh.user_id=$uid WHERE q.kelas_id=$kelas_id ORDER BY q.id DESC");
while($q&&$r=mysqli_fetch_assoc($q))$quiz[]=$r;

$absen=[]; $q=mysqli_query($koneksi,"SELECT a.id,a.judul_absensi,a.tanggal,a.jam_mulai,a.jam_selesai,ah.status,ah.waktu FROM absensi a LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=$uid WHERE a.kelas_id=$kelas_id ORDER BY a.tanggal DESC,a.id DESC");
while($q&&$r=mysqli_fetch_assoc($q))$absen[]=$r;

$tugas_dibuka=isset($_GET['tugas'])?(int)$_GET['tugas']:0;
if($tugas_dibuka&&!isset($tugas[$tugas_dibuka]))$tugas_dibuka=0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($kelas['nama_kelas']) ?> — SLearning</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="murid.php" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo"><span class="brand-text"><strong>SLearning</strong><span>Ruang Kelas • SMKN 7</span></span></a>
  <div class="nav-right" id="navRight"><span class="pill muted"><?= svg_icon('key',13) ?> <?= e($kelas['kode_gabung']) ?></span><a href="murid.php" class="btn btn-ghost btn-sm"><?= svg_icon('arrow-left',15) ?> Dashboard</a></div>
  <button class="burger" id="burger" aria-label="Buka menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>
<main class="page page-grid-bg"><div class="container page-inner">
  <section class="welcome"><div><small>Ruang Kelas Aktif</small><h1><?= e($kelas['nama_kelas']) ?></h1><p>Pengajar: <strong style="color:#fff"><?= e($kelas['nama_guru'] ?? 'Guru Pengampu') ?></strong> • <?= count($tugas) ?> tugas • <?= count($quiz) ?> quiz • <?= count($absen) ?> sesi absensi</p></div>
  <div style="position:relative;z-index:2;display:flex;gap:10px;flex-wrap:wrap"><a href="#tugas" class="btn btn-yellow"><?= svg_icon('clipboard',16) ?> Tugas</a><a href="#quiz" class="btn btn-ghost" style="background:#fff"><?= svg_icon('help',16) ?> Quiziz</a></div></section>

  <?php if($pesan!==''): ?><div class="alert <?= $tipe==='success'?'success':'error' ?>"><?= svg_icon($tipe==='success'?'check-circle':'info',18) ?><span><?= e($pesan) ?></span></div><?php endif; ?>

  <div class="main-grid"><div>
    <section class="section" id="tugas"><div class="section-head"><div><span class="eyebrow">Penugasan</span><h2>Daftar Tugas Kelas</h2></div></div>
      <?php if(empty($tugas)): ?><div class="empty"><?= svg_icon('clipboard',18) ?><span>Belum ada tugas dari guru di kelas ini.</span></div>
      <?php else: ?><div class="task-list"><?php foreach($tugas as $id_t=>$t): $done=!empty($t['nama_file']); ?>
        <a href="?id=<?= $kelas_id ?>&tugas=<?= (int)$id_t ?>#pengumpulan" style="display:block"><div class="task-card"><div class="task-icon"><?= svg_icon('file',20) ?></div>
        <div class="task-content"><h3><?= e($t['judul']) ?></h3><p>Deadline: <?= $t['deadline']?e(tanggal_id($t['deadline'])):'tanpa batas' ?><?php if($t['nilai']!==null) echo ' • Nilai: <strong>'.(int)$t['nilai'].'</strong>'; ?></p></div>
        <span class="pill <?= $done?'ok':'wait' ?>"><?= $done?'Sudah Kumpul':'Belum Kumpul' ?></span></div></a>
      <?php endforeach; ?></div><?php endif; ?>
      <?php if($tugas_dibuka&&isset($tugas[$tugas_dibuka])): $td=$tugas[$tugas_dibuka]; ?>
      <div class="card" id="pengumpulan" style="margin-top:14px;border-color:var(--yellow-border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h3 style="font-size:17px"><?= e($td['judul']) ?></h3><a href="kelas.php?id=<?= $kelas_id ?>" class="btn btn-ghost btn-sm"><?= svg_icon('x',15) ?> Tutup</a></div>
        <?php if(!empty($td['deskripsi'])): ?><p style="font-size:13.5px;background:var(--bg-soft);padding:13px;border-radius:11px;margin-bottom:12px"><?= nl2br(e($td['deskripsi'])) ?></p><?php endif; ?>
        <?php if(!empty($td['nama_file'])): ?><div class="alert success"><?= svg_icon('check-circle',18) ?><span>Terkumpul: <strong><?= e($td['nama_file']) ?></strong> (<?= e(tanggal_id($td['dikumpulkan_at'])) ?>)<?php if($td['nilai']!==null) echo ' • Nilai <strong>'.(int)$td['nilai'].'</strong>'; ?><?php if(!empty($td['feedback'])) echo '<br>Umpan balik: '.e($td['feedback']); ?></span></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data"><input type="hidden" name="tugas_id" value="<?= (int)$tugas_dibuka ?>">
          <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Upload file tugas</label>
          <input type="file" name="file_tugas" required class="form-control" style="margin-bottom:10px"><button type="submit" name="kumpulkan_tugas" class="btn btn-yellow"><?= svg_icon('upload',16) ?> Kirim Tugas</button>
          <p class="hint" style="margin-top:8px">Maksimal 10 MB • PDF, DOC, PPT, ZIP, RAR, gambar.</p></form>
      </div><?php endif; ?>
    </section>

    <section class="section" id="quiz"><div class="section-head"><div><span class="eyebrow">Evaluasi</span><h2>Quiz Kelas Ini</h2></div></div>
      <?php if(empty($quiz)): ?><div class="empty"><?= svg_icon('help',18) ?><span>Belum ada quiz di kelas ini.</span></div>
      <?php else: ?><div class="quiz-grid"><?php foreach($quiz as $z): $done=!empty($z['dikerjakan_at']); ?>
        <article class="quiz-card"><div class="quiz-top"><span class="quiz-label"><?= (int)$z['jml'] ?> SOAL</span><span class="quiz-time"><?= svg_icon('clock',13) ?> <?= (int)$z['durasi_menit'] ?> mnt</span></div>
        <h3><?= e($z['judul']) ?></h3><p><?= $z['deskripsi']?e(mb_strimwidth($z['deskripsi'],0,80,'...')):'Kerjakan dengan teliti sebelum waktu habis.' ?></p>
        <div class="quiz-bottom"><span><?= $done?'Skor <strong>'.(int)$z['skor'].'</strong>':'Belum dikerjakan' ?></span><a class="link-more" href="quiziz.php?id=<?= (int)$z['id'] ?>"><?= $done?'Lihat hasil':'Kerjakan' ?> <?= svg_icon('arrow-right',14) ?></a></div></article>
      <?php endforeach; ?></div><?php endif; ?>
    </section>
  </div>
  <aside>
    <section class="sidebar-widget" id="absensi"><h3>Absensi Kelas</h3><p class="subtitle">Sesi yang dibuka guru</p>
      <?php if(empty($absen)): ?><div class="empty"><?= svg_icon('calendar',18) ?><span>Belum ada sesi absensi.</span></div>
      <?php else: ?><div style="display:grid;gap:10px"><?php foreach($absen as $ab): ?>
        <div class="task-card" style="padding:14px"><div class="task-icon" style="width:40px;height:40px;min-width:40px"><?= svg_icon('calendar',18) ?></div>
        <div class="task-content"><h3 style="font-size:13px"><?= e($ab['judul_absensi']) ?></h3><p><?= e(tanggal_id($ab['tanggal'],false)) ?></p>
        <?php if($ab['status']): ?><span class="pill <?= $ab['status']==='hadir'?'ok':'wait' ?>" style="margin-top:6px"><?= e(ucfirst($ab['status'])) ?></span>
        <?php else: ?><form method="POST" style="display:flex;gap:6px;margin-top:8px"><input type="hidden" name="absensi_id" value="<?= (int)$ab['id'] ?>"><select name="status" class="form-control" style="padding:7px 9px;font-size:12px"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select><button name="isi_absen" class="btn btn-yellow btn-sm" type="submit"><?= svg_icon('check',13) ?></button></form><?php endif; ?></div></div>
      <?php endforeach; ?></div><?php endif; ?></section>
    <div class="sidebar-widget dark"><h3>Info Kelas</h3><p class="subtitle">Bagikan kode ke teman sekelas</p>
      <div class="code-box" style="background:#1c1c1f;border-color:#333;color:#fff" onclick="navigator.clipboard.writeText('<?= e($kelas['kode_gabung']) ?>');alert('Kode disalin.');"><?= svg_icon('copy',15) ?> <?= e($kelas['kode_gabung']) ?></div>
      <a href="murid.php#kelas" class="btn btn-yellow btn-sm btn-block" style="margin-top:12px"><?= svg_icon('arrow-left',14) ?> Semua Kelasku</a></div>
  </aside></div>
</div></main>
<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — <?= e($kelas['nama_kelas']) ?></span><span><a href="murid.php">Dashboard</a> • <a href="quiziz.php">Quiziz</a></span></div></div></footer>
<script>(function(){var b=document.getElementById('burger'),r=document.getElementById('navRight');if(b&&r){b.addEventListener('click',function(){r.classList.toggle('open');});}})();</script>
</body>
</html>

<?php
// SLearning — Quiziz fungsional (guru kelola, murid kerjakan + timer + nilai otomatis)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$uid = (int)$_SESSION['user_id'];
$s = mysqli_prepare($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($s,"i",$uid); mysqli_stmt_execute($s);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
if (!$user) { header("Location: logout.php"); exit; }
$jenis = $user['jenis']; $nama = $user['nama'];
$dashboard = ($jenis==='guru') ? 'guru.php' : 'murid.php';

// kolom jawaban untuk review (tambah bila belum ada)
$__c = @mysqli_query($koneksi, "SHOW COLUMNS FROM quiz_hasil LIKE 'jawaban'");
if ($__c && mysqli_num_rows($__c)===0) @mysqli_query($koneksi, "ALTER TABLE quiz_hasil ADD COLUMN jawaban TEXT NULL");

$qid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lihat_hasil = isset($_GET['hasil']);
$flash=''; $flash_t='';

// --- Submit jawaban murid ---
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['kerjakan']) && $qid>0 && $jenis==='murid') {
    $s = mysqli_prepare($koneksi, "SELECT q.id, q.kelas_id FROM quiz q JOIN anggota_kelas ak ON ak.kelas_id=q.kelas_id WHERE q.id=? AND ak.user_id=? LIMIT 1");
    mysqli_stmt_bind_param($s,'ii',$qid,$uid); mysqli_stmt_execute($s);
    $akses = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    if ($akses) {
        $s = mysqli_prepare($koneksi, "SELECT id, kunci FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($s,'i',$qid); mysqli_stmt_execute($s);
        $rr = mysqli_stmt_get_result($s); $soal=[]; while($r=mysqli_fetch_assoc($rr))$soal[]=$r; mysqli_stmt_close($s);
        $total = count($soal); $benar = 0; $jw = [];
        foreach ($soal as $so) {
            $pil = strtoupper(trim($_POST['jwb_'.$so['id']] ?? ''));
            if (!in_array($pil,['A','B','C','D'],true)) $pil='-';
            $jw[$so['id']]=$pil;
            if ($pil===$so['kunci']) $benar++;
        }
        $skor = $total>0 ? (int)round($benar/$total*100) : 0;
        $jw_json = json_encode($jw);
        $s = mysqli_prepare($koneksi, "INSERT INTO quiz_hasil (quiz_id,user_id,benar,total,skor,jawaban) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE benar=VALUES(benar),total=VALUES(total),skor=VALUES(skor),jawaban=VALUES(jawaban),dikerjakan_at=CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($s,'iiiiss',$qid,$uid,$benar,$total,$skor,$jw_json);
        if (mysqli_stmt_execute($s)) { header("Location: quiziz.php?id=$qid&hasil=1&baru=1"); exit; }
        else { $flash='Gagal menyimpan hasil.'; $flash_t='error'; }
        mysqli_stmt_close($s);
    } else { $flash='Akses quiz ditolak.'; $flash_t='error'; }
}

// --- Mode kerjakan / hasil ---
$quiz=null; $soal_list=[]; $hasil=null;
if ($qid>0) {
    if ($jenis==='guru') {
        $s=mysqli_prepare($koneksi,"SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.id=? AND q.guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($s,'ii',$qid,$uid); mysqli_stmt_execute($s);
        $quiz=mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    } else {
        $s=mysqli_prepare($koneksi,"SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id JOIN anggota_kelas ak ON ak.kelas_id=q.kelas_id WHERE q.id=? AND ak.user_id=? LIMIT 1");
        mysqli_stmt_bind_param($s,'ii',$qid,$uid); mysqli_stmt_execute($s);
        $quiz=mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
    }
    if ($quiz) {
        $s=mysqli_prepare($koneksi,"SELECT * FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($s,'i',$qid); mysqli_stmt_execute($s);
        $rr=mysqli_stmt_get_result($s); while($r=mysqli_fetch_assoc($rr))$soal_list[]=$r; mysqli_stmt_close($s);
        $s=mysqli_prepare($koneksi,"SELECT * FROM quiz_hasil WHERE quiz_id=? AND user_id=? LIMIT 1");
        mysqli_stmt_bind_param($s,'ii',$qid,$uid); mysqli_stmt_execute($s);
        $hasil=mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
        if ($jenis==='guru') { // guru lihat rekap peserta
            $peserta=[]; $qq=mysqli_query($koneksi,"SELECT l.nama,l.username,qh.benar,qh.total,qh.skor,qh.dikerjakan_at FROM quiz_hasil qh JOIN login l ON l.id=qh.user_id WHERE qh.quiz_id=$qid ORDER BY qh.skor DESC");
            while($qq&&$r=mysqli_fetch_assoc($qq))$peserta[]=$r;
            $quiz['_peserta']=$peserta;
        }
    } else $qid=0;
}

// --- Mode list ---
$list=[];
if ($qid===0) {
    if ($jenis==='guru') {
        $s=mysqli_prepare($koneksi,"SELECT q.id,q.judul,q.deskripsi,q.durasi_menit,k.nama_kelas,(SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jml,(SELECT COUNT(*) FROM quiz_hasil qh WHERE qh.quiz_id=q.id) AS peserta FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.guru_id=? ORDER BY q.id DESC");
        mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
        $rr=mysqli_stmt_get_result($s); while($r=mysqli_fetch_assoc($rr))$list[]=$r; mysqli_stmt_close($s);
    } else {
        $q=mysqli_query($koneksi,"SELECT q.id,q.judul,q.deskripsi,q.durasi_menit,k.nama_kelas,(SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jml,qh.skor,qh.dikerjakan_at FROM quiz q JOIN kelas k ON k.id=q.kelas_id JOIN anggota_kelas ak ON ak.kelas_id=q.kelas_id AND ak.user_id=$uid LEFT JOIN quiz_hasil qh ON qh.quiz_id=q.id AND qh.user_id=$uid GROUP BY q.id ORDER BY q.id DESC");
        while($q&&$r=mysqli_fetch_assoc($q))$list[]=$r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiziz — SLearning</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a href="<?= e($dashboard) ?>" class="brand"><img src="assets/img/logo-smkn7.png" alt="Logo"><span class="brand-text"><strong>SLearning</strong><span>Quiziz • SMKN 7</span></span></a>
  <nav class="nav-links" id="navLinks"><a href="<?= e($dashboard) ?>">Dashboard</a><a href="quiziz.php" class="active">Quiziz</a><?php if($jenis==='murid'): ?><a href="murid.php#tugas">Tugas</a><?php else: ?><a href="guru.php#menu-quiz">Kelola Quiz</a><?php endif; ?></nav>
  <div class="nav-right" id="navRight"><div class="profile"><div class="avatar"><?= e(inisial_nama($nama)) ?></div><div class="profile-info"><strong><?= e($nama) ?></strong><span><?= e(ucfirst($jenis)) ?></span></div></div><a href="logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin keluar?')"><?= svg_icon('logout',15) ?> Keluar</a></div>
  <button class="burger" id="burger" aria-label="Buka menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>
<main class="page page-grid-bg"><div class="container page-inner">
  <div style="margin-bottom:18px"><a href="<?= e($dashboard) ?>" class="btn btn-ghost btn-sm"><?= svg_icon('arrow-left',15) ?> Kembali ke dashboard</a></div>
  <?php if($flash!==''): ?><div class="alert <?= $flash_t==='error'?'error':'success' ?>"><?= svg_icon('info',18) ?><span><?= e($flash) ?></span></div><?php endif; ?>

  <?php if($qid===0): ?>
  <section class="welcome"><div><small>Quiziz • <?= e(ucfirst($jenis)) ?></small><h1>Daftar Quiziz</h1><p><?= $jenis==='guru' ? 'Buat dan kelola quiz pilihan ganda. Nilai murid terhitung otomatis.' : 'Kerjakan quiz dari guru. Timer berjalan otomatis dan nilai langsung keluar.' ?></p></div>
    <div style="position:relative;z-index:2"><?php if($jenis==='guru'): ?><a href="guru.php#menu-quiz" class="btn btn-yellow"><?= svg_icon('plus',16) ?> Buat Quiz</a><?php else: ?><a href="murid.php#quiz" class="btn btn-yellow"><?= svg_icon('book',16) ?> Kelasku</a><?php endif; ?></div></section>
  <?php if(empty($list)): ?><div class="empty"><?= svg_icon('help',18) ?><span><?= $jenis==='guru' ? 'Belum ada quiz. Buat quiz pertama dari dashboard guru.' : 'Belum ada quiz. Gabung kelas dahulu atau tunggu guru mempublikasikan quiz.' ?></span></div>
  <?php else: ?><div class="quiz-grid"><?php foreach($list as $z): ?>
    <article class="quiz-card"><div class="quiz-top"><span class="quiz-label"><?= e(mb_strtoupper(mb_substr($z['nama_kelas']??$z['kelas']??'KELAS',0,14))) ?></span><span class="quiz-time"><?= svg_icon('clock',13) ?> <?= (int)$z['durasi_menit'] ?> mnt</span></div>
    <h3><?= e($z['judul']) ?></h3><p><?= $z['deskripsi']?e(mb_strimwidth($z['deskripsi'],0,90,'...')):((int)$z['jml']).' soal pilihan ganda.' ?></p>
    <div class="quiz-bottom"><span><?= (int)$z['jml'] ?> soal<?php if($jenis==='guru') echo ' • '.(int)$z['peserta'].' peserta'; elseif(!empty($z['dikerjakan_at'])) echo ' • Skor <strong>'.(int)$z['skor'].'</strong>'; ?></span>
    <?php if($jenis==='guru'): ?><a class="link-more" href="quiziz_muridgit .php?id=<?= (int)$z['id'] ?>">Pratinjau <?= svg_icon('arrow-right',14) ?></a>
    <?php else: if(!empty($z['dikerjakan_at'])): ?><a class="link-more" href="quiziz.php?id=<?= (int)$z['id'] ?>&hasil=1">Lihat hasil <?= svg_icon('arrow-right',14) ?></a><?php else: ?><a class="link-more" href="quiziz.php?id=<?= (int)$z['id'] ?>">Kerjakan <?= svg_icon('arrow-right',14) ?></a><?php endif; endif; ?></div></article>
  <?php endforeach; ?></div><?php endif; ?>

  <?php elseif($lihat_hasil || ($jenis==='murid' && $hasil && !isset($_GET['ulangi']))): ?>
    <?php if(!$hasil): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada hasil. Kerjakan quiz terlebih dahulu.</span></div><a href="quiziz.php?id=<?= (int)$qid ?>" class="btn btn-yellow" style="margin-top:12px"><?= svg_icon('arrow-right',16) ?> Kerjakan Sekarang</a>
    <?php else: $jw = json_decode($hasil['jawaban'] ?? '[]', true) ?: []; ?>
    <section class="card" style="text-align:center;padding:36px">
      <?php if(isset($_GET['baru'])): ?><div class="alert success" style="text-align:left"><?= svg_icon('check-circle',18) ?><span>Jawaban tersimpan. Berikut hasil kamu.</span></div><?php endif; ?>
      <span class="eyebrow">Hasil Quiziz</span>
      <h2 style="font-size:24px;margin-bottom:6px"><?= e($quiz['judul']) ?></h2>
      <p class="hint" style="margin-bottom:18px"><?= e($quiz['nama_kelas']) ?> • <?= (int)$hasil['benar'] ?>/<?= (int)$hasil['total'] ?> benar</p>
      <div style="font-family:var(--font-h);font-size:64px;font-weight:800;line-height:1;color:<?= (int)$hasil['skor']>=75?'var(--green-tx)':((int)$hasil['skor']>=60?'#92600a':'var(--red-tx)') ?>"><?= (int)$hasil['skor'] ?></div>
      <p class="hint" style="margin:6px 0 20px"><?= (int)$hasil['skor']>=75?'Sangat baik, pertahankan.':((int)$hasil['skor']>=60?'Cukup baik, tingkatkan lagi.':'Jangan menyerah, pelajari lagi materinya.') ?> • <?= e(tanggal_id($hasil['dikerjakan_at'])) ?></p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap"><a href="quiziz.php?id=<?= (int)$qid ?>&ulangi=1" class="btn btn-yellow" onclick="return confirm('Kerjakan ulang? Nilai sebelumnya akan diganti.')"><?= svg_icon('arrow-right',16) ?> Kerjakan Ulang</a><a href="quiziz.php" class="btn btn-ghost">Semua Quiz</a><a href="<?= e($dashboard) ?>" class="btn btn-dark">Dashboard</a></div>
    </section>
    <?php if(!empty($soal_list)): ?><h3 style="font-size:16px;margin:20px 0 12px">Pembahasan jawaban</h3>
      <?php foreach($soal_list as $i=>$so): $pj = $jw[$so['id']] ?? $jw[(string)$so['id']] ?? '-'; $ok = ($pj===$so['kunci']); ?>
      <div class="soal" style="border-color:<?= $ok?'var(--green-bd)':'var(--red-bd)' ?>"><h4><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?> <span class="pill <?= $ok?'ok':'red' ?>" style="margin-left:8px"><?= $ok?'Benar':'Kurang tepat' ?></span></h4>
        <div style="font-size:13px;display:grid;gap:6px;margin-top:8px">
        <?php foreach(['A'=>$so['opsi_a'],'B'=>$so['opsi_b'],'C'=>$so['opsi_c'],'D'=>$so['opsi_d']] as $huruf=>$teks): ?>
          <div style="padding:9px 12px;border-radius:10px;border:1.5px solid <?= $huruf===$so['kunci']?'var(--green-bd)':($huruf===$pj?'var(--red-bd)':'var(--border)') ?>;background:<?= $huruf===$so['kunci']?'var(--green-bg)':($huruf===$pj?'var(--red-bg)':'var(--bg-soft)') ?>"><strong><?= e($huruf) ?>.</strong> <?= e($teks) ?><?= $huruf===$so['kunci']?' <strong>• Kunci</strong>':'' ?><?= ($huruf===$pj&&$pj!==$so['kunci'])?' <strong>• Pilihanmu</strong>':'' ?></div>
        <?php endforeach; ?></div></div>
      <?php endforeach; ?><?php endif; ?>
    <?php endif; ?>

  <?php else: ?>
    <!-- Kerjakan / pratinjau -->
    <section class="card" style="margin-bottom:16px"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <div><span class="eyebrow"><?= e($quiz['nama_kelas']) ?></span><h2 style="font-size:24px"><?= e($quiz['judul']) ?></h2><p class="hint"><?= $quiz['deskripsi']?e($quiz['deskripsi']):'Pilih satu jawaban terbaik untuk setiap soal.' ?> • <?= count($soal_list) ?> soal • <?= (int)$quiz['durasi_menit'] ?> menit</p></div>
      <a href="quiziz.php" class="btn btn-ghost btn-sm"><?= svg_icon('x',15) ?> Batal</a></div></section>
    <?php if(empty($soal_list)): ?><div class="empty"><?= svg_icon('info',18) ?><span>Quiz ini belum memiliki soal. <?= $jenis==='guru'?'Tambahkan soal dari dashboard guru.':'Tunggu guru menambahkan soal.' ?></span></div>
      <?php if($jenis==='guru'): ?><a href="guru.php?kelola_quiz=<?= (int)$qid ?>#menu-quiz" class="btn btn-yellow" style="margin-top:12px"><?= svg_icon('plus',16) ?> Tambah Soal</a><?php endif; ?>
    <?php elseif($jenis==='guru'): ?>
      <?php foreach($soal_list as $i=>$so): ?><div class="soal"><h4><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?> <span class="pill ok" style="margin-left:8px">Kunci: <?= e($so['kunci']) ?></span></h4>
      <div style="font-size:13px;color:var(--muted);margin-top:6px">A. <?= e($so['opsi_a']) ?> • B. <?= e($so['opsi_b']) ?> • C. <?= e($so['opsi_c']) ?> • D. <?= e($so['opsi_d']) ?></div></div><?php endforeach; ?>
      <div class="card"><h3 style="font-size:16px;margin-bottom:10px">Peserta (<?= count($quiz['_peserta']) ?>)</h3>
      <?php if(empty($quiz['_peserta'])): ?><div class="empty"><?= svg_icon('info',18) ?><span>Belum ada murid mengerjakan.</span></div>
      <?php else: ?><div class="table-wrap"><table class="data"><tr><th>Murid</th><th>Benar</th><th>Skor</th><th>Waktu</th></tr>
      <?php foreach($quiz['_peserta'] as $p): ?><tr><td><strong><?= e($p['nama']) ?></strong><br><span style="font-size:12px;color:var(--muted)">@<?= e($p['username']) ?></span></td><td><?= (int)$p['benar'] ?>/<?= (int)$p['total'] ?></td><td><span class="pill <?= (int)$p['skor']>=75?'ok':((int)$p['skor']>=60?'wait':'red') ?>"><?= (int)$p['skor'] ?></span></td><td style="font-size:12px"><?= e(tanggal_id($p['dikerjakan_at'])) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
      <a href="guru.php?kelola_quiz=<?= (int)$qid ?>#menu-quiz" class="btn btn-yellow" style="margin-top:12px"><?= svg_icon('edit',15) ?> Kelola Soal</a></div>
    <?php else: ?>
      <div class="timer"><?= svg_icon('clock',20) ?><span>Sisa waktu:</span><strong id="countdown">--:--</strong><span style="font-size:12px;color:var(--muted-dark)">otomatis terkumpul saat waktu habis</span></div>
      <form method="POST" id="quizForm">
        <?php foreach($soal_list as $i=>$so): ?><div class="soal"><h4><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?></h4>
          <div class="opsi">
          <?php foreach(['A'=>$so['opsi_a'],'B'=>$so['opsi_b'],'C'=>$so['opsi_c'],'D'=>$so['opsi_d']] as $huruf=>$teks): ?>
            <label><input type="radio" name="jwb_<?= (int)$so['id'] ?>" value="<?= e($huruf) ?>" required><span class="huruf"><?= e($huruf) ?></span><span><?= e($teks) ?></span></label>
          <?php endforeach; ?></div></div>
        <?php endforeach; ?>
        <div class="card" style="position:sticky;bottom:16px;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
          <span class="hint" id="progress">0 dari <?= count($soal_list) ?> soal terjawab</span>
          <button type="submit" name="kerjakan" class="btn btn-yellow" onclick="return confirm('Kumpulkan jawaban sekarang?')"><?= svg_icon('send',16) ?> Kumpulkan Jawaban</button>
        </div>
      </form>
      <script>
      (function(){
        var total = <?= (int)$quiz['durasi_menit'] ?>*60, el=document.getElementById('countdown'), form=document.getElementById('quizForm');
        function fmt(s){var m=Math.floor(s/60),ss=s%60;return (m<10?'0':'')+m+':'+(ss<10?'0':'')+ss;}
        var t=setInterval(function(){el.textContent=fmt(total);if(total<=0){clearInterval(t);alert('Waktu habis. Jawaban dikumpulkan otomatis.');form.submit();}total--;},1000);
        el.textContent=fmt(total);
        var prog=document.getElementById('progress'), n=<?= count($soal_list) ?>;
        form.addEventListener('change',function(){var d=new Set();form.querySelectorAll('input[type=radio]:checked').forEach(function(r){d.add(r.name);});prog.textContent=d.size+' dari '+n+' soal terjawab';});
      })();
      </script>
    <?php endif; ?>
  <?php endif; ?>
</div></main>
<footer class="mini"><div class="container"><div class="footer-inner"><span>© <?= date('Y') ?> <strong>SLearning</strong> — Quiziz</span><span><a href="<?= e($dashboard) ?>">Dashboard</a> • <a href="logout.php">Keluar</a></span></div></div></footer>
<script>(function(){var b=document.getElementById('burger'),l=document.getElementById('navLinks'),r=document.getElementById('navRight');if(b){b.addEventListener('click',function(){var o=l.classList.toggle('open');if(r)r.classList.toggle('open',o);});}})();</script>
</body>
</html>

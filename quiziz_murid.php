<?php
// SLearning — Halaman Pengerjaan Quiz Khusus Murid (Focus Mode)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

// Pastikan hanya murid yang bisa mengakses halaman ini
if (!isset($_SESSION['user_id']) || ($_SESSION['jenis'] ?? '') !== 'murid') { 
    header("Location: login.php"); exit; 
}

$uid = (int)$_SESSION['user_id'];
$qid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($qid === 0) { header("Location: quiziz.php"); exit; }

// 1. Cek Akses Murid ke Quiz Ini (Apakah dia anggota kelas?)
$s = mysqli_prepare($koneksi, "SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id JOIN anggota_kelas ak ON ak.kelas_id=q.kelas_id WHERE q.id=? AND ak.user_id=? LIMIT 1");
mysqli_stmt_bind_param($s, 'ii', $qid, $uid); mysqli_stmt_execute($s);
$quiz = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);

if (!$quiz) { 
    header("Location: quiziz.php"); exit; // Ditolak
}

// 2. Cek apakah murid sudah mengerjakan quiz ini (Cegah kerja 2 kali)
$s = mysqli_prepare($koneksi, "SELECT id FROM quiz_hasil WHERE quiz_id=? AND user_id=? LIMIT 1");
mysqli_stmt_bind_param($s, 'ii', $qid, $uid); mysqli_stmt_execute($s);
$done = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);

if ($done) { 
    // Lempar ke halaman hasil di quiziz.php
    header("Location: quiziz.php?id=$qid&hasil=1"); exit; 
}

// 3. Proses Pengumpulan Jawaban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kerjakan'])) {
    $s = mysqli_prepare($koneksi, "SELECT id, kunci FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC");
    mysqli_stmt_bind_param($s,'i',$qid); mysqli_stmt_execute($s);
    $rr = mysqli_stmt_get_result($s); 
    
    $soal = []; 
    while($r = mysqli_fetch_assoc($rr)) $soal[] = $r; 
    mysqli_stmt_close($s);
    
    $total = count($soal); 
    $benar = 0; 
    $jw = [];
    
    foreach ($soal as $so) {
        $pil = strtoupper(trim($_POST['jwb_'.$so['id']] ?? ''));
        if (!in_array($pil, ['A','B','C','D'], true)) $pil = '-'; // Kosong/Tidak dijawab
        $jw[$so['id']] = $pil;
        if ($pil === $so['kunci']) $benar++;
    }
    
    $skor = $total > 0 ? (int)round($benar/$total*100) : 0;
    $jw_json = json_encode($jw);
    
    $s = mysqli_prepare($koneksi, "INSERT INTO quiz_hasil (quiz_id, user_id, benar, total, skor, jawaban, dikerjakan_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    mysqli_stmt_bind_param($s, 'iiiiss', $qid, $uid, $benar, $total, $skor, $jw_json);
    
    if (mysqli_stmt_execute($s)) { 
        // Berhasil! Lempar ke halaman hasil di quiziz.php
        header("Location: quiziz.php?id=$qid&hasil=1&baru=1"); exit; 
    } else { 
        die("Terjadi kesalahan sistem saat menyimpan nilai."); 
    }
}

// 4. Ambil Daftar Soal
$soal_list = [];
$s = mysqli_prepare($koneksi, "SELECT * FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC");
mysqli_stmt_bind_param($s, 'i', $qid); mysqli_stmt_execute($s);
$rr = mysqli_stmt_get_result($s); 
while($r = mysqli_fetch_assoc($rr)) $soal_list[] = $r; 
mysqli_stmt_close($s);

if(empty($soal_list)) {
    die("Guru belum memasukkan soal ke dalam quiz ini. <a href='quiziz.php'>Kembali</a>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mengerjakan Quiz: <?= e($quiz['judul']) ?> — SLearning</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
<style>
    /* DESAIN FOCUS MODE */
    body { background-color: #f3f4f6; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
    
    .focus-header { background: #111827; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .focus-title h1 { margin: 0; font-size: 18px; font-weight: 700; }
    .focus-title span { font-size: 12px; color: #9ca3af; }
    
    .timer-box { background: #dc2626; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 18px; display: flex; align-items: center; gap: 8px; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.2); }
    .timer-box.warning { background: #991b1b; animation: blink 1s infinite; }
    @keyframes blink { 50% { opacity: 0.5; } }

    .quiz-body { max-width: 800px; margin: 40px auto 100px; padding: 0 20px; }
    
    .q-card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; }
    .q-title { font-size: 17px; font-weight: 600; margin-bottom: 24px; color: #1f2937; line-height: 1.6; }
    
    /* Styling Pilihan Ganda Interaktif */
    .opt-radio { display: none; }
    .opt-label { display: flex; align-items: center; padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 10px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s; background: #fff; }
    .opt-label:hover { border-color: #d1d5db; background: #f9fafb; }
    .opt-char { width: 30px; height: 30px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 16px; color: #4b5563; flex-shrink: 0; }
    
    /* Kondisi jika radio button dipilih */
    .opt-radio:checked + .opt-label { border-color: var(--yellow, #fbbf24); background: #fffbeb; box-shadow: 0 4px 6px rgba(251, 191, 36, 0.1); }
    .opt-radio:checked + .opt-label .opt-char { background: var(--yellow, #fbbf24); color: #000; }
    .opt-radio:checked + .opt-label .opt-text { font-weight: 600; color: #000; }
    
    .focus-footer { position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 16px 30px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; z-index: 100; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); }
    
    @media(max-width: 600px) {
        .focus-header { flex-direction: column; gap: 10px; text-align: center; }
        .focus-footer { flex-direction: column; gap: 15px; text-align: center; }
    }
</style>
</head>
<body>

<div class="focus-header">
    <div class="focus-title">
        <h1><?= e($quiz['judul']) ?></h1>
        <span><?= e($quiz['nama_kelas']) ?> • <?= count($soal_list) ?> Soal</span>
    </div>
    <div class="timer-box" id="timerBox">
        <?= svg_icon('clock', 20) ?> <span id="countdown">--:--</span>
    </div>
</div>

<form method="POST" id="quizForm">
    <!-- WAJIB: input hidden ini agar PHP mendeteksi form disubmit meski lewat JavaScript -->
    <input type="hidden" name="kerjakan" value="1">
    
    <div class="quiz-body">
        <?php foreach($soal_list as $i => $so): ?>
        <div class="q-card">
            <div class="q-title"><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?></div>
            
            <div class="q-options">
                <?php foreach(['A'=>$so['opsi_a'], 'B'=>$so['opsi_b'], 'C'=>$so['opsi_c'], 'D'=>$so['opsi_d']] as $huruf => $teks): ?>
                
                <!-- Radio diletakkan SEBELUM label agar CSS Selectors bekerja -->
                <input type="radio" name="jwb_<?= (int)$so['id'] ?>" id="opt_<?= $so['id'] ?>_<?= $huruf ?>" value="<?= e($huruf) ?>" class="opt-radio">
                
                <label for="opt_<?= $so['id'] ?>_<?= $huruf ?>" class="opt-label">
                    <span class="opt-char"><?= e($huruf) ?></span>
                    <span class="opt-text"><?= e($teks) ?></span>
                </label>
                
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="focus-footer">
        <div>
            <strong id="progressText" style="color:#1f2937">0</strong> dari <?= count($soal_list) ?> soal terjawab
        </div>
        <button type="submit" class="btn btn-yellow" style="padding: 12px 24px; font-size: 16px;" onclick="return confirm('Kumpulkan jawaban sekarang? Anda tidak bisa mengulangi lagi.')">
            <?= svg_icon('check-circle', 18) ?> Kumpulkan Jawaban
        </button>
    </div>
</form>

<script>
(function(){
    // Logika Timer
    const totalDetikAwal = <?= (int)$quiz['durasi_menit'] ?> * 60;
    let sisaDetik = totalDetikAwal;
    const elCountdown = document.getElementById('countdown');
    const elTimerBox = document.getElementById('timerBox');
    const form = document.getElementById('quizForm');
    
    function formatWaktu(s) {
        const m = Math.floor(s/60), ss = s%60;
        return (m<10?'0':'')+m+':'+(ss<10?'0':'')+ss;
    }
    
    const interval = setInterval(function() {
        elCountdown.textContent = formatWaktu(sisaDetik);
        
        // Peringatan merah berkedip di 60 detik terakhir
        if(sisaDetik <= 60 && !elTimerBox.classList.contains('warning')) {
            elTimerBox.classList.add('warning');
        }
        
        if (sisaDetik <= 0) {
            clearInterval(interval);
            elCountdown.textContent = "00:00";
            alert('Waktu habis! Jawaban Anda sedang dikumpulkan otomatis.');
            form.submit();
        }
        sisaDetik--;
    }, 1000);
    
    elCountdown.textContent = formatWaktu(sisaDetik);

    // Logika Hitung Progress Terjawab
    const progText = document.getElementById('progressText');
    const totalSoal = <?= count($soal_list) ?>;
    
    form.addEventListener('change', function() {
        let terjawab = new Set();
        form.querySelectorAll('input[type=radio]:checked').forEach(function(r){
            terjawab.add(r.name);
        });
        progText.textContent = terjawab.size;
        
        // Beri warna hijau ke teks progress jika semua terjawab
        if(terjawab.size === totalSoal) {
            progText.style.color = "var(--green-tx, #16a34a)";
        }
    });
})();
</script>
</body>
</html>
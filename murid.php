<?php
/**
 * SLearning - Dashboard Murid
 * Versi bersih: konflik Git dan potongan halaman Login/Register sudah dihapus.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   KONEKSI DATABASE
   =========================
   File koneksi proyek harus membuat variabel mysqli $koneksi.
   Lokasi umum dicoba otomatis. Jika lokasi berbeda, ubah daftar di bawah.
*/
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
    die('Koneksi database $koneksi tidak ditemukan. Pastikan file koneksi.php tersedia dan membuat variabel mysqli $koneksi.');
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
if (($_SESSION['jenis'] ?? '') === 'guru') {
    header('Location: guru.php');
    exit;
}
if (($_SESSION['jenis'] ?? '') === 'waka') {
    header('Location: waka.php');
    exit;
}
$uid = (int)$_SESSION['user_id'];
$user = stmt_row($koneksi, "SELECT id,nama,username,email,jenis,level,password,foto FROM login WHERE id=? LIMIT 1", 'i', [$uid]);
if (!$user || ($user['jenis'] ?? '') !== 'murid') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
foreach (['nama','username','email','jenis','level','foto'] as $kolom) {
    $_SESSION[$kolom] = $user[$kolom] ?? '';
}
$nama_murid = $user['nama'];
$foto_profil = $user['foto'] ?? '';

/* =========================
   RINGKASAN FITUR
   (Migrasi anggota_kelas.status, laporan_pengguna, dan kolom lain
    sudah otomatis oleh config/koneksi.php — tidak perlu di sini.)
   ========================= */

/* =========================
   PROSES FORM
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profil
    if (isset($_POST['update_profil'])) {
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_murid('Nama dan email yang valid wajib diisi.', 'error');
        } else {
            $sql = "UPDATE login SET nama=?, email=?";
            $types = 'ss';
            $params = [$nama, $email];
            $passLama = $_POST['password_lama'] ?? '';
            $passBaru = $_POST['password_baru'] ?? '';
            if ($passBaru !== '') {
                if (!password_verify($passLama, $user['password'])) {
                    flash_murid('Password lama tidak sesuai.', 'error', 'Gagal');
                    header('Location: murid.php?page=profil'); exit;
                }
                if (strlen($passBaru) < 8) {
                    flash_murid('Password baru minimal 8 karakter.', 'error');
                    header('Location: murid.php?page=profil'); exit;
                }
                $sql .= ', password=?';
                $types .= 's';
                $params[] = password_hash($passBaru, PASSWORD_DEFAULT);
            }
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $mime = mime_content_type($_FILES['foto']['tmp_name']);
                $allowed = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif'];
                if (isset($allowed[$ext]) && $mime === $allowed[$ext]) {
                    $folder = __DIR__.'/uploads/profil/';
                    if (!is_dir($folder)) mkdir($folder, 0755, true);
                    $namaFile = 'murid_'.$uid.'_'.bin2hex(random_bytes(5)).'.'.$ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $folder.$namaFile)) {
                        $sql .= ', foto=?'; $types .= 's'; $params[] = 'uploads/profil/'.$namaFile;
                    }
                } else {
                    flash_murid('Foto harus berupa JPG, PNG, atau GIF.', 'error');
                }
            }
            $sql .= ' WHERE id=?'; $types .= 'i'; $params[] = $uid;
            $st = mysqli_prepare($koneksi, $sql);
            if ($st) {
                mysqli_stmt_bind_param($st, $types, ...$params);
                $ok = mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
                flash_murid($ok ? 'Profil berhasil diperbarui.' : 'Profil gagal diperbarui.', $ok ? 'success' : 'error');
            } else flash_murid('Query profil gagal diproses.', 'error');
        }
        header('Location: murid.php?page=profil'); exit;
    }

    // Gabung kelas
    if (isset($_POST['gabung_kelas'])) {
        $kode = strtoupper(trim($_POST['kode_gabung'] ?? ''));
        $kelas = $kode !== '' ? stmt_row($koneksi, "SELECT id,nama_kelas FROM kelas WHERE kode_gabung=? LIMIT 1", 's', [$kode]) : null;
        if (!$kelas) {
            flash_murid('Kode kelas tidak ditemukan.', 'error', 'Gagal');
        } else {
            $kid = (int)$kelas['id'];
            $anggota = stmt_row($koneksi, "SELECT id,status FROM anggota_kelas WHERE kelas_id=? AND user_id=? LIMIT 1", 'ii', [$kid,$uid]);
            if ($anggota) {
                if (($anggota['status'] ?? 'disetujui') === 'disetujui') {
                    flash_murid('Kamu sudah tergabung di kelas ini.', 'info');
                } else {
                    $st = mysqli_prepare($koneksi, "UPDATE anggota_kelas SET status='pending' WHERE id=?");
                    mysqli_stmt_bind_param($st, 'i', $anggota['id']); mysqli_stmt_execute($st); mysqli_stmt_close($st);
                    flash_murid('Permintaan bergabung dikirim ulang.', 'success');
                }
            } else {
                $st = mysqli_prepare($koneksi, "INSERT INTO anggota_kelas (kelas_id,user_id,status) VALUES (?,?,'pending')");
                mysqli_stmt_bind_param($st, 'ii', $kid, $uid);
                $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
                flash_murid($ok ? 'Permintaan bergabung dikirim. Tunggu persetujuan guru.' : 'Gagal mengirim permintaan.', $ok ? 'success' : 'error');
            }
            header('Location: murid.php?page=detail_kelas&id='.$kid); exit;
        }
        header('Location: murid.php?page=kelas'); exit;
    }

    // Isi absensi
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
        } else flash_murid('Sesi absensi tidak ditemukan atau akses ditolak.', 'error');
        header('Location: murid.php?page=absensi'); exit;
    }

    // Kumpulkan tugas
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
            $allowed = ['pdf','doc','docx','ppt','pptx','zip','rar','jpg','jpeg','png'];
            if ($f['size'] > 10*1024*1024 || !in_array($ext, $allowed, true)) {
                flash_murid('File maksimal 10 MB dan format harus PDF/DOC/DOCX/PPT/PPTX/ZIP/RAR/JPG/PNG.', 'error');
            } else {
                $folder = __DIR__.'/uploads/tugas/';
                if (!is_dir($folder)) mkdir($folder, 0755, true);
                $fileBaru = 'tugas_'.$uid.'_'.$tid.'_'.bin2hex(random_bytes(5)).'.'.$ext;
                $pathDatabase = 'uploads/tugas/'.$fileBaru;
                if (move_uploaded_file($f['tmp_name'], $folder.$fileBaru)) {
                    $lama = stmt_row($koneksi, "SELECT id,file_path FROM pengumpulan_tugas WHERE tugas_id=? AND user_id=? LIMIT 1", 'ii', [$tid,$uid]);
                    if ($lama) {
                        $st = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nama_file=?,file_path=?,nilai=NULL,feedback=NULL,dikumpulkan_at=CURRENT_TIMESTAMP WHERE id=?");
                        mysqli_stmt_bind_param($st, 'ssi', $f['name'], $pathDatabase, $lama['id']);
                    } else {
                        $st = mysqli_prepare($koneksi, "INSERT INTO pengumpulan_tugas (tugas_id,user_id,nama_file,file_path) VALUES (?,?,?,?)");
                        mysqli_stmt_bind_param($st, 'iiss', $tid, $uid, $f['name'], $pathDatabase);
                    }
                    $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
                    flash_murid($ok ? 'Tugas berhasil dikumpulkan.' : 'Gagal menyimpan data tugas.', $ok ? 'success' : 'error');
                } else flash_murid('File gagal diunggah.', 'error');
            }
        }
        header('Location: murid.php?page=tugas'); exit;
    }

    // Kirim laporan
    if (isset($_POST['kirim_laporan'])) {
        $terlapor = (int)($_POST['terlapor_id'] ?? 0);
        $kategori = trim($_POST['kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tujuan = stmt_row($koneksi, "SELECT id FROM login WHERE id=? AND jenis IN ('guru','murid')", 'i', [$terlapor]);
        if (!$tujuan || $terlapor === $uid || $kategori === '' || $deskripsi === '') {
            flash_murid('Data laporan tidak valid.', 'error');
        } else {
            $st = mysqli_prepare($koneksi, "INSERT INTO laporan_pengguna (pelapor_id,terlapor_id,kategori,deskripsi) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($st, 'iiss', $uid, $terlapor, $kategori, $deskripsi);
            $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st);
            flash_murid($ok ? 'Laporan berhasil dikirim.' : 'Laporan gagal dikirim.', $ok ? 'success' : 'error');
        }
        header('Location: murid.php?page=laporan'); exit;
    }
}

/* =========================
   DATA DASHBOARD
   ========================= */
$kelasSaya = stmt_rows($koneksi, "SELECT k.id,k.nama_kelas,k.kode_gabung,k.guru_id,k.tahun_ajaran,COALESCE(ak.status,'disetujui') status_izin,l.nama nama_guru FROM anggota_kelas ak JOIN kelas k ON k.id=ak.kelas_id LEFT JOIN login l ON l.id=k.guru_id WHERE ak.user_id=? ORDER BY k.id DESC", 'i', [$uid]);
$kelasDisetujui = [];
foreach ($kelasSaya as $k) if (($k['status_izin'] ?? 'disetujui') === 'disetujui') $kelasDisetujui[] = (int)$k['id'];
$inKelas = $kelasDisetujui ? implode(',', $kelasDisetujui) : '0';
$statTugas = 0; $statQuiz = 0;
$r = mysqli_query($koneksi, "SELECT COUNT(*) jml FROM tugas WHERE kelas_id IN ($inKelas)");
if ($r) $statTugas = (int)(mysqli_fetch_assoc($r)['jml'] ?? 0);
$r = mysqli_query($koneksi, "SELECT COUNT(*) jml FROM quiz WHERE kelas_id IN ($inKelas)");
if ($r) $statQuiz = (int)(mysqli_fetch_assoc($r)['jml'] ?? 0);
$notifikasi = [];
if ($inKelas !== '0') {
    $r = mysqli_query($koneksi, "SELECT judul_tugas judul,'tugas' tipe,created_at tanggal FROM tugas WHERE kelas_id IN ($inKelas) UNION SELECT judul,'quiz' tipe,created_at tanggal FROM quiz WHERE kelas_id IN ($inKelas) ORDER BY tanggal DESC LIMIT 15");
    if ($r) while ($x = mysqli_fetch_assoc($r)) $notifikasi[] = $x;
}
$page = $_GET['page'] ?? 'beranda';
$quizId = (int)($_GET['quiz_id'] ?? 0);
$flash = $_SESSION['flash_murid'] ?? null;
unset($_SESSION['flash_murid']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SLearning | Dashboard Murid</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root{--yellow:#fff8d6;--sage:#6d8c6d;--sage-dark:#577357;--sage-light:#e8f0e8;--ink:#263326;--muted:#687968;--line:#dce7dc}
*{box-sizing:border-box}body{margin:0;background:#f4f7f5;color:var(--ink);font-family:'Plus Jakarta Sans',Arial,sans-serif}
a{color:inherit;text-decoration:none}.header{background:linear-gradient(180deg,var(--yellow),#fff);border-bottom:1px solid #eee5bd;position:sticky;top:0;z-index:5}
.header-top{max-width:1150px;margin:auto;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.logo{font-size:22px;font-weight:800;color:#2f402f}.logo span{font-size:12px;background:var(--sage-light);padding:5px 10px;border-radius:30px;color:var(--sage)}
.header-actions{display:flex;gap:10px;align-items:center}.header-actions a{padding:9px 13px;border-radius:30px;background:#fff;border:1px solid var(--line);font-size:13px;font-weight:700}
.nav{max-width:1150px;margin:auto;padding:0 16px;display:flex;gap:5px;overflow-x:auto}.nav a{padding:13px 16px;font-size:13px;font-weight:700;color:var(--muted);white-space:nowrap;border-radius:10px 10px 0 0}.nav a.active,.nav a:hover{background:#fff;color:var(--ink);border-bottom:3px solid var(--sage)}
.container{max-width:1100px;margin:30px auto;padding:0 22px 60px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:25px;margin-bottom:18px;box-shadow:0 5px 18px #6d8c6d0c}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.stat{display:flex;gap:15px;align-items:center}.stat-icon{font-size:25px;background:var(--yellow);padding:15px;border-radius:14px}.muted{color:var(--muted);font-size:13px}.btn{display:inline-block;background:var(--sage);color:#fff;border:0;border-radius:30px;padding:11px 17px;font-weight:800;font-size:13px;cursor:pointer}.btn:hover{background:var(--sage-dark)}.btn-light{background:#fff;color:var(--sage-dark);border:1px solid var(--line)}
input,select,textarea{font:inherit;width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;background:#fbfdfb;margin:6px 0 14px}label{font-size:13px;font-weight:700}
table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid var(--line);font-size:13px}
.alert{padding:13px 16px;border-radius:12px;margin-bottom:18px;background:#e8f5e9;color:#28652d}.alert.error{background:#ffefed;color:#a43b30}
.badge{display:inline-block;padding:5px 10px;border-radius:30px;background:var(--yellow);font-size:11px;font-weight:800}
@media(max-width:600px){.header-top{padding:14px}.container{padding:0 13px}.card{padding:18px}.header-actions a{font-size:11px;padding:8px}}
</style>
</head>
<body>
<header class="header">
  <div class="header-top">
    <a class="logo" href="murid.php">SLearning <span>Murid</span></a>
    <div class="header-actions">
      <a href="murid.php?page=profil"><?= e($nama_murid) ?></a>
      <a href="logout.php" onclick="return confirm('Keluar dari SLearning?')">Keluar</a>
    </div>
  </div>
  <nav class="nav">
    <?php
    $menu = ['beranda'=>'Beranda','kelas'=>'Kelas','absensi'=>'Absensi','tugas'=>'Tugas','quiz'=>'Quiz','notif'=>'Notifikasi','laporan'=>'Laporan','profil'=>'Profil'];
    foreach ($menu as $key=>$label):
    ?>
      <a class="<?= $page===$key?'active':'' ?>" href="murid.php?page=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="container">
<?php if ($flash): ?>
  <div class="alert <?= ($flash['tipe'] ?? '')==='error'?'error':'' ?>"><?= e($flash['pesan']) ?></div>
<?php endif; ?>

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

<?php elseif ($page === 'absensi'): ?>
  <div class="card"><h2>Absensi Kehadiran</h2>
  <?php $absen=$inKelas!=='0'?stmt_rows($koneksi,"SELECT a.id,a.judul_absensi,a.tanggal,k.nama_kelas,ah.status FROM absensi a JOIN kelas k ON k.id=a.kelas_id LEFT JOIN absensi_hadir ah ON ah.absensi_id=a.id AND ah.user_id=? WHERE a.kelas_id IN ($inKelas) ORDER BY a.tanggal DESC",'i',[$uid]):[]; ?>
  <?php if (!$absen): ?><p class="muted">Belum ada sesi absensi.</p><?php else: foreach($absen as $a): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--line)"><b><?= e($a['judul_absensi']) ?></b><p class="muted"><?= e($a['nama_kelas']) ?> · <?= e(tanggal_id($a['tanggal'])) ?></p>
    <?php if ($a['status']): ?><span class="badge">Sudah absen: <?= e($a['status']) ?></span><?php else: ?>
      <form method="post"><input type="hidden" name="absensi_id" value="<?= (int)$a['id'] ?>"><select name="status"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select><button class="btn" name="isi_absen">Kirim Absen</button></form>
    <?php endif; ?></div>
  <?php endforeach; endif; ?></div>

<?php elseif ($page === 'tugas'): ?>
  <div class="card"><h2>Daftar Tugas</h2>
  <?php $tugas=$inKelas!=='0'?stmt_rows($koneksi,"SELECT t.id,t.judul_tugas,t.deadline,k.nama_kelas,pt.nama_file FROM tugas t JOIN kelas k ON k.id=t.kelas_id LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id=t.id AND pt.user_id=? WHERE t.kelas_id IN ($inKelas) ORDER BY t.deadline ASC",'i',[$uid]):[]; ?>
  <?php if (!$tugas): ?><p class="muted">Belum ada tugas tersedia.</p><?php else: foreach($tugas as $t): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--line)"><b><?= e($t['judul_tugas']) ?></b><p class="muted"><?= e($t['nama_kelas']) ?> · Deadline: <?= e(tanggal_id($t['deadline']??'',false)) ?></p>
    <span class="badge"><?= $t['nama_file']?'Sudah dikumpulkan':'Belum dikumpulkan' ?></span>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="tugas_id" value="<?= (int)$t['id'] ?>"><input type="file" name="file_tugas" required><button class="btn" name="kumpulkan_tugas">Kumpulkan Tugas</button></form></div>
  <?php endforeach; endif; ?></div>

<?php elseif ($page === 'quiz'): ?>
  <div class="card"><h2>Quiz Tersedia</h2>
  <?php $quiz=$inKelas!=='0'?stmt_rows($koneksi,"SELECT q.id,q.judul,k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.kelas_id IN ($inKelas) ORDER BY q.id DESC"):[]; ?>
  <?php if (!$quiz): ?><p class="muted">Belum ada quiz tersedia.</p><?php else: foreach($quiz as $q): ?>
    <div style="padding:12px 0;border-bottom:1px solid var(--line)"><b><?= e($q['judul']) ?></b><p class="muted"><?= e($q['nama_kelas']) ?></p><a class="btn" href="murid.php?page=kerjakan_quiz&quiz_id=<?= (int)$q['id'] ?>">Buka Quiz</a></div>
  <?php endforeach; endif; ?></div>

<?php elseif ($page === 'kerjakan_quiz'): ?>
  <?php
  $quizDetail = $quizId > 0 ? stmt_row($koneksi, "SELECT q.*,k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id JOIN anggota_kelas ak ON ak.kelas_id=k.id WHERE q.id=? AND ak.user_id=? AND COALESCE(ak.status,'disetujui')='disetujui' LIMIT 1", 'ii', [$quizId,$uid]) : null;
  ?>
  <div class="card">
    <?php if (!$quizDetail): ?>
      <h2>Quiz tidak ditemukan</h2><p class="muted">Quiz tidak tersedia atau kamu belum mendapat izin masuk kelas.</p>
    <?php else: ?>
      <h2><?= e($quizDetail['judul'] ?? 'Quiz') ?></h2>
      <p class="muted">Kelas: <?= e($quizDetail['nama_kelas']) ?></p>
      <p>Quiz ini terdaftar di kelas yang kamu ikuti. Tampilan pengerjaan soal perlu disesuaikan dengan struktur tabel soal dan jawaban pada database proyek.</p>
      <a class="btn btn-light" href="murid.php?page=quiz">Kembali ke daftar quiz</a>
    <?php endif; ?>
  </div>

<?php elseif ($page === 'notif' || $page === 'notifikasi'): ?>
  <div class="card"><h2>Pemberitahuan</h2>
  <?php if (!$notifikasi): ?><p class="muted">Belum ada pemberitahuan baru.</p><?php else: foreach($notifikasi as $n): ?>
    <p><span class="badge"><?= e(strtoupper($n['tipe'])) ?></span> <?= e($n['judul']) ?> <span class="muted"><?= e(tanggal_id($n['tanggal'])) ?></span></p>
  <?php endforeach; endif; ?></div>

<?php elseif ($page === 'profil'): ?>
  <div class="card"><h2>Pengaturan Profil</h2>
  <form method="post" enctype="multipart/form-data">
    <label>Nama</label><input name="nama" value="<?= e($user['nama']) ?>" required>
    <label>Email</label><input type="email" name="email" value="<?= e($user['email']) ?>" required>
    <label>Foto Profil</label><input type="file" name="foto" accept=".jpg,.jpeg,.png,.gif">
    <label>Password Lama (isi jika ingin mengganti password)</label><input type="password" name="password_lama">
    <label>Password Baru</label><input type="password" name="password_baru" minlength="8">
    <button class="btn" name="update_profil">Simpan Profil</button>
  </form></div>

<?php elseif ($page === 'laporan'): ?>
  <div class="card"><h2>🚩 Lapor Pelanggaran</h2>
  <p class="muted">Laporan akan diteruskan kepada pengelola sekolah.</p>
  <form method="post">
    <label>Pengguna yang dilaporkan</label>
    <select name="terlapor_id" required><option value="">Pilih pengguna</option>
    <?php foreach(stmt_rows($koneksi,"SELECT id,nama,jenis FROM login WHERE jenis IN ('guru','murid') AND id<>? ORDER BY nama",'i',[$uid]) as $x): ?>
      <option value="<?= (int)$x['id'] ?>"><?= e($x['nama']) ?> (<?= e($x['jenis']) ?>)</option>
    <?php endforeach; ?></select>
    <label>Kategori</label><select name="kategori" required><option value="">Pilih kategori</option><option>Perkataan Kasar / Bullying</option><option>Pelecehan / Tindak Kekerasan</option><option>Kecurangan / Menyontek</option><option>Sikap Tidak Profesional</option><option>Lainnya</option></select>
    <label>Keterangan</label><textarea name="deskripsi" rows="4" required></textarea>
    <button class="btn" name="kirim_laporan">Kirim Laporan</button>
  </form>
  <h3>Riwayat Laporan Saya</h3>
  <?php foreach(stmt_rows($koneksi,"SELECT lp.*,l.nama,l.jenis FROM laporan_pengguna lp JOIN login l ON l.id=lp.terlapor_id WHERE lp.pelapor_id=? ORDER BY lp.created_at DESC",'i',[$uid]) as $lp): ?>
    <p><b><?= e($lp['nama']) ?></b> — <?= e($lp['kategori']) ?> <span class="badge"><?= e($lp['status']) ?></span></p>
  <?php endforeach; ?></div>

<?php else: ?>
  <div class="card"><h1>Halo, <?= e($nama_murid) ?> 👋</h1><p class="muted">Selamat datang di SLearning SMK Negeri 7 Batam.</p></div>
  <div class="grid">
    <div class="card stat"><span class="stat-icon">📝</span><div><h2><?= $statTugas ?></h2><p class="muted">Tugas Aktif</p></div></div>
    <div class="card stat"><span class="stat-icon">🎮</span><div><h2><?= $statQuiz ?></h2><p class="muted">Quiz Tersedia</p></div></div>
    <div class="card stat"><span class="stat-icon">🏫</span><div><h2><?= count($kelasSaya) ?></h2><p class="muted">Kelas Diikuti</p></div></div>
  </div>
  <div class="card"><h2>Aktivitas Terbaru</h2>
  <?php if (!$notifikasi): ?><p class="muted">Belum ada aktivitas baru.</p><?php else: foreach($notifikasi as $n): ?>
    <p><span class="badge"><?= e(strtoupper($n['tipe'])) ?></span> <?= e($n['judul']) ?> · <span class="muted"><?= e(tanggal_id($n['tanggal'])) ?></span></p>
  <?php endforeach; endif; ?></div>
<?php endif; ?>
</main>
</body>
</html>

<?php
// SLearning — Pengaturan Sistem (Super Admin / Waka)
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['jenis'] !== 'waka') { 
    header("Location: login.php"); 
    exit; 
}

$host = "localhost"; $dbname = "slearning_db"; $dbuser = "root"; $dbpass = "";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    
    // Auto-create tabel pengaturan sistem
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
        id INT PRIMARY KEY, 
        nama_sekolah VARCHAR(100) NOT NULL, 
        tahun_ajaran VARCHAR(50) NOT NULL, 
        semester ENUM('Ganjil', 'Genap') NOT NULL
    )");

    // Insert default data jika tabel masih kosong
    $cek = $pdo->query("SELECT COUNT(*) FROM pengaturan")->fetchColumn();
    if ($cek == 0) {
        $pdo->exec("INSERT INTO pengaturan (id, nama_sekolah, tahun_ajaran, semester) VALUES (1, 'SMK Negeri 7 Batam', '2026/2027', 'Ganjil')");
    }

} catch (PDOException $e) { die("Koneksi database gagal."); }

$msg = ""; $msg_type = "success";

// Proses Update Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = trim($_POST['nama_sekolah'] ?? '');
    $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '');
    $semester = $_POST['semester'] ?? 'Ganjil';

    if ($nama_sekolah !== '' && $tahun_ajaran !== '') {
        $stmt = $pdo->prepare("UPDATE pengaturan SET nama_sekolah = ?, tahun_ajaran = ?, semester = ? WHERE id = 1");
        if ($stmt->execute([$nama_sekolah, $tahun_ajaran, $semester])) {
            $msg = "Pengaturan sistem berhasil diperbarui!";
        } else {
            $msg = "Gagal memperbarui pengaturan."; $msg_type = "error";
        }
    }
}

// Ambil data pengaturan saat ini
$pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id = 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Sistem — Slearning SMKN 7</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root { 
    --nav-yellow: #FFF8D6; --nav-yellow-dark: #F2E7B5; --sage-body: #F4F7F5; --sage-50: #E8F0E8; --sage-100: #D1E0D1;
    --sage-300: #9FBA9F; --sage-500: #6D8C6D; --sage-600: #577357; --sage-800: #2F402F; --sage-900: #1D261D;
    --shadow-sm: 0 4px 16px rgba(109, 140, 109, 0.06);
    --radius-sm: 12px; --radius-md: 16px;
}
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; }

.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 900px; margin: 0 auto; padding: 0 32px 16px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }
.btn-back { color: var(--sage-600); text-decoration: none; background: #FFFFFF; padding: 8px 16px; border-radius: 50px; border: 1px solid var(--sage-100); font-size: 13.5px; font-weight: 700; transition: 0.3s; }
.btn-back:hover { background: var(--sage-50); color: var(--sage-900); }

.container { max-width: 900px; margin: 40px auto; padding: 0 32px; }
.panel { background: #FFFFFF; border-radius: var(--radius-md); border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); padding: 40px; }
.panel-header { display: flex; align-items: center; gap: 14px; margin-bottom: 30px; border-bottom: 2px solid var(--sage-50); padding-bottom: 20px; }
.panel-header h2 { font-size: 22px; font-weight: 800; color: var(--sage-900); }
.panel-header span { background: var(--nav-yellow); padding: 8px; border-radius: 12px; font-size: 24px; border: 1px solid #E2D38A; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 13.5px; font-weight: 700; color: var(--sage-800); margin-bottom: 8px; }
.input-elegant { width: 100%; background: var(--sage-body); border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 14px 18px; font-size: 14px; outline: none; color: var(--sage-900); font-family: inherit; transition: 0.2s; }
.input-elegant:focus { border-color: var(--sage-500); background: #FFFFFF; box-shadow: 0 0 0 3px rgba(109,140,109,0.12); }

.btn-primary { background: var(--sage-500); color: #FFFFFF; border: none; padding: 14px 28px; border-radius: 50px; font-size: 14.5px; font-weight: 700; cursor: pointer; transition: 0.3s; display: block; width: 100%; margin-top: 30px; box-shadow: 0 4px 12px rgba(109,140,109,0.2); }
.btn-primary:hover { background: var(--sage-600); transform: translateY(-1px); }
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="waka.php" class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
      SLearning <span style="font-weight: 500; font-size: 15px; color: var(--sage-600); margin-left: 6px;">| Pengaturan</span>
    </a>
    <a href="waka.php" class="btn-back">← Kembali ke Dashboard</a>
  </div>
</header>

<main class="container">
    <?php if($msg): ?>
        <div style="background: <?= $msg_type==='error'?'#FDF2F2':'#E8F5E9' ?>; color: <?= $msg_type==='error'?'#D9534F':'#2E7D32' ?>; padding: 16px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; font-weight: 700; border: 1px solid <?= $msg_type==='error'?'#F5C6CB':'#C3DDBC' ?>;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <span>⚙️</span>
            <h2>Pengaturan Sistem Akademik</h2>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Nama Instansi / Sekolah</label>
                <input type="text" name="nama_sekolah" class="input-elegant" value="<?= htmlspecialchars($pengaturan['nama_sekolah']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Tahun Ajaran Aktif</label>
                <input type="text" name="tahun_ajaran" class="input-elegant" value="<?= htmlspecialchars($pengaturan['tahun_ajaran']) ?>" placeholder="Contoh: 2026/2027" required>
            </div>

            <div class="form-group">
                <label>Semester Berjalan</label>
                <select name="semester" class="input-elegant">
                    <option value="Ganjil" <?= $pengaturan['semester'] === 'Ganjil' ? 'selected' : '' ?>>Semester Ganjil</option>
                    <option value="Genap" <?= $pengaturan['semester'] === 'Genap' ? 'selected' : '' ?>>Semester Genap</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">💾 Simpan Perubahan Sistem</button>
        </form>
    </div>
</main>
</body>
</html>
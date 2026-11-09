<?php
// Waktu mengikuti WIB agar tanggal registrasi/aktivitas sesuai tanggal nyata (no. 8).
date_default_timezone_set('Asia/Jakarta');
// Perbaikan sesi login (no. 11): cookie sesi tidak bisa dibaca JS & ID sesi asal tidak diterima.
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
session_start();

// Keluar manual: login.php?logout=1
if (isset($_GET["logout"])) {
    session_unset(); session_destroy();
    header("Location: login.php"); exit;
}

// Nama file dashboard tiap peran. Ubah di sini kalau nama halaman dashboard berbeda.
$dashboardFiles = ["murid" => "murid.php", "guru" => "guru.php", "waka" => "waka.php", "admin" => "admin.php"];
$levelByJenis   = ["murid" => ["user"], "guru" => ["admin"], "waka" => ["admin", "super admin"], "admin" => ["super admin"]];

// Akun Petugas/Admin (super admin) dibuat otomatis SEKALI jika belum ada. Super admin tidak bisa mendaftar lewat form.
// GANTI username & password ini sebelum dipakai sungguhan.
$superAdminUsername = "admin";
$superAdminPassword = "admin12345";

function startUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION["user_id"] = $user["id"]; $_SESSION["nama"] = $user["nama"]; $_SESSION["username"] = $user["username"];
    $_SESSION["email"] = $user["email"] ?? ""; $_SESSION["jenis"] = $user["jenis"]; $_SESSION["level"] = $user["level"];
}

// Jika sudah login, arahkan ke dashboard sesuai peran.
// Redirect hanya dilakukan jika file dashboard benar-benar ada (mencegah 404 Not Found).
$dashboardMissing = "";
if (isset($_SESSION["user_id"], $_SESSION["jenis"])) {
    $roleNow = $_SESSION["jenis"];
    if (isset($dashboardFiles[$roleNow]) && in_array($_SESSION["level"] ?? "", $levelByJenis[$roleNow], true)) {
        $dest = $dashboardFiles[$roleNow];
        if (is_file(__DIR__ . "/" . $dest)) { header("Location: " . $dest); exit; }
        $dashboardMissing = $dest;
    } else {
        session_unset(); session_destroy(); session_start();
    }
}

$host = "localhost"; $dbname = "slearning_db"; $dbuser = "root"; $dbpass = "";
$error = ""; $success = ""; $mode = $_POST["mode"] ?? (($_GET["mode"] ?? "") === "register" ? "register" : "login");
$nama = ""; $username = ""; $email = ""; $jenis = "murid"; $registrationCode = "";
$nis = ""; $loginJenis = $_POST["login_type"] ?? "murid"; $loginNama = trim($_POST["login_nama"] ?? ""); $loginNis = trim($_POST["login_nis"] ?? "");
if (!in_array($loginJenis, ["murid", "guru", "waka", "admin"], true)) $loginJenis = "murid";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'"]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS login (
        id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NULL UNIQUE,
        password VARCHAR(255) NOT NULL, jenis ENUM('waka','guru','murid','admin') NOT NULL,
        level ENUM('super admin','admin','user') NOT NULL DEFAULT 'user',
        nis VARCHAR(30) NULL UNIQUE, foto VARCHAR(255) NULL,
        status_persetujuan ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
    )");

    // Migrasi untuk tabel lama agar kolom dan nilai level konsisten.
    $cols = $pdo->query("SHOW COLUMNS FROM login")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array("nis", $cols, true)) {
        $pdo->exec("ALTER TABLE login ADD COLUMN nis VARCHAR(30) NULL UNIQUE");
    }
    if (!in_array("foto", $cols, true)) {
        $pdo->exec("ALTER TABLE login ADD COLUMN foto VARCHAR(255) NULL");
    }
    if (in_array("email", $cols, true)) {
        $pdo->exec("ALTER TABLE login MODIFY email VARCHAR(100) NULL");
    }
    if (!in_array("status_persetujuan", $cols, true)) {
        $pdo->exec("ALTER TABLE login ADD COLUMN status_persetujuan ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
    }

    // Akun Guru yang tertahan status pending diaktifkan (tidak ada alur persetujuan).
    $pdo->exec("UPDATE login SET status_persetujuan = 'approved' WHERE jenis = 'guru' AND status_persetujuan = 'pending'");

    // Migrasikan ENUM level hanya jika strukturnya belum sesuai.
    // Ubah sementara ke VARCHAR agar nilai ENUM lama tetap bisa dibaca.
    $levelColumn = $pdo->query("SHOW COLUMNS FROM login LIKE 'level'")->fetch();
    if ($levelColumn && strtolower($levelColumn['Type']) !== "enum('super admin','admin','user')") {
        $pdo->exec("ALTER TABLE login MODIFY level VARCHAR(50) NOT NULL DEFAULT 'user'");
        // Kode aplikasi memakai pemetaan tetap: waka=super admin, guru=admin, murid=user.
        $pdo->exec("UPDATE login SET level = CASE
            WHEN jenis = 'waka' THEN 'super admin'
            WHEN jenis = 'guru' THEN 'admin'
            WHEN jenis = 'murid' THEN 'user'
            ELSE 'user'
        END");
        $pdo->exec("ALTER TABLE login MODIFY level ENUM('super admin','admin','user') NOT NULL DEFAULT 'user'");
    }

    // Pastikan jenis 'admin' (Petugas/Admin) tersedia pada tabel lama.
    $jenisColumn = $pdo->query("SHOW COLUMNS FROM login LIKE 'jenis'")->fetch();
    if ($jenisColumn && stripos($jenisColumn['Type'], "'admin'") === false) {
        $pdo->exec("ALTER TABLE login MODIFY jenis ENUM('waka','guru','murid','admin') NOT NULL");
    }

    // Buat akun super admin otomatis jika belum ada.
    $hasAdmin = $pdo->query("SELECT id FROM login WHERE jenis = 'admin' LIMIT 1")->fetch();
    if (!$hasAdmin) {
        $seed = $pdo->prepare("INSERT IGNORE INTO login (nama, username, email, password, jenis, level, nis, status_persetujuan) VALUES ('Super Admin', ?, NULL, ?, 'admin', 'super admin', NULL, 'approved')");
        $seed->execute([$superAdminUsername, password_hash($superAdminPassword, PASSWORD_DEFAULT)]);
    }
} catch (PDOException $e) {
    die("Koneksi atau struktur database gagal. Periksa database slearning_db dan tabel login.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = $_POST["mode"] ?? "login";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($mode === "register") {
        $username = trim($_POST["register_username"] ?? "");
        $nama = trim($_POST["nama"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $nis = trim($_POST["nis"] ?? "");
        $jenis = $_POST["jenis"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";
        $isMurid = $jenis === "murid";

        if ($nama === "" || $password === "" || $confirm === "" || ($isMurid ? $nis === "" : ($username === "" || $email === ""))) {
            $error = "Semua kolom wajib diisi!";
        } elseif (!in_array($jenis, ["guru", "murid"], true)) {
            $error = "Pilih jenis pendaftaran yang valid!";
        } elseif (!$isMurid && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid!";
        } elseif ($isMurid && !preg_match('/^[0-9]{3,30}$/', $nis)) {
            $error = "NIS hanya boleh berisi angka (3-30 digit).";
        } elseif (strlen($password) < 8) {
            $error = "Password minimal 8 karakter!";
        } elseif ($password !== $confirm) {
            $error = "Konfirmasi password tidak cocok!";
        } else {
            if ($isMurid) {
                $check = $pdo->prepare("SELECT id FROM login WHERE nis = ?");
                $check->execute([$nis]);
            } else {
                $check = $pdo->prepare("SELECT id FROM login WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
            }
            if ($check->fetch()) {
                $error = $isMurid ? "NIS sudah terdaftar. Periksa kembali NIS atau hubungi pengelola." : "Username atau email sudah terdaftar!";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $level = $jenis === "murid" ? "user" : "admin";
                // Akun Murid dan Guru langsung aktif.
                $approvalStatus = "approved";
                if ($isMurid) {
                    // Username internal tetap unik, sedangkan murid masuk memakai nama + NIS.
                    $internalUsername = "murid_" . $nis;
                    $stmt = $pdo->prepare("INSERT INTO login (nama, username, email, password, jenis, level, nis, status_persetujuan) VALUES (?, ?, NULL, ?, 'murid', 'user', ?, 'approved')");
                    $stmt->execute([$nama, $internalUsername, $hashedPassword, $nis]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO login (nama, username, email, password, jenis, level, nis, status_persetujuan) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)");
                    $stmt->execute([$nama, $username, $email, $hashedPassword, $jenis, $level, $approvalStatus]);
                }
                $success = $isMurid ? "Pendaftaran murid berhasil! Silakan masuk menggunakan nama, NIS, dan password." : "Pendaftaran " . ($jenis === "waka" ? "waka" : "guru") . " berhasil! Silakan masuk menggunakan username dan password.";
                $loginJenis = $jenis; $mode = "login"; $nama = ""; $username = ""; $email = ""; $nis = ""; $jenis = "murid"; $registrationCode = "";
            }
        }
    } elseif ($mode === "login") {
        $loginType = $_POST["login_type"] ?? "murid";
        if (!in_array($loginType, ["murid", "guru", "waka", "admin"], true)) $loginType = "murid";
        $loginJenis = $loginType;
        $loginNama = trim($_POST["login_nama"] ?? "");
        $loginNis = trim($_POST["login_nis"] ?? "");
        if ($password === "") {
            $error = "Password wajib diisi!";
        } elseif ($loginType === "murid") {
            if ($loginNama === "") $error = "Nama lengkap atau NIS dan password wajib diisi!";
            elseif (!preg_match('/^[A-Za-z0-9 ]+$/', $loginNama)) $error = "Nama/NIS hanya boleh berisi huruf dan angka.";
            else {
                // Satu kolom: boleh diisi NIS atau nama lengkap. Jika ada nama yang sama, cocokkan lewat password.
                $stmt = $pdo->prepare("SELECT * FROM login WHERE jenis='murid' AND (nis = ? OR nama = ?)");
                $stmt->execute([$loginNama, $loginNama]);
                $user = null;
                foreach ($stmt->fetchAll() as $row) {
                    if (password_verify($password, $row["password"])) { $user = $row; break; }
                }
                if ($user && ($user["level"] ?? "") === "user" && ($user["status_persetujuan"] ?? "approved") === "approved") {
                    startUserSession($user);
                    $dest = $dashboardFiles["murid"];
                    if (is_file(__DIR__ . "/" . $dest)) { header("Location: " . $dest); exit; }
                    $dashboardMissing = $dest;
                } else $error = "Nama/NIS atau password murid salah, atau akun belum disetujui.";
            }
        } else {
            // Guru, Waka, dan Petugas/Admin (super admin) masuk dengan username + password.
            if ($username === "") $error = "Username dan password wajib diisi!";
            else {
                $stmt = $pdo->prepare("SELECT * FROM login WHERE username = ? AND jenis = ? LIMIT 1");
                $stmt->execute([$username, $loginType]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user["password"])) {
                    $statusAkun = $user["status_persetujuan"] ?? "approved";
                    if ($statusAkun === "pending") {
                        $error = "Akun Anda belum aktif. Hubungi pengelola sekolah.";
                    } elseif ($statusAkun === "rejected") {
                        $error = "Pendaftaran akun ditolak. Hubungi pengelola sekolah.";
                    } else {
                        startUserSession($user);
                        $dest = $dashboardFiles[$user["jenis"]];
                        if (is_file(__DIR__ . "/" . $dest)) { header("Location: " . $dest); exit; }
                        $dashboardMissing = $dest;
                    }
                } else {
                    $error = "Username atau password salah!";
                }
            }
        }
    }
}

// Peran yang tampil aktif saat halaman dibuka.
$initRole = $mode === "register" ? $jenis : $loginJenis;
if (!in_array($initRole, ["murid", "guru", "waka", "admin"], true) || $initRole === "admin" || ($mode === "register" && $initRole === "waka")) $initRole = "murid";
?>
<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SLearning | Login & Register</title>

<style>
:root {
    --sage: #9CAF88;
    --sage-dark: #718765;
    --sage-deep: #53694b;
    --sage-light: #eef2e8;
    --yellow: #fdf1b0;
    --yellow-soft: #fffbe0;
    --yellow-border: #efe2a0;
    --ink: #344331;
    --muted: #71806d;
    --line: #dce3d3;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    min-height:100vh; color:var(--ink); font-family:'Open Sans',Arial,sans-serif;
    background:linear-gradient(125deg,#ffffff 0%,#fffdf0 50%,#eef3e8 100%);
}
button,input,select { font:inherit; }
a { color:inherit; text-decoration:none; }
.navbar {
    min-height:84px; padding:14px clamp(20px,5vw,76px); display:flex; align-items:center;
    justify-content:space-between; gap:20px; background:rgba(143,166,124,.97);
    border-bottom:1px solid rgba(255,255,255,.35); box-shadow:0 6px 24px rgba(55,75,45,.10);
}
.logo { display:flex; align-items:center; gap:13px; }
.logo img { width:50px; height:50px; object-fit:contain; background:#fff9dc; border-radius:12px; padding:3px; }
.logo h2 { color:#ffffff; font-size:clamp(20px,2.5vw,27px); line-height:1.15; font-weight:800; }
.logo p { color:#fdf1b0; font-size:11px; margin-top:5px; letter-spacing:1px; font-weight:700; }
.nav-menu { display:flex; align-items:center; gap:12px; }
.nav-button { display:inline-flex; align-items:center; justify-content:center; padding:12px 22px; border:1px solid rgba(255,255,255,.65); border-radius:999px; background:var(--yellow); color:var(--sage-deep); font-weight:700; transition:.2s; }
.nav-button:hover { background:#fff9dc; transform:translateY(-1px); }
.main {
    min-height:calc(100vh - 84px); padding:clamp(28px,5vw,64px) clamp(20px,5vw,76px);
    display:grid; grid-template-columns:minmax(0,1fr) minmax(360px,590px); align-items:center; gap:clamp(32px,5vw,70px);
    background-image:radial-gradient(circle at 0% 0%,rgba(156,175,136,.20),transparent 28%),radial-gradient(circle at 100% 100%,rgba(253,241,176,.70),transparent 32%);
}
.hero { max-width:610px; }
.badge { display:inline-block; padding:9px 15px; margin-bottom:22px; background:var(--yellow-soft); border:1px solid var(--yellow-border); border-radius:999px; color:var(--sage-deep); font-size:12px; font-weight:800; letter-spacing:.06em; }
.hero h1 { font-family:'Poppins',Arial,sans-serif; font-size:clamp(30px,4vw,52px); line-height:1.15; font-weight:800; letter-spacing:-1px; margin-bottom:22px; color:var(--sage-deep); }
.hero p { color:#65745f; font-size:clamp(15px,1.4vw,18px); line-height:1.8; max-width:530px; }
.features { display:flex; flex-wrap:wrap; gap:13px 20px; margin-top:28px; color:#596b52; font-size:13px; font-weight:600; }
.features span { color:var(--sage-dark); margin-right:6px; font-weight:900; }
.auth-card { width:100%; max-width:590px; justify-self:end; padding:clamp(24px,3vw,38px); background:#ffffff; border:1px solid #f0e6a8; border-radius:26px; box-shadow:0 22px 55px rgba(78,96,62,.13); backdrop-filter:blur(8px); }
.auth-header h2 { font-family:'Poppins',Arial,sans-serif; font-size:clamp(23px,2.5vw,29px); color:var(--sage-deep); margin-bottom:8px; }
.auth-header p { color:var(--muted); font-size:14px; line-height:1.6; margin-bottom:23px; }
.alert { padding:12px 14px; margin-bottom:17px; border-radius:12px; font-size:13px; line-height:1.5; }
.alert.error { background:#fff0ed; color:#a43b30; border:1px solid #efc5be; }
.alert.success { background:#edf7e9; color:#3e7041; border:1px solid #c3ddbc; }
.tabs { display:grid; grid-template-columns:1fr 1fr; gap:5px; padding:5px; margin-bottom:24px; background:#fff8d2; border:1px solid #f0e6a8; border-radius:999px; }
.tab { min-height:46px; padding:11px; border:0; border-radius:999px; background:transparent; color:#66785e; font-size:14px; font-weight:800; cursor:pointer; transition:.2s; }
.tab.active { background:var(--sage-dark); color:#ffffff; box-shadow:0 4px 10px rgba(83,105,75,.18); }
.tabs.role-tabs { grid-template-columns:repeat(var(--cols,4),1fr); }
.role-tab { padding:10px 4px; font-size:13px; }
.form-group { margin-bottom:17px; }
.form-group label { display:block; margin-bottom:8px; font-size:13px; font-weight:700; color:#465941; }
.input-box { min-height:52px; display:flex; align-items:center; padding:0 14px; border:1.5px solid #d6dfcd; border-radius:14px; background:#ffffff; transition:.2s; }
.input-box:focus-within { border-color:var(--sage-dark); background:#fffffa; box-shadow:0 0 0 3px rgba(156,175,136,.2); }
.input-icon { color:var(--sage-dark); margin-right:11px; font-size:17px; }
.input-box input { width:100%; min-width:0; height:50px; border:0; outline:0; background:transparent; color:var(--ink); font-size:14px; }
.input-box input::placeholder { color:#98a38f; }
.select-box { width:100%; min-height:52px; padding:0 14px; border:1.5px solid #d6dfcd; border-radius:14px; background:#ffffff; color:var(--ink); font-size:14px; outline:0; cursor:pointer; }
.select-box:focus { border-color:var(--sage-dark); box-shadow:0 0 0 3px rgba(156,175,136,.18); }
.password-toggle { flex-shrink:0; padding:5px; border:0; background:transparent; color:var(--sage-dark); cursor:pointer; }
.form-options { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:5px 0 22px; font-size:13px; }
.remember { display:flex; align-items:center; gap:8px; color:var(--muted); }
.remember input { accent-color:var(--sage-dark); }
.forgot { color:var(--sage-deep); font-weight:700; }
.submit-btn { width:100%; min-height:53px; border:0; border-radius:999px; background:var(--sage-dark); color:#ffffff; font-size:15px; font-weight:800; cursor:pointer; transition:.2s; box-shadow:0 7px 16px rgba(83,105,75,.16); }
.submit-btn:hover { background:var(--sage-deep); transform:translateY(-1px); }
.auth-footer { text-align:center; margin:23px 0; color:var(--muted); font-size:13px; }
.auth-footer button { border:0; background:transparent; color:var(--sage-deep); font-weight:800; cursor:pointer; font-size:13px; margin-left:4px; }
.security { border-top:1px solid var(--line); padding-top:17px; text-align:center; color:#7b8873; font-size:12px; }
@media(max-width:950px) { .main { grid-template-columns:1fr; gap:32px; padding-top:38px; } .hero { text-align:center; margin:auto; } .hero p { margin:auto; } .features { justify-content:center; } .auth-card { justify-self:center; } }
@media(max-width:520px) { .tabs.role-tabs { grid-template-columns:repeat(2,1fr) !important; } .navbar { min-height:72px; padding:12px 5%; } .logo img { width:42px; height:42px; } .logo p { font-size:9px; letter-spacing:.5px; } .nav-button { padding:10px 14px; font-size:12px; } .main { padding:30px 5%; } .hero h1 { font-size:32px; } .auth-card { padding:23px 18px; border-radius:20px; } .form-options { align-items:flex-start; font-size:12px; } }
</style>
</head>

<body>

<!-- NAVBAR -->

<header class="navbar">

    <div class="logo">
        <!-- Pastikan nama file gambar logo sesuai (contoh: image_a630c8.png) -->
        <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">

        <div>
            <h2>SLearning</h2>
            <p>SKAJU LEARNING • SMKN 7</p>
        </div>
    </div>

    <nav class="nav-menu">
        <a href="#auth" class="nav-button">Masuk / Daftar</a>
    </nav>

</header>

<!-- MAIN -->

<main class="main">

    <!-- HERO -->

    <section class="hero">

        <div class="badge">
            🟡 SKAJU LEARNING • SMK NEGERI 7 BATAM
        </div>

        <h1>
            SMK NEGERI 7 BATAM
            <br>
            INOVATIF DAN
            <br>
            BERBUDAYA
            <br>
            SMK BISA SMK HEBAT
        </h1>

        <p>
            SLearning menyatukan pembelajaran,
            pengumpulan tugas, dan pengelolaan kelas
            dalam satu tempat. Tanpa grup chat tercecer,
            tanpa deadline terlewat.
        </p>

        <div class="features">
            <div><span>✓</span> Belajar lebih teratur</div>
            <div><span>✓</span> Pengumpulan tugas</div>
            <div><span>✓</span> Gratis untuk warga sekolah</div>
        </div>

    </section>

    <!-- AUTHENTICATION CARD -->

    <section class="auth-card" id="auth">

        <div class="auth-header">
            <h2 id="formTitle">Masuk ke SLearning</h2>
            <p id="formSubtitle">
                Masuk untuk melanjutkan aktivitas belajarmu.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($dashboardMissing): ?>
            <div class="alert error">
                Login berhasil, tetapi halaman <b><?= htmlspecialchars($dashboardMissing) ?></b> belum ada di folder proyek.
                <a href="login.php?logout=1" style="text-decoration:underline;font-weight:700;">Keluar</a>
            </div>
        <?php endif; ?>

        <!-- TAB PERAN -->

        <div class="tabs role-tabs" id="roleTabs">
            <button type="button" class="tab role-tab" data-role="murid" onclick="setRole('murid')">Siswa</button>
            <button type="button" class="tab role-tab" data-role="guru" onclick="setRole('guru')">Guru</button>
            <button type="button" class="tab role-tab" data-role="waka" onclick="setRole('waka')">Waka</button>
        </div>

        <!-- FORM -->

        <form method="POST" action="" id="authForm">

            <input
                type="hidden"
                name="mode"
                id="mode"
                value="<?= htmlspecialchars($mode) ?>">

            <!-- REGISTER FIELDS -->

            <div id="registerFields">

                <input type="hidden" name="jenis" id="jenis" value="<?= htmlspecialchars($initRole) ?>">

                <!-- NAMA -->

                <div class="form-group">

                    <label for="nama">
                        Nama Lengkap
                    </label>

                    <div class="input-box">
                        <span class="input-icon">♙</span>

                        <input
                            type="text"
                            name="nama"
                            id="nama"
                            placeholder="Masukkan nama lengkap"
                            value="<?= htmlspecialchars($nama) ?>">
                    </div>

                </div>

                <!-- EMAIL -->

                <div class="form-group" id="nisRegisterGroup" style="display:none;">
                    <label for="nis">NIS (Nomor Induk Siswa)</label>
                    <div class="input-box">
                        <span class="input-icon">♙</span>
                        <input type="text" name="nis" id="nis" placeholder="Masukkan NIS (hanya angka)" value="<?= htmlspecialchars($nis) ?>" autocomplete="off" inputmode="numeric" pattern="[0-9]{3,30}" maxlength="30" title="NIS hanya boleh berisi angka" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    </div>
                </div>

                <div class="form-group" id="registerUsernameGroup"> 
                    <label for="register_username">Username</label>
                    <div class="input-box">
                        <span class="input-icon">♙</span>
                        <input type="text" name="register_username" id="register_username" placeholder="Masukkan username" autocomplete="username" value="<?= htmlspecialchars($username) ?>">
                    </div>
                </div>

                <div class="form-group" id="emailGroup">

                    <label for="email">
                        Gmail / Email
                    </label>

                    <div class="input-box">
                        <span class="input-icon">✉</span>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            placeholder="nama@gmail.com"
                            value="<?= htmlspecialchars($email) ?>">
                    </div>

                </div>

            </div>

            <input type="hidden" name="login_type" id="login_type" value="<?= htmlspecialchars($initRole) ?>">

            <div class="form-group" id="loginNamaGroup">
                <label for="login_nama">Nama Lengkap atau NIS</label>
                <div class="input-box">
                    <span class="input-icon">♙</span>
                    <input type="text" name="login_nama" id="login_nama" placeholder="Masukkan nama lengkap atau NIS" value="<?= htmlspecialchars($loginNama) ?>" autocomplete="username" maxlength="100" oninput="this.value=this.value.replace(/[^A-Za-z0-9 ]/g,'')">
                </div>
            </div>

            <div class="form-group" id="loginUsernameGroup">
                <label for="login_username" id="usernameLabel">Username</label>
                <div class="input-box">
                    <span class="input-icon">♙</span>
                    <input type="text" name="username" id="login_username" placeholder="Masukkan username" autocomplete="username" value="<?= htmlspecialchars($username) ?>">
                </div>
            </div>

            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-box">
                    <span class="input-icon">🔒</span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Masukkan password"
                        required>

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('password')">
                        👁
                    </button>
                </div>

            </div>

            <!-- CONFIRM PASSWORD -->

            <div class="form-group" id="confirmGroup">

                <label for="confirm_password">
                    Konfirmasi Password
                </label>

                <div class="input-box">
                    <span class="input-icon">🔒</span>

                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        placeholder="Ulangi password">

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('confirm_password')">
                        👁
                    </button>
                </div>

            </div>

            <!-- LOGIN OPTIONS -->

            <div class="form-options" id="loginOptions">

                <label class="remember">
                    <input type="checkbox" name="remember" value="1" <?= isset($_COOKIE["slearning_remember"]) ? "checked" : "" ?>>
                    Ingat saya
                </label>

                <a href="#" class="forgot">
                    Lupa password?
                </a>

            </div>

            <!-- SUBMIT -->

            <button
                type="submit"
                class="submit-btn"
                id="submitBtn">

                Masuk

            </button>

        </form>

        <!-- FOOTER -->

        <div class="auth-footer">
            <div id="footerNormal">
                <span id="footerText">Belum punya akun?</span>
                <button type="button" id="footerButton" onclick="setMode(currentMode === 'register' ? 'login' : 'register')">Daftar gratis</button>
            </div>
            <div id="footerAdmin" style="display:none;">
                Akun Petugas/Admin dibuat oleh pengelola sistem, jadi tidak ada pendaftaran untuk peran ini.
            </div>
        </div>

        <div class="security">
            🛡 Akses khusus warga SMK Negeri 7 Batam
        </div>

    </section>

</main>

<script>
let currentMode = <?= json_encode($mode) ?>;
let currentRole = <?= json_encode($initRole) ?>;

function setRole(role) {
    if (role === "admin" || (currentMode === "register" && role === "waka")) role = "murid";
    currentRole = role;
    document.getElementById("jenis").value = role;
    document.getElementById("login_type").value = role;
    document.querySelectorAll(".role-tab").forEach(btn => btn.classList.toggle("active", btn.dataset.role === role));
    const isAdmin = role === "admin";
    document.getElementById("footerNormal").style.display = isAdmin ? "none" : "block";
    document.getElementById("footerAdmin").style.display = isAdmin ? "block" : "none";
    document.getElementById("login_username").placeholder = isAdmin ? "Username petugas / admin" : "Masukkan username";
    updateRegistrationFields();
    updateLoginFields();
}

function setMode(mode) {
    currentMode = mode;
    const isRegister = mode === "register";
    document.getElementById("mode").value = mode;
    document.getElementById("formTitle").textContent = isRegister ? "Buat Akun SLearning" : "Masuk ke SLearning";
    document.getElementById("formSubtitle").textContent = isRegister ? "Pilih jenis akun, lalu lengkapi data berikut." : "Pilih jenis akunmu, lalu masuk.";
    document.getElementById("registerFields").style.display = isRegister ? "block" : "none";
    document.getElementById("confirmGroup").style.display = isRegister ? "block" : "none";
    document.getElementById("loginOptions").style.display = isRegister ? "none" : "flex";
    document.getElementById("confirm_password").required = isRegister;
    document.getElementById("submitBtn").textContent = isRegister ? "Daftar Sekarang →" : "Masuk";
    document.getElementById("footerText").textContent = isRegister ? "Sudah punya akun?" : "Belum punya akun?";
    document.getElementById("footerButton").textContent = isRegister ? "Masuk di sini" : "Daftar gratis";
    document.getElementById("password").autocomplete = isRegister ? "new-password" : "current-password";
    // Tab Petugas/Admin dihapus; tab Waka disembunyikan saat daftar.
    document.querySelector('.role-tab[data-role="waka"]').style.display = isRegister ? "none" : "";
    document.getElementById("roleTabs").style.setProperty("--cols", isRegister ? 2 : 3);
    setRole(currentRole);
}

function updateRegistrationFields() {
    const isMurid = currentRole === "murid";
    const isRegister = currentMode === "register";
    document.getElementById("nisRegisterGroup").style.display = isRegister && isMurid ? "block" : "none";
    document.getElementById("emailGroup").style.display = isRegister && !isMurid ? "block" : "none";
    document.getElementById("registerUsernameGroup").style.display = isRegister && !isMurid ? "block" : "none";
    document.getElementById("nis").required = isRegister && isMurid;
    document.getElementById("email").required = isRegister && !isMurid;
    document.getElementById("register_username").required = isRegister && !isMurid;
}

function updateLoginFields() {
    const student = currentRole === "murid";
    const isLogin = currentMode === "login";
    document.getElementById("loginNamaGroup").style.display = isLogin && student ? "block" : "none";
    document.getElementById("loginUsernameGroup").style.display = isLogin && !student ? "block" : "none";
    document.getElementById("login_nama").required = isLogin && student;
    document.getElementById("login_username").required = isLogin && !student;
}

function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}
setMode(currentMode);
</script>

</body>
</html>
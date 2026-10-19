<?php
session_start();

// Kalau sudah login, langsung arahkan sesuai jenis akun
if (isset($_SESSION["user_id"]) && isset($_SESSION["jenis"])) {
    if ($_SESSION["jenis"] === "guru") {
        header("Location: guru.php");
    } else {
        header("Location: murid.php");
    }
    exit;
}

/* =====================================
   DATABASE CONNECTION
===================================== */

$host = "localhost";
$dbname = "slearning_db";
$dbuser = "root";
$dbpass = "";

$error = "";
$success = "";

$mode = $_POST["mode"] ?? "login";

$nama = "";
$username = $_COOKIE["slearning_remember"] ?? "";
$email = "";
$jenis = "murid";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Membuat tabel otomatis jika belum ada (sesuai slearning_db.login)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            jenis ENUM('guru','murid') NOT NULL,
            level ENUM('admin','user') NOT NULL DEFAULT 'user'
        )
    ");

} catch (PDOException $e) {
    die("Koneksi database gagal. Pastikan MySQL aktif dan database slearning_db sudah dibuat.");
}


/* =====================================
   FORM PROCESSING
===================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $mode = $_POST["mode"] ?? "login";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($mode === "register") {

        $nama = trim($_POST["nama"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $jenis = $_POST["jenis"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";

        // Validasi
        if (
            empty($nama) ||
            empty($username) ||
            empty($email) ||
            empty($password) ||
            empty($confirm)
        ) {
            $error = "Semua kolom wajib diisi!";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format Gmail atau email tidak valid!";

        } elseif (!in_array($jenis, ["guru", "murid"], true)) {
            $error = "Pilih jenis pendaftaran yang valid!";

        } elseif (strlen($password) < 8) {
            $error = "Password minimal 8 karakter!";

        } elseif ($password !== $confirm) {
            $error = "Konfirmasi password tidak cocok!";

        } else {

            // Cek username atau email sudah digunakan
            $check = $pdo->prepare("
                SELECT id FROM login
                WHERE username = ? OR email = ?
            ");

            $check->execute([$username, $email]);

            if ($check->fetch()) {

                $error = "Username atau email sudah terdaftar!";

            } else {

                // Hash password sebelum disimpan
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    INSERT INTO login
                    (nama, username, email, password, jenis, level)
                    VALUES (?, ?, ?, ?, ?, 'user')
                ");

                $stmt->execute([
                    $nama,
                    $username,
                    $email,
                    $hashedPassword,
                    $jenis
                ]);

                $success = "Pendaftaran berhasil! Silakan masuk menggunakan username dan password.";

                $mode = "login";
                $nama = "";
                $username = "";
                $email = "";
                $jenis = "murid";
            }
        }

    } elseif ($mode === "login") {

        if (empty($username) || empty($password)) {

            $error = "Username dan password wajib diisi!";

        } else {

            // Cari akun berdasarkan username di tabel login
            $stmt = $pdo->prepare("
                SELECT * FROM login
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["nama"] = $user["nama"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["email"] = $user["email"] ?? "";
                $_SESSION["jenis"] = $user["jenis"];
                $_SESSION["level"] = $user["level"] ?? "user";

                // Ingat username selama 30 hari (tidak menyimpan password).
                $cookieOptions = [
                    "expires" => !empty($_POST["remember"]) ? time() + (30 * 86400) : time() - 3600,
                    "path" => "/",
                    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
                    "httponly" => true,
                    "samesite" => "Lax"
                ];
                if (!empty($_POST["remember"])) {
                    setcookie("slearning_remember", $username, $cookieOptions);
                } else {
                    setcookie("slearning_remember", "", $cookieOptions);
                }

                // Langsung pindahkan ke halaman sesuai jenis akun
                if ($user["jenis"] === "guru") {
                    header("Location: guru.php");
                } else {
                    header("Location: murid.php");
                }
                exit;

            } else {

                $error = "Username atau password salah!";

            }
        }
    }
}
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
    --sage-light: #e7eddf;
    --yellow: #fff3b0;
    --yellow-soft: #fff9dc;
    --yellow-border: #e7d77b;
    --ink: #344331;
    --muted: #71806d;
    --line: #dce3d3;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    min-height:100vh; color:var(--ink); font-family:'Open Sans',Arial,sans-serif;
    background:linear-gradient(125deg,#fffbe4 0%,#f7f6d9 48%,#e9eedf 100%);
}
button,input,select { font:inherit; }
a { color:inherit; text-decoration:none; }
.navbar {
    min-height:84px; padding:14px clamp(20px,5vw,76px); display:flex; align-items:center;
    justify-content:space-between; gap:20px; background:rgba(132,153,116,.96);
    border-bottom:1px solid rgba(255,255,255,.35); box-shadow:0 6px 24px rgba(55,75,45,.10);
}
.logo { display:flex; align-items:center; gap:13px; }
.logo img { width:50px; height:50px; object-fit:contain; background:#fff9dc; border-radius:12px; padding:3px; }
.logo h2 { color:#fffbea; font-size:clamp(20px,2.5vw,27px); line-height:1.15; font-weight:800; }
.logo p { color:#fff3b0; font-size:11px; margin-top:5px; letter-spacing:1px; font-weight:700; }
.nav-menu { display:flex; align-items:center; gap:12px; }
.nav-button { display:inline-flex; align-items:center; justify-content:center; padding:12px 22px; border:1px solid rgba(255,255,255,.65); border-radius:999px; background:var(--yellow); color:var(--sage-deep); font-weight:700; transition:.2s; }
.nav-button:hover { background:#fff9dc; transform:translateY(-1px); }
.main {
    min-height:calc(100vh - 84px); padding:clamp(28px,5vw,64px) clamp(20px,5vw,76px);
    display:grid; grid-template-columns:minmax(0,1fr) minmax(360px,590px); align-items:center; gap:clamp(32px,5vw,70px);
    background-image:radial-gradient(circle at 0% 0%,rgba(156,175,136,.20),transparent 28%),radial-gradient(circle at 100% 100%,rgba(255,243,176,.55),transparent 30%);
}
.hero { max-width:610px; }
.badge { display:inline-block; padding:9px 15px; margin-bottom:22px; background:var(--yellow-soft); border:1px solid var(--yellow-border); border-radius:999px; color:var(--sage-deep); font-size:12px; font-weight:800; letter-spacing:.06em; }
.hero h1 { font-family:'Poppins',Arial,sans-serif; font-size:clamp(30px,4vw,52px); line-height:1.15; font-weight:800; letter-spacing:-1px; margin-bottom:22px; color:var(--sage-deep); }
.hero p { color:#65745f; font-size:clamp(15px,1.4vw,18px); line-height:1.8; max-width:530px; }
.features { display:flex; flex-wrap:wrap; gap:13px 20px; margin-top:28px; color:#596b52; font-size:13px; font-weight:600; }
.features span { color:var(--sage-dark); margin-right:6px; font-weight:900; }
.auth-card { width:100%; max-width:590px; justify-self:end; padding:clamp(24px,3vw,38px); background:rgba(255,253,235,.92); border:1px solid rgba(221,214,155,.85); border-radius:26px; box-shadow:0 22px 55px rgba(78,96,62,.13); backdrop-filter:blur(8px); }
.auth-header h2 { font-family:'Poppins',Arial,sans-serif; font-size:clamp(23px,2.5vw,29px); color:var(--sage-deep); margin-bottom:8px; }
.auth-header p { color:var(--muted); font-size:14px; line-height:1.6; margin-bottom:23px; }
.alert { padding:12px 14px; margin-bottom:17px; border-radius:12px; font-size:13px; line-height:1.5; }
.alert.error { background:#fff0ed; color:#a43b30; border:1px solid #efc5be; }
.alert.success { background:#edf7e9; color:#3e7041; border:1px solid #c3ddbc; }
.tabs { display:grid; grid-template-columns:1fr 1fr; gap:5px; padding:5px; margin-bottom:24px; background:#e8eddf; border:1px solid #dce3d3; border-radius:999px; }
.tab { min-height:46px; padding:11px; border:0; border-radius:999px; background:transparent; color:#66785e; font-size:14px; font-weight:800; cursor:pointer; transition:.2s; }
.tab.active { background:var(--sage-dark); color:#fffbea; box-shadow:0 4px 10px rgba(83,105,75,.18); }
.form-group { margin-bottom:17px; }
.form-group label { display:block; margin-bottom:8px; font-size:13px; font-weight:700; color:#465941; }
.input-box { min-height:52px; display:flex; align-items:center; padding:0 14px; border:1.5px solid #d6dfcd; border-radius:14px; background:rgba(255,255,255,.55); transition:.2s; }
.input-box:focus-within { border-color:var(--sage-dark); background:#fffef4; box-shadow:0 0 0 3px rgba(156,175,136,.2); }
.input-icon { color:var(--sage-dark); margin-right:11px; font-size:17px; }
.input-box input { width:100%; min-width:0; height:50px; border:0; outline:0; background:transparent; color:var(--ink); font-size:14px; }
.input-box input::placeholder { color:#98a38f; }
.select-box { width:100%; min-height:52px; padding:0 14px; border:1.5px solid #d6dfcd; border-radius:14px; background:rgba(255,255,255,.6); color:var(--ink); font-size:14px; outline:0; cursor:pointer; }
.select-box:focus { border-color:var(--sage-dark); box-shadow:0 0 0 3px rgba(156,175,136,.18); }
.password-toggle { flex-shrink:0; padding:5px; border:0; background:transparent; color:var(--sage-dark); cursor:pointer; }
.form-options { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:5px 0 22px; font-size:13px; }
.remember { display:flex; align-items:center; gap:8px; color:var(--muted); }
.remember input { accent-color:var(--sage-dark); }
.forgot { color:var(--sage-deep); font-weight:700; }
.submit-btn { width:100%; min-height:53px; border:0; border-radius:999px; background:var(--sage-dark); color:#fffbea; font-size:15px; font-weight:800; cursor:pointer; transition:.2s; box-shadow:0 7px 16px rgba(83,105,75,.16); }
.submit-btn:hover { background:var(--sage-deep); transform:translateY(-1px); }
.auth-footer { text-align:center; margin:23px 0; color:var(--muted); font-size:13px; }
.auth-footer button { border:0; background:transparent; color:var(--sage-deep); font-weight:800; cursor:pointer; font-size:13px; margin-left:4px; }
.security { border-top:1px solid var(--line); padding-top:17px; text-align:center; color:#7b8873; font-size:12px; }
@media(max-width:950px) { .main { grid-template-columns:1fr; gap:32px; padding-top:38px; } .hero { text-align:center; margin:auto; } .hero p { margin:auto; } .features { justify-content:center; } .auth-card { justify-self:center; } }
@media(max-width:520px) { .navbar { min-height:72px; padding:12px 5%; } .logo img { width:42px; height:42px; } .logo p { font-size:9px; letter-spacing:.5px; } .nav-button { padding:10px 14px; font-size:12px; } .main { padding:30px 5%; } .hero h1 { font-size:32px; } .auth-card { padding:23px 18px; border-radius:20px; } .form-options { align-items:flex-start; font-size:12px; } }
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

        <!-- TABS -->

        <div class="tabs">

            <button
                type="button"
                id="loginTab"
                class="tab"
                onclick="setMode('login')">
                Masuk
            </button>

            <button
                type="button"
                id="registerTab"
                class="tab"
                onclick="setMode('register')">
                Daftar
            </button>

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

                <!-- DAFTAR SEBAGAI -->

                <div class="form-group">

                    <label for="jenis">
                        Daftar sebagai apa?
                    </label>

                    <select
                        name="jenis"
                        id="jenis"
                        class="select-box">

                        <option value="guru"
                            <?= $jenis === "guru" ? "selected" : "" ?>>
                            Guru
                        </option>

                        <option value="murid"
                            <?= $jenis === "murid" ? "selected" : "" ?>>
                            Murid
                        </option>

                    </select>

                </div>

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

                <div class="form-group">

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

            <!-- USERNAME -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <div class="input-box">
                    <span class="input-icon">♙</span>

                    <input
                        type="text"
                        name="username"
                        id="username"
                        placeholder="Masukkan username"
                        autocomplete="username"
                        value="<?= htmlspecialchars($username) ?>"
                        required>
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

            <span id="footerText">
                Belum punya akun?
            </span>

            <button
                type="button"
                id="footerButton"
                onclick="setMode('register')">

                Daftar gratis

            </button>

        </div>

        <div class="security">
            🛡 Akses khusus warga SMK Negeri 7 Batam
        </div>

    </section>

</main>

<script>

// MODE AWAL
let currentMode = <?= json_encode($mode) ?>;


// =====================================
// GANTI LOGIN / REGISTER
// =====================================

function setMode(mode) {

    currentMode = mode;

    document.getElementById("mode").value = mode;

    const loginTab = document.getElementById("loginTab");
    const registerTab = document.getElementById("registerTab");

    const registerFields = document.getElementById("registerFields");
    const confirmGroup = document.getElementById("confirmGroup");
    const loginOptions = document.getElementById("loginOptions");

    const nama = document.getElementById("nama");
    const email = document.getElementById("email");
    const confirm = document.getElementById("confirm_password");

    const title = document.getElementById("formTitle");
    const subtitle = document.getElementById("formSubtitle");
    const submit = document.getElementById("submitBtn");

    const footerText = document.getElementById("footerText");
    const footerButton = document.getElementById("footerButton");

    loginTab.classList.toggle("active", mode === "login");
    registerTab.classList.toggle("active", mode === "register");

    if (mode === "login") {

        title.textContent = "Masuk ke SLearning";
        subtitle.textContent =
            "Masuk menggunakan username dan password.";

        registerFields.style.display = "none";
        confirmGroup.style.display = "none";
        loginOptions.style.display = "flex";

        nama.required = false;
        email.required = false;
        confirm.required = false;

        submit.textContent = "Masuk";

        footerText.textContent = "Belum punya akun?";
        footerButton.textContent = "Daftar gratis";

        footerButton.onclick = function() {
            setMode("register");
        };

        document.getElementById("password").autocomplete =
            "current-password";

    } else {

        title.textContent = "Buat Akun SLearning";
        subtitle.textContent =
            "Lengkapi data berikut untuk mendaftar.";

        registerFields.style.display = "block";
        confirmGroup.style.display = "block";
        loginOptions.style.display = "none";

        nama.required = true;
        email.required = true;
        confirm.required = true;

        submit.textContent = "Daftar Sekarang →";

        footerText.textContent = "Sudah punya akun?";
        footerButton.textContent = "Masuk di sini";

        footerButton.onclick = function() {
            setMode("login");
        };

        document.getElementById("password").autocomplete =
            "new-password";
    }
}


// =====================================
// TAMPILKAN PASSWORD
// =====================================

function togglePassword(id) {

    const input = document.getElementById(id);

    input.type =
        input.type === "password"
        ? "text"
        : "password";
}


// =====================================
// INITIAL DISPLAY
// =====================================

setMode(currentMode);

</script>

</body>
</html>
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
$username = "";
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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #fff;
    color: #171717;
}

/* NAVBAR */

.navbar {
    height: 90px;
    padding: 0 8%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border-bottom: 1px solid #eee;
}

.logo {
    display: flex;
    align-items: center;
    gap: 14px;
}

.logo img {
    width: 50px;
    height: 50px;
    object-fit: contain;
}

.logo h2 {
    font-size: 23px;
}

.logo p {
    color: #8490a0;
    font-size: 12px;
    margin-top: 5px;
    letter-spacing: 1px;
}

.nav-menu {
    display: flex;
    align-items: center;
}

.nav-menu .nav-button {
    padding: 15px 25px;
    border: 1px solid #ddd;
    border-radius: 13px;
    color: #171717;
    text-decoration: none;
    font-weight: 600;
}

/* MAIN */

.main {
    min-height: calc(100vh - 90px);
    padding: 55px 8%;

    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 65px;

    background-color: #fff;
    background-image:
        linear-gradient(#eeeeee 1px, transparent 1px),
        linear-gradient(90deg, #eeeeee 1px, transparent 1px),
        linear-gradient(110deg, #fff 45%, #fff8dc 100%);
    background-size: 52px 52px, 52px 52px, cover;
}

/* HERO */

.hero {
    max-width: 600px;
}

.badge {
    display: inline-block;
    padding: 12px 18px;
    margin-bottom: 30px;
    background: #fff7d6;
    border: 1px solid #f6d45c;
    border-radius: 30px;
    color: #745b00;
    font-size: 14px;
}

.hero h1 {
    font-size: clamp(32px, 4vw, 55px);
    line-height: 1.12;
    font-weight: 800;
    letter-spacing: -1px;
    margin-bottom: 25px;
    text-transform: uppercase;
}

.hero p {
    color: #657286;
    font-size: 19px;
    line-height: 1.7;
    max-width: 520px;
}

.features {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 35px;
    color: #657286;
    font-size: 14px;
}

.features span {
    color: #e5a900;
    margin-right: 6px;
}

/* AUTH CARD */

.auth-card {
    width: 100%;
    max-width: 590px;
    justify-self: end;

    padding: 35px;
    background: rgba(255,255,255,.97);
    border: 1px solid #e3e6eb;
    border-radius: 25px;
    box-shadow: 0 20px 55px rgba(0,0,0,.08);
}

.auth-header h2 {
    font-size: 29px;
    margin-bottom: 10px;
}

.auth-header p {
    color: #8490a0;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 25px;
}

/* ALERT */

.alert {
    padding: 13px 15px;
    margin-bottom: 18px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.5;
}

.alert.error {
    background: #fff0f0;
    color: #b42318;
    border: 1px solid #f5c2c0;
}

.alert.success {
    background: #edfff4;
    color: #18794e;
    border: 1px solid #b7e4c7;
}

/* TABS */

.tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 5px;
    margin-bottom: 25px;
    background: #f3f4f6;
    border-radius: 13px;
}

.tab {
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: #657286;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.tab.active {
    background: white;
    color: #171717;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
}

/* FORM */

.form-group {
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    margin-bottom: 9px;
    font-size: 14px;
    font-weight: 600;
    color: #283345;
}

.input-box {
    height: 54px;
    display: flex;
    align-items: center;
    padding: 0 15px;
    border: 1.5px solid #dce1e8;
    border-radius: 11px;
    transition: .2s;
}

.input-box:focus-within {
    border-color: #ffb900;
    box-shadow: 0 0 0 3px rgba(255,193,7,.12);
}

.input-icon {
    color: #8490a0;
    margin-right: 12px;
    font-size: 18px;
}

.input-box input {
    width: 100%;
    height: 100%;
    border: none;
    outline: none;
    background: transparent;
    font-size: 14px;
    color: #222;
}

.input-box input::placeholder {
    color: #9aa4b2;
}

/* SELECT */

.select-box {
    width: 100%;
    height: 54px;
    padding: 0 14px;
    border: 1.5px solid #dce1e8;
    border-radius: 11px;
    background: white;
    color: #283345;
    font-size: 14px;
    outline: none;
    cursor: pointer;
}

.select-box:focus {
    border-color: #ffb900;
}

/* PASSWORD */

.password-toggle {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 17px;
    color: #8490a0;
}

/* OPTIONS */

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 5px 0 24px;
    font-size: 14px;
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #657286;
}

.remember input {
    accent-color: #ffb900;
}

.forgot {
    color: #b88900;
    text-decoration: none;
    font-weight: 600;
}

/* BUTTON */

.submit-btn {
    width: 100%;
    height: 54px;
    border: none;
    border-radius: 12px;
    background: #ffbd00;
    color: #171717;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.submit-btn:hover {
    background: #f0ae00;
    transform: translateY(-1px);
}

/* FOOTER */

.auth-footer {
    text-align: center;
    margin: 27px 0;
    color: #657286;
    font-size: 14px;
}

.auth-footer button {
    border: none;
    background: transparent;
    color: #bd8b00;
    font-weight: 700;
    cursor: pointer;
    font-size: 14px;
}

.security {
    border-top: 1px solid #eee;
    padding-top: 22px;
    text-align: center;
    color: #8490a0;
    font-size: 13px;
}

/* RESPONSIVE */

@media(max-width: 950px) {
    .main {
        grid-template-columns: 1fr;
        padding: 45px 6%;
        gap: 40px;
    }

    .hero {
        text-align: center;
        margin: auto;
    }

    .hero p {
        margin: auto;
    }

    .features {
        justify-content: center;
    }

    .auth-card {
        justify-self: center;
    }
}

@media(max-width: 500px) {
    .navbar {
        height: 75px;
        padding: 0 5%;
    }

    .logo img {
        width: 40px;
        height: 40px;
    }

    .logo h2 {
        font-size: 19px;
    }

    .logo p {
        font-size: 10px;
    }

    .main {
        padding: 35px 5%;
    }

    .hero h1 {
        font-size: 35px;
    }

    .hero p {
        font-size: 16px;
    }

    .auth-card {
        padding: 25px 20px;
    }

    .auth-header h2 {
        font-size: 25px;
    }

    .security {
        font-size: 11px;
    }
}
</style>
</head>

<body>

<!-- NAVBAR -->

<header class="navbar">

    <div class="logo">
        <!-- Pastikan nama file gambar logo sesuai (contoh: image_a630c8.png) -->
        <img src="image_a630c8.png" alt="Logo SMK Negeri 7 Batam">

        <div>
            <h2>SLearning</h2>
            <p>SKAJU LEARNING • SMKN 7</p>
        </div>
    </div>

    <nav class="nav-menu">
        <a href="#auth" class="nav-button">Masuk</a>
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
                    <input type="checkbox" name="remember">
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
<?php
session_start();
require_once __DIR__ . '/config/helpers.php';

// Kalau sudah login, langsung arahkan sesuai jenis akun
if (isset($_SESSION["user_id"]) && isset($_SESSION["jenis"])) {
    if ($_SESSION["jenis"] === "guru") { header("Location: guru.php"); }
    else { header("Location: murid.php"); }
    exit;
}

$host = "localhost"; $dbname = "slearning_db"; $dbuser = "root"; $dbpass = "";
$error = ""; $success = "";
$mode = $_POST["mode"] ?? $_GET["mode"] ?? "login";
if (!in_array($mode, ["login","register"], true)) $mode = "login";
$nama = ""; $username = $_COOKIE["slearning_remember"] ?? ""; $email = ""; $jenis = "murid";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS login (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL, username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
        jenis ENUM('guru','murid') NOT NULL, level ENUM('admin','user') NOT NULL DEFAULT 'user'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    die("Koneksi database gagal. Pastikan MySQL aktif dan database slearning_db sudah dibuat.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mode = $_POST["mode"] ?? "login";
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    if ($mode === "register") {
        $nama = trim($_POST["nama"] ?? ""); $email = trim($_POST["email"] ?? "");
        $jenis = $_POST["jenis"] ?? ""; $confirm = $_POST["confirm_password"] ?? "";
        if (empty($nama)||empty($username)||empty($email)||empty($password)||empty($confirm)) $error = "Semua kolom wajib diisi.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Format email tidak valid.";
        elseif (!in_array($jenis, ["guru","murid"], true)) $error = "Pilih jenis pendaftaran yang valid.";
        elseif (strlen($username) < 4) $error = "Username minimal 4 karakter.";
        elseif (strlen($password) < 8) $error = "Password minimal 8 karakter.";
        elseif ($password !== $confirm) $error = "Konfirmasi password tidak cocok.";
        else {
            $check = $pdo->prepare("SELECT id FROM login WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) $error = "Username atau email sudah terdaftar. Silakan masuk.";
            else {
                $stmt = $pdo->prepare("INSERT INTO login (nama, username, email, password, jenis, level) VALUES (?, ?, ?, ?, ?, 'user')");
                $stmt->execute([$nama, $username, $email, password_hash($password, PASSWORD_DEFAULT), $jenis]);
                $success = "Pendaftaran berhasil. Silakan masuk menggunakan username dan password.";
                $mode = "login"; $nama=""; $email=""; $jenis="murid";
            }
        }
    } elseif ($mode === "login") {
        if (empty($username) || empty($password)) $error = "Username dan password wajib diisi.";
        else {
            $stmt = $pdo->prepare("SELECT * FROM login WHERE username = ? LIMIT 1");
            $stmt->execute([$username]); $user = $stmt->fetch();
            if ($user && password_verify($password, $user["password"])) {
                session_regenerate_id(true);
                $_SESSION["user_id"]=$user["id"]; $_SESSION["nama"]=$user["nama"];
                $_SESSION["username"]=$user["username"]; $_SESSION["email"]=$user["email"] ?? "";
                $_SESSION["jenis"]=$user["jenis"]; $_SESSION["level"]=$user["level"] ?? "user";
                if (!empty($_POST["remember"])) setcookie("slearning_remember", $username, time()+30*86400, "/");
                else setcookie("slearning_remember", "", time()-3600, "/");
                header("Location: " . ($user["jenis"] === "guru" ? "guru.php" : "murid.php"));
                exit;
            } else $error = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — SLearning | SMK Negeri 7 Batam</title>
<meta name="description" content="Masuk atau daftar ke SLearning SMK Negeri 7 Batam.">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<link rel="stylesheet" href="assets/css/slearning.css">
</head>
<body>
<header class="navbar"><div class="nav-inner">
  <a class="brand" href="index.php" aria-label="SLearning Beranda">
    <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">
    <span class="brand-text"><strong>SLearning</strong><span>SKAJU Learning • SMKN 7</span></span>
  </a>
  <div class="nav-right" id="navRight">
    <a class="btn btn-ghost btn-sm" href="index.php"><?= svg_icon('arrow-left',16) ?> Beranda</a>
    <button class="btn btn-yellow btn-sm" type="button" onclick="setMode('register')">Daftar Gratis</button>
  </div>
  <button class="burger" id="burger" aria-label="Buka menu" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></header>

<main class="page page-grid-bg"><div class="container"><div class="auth-layout">
  <section class="auth-hero">
    <div>
      <span class="eyebrow">SKAJU Learning • SMK Negeri 7 Batam</span>
      <h1>SMK Negeri 7 Batam inovatif dan berbudaya</h1>
      <p>SLearning menyatukan pembelajaran, pengumpulan tugas, absensi, dan Quiziz dalam satu tempat. Tanpa grup chat tercecer, tanpa deadline terlewat.</p>
      <div class="auth-points">
        <span><?= svg_icon('check-circle',16) ?> Belajar lebih teratur</span>
        <span><?= svg_icon('check-circle',16) ?> Pengumpulan tugas terpantau</span>
        <span><?= svg_icon('check-circle',16) ?> Gratis warga sekolah</span>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
        <div class="stat-card" style="flex:1;min-width:140px"><div class="stat-icon"><?= svg_icon('users',20) ?></div><div><div class="stat-number">6</div><div class="stat-label">Program Keahlian</div></div></div>
        <div class="stat-card" style="flex:1;min-width:140px"><div class="stat-icon"><?= svg_icon('shield',20) ?></div><div><div class="stat-number">24/7</div><div class="stat-label">Akses Belajar</div></div></div>
      </div>
    </div>
  </section>

  <section class="auth-card" id="auth" aria-label="Form autentikasi">
    <div style="margin-bottom:18px"><h2 id="formTitle" style="font-size:26px">Masuk ke SLearning</h2>
    <p id="formSubtitle" style="color:var(--muted);font-size:13.5px;margin-top:4px">Masuk menggunakan username dan password sekolah.</p></div>
    <?php if ($error): ?><div class="alert error" role="alert"><?= svg_icon('info',18) ?><span><?= e($error) ?></span></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success" role="alert"><?= svg_icon('check-circle',18) ?><span><?= e($success) ?></span></div><?php endif; ?>
    <div class="tabs" role="tablist">
      <button type="button" id="loginTab" class="tab" onclick="setMode('login')">Masuk</button>
      <button type="button" id="registerTab" class="tab" onclick="setMode('register')">Daftar</button>
    </div>
    <form method="POST" action="login.php" id="authForm" novalidate>
      <input type="hidden" name="mode" id="mode" value="<?= e($mode) ?>">
      <div id="registerFields">
        <div class="form-group"><label for="jenis">Daftar sebagai</label>
          <select name="jenis" id="jenis" class="form-control">
            <option value="murid" <?= $jenis==="murid"?"selected":"" ?>>Murid</option>
            <option value="guru" <?= $jenis==="guru"?"selected":"" ?>>Guru</option>
          </select>
        </div>
        <div class="form-group"><label for="nama">Nama lengkap</label>
          <div class="input-wrap"><?= svg_icon('user',18) ?><input type="text" name="nama" id="nama" placeholder="cth: Ahmad Fauzi" value="<?= e($nama) ?>" autocomplete="name"></div>
        </div>
        <div class="form-group"><label for="email">Email</label>
          <div class="input-wrap"><?= svg_icon('mail',18) ?><input type="email" name="email" id="email" placeholder="nama@gmail.com" value="<?= e($email) ?>" autocomplete="email"></div>
        </div>
      </div>
      <div class="form-group"><label for="username">Username</label>
        <div class="input-wrap"><?= svg_icon('user',18) ?><input type="text" name="username" id="username" placeholder="cth: ahmad_12" value="<?= e($username) ?>" autocomplete="username" required></div>
      </div>
      <div class="form-group"><label for="password">Password</label>
        <div class="input-wrap"><?= svg_icon('lock',18) ?><input type="password" name="password" id="password" placeholder="Minimal 8 karakter" required autocomplete="current-password">
        <button type="button" onclick="togglePassword('password',this)" aria-label="Tampilkan password" style="border:none;background:none;cursor:pointer;color:#8490a0;display:flex"><?= svg_icon('eye',18) ?></button></div>
      </div>
      <div class="form-group" id="confirmGroup"><label for="confirm_password">Konfirmasi password</label>
        <div class="input-wrap"><?= svg_icon('lock',18) ?><input type="password" name="confirm_password" id="confirm_password" placeholder="Ulangi password" autocomplete="new-password">
        <button type="button" onclick="togglePassword('confirm_password',this)" aria-label="Tampilkan password" style="border:none;background:none;cursor:pointer;color:#8490a0;display:flex"><?= svg_icon('eye',18) ?></button></div>
      </div>
      <div id="loginOptions" style="display:flex;justify-content:space-between;align-items:center;margin:4px 0 20px;font-size:13.5px">
        <label style="display:flex;align-items:center;gap:8px;color:var(--muted);cursor:pointer"><input type="checkbox" name="remember" style="accent-color:#b88900" <?= isset($_COOKIE['slearning_remember'])?'checked':'' ?>> Ingat saya</label>
        <a href="#" onclick="alert('Hubungi wali kelas / admin sekolah untuk reset password.');return false;" style="color:#92600a;font-weight:700">Lupa password?</a>
      </div>
      <button type="submit" class="btn btn-yellow btn-block" id="submitBtn"><?= svg_icon('login',17) ?> <span>Masuk</span></button>
    </form>
    <div style="text-align:center;margin:22px 0;color:var(--muted);font-size:13.5px">
      <span id="footerText">Belum punya akun?</span>
      <button type="button" id="footerButton" onclick="setMode('register')" style="border:none;background:none;color:#92600a;font-weight:700;cursor:pointer;font-size:13.5px">Daftar gratis</button>
    </div>
    <div class="hint" style="border-top:1px solid var(--border);padding-top:18px;text-align:center;display:flex;gap:8px;justify-content:center;align-items:center"><?= svg_icon('shield',16) ?> Akses khusus warga SMK Negeri 7 Batam</div>
  </section>
</div></div></main>

<footer class="mini"><div class="container"><div class="footer-inner">
  <span>© <?= date('Y') ?> <strong>SLearning</strong> — SMK Negeri 7 Batam</span>
  <span><a href="index.php">Beranda</a> • <a href="cek-db.php">Status sistem</a></span>
</div></div></footer>

<script>
let currentMode = <?= json_encode($mode) ?>;
function setMode(mode){
  currentMode = mode;
  document.getElementById("mode").value = mode;
  const loginTab=document.getElementById("loginTab"),registerTab=document.getElementById("registerTab");
  const registerFields=document.getElementById("registerFields"),confirmGroup=document.getElementById("confirmGroup"),loginOptions=document.getElementById("loginOptions");
  const nama=document.getElementById("nama"),email=document.getElementById("email"),confirm=document.getElementById("confirm_password");
  const title=document.getElementById("formTitle"),subtitle=document.getElementById("formSubtitle"),submit=document.getElementById("submitBtn");
  const footerText=document.getElementById("footerText"),footerButton=document.getElementById("footerButton");
  loginTab.classList.toggle("active",mode==="login");registerTab.classList.toggle("active",mode==="register");
  if(mode==="login"){
    title.textContent="Masuk ke SLearning";subtitle.textContent="Masuk menggunakan username dan password sekolah.";
    registerFields.style.display="none";confirmGroup.style.display="none";loginOptions.style.display="flex";
    nama.required=false;email.required=false;confirm.required=false;
    submit.innerHTML='<?= str_replace("'","\\'",svg_icon("login",17)) ?> <span>Masuk</span>';
    footerText.textContent="Belum punya akun?";footerButton.textContent="Daftar gratis";
    footerButton.onclick=function(){setMode("register");};
    document.getElementById("password").autocomplete="current-password";
  }else{
    title.textContent="Buat Akun SLearning";subtitle.textContent="Lengkapi data berikut untuk mendaftar.";
    registerFields.style.display="block";confirmGroup.style.display="block";loginOptions.style.display="none";
    nama.required=true;email.required=true;confirm.required=true;
    submit.innerHTML='<?= str_replace("'","\\'",svg_icon("plus",17)) ?> <span>Daftar Sekarang</span>';
    footerText.textContent="Sudah punya akun?";footerButton.textContent="Masuk di sini";
    footerButton.onclick=function(){setMode("login");};
    document.getElementById("password").autocomplete="new-password";
  }
  history.replaceState(null,"","login.php?mode="+mode);
}
function togglePassword(id,btn){
  const input=document.getElementById(id);
  const show=input.type==="password";input.type=show?"text":"password";
  btn.innerHTML=show?'<?= str_replace("'","\\'",svg_icon("eye-off",18)) ?>':'<?= str_replace("'","\\'",svg_icon("eye",18)) ?>';
}
setMode(currentMode);
(function(){var b=document.getElementById('burger'),r=document.getElementById('navRight');if(b&&r){b.addEventListener('click',function(){var o=r.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');});}})();
</script>
</body>
</html>

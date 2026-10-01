<?php
session_start();

/* =========================================================
   CEK LOGIN
   ========================================================= */
if (!isset($_SESSION['login'])) {
    header("Location: murid.php");
    exit;
}

/* =========================================================
   AMBIL DATA DARI SESSION
   Menyesuaikan beberapa kemungkinan nama session
   ========================================================= */
$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Murid';
$nis = $_SESSION['NIS'] ?? $_SESSION['nis'] ?? '-';
$kelas = $_SESSION['kelas'] ?? $_SESSION['Kelas'] ?? '-';
$username = $_SESSION['username'] ?? '-';

/* =========================================================
   DATA SEMENTARA
   Nanti bisa diganti dengan data dari database
   ========================================================= */
$total_tugas = 5;
$sudah_kumpul = 3;
$belum_kumpul = 2;
$total_quiz = 3;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Beranda Murid - E-Tugas SKAJU</title>

<style>

/* =========================================================
   RESET
   ========================================================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f6f7fb;
    color: #252525;
}

/* =========================================================
   SIDEBAR
   ========================================================= */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 250px;
    height: 100vh;

    background: #ffffff;
    border-right: 1px solid #e5e5e5;

    padding: 25px 18px;

    display: flex;
    flex-direction: column;

    z-index: 100;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 5px 10px 30px;
}

.logo-icon {
    width: 42px;
    height: 42px;

    background: #f2c94c;
    border-radius: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 21px;
}

.logo-text h2 {
    font-size: 18px;
    color: #222;
}

.logo-text p {
    font-size: 11px;
    color: #888;
    margin-top: 3px;
}

/* MENU */

.menu-title {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;

    margin: 5px 12px 10px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 6px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 13px;

    padding: 12px 14px;

    text-decoration: none;
    color: #666;

    border-radius: 10px;

    font-size: 14px;

    transition: 0.2s;
}

.menu a:hover {
    background: #fff8dc;
    color: #222;
}

.menu a.active {
    background: #f2c94c;
    color: #222;
    font-weight: bold;
}

.menu-icon {
    width: 20px;
    text-align: center;
}

/* LOGOUT */

.sidebar-bottom {
    margin-top: auto;
}

.logout {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 12px 14px;

    color: #d9534f;
    text-decoration: none;

    font-size: 14px;

    border-radius: 10px;
}

.logout:hover {
    background: #fff0f0;
}

/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 250px;
    min-height: 100vh;
}

/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {
    height: 75px;

    background: #ffffff;

    border-bottom: 1px solid #e7e7e7;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 35px;
}

.page-title h1 {
    font-size: 22px;
    color: #222;
}

.page-title p {
    font-size: 12px;
    color: #999;
    margin-top: 4px;
}

.profile-mini {
    display: flex;
    align-items: center;
    gap: 11px;
}

.avatar {
    width: 39px;
    height: 39px;

    border-radius: 50%;

    background: #f2c94c;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
    color: #333;
}

.profile-mini div:last-child {
    line-height: 1.3;
}

.profile-mini strong {
    display: block;
    font-size: 13px;
}

.profile-mini span {
    font-size: 11px;
    color: #999;
}

/* =========================================================
   CONTENT
   ========================================================= */

.content {
    padding: 32px 35px;
}

/* WELCOME */

.welcome {
    background: #ffffff;

    border-radius: 18px;

    padding: 28px 30px;

    margin-bottom: 25px;

    border: 1px solid #e8e8e8;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.welcome-text h2 {
    font-size: 25px;
    margin-bottom: 8px;
}

.welcome-text p {
    color: #777;
    font-size: 14px;
}

.welcome-badge {
    width: 80px;
    height: 80px;

    background: #fff8dc;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 37px;
}

/* =========================================================
   STATISTIC CARD
   ========================================================= */

.stats {
    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {
    background: #ffffff;

    border: 1px solid #e8e8e8;

    border-radius: 15px;

    padding: 20px;

    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 48px;
    height: 48px;

    border-radius: 12px;

    background: #fff8dc;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 22px;
}

.stat-card h3 {
    font-size: 22px;
    margin-bottom: 3px;
}

.stat-card p {
    font-size: 12px;
    color: #888;
}

/* =========================================================
   GRID CONTENT
   ========================================================= */

.dashboard-grid {
    display: grid;

    grid-template-columns: 1.5fr 1fr;

    gap: 20px;
}

/* CARD */

.card {
    background: #ffffff;

    border: 1px solid #e8e8e8;

    border-radius: 16px;

    padding: 23px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 17px;
}

.card-header a {
    text-decoration: none;

    font-size: 12px;

    color: #b28b00;
}

/* TASK */

.task {
    border: 1px solid #eeeeee;

    border-radius: 12px;

    padding: 15px;

    margin-bottom: 12px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.task:last-child {
    margin-bottom: 0;
}

.task h4 {
    font-size: 14px;
    margin-bottom: 5px;
}

.task p {
    font-size: 11px;
    color: #999;
}

.status {
    padding: 6px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: bold;
}

.status.done {
    background: #e8f7ed;
    color: #238b45;
}

.status.pending {
    background: #fff4d5;
    color: #a87a00;
}

/* =========================================================
   PROFILE CARD
   ========================================================= */

.profile-card {
    margin-bottom: 20px;
}

.profile-info {
    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;
}

.profile-avatar {
    width: 58px;
    height: 58px;

    background: #f2c94c;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 20px;

    font-weight: bold;
}

.profile-info h3 {
    font-size: 15px;
}

.profile-info p {
    font-size: 11px;

    color: #999;

    margin-top: 4px;
}

.info-row {
    display: flex;

    justify-content: space-between;

    padding: 11px 0;

    border-bottom: 1px solid #eeeeee;

    font-size: 12px;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row span:first-child {
    color: #999;
}

.info-row span:last-child {
    font-weight: bold;
}

/* =========================================================
   QUIZ CARD
   ========================================================= */

.quiz-card {
    background: #222;

    color: white;

    border-radius: 16px;

    padding: 23px;
}

.quiz-card h3 {
    font-size: 17px;

    margin-bottom: 7px;
}

.quiz-card p {
    font-size: 12px;

    color: #bbb;

    line-height: 1.5;

    margin-bottom: 18px;
}

.quiz-button {
    display: inline-block;

    background: #f2c94c;

    color: #222;

    text-decoration: none;

    padding: 10px 15px;

    border-radius: 9px;

    font-size: 12px;

    font-weight: bold;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {

    .sidebar {
        position: relative;

        width: 100%;
        height: auto;

        border-right: none;
        border-bottom: 1px solid #ddd;
    }

    .main {
        margin-left: 0;
    }

    .menu {
        display: flex;
        gap: 5px;
        overflow-x: auto;
    }

    .menu li {
        min-width: max-content;
    }

    .sidebar-bottom {
        margin-top: 15px;
    }

    .topbar {
        padding: 0 20px;
    }

    .content {
        padding: 20px;
    }

    .welcome {
        padding: 22px;
    }

    .welcome-badge {
        display: none;
    }

}

</style>
</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            📚
        </div>

        <div class="logo-text">
            <h2>E-Tugas</h2>
            <p>SKAJU Learning</p>
        </div>

    </div>


    <div class="menu-title">
        Menu Utama
    </div>

    <ul class="menu">

        <li>
            <a href="murid.php" class="active">
                <span class="menu-icon">🏠</span>
                Beranda
            </a>
        </li>

        <li>
            <a href="tugas_murid.php">
                <span class="menu-icon">📚</span>
                Tugas Saya
            </a>
        </li>

        <li>
            <a href="quiziz.php">
                <span class="menu-icon">🎯</span>
                Quiziz
            </a>
        </li>

        <li>
            <a href="#profile">
                <span class="menu-icon">👤</span>
                Profile
            </a>
        </li>

    </ul>


    <div class="sidebar-bottom">

        <a href="logout.php" class="logout">
            <span>🚪</span>
            Logout
        </a>

    </div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="page-title">

            <h1>Beranda</h1>

            <p>Dashboard Murid E-Tugas SKAJU</p>

        </div>


        <div class="profile-mini">

            <div class="avatar">
                <?php echo strtoupper(substr($nama, 0, 1)); ?>
            </div>

            <div>

                <strong>
                    <?php echo htmlspecialchars($nama); ?>
                </strong>

                <span>
                    Murid
                </span>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <!-- WELCOME -->

        <div class="welcome">

            <div class="welcome-text">

                <h2>
                    Halo, <?php echo htmlspecialchars($nama); ?>! 👋
                </h2>

                <p>
                    Selamat datang di E-Tugas SKAJU.
                    Jangan lupa cek tugas dan deadline kamu hari ini.
                </p>

            </div>


            <div class="welcome-badge">
                📖
            </div>

        </div>


        <!-- STATISTICS -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    📚
                </div>

                <div>

                    <h3>
                        <?php echo $total_tugas; ?>
                    </h3>

                    <p>
                        Total Tugas
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ✅
                </div>

                <div>

                    <h3>
                        <?php echo $sudah_kumpul; ?>
                    </h3>

                    <p>
                        Sudah Dikumpulkan
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    ⏳
                </div>

                <div>

                    <h3>
                        <?php echo $belum_kumpul; ?>
                    </h3>

                    <p>
                        Belum Dikumpulkan
                    </p>

                </div>

            </div>

        </div>


        <!-- GRID -->

        <div class="dashboard-grid">


            <!-- LEFT -->

            <div>


                <!-- TASK -->

                <div class="card">

                    <div class="card-header">

                        <h3>Tugas Terbaru</h3>

                        <a href="tugas_murid.php">
                            Lihat Semua →
                        </a>

                    </div>


                    <div class="task">

                        <div>

                            <h4>
                                Pemrograman Berorientasi Objek
                            </h4>

                            <p>
                                Deadline: 30 September 2026
                            </p>

                        </div>

                        <span class="status done">
                            Sudah
                        </span>

                    </div>


                    <div class="task">

                        <div>

                            <h4>
                                Basis Data
                            </h4>

                            <p>
                                Deadline: 2 Oktober 2026
                            </p>

                        </div>

                        <span class="status pending">
                            Belum
                        </span>

                    </div>


                    <div class="task">

                        <div>

                            <h4>
                                Bahasa Indonesia
                            </h4>

                            <p>
                                Deadline: 4 Oktober 2026
                            </p>

                        </div>

                        <span class="status done">
                            Sudah
                        </span>

                    </div>

                </div>


                <br>


                <!-- QUIZ -->

                <div class="quiz-card">

                    <h3>
                        🎯 Quiziz
                    </h3>

                    <p>
                        Uji pemahaman kamu dengan mengerjakan
                        quiz yang telah diberikan oleh guru.
                    </p>

                    <a href="quiziz.php" class="quiz-button">
                        Buka Quiziz
                    </a>

                </div>

            </div>


            <!-- RIGHT -->

            <div>


                <!-- PROFILE -->

                <div class="card profile-card" id="profile">

                    <div class="card-header">

                        <h3>Profile Saya</h3>

                    </div>


                    <div class="profile-info">

                        <div class="profile-avatar">

                            <?php
                            echo strtoupper(substr($nama, 0, 1));
                            ?>

                        </div>

                        <div>

                            <h3>
                                <?php
                                echo htmlspecialchars($nama);
                                ?>
                            </h3>

                            <p>
                                @<?php
                                echo htmlspecialchars($username);
                                ?>
                            </p>

                        </div>

                    </div>


                    <div class="info-row">

                        <span>Nama</span>

                        <span>
                            <?php
                            echo htmlspecialchars($nama);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span>NIS</span>

                        <span>
                            <?php
                            echo htmlspecialchars($nis);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span>Kelas</span>

                        <span>
                            <?php
                            echo htmlspecialchars($kelas);
                            ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span>Status</span>

                        <span style="color:#238b45;">
                            Aktif
                        </span>

                    </div>

                </div>


                <!-- QUICK INFO -->

                <div class="card">

                    <div class="card-header">

                        <h3>Informasi</h3>

                    </div>

                    <p style="
                        font-size:12px;
                        color:#777;
                        line-height:1.7;
                    ">

                        Pastikan kamu mengumpulkan tugas
                        sebelum deadline yang telah ditentukan
                        oleh guru.

                    </p>

                </div>

            </div>


        </div>

    </section>

</main>

</body>
</html>
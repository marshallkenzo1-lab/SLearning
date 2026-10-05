<?php
// SLearning - SKAJU Learning | Profil Murid
session_start();

$nama_murid = $_SESSION['nama'] ?? 'Andhini';
$nis = $_SESSION['nis'] ?? 'NIS 20260001';

$kelas = $_SESSION['kelas'] ?? 'XI PPLG';
$email = $_SESSION['email'] ?? 'andhini@student.skaju.sch.id';

$inisial = strtoupper(substr($nama_murid, 0, 1));
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profil Saya — SLearning</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap"
rel="stylesheet">

<link
rel="icon"
type="image/png"
href="assets/img/logo-smkn7.png"
>

<style>

:root{
    --bg:#ffffff;
    --bg-soft:#f7f7f8;

    --dark:#0a0a0b;
    --surface:#141416;

    --yellow:#ffc107;
    --yellow-hover:#ffb300;
    --yellow-soft:#fff6d6;

    --text:#0a0a0b;
    --muted:#6b7280;
    --border:#e7e7ea;

    --radius:18px;
    --max:1200px;

    --shadow:0 16px 40px rgba(0,0,0,.08);

    --font-h:'Poppins',sans-serif;
    --font-b:'Open Sans',sans-serif;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{
    font-family:var(--font-b);
    background:var(--bg);
    color:var(--text);
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
}

a{
    text-decoration:none;
    color:inherit;
}

button,
input{
    font-family:inherit;
}

.container{
    max-width:var(--max);
    margin:auto;
    padding:0 24px;
}


/* ================= NAVBAR ================= */

.navbar{
    position:sticky;
    top:0;
    z-index:50;

    background:rgba(255,255,255,.95);
    backdrop-filter:blur(12px);

    border-bottom:1px solid var(--border);

    box-shadow:0 8px 30px rgba(0,0,0,.07);
}

.nav-inner{
    max-width:var(--max);
    margin:auto;

    padding:14px 24px;

    display:flex;
    align-items:center;

    gap:25px;
}

.brand{
    display:flex;
    align-items:center;
    gap:12px;
}

.brand img{
    width:44px;
    height:44px;

    object-fit:contain;

    background:#fff;
    border-radius:12px;
    padding:4px;

    border:1px solid var(--border);
}

.brand-text{
    line-height:1.2;
}

.brand-text strong{
    font-family:var(--font-h);
    font-size:17px;
    display:block;
}

.brand-text span{
    display:block;

    font-size:11px;
    color:var(--muted);

    text-transform:uppercase;
    letter-spacing:.04em;
}


/* NAV */

.nav-links{
    margin-left:auto;

    display:flex;
    gap:5px;
}

.nav-links a{
    color:#4b5563;

    font-size:14px;
    font-weight:600;

    padding:10px 14px;

    border-radius:10px;

    transition:.2s;
}

.nav-links a:hover,
.nav-links a.active{
    color:var(--text);
    background:var(--yellow-soft);
}


/* PROFILE NAV */

.profile{
    display:flex;
    align-items:center;

    gap:10px;

    padding-left:14px;

    border-left:1px solid var(--border);
}

.avatar{
    width:40px;
    height:40px;

    border-radius:50%;

    background:var(--dark);
    color:var(--yellow);

    display:flex;
    align-items:center;
    justify-content:center;

    font-family:var(--font-h);
    font-weight:800;
    font-size:17px;
}

.profile-info{
    line-height:1.2;
}

.profile-info strong{
    display:block;

    font-family:var(--font-h);
    font-size:13px;
}

.profile-info span{
    display:block;

    color:var(--muted);
    font-size:11px;

    margin-top:3px;
}


/* ================= MAIN ================= */

.profile-page{
    min-height:calc(100vh - 73px);

    position:relative;

    background:
        radial-gradient(
            800px 380px at 85% 0%,
            rgba(255,193,7,.20),
            transparent
        ),
        var(--bg);

    overflow:hidden;
}


/* GRID */

.profile-page::before{
    content:"";

    position:absolute;
    inset:0;

    pointer-events:none;

    background-image:
        linear-gradient(
            #eeeeef 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            #eeeeef 1px,
            transparent 1px
        );

    background-size:44px 44px;

    mask-image:
        radial-gradient(
            800px 500px at 50% 0%,
            black,
            transparent
        );

    -webkit-mask-image:
        radial-gradient(
            800px 500px at 50% 0%,
            black,
            transparent
        );
}

.profile-content{
    position:relative;
    z-index:1;

    padding:55px 0 70px;
}


/* ================= HEADER ================= */

.page-header{
    margin-bottom:25px;
}

.page-header small{
    display:block;

    color:#9a7200;

    font-family:var(--font-h);
    font-weight:800;

    font-size:12px;

    text-transform:uppercase;
    letter-spacing:.08em;

    margin-bottom:7px;
}

.page-header h1{
    font-family:var(--font-h);

    font-size:34px;
    font-weight:800;

    margin-bottom:5px;
}

.page-header p{
    color:var(--muted);
    font-size:14px;
}


/* ================= PROFILE GRID ================= */

.profile-grid{
    display:grid;

    grid-template-columns:320px 1fr;

    gap:22px;

    align-items:start;
}


/* ================= PROFILE CARD ================= */

.profile-card{
    background:#fff;

    border:1px solid var(--border);

    border-radius:20px;

    padding:30px 25px;

    text-align:center;

    box-shadow:0 8px 30px rgba(0,0,0,.04);
}

.profile-avatar{
    width:100px;
    height:100px;

    margin:0 auto 17px;

    border-radius:50%;

    background:var(--dark);
    color:var(--yellow);

    display:flex;
    align-items:center;
    justify-content:center;

    font-family:var(--font-h);

    font-size:40px;
    font-weight:800;

    border:6px solid var(--yellow-soft);
}

.profile-card h2{
    font-family:var(--font-h);

    font-size:21px;
    font-weight:800;

    margin-bottom:3px;
}

.profile-card .nis{
    color:var(--muted);
    font-size:12px;

    margin-bottom:22px;
}

.student-badge{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    background:var(--yellow-soft);

    color:#805f00;

    padding:7px 13px;

    border-radius:999px;

    font-size:11px;
    font-weight:700;
}


/* ================= DATA CARD ================= */

.data-card{
    background:#fff;

    border:1px solid var(--border);

    border-radius:20px;

    padding:28px;
}

.card-title{
    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:22px;

    padding-bottom:17px;

    border-bottom:1px solid var(--border);
}

.card-title h2{
    font-family:var(--font-h);

    font-size:20px;
}

.card-title span{
    font-size:11px;
    color:var(--muted);
}


/* DATA */

.data-list{
    display:grid;

    grid-template-columns:1fr 1fr;

    gap:15px;
}

.data-item{
    border:1px solid var(--border);

    border-radius:13px;

    padding:16px 17px;

    background:#fff;
}

.data-label{
    display:block;

    color:var(--muted);

    font-size:11px;

    margin-bottom:5px;
}

.data-value{
    font-family:var(--font-h);

    font-size:14px;
    font-weight:600;

    word-break:break-word;
}


/* ================= ACTION ================= */

.profile-actions{
    display:flex;

    justify-content:flex-end;

    gap:10px;

    margin-top:22px;
}

.btn{
    display:inline-flex;

    align-items:center;
    justify-content:center;

    gap:8px;

    min-height:44px;

    padding:11px 18px;

    border-radius:11px;

    border:1px solid transparent;

    font-family:var(--font-h);

    font-weight:600;

    font-size:13px;

    transition:.2s;
}

.btn:hover{
    transform:translateY(-2px);
}

.btn-yellow{
    background:var(--yellow);
    color:#171300;
}

.btn-yellow:hover{
    background:var(--yellow-hover);

    box-shadow:
        0 8px 22px rgba(255,193,7,.3);
}

.btn-outline{
    background:#fff;

    color:var(--text);

    border-color:var(--border);
}

.btn-outline:hover{
    border-color:#c9c9ce;
}


/* ================= ACCOUNT STATUS ================= */

.account-card{
    margin-top:22px;

    background:var(--dark);

    color:#fff;

    border-radius:20px;

    padding:24px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;
}

.account-left{
    display:flex;
    align-items:center;
    gap:13px;
}

.status-icon{
    width:43px;
    height:43px;

    border-radius:12px;

    background:var(--yellow-soft);
    color:#8a6500;

    display:flex;
    align-items:center;
    justify-content:center;
}

.account-left strong{
    display:block;

    font-family:var(--font-h);

    font-size:14px;
}

.account-left span{
    display:block;

    color:#a1a1aa;

    font-size:11px;
}

.status{
    background:#e7f7ee;

    color:#15803d;

    padding:7px 12px;

    border-radius:999px;

    font-size:11px;
    font-weight:700;
}


/* ================= FOOTER ================= */

footer{
    background:#08080a;

    color:#a1a1aa;

    padding:25px 0;

    border-top:4px solid var(--yellow);
}

.footer-inner{
    display:flex;

    align-items:center;
    justify-content:space-between;

    gap:15px;

    flex-wrap:wrap;

    font-size:12px;
}

.footer-inner strong{
    color:#fff;
}


/* ================= TABLET ================= */

@media(max-width:850px){

    .nav-links{
        display:none;
    }

    .profile-grid{
        grid-template-columns:1fr;
    }

    .profile-card{
        text-align:left;

        display:flex;
        align-items:center;

        gap:20px;
    }

    .profile-avatar{
        margin:0;
        flex-shrink:0;
    }

}


/* ================= MOBILE ================= */

@media(max-width:600px){

    .container{
        padding:0 16px;
    }

    .nav-inner{
        padding:12px 16px;
    }

    .brand-text span{
        font-size:9px;
    }

    .profile-info{
        display:none;
    }

    .profile-content{
        padding:30px 0 50px;
    }

    .page-header h1{
        font-size:28px;
    }

    .profile-card{
        display:block;

        text-align:center;

        padding:25px 20px;
    }

    .profile-avatar{
        margin:0 auto 15px;
    }

    .data-card{
        padding:21px 17px;
    }

    .data-list{
        grid-template-columns:1fr;
    }

    .card-title{
        align-items:flex-start;
        flex-direction:column;
        gap:4px;
    }

    .profile-actions{
        flex-direction:column;
    }

    .profile-actions .btn{
        width:100%;
    }

    .account-card{
        align-items:flex-start;
        flex-direction:column;
    }

    .status{
        align-self:flex-start;
    }

    .footer-inner{
        flex-direction:column;
        align-items:flex-start;
    }

}

</style>

</head>


<body>


<!-- ================= NAVBAR ================= -->

<header class="navbar">

    <div class="nav-inner">


        <!-- BRAND -->

        <a href="murid.php" class="brand">

            <img
                src="assets/img/logo-smkn7.png"
                alt="Logo SMK Negeri 7 Batam"
            >

            <span class="brand-text">

                <strong>SLearning</strong>

                <span>
                    SKAJU Learning • SMKN 7
                </span>

            </span>

        </a>


        <!-- NAVIGATION -->

        <nav class="nav-links">

            <a href="murid.php">
                Dashboard
            </a>

            <a href="murid.php#tugas">
                Tugas
            </a>

            <a href="murid.php#quiz">
                Quiziz
            </a>

            <a href="murid.php#aktivitas">
                Aktivitas
            </a>

        </nav>


        <!-- PROFILE -->

        <div class="profile">

            <div class="avatar">

                <?php
                echo $inisial;
                ?>

            </div>

            <div class="profile-info">

                <strong>
                    <?php
                    echo htmlspecialchars($nama_murid);
                    ?>
                </strong>

                <span>
                    <?php
                    echo htmlspecialchars($nis);
                    ?>
                </span>

            </div>

        </div>


    </div>

</header>


<!-- ================= MAIN ================= -->

<main class="profile-page">

    <div class="container profile-content">


        <!-- HEADER -->

        <section class="page-header">

            <small>Profil Murid</small>

            <h1>Profil Saya</h1>

            <p>
                Kelola dan lihat informasi akun kamu di SLearning.
            </p>

        </section>


        <!-- PROFILE -->

        <div class="profile-grid">


            <!-- LEFT -->

            <section class="profile-card">

                <div class="profile-avatar">

                    <?php
                    echo $inisial;
                    ?>

                </div>

                <div>

                    <h2>
                        <?php
                        echo htmlspecialchars($nama_murid);
                        ?>
                    </h2>

                    <p class="nis">
                        <?php
                        echo htmlspecialchars($nis);
                        ?>
                    </p>

                    <span class="student-badge">
                        Siswa SLearning
                    </span>

                </div>

            </section>


            <!-- RIGHT -->

            <section class="data-card">

                <div class="card-title">

                    <h2>Informasi Siswa</h2>

                    <span>
                        Data akun
                    </span>

                </div>


                <div class="data-list">


                    <div class="data-item">

                        <span class="data-label">
                            Nama Lengkap
                        </span>

                        <span class="data-value">
                            <?php
                            echo htmlspecialchars($nama_murid);
                            ?>
                        </span>

                    </div>


                    <div class="data-item">

                        <span class="data-label">
                            NIS
                        </span>

                        <span class="data-value">
                            <?php
                            echo htmlspecialchars($nis);
                            ?>
                        </span>

                    </div>


                    <div class="data-item">

                        <span class="data-label">
                            Kelas
                        </span>

                        <span class="data-value">
                            <?php
                            echo htmlspecialchars($kelas);
                            ?>
                        </span>

                    </div>


                    <div class="data-item">

                        <span class="data-label">
                            Jurusan
                        </span>

                        <span class="data-value">
                            Pengembangan Perangkat Lunak dan Gim
                        </span>

                    </div>


                    <div class="data-item">

                        <span class="data-label">
                            Email
                        </span>

                        <span class="data-value">
                            <?php
                            echo htmlspecialchars($email);
                            ?>
                        </span>

                    </div>


                    <div class="data-item">

                        <span class="data-label">
                            Sekolah
                        </span>

                        <span class="data-value">
                            SMK Negeri 7 Batam
                        </span>

                    </div>


                </div>


                <div class="profile-actions">

                    <a
                        href="murid.php"
                        class="btn btn-outline"
                    >
                        ← Kembali
                    </a>

                    <a
                        href="#"
                        class="btn btn-yellow"
                    >
                        Edit Profil
                    </a>

                </div>

            </section>

        </div>


        <!-- ACCOUNT STATUS -->

        <section class="account-card">

            <div class="account-left">

                <div class="status-icon">

                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M20 6 9 17l-5-5"/>

                    </svg>

                </div>


                <div>

                    <strong>
                        Akun Aktif
                    </strong>

                    <span>
                        Akun kamu dapat digunakan untuk mengakses SLearning.
                    </span>

                </div>

            </div>


            <span class="status">
                Aktif
            </span>

        </section>


    </div>

</main>


<!-- ================= FOOTER ================= -->

<footer>

    <div class="container">

        <div class="footer-inner">

            <span>
                © <?php echo date('Y'); ?>

                <strong>
                    SLearning
                </strong>

                — SMK Negeri 7 Batam
            </span>

            <span>
                Profil Murid
            </span>

        </div>

    </div>

</footer>


</body>

</html>
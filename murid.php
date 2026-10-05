<?php
// SLearning - SKAJU Learning | Dashboard Murid
session_start();

<<<<<<< HEAD
// Jika nanti sudah ada sistem login, bisa aktifkan:
// if (!isset($_SESSION['login'])) {
//     header("Location: login.php");
//     exit;
// }
=======
/*
    Untuk sementara menggunakan data contoh.
    Nanti bisa disambungkan ke database.
*/
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

$nama_murid = $_SESSION['nama'] ?? 'Andhini';
$nis = $_SESSION['nis'] ?? 'NIS 20260001';
?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Murid — SLearning</title>

<<<<<<< HEAD
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">

<style>

:root{
    --bg:#ffffff;
    --bg-soft:#f7f7f8;
=======
<meta name="description" content="Dashboard murid SLearning SMK Negeri 7 Batam">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap"
rel="stylesheet"
>

<link
rel="icon"
type="image/png"
href="assets/img/logo-smkn7.png"
>

<style>

/* =========================================================
   ROOT
========================================================= */

:root{

    --bg:#ffffff;
    --bg-soft:#f7f7f8;

>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db
    --dark:#0a0a0b;
    --surface:#141416;
    --surface-2:#1c1c1f;

    --yellow:#ffc107;
    --yellow-hover:#ffb300;
    --yellow-soft:#fff6d6;

    --text:#0a0a0b;
    --muted:#6b7280;
    --muted-dark:#a1a1aa;

    --border:#e7e7ea;

    --radius:16px;
    --radius-lg:22px;

    --max:1200px;

    --shadow:0 16px 40px rgba(0,0,0,.08);

    --font-h:'Poppins',sans-serif;
    --font-b:'Open Sans',sans-serif;
<<<<<<< HEAD
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

button{
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
    background:rgba(255,255,255,.94);
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

.brand-text strong{
    font-family:var(--font-h);
    font-size:17px;
    display:block;
}

.brand-text span{
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.04em;
}

.nav-links{
    margin-left:auto;
    display:flex;
    gap:5px;
}

.nav-links a{
    font-size:14px;
    font-weight:600;
    color:#4b5563;
    padding:10px 13px;
    border-radius:10px;
}

.nav-links a:hover,
.nav-links a.active{
    background:var(--yellow-soft);
    color:var(--text);
}

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
}

.profile-info{
    line-height:1.2;
}

.profile-info strong{
    display:block;
    font-size:13px;
}

.profile-info span{
    color:var(--muted);
    font-size:11px;
}

/* ================= HERO ================= */

.dashboard{
    min-height:calc(100vh - 73px);
    background:
        radial-gradient(
            700px 350px at 85% 0%,
            rgba(255,193,7,.20),
            transparent
        ),
        var(--bg);
}

.dashboard::before{
    content:"";
    position:fixed;
    inset:0;
    pointer-events:none;
    background-image:
        linear-gradient(#eeeeef 1px,transparent 1px),
        linear-gradient(90deg,#eeeeef 1px,transparent 1px);
    background-size:44px 44px;
    mask-image:radial-gradient(
        700px 500px at 50% 0%,
        black,
        transparent
    );
    z-index:0;
}

.dashboard-content{
    position:relative;
    z-index:1;
    padding:42px 0 70px;
}

/* ================= WELCOME ================= */

.welcome{
    background:var(--dark);
    color:white;
    border-radius:24px;
    padding:34px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:25px;
    position:relative;
    overflow:hidden;
    margin-bottom:24px;
}

.welcome::after{
    content:"";
    position:absolute;
    width:330px;
    height:330px;
    right:-100px;
    top:-140px;
    background:radial-gradient(
        circle,
        rgba(255,193,7,.38),
        transparent 70%
    );
}

.welcome-text{
    position:relative;
    z-index:2;
}

.welcome small{
    color:var(--yellow);
    font-weight:700;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.welcome h1{
    font-family:var(--font-h);
    font-size:clamp(25px,4vw,38px);
    margin:6px 0 8px;
}

.welcome p{
    color:#b9b9bf;
    font-size:14px;
}

.welcome-button{
    position:relative;
    z-index:2;
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
    cursor:pointer;
    transition:.2s;
}

.btn-yellow{
    background:var(--yellow);
    color:#171300;
}

.btn-yellow:hover{
    background:var(--yellow-hover);
    transform:translateY(-2px);
    box-shadow:0 8px 22px rgba(255,193,7,.3);
}

.btn-dark{
    background:var(--dark);
    color:white;
}

.btn-outline{
    background:white;
    border-color:var(--border);
    color:var(--text);
}

/* ================= STAT ================= */

.stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:35px;
}

.stat-card{
    background:white;
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:20px;
    display:flex;
    align-items:center;
    gap:15px;
    transition:.2s;
}

.stat-card:hover{
    transform:translateY(-3px);
    box-shadow:var(--shadow);
}

.stat-icon{
    width:46px;
    height:46px;
    border-radius:13px;
    background:var(--yellow-soft);
    color:#8a6500;
    display:flex;
    align-items:center;
    justify-content:center;
}

.stat-number{
    font-family:var(--font-h);
    font-size:24px;
    font-weight:800;
    line-height:1;
}

.stat-label{
    color:var(--muted);
    font-size:12px;
    margin-top:4px;
}

/* ================= SECTION HEADER ================= */

.section-head{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:15px;
    margin-bottom:16px;
}

.section-head h2{
    font-family:var(--font-h);
    font-size:22px;
}

.section-head p{
    color:var(--muted);
    font-size:13px;
}

/* ================= TASK ================= */

.main-grid{
    display:grid;
    grid-template-columns:1.45fr .75fr;
    gap:22px;
    margin-bottom:32px;
}

.task-list{
    display:grid;
    gap:12px;
}

.task-card{
    background:white;
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:19px;
    display:flex;
    align-items:center;
    gap:15px;
    transition:.2s;
}

.task-card:hover{
    transform:translateY(-3px);
    box-shadow:var(--shadow);
}

.task-icon{
    width:48px;
    height:48px;
    border-radius:13px;
    background:var(--yellow-soft);
    color:#7a5b00;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
}

.task-content{
    flex:1;
}

.task-content h3{
    font-family:var(--font-h);
    font-size:14px;
    margin-bottom:3px;
}

.task-content p{
    color:var(--muted);
    font-size:12px;
}

.task-status{
    font-size:11px;
    font-weight:700;
    padding:6px 11px;
    border-radius:999px;
    white-space:nowrap;
}

.status-wait{
    background:var(--yellow-soft);
    color:#8a6500;
}

.status-done{
    background:#e7f7ee;
    color:#15803d;
}

.status-late{
    background:#feecec;
    color:#dc2626;
}

/* ================= QUICK ================= */

.quick-card{
    background:var(--dark);
    color:white;
    border-radius:var(--radius);
    padding:24px;
}

.quick-card h3{
    font-family:var(--font-h);
    font-size:18px;
    margin-bottom:6px;
}

.quick-card > p{
    color:#a1a1aa;
    font-size:12px;
    margin-bottom:18px;
}

.quick-menu{
    display:grid;
    gap:9px;
}

.quick-menu a{
    background:#1c1c1f;
    border:1px solid #2a2a2e;
    padding:13px;
    border-radius:12px;
    display:flex;
    align-items:center;
    gap:11px;
    font-size:13px;
    transition:.2s;
}

.quick-menu a:hover{
    background:#242427;
    border-color:#414146;
    transform:translateX(3px);
}

.quick-menu svg{
    color:var(--yellow);
}

/* ================= QUIZ ================= */

.quiz-section{
    margin-top:10px;
}

.quiz-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
}

.quiz-card{
    background:white;
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:21px;
    transition:.2s;
}

.quiz-card:hover{
    transform:translateY(-4px);
    box-shadow:var(--shadow);
}

.quiz-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:16px;
}

.quiz-label{
    font-size:10px;
    font-weight:800;
    background:var(--dark);
    color:var(--yellow);
    padding:6px 9px;
    border-radius:7px;
}

.quiz-time{
    color:var(--muted);
    font-size:11px;
}

.quiz-card h3{
    font-family:var(--font-h);
    font-size:15px;
    margin-bottom:5px;
}

.quiz-card p{
    color:var(--muted);
    font-size:12px;
    margin-bottom:17px;
}

.quiz-bottom{
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.quiz-bottom span{
    font-size:11px;
    color:var(--muted);
}

.quiz-bottom a{
    font-size:12px;
    font-weight:700;
    color:#765600;
}

/* ================= ACTIVITY ================= */

.activity{
    margin-top:34px;
}

.activity-card{
    background:white;
    border:1px solid var(--border);
    border-radius:var(--radius);
    overflow:hidden;
}

.activity-item{
    display:flex;
    align-items:center;
    gap:14px;
    padding:16px 19px;
    border-bottom:1px solid var(--border);
}

.activity-item:last-child{
    border-bottom:none;
}

.activity-dot{
    width:9px;
    height:9px;
    background:var(--yellow);
    border-radius:50%;
    flex-shrink:0;
}

.activity-item strong{
    font-size:13px;
    display:block;
}

.activity-item span{
    font-size:11px;
    color:var(--muted);
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
    justify-content:space-between;
    gap:15px;
    flex-wrap:wrap;
    font-size:12px;
}

footer strong{
    color:white;
}

/* ================= MOBILE ================= */

@media(max-width:980px){

    .stats{
        grid-template-columns:1fr 1fr;
    }

    .main-grid{
        grid-template-columns:1fr;
    }

    .quiz-grid{
        grid-template-columns:1fr 1fr;
    }

    .nav-links{
        display:none;
    }

}

@media(max-width:650px){

    .nav-inner{
        padding:12px 16px;
    }

    .profile-info{
        display:none;
    }

    .container{
        padding:0 16px;
    }

    .dashboard-content{
        padding-top:25px;
    }

    .welcome{
        padding:25px;
        flex-direction:column;
        align-items:flex-start;
    }

    .stats{
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .stat-card{
        padding:15px;
    }

    .stat-number{
        font-size:20px;
    }

    .stat-icon{
        width:40px;
        height:40px;
    }

    .task-card{
        align-items:flex-start;
    }

    .task-status{
        display:none;
    }

    .quiz-grid{
        grid-template-columns:1fr;
=======
}


/* =========================================================
   RESET
========================================================= */

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

    font-size:16px;

    line-height:1.6;

    color:var(--text);

    background:var(--bg);

    -webkit-font-smoothing:antialiased;

    overflow-x:hidden;
}

a{
    text-decoration:none;
    color:inherit;
}

button{
    font-family:inherit;
}

h1,
h2,
h3,
h4{
    font-family:var(--font-h);
    line-height:1.15;
}

.container{

    max-width:var(--max);

    margin:0 auto;

    padding:0 24px;
}


/* =========================================================
   NAVBAR
========================================================= */

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

    margin:0 auto;

    padding:14px 24px;

    display:flex;

    align-items:center;

    gap:25px;
}


/* BRAND */

.brand{

    display:flex;

    align-items:center;

    gap:12px;

    min-height:44px;
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

    font-size:11.5px;

    color:var(--muted);

    letter-spacing:.04em;

    text-transform:uppercase;
}


/* NAV LINKS */

.nav-links{

    display:flex;

    gap:5px;

    margin-left:auto;
}

.nav-links a{

    color:#4b5563;

    font-size:14px;

    font-weight:600;

    padding:10px 14px;

    border-radius:10px;

    transition:.2s;

    min-height:44px;

    display:inline-flex;

    align-items:center;
}

.nav-links a:hover{

    color:var(--text);

    background:var(--yellow-soft);
}

.nav-links a.active{

    color:var(--text);

    background:var(--yellow-soft);
}


/* PROFILE */

.profile{

    display:flex;

    align-items:center;

    gap:10px;

    padding-left:14px;

    border-left:1px solid var(--border);

    min-height:44px;
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


/* =========================================================
   DASHBOARD BACKGROUND
========================================================= */

.dashboard{

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


/* GRID BACKGROUND */

.dashboard::before{

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

.dashboard-content{

    position:relative;

    z-index:1;

    padding:58px 0 75px;
}


/* =========================================================
   WELCOME
========================================================= */

.welcome{

    background:var(--dark);

    color:#fff;

    border-radius:30px;

    padding:48px 54px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:35px;

    position:relative;

    overflow:hidden;

    margin-bottom:32px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.12);
}

.welcome::after{

    content:"";

    position:absolute;

    width:380px;

    height:380px;

    right:-100px;

    top:-170px;

    background:
        radial-gradient(
            circle,
            rgba(255,193,7,.40),
            transparent 70%
        );
}

.welcome-text{

    position:relative;

    z-index:2;
}

.welcome small{

    display:block;

    color:var(--yellow);

    font-family:var(--font-h);

    font-weight:800;

    font-size:13px;

    letter-spacing:.08em;

    text-transform:uppercase;

    margin-bottom:10px;
}

.welcome h1{

    font-size:clamp(30px,4vw,48px);

    font-weight:800;

    margin-bottom:12px;

    color:#fff;
}

.welcome p{

    color:#b9b9bf;

    font-size:15px;
}

.welcome-button{

    position:relative;

    z-index:2;

    flex-shrink:0;
}


/* =========================================================
   BUTTON
========================================================= */

.btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    min-height:44px;

    padding:11px 19px;

    border-radius:12px;

    border:1px solid transparent;

    font-family:var(--font-h);

    font-weight:600;

    font-size:13px;

    cursor:pointer;

    transition:
        transform .2s,
        box-shadow .2s,
        background .2s;
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


/* =========================================================
   STATISTICS
========================================================= */

.stats{

    width:100%;

    display:grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap:16px;

    margin-bottom:42px;
}

.stat-card{

    width:100%;

    min-width:0;

    background:#fff;

    border:1px solid var(--border);

    border-radius:18px;

    padding:22px;

    display:flex;

    align-items:center;

    gap:15px;

    transition:
        transform .2s,
        box-shadow .2s,
        border-color .2s;
}

.stat-card:hover{

    transform:translateY(-3px);

    box-shadow:var(--shadow);

    border-color:#d8d8dc;
}

.stat-icon{

    width:48px;

    height:48px;

    min-width:48px;

    border-radius:14px;

    background:var(--yellow-soft);

    color:#8a6500;

    display:flex;

    align-items:center;

    justify-content:center;
}

.stat-content{

    min-width:0;
}

.stat-number{

    font-family:var(--font-h);

    font-size:26px;

    font-weight:800;

    line-height:1;
}

.stat-label{

    color:var(--muted);

    font-size:12.5px;

    margin-top:5px;

    white-space:nowrap;
}


/* =========================================================
   SECTION HEADER
========================================================= */

.section-head{

    display:flex;

    justify-content:space-between;

    align-items:flex-end;

    gap:15px;

    margin-bottom:18px;
}

.section-head h2{

    font-size:24px;

    margin-bottom:5px;
}

.section-head p{

    color:var(--muted);

    font-size:13px;
}


/* =========================================================
   MAIN GRID
========================================================= */

.main-grid{

    display:grid;

    grid-template-columns:
        minmax(0,1.5fr)
        minmax(280px,.75fr);

    gap:22px;

    margin-bottom:40px;
}


/* =========================================================
   TASK
========================================================= */

.task-list{

    display:grid;

    gap:12px;
}

.task-card{

    background:#fff;

    border:1px solid var(--border);

    border-radius:17px;

    padding:19px;

    display:flex;

    align-items:center;

    gap:15px;

    transition:
        transform .2s,
        box-shadow .2s;
}

.task-card:hover{

    transform:translateY(-3px);

    box-shadow:var(--shadow);
}

.task-icon{

    width:48px;

    height:48px;

    min-width:48px;

    border-radius:13px;

    background:var(--yellow-soft);

    color:#7a5b00;

    display:flex;

    align-items:center;

    justify-content:center;
}

.task-content{

    flex:1;

    min-width:0;
}

.task-content h3{

    font-size:14px;

    margin-bottom:4px;
}

.task-content p{

    color:var(--muted);

    font-size:12px;
}

.task-status{

    font-size:11px;

    font-weight:700;

    padding:6px 11px;

    border-radius:999px;

    white-space:nowrap;
}

.status-wait{

    background:var(--yellow-soft);

    color:#8a6500;
}

.status-done{

    background:#e7f7ee;

    color:#15803d;
}


/* =========================================================
   QUICK ACCESS
========================================================= */

.quick-card{

    background:var(--dark);

    color:#fff;

    border-radius:20px;

    padding:25px;

    min-height:100%;
}

.quick-card h3{

    font-size:19px;

    margin-bottom:5px;
}

.quick-card > p{

    color:#a1a1aa;

    font-size:12px;

    margin-bottom:18px;
}

.quick-menu{

    display:grid;

    gap:9px;
}

.quick-menu a{

    background:#1c1c1f;

    border:1px solid #2a2a2e;

    padding:13px;

    border-radius:12px;

    display:flex;

    align-items:center;

    gap:11px;

    font-size:13px;

    transition:.2s;
}

.quick-menu a:hover{

    background:#242427;

    border-color:#414146;

    transform:translateX(3px);
}

.quick-menu svg{

    color:var(--yellow);

    flex-shrink:0;
}


/* =========================================================
   QUIZ
========================================================= */

.quiz-section{

    margin-top:5px;

    margin-bottom:42px;
}

.quiz-grid{

    display:grid;

    grid-template-columns:
        repeat(3, minmax(0,1fr));

    gap:16px;
}

.quiz-card{

    background:#fff;

    border:1px solid var(--border);

    border-radius:17px;

    padding:21px;

    transition:
        transform .2s,
        box-shadow .2s;
}

.quiz-card:hover{

    transform:translateY(-4px);

    box-shadow:var(--shadow);
}

.quiz-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    margin-bottom:17px;
}

.quiz-label{

    font-size:10px;

    font-weight:800;

    background:var(--dark);

    color:var(--yellow);

    padding:6px 9px;

    border-radius:7px;
}

.quiz-time{

    color:var(--muted);

    font-size:11px;
}

.quiz-card h3{

    font-size:15px;

    margin-bottom:6px;
}

.quiz-card p{

    color:var(--muted);

    font-size:12px;

    line-height:1.6;

    margin-bottom:18px;
}

.quiz-bottom{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;
}

.quiz-bottom span{

    color:var(--muted);

    font-size:11px;
}

.quiz-bottom a{

    color:#765600;

    font-size:12px;

    font-weight:700;
}

.quiz-bottom a:hover{

    color:#000;
}


/* =========================================================
   ACTIVITY
========================================================= */

.activity{

    margin-top:5px;
}

.activity-card{

    background:#fff;

    border:1px solid var(--border);

    border-radius:17px;

    overflow:hidden;
}

.activity-item{

    display:flex;

    align-items:center;

    gap:14px;

    padding:17px 20px;

    border-bottom:1px solid var(--border);
}

.activity-item:last-child{

    border-bottom:none;
}

.activity-dot{

    width:9px;

    height:9px;

    min-width:9px;

    background:var(--yellow);

    border-radius:50%;
}

.activity-item strong{

    display:block;

    font-size:13px;

    margin-bottom:2px;
}

.activity-item span{

    display:block;

    color:var(--muted);

    font-size:11px;
}


/* =========================================================
   FOOTER
========================================================= */

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


/* =========================================================
   TABLET
========================================================= */

@media(max-width:1000px){

    .nav-links{

        display:none;
    }

    .stats{

        grid-template-columns:
            repeat(2, minmax(0,1fr));
    }

    .main-grid{

        grid-template-columns:1fr;
    }

    .quick-card{

        min-height:auto;
    }

    .quiz-grid{

        grid-template-columns:
            repeat(2, minmax(0,1fr));
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:650px){

    .container{

        padding:0 16px;
    }

    .nav-inner{

        padding:12px 16px;
    }

    .brand-text span{

        font-size:9.5px;
    }

    .profile-info{

        display:none;
    }

    .dashboard-content{

        padding:28px 0 55px;
    }

    .welcome{

        padding:27px 24px;

        border-radius:22px;

        flex-direction:column;

        align-items:flex-start;

        gap:22px;

        margin-bottom:22px;
    }

    .welcome h1{

        font-size:30px;
    }

    .welcome p{

        font-size:13px;
    }

    .welcome-button{

        width:100%;
    }

    .welcome-button .btn{

        width:100%;
    }

    .stats{

        grid-template-columns:
            repeat(2, minmax(0,1fr));

        gap:10px;

        margin-bottom:32px;
    }

    .stat-card{

        padding:15px;

        gap:10px;

        border-radius:15px;
    }

    .stat-icon{

        width:40px;

        height:40px;

        min-width:40px;

        border-radius:11px;
    }

    .stat-number{

        font-size:20px;
    }

    .stat-label{

        font-size:10.5px;

        white-space:normal;
    }

    .section-head{

        align-items:flex-start;

        flex-direction:column;
    }

    .section-head .btn{

        width:100%;
    }

    .task-card{

        align-items:flex-start;

        padding:15px;
    }

    .task-status{

        display:none;
    }

    .task-content h3{

        font-size:13px;
    }

    .task-content p{

        font-size:11px;
    }

    .quiz-grid{

        grid-template-columns:1fr;
    }

    .quick-card{

        padding:21px;
    }

    .footer-inner{

        flex-direction:column;

        align-items:flex-start;
    }

}


/* =========================================================
   VERY SMALL SCREEN
========================================================= */

@media(max-width:400px){

    .brand-text{

        display:none;
    }

    .stats{

        grid-template-columns:1fr;
    }

    .stat-card{

        padding:17px;
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db
    }

}

</style>

</head>


<body>

<<<<<<< HEAD
<!-- ================= NAVBAR ================= -->

<header class="navbar">
    <div class="nav-inner">

```
    <a href="murid.php" class="brand">

        <img
            src="assets/img/logo-smkn7.png"
            alt="Logo SMK Negeri 7 Batam"
        >

        <span class="brand-text">
            <strong>SLearning</strong>
            <span>SKAJU Learning • SMKN 7</span>
        </span>

    </a>
=======

<!-- ======================================================
     NAVBAR
====================================================== -->

<header class="navbar">

    <div class="nav-inner">
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

    <nav class="nav-links">

<<<<<<< HEAD
        <a href="murid.php" class="active">
            Dashboard
        </a>

        <a href="#tugas">
            Tugas
        </a>

        <a href="#quiz">
            Quiziz
        </a>

        <a href="#aktivitas">
            Aktivitas
        </a>

    </nav>

    <div class="profile">

        <div class="avatar">
            <?php echo strtoupper(substr($nama_murid,0,1)); ?>
        </div>

        <div class="profile-info">
            <strong>
                <?php echo htmlspecialchars($nama_murid); ?>
            </strong>

            <span>
                <?php echo htmlspecialchars($nis); ?>
            </span>
        </div>

    </div>

</div>
```

</header>

<!-- ================= DASHBOARD ================= -->

<main class="dashboard">

<div class="container dashboard-content">

<!-- WELCOME -->

<section class="welcome">

```
<div class="welcome-text">

    <small>Dashboard Murid</small>

    <h1>
        Halo, <?php echo htmlspecialchars($nama_murid); ?> 👋
    </h1>

    <p>
        Selamat datang di SLearning.
        Cek tugas dan Quiziz kamu hari ini.
    </p>

</div>

<div class="welcome-button">

    <a href="#tugas" class="btn btn-yellow">
        Lihat Tugas
        <svg width="16" height="16"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round">

            <path d="M5 12h14"/>
            <path d="m13 6 6 6-6 6"/>

        </svg>
    </a>

</div>
```

</section>

<!-- STATISTIK -->

<section class="stats">

```
<div class="stat-card">

    <div class="stat-icon">
        <svg width="21" height="21"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2"
             stroke-linecap="round"
             stroke-linejoin="round">

            <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/>
            <path d="M14 3v5h5"/>
            <path d="M9 13h6"/>
            <path d="M9 17h4"/>

        </svg>
    </div>

    <div>
        <div class="stat-number">4</div>
        <div class="stat-label">Tugas Aktif</div>
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <svg width="21" height="21"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <circle cx="12" cy="12" r="9"/>
            <path d="M12 8v5l3 2"/>

        </svg>

    </div>

    <div>
        <div class="stat-number">2</div>
        <div class="stat-label">Quiziz Tersedia</div>
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <svg width="21" height="21"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <path d="M20 6 9 17l-5-5"/>

        </svg>

    </div>

    <div>
        <div class="stat-number">8</div>
        <div class="stat-label">Tugas Selesai</div>
    </div>

</div>


<div class="stat-card">

    <div class="stat-icon">

        <svg width="21" height="21"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <path d="M12 2v20"/>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/>

        </svg>

    </div>

    <div>
        <div class="stat-number">92</div>
        <div class="stat-label">Nilai Rata-rata</div>
    </div>

</div>
```

</section>

<!-- TUGAS + QUICK MENU -->

<div class="main-grid">

<section id="tugas">

```
<div class="section-head">

    <div>
        <h2>Tugas Terbaru</h2>
        <p>Jangan sampai melewati deadline.</p>
    </div>

    <a href="#" class="btn btn-outline">
        Lihat Semua
    </a>

</div>


<div class="task-list">


    <div class="task-card">

        <div class="task-icon">

            <svg width="22" height="22"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round">

                <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9 13h6"/>
                <path d="M9 17h4"/>

            </svg>

        </div>

        <div class="task-content">

            <h3>
                Laporan Praktik Kerja Lapangan
            </h3>

            <p>
                PPLG • Deadline 4 Oktober 2026, 23.59 WIB
            </p>

        </div>

        <span class="task-status status-wait">
            Belum Kumpul
        </span>

    </div>


    <div class="task-card">

        <div class="task-icon">

            <svg width="22" height="22"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2">

                <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9 13h6"/>
                <path d="M9 17h4"/>

            </svg>

        </div>

        <div class="task-content">

            <h3>
                Praktik PBO — Class & Object
            </h3>

            <p>
                PPLG • Deadline 6 Oktober 2026
            </p>

        </div>

        <span class="task-status status-done">
            Sudah Kumpul
        </span>

    </div>


    <div class="task-card">

        <div class="task-icon">

            <svg width="22" height="22"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2">

                <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 1 1-1V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9 13h6"/>
                <path d="M9 17h4"/>

            </svg>

        </div>

        <div class="task-content">

            <h3>
                Tugas Argumentasi Bahasa Indonesia
            </h3>

            <p>
                Bahasa Indonesia • Deadline 8 Oktober 2026
            </p>

        </div>

        <span class="task-status status-wait">
            Belum Kumpul
        </span>

    </div>


</div>
```

</section>

<!-- QUICK MENU -->

<aside class="quick-card">

```
<h3>Akses Cepat</h3>

<p>
    Akses fitur SLearning dengan cepat.
</p>

<div class="quick-menu">

    <a href="#tugas">

        <svg width="19" height="19"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/>
            <path d="M14 3v5h5"/>

        </svg>

        Pengumpulan Tugas

    </a>


    <a href="#quiz">

        <svg width="19" height="19"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <circle cx="12" cy="12" r="9"/>
            <path d="M9.5 9.5a2.5 2.5 0 1 1 5 0c0 1.5-2.5 2-2.5 3.5"/>
            <path d="M12 17h.01"/>

        </svg>

        Quiziz

    </a>


    <a href="#aktivitas">

        <svg width="19" height="19"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <path d="M3 12h4l3-9 4 18 3-9h4"/>

        </svg>

        Aktivitas Saya

    </a>


    <a href="#">

        <svg width="19" height="19"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="2">

            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1.5-4 4-6 8-6s6.5 2 8 6"/>

        </svg>

        Profil Saya

    </a>

</div>
```

</aside>
=======
        <!-- BRAND -->

        <a
            href="murid.php"
            class="brand"
            aria-label="SLearning Dashboard"
        >

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

        <nav
            class="nav-links"
            aria-label="Navigasi murid"
        >

            <a
                href="murid.php"
                class="active"
            >
                Dashboard
            </a>

            <a href="#tugas">
                Tugas
            </a>

            <a href="#quiz">
                Quiziz
            </a>

            <a href="#aktivitas">
                Aktivitas
            </a>

        </nav>
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

</div>

<<<<<<< HEAD
<!-- QUIZ -->

<section class="quiz-section" id="quiz">

```
<div class="section-head">

    <div>
        <h2>Quiziz Tersedia</h2>
        <p>Kerjakan quiz yang diberikan guru.</p>
    </div>

    <a href="#" class="btn btn-outline">
        Semua Quiz
    </a>

</div>


<div class="quiz-grid">


    <article class="quiz-card">

        <div class="quiz-top">

            <span class="quiz-label">
                PPLG
            </span>

            <span class="quiz-time">
                30 menit
            </span>

        </div>

        <h3>
            PBO — Class & Object
        </h3>

        <p>
            20 soal pilihan ganda tentang konsep dasar PBO.
        </p>

        <div class="quiz-bottom">

            <span>
                20 Soal
            </span>

            <a href="#">
                Kerjakan →
            </a>

        </div>

    </article>


    <article class="quiz-card">

        <div class="quiz-top">
=======
        <!-- PROFILE -->

        <div class="profile">

            <div class="avatar">

                <?php
                echo strtoupper(
                    substr($nama_murid, 0, 1)
                );
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


<!-- ======================================================
     DASHBOARD
====================================================== -->

<main class="dashboard">

    <div class="container dashboard-content">
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

            <span class="quiz-label">
                B. INDONESIA
            </span>

<<<<<<< HEAD
            <span class="quiz-time">
                20 menit
            </span>
=======
        <!-- =================================================
             WELCOME
        ================================================== -->

        <section class="welcome">

            <div class="welcome-text">

                <small>
                    Dashboard Murid
                </small>

                <h1>
                    Halo,
                    <?php
                    echo htmlspecialchars($nama_murid);
                    ?>
                    👋
                </h1>

                <p>
                    Selamat datang di SLearning.
                    Cek tugas dan Quiziz kamu hari ini.
                </p>

            </div>


            <div class="welcome-button">

                <a
                    href="#tugas"
                    class="btn btn-yellow"
                >

                    Lihat Tugas

                    <svg
                        width="17"
                        height="17"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M5 12h14"/>
                        <path d="m13 6 6 6-6 6"/>

                    </svg>

                </a>

            </div>
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

        </section>

        <h3>
            Teks Argumentasi
        </h3>

<<<<<<< HEAD
        <p>
            Uji pemahaman tentang struktur dan kaidah argumentasi.
        </p>

        <div class="quiz-bottom">
=======
        <!-- =================================================
             STATISTIK
        ================================================== -->

        <section class="stats">
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

            <span>
                15 Soal
            </span>

<<<<<<< HEAD
            <a href="#">
                Kerjakan →
            </a>
=======
            <!-- TUGAS AKTIF -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path
                            d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                        />

                        <path d="M14 3v5h5"/>

                        <path d="M9 13h6"/>

                        <path d="M9 17h4"/>

                    </svg>

                </div>

                <div class="stat-content">

                    <div class="stat-number">
                        4
                    </div>

                    <div class="stat-label">
                        Tugas Aktif
                    </div>

                </div>

            </div>


            <!-- QUIZIZ -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        />

                        <path d="M12 8v5l3 2"/>

                    </svg>

                </div>

                <div class="stat-content">

                    <div class="stat-number">
                        2
                    </div>

                    <div class="stat-label">
                        Quiziz Tersedia
                    </div>

                </div>

            </div>


            <!-- TUGAS SELESAI -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M20 6L9 17l-5-5"/>

                    </svg>

                </div>

                <div class="stat-content">

                    <div class="stat-number">
                        8
                    </div>

                    <div class="stat-label">
                        Tugas Selesai
                    </div>

                </div>

            </div>
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db


<<<<<<< HEAD
    </article>


    <article class="quiz-card">

        <div class="quiz-top">

            <span class="quiz-label">
                SEJARAH
            </span>
=======
            <!-- NILAI -->

            <div class="stat-card">

                <div class="stat-icon">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M12 2v20"/>

                        <path
                            d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"
                        />

                    </svg>

                </div>

                <div class="stat-content">

                    <div class="stat-number">
                        92
                    </div>

                    <div class="stat-label">
                        Nilai Rata-rata
                    </div>

                </div>

            </div>


        </section>


        <!-- =================================================
             TUGAS + AKSES CEPAT
        ================================================== -->

        <div class="main-grid">


            <!-- TUGAS -->

            <section id="tugas">

                <div class="section-head">

                    <div>

                        <h2>
                            Tugas Terbaru
                        </h2>

                        <p>
                            Jangan sampai melewati deadline.
                        </p>

                    </div>

                    <a
                        href="#"
                        class="btn btn-outline"
                    >
                        Lihat Semua
                    </a>

                </div>


                <div class="task-list">


                    <!-- TASK 1 -->

                    <article class="task-card">

                        <div class="task-icon">

                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                                />

                                <path d="M14 3v5h5"/>

                                <path d="M9 13h6"/>

                                <path d="M9 17h4"/>

                            </svg>

                        </div>


                        <div class="task-content">

                            <h3>
                                Laporan Praktik Kerja Lapangan
                            </h3>

                            <p>
                                PPLG • Deadline 4 Oktober 2026, 23.59 WIB
                            </p>

                        </div>


                        <span class="task-status status-wait">
                            Belum Kumpul
                        </span>

                    </article>


                    <!-- TASK 2 -->

                    <article class="task-card">

                        <div class="task-icon">

                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                                />

                                <path d="M14 3v5h5"/>

                                <path d="M9 13h6"/>

                                <path d="M9 17h4"/>

                            </svg>

                        </div>


                        <div class="task-content">

                            <h3>
                                Praktik PBO — Class & Object
                            </h3>

                            <p>
                                PPLG • Deadline 6 Oktober 2026
                            </p>

                        </div>


                        <span class="task-status status-done">
                            Sudah Kumpul
                        </span>

                    </article>


                    <!-- TASK 3 -->

                    <article class="task-card">

                        <div class="task-icon">

                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                                />

                                <path d="M14 3v5h5"/>

                                <path d="M9 13h6"/>

                                <path d="M9 17h4"/>

                            </svg>

                        </div>


                        <div class="task-content">

                            <h3>
                                Tugas Argumentasi Bahasa Indonesia
                            </h3>

                            <p>
                                Bahasa Indonesia • Deadline 8 Oktober 2026
                            </p>

                        </div>


                        <span class="task-status status-wait">
                            Belum Kumpul
                        </span>

                    </article>


                    <!-- TASK 4 -->

                    <article class="task-card">

                        <div class="task-icon">

                            <svg
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path
                                    d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                                />

                                <path d="M14 3v5h5"/>

                                <path d="M9 13h6"/>

                                <path d="M9 17h4"/>

                            </svg>

                        </div>


                        <div class="task-content">

                            <h3>
                                Pengaruh Revolusi Besar di Eropa
                            </h3>

                            <p>
                                Sejarah • Deadline 10 Oktober 2026
                            </p>

                        </div>


                        <span class="task-status status-wait">
                            Belum Kumpul
                        </span>

                    </article>


                </div>

            </section>


            <!-- AKSES CEPAT -->

            <aside class="quick-card">

                <h3>
                    Akses Cepat
                </h3>

                <p>
                    Akses fitur SLearning dengan cepat.
                </p>


                <div class="quick-menu">


                    <a href="#tugas">

                        <svg
                            width="19"
                            height="19"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >

                            <path
                                d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
                            />

                            <path d="M14 3v5h5"/>

                        </svg>

                        Pengumpulan Tugas

                    </a>


                    <a href="#quiz">

                        <svg
                            width="19"
                            height="19"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >

                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M9.5 9.5a2.5 2.5 0 1 1 5 0c0 1.5-2.5 2-2.5 3.5"
                            />

                            <path d="M12 17h.01"/>

                        </svg>

                        Quiziz

                    </a>


                    <a href="#aktivitas">

                        <svg
                            width="19"
                            height="19"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <path d="M3 12h4l3-9 4 18 3-9h4"/>

                        </svg>

                        Aktivitas Saya

                    </a>


                    <a href="#">

                        <svg
                            width="19"
                            height="19"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <circle
                                cx="12"
                                cy="8"
                                r="4"
                            />

                            <path
                                d="M4 21c1.5-4 4-6 8-6s6.5 2 8 6"
                            />

                        </svg>

                        Profil Saya

                    </a>


                </div>

            </aside>


        </div>


        <!-- =================================================
             QUIZIZ
        ================================================== -->

        <section
            class="quiz-section"
            id="quiz"
        >

            <div class="section-head">

                <div>

                    <h2>
                        Quiziz Tersedia
                    </h2>

                    <p>
                        Kerjakan quiz yang diberikan guru.
                    </p>

                </div>

                <a
                    href="#"
                    class="btn btn-outline"
                >
                    Semua Quiz
                </a>

            </div>


            <div class="quiz-grid">


                <!-- QUIZ 1 -->

                <article class="quiz-card">

                    <div class="quiz-top">

                        <span class="quiz-label">
                            PPLG
                        </span>

                        <span class="quiz-time">
                            30 menit
                        </span>

                    </div>

                    <h3>
                        PBO — Class & Object
                    </h3>

                    <p>
                        20 soal pilihan ganda tentang konsep dasar Pemrograman Berorientasi Objek.
                    </p>

                    <div class="quiz-bottom">

                        <span>
                            20 Soal
                        </span>

                        <a href="#">
                            Kerjakan →
                        </a>

                    </div>

                </article>


                <!-- QUIZ 2 -->

                <article class="quiz-card">

                    <div class="quiz-top">

                        <span class="quiz-label">
                            B. INDONESIA
                        </span>

                        <span class="quiz-time">
                            20 menit
                        </span>

                    </div>

                    <h3>
                        Teks Argumentasi
                    </h3>

                    <p>
                        Uji pemahaman tentang struktur dan kaidah teks argumentasi.
                    </p>

                    <div class="quiz-bottom">

                        <span>
                            15 Soal
                        </span>

                        <a href="#">
                            Kerjakan →
                        </a>

                    </div>

                </article>


                <!-- QUIZ 3 -->

                <article class="quiz-card">

                    <div class="quiz-top">

                        <span class="quiz-label">
                            SEJARAH
                        </span>

                        <span class="quiz-time">
                            25 menit
                        </span>

                    </div>

                    <h3>
                        Kolonialisme & Imperialisme
                    </h3>

                    <p>
                        Materi kolonialisme, imperialisme, VOC, dan perlawanan terhadap kolonialisme.
                    </p>

                    <div class="quiz-bottom">

                        <span>
                            20 Soal
                        </span>

                        <a href="#">
                            Kerjakan →
                        </a>

                    </div>

                </article>


            </div>

        </section>


        <!-- =================================================
             AKTIVITAS
        ================================================== -->

        <section
            class="activity"
            id="aktivitas"
        >

            <div class="section-head">

                <div>

                    <h2>
                        Aktivitas Terbaru
                    </h2>

                    <p>
                        Riwayat aktivitas belajar kamu.
                    </p>

                </div>

            </div>
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

            <span class="quiz-time">
                25 menit
            </span>

            <div class="activity-card">

<<<<<<< HEAD
        <h3>
            Kolonialisme & Imperialisme
        </h3>

        <p>
            Materi kolonialisme, imperialisme, VOC, dan perlawanan.
        </p>

        <div class="quiz-bottom">

            <span>
                20 Soal
            </span>

            <a href="#">
                Kerjakan →
            </a>

        </div>

    </article>


</div>
```

</section>

<!-- AKTIVITAS -->

<section class="activity" id="aktivitas">

```
<div class="section-head">

    <div>
        <h2>Aktivitas Terbaru</h2>
        <p>Riwayat aktivitas belajar kamu.</p>
    </div>

</div>


<div class="activity-card">

    <div class="activity-item">

        <span class="activity-dot"></span>

        <div>
            <strong>
                Mengumpulkan tugas PBO — Class & Object
            </strong>

            <span>
                Hari ini • Nilai: 92
            </span>
        </div>

    </div>


    <div class="activity-item">

        <span class="activity-dot"></span>

        <div>
            <strong>
                Menyelesaikan Quiziz Jaringan Komputer
            </strong>

            <span>
                30 September 2026 • Nilai: 95
            </span>
        </div>

    </div>


    <div class="activity-item">

        <span class="activity-dot"></span>

        <div>
            <strong>
                Mengumpulkan tugas Bahasa Indonesia
            </strong>

            <span>
                29 September 2026 • Menunggu penilaian
            </span>
        </div>

    </div>


</div>
```

</section>

</div>

</main>

<!-- ================= FOOTER ================= -->

<footer>

```
<div class="container">

    <div class="footer-inner">

        <span>
            © <?php echo date('Y'); ?>
            <strong>SLearning</strong>
            — SMK Negeri 7 Batam
        </span>

        <span>
            Dashboard Murid
        </span>

    </div>

</div>
```

</footer>

</body>
</html>
=======

                <div class="activity-item">

                    <span class="activity-dot"></span>

                    <div>

                        <strong>
                            Mengumpulkan tugas PBO — Class & Object
                        </strong>

                        <span>
                            Hari ini • Nilai: 92
                        </span>

                    </div>

                </div>


                <div class="activity-item">

                    <span class="activity-dot"></span>

                    <div>

                        <strong>
                            Menyelesaikan Quiziz Jaringan Komputer
                        </strong>

                        <span>
                            30 September 2026 • Nilai: 95
                        </span>

                    </div>

                </div>


                <div class="activity-item">

                    <span class="activity-dot"></span>

                    <div>

                        <strong>
                            Mengumpulkan tugas Bahasa Indonesia
                        </strong>

                        <span>
                            29 September 2026 • Menunggu penilaian
                        </span>

                    </div>

                </div>


                <div class="activity-item">

                    <span class="activity-dot"></span>

                    <div>

                        <strong>
                            Login ke SLearning
                        </strong>

                        <span>
                            Hari ini • Dashboard Murid
                        </span>

                    </div>

                </div>


            </div>

        </section>


    </div>

</main>


<!-- ======================================================
     FOOTER
====================================================== -->

<footer>

    <div class="container">

        <div class="footer-inner">

            <span>

                ©
                <?php
                echo date('Y');
                ?>

                <strong>
                    SLearning
                </strong>

                — SMK Negeri 7 Batam

            </span>


            <span>
                Dashboard Murid
            </span>

        </div>

    </div>

</footer>


</body>

</html>
>>>>>>> 9bdcaecd98676393356710779773a2d75dca00db

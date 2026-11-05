<?php
// SLearning - SKAJU Learning | SMK Negeri 7 Batam
// Halaman beranda guest (belum login). Tombol auth masih dummy -> login.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SLearning — SKAJU Learning | SMK Negeri 7 Batam</title>
<meta name="description" content="SLearning adalah platform pembelajaran SMK Negeri 7 Batam: kerjakan Quiziz dan kumpulkan tugas tepat waktu dalam satu tempat.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
:root{
  --nav-yellow:#FFF8D6;
  --nav-yellow-dark:#F2E7B5;
  --sage-body:#F4F7F5;
  --sage-50:#E8F0E8;
  --sage-100:#D1E0D1;
  --sage-500:#6D8C6D;
  --sage-600:#577357;
  --sage-800:#2F402F;
  --sage-900:#1D261D;
  --radius-sm:12px;
  --radius-md:16px;
  --radius-lg:24px;
  --max:1200px;
  --shadow-sm:0 4px 12px rgba(109,140,109,.05);
  --shadow-md:0 16px 40px rgba(109,140,109,.12);
  --font:'Plus Jakarta Sans',sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:var(--font)}
html{scroll-behavior:smooth}
body{background:var(--sage-body);color:var(--sage-900);font-size:16px;line-height:1.6;min-height:100vh;position:relative;overflow-x:hidden;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;top:-10%;left:-5%;width:50vw;height:50vw;background:radial-gradient(circle,rgba(255,248,214,.8) 0%,rgba(255,248,214,0) 70%);border-radius:50%;pointer-events:none;z-index:-1}
body::after{content:'';position:fixed;bottom:-10%;right:-5%;width:60vw;height:60vw;background:radial-gradient(circle,rgba(109,140,109,.15) 0%,rgba(109,140,109,0) 70%);border-radius:50%;pointer-events:none;z-index:-1}
h1,h2,h3,h4{line-height:1.2;letter-spacing:-.02em;color:var(--sage-800)}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}
.container{max-width:var(--max);margin:0 auto;padding:0 32px}
:focus-visible{outline:3px solid var(--sage-500);outline-offset:3px;border-radius:6px}

/* ===== NAVBAR (kuning lembut, sama seperti dashboard murid) ===== */
.navbar{position:sticky;top:0;z-index:50;background:linear-gradient(180deg,var(--nav-yellow) 0%,#FFFDF6 100%);border-bottom:1px solid var(--nav-yellow-dark);box-shadow:0 2px 10px rgba(0,0,0,.02)}
.nav-inner{max-width:var(--max);margin:0 auto;padding:14px 32px;display:flex;align-items:center;gap:20px}
.brand{display:flex;align-items:center;gap:12px;color:var(--sage-800);min-height:44px}
.brand img{width:44px;height:44px;object-fit:contain}
.brand-text{line-height:1.2}
.brand-text strong{font-size:20px;font-weight:800;letter-spacing:-.5px;display:block;color:var(--sage-800)}
.brand-text span{font-size:11px;font-weight:600;color:var(--sage-600)}
.nav-links{display:flex;gap:6px;margin-left:auto}
.nav-links a{color:var(--sage-600);font-size:13px;font-weight:700;padding:10px 16px;border-radius:10px;transition:.3s;min-height:44px;display:inline-flex;align-items:center}
.nav-links a:hover{color:var(--sage-900);background:#fff;box-shadow:var(--shadow-sm)}
.nav-cta{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:800;font-size:13px;padding:11px 22px;border-radius:50px;min-height:44px;cursor:pointer;border:1px solid transparent;transition:.3s}
.btn:active{transform:scale(.98)}
.btn-yellow{background:var(--sage-500);color:#fff;box-shadow:0 4px 12px rgba(109,140,109,.3)}
.btn-yellow:hover{background:var(--sage-600);transform:translateY(-2px);box-shadow:0 6px 16px rgba(109,140,109,.4)}
.btn-ghost{background:#fff;border-color:var(--sage-100);color:var(--sage-800)}
.btn-ghost:hover{background:var(--sage-50);border-color:var(--sage-500);transform:translateY(-2px)}
.burger{display:none;margin-left:auto;background:#fff;border:1px solid var(--sage-50);color:var(--sage-600);width:44px;height:44px;border-radius:50%;cursor:pointer;box-shadow:var(--shadow-sm)}

/* ===== HERO ===== */
.hero{position:relative;overflow:hidden}
.hero-inner{position:relative;max-width:var(--max);margin:0 auto;padding:72px 32px 64px;display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
.badge{display:inline-flex;align-items:center;gap:8px;background:var(--sage-50);border:1px solid var(--sage-100);color:var(--sage-600);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px;padding:8px 14px;border-radius:50px;margin-bottom:20px}
.badge-dot{width:8px;height:8px;border-radius:50%;background:var(--sage-500);box-shadow:0 0 0 5px rgba(109,140,109,.2)}
.hero h1{font-size:clamp(34px,5vw,54px);font-weight:800;margin-bottom:16px;color:var(--sage-800)}
.hero h1 .hl{background:linear-gradient(transparent 62%,var(--nav-yellow-dark) 62%)}
.hero p.lead{color:var(--sage-600);font-size:16.5px;font-weight:500;max-width:520px;margin-bottom:28px}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px}
.hero-note{display:flex;gap:18px;flex-wrap:wrap;color:var(--sage-600);font-size:13px;font-weight:600}
.hero-note span{display:inline-flex;align-items:center;gap:7px}
.hero-note svg{flex-shrink:0}
/* mockup */
.mock{background:#fff;border-radius:var(--radius-lg);box-shadow:var(--shadow-md);overflow:hidden;border:1px solid var(--sage-50)}
.mock-top{display:flex;align-items:center;gap:8px;padding:14px 18px;border-bottom:1px solid var(--nav-yellow-dark);background:linear-gradient(180deg,var(--nav-yellow),#FFFDF6)}
.dot{width:11px;height:11px;border-radius:50%}
.mock-body{padding:20px;display:grid;gap:14px}
.mock-row{display:flex;align-items:center;gap:14px;border:1px solid var(--sage-100);background:#FAFCFA;border-radius:var(--radius-md);padding:14px}
.mock-ic{width:48px;height:48px;border-radius:14px;background:var(--nav-yellow);color:var(--sage-800);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mock-row.dark{background:linear-gradient(135deg,var(--sage-50) 0%,#fff 100%)}
.mock-row h4{font-size:14px;font-weight:800;margin-bottom:3px}
.mock-row small{font-size:12px;color:var(--sage-600);font-weight:600}
.pill{margin-left:auto;font-size:11px;font-weight:800;padding:6px 12px;border-radius:50px;white-space:nowrap}
.pill.ok{background:#E8F5E9;color:#2E7D32;border:1px solid #C8E6C9}
.pill.wait{background:var(--nav-yellow);color:#77651F;border:1px solid var(--nav-yellow-dark)}
.pill.go{background:var(--sage-500);color:#fff}
.mock-progress{height:8px;background:var(--sage-50);border-radius:99px;overflow:hidden;margin-top:8px}
.mock-progress i{display:block;height:100%;width:68%;background:var(--sage-500);border-radius:99px}

/* ===== STATS ===== */
.stats-inner{max-width:var(--max);margin:0 auto;padding:8px 32px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.stat{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:22px;text-align:center;transition:.4s}
.stat:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--sage-100)}
.stat strong{font-size:clamp(24px,3vw,32px);font-weight:800;display:block;line-height:1.1;color:var(--sage-800);margin-bottom:4px}
.stat span{font-size:13px;font-weight:600;color:var(--sage-600)}

/* ===== SECTIONS ===== */
.section{padding:72px 0}
.section.soft{background:linear-gradient(180deg,rgba(232,240,232,.55),rgba(232,240,232,.15))}
.eyebrow{display:inline-block;font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--sage-600);background:var(--nav-yellow);border:1px solid var(--nav-yellow-dark);padding:7px 14px;border-radius:50px;margin-bottom:14px}
.section h2{font-size:clamp(26px,3.5vw,36px);font-weight:800;margin-bottom:12px}
.section .sub{color:var(--sage-600);font-weight:500;max-width:640px;margin-bottom:32px;font-size:15.5px}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.grid-4{display:grid;grid-template-columns:repeat(2,1fr);gap:24px}
.card{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-lg);padding:30px;box-shadow:var(--shadow-sm);transition:all .4s ease}
.card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--sage-100)}
.card.featured{background:linear-gradient(135deg,var(--sage-500) 0%,var(--sage-600) 100%);color:#fff;border-color:var(--sage-600)}
.card.featured h3{color:#fff}
.card.featured p{color:rgba(255,255,255,.85)}
.icon-box{width:56px;height:56px;border-radius:16px;background:var(--nav-yellow);border:1px solid var(--nav-yellow-dark);color:var(--sage-800);display:flex;align-items:center;justify-content:center;margin-bottom:18px}
.card.featured .icon-box{background:var(--nav-yellow);color:var(--sage-800)}
.card h3{font-size:18px;font-weight:800;margin-bottom:8px}
.card p{font-size:14px;font-weight:500;color:var(--sage-600)}
.card.featured .tag{display:inline-block;margin-top:14px;font-size:12px;font-weight:800;color:var(--nav-yellow)}
.link-more{display:inline-flex;align-items:center;gap:6px;margin-top:14px;font-weight:800;font-size:13px;color:var(--sage-600)}
.link-more:hover{color:var(--sage-900)}
.card.featured .link-more{color:#fff}

/* jurusan */
.jurusan-card{display:flex;gap:14px;align-items:flex-start}
.jurusan-code{font-weight:800;font-size:13px;background:var(--sage-50);color:var(--sage-600);border:1px solid var(--sage-100);padding:8px 12px;border-radius:var(--radius-sm);white-space:nowrap}
.jurusan-card h3{font-size:16px;margin-bottom:4px}
.jurusan-card p{font-size:13.5px}

/* steps */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;counter-reset:step}
.step{background:#fff;border:1px solid var(--sage-50);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:30px;position:relative;transition:all .4s ease}
.step:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);border-color:var(--sage-100)}
.step::before{counter-increment:step;content:"0" counter(step);font-weight:800;font-size:13px;color:var(--sage-600);background:var(--nav-yellow);border:1px solid var(--nav-yellow-dark);padding:6px 12px;border-radius:50px;display:inline-block;margin-bottom:14px}
.step h3{font-size:17px;font-weight:800;margin-bottom:8px}
.step p{font-size:14px;font-weight:500;color:var(--sage-600)}

/* CTA */
.cta-band{background:linear-gradient(135deg,var(--sage-500) 0%,var(--sage-600) 100%);border-radius:var(--radius-lg);color:#fff;padding:48px;display:grid;grid-template-columns:1.2fr .8fr;gap:28px;align-items:center;position:relative;overflow:hidden;box-shadow:var(--shadow-md)}
.cta-band::after{content:"";position:absolute;right:-80px;top:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,248,214,.45),transparent 70%)}
.cta-band h2{font-size:clamp(24px,3vw,32px);margin-bottom:10px;color:#fff}
.cta-band h2 span{color:var(--nav-yellow)}
.cta-band p{color:rgba(255,255,255,.85);font-size:15px;font-weight:500;margin-bottom:22px}
.cta-band .btn-yellow{background:var(--nav-yellow);color:var(--sage-800);box-shadow:0 4px 12px rgba(0,0,0,.12)}
.cta-band .btn-yellow:hover{background:#fff}
.cta-band .btn-ghost{background:transparent;border-color:rgba(255,255,255,.5);color:#fff}
.cta-band .btn-ghost:hover{background:rgba(255,255,255,.12);border-color:#fff}
.cta-visual{display:flex;gap:12px;justify-content:flex-end;position:relative;z-index:1}
.mini-badge{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);color:#fff;border-radius:var(--radius-md);padding:16px 18px;font-size:13px;font-weight:600;min-width:150px}
.mini-badge strong{color:var(--nav-yellow);font-size:24px;font-weight:800;display:block}

/* footer (kuning lembut, senada navbar) */
footer{background:linear-gradient(0deg,var(--nav-yellow) 0%,#FFFDF6 100%);color:var(--sage-600);padding:56px 0 24px;border-top:1px solid var(--nav-yellow-dark);margin-top:24px}
.foot-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:32px;margin-bottom:36px}
.foot-brand{display:flex;gap:12px;align-items:center;margin-bottom:14px;color:var(--sage-800)}
.foot-brand img{width:48px;height:48px;object-fit:contain}
.foot-brand strong{font-weight:800;display:block;color:var(--sage-800)}
.foot-brand small{color:var(--sage-600);font-size:12px;font-weight:600}
footer p,footer li{font-size:14px;font-weight:500;color:var(--sage-600)}
footer ul{list-style:none;display:grid;gap:10px;margin-top:12px}
footer h4{color:var(--sage-800);font-size:14px;font-weight:800;margin-bottom:4px}
footer a:hover{color:var(--sage-900);text-decoration:underline}
.foot-bottom{border-top:1px solid var(--nav-yellow-dark);padding-top:18px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:12.5px;font-weight:600;color:var(--sage-600)}

/* reveal */
.reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease}
.reveal.visible{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation:none!important;transition:none!important}
  .reveal{opacity:1;transform:none}
}

@media(max-width:980px){
  .hero-inner{grid-template-columns:1fr;padding:52px 32px 44px}
  .grid-3{grid-template-columns:1fr 1fr}
  .steps{grid-template-columns:1fr}
  .cta-band{grid-template-columns:1fr;padding:32px}
  .cta-visual{justify-content:flex-start}
  .foot-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:720px){
  .container,.nav-inner,.hero-inner,.stats-inner{padding-left:20px;padding-right:20px}
  .nav-links,.nav-cta{display:none}
  .nav-links.open{display:flex;position:absolute;top:72px;left:12px;right:12px;background:#fff;border:1px solid var(--sage-50);box-shadow:var(--shadow-md);border-radius:var(--radius-md);padding:12px;flex-direction:column;z-index:60}
  .nav-cta.open{display:flex;position:absolute;top:290px;left:12px;right:12px;padding:0 12px 12px;background:#fff;border:1px solid var(--sage-50);box-shadow:var(--shadow-md);border-top:none;border-radius:0 0 var(--radius-md) var(--radius-md);flex-direction:column;z-index:60}
  .burger{display:inline-flex;align-items:center;justify-content:center}
  .grid-2,.grid-3,.grid-4{grid-template-columns:1fr}
  .stats-inner{grid-template-columns:1fr 1fr}
  .section{padding:52px 0}
  .foot-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
  <div class="nav-inner">
    <a class="brand" href="index.php" aria-label="SLearning Beranda">
      <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">
      <span class="brand-text"><strong>SLearning</strong><span>SKAJU Learning • SMKN 7</span></span>
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Navigasi utama">
      <a href="#fitur">Fitur</a>
      <a href="#jurusan">Jurusan</a>
      <a href="#alur">Cara Kerja</a>
      <a href="#kontak">Kontak</a>
    </nav>
    <div class="nav-cta" id="navCta">
      <a class="btn btn-ghost" href="login.php">Masuk</a>
      <a class="btn btn-yellow" href="login.php">Daftar Gratis</a>
    </div>
    <button class="burger" id="burger" aria-label="Buka menu" aria-expanded="false">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div>
      <span class="badge"><span class="badge-dot"></span> SKAJU LEARNING • SMK NEGERI 7 BATAM</span>
      <h1>Belajar lebih cepat.<br>Kumpul <span class="hl">tepat waktu.</span></h1>
      <p class="lead">SLearning menyatukan <strong>Quiziz</strong> dan <strong>pengumpulan tugas</strong> dalam satu tempat. Tanpa grup chat tercecer, tanpa deadline terlewat.</p>
      <div class="hero-actions">
        <a class="btn btn-yellow" href="login.php">Mulai Belajar
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn btn-ghost" href="#fitur">Lihat Fitur</a>
      </div>
      <div class="hero-note">
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6D8C6D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> 1.280 siswa aktif</span>
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6D8C6D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> 6 program keahlian</span>
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6D8C6D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Gratis untuk warga sekolah</span>
      </div>
    </div>
    <div class="mock" role="img" aria-label="Pratinjau dashboard SLearning berisi quiz dan tugas">
      <div class="mock-top"><span class="dot" style="background:#ff5f57"></span><span class="dot" style="background:#febc2e"></span><span class="dot" style="background:#28c840"></span><span style="margin-left:8px;font-size:12.5px;color:#577357;font-weight:700">slearning.smkn7 — dashboard murid</span></div>
      <div class="mock-body">
        <div class="mock-row">
          <div class="mock-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2F402F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5l3 2"/></svg></div>
          <div><h4>Quiziz: Jaringan Komputer Dasar</h4><small>20 soal • 30 menit • TJKT</small><div class="mock-progress"><i></i></div></div>
          <span class="pill go">Kerjakan</span>
        </div>
        <div class="mock-row dark">
          <div class="mock-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2F402F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></div>
          <div><h4>Tugas: Laporan PKL Panasonic</h4><small>Deadline 4 Okt 2026 • 23.59 WIB</small></div>
          <span class="pill wait">Belum kumpul</span>
        </div>
        <div class="mock-row">
          <div class="mock-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2F402F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
          <div><h4>PBO: Class &amp; Object</h4><small>Terkumpul • Nilai 92</small></div>
          <span class="pill ok">Sudah</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats">
  <div class="stats-inner">
    <div class="stat"><strong>1.280</strong><span>Peserta Didik</span></div>
    <div class="stat"><strong>6</strong><span>Program Keahlian</span></div>
    <div class="stat"><strong>2</strong><span>Fitur Inti: Quiziz &amp; Tugas</span></div>
    <div class="stat"><strong>100%</strong><span>Gratis Warga SKAJU</span></div>
  </div>
</div>

<!-- FITUR -->
<section class="section" id="fitur">
  <div class="container">
    <span class="eyebrow reveal">Fitur utama</span>
    <h2 class="reveal">Semua kebutuhan belajar, satu pintu.</h2>
    <p class="sub reveal">Dibuat untuk murid SMKN 7 Batam agar tidak lagi kumpul tugas via chat dan kejar-kejaran link quiz.</p>
    <div class="grid-2">
      <article class="card featured reveal">
        <div class="icon-box"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 115 0c0 1.5-2.5 2-2.5 3.5M12 17h.01"/></svg></div>
        <h3>Quiziz Interaktif</h3>
        <p>Kerjakan quiz pilihan ganda dari guru dengan timer otomatis, nilai langsung keluar, dan bisa diulang sesuai aturan kelas.</p>
        <span class="tag">Timer • Nilai instan • Bank soal per jurusan</span><br>
        <a class="link-more" href="login.php">Buka Quiziz →</a>
      </article>
      <article class="card reveal" style="border-top:4px solid var(--sage-500)">
        <div class="icon-box"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8z"/><path d="M14 3v5h5M12 11v6M9 14h6"/></svg></div>
        <h3>Tempat Pengumpulan Tugas</h3>
        <p>Upload file tugas (PDF, DOC, gambar, ZIP) sebelum deadline. Status Sudah / Belum tercatat otomatis, tidak perlu konfirmasi manual.</p>
        <a class="link-more" href="login.php">Kumpulkan Tugas →</a>
      </article>
    </div>
    <div class="grid-4" style="margin-top:18px">
      <article class="card reveal"><h3>Deadline Jelas</h3><p>Tanggal &amp; jam batas kumpul tampil di dashboard. Tidak ada alasan terlewat.</p></article>
      <article class="card reveal"><h3>Riwayat &amp; Nilai</h3><p>Semua quiz dan tugas tersimpan rapi, siap dipantau wali kelas.</p></article>
      <article class="card reveal"><h3>Per Jurusan</h3><p>Soal dan tugas disesuaikan PPLG, TJKT, DKV, TKL, MPLB, dan Pemasaran.</p></article>
      <article class="card reveal"><h3>Aman &amp; Ringan</h3><p>Jalan cepat di HP dan lab sekolah, tanpa aplikasi tambahan.</p></article>
    </div>
  </div>
</section>

<!-- JURUSAN -->
<section class="section soft" id="jurusan">
  <div class="container">
    <span class="eyebrow reveal">SMK Negeri 7 Batam</span>
    <h2 class="reveal">6 Konsentrasi keahlian unggulan.</h2>
    <p class="sub reveal">SLearning melayani seluruh jurusan aktif sesuai situs resmi sekolah.</p>
    <div class="grid-3">
      <article class="card jurusan-card reveal"><span class="jurusan-code">PPLG</span><div><h3>Rekayasa Perangkat Lunak</h3><p>Pengembangan Perangkat Lunak dan Gim.</p></div></article>
      <article class="card jurusan-card reveal"><span class="jurusan-code">TJKT</span><div><h3>Teknik Jaringan Komputer</h3><p>TJAT &amp; TKJ — Telekomunikasi dan jaringan.</p></div></article>
      <article class="card jurusan-card reveal"><span class="jurusan-code">DKV</span><div><h3>Desain Komunikasi Visual</h3><p>Desain grafis, ilustrasi, dan media kreatif.</p></div></article>
      <article class="card jurusan-card reveal"><span class="jurusan-code">TKL</span><div><h3>Teknik Instalasi Tenaga Listrik</h3><p>TITL — Ketenagalistrikan dan instalasi.</p></div></article>
      <article class="card jurusan-card reveal"><span class="jurusan-code">MPLB</span><div><h3>Manajemen Perkantoran</h3><p>Layanan bisnis dan administrasi perkantoran.</p></div></article>
      <article class="card jurusan-card reveal"><span class="jurusan-code">BR</span><div><h3>Pemasaran — Bisnis Retail</h3><p>Bisnis, pemasaran, dan retail modern.</p></div></article>
    </div>
  </div>
</section>

<!-- ALUR -->
<section class="section" id="alur">
  <div class="container">
    <span class="eyebrow reveal">Cara kerja</span>
    <h2 class="reveal">Mulai dalam 3 langkah.</h2>
    <p class="sub reveal">Belum punya akun? Minta akun ke wali kelas / admin sekolah, lalu masuk.</p>
    <div class="steps">
      <div class="step reveal"><h3>Masuk dengan akun sekolah</h3><p>Gunakan NIS / username yang diberikan guru. Belum punya? Klik Daftar dan hubungi admin.</p></div>
      <div class="step reveal"><h3>Kerjakan Quiziz &amp; Tugas</h3><p>Buka dashboard murid, pilih quiz yang tersedia atau upload file tugas sebelum deadline.</p></div>
      <div class="step reveal"><h3>Pantau status &amp; nilai</h3><p>Status Sudah / Belum dan nilai tampil otomatis. Fokus belajar, sisanya sistem yang catat.</p></div>
    </div>
    <div class="cta-band reveal" style="margin-top:28px">
      <div>
        <h2>Siap masuk ke <span>SLearning?</span></h2>
        <p>Akun diberikan oleh sekolah. Jika sudah punya akun, langsung masuk dan cek tugas hari ini.</p>
        <div class="hero-actions">
          <a class="btn btn-yellow" href="login.php">Masuk Sekarang</a>
          <a class="btn btn-ghost" href="https://smkn7batam.sch.id/" target="_blank" rel="noopener">Web Resmi SMKN 7</a>
        </div>
      </div>
      <div class="cta-visual">
        <div class="mini-badge"><strong>24/7</strong>Akses kapan saja</div>
        <div class="mini-badge"><strong>2</strong>Fitur inti aktif</div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer id="kontak">
  <div class="container">
    <div class="foot-grid">
      <div>
        <div class="foot-brand">
          <img src="assets/img/logo-smkn7.png" alt="Logo SMK Negeri 7 Batam">
          <div><strong>SLearning • SKAJU Learning</strong><small>SMK Negeri 7 Batam</small></div>
        </div>
        <p>Platform pembelajaran untuk quiz dan pengumpulan tugas. Mencetak generasi siap kerja, terampil, dan berdaya saing global.</p>
      </div>
      <div>
        <h4>Menu</h4>
        <ul>
          <li><a href="#fitur">Fitur Quiziz &amp; Tugas</a></li>
          <li><a href="#jurusan">Program Keahlian</a></li>
          <li><a href="#alur">Cara Kerja</a></li>
          <li><a href="login.php">Masuk / Daftar</a></li>
        </ul>
      </div>
      <div>
        <h4>Kontak Sekolah</h4>
        <ul>
          <li>Perumahan Sekawan Pemko, Belian, Batam Kota, Kepulauan Riau 29463</li>
          <li>(0778) 4805790</li>
          <li>smknegeri7batam@gmail.com</li>
          <li><a href="https://smkn7batam.sch.id/" target="_blank" rel="noopener">smkn7batam.sch.id</a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?php echo date('Y'); ?> SLearning — SMK Negeri 7 Batam. Untuk keperluan pembelajaran.</span>
      <span>Halaman guest • <a href="cek-db.php" style="color:#577357">cek-db</a></span>
      <span style="width:100%;text-align:center">Dikembangkan oleh siswa SMK Negeri 7 Batam &bull; Guru Pembimbing: <strong>Debby Fransurya</strong></span>
    </div>
  </div>
</footer>

<script>
(function(){
  var burger=document.getElementById('burger'),
      links=document.getElementById('navLinks'),
      cta=document.getElementById('navCta');
  if(burger){
    burger.addEventListener('click',function(){
      var open=links.classList.toggle('open');
      cta.classList.toggle('open',open);
      burger.setAttribute('aria-expanded',open?'true':'false');
    });
    links.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click',function(){links.classList.remove('open');cta.classList.remove('open');});
    });
  }
  var io=('IntersectionObserver' in window)?new IntersectionObserver(function(es){
    es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target);}});
  },{threshold:.12}):null;
  document.querySelectorAll('.reveal').forEach(function(el){if(io){io.observe(el);}else{el.classList.add('visible');}});
})();
</script>
</body>
</html>
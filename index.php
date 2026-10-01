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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
:root{
  --bg:#ffffff;
  --bg-soft:#f7f7f8;
  --dark:#0a0a0b;
  --surface:#141416;
  --surface-2:#1c1c1f;
  --yellow:#ffc107;
  --yellow-hover:#ffb300;
  --yellow-soft:#fff6d6;
  --text:#0a0a0b;
  --text-light:#fafafa;
  --muted:#6b7280;
  --muted-dark:#a1a1aa;
  --border:#e7e7ea;
  --border-dark:#2a2a2e;
  --radius:16px;
  --radius-lg:22px;
  --max:1200px;
  --shadow:0 20px 50px rgba(0,0,0,.12);
  --font-h:'Poppins',sans-serif;
  --font-b:'Open Sans',sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:var(--font-b);font-size:16px;line-height:1.6;color:var(--text);background:var(--bg);-webkit-font-smoothing:antialiased;overflow-x:hidden}
h1,h2,h3,h4{font-family:var(--font-h);line-height:1.15;letter-spacing:-.02em}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}
.container{max-width:var(--max);margin:0 auto;padding:0 24px}
:focus-visible{outline:3px solid var(--yellow);outline-offset:3px;border-radius:6px}

/* ===== NAVBAR (putih) ===== */
.navbar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);box-shadow:0 8px 30px rgba(0,0,0,.08)}
.nav-inner{max-width:var(--max);margin:0 auto;padding:14px 24px;display:flex;align-items:center;gap:20px}
.brand{display:flex;align-items:center;gap:12px;color:var(--text);min-height:44px}
.brand img{width:44px;height:44px;object-fit:contain;background:#fff;border-radius:12px;padding:4px;border:1px solid var(--border)}
.brand-text{line-height:1.2}
.brand-text strong{font-family:var(--font-h);font-size:17px;display:block}
.brand-text span{font-size:11.5px;color:var(--muted);letter-spacing:.04em;text-transform:uppercase}
.nav-links{display:flex;gap:6px;margin-left:auto}
.nav-links a{color:#4b5563;font-size:14px;font-weight:600;padding:10px 14px;border-radius:10px;transition:.2s;min-height:44px;display:inline-flex;align-items:center}
.nav-links a:hover{color:var(--text);background:var(--yellow-soft)}
.nav-cta{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:var(--font-h);font-weight:600;font-size:14px;padding:12px 20px;border-radius:12px;min-height:44px;cursor:pointer;border:1px solid transparent;transition:transform .2s,box-shadow .2s,background .2s}
.btn:active{transform:scale(.98)}
.btn-yellow{background:var(--yellow);color:#141400}
.btn-yellow:hover{background:var(--yellow-hover);box-shadow:0 8px 24px rgba(255,193,7,.35)}
.btn-ghost{border-color:#d4d4d8;color:var(--text);background:#fff}
.btn-ghost:hover{border-color:var(--dark);color:var(--dark)}
.btn-dark{background:var(--dark);color:#fff}
.btn-dark:hover{background:#1d1d20}
.burger{display:none;margin-left:auto;background:#fff;border:1px solid var(--border);color:var(--text);width:44px;height:44px;border-radius:10px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.08)}

/* ===== HERO (putih) ===== */
.hero{background:radial-gradient(800px 380px at 85% 0%,rgba(255,193,7,.22),transparent),var(--bg);color:var(--text);overflow:hidden;position:relative;border-bottom:1px solid var(--border)}
.hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(#ececf0 1px,transparent 1px),linear-gradient(90deg,#ececf0 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(750px 420px at 50% 0%,black,transparent)}
.hero-inner{position:relative;max-width:var(--max);margin:0 auto;padding:72px 24px 64px;display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
.badge{display:inline-flex;align-items:center;gap:8px;background:var(--yellow-soft);border:1px solid #f0d878;color:#7a5b00;font-size:12.5px;font-weight:700;letter-spacing:.02em;padding:8px 14px;border-radius:999px;margin-bottom:20px}
.badge-dot{width:8px;height:8px;border-radius:50%;background:var(--yellow);box-shadow:0 0 0 5px rgba(255,193,7,.25)}
.hero h1{font-size:clamp(34px,5vw,56px);font-weight:800;margin-bottom:16px;color:var(--text)}
.hero h1 .hl{background:linear-gradient(transparent 62%,var(--yellow) 62%)}
.hero p.lead{color:#4b5563;font-size:17px;max-width:520px;margin-bottom:28px}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px}
.hero .btn-ghost{border-color:#d4d4d8;color:var(--text);background:#fff}
.hero .btn-ghost:hover{border-color:var(--dark);color:var(--dark)}
.hero-note{display:flex;gap:18px;flex-wrap:wrap;color:var(--muted);font-size:13.5px}
.hero-note span{display:inline-flex;align-items:center;gap:7px}
.hero-note svg{flex-shrink:0}
/* mockup */
.mock{background:#fff;color:var(--text);border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;border:1px solid #ececf0}
.mock-top{display:flex;align-items:center;gap:8px;padding:14px 18px;border-bottom:1px solid #eee;background:#fafafb}
.dot{width:11px;height:11px;border-radius:50%}
.mock-body{padding:20px;display:grid;gap:14px}
.mock-row{display:flex;align-items:center;gap:14px;border:1px solid #eee;border-radius:14px;padding:14px}
.mock-ic{width:44px;height:44px;border-radius:12px;background:var(--yellow-soft);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mock-row.dark{background:var(--dark);color:#fff;border-color:var(--dark)}
.mock-row.dark small{color:#b9b9bf}
.mock-row h4{font-size:14.5px;margin-bottom:3px}
.mock-row small{font-size:12px;color:var(--muted)}
.pill{margin-left:auto;font-size:11px;font-weight:700;padding:6px 12px;border-radius:999px;white-space:nowrap}
.pill.ok{background:#e7f7ee;color:#15803d}
.pill.wait{background:var(--yellow-soft);color:#92600a}
.pill.go{background:var(--yellow);color:#201a00}
.mock-progress{height:8px;background:#eee;border-radius:99px;overflow:hidden;margin-top:8px}
.mock-progress i{display:block;height:100%;width:68%;background:var(--yellow);border-radius:99px}

/* ===== STATS ===== */
.stats{background:var(--yellow);color:#1a1400}
.stats-inner{max-width:var(--max);margin:0 auto;padding:22px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.stat{text-align:center;padding:8px;border-left:1px solid rgba(0,0,0,.15)}
.stat:first-child{border-left:none}
.stat strong{font-family:var(--font-h);font-size:clamp(22px,3vw,32px);display:block;line-height:1}
.stat span{font-size:13px;font-weight:600;opacity:.8}

/* ===== SECTIONS ===== */
.section{padding:72px 0}
.section.soft{background:var(--bg-soft)}
.eyebrow{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#92600a;background:var(--yellow-soft);border:1px solid #f5df8a;padding:7px 14px;border-radius:999px;margin-bottom:14px}
.section h2{font-size:clamp(26px,3.5vw,38px);margin-bottom:12px}
.section .sub{color:var(--muted);max-width:640px;margin-bottom:32px;font-size:16px}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.grid-4{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:26px;transition:transform .22s,box-shadow .22s,border-color .22s}
.card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.09);border-color:#d8d8dc}
.card.featured{background:var(--dark);color:#fff;border-color:var(--dark)}
.card.featured p{color:#b9b9bf}
.icon-box{width:52px;height:52px;border-radius:14px;background:var(--dark);color:var(--yellow);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
.card.featured .icon-box{background:var(--yellow);color:#141400}
.card h3{font-size:18px;margin-bottom:8px}
.card p{font-size:14px;color:var(--muted)}
.card.featured .tag{display:inline-block;margin-top:14px;font-size:12px;font-weight:700;color:var(--yellow)}
.link-more{display:inline-flex;align-items:center;gap:6px;margin-top:14px;font-weight:700;font-size:13.5px;color:#111}
.card.featured .link-more{color:var(--yellow)}

/* jurusan */
.jurusan-card{display:flex;gap:14px;align-items:flex-start}
.jurusan-code{font-family:var(--font-h);font-weight:800;font-size:13px;background:var(--dark);color:var(--yellow);padding:8px 12px;border-radius:10px;white-space:nowrap}
.jurusan-card h3{font-size:16px;margin-bottom:4px}
.jurusan-card p{font-size:13.5px}

/* steps */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;counter-reset:step}
.step{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:26px;position:relative}
.step::before{counter-increment:step;content:"0" counter(step);font-family:var(--font-h);font-weight:800;font-size:13px;color:#92600a;background:var(--yellow-soft);border:1px solid #f0d878;padding:6px 12px;border-radius:999px;display:inline-block;margin-bottom:14px}
.step h3{font-size:17px;margin-bottom:8px}
.step p{font-size:14px;color:var(--muted)}

/* CTA */
.cta-band{background:var(--dark);border-radius:24px;color:#fff;padding:48px;display:grid;grid-template-columns:1.2fr .8fr;gap:28px;align-items:center;position:relative;overflow:hidden}
.cta-band::after{content:"";position:absolute;right:-80px;top:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,193,7,.35),transparent 70%)}
.cta-band h2{font-size:clamp(24px,3vw,34px);margin-bottom:10px}
.cta-band h2 span{color:var(--yellow)}
.cta-band p{color:#b9b9bf;font-size:15px;margin-bottom:22px}
.cta-visual{display:flex;gap:12px;justify-content:flex-end}
.mini-badge{background:#1c1c1f;border:1px solid #333;color:#fff;border-radius:14px;padding:16px 18px;font-size:13px;min-width:150px}
.mini-badge strong{color:var(--yellow);font-size:22px;display:block;font-family:var(--font-h)}

/* footer */
footer{background:#08080a;color:#d4d4d8;padding:56px 0 24px;border-top:4px solid var(--yellow)}
.foot-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:32px;margin-bottom:36px}
.foot-brand{display:flex;gap:12px;align-items:center;margin-bottom:14px;color:#fff}
.foot-brand img{width:48px;height:48px;background:#fff;border-radius:12px;padding:4px}
.foot-brand strong{font-family:var(--font-h);display:block}
.foot-brand small{color:var(--muted-dark);font-size:12px}
footer p,footer li{font-size:14px;color:#a1a1aa}
footer ul{list-style:none;display:grid;gap:10px;margin-top:12px}
footer h4{color:#fff;font-size:14px;margin-bottom:4px}
footer a:hover{color:var(--yellow)}
.foot-bottom{border-top:1px solid #232326;padding-top:18px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:12.5px;color:#71717a}

/* reveal */
.reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease}
.reveal.visible{opacity:1;transform:none}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation:none!important;transition:none!important}
  .reveal{opacity:1;transform:none}
}

@media(max-width:980px){
  .hero-inner{grid-template-columns:1fr;padding:52px 24px 44px}
  .grid-3{grid-template-columns:1fr 1fr}
  .steps{grid-template-columns:1fr}
  .cta-band{grid-template-columns:1fr;padding:32px}
  .cta-visual{justify-content:flex-start}
  .foot-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:720px){
  .nav-links,.nav-cta{display:none}
  .nav-links.open{display:flex;position:absolute;top:72px;left:12px;right:12px;background:#fff;border:1px solid var(--border);box-shadow:0 16px 40px rgba(0,0,0,.12);border-radius:14px;padding:12px;flex-direction:column;z-index:60}
  .nav-cta.open{display:flex;position:absolute;top:290px;left:12px;right:12px;padding:0 12px 12px;background:#fff;border:1px solid var(--border);box-shadow:0 16px 40px rgba(0,0,0,.12);border-top:none;border-radius:0 0 14px 14px;flex-direction:column;z-index:60}
  .burger{display:inline-flex;align-items:center;justify-content:center}
  .grid-2,.grid-3,.grid-4{grid-template-columns:1fr}
  .stats-inner{grid-template-columns:1fr 1fr}
  .stat:nth-child(3){border-left:none}
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
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffc107" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> 1.280 siswa aktif</span>
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffc107" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> 6 program keahlian</span>
        <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffc107" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Gratis untuk warga sekolah</span>
      </div>
    </div>
    <div class="mock" role="img" aria-label="Pratinjau dashboard SLearning berisi quiz dan tugas">
      <div class="mock-top"><span class="dot" style="background:#ff5f57"></span><span class="dot" style="background:#febc2e"></span><span class="dot" style="background:#28c840"></span><span style="margin-left:8px;font-size:12.5px;color:#6b7280;font-weight:600">slearning.smkn7 — dashboard murid</span></div>
      <div class="mock-body">
        <div class="mock-row">
          <div class="mock-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#141400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5l3 2"/></svg></div>
          <div><h4>Quiziz: Jaringan Komputer Dasar</h4><small>20 soal • 30 menit • TJKT</small><div class="mock-progress"><i></i></div></div>
          <span class="pill go">Kerjakan</span>
        </div>
        <div class="mock-row dark">
          <div class="mock-ic" style="background:#ffc107"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#141400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></div>
          <div><h4>Tugas: Laporan PKL Panasonic</h4><small>Deadline 4 Okt 2026 • 23.59 WIB</small></div>
          <span class="pill wait">Belum kumpul</span>
        </div>
        <div class="mock-row">
          <div class="mock-ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#141400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
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
      <article class="card reveal" style="border-top:4px solid var(--yellow)">
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
      <span>Halaman guest • <a href="cek-db.php" style="color:#71717a">cek-db</a></span>
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

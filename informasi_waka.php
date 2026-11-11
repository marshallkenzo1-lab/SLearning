<?php
session_start();
// Halaman ini bersifat publik, tidak ada pengecekan session strict.
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Wakil Kepala Sekolah — SMKN 7 Batam</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
:root { 
    --nav-yellow: #FFF8D6; --nav-yellow-dark: #F2E7B5; --sage-body: #F4F7F5; --sage-50: #E8F0E8; --sage-100: #D1E0D1;
    --sage-300: #9FBA9F; --sage-500: #6D8C6D; --sage-600: #577357; --sage-800: #2F402F; --sage-900: #1D261D;
    --shadow-sm: 0 4px 16px rgba(109, 140, 109, 0.06); --shadow-md: 0 8px 24px rgba(109, 140, 109, 0.12);
    --radius-md: 16px; --radius-lg: 24px;
}
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; position: relative; overflow-x: hidden; }

/* Ornamen Background */
body::before { content: ''; position: absolute; top: -10%; left: -5%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(255, 248, 214, 0.7) 0%, transparent 70%); border-radius: 50%; pointer-events: none; z-index: -1; }

.header { background: #FFFFFF; padding: 16px 32px; border-bottom: 1px solid var(--sage-100); box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 100; }
.header-container { max-width: 1100px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 20px; font-weight: 800; color: var(--sage-800); }
.logo svg { width: 32px; height: 32px; color: var(--sage-500); }
.btn-kembali { background: var(--sage-50); color: var(--sage-800); padding: 10px 20px; border-radius: 50px; font-size: 13.5px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; border: 1px solid var(--sage-100); }
.btn-kembali:hover { background: var(--sage-100); transform: translateY(-2px); }

.container { max-width: 1100px; margin: 50px auto 80px; padding: 0 32px; }

.hero-section { text-align: center; margin-bottom: 60px; }
.hero-section h1 { font-size: 34px; font-weight: 800; color: var(--sage-900); margin-bottom: 16px; letter-spacing: -0.5px; }
.hero-section p { font-size: 15px; color: var(--sage-600); max-width: 650px; margin: 0 auto; font-weight: 500; line-height: 1.7; }

.waka-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; }
.waka-card { background: #FFFFFF; border-radius: var(--radius-lg); padding: 36px 24px; text-align: center; border: 1px solid var(--sage-100); box-shadow: var(--shadow-sm); transition: all 0.4s ease; position: relative; overflow: hidden; }
.waka-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-md); border-color: var(--sage-300); }
.waka-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: var(--sage-500); opacity: 0; transition: 0.4s; }
.waka-card:hover::before { opacity: 1; }

.waka-avatar { width: 110px; height: 110px; border-radius: 50%; margin: 0 auto 20px; background: var(--nav-yellow); border: 4px solid #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 44px; color: var(--sage-600); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
.waka-jabatan { display: inline-block; background: var(--sage-50); color: var(--sage-800); font-size: 12px; font-weight: 800; padding: 6px 14px; border-radius: 50px; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid var(--sage-100); }
.waka-nama { font-size: 18px; font-weight: 800; color: var(--sage-900); margin-bottom: 12px; }
.waka-deskripsi { font-size: 13.5px; color: var(--sage-600); font-weight: 500; line-height: 1.6; }

@media (max-width: 768px) { .header-container { flex-direction: column; gap: 16px; } }
</style>
</head>
<body>

<header class="header">
    <div class="header-container">
        <a href="login.php" class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            SLearning SMKN 7 Batam
        </a>
        <a href="javascript:history.back()" class="btn-kembali">← Kembali</a>
    </div>
</header>

<main class="container">
    <div class="hero-section">
        <h1>Struktur Pimpinan & Wakil Kepala Sekolah</h1>
        <p>Mengenal jajaran Wakil Kepala Sekolah (Waka) SMKN 7 Batam yang berdedikasi dalam memanajemen kurikulum, siswa, fasilitas sarana prasarana, hingga hubungan dengan dunia industri.</p>
    </div>

    <div class="waka-grid">
        <!-- WAKA KURIKULUM -->
        <div class="waka-card">
            <div class="waka-avatar">📖</div>
            <span class="waka-jabatan">Waka Kurikulum</span>
            <div class="waka-nama">Nama Guru, S.Pd., Gr.</div>
            <div class="waka-deskripsi">Bertanggung jawab atas jadwal pelajaran, kalender akademik, silabus, serta evaluasi hasil belajar mengajar (UTS & UAS).</div>
        </div>

        <!-- WAKA KESISWAAN -->
        <div class="waka-card">
            <div class="waka-avatar">👥</div>
            <span class="waka-jabatan">Waka Kesiswaan</span>
            <div class="waka-nama">Nama Guru, S.Kom.</div>
            <div class="waka-deskripsi">Menangani aktivitas siswa, tata tertib, pembinaan OSIS, kegiatan ekstrakurikuler, dan kedisiplinan sekolah.</div>
        </div>

        <!-- WAKA HUBIN / HUMAS -->
        <div class="waka-card">
            <div class="waka-avatar">🤝</div>
            <span class="waka-jabatan">Waka Hubin / Humas</span>
            <div class="waka-nama">Nama Guru, S.T.</div>
            <div class="waka-deskripsi">Mengelola penyaluran Praktik Kerja Lapangan (PKL) siswa ke perusahaan serta kemitraan dengan dunia industri.</div>
        </div>

        <!-- WAKA SARPRAS -->
        <div class="waka-card">
            <div class="waka-avatar">🏫</div>
            <span class="waka-jabatan">Waka Sarpras</span>
            <div class="waka-nama">Nama Guru, S.Pd.</div>
            <div class="waka-deskripsi">Bertugas memelihara seluruh sarana dan prasarana, termasuk fasilitas laboratorium jurusan (RPL, TJKT, DKV, dll).</div>
        </div>
    </div>
</main>
</body>
</html>
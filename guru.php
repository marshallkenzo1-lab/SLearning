<?php
// SLearning — Dashboard Guru (Fix Struktur Database & Fitur Status Murid + Perpanjangan Deadline)
session_start();
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['jenis'] ?? '') === 'murid') { header("Location: login.php"); exit; }
$user_id = (int)$_SESSION['user_id'];

$page = $_GET['page'] ?? 'beranda';
$detail_kelas_id = (int)($_GET['id'] ?? 0);

// Kolom gambar untuk soal Quiziz (dibuat otomatis jika belum tersedia).
$cekGambarSoal = @mysqli_query($koneksi, "SHOW COLUMNS FROM quiz_soal LIKE 'gambar'");
if ($cekGambarSoal && mysqli_num_rows($cekGambarSoal) === 0) {
    @mysqli_query($koneksi, "ALTER TABLE quiz_soal ADD COLUMN gambar VARCHAR(255) NULL");
}

function uploadGambarSoal($field = 'gambar_soal') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [true, ''];
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return [false, 'Upload foto gagal. Coba pilih foto lagi.'];
    if ($f['size'] > 5 * 1024 * 1024) return [false, 'Ukuran foto maksimal 5 MB.'];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || empty($info['mime'])) return [false, 'File harus berupa gambar JPG, PNG, atau WEBP.'];
    $exts = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    if (!isset($exts[$info['mime']])) return [false, 'Format foto harus JPG, PNG, atau WEBP.'];
    $dir = __DIR__ . '/uploads/quiz_soal/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) return [false, 'Folder upload foto tidak bisa dibuat.'];
    $nama = 'soal_' . bin2hex(random_bytes(8)) . '.' . $exts[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], $dir . $nama)) return [false, 'Foto gagal disimpan. Periksa izin folder uploads.'];
    return [true, 'uploads/quiz_soal/' . $nama];
}


function flash_set($msg, $type='success'){ $_SESSION['flash_msg']=$msg; $_SESSION['flash_type']=$type; }
$flash_msg = $_SESSION['flash_msg'] ?? ''; $flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// ---------- REKAP ABSENSI: RENTANG TANGGAL, DATA, DAN EKSPOR ----------
function rekapRentangTanggal($periode, $tanggalInput) {
    $dt = DateTime::createFromFormat('!Y-m-d', $tanggalInput);
    if (!$dt) $dt = new DateTime('today');
    if ($periode === 'mingguan') {
        $mulai = clone $dt;
        $mulai->modify('monday this week');
        $akhir = clone $mulai;
        $akhir->modify('+6 days');
    } else {
        $mulai = new DateTime($dt->format('Y-m-01'));
        $akhir = clone $mulai;
        $akhir->modify('last day of this month');
    }
    return [$mulai->format('Y-m-d'), $akhir->format('Y-m-d')];
}

function rekapAmbilData($koneksi, $user_id, $kelas_id, $mulai, $akhir) {
    $data = [];
    // Pastikan kelas benar-benar milik guru yang sedang login.
    $cek = mysqli_query($koneksi, "SELECT id, nama_kelas FROM kelas WHERE id=".(int)$kelas_id." AND guru_id=".(int)$user_id." LIMIT 1");
    $kelas = $cek ? mysqli_fetch_assoc($cek) : null;
    if (!$kelas) return [$kelas, [], 0];

    $qSesi = mysqli_query($koneksi, "SELECT id, tanggal, judul_absensi FROM absensi WHERE guru_id=".(int)$user_id." AND kelas_id=".(int)$kelas_id." AND tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$mulai)."' AND '".mysqli_real_escape_string($koneksi,$akhir)."' ORDER BY tanggal ASC, id ASC");
    $sesi = [];
    if ($qSesi) while ($r = mysqli_fetch_assoc($qSesi)) $sesi[] = $r;
    $totalSesi = count($sesi);
    if (!$totalSesi) return [$kelas, [], 0];

    $qMurid = mysqli_query($koneksi, "SELECT l.id AS user_id, l.nama AS nama_murid FROM anggota_kelas ak JOIN login l ON l.id=ak.user_id WHERE ak.kelas_id=".(int)$kelas_id." ORDER BY l.nama ASC");
    $hariIni = date('Y-m-d');
    while ($qMurid && ($m = mysqli_fetch_assoc($qMurid))) {
        $r = [
            'nama_murid' => $m['nama_murid'], 'total_sesi' => $totalSesi,
            'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'belum_absen' => 0,
            'persentase' => 0
        ];
        foreach ($sesi as $ss) {
            $aid = (int)$ss['id']; $mid = (int)$m['user_id'];
            $qa = mysqli_query($koneksi, "SELECT status FROM absensi_hadir WHERE absensi_id=$aid AND user_id=$mid LIMIT 1");
            $ah = $qa ? mysqli_fetch_assoc($qa) : null;
            if ($ah && isset($ah['status'])) {
                $st = strtolower(trim($ah['status']));
                if (in_array($st, ['hadir','izin','sakit','alpa'], true)) $r[$st]++;
                else $r['belum_absen']++;
            } else {
                // Sesi yang sudah lewat otomatis dihitung Alpa; sesi hari ini belum dianggap Alpa.
                if ($ss['tanggal'] < $hariIni) $r['alpa']++;
                else $r['belum_absen']++;
            }
        }
        $r['persentase'] = $totalSesi > 0 ? round(($r['hadir'] / $totalSesi) * 100, 1) : 0;
        $data[] = $r;
    }
    return [$kelas, $data, $totalSesi];
}

function xlsxKolom($n) {
    $s = '';
    while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = (int)(($n - 1) / 26); }
    return $s;
}
function xlsxEscape($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8'); }
function kirimXlsx($namaFile, $rows) {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'Ekspor XLSX membutuhkan ekstensi PHP ZipArchive. Aktifkan extension=zip di php.ini XAMPP, lalu restart Apache.';
        exit;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'slx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { @unlink($tmp); http_response_code(500); echo 'Gagal membuat file Excel.'; exit; }
    $sheetRows = '';
    foreach ($rows as $ri => $row) {
        $cells = '';
        foreach (array_values($row) as $ci => $val) {
            $ref = xlsxKolom($ci + 1) . ($ri + 1);
            if (is_int($val) || is_float($val) || (is_string($val) && $val !== '' && is_numeric($val))) {
                $cells .= '<c r="'.$ref.'"><v>'.xlsxEscape($val).'</v></c>';
            } else {
                $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.xlsxEscape($val).'</t></is></c>';
            }
        }
        $sheetRows .= '<row r="'.($ri + 1).'">'.$cells.'</row>';
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekap Absensi" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$namaFile.'"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}

// Ekspor dilakukan sebelum HTML dirender agar file tidak rusak oleh output halaman.
if (isset($_GET['ekspor_rekap'])) {
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    $periode = ($_GET['periode'] ?? 'bulanan') === 'mingguan' ? 'mingguan' : 'bulanan';
    $tanggal_ref = $_GET['tanggal_ref'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_ref)) $tanggal_ref = date('Y-m-d');
    [$mulai, $akhir] = rekapRentangTanggal($periode, $tanggal_ref);
    [$kelas_rekap, $baris_rekap, $total_sesi_rekap] = rekapAmbilData($koneksi, $user_id, $kelas_id, $mulai, $akhir);
    if (!$kelas_rekap) { http_response_code(403); exit('Kelas tidak ditemukan atau bukan milik akun guru ini.'); }
    $rows = [[ 'REKAP ABSENSI SLearning' ], ['Kelas', $kelas_rekap['nama_kelas']], ['Periode', ucfirst($periode)], ['Rentang tanggal', $mulai.' s.d. '.$akhir], ['Total sesi', $total_sesi_rekap], [], ['Nama Murid','Total Sesi','Hadir','Izin','Sakit','Alpa','Belum Absen','Persentase Kehadiran']];
    foreach ($baris_rekap as $r) $rows[] = [$r['nama_murid'], $r['total_sesi'], $r['hadir'], $r['izin'], $r['sakit'], $r['alpa'], $r['belum_absen'], $r['persentase'].'%'];
    $format = $_GET['ekspor_rekap'];
    $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $kelas_rekap['nama_kelas']);
    $filebase = 'rekap_absensi_'.$slug.'_'.$mulai.'_'.$akhir;
    if ($format === 'xlsx') kirimXlsx($filebase.'.xlsx', $rows);
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filebase.'.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) fputcsv($out, $row, ';');
        fclose($out); exit;
    }
    http_response_code(400); exit('Format ekspor tidak valid.');
}

// ---------- PROSES AKSI POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    $target_page = $_POST['redirect_page'] ?? 'beranda';

    if ($aksi === 'buat_quiz') {
        $judul = trim($_POST['judul_quiz'] ?? '');
        $deskripsi = trim($_POST['deskripsi_quiz'] ?? '');
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $durasi = max(1, min(300, (int)($_POST['durasi_menit'] ?? 15)));
        $cekKelas = mysqli_prepare($koneksi, "SELECT id FROM kelas WHERE id=? AND guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($cekKelas, 'ii', $kelas_id, $user_id); mysqli_stmt_execute($cekKelas);
        $kelasValid = mysqli_fetch_assoc(mysqli_stmt_get_result($cekKelas)); mysqli_stmt_close($cekKelas);
        if ($judul !== '' && $kelasValid) {
            $st = mysqli_prepare($koneksi, "INSERT INTO quiz (guru_id, kelas_id, judul, deskripsi, durasi_menit) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, 'iissi', $user_id, $kelas_id, $judul, $deskripsi, $durasi);
            if (mysqli_stmt_execute($st)) { $quizBaru = mysqli_insert_id($koneksi); flash_set('Quiz berhasil dibuat. Sekarang tambahkan soal dan foto jika diperlukan.'); mysqli_stmt_close($st); header('Location: guru.php?page=quiz&kelola_quiz='.$quizBaru); exit; }
            mysqli_stmt_close($st); flash_set('Quiz gagal dibuat: periksa struktur tabel quiz.', 'error');
        } else flash_set('Judul quiz dan kelas yang kamu ajar wajib dipilih.', 'error');
        header('Location: guru.php?page=quiz'); exit;
    }

    if ($aksi === 'tambah_soal_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $opsi_a = trim($_POST['opsi_a'] ?? ''); $opsi_b = trim($_POST['opsi_b'] ?? '');
        $opsi_c = trim($_POST['opsi_c'] ?? ''); $opsi_d = trim($_POST['opsi_d'] ?? '');
        $kunci = strtoupper(trim($_POST['kunci'] ?? ''));
        $cekQuiz = mysqli_prepare($koneksi, "SELECT id FROM quiz WHERE id=? AND guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($cekQuiz, 'ii', $quiz_id, $user_id); mysqli_stmt_execute($cekQuiz);
        $quizValid = mysqli_fetch_assoc(mysqli_stmt_get_result($cekQuiz)); mysqli_stmt_close($cekQuiz);
        if (!$quizValid) { flash_set('Quiz tidak ditemukan atau bukan milik akunmu.', 'error'); header('Location: guru.php?page=quiz'); exit; }
        if ($pertanyaan === '' || $opsi_a === '' || $opsi_b === '' || $opsi_c === '' || $opsi_d === '' || !in_array($kunci, ['A','B','C','D'], true)) {
            flash_set('Isi pertanyaan, semua pilihan A–D, dan kunci jawaban.', 'error'); header('Location: guru.php?page=quiz&kelola_quiz='.$quiz_id); exit;
        }
        [$okUpload, $gambarAtauPesan] = uploadGambarSoal('gambar_soal');
        if (!$okUpload) { flash_set($gambarAtauPesan, 'error'); header('Location: guru.php?page=quiz&kelola_quiz='.$quiz_id); exit; }
        $gambar = $gambarAtauPesan;
        $st = mysqli_prepare($koneksi, "INSERT INTO quiz_soal (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci, gambar) VALUES (?,?,?,?,?,?,?,?)");
        if ($st) {
            mysqli_stmt_bind_param($st, 'isssssss', $quiz_id, $pertanyaan, $opsi_a, $opsi_b, $opsi_c, $opsi_d, $kunci, $gambar);
            if (mysqli_stmt_execute($st)) flash_set('Soal berhasil ditambahkan' . ($gambar !== '' ? ' beserta fotonya.' : '.'));
            else { if ($gambar !== '') @unlink(__DIR__ . '/' . $gambar); flash_set('Gagal menyimpan soal. Pastikan kolom gambar tersedia di tabel quiz_soal.', 'error'); }
            mysqli_stmt_close($st);
        } else { if ($gambar !== '') @unlink(__DIR__ . '/' . $gambar); flash_set('Gagal menyiapkan penyimpanan soal.', 'error'); }
        header('Location: guru.php?page=quiz&kelola_quiz='.$quiz_id); exit;
    }

    if ($aksi === 'hapus_soal_quiz') {
        $soal_id = (int)($_POST['soal_id'] ?? 0); $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $st = mysqli_prepare($koneksi, "SELECT qs.gambar FROM quiz_soal qs JOIN quiz q ON q.id=qs.quiz_id WHERE qs.id=? AND qs.quiz_id=? AND q.guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'iii', $soal_id, $quiz_id, $user_id); mysqli_stmt_execute($st); $rowSoal = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
        if ($rowSoal) { mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE id=$soal_id AND quiz_id=$quiz_id"); if (!empty($rowSoal['gambar'])) { $path = __DIR__ . '/' . $rowSoal['gambar']; if (is_file($path)) @unlink($path); } flash_set('Soal berhasil dihapus.'); }
        else flash_set('Soal tidak ditemukan.', 'error');
        header('Location: guru.php?page=quiz&kelola_quiz='.$quiz_id); exit;
    }

    if ($aksi === 'hapus_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $st = mysqli_prepare($koneksi, "SELECT id FROM quiz WHERE id=? AND guru_id=? LIMIT 1"); mysqli_stmt_bind_param($st, 'ii', $quiz_id, $user_id); mysqli_stmt_execute($st); $quizValid = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
        if ($quizValid) {
            $qg = mysqli_query($koneksi, "SELECT gambar FROM quiz_soal WHERE quiz_id=$quiz_id"); while ($qg && $g=mysqli_fetch_assoc($qg)) { if (!empty($g['gambar']) && is_file(__DIR__.'/'.$g['gambar'])) @unlink(__DIR__.'/'.$g['gambar']); }
            mysqli_query($koneksi, "DELETE FROM quiz_hasil WHERE quiz_id=$quiz_id"); mysqli_query($koneksi, "DELETE FROM quiz_soal WHERE quiz_id=$quiz_id"); mysqli_query($koneksi, "DELETE FROM quiz WHERE id=$quiz_id AND guru_id=$user_id"); flash_set('Quiz dan soal-soalnya berhasil dihapus.');
        } else flash_set('Quiz tidak ditemukan.', 'error');
        header('Location: guru.php?page=quiz'); exit;
    }

    if ($aksi === 'update_profil') {
        $nama_baru = trim($_POST['nama'] ?? '');
        $email_baru = trim($_POST['email'] ?? '');
        $pass_lama = $_POST['password_lama'] ?? '';
        $pass_baru = $_POST['password_baru'] ?? '';

        if ($nama_baru !== '' && $email_baru !== '') {
            $q_cek = mysqli_query($koneksi, "SELECT password FROM login WHERE id = $user_id LIMIT 1");
            $res_cek = $q_cek ? mysqli_fetch_assoc($q_cek) : null;

            $update_pass_sql = "";
            $params = [$nama_baru, $email_baru];
            $types = "ss";

            if (!empty($pass_baru)) {
                if ($res_cek && password_verify($pass_lama, $res_cek['password'])) {
                    $hashed_pass = password_hash($pass_baru, PASSWORD_DEFAULT);
                    $update_pass_sql = ", password = ?";
                    $params[] = $hashed_pass;
                    $types .= "s";
                } else {
                    flash_set('Password lama salah! Gagal memperbarui sandi.', 'error');
                    header("Location: guru.php?page=profil"); exit;
                }
            }

            $params[] = $user_id;
            $types .= "i";

            $upd = mysqli_prepare($koneksi, "UPDATE login SET nama = ?, email = ? ".$update_pass_sql." WHERE id = ?");
            mysqli_stmt_bind_param($upd, $types, ...$params);
            if (mysqli_stmt_execute($upd)) {
                $_SESSION['nama'] = $nama_baru;
                flash_set('Profil berhasil diperbarui.');
            } else {
                flash_set('Gagal memperbarui profil.', 'error');
            }
            mysqli_stmt_close($upd);
        }
        header("Location: guru.php?page=profil"); exit;
    }

    if ($aksi === 'tambah_kelas') {
        $nama = trim($_POST['nama_kelas_baru'] ?? '');
        if ($nama !== '') {
            $kode = 'KLS-'.strtoupper(substr(md5(mt_rand().time()),0,5));
            $s = mysqli_prepare($koneksi, "INSERT INTO kelas (nama_kelas, kode_gabung, guru_id) VALUES (?,?,?)");
            mysqli_stmt_bind_param($s, "ssi", $nama, $kode, $user_id);
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
            flash_set('Kelas "'.$nama.'" berhasil dibuat.');
        }
        header("Location: guru.php?page=".$target_page); exit;
    }
    if ($aksi === 'hapus_kelas') {
        $id = (int)($_POST['kelas_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM kelas WHERE id=$id AND guru_id=$user_id");
        flash_set('Kelas berhasil dihapus.');
        header("Location: guru.php?page=".$target_page); exit;
    }
    if ($aksi === 'buat_absen') {
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $judul = trim($_POST['judul_absensi'] ?? '') ?: ('Absensi — '.$tanggal);
        if ($kelas_id > 0) {
            $s = mysqli_prepare($koneksi, "INSERT INTO absensi (guru_id, kelas_id, judul_absensi, tanggal) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($s, "iiss", $user_id, $kelas_id, $judul, $tanggal);
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
            flash_set('Sesi absensi berhasil dibuka.');
        } else flash_set('Pilih kelas terlebih dahulu!', 'error');
        header("Location: guru.php?page=absensi"); exit;
    }
    if ($aksi === 'hapus_absen') {
        $id = (int)($_POST['absen_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM absensi WHERE id=$id AND guru_id=$user_id");
        flash_set('Sesi absensi berhasil dihapus.');
        header("Location: guru.php?page=absensi"); exit;
    }
    if ($aksi === 'tambah_tugas') {
        $judul = trim($_POST['judul_tugas'] ?? ''); 
        $kelas_id = (int)($_POST['kelas_id'] ?? 0);
        $deadline = str_replace('T',' ',trim($_POST['deadline'] ?? '')); 
        $desk = trim($_POST['deskripsi'] ?? '');
        
        $file_tugas = '';
        if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['file_tugas']['name'], PATHINFO_EXTENSION);
            $file_tugas = 'tugas_'.time().'_'.mt_rand(1000,9999).'.'.$ext;
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            move_uploaded_file($_FILES['file_tugas']['tmp_name'], $upload_dir . $file_tugas);
        }

        if ($judul !== '' && $kelas_id > 0) {
            if ($deadline === '') $deadline = null;
            $st = mysqli_prepare($koneksi, "INSERT INTO tugas (guru_id, kelas_id, judul_tugas, deadline, deskripsi) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, "iisss", $user_id, $kelas_id, $judul, $deadline, $desk);
            mysqli_stmt_execute($st); mysqli_stmt_close($st);
            flash_set('Tugas berhasil dipublikasikan.');
        } else flash_set('Judul dan pilih kelas wajib diisi!', 'error');
        header("Location: guru.php?page=tugas"); exit;
    }
    if ($aksi === 'hapus_tugas') {
        $id = (int)($_POST['tugas_id'] ?? 0);
        mysqli_query($koneksi, "DELETE FROM pengumpulan_tugas WHERE tugas_id=$id");
        mysqli_query($koneksi, "DELETE FROM tugas WHERE id=$id AND guru_id=$user_id");
        flash_set('Tugas berhasil dihapus.');
        header("Location: guru.php?page=tugas"); exit;
    }
    
    // AKSI BARU: Perpanjang / Ubah Deadline Tugas
    if ($aksi === 'update_deadline') {
        $tugas_id = (int)($_POST['tugas_id'] ?? 0);
        $deadline_baru = str_replace('T',' ',trim($_POST['deadline'] ?? ''));
        if (empty($deadline_baru)) $deadline_baru = null;
        
        $upd_dl = mysqli_prepare($koneksi, "UPDATE tugas SET deadline = ? WHERE id = ? AND guru_id = ?");
        mysqli_stmt_bind_param($upd_dl, "sii", $deadline_baru, $tugas_id, $user_id);
        if (mysqli_stmt_execute($upd_dl)) {
            flash_set('Batas waktu (deadline) tugas berhasil diperbarui/dibuka kembali.');
        } else {
            flash_set('Gagal memperbarui batas waktu.', 'error');
        }
        mysqli_stmt_close($upd_dl);
        header("Location: guru.php?page=detail_kelas&id=".$detail_kelas_id); exit;
    }

    if ($aksi === 'beri_nilai') {
        $pengumpulan_id = (int)($_POST['pengumpulan_id'] ?? 0);
        $nilai = (int)($_POST['nilai'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        
        $up_n = mysqli_prepare($koneksi, "UPDATE pengumpulan_tugas SET nilai = ?, feedback = ? WHERE id = ?");
        mysqli_stmt_bind_param($up_n, "isi", $nilai, $feedback, $pengumpulan_id);
        if (mysqli_stmt_execute($up_n)) {
            flash_set('Nilai berhasil disimpan.');
        } else {
            flash_set('Gagal menyimpan nilai.', 'error');
        }
        mysqli_stmt_close($up_n);
        header("Location: guru.php?page=detail_kelas&id=".$detail_kelas_id); exit;
    }
}

// Ambil Data User
$q_user = mysqli_query($koneksi, "SELECT id, nama, username, email, jenis, level FROM login WHERE id = $user_id LIMIT 1");
$user = $q_user ? mysqli_fetch_assoc($q_user) : ['nama' => 'Guru', 'username' => 'guru', 'email' => ''];

$nama_guru = $user['nama'] ?? 'Guru';
$username_guru = $user['username'] ?? 'guru';
$email_guru = $user['email'] ?? '';

// Data Pendukung Dasbor
$list_kelas = [];
$r_kls = mysqli_query($koneksi, "SELECT id AS id_kelas, nama_kelas, kode_gabung, (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=k.id) AS jml_murid FROM kelas k WHERE k.guru_id=$user_id ORDER BY k.id DESC");
if($r_kls) { while ($r = mysqli_fetch_assoc($r_kls)) $list_kelas[] = $r; }

$jml_kelas = count($list_kelas);
$q_tugas = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM tugas WHERE guru_id=$user_id");
$jml_tugas = $q_tugas ? mysqli_fetch_assoc($q_tugas)['c'] : 0;

$q_quiz = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM quiz WHERE guru_id=$user_id");
$jml_quiz = $q_quiz ? mysqli_fetch_assoc($q_quiz)['c'] : 0;

$q_absen = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM absensi WHERE guru_id=$user_id");
$jml_absen = $q_absen ? mysqli_fetch_assoc($q_absen)['c'] : 0;

// Data untuk Beranda (Notifikasi Tugas Belum Dinilai & Absen)
$notif_tugas_belum_dinilai = [];
$notif_error = null;
$tgl_hari_ini = date('Y-m-d');
$absen_hari_ini = [];

if ($page === 'beranda') {
    $query_notif = "SELECT pt.id, pt.dikumpulkan_at, l.nama AS nama_murid, t.judul_tugas, k.nama_kelas, t.kelas_id 
        FROM pengumpulan_tugas pt 
        LEFT JOIN login l ON pt.user_id = l.id 
        JOIN tugas t ON pt.tugas_id = t.id 
        JOIN kelas k ON t.kelas_id = k.id 
        WHERE t.guru_id = $user_id AND (pt.nilai IS NULL OR pt.nilai = 0 OR pt.nilai = '') 
        ORDER BY pt.dikumpulkan_at DESC LIMIT 5";
        
    $q_notif = mysqli_query($koneksi, $query_notif);
    
    if($q_notif) { 
        while ($rn = mysqli_fetch_assoc($q_notif)) { $notif_tugas_belum_dinilai[] = $rn; } 
    } else {
        $notif_error = mysqli_error($koneksi);
    }

    $q_absen_hari = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, k.nama_kelas, 
        (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid,
        (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS total_hadir
        FROM absensi a 
        JOIN kelas k ON a.kelas_id = k.id 
        WHERE a.guru_id = $user_id AND a.tanggal = '$tgl_hari_ini'");
    if($q_absen_hari) { while ($ra = mysqli_fetch_assoc($q_absen_hari)) { $absen_hari_ini[] = $ra; } }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Guru — Slearning</title>
<link rel="icon" type="image/png" href="assets/img/logo-smkn7.png">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root { 
    --nav-yellow: #FFF8D6;
    --nav-yellow-dark: #F2E7B5;
    --sage-body: #F4F7F5;
    --sage-50: #E8F0E8;
    --sage-100: #D1E0D1;
    --sage-300: #9FBA9F;
    --sage-500: #6D8C6D;
    --sage-600: #577357;
    --sage-800: #2F402F;
    --sage-900: #1D261D;
    --bg-card: #FFFFFF;
    --shadow-sm: 0 4px 12px rgba(109, 140, 109, 0.05);
    --shadow-md: 0 8px 24px rgba(109, 140, 109, 0.08);
    --shadow-lg: 0 16px 40px rgba(109, 140, 109, 0.12);
    --radius-sm: 12px;
    --radius-md: 16px;
    --radius-lg: 24px;
}
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: var(--sage-body); color: var(--sage-900); min-height: 100vh; padding-bottom: 60px; position: relative; overflow-x: hidden; }

body::before { content: ''; position: fixed; top: -10%; left: -5%; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(255, 248, 214, 0.8) 0%, rgba(255, 248, 214, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
body::after { content: ''; position: fixed; bottom: -10%; right: -5%; width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(109, 140, 109, 0.15) 0%, rgba(109, 140, 109, 0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }

.header { background: linear-gradient(180deg, var(--nav-yellow) 0%, #FFFDF6 100%); position: relative; z-index: 10; padding-top: 16px; border-bottom: 1px solid var(--nav-yellow-dark); box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
.header-top { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 32px 16px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 22px; font-weight: 800; color: var(--sage-800); letter-spacing: -0.5px; }
.logo svg { width: 30px; height: 30px; color: var(--sage-600); }

.header-icons { display: flex; align-items: center; gap: 24px; }
.header-icons a { color: var(--sage-600); transition: all 0.3s ease; position: relative; display: flex; align-items: center; text-decoration: none; background: #FFFFFF; padding: 10px; border-radius: 50%; border: 1px solid var(--sage-50); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
.header-icons a:hover { color: var(--sage-900); background: var(--sage-50); transform: translateY(-2px); border-color: var(--sage-100); }
.header-icons svg { width: 20px; height: 20px; }
.notif-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #E07A5F; border-radius: 50%; border: 2px solid #FFFFFF; }

.nav-tabs { display: flex; justify-content: center; gap: 8px; max-width: 1200px; margin: 0 auto; padding: 0 32px; }
.nav-tab { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 28px; text-decoration: none; color: var(--sage-600); font-size: 13px; font-weight: 600; transition: all 0.3s ease; position: relative; border-radius: var(--radius-sm) var(--radius-sm) 0 0; }
.nav-tab svg { width: 20px; height: 20px; stroke-width: 2.2; }
.nav-tab:hover { color: var(--sage-800); background: rgba(255, 255, 255, 0.6); }
.nav-tab.active { color: var(--sage-900); background: #FFFFFF; }
.nav-tab.active::after { content: ''; position: absolute; bottom: 0; left: 15%; width: 70%; height: 3px; background: var(--sage-500); border-radius: 4px 4px 0 0; }

.container { max-width: 1100px; margin: 0 auto; padding: 40px 32px; position: relative; z-index: 1; }

.card-manage { background: var(--bg-card); border-radius: var(--radius-lg); padding: 36px; margin-bottom: 48px; border: 1px solid var(--sage-50); box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
.card-manage h2 { font-size: 20px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px; letter-spacing: -0.3px; }
.card-manage p { font-size: 14px; color: var(--sage-600); font-weight: 500; }

.btn-primary { background: var(--sage-500); color: #FFFFFF; border: none; padding: 12px 28px; border-radius: 50px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(109, 140, 109, 0.3); text-decoration: none; }
.btn-primary:hover { background: var(--sage-600); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(109, 140, 109, 0.4); }
.btn-primary svg { width: 18px; height: 18px; }

.input-elegant { width: 100%; background: var(--sage-body); border: 1px solid var(--sage-100); border-radius: var(--radius-sm); padding: 16px 20px; font-size: 14px; font-weight: 500; color: var(--sage-900); outline: none; transition: all 0.3s ease; }
.input-elegant:focus { border-color: var(--sage-500); background: #FFFFFF; box-shadow: 0 0 0 4px rgba(109, 140, 109, 0.1); }

.section-title { font-size: 18px; font-weight: 800; color: var(--sage-800); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.section-title::before { content: ''; display: block; width: 8px; height: 24px; background: var(--sage-500); border-radius: 4px; }

.class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
.class-card { background: var(--bg-card); border-radius: var(--radius-md); padding: 28px; box-shadow: var(--shadow-sm); border: 1px solid var(--sage-50); transition: all 0.4s ease; position: relative; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
.class-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--sage-100); }

.class-card-header { display: flex; align-items: center; gap: 18px; margin-bottom: 16px; }
.class-icon { width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--sage-50); color: var(--sage-600); border: 1px solid var(--sage-100); }
.class-icon svg { width: 24px; height: 24px; stroke-width: 2; }
.class-info h4 { font-size: 17px; font-weight: 800; color: var(--sage-800); margin-bottom: 6px; }
.class-info .murid-count { font-size: 13px; color: var(--sage-600); font-weight: 500; display: flex; align-items: center; gap: 6px; }

.kode-badge { display: inline-flex; align-items: center; gap: 10px; background: var(--nav-yellow); border: 1px dashed #E2D38A; border-radius: var(--radius-sm); padding: 8px 14px; font-family: monospace; font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 20px; }

.btn-card-action { display: flex; gap: 10px; margin-top: auto; }
.btn-hapus { background: transparent; color: #E07A5F; border: 1px solid #F4E3E0; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
.btn-hapus:hover { background: #FFF3F0; }
.btn-masuk-kelas { background: var(--sage-500); color: #fff; border: none; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; flex: 1; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; }
.btn-masuk-kelas:hover { background: var(--sage-600); }

.empty-state { text-align: center; padding: 40px 20px; color: var(--sage-600); background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--sage-300); }
.empty-state svg { width: 56px; height: 56px; margin-bottom: 16px; opacity: 0.5; }
.alert { padding: 16px 24px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
.alert.success { background: var(--sage-50); color: var(--sage-800); border-left: 4px solid var(--sage-500); }
.alert.error { background: #FFF3F0; color: #E07A5F; border-left: 4px solid #E07A5F; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.form-label { display: block; font-size: 13px; font-weight: 700; color: var(--sage-800); margin-bottom: 8px; }

/* Dashboard Beranda Baru */
.beranda-hero { background: linear-gradient(135deg, var(--sage-50) 0%, #FFFFFF 100%); border-radius: var(--radius-lg); padding: 40px; border: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; box-shadow: var(--shadow-sm); }
.beranda-hero h1 { font-size: 26px; font-weight: 800; color: var(--sage-800); margin-bottom: 8px; }
.beranda-hero p { font-size: 14.5px; color: var(--sage-600); font-weight: 500; max-width: 550px; line-height: 1.6; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 36px; }
.stat-card { background: #FFFFFF; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 18px; }
.stat-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--nav-yellow); display: flex; align-items: center; justify-content: center; color: var(--sage-800); font-size: 24px; font-weight: 800; }
.stat-info h3 { font-size: 24px; font-weight: 800; color: var(--sage-800); }
.stat-info p { font-size: 13px; color: var(--sage-600); font-weight: 600; }

.dashboard-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 40px; }
.panel { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-50); box-shadow: var(--shadow-sm); padding: 24px; }
.panel-header { font-size: 16px; font-weight: 800; color: var(--sage-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.panel-list { display: flex; flex-direction: column; gap: 12px; }
.panel-item { padding: 16px; border: 1px solid var(--sage-50); border-radius: var(--radius-sm); background: var(--sage-body); display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:10px; }
.panel-item h4 { font-size: 14px; font-weight: 700; color: var(--sage-800); margin-bottom: 4px; }
.panel-item p { font-size: 12px; color: var(--sage-600); }

.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.quick-btn { background: #FFFFFF; border: 1px solid var(--sage-100); border-radius: var(--radius-md); padding: 20px; text-decoration: none; color: var(--sage-800); font-weight: 700; display: flex; align-items: center; gap: 14px; transition: 0.3s; box-shadow: var(--shadow-sm); }
.quick-btn:hover { background: var(--sage-50); border-color: var(--sage-300); transform: translateY(-2px); }
.quick-btn svg { width: 22px; height: 22px; color: var(--sage-500); }

/* Style Khusus Bagian Detail Tugas & Status Murid (Hijau / Merah) */
.task-section-box { background: #fff; border-radius: var(--radius-md); border: 1px solid var(--sage-100); margin-bottom: 24px; overflow: hidden; box-shadow: var(--shadow-sm); }
.task-section-header { background: var(--sage-50); padding: 20px 24px; border-bottom: 1px solid var(--sage-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.student-row-list { list-style: none; padding: 0; margin: 0; }
.student-row-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--sage-50); flex-wrap: wrap; gap: 16px; }
.student-row-item:last-child { border-bottom: none; }
.badge-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 50px; }
.badge-hijau { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.badge-merah { background: #FFEBEE; color: #C62828; border: 1px solid #FFCDD2; }
.form-penilaian-inline { display: flex; gap: 8px; align-items: center; background: #FAFCFA; padding: 12px; border-radius: 12px; border: 1px solid var(--sage-100); }

@media (max-width: 768px) {
    .header-top { flex-wrap: wrap; gap: 16px; }
    .nav-tabs { overflow-x: auto; justify-content: flex-start; padding: 0 16px; }
    .form-grid { grid-template-columns: 1fr; }
    .beranda-hero { flex-direction: column; align-items: flex-start; gap: 20px; }
    .dashboard-panels { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-top">
    <a href="guru.php?page=beranda" class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      Slearning
    </a>
    
    <div class="header-icons">
      <a href="#" title="Notifikasi">
        <?php if(!empty($notif_tugas_belum_dinilai)): ?><div class="notif-dot"></div><?php endif; ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </a>
      <a href="guru.php?page=profil" title="Profil">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </a>
      <a href="logout.php" onclick="return confirm('Keluar dari Slearning?')" title="Keluar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </div>

  <nav class="nav-tabs">
    <a href="guru.php?page=beranda" class="nav-tab <?= $page==='beranda'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Beranda</span>
    </a>
    <a href="guru.php?page=kelas" class="nav-tab <?= $page==='kelas' || $page==='detail_kelas'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
      <span>Kelas</span>
    </a>
    <a href="guru.php?page=absensi" class="nav-tab <?= $page==='absensi'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
      <span>Absensi</span>
    </a>
    <a href="guru.php?page=rekap_absensi" class="nav-tab <?= $page==='rekap_absensi'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/></svg>
      <span>Rekap</span>
    </a>
    <a href="guru.php?page=tugas" class="nav-tab <?= $page==='tugas'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Tugas</span>
    </a>
    <a href="guru.php?page=quiz" class="nav-tab <?= $page==='quiz'?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="14" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="18"/><line x1="9" y1="10" x2="9.01" y2="10"/></svg>
      <span>Quiz</span>
    </a>
  </nav>
</header>

<main class="container">
  <?php if ($flash_msg): ?>
    <div class="alert <?= $flash_type==='error'?'error':'success' ?>">
      <?= $flash_type==='error' ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' ?>
      <?= e($flash_msg) ?>
    </div>
  <?php endif; ?>

  <!-- ================= HALAMAN BERANDA ================= -->
  <?php if ($page === 'beranda'): ?>
    <div class="beranda-hero">
      <div>
        <h1>Halo, <?= e($nama_guru) ?> 👋</h1>
        <p>Selamat datang di panel pengajar Slearning. Pantau pemberitahuan tugas yang siap dinilai dan kehadiran murid hari ini.</p>
      </div>
      <a href="guru.php?page=kelas" class="btn-primary">Kelola Kelas</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">🏫</div>
        <div class="stat-info">
          <h3><?= $jml_kelas ?></h3>
          <p>Total Kelas Aktif</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📄</div>
        <div class="stat-info">
          <h3><?= $jml_tugas ?></h3>
          <p>Tugas Dipublikasi</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-info">
          <h3><?= $jml_absen ?></h3>
          <p>Jumlah Absensi Yang Telah Dibuat</p>
        </div>
      </div>
    </div>

    <div class="dashboard-panels">
        <!-- Panel Tugas Belum Dinilai -->
        <div class="panel">
            <div class="panel-header">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#E07A5F" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Tugas Perlu Dinilai
            </div>
            
            <?php if ($notif_error): ?>
                <div class="empty-state" style="padding: 20px; border-color: red;">
                    <p style="color:red; font-size: 13px;">Gagal mengambil data dari database.</p>
                    <p style="color:red; font-size: 11px; margin-top:5px; font-family:monospace;"><?= $notif_error ?></p>
                </div>
            <?php elseif (empty($notif_tugas_belum_dinilai)): ?>
                <div class="empty-state" style="padding: 20px;"><p>Belum ada tugas baru yang perlu dinilai.</p></div>
            <?php else: ?>
                <div class="panel-list">
                    <?php foreach($notif_tugas_belum_dinilai as $nt): ?>
                        <div class="panel-item">
                            <div>
                                <h4><?= e($nt['nama_murid'] ?? 'Siswa Tidak Diketahui') ?></h4>
                                <p><?= e($nt['judul_tugas']) ?> - <?= e($nt['nama_kelas']) ?></p>
                            </div>
                            <a href="guru.php?page=detail_kelas&id=<?= $nt['kelas_id'] ?>" class="btn-primary" style="padding: 8px 12px; font-size: 12px;">Beri Nilai</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel Pantauan Absensi Hari Ini -->
        <div class="panel">
            <div class="panel-header">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Absensi Murid Hari Ini
            </div>
            <?php if (empty($absen_hari_ini)): ?>
                <div class="empty-state" style="padding: 20px;"><p>Tidak ada sesi absensi untuk hari ini.</p></div>
            <?php else: ?>
                <div class="panel-list">
                    <?php foreach($absen_hari_ini as $ah): ?>
                        <div class="panel-item">
                            <div>
                                <h4><?= e($ah['judul_absensi']) ?></h4>
                                <p>Kelas: <?= e($ah['nama_kelas']) ?></p>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-weight: 800; font-size: 16px; color: var(--sage-800);"><?= (int)$ah['total_hadir'] ?> / <?= (int)$ah['total_murid'] ?></span>
                                <p>Hadir</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="section-title">Akses Pintar</h3>
    <div class="quick-actions">
      <a href="guru.php?page=kelas" class="quick-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
        Daftar Kelas & Nilai
      </a>
      <a href="guru.php?page=absensi" class="quick-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Buka Sesi Absensi
      </a>
      <a href="guru.php?page=tugas" class="quick-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Buat & Unggah Tugas
      </a>
      <a href="guru.php?page=profil" class="quick-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Pengaturan Profil
      </a>
    </div>

  <!-- ================= HALAMAN KELAS ================= -->
  <?php elseif ($page === 'kelas'): ?>
    <div class="card-manage">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2>Manajemen Kelas & Kode Gabung</h2>
          <p>Kelola kelas pengajaran Anda dan bagikan kode unik kepada murid.</p>
        </div>
        <button type="submit" form="form-tambah-kelas" class="btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Buat Kelas Baru
        </button>
      </div>
      
      <form id="form-tambah-kelas" action="guru.php?page=kelas" method="POST">
        <input type="hidden" name="aksi" value="tambah_kelas">
        <input type="hidden" name="redirect_page" value="kelas">
        <input type="text" name="nama_kelas_baru" class="input-elegant" placeholder="Ketik nama kelas baru, contoh: XII Rekayasa Perangkat Lunak 1..." required>
      </form>
    </div>

    <h3 class="section-title">Daftar Kelas Aktif (<?= count($list_kelas) ?>)</h3>
    
    <?php if (empty($list_kelas)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>Belum ada kelas yang terdaftar.</p>
      </div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_kelas as $kls): ?>
        <div class="class-card">
          <div class="class-card-header">
            <div class="class-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="class-info">
              <h4><?= e($kls['nama_kelas']) ?></h4>
              <span class="murid-count">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?= (int)$kls['jml_murid'] ?> Murid Tergabung
              </span>
            </div>
          </div>

          <div class="kode-badge" title="Kode Gabung Kelas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <?= e($kls['kode_gabung']) ?>
          </div>

          <div class="btn-card-action">
            <a href="guru.php?page=detail_kelas&id=<?= (int)$kls['id_kelas'] ?>" class="btn-masuk-kelas">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              Masuk & Nilai
            </a>
            
            <form method="POST" onsubmit="return confirm('Hapus kelas ini?')" style="display:inline;">
              <input type="hidden" name="aksi" value="hapus_kelas">
              <input type="hidden" name="redirect_page" value="kelas">
              <input type="hidden" name="kelas_id" value="<?= (int)$kls['id_kelas'] ?>">
              <button type="submit" class="btn-hapus">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="16" height="16"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                Hapus
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>


  <!-- ================= HALAMAN DETAIL KELAS & PENILAIAN TUGAS (DIROMBAK SESUAI PERMINTAAN) ================= -->
  <?php elseif ($page === 'detail_kelas'): ?>
    <?php
    $q_dk = mysqli_query($koneksi, "SELECT * FROM kelas WHERE id = $detail_kelas_id AND guru_id = $user_id LIMIT 1");
    $kelas_ini = $q_dk ? mysqli_fetch_assoc($q_dk) : null;
    ?>
    
    <?php if (!$kelas_ini): ?>
      <div class="empty-state"><p>Kelas tidak ditemukan atau Anda tidak memiliki akses.</p></div>
    <?php else: ?>
      <div class="card-manage">
        <a href="guru.php?page=kelas" style="display:inline-flex; align-items:center; gap:6px; color:var(--sage-600); text-decoration:none; font-weight:700; font-size:13px; margin-bottom:12px;">← Kembali ke Daftar Kelas</a>
        <h2>Pantauan Tugas & Status Pengerjaan Murid: <?= e($kelas_ini['nama_kelas']) ?></h2>
        <p>Kode Gabung: <strong><?= e($kelas_ini['kode_gabung']) ?></strong> &bull; Murid yang melewati deadline akan kehilangan akses kumpul sampai Anda memperpanjang waktunya.</p>
      </div>

      <?php
      // Ambil daftar tugas di kelas ini
      $q_tugas_kelas = mysqli_query($koneksi, "SELECT * FROM tugas WHERE kelas_id = $detail_kelas_id ORDER BY id DESC");
      
      if(mysqli_num_rows($q_tugas_kelas) === 0): ?>
          <div class="empty-state"><p>Belum ada tugas yang dibuat untuk kelas ini.</p></div>
      <?php else: 
          while($tugas = mysqli_fetch_assoc($q_tugas_kelas)): 
              $tugas_id = $tugas['id'];
              
              // Cek status deadline apakah sudah expired (lewat waktu)
              $is_expired = false;
              $teks_deadline = 'Tanpa Batas Waktu';
              if(!empty($tugas['deadline'])) {
                  $teks_deadline = date('d M Y, H:i', strtotime($tugas['deadline']));
                  if(strtotime($tugas['deadline']) < time()) {
                      $is_expired = true;
                  }
              }
      ?>
          <div class="task-section-box">
              <div class="task-section-header">
                  <div>
                      <h3 style="font-size: 17px; font-weight: 800; color: var(--sage-900); margin-bottom: 4px;">📄 <?= e($tugas['judul_tugas']) ?></h3>
                      <p style="font-size: 13px; color: var(--sage-600);">
                          Deadline: <strong style="color: <?= $is_expired ? '#C62828' : 'var(--sage-800)' ?>;"><?= $teks_deadline ?></strong> 
                          <?= $is_expired ? '<span style="color: #C62828; font-weight: bold; margin-left: 6px;">(Terkunci untuk Murid)</span>' : '' ?>
                      </p>
                  </div>
                  
                  <!-- Form Perpanjang / Ubah Deadline -->
                  <form action="guru.php?page=detail_kelas&id=<?= $detail_kelas_id ?>" method="POST" style="display: flex; gap: 8px; align-items: center;">
                      <input type="hidden" name="aksi" value="update_deadline">
                      <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
                      <input type="datetime-local" name="deadline" class="input-elegant" style="padding: 8px 12px; font-size: 12px; width: auto;" title="Pilih waktu baru untuk membuka kembali akses">
                      <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 12px; background: var(--sage-800); box-shadow: none;">Ubah / Buka Akses</button>
                  </form>
              </div>

              <!-- Daftar Semua Murid di Kelas beserta Status Hijau / Merah -->
              <ul class="student-row-list">
                  <?php
                  // Query menggabungkan seluruh murid di kelas dengan hasil pengumpulan tugas spesifik ini
                  $q_status_murid = mysqli_query($koneksi, "
                      SELECT l.id AS user_id, l.nama AS nama_murid, 
                             pt.id AS pengumpulan_id, pt.file_path, pt.nama_file, pt.dikumpulkan_at, pt.nilai, pt.feedback 
                      FROM anggota_kelas ak 
                      JOIN login l ON ak.user_id = l.id 
                      LEFT JOIN pengumpulan_tugas pt ON l.id = pt.user_id AND pt.tugas_id = $tugas_id 
                      WHERE ak.kelas_id = $detail_kelas_id 
                      ORDER BY l.nama ASC
                  ");

                  if(mysqli_num_rows($q_status_murid) === 0):
                  ?>
                      <li class="student-row-item" style="justify-content: center; color: var(--sage-600);">Belum ada murid yang bergabung di kelas ini.</li>
                  <?php 
                  else:
                      while($sm = mysqli_fetch_assoc($q_status_murid)):
                          $sudah_mengerjakan = !empty($sm['pengumpulan_id']);
                  ?>
                      <li class="student-row-item">
                          <div style="flex: 1; min-width: 240px;">
                              <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                  <strong style="font-size: 14.5px; color: var(--sage-900);"><?= e($sm['nama_murid']) ?></strong>
                                  
                                  <!-- Logo Status Hijau / Merah -->
                                  <?php if($sudah_mengerjakan): ?>
                                      <span class="badge-status badge-hijau" title="Murid sudah mengumpulkan tugas">
                                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                          Sudah Mengerjakan
                                      </span>
                                  <?php else: ?>
                                      <span class="badge-status badge-merah" title="Murid belum mengumpulkan tugas">
                                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                          Belum Mengerjakan
                                      </span>
                                  <?php endif; ?>
                              </div>

                              <?php if($sudah_mengerjakan): 
                                  $file_path = $sm['file_path'] ?? '';
                                  $nama_file = $sm['nama_file'] ?? '';
                                  $link_file = '';
                                  if (!empty($file_path)) {
                                      $link_file = (strpos($file_path, 'uploads/') === false) ? 'uploads/' . ltrim($file_path, '/') : $file_path;
                                  } elseif (!empty($nama_file)) {
                                      $link_file = 'uploads/' . $nama_file;
                                  }
                              ?>
                                  <div style="display: flex; align-items: center; gap: 12px; margin-top: 4px;">
                                      <?php if(!empty($link_file)): ?>
                                          <a href="<?= e($link_file) ?>" target="_blank" style="font-size: 12px; color: var(--sage-600); text-decoration: underline; font-weight: 600;">Lihat File Lampiran</a>
                                      <?php endif; ?>
                                      <span style="font-size: 11px; color: #9ca3af;">Dikumpul: <?= e($sm['dikumpulkan_at']) ?></span>
                                  </div>
                              <?php endif; ?>
                          </div>

                          <?php if($sudah_mengerjakan): ?>
                              <!-- Form Penilaian Langsung di Samping Nama -->
                              <form action="guru.php?page=detail_kelas&id=<?= $detail_kelas_id ?>" method="POST" class="form-penilaian-inline">
                                  <input type="hidden" name="aksi" value="beri_nilai">
                                  <input type="hidden" name="pengumpulan_id" value="<?= (int)$sm['pengumpulan_id'] ?>">
                                  
                                  <div>
                                      <label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Nilai</label>
                                      <input type="number" name="nilai" value="<?= $sm['nilai'] ?>" placeholder="0-100" class="input-elegant" style="width: 75px; padding: 6px 10px; background: #fff;" required>
                                  </div>
                                  
                                  <div>
                                      <label style="font-size: 10px; font-weight: 700; color: var(--sage-600); display: block; margin-bottom: 2px;">Feedback</label>
                                      <input type="text" name="feedback" value="<?= e($sm['feedback'] ?? '') ?>" placeholder="Catatan..." class="input-elegant" style="width: 130px; padding: 6px 10px; background: #fff;">
                                  </div>
                                  
                                  <button type="submit" class="btn-primary" style="padding: 8px 14px; font-size: 12px; margin-top: 15px; box-shadow: none;">Simpan</button>
                              </form>
                          <?php endif; ?>
                      </li>
                  <?php 
                      endwhile; 
                  endif; 
                  ?>
              </ul>
          </div>
      <?php 
          endwhile; 
      endif; 
      ?>
    <?php endif; ?>


  <!-- ================= HALAMAN ABSENSI ================= -->
  <?php elseif ($page === 'absensi'): ?>
    <div class="card-manage">
      <h2>Buka Sesi Absensi</h2>
      <p>Catat kehadiran siswa berdasarkan kelas dan tanggal pertemuan hari ini atau mendatang.</p>
      
      <form action="guru.php?page=absensi" method="POST" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="buat_absen">
        <div class="form-grid">
          <div>
            <label class="form-label">Judul Sesi Absensi</label>
            <input type="text" name="judul_absensi" class="input-elegant" placeholder="Contoh: Pertemuan 1 - Jaringan Dasar" required>
          </div>
          <div>
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required style="cursor:pointer;">
              <option value="" disabled selected>-- Tentukan Kelas --</option>
              <?php foreach ($list_kelas as $kls): ?>
                <option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="margin-bottom: 24px;">
          <label class="form-label">Tanggal Pelaksanaan (Hari Ini / Ke Depan)</label>
          <input type="date" name="tanggal" class="input-elegant" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required style="max-width: 300px;">
        </div>
        <button type="submit" class="btn-primary">+ Buka Sesi Absensi</button>
      </form>
    </div>

    <h3 class="section-title">Riwayat Sesi Absensi Aktif</h3>
    <?php
    $list_absen_sesi = [];
    $qa = mysqli_query($koneksi, "SELECT a.id, a.judul_absensi, a.tanggal, k.nama_kelas, 
        (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=a.kelas_id) AS total_murid,
        (SELECT COUNT(*) FROM absensi_hadir ah WHERE ah.absensi_id=a.id) AS total_hadir
        FROM absensi a JOIN kelas k ON k.id=a.kelas_id WHERE a.guru_id=$user_id ORDER BY a.id DESC");
    if($qa) { while ($ra = mysqli_fetch_assoc($qa)) $list_absen_sesi[] = $ra; }
    ?>
    <?php if(empty($list_absen_sesi)): ?>
      <div class="empty-state"><p>Belum ada sesi absensi yang dibuka.</p></div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_absen_sesi as $ab): ?>
        <div class="class-card">
          <h4 style="font-size:16px; font-weight:800; color:var(--sage-800); margin-bottom:6px;"><?= e($ab['judul_absensi']) ?></h4>
          <p style="font-size:13.5px; color:var(--sage-600); font-weight:600; margin-bottom:4px;">📍 Kelas: <?= e($ab['nama_kelas']) ?></p>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:20px;">📅 Tanggal: <?= e($ab['tanggal']) ?> &nbsp;|&nbsp; 🙋‍♂️ Hadir: <strong><?= (int)$ab['total_hadir'] ?>/<?= (int)$ab['total_murid'] ?></strong></p>
          
          <form method="POST" onsubmit="return confirm('Hapus sesi absensi ini?')" style="margin-top:auto;">
            <input type="hidden" name="aksi" value="hapus_absen">
            <input type="hidden" name="absen_id" value="<?= (int)$ab['id'] ?>">
            <button type="submit" class="btn-hapus" style="width:100%;">Hapus Sesi Absensi</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>


  <!-- ================= HALAMAN REKAP ABSENSI ================= -->
  <?php elseif ($page === 'rekap_absensi'): ?>
    <?php
      $kelas_rekap_id = (int)($_GET['kelas_id'] ?? ($list_kelas[0]['id_kelas'] ?? 0));
      $periode_rekap = ($_GET['periode'] ?? 'bulanan') === 'mingguan' ? 'mingguan' : 'bulanan';
      $tanggal_ref_rekap = $_GET['tanggal_ref'] ?? date('Y-m-d');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_ref_rekap)) $tanggal_ref_rekap = date('Y-m-d');
      [$mulai_rekap, $akhir_rekap] = rekapRentangTanggal($periode_rekap, $tanggal_ref_rekap);
      [$kelas_rekap, $data_rekap, $jumlah_sesi_rekap] = rekapAmbilData($koneksi, $user_id, $kelas_rekap_id, $mulai_rekap, $akhir_rekap);
      $query_rekap_base = 'guru.php?page=rekap_absensi&kelas_id='.(int)$kelas_rekap_id.'&periode='.urlencode($periode_rekap).'&tanggal_ref='.urlencode($tanggal_ref_rekap);
    ?>
    <div class="card-manage">
      <h2>Rekap Kehadiran Murid</h2>
      <p>Lihat ringkasan absensi mingguan atau bulanan dan unduh datanya sebagai Excel atau CSV.</p>
      <form method="GET" action="guru.php" style="margin-top:24px;">
        <input type="hidden" name="page" value="rekap_absensi">
        <div class="form-grid">
          <div><label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required>
              <?php foreach ($list_kelas as $kls): ?><option value="<?= (int)$kls['id_kelas'] ?>" <?= (int)$kls['id_kelas']===$kelas_rekap_id?'selected':'' ?>><?= e($kls['nama_kelas']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label class="form-label">Periode Rekap</label>
            <select name="periode" class="input-elegant"><option value="mingguan" <?= $periode_rekap==='mingguan'?'selected':'' ?>>Mingguan</option><option value="bulanan" <?= $periode_rekap==='bulanan'?'selected':'' ?>>Bulanan</option></select>
          </div>
        </div>
        <div style="margin-bottom:24px;max-width:360px"><label class="form-label">Pilih tanggal acuan</label><input type="date" name="tanggal_ref" class="input-elegant" value="<?= e($tanggal_ref_rekap) ?>" required><p style="font-size:12px;color:var(--sage-600);margin-top:8px">Tanggal ini menentukan minggu atau bulan yang akan direkap.</p></div>
        <button type="submit" class="btn-primary">Tampilkan Rekap</button>
      </form>
    </div>

    <?php if (empty($list_kelas)): ?>
      <div class="empty-state"><p>Belum ada kelas. Buat kelas terlebih dahulu untuk merekap absensi.</p></div>
    <?php elseif (!$kelas_rekap): ?>
      <div class="empty-state"><p>Kelas tidak ditemukan atau bukan milik akun guru ini.</p></div>
    <?php else: ?>
      <div class="card-manage" style="padding:26px">
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
          <div><h2><?= e($kelas_rekap['nama_kelas']) ?></h2><p><?= $periode_rekap==='mingguan'?'Rekap Mingguan':'Rekap Bulanan' ?>: <strong><?= e($mulai_rekap) ?></strong> sampai <strong><?= e($akhir_rekap) ?></strong></p><p style="margin-top:8px">Total sesi absensi: <strong><?= (int)$jumlah_sesi_rekap ?></strong></p></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn-primary" href="<?= e($query_rekap_base.'&ekspor_rekap=xlsx') ?>">Download Excel (.xlsx)</a>
            <a class="btn-primary" style="background:#fff;color:var(--sage-800);border:1px solid var(--sage-100);box-shadow:none" href="<?= e($query_rekap_base.'&ekspor_rekap=csv') ?>">Download CSV</a>
          </div>
        </div>
        <p style="font-size:12px;margin-top:16px">Catatan: sesi tanggal lampau yang tidak diisi otomatis dihitung sebagai <strong>Alpa</strong>. Untuk sesi hari ini yang belum diisi, statusnya masih <strong>Belum Absen</strong>.</p>
      </div>
      <?php if ($jumlah_sesi_rekap === 0): ?>
        <div class="empty-state"><p>Tidak ada sesi absensi untuk kelas dan rentang tanggal tersebut.</p></div>
      <?php elseif (empty($data_rekap)): ?>
        <div class="empty-state"><p>Belum ada murid yang bergabung di kelas ini.</p></div>
      <?php else: ?>
        <div style="overflow-x:auto;background:#fff;border:1px solid var(--sage-100);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)">
          <table style="width:100%;border-collapse:collapse;min-width:850px;font-size:13px">
            <thead><tr style="background:var(--sage-50);color:var(--sage-800);text-align:left">
              <th style="padding:14px">Nama Murid</th><th style="padding:14px;text-align:center">Hadir</th><th style="padding:14px;text-align:center">Izin</th><th style="padding:14px;text-align:center">Sakit</th><th style="padding:14px;text-align:center">Alpa</th><th style="padding:14px;text-align:center">Belum Absen</th><th style="padding:14px;text-align:center">Persentase Hadir</th>
            </tr></thead><tbody>
              <?php foreach ($data_rekap as $r): ?><tr style="border-top:1px solid var(--sage-50)">
                <td style="padding:14px;font-weight:700"><?= e($r['nama_murid']) ?></td><td style="padding:14px;text-align:center"><?= (int)$r['hadir'] ?></td><td style="padding:14px;text-align:center"><?= (int)$r['izin'] ?></td><td style="padding:14px;text-align:center"><?= (int)$r['sakit'] ?></td><td style="padding:14px;text-align:center;color:<?= $r['alpa']>0?'#C62828':'inherit' ?>;font-weight:<?= $r['alpa']>0?'800':'500' ?>"><?= (int)$r['alpa'] ?></td><td style="padding:14px;text-align:center"><?= (int)$r['belum_absen'] ?></td><td style="padding:14px;text-align:center;font-weight:800"><?= number_format($r['persentase'],1,',','.') ?>%</td>
              </tr><?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  <!-- ================= HALAMAN TUGAS ================= -->
  <?php elseif ($page === 'tugas'): ?>
    <div class="card-manage">
      <h2>Buat & Publikasikan Tugas</h2>
      <p>Unggah materi atau berikan instruksi tugas baru kepada siswa (tanggal deadline hanya bisa dipilih ke depan).</p>
      
      <form action="guru.php?page=tugas" method="POST" enctype="multipart/form-data" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="tambah_tugas">
        <div class="form-grid">
          <div>
            <label class="form-label">Judul Tugas</label>
            <input type="text" name="judul_tugas" class="input-elegant" required placeholder="Contoh: Praktik Algoritma Pemrograman">
          </div>
          <div>
            <label class="form-label">Pilih Kelas</label>
            <select name="kelas_id" class="input-elegant" required style="cursor:pointer;">
              <option value="" disabled selected>-- Tentukan Kelas Tujuan --</option>
              <?php foreach ($list_kelas as $kls): ?>
                <option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <div class="form-grid">
          <div>
            <label class="form-label">Batas Waktu (Deadline - Ke Depan)</label>
            <input type="datetime-local" name="deadline" class="input-elegant" min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div>
            <label class="form-label">Lampiran Berkas / File Tugas (Opsional)</label>
            <input type="file" name="file_tugas" class="input-elegant" style="padding: 12px; background:#fff;">
          </div>
        </div>

        <div style="margin-bottom: 24px;">
          <label class="form-label">Instruksi Pengerjaan</label>
          <textarea name="deskripsi" class="input-elegant" style="min-height: 120px; resize: vertical;" placeholder="Tulis instruksi tugas secara rinci di sini..."></textarea>
        </div>

        <button type="submit" class="btn-primary">Publikasikan Tugas Baru</button>
      </form>
    </div>

    <h3 class="section-title">Daftar Tugas Terkirim</h3>
    <?php 
    $list_tugas = [];
    $q = mysqli_query($koneksi, "SELECT t.id, t.judul_tugas AS judul, t.deadline, k.nama_kelas AS kelas,
        (SELECT COUNT(*) FROM anggota_kelas ak WHERE ak.kelas_id=t.kelas_id) AS jml_target,
        (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id=t.id) AS jml_kumpul
        FROM tugas t JOIN kelas k ON t.kelas_id=k.id WHERE t.guru_id=$user_id ORDER BY t.id DESC");
    if($q) { while ($r = mysqli_fetch_assoc($q)) $list_tugas[] = $r; }
    ?>
    <?php if (empty($list_tugas)): ?>
      <div class="empty-state"><p>Belum ada tugas yang dipublikasikan.</p></div>
    <?php else: ?>
      <div class="class-grid">
        <?php foreach ($list_tugas as $tg): ?>
        <div class="class-card">
          <h4 style="font-size:16px; font-weight:800; color:var(--sage-800); margin-bottom:6px;"><?= e($tg['judul']) ?></h4>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:4px;">📍 Kelas: <?= e($tg['kelas']) ?></p>
          <p style="font-size:13px; color:var(--sage-600); font-weight:600; margin-bottom:20px;">⏳ Deadline: <?= e($tg['deadline'] ?: 'Tanpa Batas') ?> &nbsp;|&nbsp; 📥 Kumpul: <strong><?= (int)$tg['jml_kumpul'] ?>/<?= (int)$tg['jml_target'] ?></strong></p>
          
          <form method="POST" onsubmit="return confirm('Hapus tugas ini?')" style="margin-top:auto;">
            <input type="hidden" name="aksi" value="hapus_tugas">
            <input type="hidden" name="tugas_id" value="<?= (int)$tg['id'] ?>">
            <button type="submit" class="btn-hapus" style="width:100%;">Hapus Tugas</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>


  <!-- ================= HALAMAN PROFIL ================= -->
  <?php elseif ($page === 'profil'): ?>
    <div class="card-manage">
      <h2>Pengaturan Profil & Keamanan Akun</h2>
      <p>Perbarui informasi identitas akun pengajar serta ubah kata sandi.</p>
      
      <form action="guru.php?page=profil" method="POST" style="margin-top: 24px;">
        <input type="hidden" name="aksi" value="update_profil">
        <div class="form-grid">
          <div><label class="form-label">Nama Lengkap</label><input type="text" name="nama" class="input-elegant" value="<?= e($nama_guru) ?>" required></div>
          <div><label class="form-label">Alamat Email</label><input type="email" name="email" class="input-elegant" value="<?= e($email_guru) ?>" required></div>
        </div>
        <div class="form-grid">
          <div><label class="form-label">Password Lama</label><input type="password" name="password_lama" class="input-elegant" placeholder="Password lama..."></div>
          <div><label class="form-label">Password Baru</label><input type="password" name="password_baru" class="input-elegant" placeholder="Password baru..."></div>
        </div>
        <button type="submit" class="btn-primary">Simpan Perubahan Profil</button>
      </form>
    </div>


  <!-- ================= HALAMAN QUIZ ================= -->
  <?php else: ?>
    <?php
      $kelolaQuizId = (int)($_GET['kelola_quiz'] ?? 0);
      $quizKelola = null; $soalKelola = []; $daftarQuiz = [];
      if ($kelolaQuizId > 0) {
        $sq = mysqli_prepare($koneksi, "SELECT q.*, k.nama_kelas FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.id=? AND q.guru_id=? LIMIT 1");
        mysqli_stmt_bind_param($sq, 'ii', $kelolaQuizId, $user_id); mysqli_stmt_execute($sq); $quizKelola = mysqli_fetch_assoc(mysqli_stmt_get_result($sq)); mysqli_stmt_close($sq);
        if ($quizKelola) { $ss = mysqli_prepare($koneksi, "SELECT * FROM quiz_soal WHERE quiz_id=? ORDER BY id ASC"); mysqli_stmt_bind_param($ss, 'i', $kelolaQuizId); mysqli_stmt_execute($ss); $rs=mysqli_stmt_get_result($ss); while($rr=mysqli_fetch_assoc($rs)) $soalKelola[]=$rr; mysqli_stmt_close($ss); }
      }
      $dq = mysqli_query($koneksi, "SELECT q.id,q.judul,q.deskripsi,q.durasi_menit,q.kelas_id,k.nama_kelas,(SELECT COUNT(*) FROM quiz_soal qs WHERE qs.quiz_id=q.id) AS jumlah_soal,(SELECT COUNT(*) FROM quiz_hasil qh WHERE qh.quiz_id=q.id) AS peserta FROM quiz q JOIN kelas k ON k.id=q.kelas_id WHERE q.guru_id=$user_id ORDER BY q.id DESC");
      while($dq && $rr=mysqli_fetch_assoc($dq)) $daftarQuiz[]=$rr;
    ?>
    <div class="card-manage">
      <h2>Modul Quiziz Pengajar</h2>
      <p style="margin-bottom:22px">Buat kuis pilihan ganda, tambahkan foto pada soal, dan pantau hasil murid.</p>
      <?php if (!$quizKelola): ?>
        <h3 style="font-size:16px;color:var(--sage-800);margin-bottom:14px">Buat Quiz Baru</h3>
        <form method="post" action="guru.php?page=quiz">
          <input type="hidden" name="aksi" value="buat_quiz"><input type="hidden" name="redirect_page" value="quiz">
          <div class="form-grid">
            <div><label class="form-label">Judul Quiz</label><input class="input-elegant" name="judul_quiz" required maxlength="180" placeholder="Contoh: Quiz Basis Data"></div>
            <div><label class="form-label">Kelas</label><select class="input-elegant" name="kelas_id" required><option value="">-- Pilih kelas --</option><?php foreach($list_kelas as $kls): ?><option value="<?= (int)$kls['id_kelas'] ?>"><?= e($kls['nama_kelas']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-grid">
            <div><label class="form-label">Durasi (menit)</label><input class="input-elegant" type="number" name="durasi_menit" min="1" max="300" value="15" required></div>
            <div><label class="form-label">Deskripsi (opsional)</label><input class="input-elegant" name="deskripsi_quiz" placeholder="Petunjuk atau materi quiz"></div>
          </div>
          <button class="btn-primary" type="submit">+ Buat Quiz</button>
        </form>
        <?php if (!$daftarQuiz): ?><div class="empty-state" style="margin-top:22px"><p>Belum ada quiz. Buat quiz pertamamu di atas.</p></div><?php else: ?>
          <h3 style="font-size:16px;color:var(--sage-800);margin:30px 0 14px">Quiz yang Sudah Dibuat</h3>
          <div style="display:grid;gap:14px">
          <?php foreach($daftarQuiz as $qz): ?><div style="background:#fff;border:1px solid var(--sage-100);border-radius:16px;padding:18px;display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap">
            <div><strong style="color:var(--sage-800)"><?= e($qz['judul']) ?></strong><p style="margin-top:5px"><?= e($qz['nama_kelas']) ?> · <?= (int)$qz['jumlah_soal'] ?> soal · <?= (int)$qz['durasi_menit'] ?> menit · <?= (int)$qz['peserta'] ?> peserta</p></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn-primary" style="padding:9px 15px" href="guru.php?page=quiz&kelola_quiz=<?= (int)$qz['id'] ?>">Kelola Soal</a><a class="btn-primary" style="padding:9px 15px;background:var(--sage-800)" href="quiziz.php?id=<?= (int)$qz['id'] ?>">Pratinjau</a><form method="post" onsubmit="return confirm('Hapus quiz beserta semua soal dan hasilnya?')"><input type="hidden" name="aksi" value="hapus_quiz"><input type="hidden" name="quiz_id" value="<?= (int)$qz['id'] ?>"><button class="btn-primary" style="padding:9px 15px;background:#b94a48" type="submit">Hapus</button></form></div>
          </div><?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php elseif (!$quizKelola): ?>
        <div class="alert error">Quiz tidak ditemukan atau bukan milik akun guru ini.</div><a class="btn-primary" href="guru.php?page=quiz">Kembali ke daftar quiz</a>
      <?php else: ?>
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:22px"><div><h3 style="font-size:20px;color:var(--sage-800)"><?= e($quizKelola['judul']) ?></h3><p><?= e($quizKelola['nama_kelas']) ?> · <?= (int)$quizKelola['durasi_menit'] ?> menit</p></div><a class="btn-primary" href="guru.php?page=quiz">← Daftar Quiz</a></div>
        <form method="post" enctype="multipart/form-data" action="guru.php?page=quiz&kelola_quiz=<?= (int)$quizKelola['id'] ?>" style="background:#F8FAF7;border:1px solid var(--sage-100);border-radius:16px;padding:22px;margin-bottom:24px">
          <input type="hidden" name="aksi" value="tambah_soal_quiz"><input type="hidden" name="quiz_id" value="<?= (int)$quizKelola['id'] ?>">
          <h3 style="font-size:16px;color:var(--sage-800);margin-bottom:16px">Tambah Soal</h3>
          <div style="margin-bottom:16px"><label class="form-label">Pertanyaan</label><textarea class="input-elegant" name="pertanyaan" rows="3" required placeholder="Tulis pertanyaan di sini..." style="width:100%;resize:vertical"></textarea></div>
          <div style="margin-bottom:18px"><label class="form-label">Foto soal (opsional)</label><input class="input-elegant" type="file" name="gambar_soal" id="gambarSoal" accept="image/png,image/jpeg,image/webp"><small style="display:block;color:var(--sage-600);margin-top:6px">Format JPG, PNG, WEBP · maksimal 5 MB</small><img id="previewGambarSoal" alt="Pratinjau foto soal" style="display:none;max-width:100%;max-height:260px;margin-top:12px;border-radius:12px;border:1px solid var(--sage-100)"></div>
          <div class="form-grid">
            <div><label class="form-label">Pilihan A</label><input class="input-elegant" name="opsi_a" required></div><div><label class="form-label">Pilihan B</label><input class="input-elegant" name="opsi_b" required></div>
            <div><label class="form-label">Pilihan C</label><input class="input-elegant" name="opsi_c" required></div><div><label class="form-label">Pilihan D</label><input class="input-elegant" name="opsi_d" required></div>
          </div>
          <div style="max-width:320px;margin-bottom:20px"><label class="form-label">Kunci Jawaban</label><select class="input-elegant" name="kunci" required><option value="">-- Pilih jawaban benar --</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
          <button class="btn-primary" type="submit">+ Simpan Soal</button>
        </form>
        <script>document.getElementById('gambarSoal')?.addEventListener('change',function(){const f=this.files&&this.files[0],img=document.getElementById('previewGambarSoal');if(!f){img.style.display='none';img.removeAttribute('src');return;}if(!f.type.startsWith('image/')){alert('Pilih file gambar.');this.value='';img.style.display='none';return;}img.src=URL.createObjectURL(f);img.style.display='block';});</script>
        <h3 style="font-size:16px;color:var(--sage-800);margin-bottom:14px">Daftar Soal (<?= count($soalKelola) ?>)</h3>
        <?php if (!$soalKelola): ?><div class="empty-state"><p>Belum ada soal. Tambahkan soal di atas.</p></div><?php else: ?>
          <div style="display:grid;gap:14px">
          <?php foreach($soalKelola as $i=>$so): ?><div style="border:1px solid var(--sage-100);border-radius:14px;padding:18px;background:#fff"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><strong><?= ($i+1) ?>. <?= e($so['pertanyaan']) ?></strong><form method="post" onsubmit="return confirm('Hapus soal ini?')"><input type="hidden" name="aksi" value="hapus_soal_quiz"><input type="hidden" name="quiz_id" value="<?= (int)$quizKelola['id'] ?>"><input type="hidden" name="soal_id" value="<?= (int)$so['id'] ?>"><button type="submit" class="btn-primary" style="padding:7px 12px;background:#b94a48">Hapus</button></form></div>
            <?php if(!empty($so['gambar'])): ?><img src="<?= e($so['gambar']) ?>" alt="Foto soal" style="display:block;max-width:100%;max-height:260px;margin:12px 0;border-radius:10px;border:1px solid var(--sage-100)"><?php endif; ?>
            <p style="font-size:13px;color:var(--sage-600);margin-top:10px">A. <?= e($so['opsi_a']) ?> · B. <?= e($so['opsi_b']) ?> · C. <?= e($so['opsi_c']) ?> · D. <?= e($so['opsi_d']) ?></p><p style="margin-top:8px;font-size:12px;font-weight:800;color:var(--sage-600)">Kunci: <?= e($so['kunci']) ?></p></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</main>
</body>
</html>
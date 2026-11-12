<?php
// ============================================================
//  PSC 119 — proses.php (DENGAN UPLOAD FOTO PASIEN & KTP)
//  Memproses form input laporan darurat
//  - Penugasan tim otomatis berdasarkan shift aktif
//  - Upload foto KTP dan foto pasien
// ============================================================
session_start();
require_once __DIR__ . '/../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true || !isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

$operator_id = (int) $_SESSION['id'];

// ============================================================
// 1. SANITASI & VALIDASI INPUT
// ============================================================
$nama_pelapor      = trim($_POST['nama_pelapor']      ?? '');
$no_telp_pelapor   = trim($_POST['no_telp_pelapor']   ?? '');
$lokasi_kejadian   = trim($_POST['lokasi_kejadian']   ?? '');
$jenis_kasus_id    = (int) ($_POST['jenis_kasus_id']  ?? 0);
$triase_id         = (int) ($_POST['triase_id']        ?? 0);
$deskripsi         = trim($_POST['deskripsi']          ?? '');
$waktu_kejadian    = trim($_POST['waktu_kejadian']     ?? '');

$errors = [];
if ($nama_pelapor === '')    $errors[] = 'Nama pelapor wajib diisi.';
if ($no_telp_pelapor === '') $errors[] = 'Nomor telepon pelapor wajib diisi.';
if ($lokasi_kejadian === '') $errors[] = 'Lokasi kejadian wajib diisi.';
if ($jenis_kasus_id <= 0)   $errors[] = 'Jenis kasus wajib dipilih.';
if ($triase_id <= 0)        $errors[] = 'Tingkat triase wajib dipilih.';
if ($deskripsi === '')      $errors[] = 'Deskripsi kejadian wajib diisi.';
if ($waktu_kejadian === '') $errors[] = 'Waktu kejadian wajib diisi.';

// Konversi datetime-local ke format MySQL
if ($waktu_kejadian !== '') {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $waktu_kejadian);
    if (!$dt) $errors[] = 'Format waktu kejadian tidak valid.';
    else $waktu_kejadian = $dt->format('Y-m-d H:i:s');
}

// Direktori upload
$upload_dir = __DIR__ . '/../uploads/pasien/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ============================================================
// KUMPULKAN DATA PASIEN + UPLOAD FOTO
// ============================================================
$daftar_pasien_input = [];

if (!empty($_POST['pasien']) && is_array($_POST['pasien'])) {
    foreach ($_POST['pasien'] as $idx => $p) {
        $kondisi = trim($p['kondisi_awal'] ?? '');
        if ($kondisi === '') {
            $errors[] = 'Kondisi/keluhan pasien #' . ((int)$idx + 1) . ' wajib diisi.';
            continue;
        }

        // Proses upload foto_ktp
        $foto_ktp = null;
        if (isset($_FILES['foto_ktp']['name'][$idx]) && $_FILES['foto_ktp']['error'][$idx] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto_ktp']['tmp_name'][$idx];
            $name = $_FILES['foto_ktp']['name'][$idx];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $errors[] = "Format foto KTP pasien #" . ((int)$idx+1) . " tidak valid (JPG/PNG).";
            } elseif ($_FILES['foto_ktp']['size'][$idx] > 2 * 1024 * 1024) {
                $errors[] = "Ukuran foto KTP pasien #" . ((int)$idx+1) . " maksimal 2MB.";
            } else {
                $new_name = 'ktp_' . uniqid() . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                    $foto_ktp = 'uploads/pasien/' . $new_name;
                }
            }
        }

        // Proses upload foto_pasien
        $foto_pasien = null;
        if (isset($_FILES['foto_pasien']['name'][$idx]) && $_FILES['foto_pasien']['error'][$idx] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['foto_pasien']['tmp_name'][$idx];
            $name = $_FILES['foto_pasien']['name'][$idx];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $errors[] = "Format foto pasien #" . ((int)$idx+1) . " tidak valid (JPG/PNG).";
            } elseif ($_FILES['foto_pasien']['size'][$idx] > 2 * 1024 * 1024) {
                $errors[] = "Ukuran foto pasien #" . ((int)$idx+1) . " maksimal 2MB.";
            } else {
                $new_name = 'pasien_' . uniqid() . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                    $foto_pasien = 'uploads/pasien/' . $new_name;
                }
            }
        }

        $daftar_pasien_input[] = [
            'nama_pasien'   => trim($p['nama_pasien']   ?? '') ?: null,
            'nik'           => trim($p['nik']           ?? '') ?: null,
            'umur'          => (isset($p['umur']) && ctype_digit($p['umur'])) ? (int)$p['umur'] : null,
            'jenis_kelamin' => in_array($p['jenis_kelamin'] ?? '', ['L','P'], true) ? $p['jenis_kelamin'] : null,
            'kondisi_awal'  => $kondisi,
            'foto_ktp'      => $foto_ktp,
            'foto_pasien'   => $foto_pasien
        ];
    }
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: index.php');
    exit;
}

// ============================================================
// 2. PENUGASAN TIM OTOMATIS BERDASARKAN SHIFT AKTIF
// ============================================================
$jam_sekarang = date('H:i:s');
if ($jam_sekarang >= '08:00:00' && $jam_sekarang < '14:00:00') {
    $kode_shift = 'PAGI';
} elseif ($jam_sekarang >= '14:00:00' && $jam_sekarang < '20:00:00') {
    $kode_shift = 'SIANG';
} else {
    $kode_shift = 'MALAM';
}

$hari_ini = date('Y-m-d');
$query_shift = "SELECT js.id AS jadwal_shift_id, js.tim_id
                FROM jadwal_shift js
                JOIN shift_definisi sd ON sd.id = js.shift_definisi_id
                WHERE js.tanggal = ? AND sd.kode_shift = ?
                LIMIT 1";
$stmt_shift = mysqli_prepare($koneksi, $query_shift);
mysqli_stmt_bind_param($stmt_shift, 'ss', $hari_ini, $kode_shift);
mysqli_stmt_execute($stmt_shift);
$row_shift = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_shift));
mysqli_stmt_close($stmt_shift);

if ($row_shift) {
    $jadwal_shift_id_auto = (int) $row_shift['jadwal_shift_id'];
    $tim_id_auto          = (int) $row_shift['tim_id'];
    
    // Ambil nama tim dari database
    $nama_tim = '';
    $stmt_nama = mysqli_prepare($koneksi, "SELECT nama_tim FROM tim WHERE id = ?");
    mysqli_stmt_bind_param($stmt_nama, 'i', $tim_id_auto);
    mysqli_stmt_execute($stmt_nama);
    $res_nama = mysqli_stmt_get_result($stmt_nama);
    if ($row_nama = mysqli_fetch_assoc($res_nama)) {
        $nama_tim = $row_nama['nama_tim'];
    }
    mysqli_stmt_close($stmt_nama);
} else {
    $_SESSION['error'] = "Tidak ada tim yang sedang berjaga untuk shift {$kode_shift} pada hari ini.";
    header('Location: index.php');
    exit;
}

// ============================================================
// 3. GENERATE NOMOR KASUS
// ============================================================
$tanggal_prefix = date('Ymd');
$prefix_like = 'PSC-' . $tanggal_prefix . '-%';

mysqli_begin_transaction($koneksi);

$stmt_cek = mysqli_prepare($koneksi, "SELECT nomor_kasus FROM kasus WHERE nomor_kasus LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
mysqli_stmt_bind_param($stmt_cek, 's', $prefix_like);
mysqli_stmt_execute($stmt_cek);
$row_terakhir = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
mysqli_stmt_close($stmt_cek);

if ($row_terakhir) {
    $parts = explode('-', $row_terakhir['nomor_kasus']);
    $urutan = (int) end($parts) + 1;
} else {
    $urutan = 1;
}
$nomor_kasus = 'PSC-' . $tanggal_prefix . '-' . str_pad($urutan, 3, '0', STR_PAD_LEFT);

// ============================================================
// 4. INSERT KASUS (dengan tim_penanggung_jawab_id) - AMAN
// ============================================================
$status_awal = 'menunggu_respon';

// Konversi 0 menjadi NULL untuk foreign key
$jadwal_shift_id_final = ($jadwal_shift_id_auto > 0) ? $jadwal_shift_id_auto : null;
$tim_id_final = ($tim_id_auto > 0) ? $tim_id_auto : null;

$stmt_insert = mysqli_prepare($koneksi, "
    INSERT INTO kasus
        (nomor_kasus, nama_pelapor, no_telp_pelapor, lokasi_kejadian,
         jenis_kasus_id, triase_id, deskripsi, waktu_kejadian,
         jadwal_shift_id, tim_id, tim_penanggung_jawab_id, operator_id, status_terakhir, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

// Definisikan tipe data untuk bind_param
// Urutan: 1 s, 2 s, 3 s, 4 s, 5 i, 6 i, 7 s, 8 s, 9 i (bisa null), 10 i (null), 11 i (null), 12 i, 13 s
$types = 'ssssiissiiiis'; // perbaiki: 13 karakter? Hitung: s(1) s(2) s(3) s(4) i(5) i(6) s(7) s(8) i(9) i(10) i(11) i(12) s(13) -> 13. String 'ssssiissiiiii' -> 4s+2i+2s+4i+1s? 4+2+2+4+1=13 (iya). Tapi urutan i ke-10,11,12,13? No: setelah 'ssssiiss' -> 8 karakter, sisa 5 karakter 'iiiii' -> 5 i. Jadi total 4s+2i+2s+5i = 4+2+2+5=13. Pas.

$params = [
    $nomor_kasus, $nama_pelapor, $no_telp_pelapor, $lokasi_kejadian,
    $jenis_kasus_id, $triase_id, $deskripsi, $waktu_kejadian,
    $jadwal_shift_id_final, $tim_id_final, $tim_id_final, $operator_id, $status_awal
];

// Gunakan splat operator PHP 5.6+ untuk bind dinamis
mysqli_stmt_bind_param($stmt_insert, $types, ...$params);

if (!mysqli_stmt_execute($stmt_insert)) {
    mysqli_rollback($koneksi);
    $_SESSION['error'] = 'Gagal menyimpan laporan: ' . mysqli_stmt_error($stmt_insert);
    header('Location: index.php');
    exit;
}
$id_kasus_baru = (int) mysqli_stmt_insert_id($stmt_insert);
mysqli_stmt_close($stmt_insert);


// ============================================================
// 5. RIWAYAT STATUS AWAL
// ============================================================
$catatan_awal = 'Laporan masuk diterima oleh operator. Tim ditugaskan secara otomatis.';
$stmt_riwayat = mysqli_prepare($koneksi, "INSERT INTO riwayat_status_kasus (kasus_id, status, diubah_oleh, catatan, waktu) VALUES (?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt_riwayat, 'isis', $id_kasus_baru, $status_awal, $operator_id, $catatan_awal);
mysqli_stmt_execute($stmt_riwayat);
mysqli_stmt_close($stmt_riwayat);

// ============================================================
// 6. INSERT PASIEN (dengan foto)
// ============================================================
if (empty($daftar_pasien_input)) {
    $daftar_pasien_input[] = [
        'nama_pasien'   => 'Pasien Tidak Dikenal',
        'nik'           => null,
        'umur'          => null,
        'jenis_kelamin' => null,
        'kondisi_awal'  => '-',
        'foto_ktp'      => null,
        'foto_pasien'   => null
    ];
}

$stmt_pasien = mysqli_prepare($koneksi, "
    INSERT INTO pasien (kasus_id, nama_pasien, nik, umur, jenis_kelamin, kondisi_awal, foto_ktp, foto_pasien, diinput_oleh, created_at, updated_at)
    VALUES (?, ?, ?, NULLIF(?,0), NULLIF(?, ''), ?, ?, ?, ?, NOW(), NOW())
");

foreach ($daftar_pasien_input as $p) {

    $nama         = $p['nama_pasien'] ?? '';
    $nik          = $p['nik'] ?? '';
    $umur         = $p['umur'] ?? 0;
    $jk           = $p['jenis_kelamin'] ?? '';
    $kondisi      = $p['kondisi_awal'] ?? '';
    $foto_ktp     = $p['foto_ktp'] ?? '';
    $foto_pasien  = $p['foto_pasien'] ?? '';


    mysqli_stmt_bind_param(
        $stmt_pasien,
        'ississssi',
        $id_kasus_baru,
        $nama,
        $nik,
        $umur,
        $jk,
        $kondisi,
        $foto_ktp,
        $foto_pasien,
        $operator_id
    );

    mysqli_stmt_execute($stmt_pasien);
}

mysqli_stmt_close($stmt_pasien);

// Commit semua
mysqli_commit($koneksi);

// ============================================================
// 7. SUKSES
// ============================================================
$_SESSION['success'] = "Laporan berhasil disimpan dengan nomor kasus: <strong>{$nomor_kasus}</strong> Dan <strong>{$nama_tim}</strong> telah ditugaskan secara otomatis.";
header('Location: index.php');
exit;
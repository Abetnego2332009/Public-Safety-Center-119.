<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$user_id = $_SESSION['id'];
$kasus_id = (int) ($_POST['kasus_id'] ?? 0);
$tim_tujuan = (int) ($_POST['tim_tujuan'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');

if ($kasus_id <= 0 || $tim_tujuan <= 0 || empty($alasan)) {
    $_SESSION['error'] = 'Data tidak lengkap.';
    header("Location: edit.php?id=$kasus_id");
    exit;
}

// Validasi: user harus menjadi tim penanggung jawab kasus ini
$sql_cek = "SELECT tim_penanggung_jawab_id, status_terakhir FROM kasus WHERE id = ?";
$stmt = mysqli_prepare($koneksi, $sql_cek);
mysqli_stmt_bind_param($stmt, 'i', $kasus_id);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$kasus || in_array($kasus['status_terakhir'], ['selesai','dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah selesai atau tidak ditemukan.';
    header("Location: edit.php?id=$kasus_id");
    exit;
}

// Ambil tim user
$user_tim = null;
$sql_ut = "SELECT tim_id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL LIMIT 1";
$stmt_ut = mysqli_prepare($koneksi, $sql_ut);
mysqli_stmt_bind_param($stmt_ut, 'i', $user_id);
mysqli_stmt_execute($stmt_ut);
$res_ut = mysqli_stmt_get_result($stmt_ut);
$row_ut = mysqli_fetch_assoc($res_ut);
mysqli_stmt_close($stmt_ut);
if ($row_ut) $user_tim = $row_ut['tim_id'];

if ($kasus['tim_penanggung_jawab_id'] != $user_tim) {
    $_SESSION['error'] = 'Anda bukan penanggung jawab kasus ini.';
    header("Location: edit.php?id=$kasus_id");
    exit;
}

// Pastikan tim tujuan adalah tim shift sekarang
$tim_shift = getTimBertugasSekarang($koneksi);
if (!$tim_shift || $tim_shift['id'] != $tim_tujuan) {
    $_SESSION['error'] = 'Tim tujuan tidak valid (bukan tim yang bertugas sekarang).';
    header("Location: edit.php?id=$kasus_id");
    exit;
}

// Proses takeover
mysqli_begin_transaction($koneksi);
try {
    // Update kasus
    $sql_upd = "UPDATE kasus SET tim_penanggung_jawab_id = ? WHERE id = ?";
    $stmt_upd = mysqli_prepare($koneksi, $sql_upd);
    mysqli_stmt_bind_param($stmt_upd, 'ii', $tim_tujuan, $kasus_id);
    mysqli_stmt_execute($stmt_upd);
    mysqli_stmt_close($stmt_upd);
    
    // Catat histori takeover
    $sql_hist = "INSERT INTO histori_takeover (kasus_id, tim_asal_id, tim_tujuan_id, alasan, user_id) VALUES (?, ?, ?, ?, ?)";
    $stmt_hist = mysqli_prepare($koneksi, $sql_hist);
    mysqli_stmt_bind_param($stmt_hist, 'iiisi', $kasus_id, $user_tim, $tim_tujuan, $alasan, $user_id);
    mysqli_stmt_execute($stmt_hist);
    mysqli_stmt_close($stmt_hist);
    
    // Catat ke log_aktivitas (opsional)
    $ip = $_SERVER['REMOTE_ADDR'];
    $deskripsi = "Takeover kasus dari tim_id $user_tim ke tim_id $tim_tujuan. Alasan: $alasan";
    $sql_log = "INSERT INTO log_aktivitas (user_id, aksi, tabel_tujuan, record_id, deskripsi, ip_address) VALUES (?, 'TAKEOVER_SHIFT', 'kasus', ?, ?, ?)";
    $stmt_log = mysqli_prepare($koneksi, $sql_log);
    mysqli_stmt_bind_param($stmt_log, 'iiss', $user_id, $kasus_id, $deskripsi, $ip);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
    
    mysqli_commit($koneksi);
    $_SESSION['success'] = "Kasus berhasil dialihkan ke " . htmlspecialchars($tim_shift['nama_tim']);
    header("Location: index.php");
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['error'] = "Gagal mengalihkan: " . $e->getMessage();
    header("Location: edit.php?id=$kasus_id");
}
exit;
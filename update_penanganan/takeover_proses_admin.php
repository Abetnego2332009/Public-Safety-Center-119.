<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1,3])) {
    $_SESSION['error'] = 'Akses ditolak.';
    header('Location: index.php');
    exit;
}

$kasus_id = (int)($_POST['kasus_id'] ?? 0);
$tim_tujuan = (int)($_POST['tim_tujuan'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');

if ($kasus_id <= 0 || $tim_tujuan <= 0 || empty($alasan)) {
    $_SESSION['error'] = 'Data tidak lengkap.';
    header('Location: index.php');
    exit;
}

// Ambil data kasus dan tim asal
$sql_kasus = "SELECT tim_penanggung_jawab_id, status_terakhir FROM kasus WHERE id = ?";
$stmt = mysqli_prepare($koneksi, $sql_kasus);
mysqli_stmt_bind_param($stmt, 'i', $kasus_id);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$kasus || in_array($kasus['status_terakhir'], ['selesai','dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah selesai/dibatalkan atau tidak ditemukan.';
    header('Location: index.php');
    exit;
}

$tim_asal = $kasus['tim_penanggung_jawab_id'];
$user_id = $_SESSION['id'];

mysqli_begin_transaction($koneksi);
try {
    // Update penanggung jawab
    $sql_upd = "UPDATE kasus SET tim_penanggung_jawab_id = ? WHERE id = ?";
    $stmt_upd = mysqli_prepare($koneksi, $sql_upd);
    mysqli_stmt_bind_param($stmt_upd, 'ii', $tim_tujuan, $kasus_id);
    mysqli_stmt_execute($stmt_upd);
    mysqli_stmt_close($stmt_upd);
    
    // Catat histori takeover
    $sql_hist = "INSERT INTO histori_takeover (kasus_id, tim_asal_id, tim_tujuan_id, alasan, user_id) VALUES (?, ?, ?, ?, ?)";
    $stmt_hist = mysqli_prepare($koneksi, $sql_hist);
    mysqli_stmt_bind_param($stmt_hist, 'iiisi', $kasus_id, $tim_asal, $tim_tujuan, $alasan, $user_id);
    mysqli_stmt_execute($stmt_hist);
    mysqli_stmt_close($stmt_hist);
    
    // Catat ke log_aktivitas (opsional)
    $ip = $_SERVER['REMOTE_ADDR'];
    $deskripsi = "ADMIN/KOORDINATOR: Takeover kasus dari tim_id $tim_asal ke tim_id $tim_tujuan. Alasan: $alasan";
    $sql_log = "INSERT INTO log_aktivitas (user_id, aksi, tabel_tujuan, record_id, deskripsi, ip_address) VALUES (?, 'TAKEOVER_SHIFT_ADMIN', 'kasus', ?, ?, ?)";
    $stmt_log = mysqli_prepare($koneksi, $sql_log);
    mysqli_stmt_bind_param($stmt_log, 'iiss', $user_id, $kasus_id, $deskripsi, $ip);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
    
    mysqli_commit($koneksi);
    
    // Ambil nama tim tujuan untuk notifikasi
    $tim_tujuan_info = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_tim FROM tim WHERE id = $tim_tujuan"));
    $_SESSION['success'] = "Takeover berhasil! Kasus sekarang ditangani oleh " . htmlspecialchars($tim_tujuan_info['nama_tim'] ?? 'tim baru');
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['error'] = 'Gagal melakukan takeover: ' . $e->getMessage();
}
header('Location: index.php');
exit;
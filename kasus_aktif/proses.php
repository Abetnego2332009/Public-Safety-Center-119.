<?php
// ============================================================
//  FILE    : kasus_aktif/proses.php
//  FUNGSI  : Memproses update status kasus
//  VERSI   : 1.1.0 (FIXED - Sesuai db_psc119.sql)
// ============================================================
session_start();
require_once __DIR__ . '/../koneksi.php';

if (
    !isset($_SESSION['sudah_login']) ||
    $_SESSION['sudah_login'] !== true ||
    !isset($_SESSION['id'])
) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_kasus = (int) ($_POST['id_kasus'] ?? 0);
$status_baru = trim($_POST['status_baru'] ?? '');
$id_tim = (int) ($_POST['id_tim'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');
$redirect_shift = trim($_POST['redirect_shift'] ?? '');
$user_id = $_SESSION['id'] ?? 0;

$status_valid = ['menuju_lokasi', 'tiba_di_lokasi', 'penanganan_di_tempat', 'dirujuk_ke_rs', 'selesai', 'dibatalkan'];

if ($id_kasus <= 0) {
    $_SESSION['error'] = 'ID kasus tidak valid.';
    header('Location: index.php');
    exit;
}

if (!in_array($status_baru, $status_valid)) {
    $_SESSION['error'] = 'Status tidak valid.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

if ($status_baru === 'dibatalkan' && $catatan === '') {
    $_SESSION['error'] = 'Alasan pembatalan wajib diisi.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

if ($status_baru === 'menuju_lokasi' && $id_tim <= 0) {
    $_SESSION['error'] = 'Tim wajib dipilih saat menugaskan.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

// Cek kasus
$stmt_cek = mysqli_prepare($koneksi, "SELECT id, status_terakhir FROM kasus WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_cek, 'i', $id_kasus);
mysqli_stmt_execute($stmt_cek);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
mysqli_stmt_close($stmt_cek);

if (!$kasus) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// ===================== TAMBAHKAN INI =====================
// Validasi akses shift (user harus tim bertugas yang berhak)
if (!canUserEditKasus($koneksi, $user_id, $id_kasus)) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah kasus ini.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}
// ==========================================================

// Validasi urutan status
$urutan = [
    'menunggu_respon' => 0,
    'menuju_lokasi' => 1,
    'tiba_di_lokasi' => 2,
    'penanganan_di_tempat' => 3,
    'dirujuk_ke_rs' => 4,
    'selesai' => 5,
    'dibatalkan' => 99
];

$status_sekarang = $kasus['status_terakhir'];
if ($status_baru !== 'dibatalkan') {
    if (($urutan[$status_baru] ?? 99) <= ($urutan[$status_sekarang] ?? 0)) {
        $_SESSION['error'] = 'Status tidak bisa mundur. Status saat ini: ' . $status_sekarang;
        header('Location: edit.php?id=' . $id_kasus);
        exit;
    }
}

if (in_array($status_sekarang, ['selesai', 'dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah final, tidak bisa diupdate.';
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($koneksi);

try {
    // Update kasus
    $set_parts = ["status_terakhir = ?"];
    $bind_types = 's';
    $bind_values = [$status_baru];
    
    if ($status_baru === 'menuju_lokasi' && $id_tim > 0) {
        $set_parts[] = "tim_id = ?";
        $bind_types .= 'i';
        $bind_values[] = $id_tim;
    }
    
    if ($status_baru === 'selesai') {
        $set_parts[] = "waktu_selesai = NOW()";
    }
    
    $bind_types .= 'i';
    $bind_values[] = $id_kasus;
    
    $sql_update = "UPDATE kasus SET " . implode(', ', $set_parts) . " WHERE id = ?";
    $stmt_upd = mysqli_prepare($koneksi, $sql_update);
    mysqli_stmt_bind_param($stmt_upd, $bind_types, ...$bind_values);
    
    if (!mysqli_stmt_execute($stmt_upd)) {
        throw new Exception('Gagal update kasus: ' . mysqli_stmt_error($stmt_upd));
    }
    mysqli_stmt_close($stmt_upd);
    
    // Insert riwayat
    $stmt_rwyt = mysqli_prepare($koneksi, "
        INSERT INTO riwayat_status_kasus (kasus_id, status, diubah_oleh, catatan)
        VALUES (?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt_rwyt, 'isis', $id_kasus, $status_baru, $user_id, $catatan);
    
    if (!mysqli_stmt_execute($stmt_rwyt)) {
        throw new Exception('Gagal insert riwayat: ' . mysqli_stmt_error($stmt_rwyt));
    }
    mysqli_stmt_close($stmt_rwyt);
    
    mysqli_commit($koneksi);
    
    $pesan_map = [
        'menuju_lokasi' => '✅ Tim berhasil ditugaskan.',
        'tiba_di_lokasi' => '📍 Konfirmasi tiba di lokasi berhasil.',
        'penanganan_di_tempat' => '🏥 Penanganan di tempat dimulai.',
        'dirujuk_ke_rs' => '🚑 Pasien dirujuk ke RS.',
        'selesai' => '🎉 Kasus berhasil diselesaikan!',
        'dibatalkan' => '⚠️ Kasus telah dibatalkan.',
    ];
    $_SESSION['success'] = $pesan_map[$status_baru] ?? 'Status berhasil diupdate.';
    
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['error'] = 'Gagal update: ' . $e->getMessage();
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

if (in_array($status_baru, ['selesai', 'dibatalkan'])) {
    $qs = $redirect_shift ? '?shift=' . urlencode($redirect_shift) : '';
    header('Location: index.php' . $qs);
} else {
    header('Location: edit.php?id=' . $id_kasus . ($redirect_shift ? '&shift=' . urlencode($redirect_shift) : ''));
}
exit;
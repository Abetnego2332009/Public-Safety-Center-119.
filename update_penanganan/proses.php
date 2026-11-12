<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true || !isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['id'];
$id_kasus = (int) ($_POST['id_kasus'] ?? 0);

// Validasi akses menggunakan fungsi baru
if (!canUserEditKasus($koneksi, $user_id, $id_kasus)) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah kasus ini.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

$status_baru = trim($_POST['status_baru'] ?? '');
$id_tim = (int) ($_POST['id_tim'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

$allowed = ['menuju_lokasi', 'tiba_di_lokasi', 'penanganan_di_tempat', 'dirujuk_ke_rs', 'selesai', 'dibatalkan'];
if (!in_array($status_baru, $allowed)) {
    $_SESSION['error'] = 'Status tidak valid.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}
if ($status_baru === 'dibatalkan' && empty($catatan)) {
    $_SESSION['error'] = 'Alasan pembatalan wajib diisi.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}
if ($status_baru === 'menuju_lokasi' && $id_tim <= 0) {
    $_SESSION['error'] = 'Tim wajib dipilih saat menuju lokasi.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

// Cek kasus
$stmt = mysqli_prepare($koneksi, "SELECT status_terakhir FROM kasus WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_kasus);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}
$status_lama = $row['status_terakhir'];
if (in_array($status_lama, ['selesai', 'dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah final.';
    header('Location: index.php');
    exit;
}
$urutan = ['menunggu_respon'=>0,'menuju_lokasi'=>1,'tiba_di_lokasi'=>2,'penanganan_di_tempat'=>3,'dirujuk_ke_rs'=>4,'selesai'=>5,'dibatalkan'=>99];
if ($status_baru !== 'dibatalkan' && ($urutan[$status_baru] ?? 99) <= ($urutan[$status_lama] ?? 0)) {
    $_SESSION['error'] = 'Status tidak bisa mundur.';
    header('Location: edit.php?id=' . $id_kasus);
    exit;
}

// Upload foto
$upload_dir = __DIR__ . '/../uploads/pasien/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
$foto_pasien = null;
$foto_ktp = null;
if (isset($_FILES['foto_pasien']) && $_FILES['foto_pasien']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['foto_pasien']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png'])) {
        $nama = 'pasien_' . uniqid() . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['foto_pasien']['tmp_name'], $upload_dir . $nama);
        $foto_pasien = 'uploads/pasien/' . $nama;
    }
}
if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['foto_ktp']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png'])) {
        $nama = 'ktp_' . uniqid() . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $upload_dir . $nama);
        $foto_ktp = 'uploads/pasien/' . $nama;
    }
}

// Rujukan RS
$rumah_sakit_id = null;
$nama_rs_manual = null;
$lokasi_rs_manual = null;
if ($status_baru === 'dirujuk_ke_rs') {
    $rs_option = $_POST['rumah_sakit_id'] ?? '';
    if ($rs_option === 'other') {
        $nama_rs_manual = trim($_POST['rs_tujuan_manual'] ?? '');
        $lokasi_rs_manual = trim($_POST['rs_lokasi_manual'] ?? '');
        if (empty($nama_rs_manual)) {
            $_SESSION['error'] = 'Nama RS tujuan wajib diisi.';
            header('Location: edit.php?id=' . $id_kasus);
            exit;
        }
    } elseif (is_numeric($rs_option) && $rs_option > 0) {
        $rumah_sakit_id = (int)$rs_option;
        $cek = mysqli_query($koneksi, "SELECT id FROM rumah_sakit WHERE id = $rumah_sakit_id AND is_aktif=1");
        if (mysqli_num_rows($cek) == 0) {
            $_SESSION['error'] = 'RS tujuan tidak valid.';
            header('Location: edit.php?id=' . $id_kasus);
            exit;
        }
    } else {
        $_SESSION['error'] = 'Pilih RS tujuan.';
        header('Location: edit.php?id=' . $id_kasus);
        exit;
    }
}

mysqli_begin_transaction($koneksi);
try {
    // Update kasus
    $set = ["status_terakhir = '$status_baru'"];
    if ($status_baru === 'menuju_lokasi' && $id_tim > 0) {
        $set[] = "tim_id = $id_tim";
        // Jika kasus belum memiliki tim_penanggung_jawab_id, isi dengan tim yang ditugaskan
        $set[] = "tim_penanggung_jawab_id = $id_tim";
    }
    if ($status_baru === 'selesai') $set[] = "waktu_selesai = NOW()";
    mysqli_query($koneksi, "UPDATE kasus SET " . implode(', ', $set) . " WHERE id = $id_kasus");

    // Riwayat
    $catatan_lengkap = $catatan;
    if ($status_baru === 'dirujuk_ke_rs') {
        $tujuan = $rumah_sakit_id ? "RS ID $rumah_sakit_id" : ($nama_rs_manual ?: 'tidak diketahui');
        $lokasi = $lokasi_rs_manual ? " Lokasi: $lokasi_rs_manual" : '';
        $catatan_lengkap = "RUJUKAN KE: {$tujuan}{$lokasi}. " . $catatan;
    }
    $stmt = mysqli_prepare($koneksi, "INSERT INTO riwayat_status_kasus (kasus_id, status, diubah_oleh, catatan) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isis', $id_kasus, $status_baru, $user_id, $catatan_lengkap);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update foto pasien
    $stmt = mysqli_prepare($koneksi, "SELECT id FROM pasien WHERE kasus_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id_kasus);
    mysqli_stmt_execute($stmt);
    $pasien_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $pasien_id = $pasien_row['id'] ?? null;
    mysqli_stmt_close($stmt);
    if ($pasien_id && ($foto_pasien || $foto_ktp)) {
        $updates = [];
        if ($foto_pasien) $updates[] = "foto_pasien = '$foto_pasien'";
        if ($foto_ktp) $updates[] = "foto_ktp = '$foto_ktp'";
        mysqli_query($koneksi, "UPDATE pasien SET " . implode(',', $updates) . " WHERE id = $pasien_id");
    }

    // Insert rujukan
    if ($status_baru === 'dirujuk_ke_rs') {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO rujukan (kasus_id, pasien_id, rumah_sakit_id, nama_rs_manual, lokasi_rs, waktu_rujukan, catatan, diinput_oleh) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiisssi', $id_kasus, $pasien_id, $rumah_sakit_id, $nama_rs_manual, $lokasi_rs_manual, $catatan, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($koneksi);
    $_SESSION['success'] = 'Status kasus berhasil diupdate.';
    header('Location: index.php');
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['error'] = 'Gagal: ' . $e->getMessage();
    header('Location: edit.php?id=' . $id_kasus);
}
exit;
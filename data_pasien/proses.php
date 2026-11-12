<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/pasien/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

function upload_foto($file_input, $prefix) {
    global $upload_dir;
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$file_input]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $nama = $prefix . '_' . uniqid() . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $upload_dir . $nama)) {
                return 'uploads/pasien/' . $nama;
            }
        }
    }
    return null;
}

function cek_akses_pasien($koneksi, $id_pasien, $role, $tim_user_id) {
    if ($role !== 'tim_respon') return true;
    if (!$tim_user_id) return false;
    $stmt = mysqli_prepare($koneksi, "SELECT 1 FROM pasien p JOIN kasus k ON p.kasus_id = k.id WHERE p.id = ? AND k.tim_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id_pasien, $tim_user_id);
    mysqli_stmt_execute($stmt);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool)$exists;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['id'];
$role = $_SESSION['nama_role'] ?? '';
$tim_user_id = $_SESSION['tim_id'] ?? null;

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        $_SESSION['error'] = 'ID tidak valid.';
        header('Location: index.php');
        exit;
    }
    if (!cek_akses_pasien($koneksi, $id, $role, $tim_user_id)) {
        $_SESSION['error'] = 'Anda tidak memiliki akses.';
        header('Location: index.php');
        exit;
    }
    
    // Upload foto baru (jika ada)
    $foto_pasien_baru = upload_foto('foto_pasien', 'pasien');
    $foto_ktp_baru = upload_foto('foto_ktp', 'ktp');
    
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $umur = ($_POST['umur'] !== '') ? (int)$_POST['umur'] : null;
    $jenis_kelamin = in_array($_POST['jenis_kelamin'] ?? '', ['L','P']) ? $_POST['jenis_kelamin'] : null;
    $kondisi_awal = trim($_POST['kondisi_awal'] ?? '');
    
    if (empty($kondisi_awal)) {
        $_SESSION['error'] = 'Kondisi awal wajib diisi.';
        header('Location: edit.php?id=' . $id);
        exit;
    }
    
    // Bangun query dinamis untuk foto
    $set_fields = "nama_pasien=?, nik=?, umur=?, jenis_kelamin=?, kondisi_awal=?, updated_at=NOW(), updated_by=?";
    $params = [$nama_pasien, $nik, $umur, $jenis_kelamin, $kondisi_awal, $user_id];
    $types = "ssissi";
    if ($foto_pasien_baru) {
        $set_fields .= ", foto_pasien=?";
        $params[] = $foto_pasien_baru;
        $types .= "s";
    }
    if ($foto_ktp_baru) {
        $set_fields .= ", foto_ktp=?";
        $params[] = $foto_ktp_baru;
        $types .= "s";
    }
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE pasien SET $set_fields WHERE id=?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Data pasien berhasil diperbarui.';
    } else {
        $_SESSION['error'] = 'Gagal: ' . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}
elseif ($action === 'add') {
    $kasus_id = (int)($_POST['kasus_id'] ?? 0);
    if (!$kasus_id) {
        $_SESSION['error'] = 'Kasus tidak valid.';
        header('Location: index.php');
        exit;
    }
    // Cek status kasus
    $stmt_cek = mysqli_prepare($koneksi, "SELECT status_terakhir, tim_id FROM kasus WHERE id = ?");
    mysqli_stmt_bind_param($stmt_cek, 'i', $kasus_id);
    mysqli_stmt_execute($stmt_cek);
    $kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
    mysqli_stmt_close($stmt_cek);
    if (!$kasus || in_array($kasus['status_terakhir'], ['selesai','dibatalkan'])) {
        $_SESSION['error'] = 'Kasus sudah selesai/dibatalkan.';
        header('Location: edit.php?kasus_id=' . $kasus_id);
        exit;
    }
    // Cek akses tim_respon
    if ($role === 'tim_respon' && (!$tim_user_id || $kasus['tim_id'] != $tim_user_id)) {
        $_SESSION['error'] = 'Anda tidak memiliki akses.';
        header('Location: index.php');
        exit;
    }
    
    $foto_pasien_baru = upload_foto('foto_pasien', 'pasien');
    $foto_ktp_baru = upload_foto('foto_ktp', 'ktp');
    
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $umur = ($_POST['umur'] !== '') ? (int)$_POST['umur'] : null;
    $jenis_kelamin = in_array($_POST['jenis_kelamin'] ?? '', ['L','P']) ? $_POST['jenis_kelamin'] : null;
    $kondisi_awal = trim($_POST['kondisi_awal'] ?? '');
    if (empty($kondisi_awal)) {
        $_SESSION['error'] = 'Kondisi awal wajib diisi.';
        header('Location: edit.php?kasus_id=' . $kasus_id);
        exit;
    }
    
    $stmt = mysqli_prepare($koneksi, "INSERT INTO pasien (kasus_id, nama_pasien, nik, umur, jenis_kelamin, kondisi_awal, foto_pasien, foto_ktp, diinput_oleh, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    mysqli_stmt_bind_param($stmt, 'issississi', $kasus_id, $nama_pasien, $nik, $umur, $jenis_kelamin, $kondisi_awal, $foto_pasien_baru, $foto_ktp_baru, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Pasien berhasil ditambahkan.';
    } else {
        $_SESSION['error'] = 'Gagal: ' . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}
elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
     if ($_SESSION['role_id'] != 1) {
         echo json_encode(['status' => 'error', 'message' => 'Hanya admin yang dapat menghapus data pasien.']);
        exit;
    }
    if (!cek_akses_pasien($koneksi, $id, $role, $tim_user_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
        exit;
    }
    $stmt = mysqli_prepare($koneksi, "DELETE FROM pasien WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Data dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
else {
    header('Location: index.php');
    exit;
}
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$nama_role = $_SESSION['nama_role'] ?? '';
if (!in_array($nama_role, ['admin', 'koordinator'])) {
    header('Location: ../dashboard.php');
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

// ========== TAMBAH ==========
if ($action == 'tambah' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama']);
    $kode = trim($_POST['kode']);
    $deskripsi = trim($_POST['deskripsi']);

    if (empty($nama)) {
        $_SESSION['error'] = "Nama jenis kasus wajib diisi.";
        header('Location: tambah.php');
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO jenis_kasus (nama, kode, deskripsi) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sss', $nama, $kode, $deskripsi);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Jenis kasus berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

// ========== EDIT ==========
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $nama = trim($_POST['nama']);
    $kode = trim($_POST['kode']);
    $deskripsi = trim($_POST['deskripsi']);

    if (empty($nama)) {
        $_SESSION['error'] = "Nama jenis kasus wajib diisi.";
        header("Location: edit.php?id=$id");
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE jenis_kasus SET nama=?, kode=?, deskripsi=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssi', $nama, $kode, $deskripsi, $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Jenis kasus berhasil diupdate.";
    } else {
        $_SESSION['error'] = "Gagal update: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

// ========== HAPUS ==========
if ($action == 'hapus' && $id > 0) {
    // Cek apakah jenis kasus sudah digunakan di tabel kasus
    $cek = mysqli_prepare($koneksi, "SELECT id FROM kasus WHERE jenis_kasus_id = ? LIMIT 1");
    mysqli_stmt_bind_param($cek, 'i', $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);
    if (mysqli_stmt_num_rows($cek) > 0) {
        $_SESSION['error'] = "Jenis kasus tidak bisa dihapus karena sudah digunakan dalam kasus.";
        header('Location: index.php');
        exit;
    }
    mysqli_stmt_close($cek);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM jenis_kasus WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Jenis kasus berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal hapus: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
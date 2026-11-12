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
    $kode = trim($_POST['kode']);
    $label = trim($_POST['label']);
    $deskripsi = trim($_POST['deskripsi']);
    $urgensi = (int)$_POST['urgensi'];
    $warna_hex = trim($_POST['warna_hex']);

    if (empty($kode) || empty($label) || $urgensi <= 0) {
        $_SESSION['error'] = "Kode, label, dan urgensi wajib diisi.";
        header('Location: tambah.php');
        exit;
    }

    // Cek duplikat kode
    $cek = mysqli_prepare($koneksi, "SELECT id FROM triase WHERE kode = ?");
    mysqli_stmt_bind_param($cek, 's', $kode);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);
    if (mysqli_stmt_num_rows($cek) > 0) {
        $_SESSION['error'] = "Kode triase sudah digunakan.";
        header('Location: tambah.php');
        exit;
    }
    mysqli_stmt_close($cek);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO triase (kode, label, deskripsi, urgensi, warna_hex) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sssis', $kode, $label, $deskripsi, $urgensi, $warna_hex);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Triase berhasil ditambahkan.";
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
    $kode = trim($_POST['kode']);
    $label = trim($_POST['label']);
    $deskripsi = trim($_POST['deskripsi']);
    $urgensi = (int)$_POST['urgensi'];
    $warna_hex = trim($_POST['warna_hex']);

    if (empty($kode) || empty($label) || $urgensi <= 0) {
        $_SESSION['error'] = "Kode, label, dan urgensi wajib diisi.";
        header("Location: edit.php?id=$id");
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE triase SET kode=?, label=?, deskripsi=?, urgensi=?, warna_hex=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssisi', $kode, $label, $deskripsi, $urgensi, $warna_hex, $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Triase berhasil diupdate.";
    } else {
        $_SESSION['error'] = "Gagal update: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

// ========== HAPUS ==========
if ($action == 'hapus' && $id > 0) {
    // Cek apakah triase sudah digunakan di kasus
    $cek = mysqli_prepare($koneksi, "SELECT id FROM kasus WHERE triase_id = ? LIMIT 1");
    mysqli_stmt_bind_param($cek, 'i', $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);
    if (mysqli_stmt_num_rows($cek) > 0) {
        $_SESSION['error'] = "Triase tidak bisa dihapus karena sudah digunakan dalam kasus.";
        header('Location: index.php');
        exit;
    }
    mysqli_stmt_close($cek);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM triase WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Triase berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal hapus: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
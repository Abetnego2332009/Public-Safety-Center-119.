<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1,3])) {
    header('Location: ../dashboard.php');
    exit;
}

// ========== TAMBAH TIM ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_tim'])) {
    $kode_tim   = mysqli_real_escape_string($koneksi, $_POST['kode_tim']);
    $nama_tim   = mysqli_real_escape_string($koneksi, $_POST['nama_tim']);
    $deskripsi  = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $is_aktif   = (int)$_POST['is_aktif'];

    if (empty($kode_tim) || empty($nama_tim)) {
        echo "<script>alert('Kode tim dan nama tim wajib diisi.'); window.location='tambah.php';</script>";
        exit;
    }

    $query = "INSERT INTO tim (kode_tim, nama_tim, deskripsi, is_aktif, created_at, updated_at) 
              VALUES ('$kode_tim', '$nama_tim', '$deskripsi', $is_aktif, NOW(), NOW())";
    
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Tim berhasil ditambahkan ✅'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah tim: " . mysqli_error($koneksi) . "'); window.location='tambah.php';</script>";
    }

// ========== UPDATE TIM ==========
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tim'])) {
    $id         = (int)$_POST['id'];
    $kode_tim   = mysqli_real_escape_string($koneksi, $_POST['kode_tim']);
    $nama_tim   = mysqli_real_escape_string($koneksi, $_POST['nama_tim']);
    $deskripsi  = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $is_aktif   = (int)$_POST['is_aktif'];

    $query = "UPDATE tim SET 
              kode_tim = '$kode_tim', 
              nama_tim = '$nama_tim', 
              deskripsi = '$deskripsi', 
              is_aktif = $is_aktif, 
              updated_at = NOW() 
              WHERE id = $id";

    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Tim berhasil diupdate ✅'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Gagal update: " . mysqli_error($koneksi) . "'); window.location='edit.php?id=$id';</script>";
    }

// ========== HAPUS TIM ==========
} elseif (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id <= 0) {
        echo "<script>alert('ID tidak valid.'); window.location='index.php';</script>";
        exit;
    }

    // Cek apakah tim memiliki relasi (misal di anggota_tim atau jadwal_shift)
    $cek = mysqli_query($koneksi, "SELECT id FROM anggota_tim WHERE tim_id = $id LIMIT 1");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Tim tidak bisa dihapus karena masih memiliki anggota tim.'); window.location='index.php';</script>";
        exit;
    }
    $cek2 = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tim_id = $id LIMIT 1");
    if (mysqli_num_rows($cek2) > 0) {
        echo "<script>alert('Tim tidak bisa dihapus karena masih memiliki jadwal shift.'); window.location='index.php';</script>";
        exit;
    }

    $query = "DELETE FROM tim WHERE id = $id";
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Tim berhasil dihapus.'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Gagal hapus: " . mysqli_error($koneksi) . "'); window.location='index.php';</script>";
    }
} else {
    header('Location: index.php');
    exit;
}
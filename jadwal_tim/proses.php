<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1, 3])) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['id'];

// ... di atas (cek session dan role)

// ========== TAMBAH JADWAL RENTANG (Satu Minggu / Periode) ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_range'])) {
    $tgl_awal   = mysqli_real_escape_string($koneksi, $_POST['tgl_awal']);
    $tgl_akhir  = mysqli_real_escape_string($koneksi, $_POST['tgl_akhir']);
    $tim_id     = (int)$_POST['tim_id'];
    $shift_id   = (int)$_POST['shift_definisi_id'];
    $catatan    = mysqli_real_escape_string($koneksi, $_POST['catatan']);

    if (empty($tgl_awal) || empty($tgl_akhir) || $tim_id <= 0 || $shift_id <= 0) {
        echo "<script>alert('Semua field wajib diisi.'); window.location='tambah_range.php';</script>";
        exit;
    }



    $start = new DateTime($tgl_awal);
    $end   = new DateTime($tgl_akhir);
    if ($start > $end) {
        echo "<script>alert('Tanggal awal harus lebih kecil atau sama dengan tanggal akhir.'); window.location='tambah_range.php';</script>";
        exit;
    }

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

    $success_count = 0;
    $error_list = [];

    foreach ($period as $date) {
        $tanggal = $date->format('Y-m-d');

        // Cek duplikat berdasarkan tanggal dan shift (bukan tim)
        $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND shift_definisi_id = $shift_id");
        if (mysqli_num_rows($cek) > 0) {
            $error_list[] = "Jadwal untuk tanggal $tanggal shift ini sudah ada.";
            continue;
        }
        // Cek duplikat
        $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND tim_id = $tim_id");
        if (mysqli_num_rows($cek) > 0) {
            $error_list[] = "Jadwal untuk tim pada tanggal $tanggal sudah ada.";
            continue;
        }

        $query = "INSERT INTO jadwal_shift (tim_id, shift_definisi_id, tanggal, dibuat_oleh, catatan, created_at, updated_at)
                  VALUES ($tim_id, $shift_id, '$tanggal', $user_id, '$catatan', NOW(), NOW())";
        if (mysqli_query($koneksi, $query)) {
            $success_count++;
        } else {
            $error_list[] = "Gagal insert tanggal $tanggal: " . mysqli_error($koneksi);
        }
    }

    if ($success_count > 0) {
        echo "<script>alert('✅ Berhasil menambahkan $success_count jadwal.'); window.location='index.php?tgl=" . $tgl_awal . "';</script>";
    } else {
        $error_msg = implode('\n', $error_list);
        echo "<script>alert('❌ Gagal menambah semua jadwal:\n$error_msg'); window.location='tambah_range.php';</script>";
    }
    exit;
}



// ========== TAMBAH JADWAL ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jadwal'])) {
    $tanggal          = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $tim_id           = (int)$_POST['tim_id'];
    $shift_definisi_id = (int)$_POST['shift_definisi_id'];
    $catatan          = mysqli_real_escape_string($koneksi, $_POST['catatan']);

    if (empty($tanggal) || $tim_id <= 0 || $shift_definisi_id <= 0) {
        echo "<script>alert('Semua field wajib diisi.'); window.location='tambah.php';</script>";
        exit;
    }

    // Cek duplikat (tim dan tanggal yang sama)
    $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND tim_id = $tim_id");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Jadwal untuk tim ini pada tanggal $tanggal sudah ada.'); window.location='tambah.php';</script>";
        exit;
    }

    // Cek duplikat berdasarkan tanggal dan shift (bukan tim)
    $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND shift_definisi_id = $shift_definisi_id");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Jadwal untuk shift ini pada tanggal $tanggal sudah ada oleh tim lain.'); window.location='tambah.php';</script>";
        exit;
    }

    $query = "INSERT INTO jadwal_shift (tim_id, shift_definisi_id, tanggal, dibuat_oleh, catatan, created_at, updated_at)
              VALUES ($tim_id, $shift_definisi_id, '$tanggal', $user_id, '$catatan', NOW(), NOW())";
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Jadwal berhasil ditambahkan ✅'); window.location='index.php?tanggal=$tanggal';</script>";
    } else {
        echo "<script>alert('Gagal menambah: " . mysqli_error($koneksi) . "'); window.location='tambah.php';</script>";
    }

    // ========== UPDATE JADWAL ==========
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_jadwal'])) {
    $id               = (int)$_POST['id'];
    $tanggal          = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $tim_id           = (int)$_POST['tim_id'];
    $shift_definisi_id = (int)$_POST['shift_definisi_id'];
    $catatan          = mysqli_real_escape_string($koneksi, $_POST['catatan']);

    // Cek duplikat (kecuali id sendiri)
    $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND tim_id = $tim_id AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Jadwal untuk tim ini pada tanggal $tanggal sudah ada.'); window.location='edit.php?id=$id';</script>";
        exit;
    }

    $cek = mysqli_query($koneksi, "SELECT id FROM jadwal_shift WHERE tanggal = '$tanggal' AND shift_definisi_id = $shift_definisi_id AND id != $id");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Jadwal untuk shift ini pada tanggal $tanggal sudah ada.'); window.location='edit.php?id=$id';</script>";
        exit;
    }

    $query = "UPDATE jadwal_shift SET 
              tim_id = $tim_id, 
              shift_definisi_id = $shift_definisi_id, 
              tanggal = '$tanggal', 
              catatan = '$catatan', 
              updated_at = NOW()
              WHERE id = $id";
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Jadwal berhasil diupdate ✅'); window.location='index.php?tanggal=$tanggal';</script>";
    } else {
        echo "<script>alert('Gagal update: " . mysqli_error($koneksi) . "'); window.location='edit.php?id=$id';</script>";
    }

    // ========== HAPUS JADWAL ==========
} elseif (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id <= 0) {
        echo "<script>alert('ID tidak valid.'); window.location='index.php';</script>";
        exit;
    }
    // Ambil tanggal untuk redirect
    $q = mysqli_query($koneksi, "SELECT tanggal FROM jadwal_shift WHERE id = $id");
    $row = mysqli_fetch_assoc($q);
    $tgl = $row ? $row['tanggal'] : date('Y-m-d');

    $query = "DELETE FROM jadwal_shift WHERE id = $id";
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Jadwal berhasil dihapus.'); window.location='index.php?tanggal=$tgl';</script>";
    } else {
        echo "<script>alert('Gagal hapus: " . mysqli_error($koneksi) . "'); window.location='index.php';</script>";
    }
} else {
    header('Location: index.php');
    exit;
}

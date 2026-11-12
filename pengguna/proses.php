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
// ========== TAMBAH ==========
if ($action == 'tambah' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $username   = trim($_POST['username']);
    $password   = password_hash('123456', PASSWORD_DEFAULT);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email      = trim($_POST['email']);
    $no_telp    = trim($_POST['no_telp']);
    $role_id    = (int)$_POST['role_id'];
    $is_aktif   = isset($_POST['is_aktif']) ? 1 : 0;

    // Data untuk anggota tim (hanya jika role = 4)
    $tim_id = isset($_POST['tim_id']) ? (int)$_POST['tim_id'] : 0;
    $jabatan = isset($_POST['jabatan']) ? $_POST['jabatan'] : '';

    // Validasi: jika role = 4, tim_id dan jabatan wajib
    if ($role_id == 4 && ($tim_id <= 0 || empty($jabatan))) {
        $_SESSION['error'] = "Untuk user tim respon, wajib pilih tim dan jabatan.";
        header('Location: tambah.php');
        exit;
    }

    // Cek username unik
    $cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($cek, 's', $username);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);
    if (mysqli_stmt_num_rows($cek) > 0) {
        $_SESSION['error'] = "Username sudah digunakan!";
        header('Location: tambah.php');
        exit;
    }
    mysqli_stmt_close($cek);

    mysqli_begin_transaction($koneksi);
    try {
        // Insert ke users
        $sql = "INSERT INTO users (username, password, nama_lengkap, email, no_telp, role_id, is_aktif) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssii', $username, $password, $nama_lengkap, $email, $no_telp, $role_id, $is_aktif);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);

        // Jika role = tim_respon, masukkan ke anggota_tim
        if ($role_id == 4 && $tim_id > 0 && !empty($jabatan)) {
            // Cek apakah user sudah memiliki keanggotaan aktif
            $cek_anggota = mysqli_prepare($koneksi, "SELECT id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL");
            mysqli_stmt_bind_param($cek_anggota, 'i', $user_id);
            mysqli_stmt_execute($cek_anggota);
            mysqli_stmt_store_result($cek_anggota);
            if (mysqli_stmt_num_rows($cek_anggota) > 0) {
                throw new Exception("User sudah memiliki keanggotaan tim aktif.");
            }
            mysqli_stmt_close($cek_anggota);

            $tgl_masuk = date('Y-m-d');
            $sql_anggota = "INSERT INTO anggota_tim (tim_id, user_id, jabatan, tgl_masuk) VALUES (?, ?, ?, ?)";
            $stmt_anggota = mysqli_prepare($koneksi, $sql_anggota);
            mysqli_stmt_bind_param($stmt_anggota, 'iiss', $tim_id, $user_id, $jabatan, $tgl_masuk);
            mysqli_stmt_execute($stmt_anggota);
            mysqli_stmt_close($stmt_anggota);
        }

        mysqli_commit($koneksi);
        $_SESSION['success'] = "Pengguna berhasil ditambahkan. Password default: 123456";
        header('Location: index.php');
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error'] = "Gagal menambahkan: " . $e->getMessage();
        header('Location: tambah.php');
    }
    exit;
}

// ========== EDIT ==========
// ========== EDIT ==========
if ($action == 'edit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $no_telp = trim($_POST['no_telp']);
    $role_id = (int)$_POST['role_id'];
    $is_aktif = isset($_POST['is_aktif']) ? 1 : 0;

    // Data untuk anggota tim (hanya jika role = 4)
    $tim_id = isset($_POST['tim_id']) ? (int)$_POST['tim_id'] : 0;
    $jabatan = isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '';

    // Validasi: jika role baru = 4 (tim_respon), maka tim_id dan jabatan wajib
    if ($role_id == 4 && ($tim_id <= 0 || empty($jabatan))) {
        $_SESSION['error'] = "Untuk user tim respon, wajib pilih tim dan jabatan.";
        header("Location: edit.php?id=$id");
        exit;
    }

    mysqli_begin_transaction($koneksi);
    try {
        // Update data user
        $sql = "UPDATE users SET nama_lengkap=?, email=?, no_telp=?, role_id=?, is_aktif=? WHERE id=?";
        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, 'sssiii', $nama_lengkap, $email, $no_telp, $role_id, $is_aktif, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Ambil role lama user (sebelum update) dari database
        $sql_old = "SELECT role_id FROM users WHERE id = ? FOR UPDATE";
        $stmt_old = mysqli_prepare($koneksi, $sql_old);
        mysqli_stmt_bind_param($stmt_old, 'i', $id);
        mysqli_stmt_execute($stmt_old);
        $res_old = mysqli_stmt_get_result($stmt_old);
        $old_role = mysqli_fetch_assoc($res_old)['role_id'];
        mysqli_stmt_close($stmt_old);

        // Cek apakah user saat ini memiliki anggota tim aktif
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL LIMIT 1");
        mysqli_stmt_bind_param($stmt_cek, 'i', $id);
        mysqli_stmt_execute($stmt_cek);
        $ada_anggota = mysqli_stmt_num_rows($stmt_cek) > 0;
        mysqli_stmt_close($stmt_cek);

        // Kasus 1: role baru = tim_respon (4)
        if ($role_id == 4) {
            if ($ada_anggota) {
                // Update anggota tim yang ada
                $sql_upd = "UPDATE anggota_tim SET tim_id = ?, jabatan = ? WHERE user_id = ? AND tgl_keluar IS NULL";
                $stmt_upd = mysqli_prepare($koneksi, $sql_upd);
                mysqli_stmt_bind_param($stmt_upd, 'isi', $tim_id, $jabatan, $id);
                mysqli_stmt_execute($stmt_upd);
                mysqli_stmt_close($stmt_upd);
            } else {
                // Insert baru ke anggota_tim
                $tgl_masuk = date('Y-m-d');
                $sql_ins = "INSERT INTO anggota_tim (tim_id, user_id, jabatan, tgl_masuk) VALUES (?, ?, ?, ?)";
                $stmt_ins = mysqli_prepare($koneksi, $sql_ins);
                mysqli_stmt_bind_param($stmt_ins, 'iiss', $tim_id, $id, $jabatan, $tgl_masuk);
                mysqli_stmt_execute($stmt_ins);
                mysqli_stmt_close($stmt_ins);
            }
        } 
        // Kasus 2: role baru BUKAN tim_respon, tetapi user sebelumnya punya anggota tim aktif
        else {
            if ($ada_anggota) {
                // Akhiri keanggotaan tim dengan mengisi tgl_keluar = hari ini
                $tgl_keluar = date('Y-m-d');
                $sql_end = "UPDATE anggota_tim SET tgl_keluar = ? WHERE user_id = ? AND tgl_keluar IS NULL";
                $stmt_end = mysqli_prepare($koneksi, $sql_end);
                mysqli_stmt_bind_param($stmt_end, 'si', $tgl_keluar, $id);
                mysqli_stmt_execute($stmt_end);
                mysqli_stmt_close($stmt_end);
            }
        }

        mysqli_commit($koneksi);
        $_SESSION['success'] = "Data pengguna berhasil diupdate";
        header('Location: index.php');
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error'] = "Gagal update: " . $e->getMessage();
        header("Location: edit.php?id=$id");
    }
    exit;
}

// ========== RESET PASSWORD ==========
if ($action == 'reset_pass' && $id > 0) {
    $new_pass = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_pass, $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Password berhasil direset menjadi <strong>123456</strong>";
    } else {
        $_SESSION['error'] = "Gagal mereset password";
    }
    header('Location: index.php');
    exit;
}

// ========== TOGGLE STATUS ==========
if ($action == 'toggle' && $id > 0) {
    if ($id == $_SESSION['id']) {
        $_SESSION['error'] = "Tidak bisa mengubah status sendiri!";
    } else {
        $stmt = mysqli_prepare($koneksi, "SELECT is_aktif FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        $new_status = $user['is_aktif'] ? 0 : 1;
        $stmt2 = mysqli_prepare($koneksi, "UPDATE users SET is_aktif = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, 'ii', $new_status, $id);
        mysqli_stmt_execute($stmt2);
        $_SESSION['success'] = "Status pengguna berhasil diubah";
    }
    header('Location: index.php');
    exit;
}

// ========== HAPUS ==========
if ($action == 'hapus' && $id > 0) {
    if ($id == $_SESSION['id']) {
        $_SESSION['error'] = "Tidak bisa menghapus akun sendiri!";
        header('Location: index.php');
        exit;
    }

    mysqli_begin_transaction($koneksi);
    try {
        // Hapus data anggota_tim terlebih dahulu (jika ada)
        $stmt1 = mysqli_prepare($koneksi, "DELETE FROM anggota_tim WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt1, 'i', $id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        // Hapus user
        $stmt2 = mysqli_prepare($koneksi, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt2, 'i', $id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        mysqli_commit($koneksi);
        $_SESSION['success'] = "Pengguna berhasil dihapus.";
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error'] = "Gagal menghapus pengguna: " . $e->getMessage();
    }
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
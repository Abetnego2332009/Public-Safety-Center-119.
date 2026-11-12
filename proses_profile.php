<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}
require_once 'koneksi.php';

$user_id = $_SESSION['id'];

// Fungsi refresh session user
function refreshUserSession($koneksi, $user_id) {
    $stmt = mysqli_prepare($koneksi, "SELECT nama_lengkap, foto_profil FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['foto_profil'] = $user['foto_profil'];
}

// ========== HAPUS FOTO PROFIL ==========
if (isset($_GET['action']) && $_GET['action'] == 'hapus_foto' && isset($_GET['user_id'])) {
    $user_id_get = (int)$_GET['user_id'];
    if ($user_id_get != $_SESSION['id']) {
        $_SESSION['error'] = "Akses ditolak.";
        header('Location: profile.php');
        exit;
    }

    // Ambil nama file foto lama
    $stmt = mysqli_prepare($koneksi, "SELECT foto_profil FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id_get);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!empty($user['foto_profil'])) {
        $file_path = __DIR__ . '/uploads/profil/' . $user['foto_profil'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt_upd = mysqli_prepare($koneksi, "UPDATE users SET foto_profil = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, 'i', $user_id_get);
        mysqli_stmt_execute($stmt_upd);
        mysqli_stmt_close($stmt_upd);
        
        // Refresh session foto profil
        refreshUserSession($koneksi, $user_id_get);
        
        $_SESSION['success'] = "Foto profil berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Tidak ada foto profil yang dihapus.";
    }
    header('Location: profile.php');
    exit;
}

// ========== UPDATE PROFIL ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $no_telp = trim($_POST['no_telp']);
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    $errors = [];
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap wajib diisi.";

    // Proses upload foto
    $foto_profil = null;
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $errors[] = "Format foto harus JPG atau PNG.";
        } elseif ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 2MB.";
        } else {
            $upload_dir = __DIR__ . '/uploads/profil/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $nama_file = 'user_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_dir . $nama_file)) {
                $foto_profil = $nama_file; // hanya simpan nama file, bukan full path
            } else {
                $errors[] = "Gagal mengupload foto. Periksa izin folder.";
            }
        }
    }

    // Cek password jika ingin ganti
    if (!empty($password_lama) || !empty($password_baru) || !empty($konfirmasi)) {
        $stmt = mysqli_prepare($koneksi, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!password_verify($password_lama, $user['password'])) {
            $errors[] = "Password lama salah.";
        } elseif (strlen($password_baru) < 4) {
            $errors[] = "Password baru minimal 4 karakter.";
        } elseif ($password_baru !== $konfirmasi) {
            $errors[] = "Konfirmasi password baru tidak cocok.";
        } else {
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $update_pass = true;
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: profile.php');
        exit;
    }

    // Update data user
    mysqli_begin_transaction($koneksi);
    try {
        $sql = "UPDATE users SET nama_lengkap=?, email=?, no_telp=?";
        $params = [$nama_lengkap, $email, $no_telp];
        $types = "sss";

        if ($foto_profil) {
            $sql .= ", foto_profil=?";
            $params[] = $foto_profil;
            $types .= "s";
        }
        if (isset($update_pass) && $update_pass === true) {
            $sql .= ", password=?";
            $params[] = $password_hash;
            $types .= "s";
        }
        $sql .= " WHERE id=?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = mysqli_prepare($koneksi, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($koneksi);
        
        // Refresh session
        refreshUserSession($koneksi, $user_id);
        
        $_SESSION['success'] = "Profil berhasil diperbarui.";
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error'] = "Gagal update profil: " . $e->getMessage();
    }
    header('Location: profile.php');
    exit;
}

header('Location: profile.php');
exit;
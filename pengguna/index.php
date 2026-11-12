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

$title = 'Manajemen Pengguna & Role';
require '../template/header.php';
require '../template/sidebar.php';

// Pesan flash
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Filter & pencarian
$filter_role = isset($_GET['role']) ? (int)$_GET['role'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT u.*, r.nama as nama_role 
        FROM users u 
        JOIN roles r ON r.id = u.role_id 
        WHERE 1=1";
$params = [];
$types = "";

if ($filter_role > 0) {
    $sql .= " AND u.role_id = ?";
    $params[] = $filter_role;
    $types .= "i";
}
if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.nama_lengkap LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}
$sql .= " ORDER BY u.id DESC";

$stmt = mysqli_prepare($koneksi, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengguna = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$res_role = mysqli_query($koneksi, "SELECT * FROM roles ORDER BY id");
$roles = mysqli_fetch_all($res_role, MYSQLI_ASSOC);
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>

    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">👥 Pengguna & Role</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="tambah.php" class="btn btn-sm btn-neutral">
                            <i class="fas fa-plus"></i> Tambah Pengguna
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--6">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header border-0">
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="mb-0">Daftar Pengguna</h3>
                    </div>
                    <div class="col-md-6 text-right">
                        <form method="GET" class="form-inline justify-content-end">
                            <select name="role" class="form-control form-control-sm mr-2">
                                <option value="0">Semua Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $filter_role == $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Cari nama/username/email" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-items-center table-flush">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pengguna) == 0): ?>
                            <tr>
                                <td colspan="8" class="text-center">Tidak ada data</td>
                            <tr>
                            <?php endif; ?>
                            <?php foreach ($pengguna as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($p['username']) ?></td>
                                <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['no_telp'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['nama_role']) ?></td>
                                <td>
                                    <?php if ($p['is_aktif'] == 1): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                                    <a href="proses.php?action=reset_pass&id=<?= $p['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Reset password?')">Reset</a>
                                    <a href="proses.php?action=toggle&id=<?= $p['id'] ?>"
                                        class="btn btn-sm <?= $p['is_aktif'] == 1 ? 'btn-success' : 'btn-danger' ?>">

                                        <?= $p['is_aktif'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                                    </a>
                                    <?php if ($p['id'] != $_SESSION['id']): ?>
                                        <a href="proses.php?action=hapus&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda Ingin Menghapus?')">Hapus</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require '../template/footer.php'; ?>
    </div>
</div>

<!-- CSS untuk memastikan sidebar tidak menabrak konten -->
<style>
    .main-content {
        margin-left: 260px !important;
        width: auto !important;
        position: relative !important;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0 !important;
        }
    }
</style>
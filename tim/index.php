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

$title = 'Data Tim';
require '../template/header.php';
require '../template/sidebar.php';

// Ambil semua tim
$query = "SELECT * FROM tim ORDER BY id";
$result = mysqli_query($koneksi, $query);
$tim_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-users mr-2"></i> Data Tim</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="tambah.php" class="btn btn-sm btn-neutral"><i class="fas fa-plus mr-1"></i> Tambah Tim</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">Daftar Tim</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr><th>ID</th><th>Kode Tim</th><th>Nama Tim</th><th>Deskripsi</th><th>Status</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tim_list)): ?>
                                    <tr><td colspan="6" class="text-center">Belum ada tim.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tim_list as $t): ?>
                                    <tr>
                                        <td><?= $t['id'] ?></td>
                                        <td><?= htmlspecialchars($t['kode_tim']) ?></td>
                                        <td><?= htmlspecialchars($t['nama_tim']) ?></td>
                                        <td><?= htmlspecialchars($t['deskripsi'] ?? '-') ?></td>
                                        <td><?= $t['is_aktif'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Nonaktif</span>' ?></td>
                                        <td>
                                            <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i>Edit</a>
                                            <a href="proses.php?hapus=<?= $t['id'] ?>" onclick="return confirm('Yakin hapus tim <?= $t['nama_tim'] ?>? Data terkait (anggota tim, jadwal shift) tidak akan terhapus otomatis.')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Hapus </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
<style>.main-content { margin-left: 260px; } @media (max-width:768px){ .main-content{ margin-left:0; } }</style>
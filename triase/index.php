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

$title = 'Master Triase';
require '../template/header.php';
require '../template/sidebar.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM triase ORDER BY urgensi ASC";
if (!empty($search)) {
    $sql = "SELECT * FROM triase WHERE kode LIKE '%$search%' OR label LIKE '%$search%' ORDER BY urgensi ASC";
}
$result = mysqli_query($koneksi, $sql);
$triase_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-chart-pie mr-2"></i> Master Triase</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="tambah.php" class="btn btn-sm btn-neutral">
                            <i class="fas fa-plus"></i> Tambah Triase
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow"><?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow"><?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header border-0">
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="mb-0">Daftar Triase</h3>
                    </div>
                    <div class="col-md-6 text-right">
                        <form method="GET" class="form-inline justify-content-end">
                            <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Cari kode/label" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Cari</button>
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
                            <th>Kode</th>
                            <th>Label</th>
                            <th>Urgensi</th>
                            <th>Warna</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($triase_list)): ?>
                            <tr><td colspan="7" class="text-center">Tidak ada data</td></tr>
                        <?php endif; ?>
                        <?php foreach ($triase_list as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['kode']) ?></td>
                                <td><?= htmlspecialchars($t['label']) ?></td>
                                <td><?= $t['urgensi'] ?> (<?= $t['urgensi'] == 1 ? 'Tertinggi' : ($t['urgensi'] == 4 ? 'Terendah' : 'Sedang') ?>)</td>
                               <td><span style="display:inline-block; width:40px; height:30px; padding:5px 10px; background:<?= htmlspecialchars($t['warna_hex']) ?>; border-radius:6px; border:1px solid #ddd;"></span></td>
                                <td><?= nl2br(htmlspecialchars($t['deskripsi'] ?? '')) ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="proses.php?action=hapus&id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus triase ini? Data kasus tidak akan terhapus.')">Hapus</a>
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

<style>.main-content { margin-left: 260px; } @media (max-width:768px){ .main-content{ margin-left:0; } }</style>
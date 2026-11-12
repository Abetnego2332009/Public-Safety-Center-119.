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

$title = 'Jenis Kasus';
require '../template/header.php';
require '../template/sidebar.php';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM jenis_kasus ORDER BY nama ASC";
if (!empty($search)) {
    $sql = "SELECT * FROM jenis_kasus WHERE nama LIKE '%$search%' OR kode LIKE '%$search%' ORDER BY nama ASC";
}
$result = mysqli_query($koneksi, $sql);
$jenis_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-ambulance mr-2"></i> Jenis Kasus</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="tambah.php" class="btn btn-sm btn-neutral">
                            <i class="fas fa-plus"></i> Tambah Jenis Kasus
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--6">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-header border-0">
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="mb-0">Daftar Jenis Kasus</h3>
                    </div>
                    <div class="col-md-6 text-right">
                        <form method="GET" class="form-inline justify-content-end">
                            <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Cari nama/kode" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Reset</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-items-center table-flush">
                    <thead class="thead-light">
                        <tr><th>ID</th><th>Nama</th><th>Kode</th><th>Deskripsi</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jenis_list)): ?>
                            <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        <?php endif; ?>
                        <?php foreach ($jenis_list as $j): ?>
                            <tr>
                                <td><?= $j['id'] ?></td>
                                <td><?= htmlspecialchars($j['nama']) ?></td>
                                <td><?= htmlspecialchars($j['kode']) ?></td>
                                <td><?= nl2br(htmlspecialchars($j['deskripsi'] ?? '')) ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                                    <a href="proses.php?action=hapus&id=<?= $j['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus jenis kasus ini? Data terkait di kasus tidak akan terhapus (hanya referensi).')">Hapus</a>
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
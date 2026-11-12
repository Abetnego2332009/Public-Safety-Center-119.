<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
$nama_role = $_SESSION['nama_role'] ?? '';
if (!in_array($nama_role, ['admin', 'koordinator'])) {
    header('Location: ../dashboard.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$id = (int)$_GET['id'];
if (!$id) {
    header('Location: index.php');
    exit;
}
$stmt = mysqli_prepare($koneksi, "SELECT * FROM jenis_kasus WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$jenis = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$jenis) {
    $_SESSION['error'] = "Jenis kasus tidak ditemukan.";
    header('Location: index.php');
    exit;
}

$title = 'Edit Jenis Kasus';
require '../template/header.php';
require '../template/sidebar.php';
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
        <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">

        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-edit mr-2"></i> Edit Jenis Kasus</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--6">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form action="proses.php?action=edit" method="POST">
                            <input type="hidden" name="id" value="<?= $jenis['id'] ?>">
                            <div class="form-group">
                                <label>Nama Jenis Kasus <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($jenis['nama']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Kode</label>
                                <input type="text" name="kode" class="form-control" value="<?= htmlspecialchars($jenis['kode'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="deskripsi" rows="3" class="form-control"><?= htmlspecialchars($jenis['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<style>.main-content { margin-left: 260px; } @media (max-width:768px){ .main-content{ margin-left:0; } }</style>
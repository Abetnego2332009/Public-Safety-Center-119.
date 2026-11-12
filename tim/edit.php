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

$id = (int)$_GET['id'];
if ($id <= 0) {
    echo "<script>alert('ID tim tidak valid.'); window.location='index.php';</script>";
    exit;
}

$query = "SELECT * FROM tim WHERE id = $id";
$result = mysqli_query($koneksi, $query);
$tim = mysqli_fetch_assoc($result);
if (!$tim) {
    echo "<script>alert('Tim tidak ditemukan.'); window.location='index.php';</script>";
    exit;
}

$title = 'Edit Tim';
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-edit mr-2"></i> Edit Tim</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" action="proses.php">
                            <input type="hidden" name="id" value="<?= $tim['id'] ?>">
                            <div class="form-group">
                                <label>Kode Tim</label>
                                <input type="text" name="kode_tim" class="form-control" value="<?= htmlspecialchars($tim['kode_tim']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nama Tim</label>
                                <input type="text" name="nama_tim" class="form-control" value="<?= htmlspecialchars($tim['nama_tim']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($tim['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="is_aktif" class="form-control">
                                    <option value="1" <?= $tim['is_aktif'] == 1 ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= $tim['is_aktif'] == 0 ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                            <button type="submit" name="update_tim" class="btn btn-primary">Update</button>
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
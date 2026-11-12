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

$id = (int)$_GET['id'];
if (!$id) {
    header('Location: index.php');
    exit;
}
$stmt = mysqli_prepare($koneksi, "SELECT * FROM triase WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$triase = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$triase) {
    $_SESSION['error'] = "Triase tidak ditemukan.";
    header('Location: index.php');
    exit;
}

$title = 'Edit Triase';
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-edit mr-2"></i> Edit Triase</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form action="proses.php?action=edit" method="POST">
                            <input type="hidden" name="id" value="<?= $triase['id'] ?>">
                            <div class="form-group">
                                <label>Kode <span class="text-danger">*</span></label>
                                <input type="text" name="kode" class="form-control" value="<?= htmlspecialchars($triase['kode']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Label <span class="text-danger">*</span></label>
                                <input type="text" name="label" class="form-control" value="<?= htmlspecialchars($triase['label']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Urgensi <span class="text-danger">*</span></label>
                                <select name="urgensi" class="form-control" required>
                                    <option value="">-- Pilih Tingkat Urgensi --</option>
                                    <option value="1" <?= $triase['urgensi'] == 1 ? 'selected' : '' ?>>1 - Tertinggi (Merah)</option>
                                    <option value="2" <?= $triase['urgensi'] == 2 ? 'selected' : '' ?>>2 - Sedang Tinggi (Kuning)</option>
                                    <option value="3" <?= $triase['urgensi'] == 3 ? 'selected' : '' ?>>3 - Sedang Rendah (Hijau)</option>
                                    <option value="4" <?= $triase['urgensi'] == 4 ? 'selected' : '' ?>>4 - Terendah (Hitam)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Warna Hex <span class="text-danger">*</span></label>
                                <input type="color" name="warna_hex" class="form-control" value="<?= htmlspecialchars($triase['warna_hex']) ?>" style="height: 50px;">
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="deskripsi" rows="3" class="form-control"><?= htmlspecialchars($triase['deskripsi'] ?? '') ?></textarea>
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
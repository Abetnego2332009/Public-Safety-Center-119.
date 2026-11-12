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
$title = 'Tambah Triase';
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-plus-circle mr-2"></i> Tambah Triase</h6>
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
                        <form action="proses.php?action=tambah" method="POST">
                            <div class="form-group">
                                <label>Kode <span class="text-danger">*</span></label>
                                <input type="text" name="kode" class="form-control" required placeholder="Contoh: P1, P2, P3, P4">
                                <small class="text-muted">Kode unik untuk triase.</small>
                            </div>
                            <div class="form-group">
                                <label>Label <span class="text-danger">*</span></label>
                                <input type="text" name="label" class="form-control" required placeholder="Contoh: Merah, Kuning, Hijau, Hitam">
                            </div>
                            <div class="form-group">
                                <label>Urgensi <span class="text-danger">*</span></label>
                                <select name="urgensi" class="form-control" required>
                                    <option value="">-- Pilih Tingkat Urgensi --</option>
                                    <option value="1">1 - Tertinggi (Merah)</option>
                                    <option value="2">2 - Sedang Tinggi (Kuning)</option>
                                    <option value="3">3 - Sedang Rendah (Hijau)</option>
                                    <option value="4">4 - Terendah (Hitam)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Warna Hex <span class="text-danger">*</span></label>
                                <input type="color" name="warna_hex" class="form-control" value="#DC2626" style="height: 50px;">
                                <small class="text-muted">Pilih warna untuk tampilan UI.</small>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <textarea name="deskripsi" rows="3" class="form-control" placeholder="Keterangan triase"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan</button>
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
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

$tim_list = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id, nama_tim, kode_tim FROM tim WHERE is_aktif = 1"), MYSQLI_ASSOC);
$shift_list = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id, kode_shift, label FROM shift_definisi ORDER BY jam_mulai"), MYSQLI_ASSOC);

// Default: minggu ini (Senin - Minggu)
$hari_ini = date('Y-m-d');
$hari_berakhir = date('Y-m-d', strtotime('+6 days'));

$title = 'Tambah Jadwal (Satu Minggu)';
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-calendar-plus mr-2"></i> Tambah Jadwal (Periode)</h6>
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
                            <input type="hidden" name="action" value="tambah_range">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Isi tanggal awal dan akhir untuk membuat jadwal otomatis setiap hari dalam rentang tersebut.
                            </div>
                            <div class="form-group">
                                <label>Tanggal Awal <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_awal" class="form-control" value="<?= $hari_ini ?>" required>
                                <small class="text-muted">Contoh: Senin, 10 Mei 2026</small>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Akhir <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_akhir" class="form-control" value="<?= $hari_berakhir?>" required>
                                <small class="text-muted">Contoh: Minggu, 16 Mei 2026</small>
                            </div>
                            <div class="form-group">
                                <label>Pilih Tim <span class="text-danger">*</span></label>
                                <select name="tim_id" class="form-control" required>
                                    <option value="">-- Pilih Tim --</option>
                                    <?php foreach ($tim_list as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama_tim']) ?> (<?= $t['kode_tim'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pilih Shift <span class="text-danger">*</span></label>
                                <select name="shift_definisi_id" class="form-control" required>
                                    <option value="">-- Pilih Shift --</option>
                                    <?php foreach ($shift_list as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['label']) ?> (<?= $s['kode_shift'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Catatan (opsional)</label>
                                <textarea name="catatan" class="form-control" rows="3" placeholder="Catatan untuk semua jadwal dalam periode ini..."></textarea>
                                <small class="text-muted">Catatan yang sama akan diterapkan ke semua hari.</small>
                            </div>
                            <button type="submit" name="simpan_range" class="btn btn-primary">Simpan Jadwal (Sekaligus)</button>
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
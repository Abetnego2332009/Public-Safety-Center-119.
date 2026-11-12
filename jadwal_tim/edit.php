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
    echo "<script>alert('ID tidak valid.'); window.location='index.php';</script>";
    exit;
}

$query = "SELECT * FROM jadwal_shift WHERE id = $id";
$result = mysqli_query($koneksi, $query);
$jadwal = mysqli_fetch_assoc($result);
if (!$jadwal) {
    echo "<script>alert('Jadwal tidak ditemukan.'); window.location='index.php';</script>";
    exit;
}

// Daftar tim dan shift
$tim_query = "SELECT id, nama_tim, kode_tim FROM tim WHERE is_aktif = 1 ORDER BY id";
$tim_result = mysqli_query($koneksi, $tim_query);
$tim_list = mysqli_fetch_all($tim_result, MYSQLI_ASSOC);

$shift_query = "SELECT id, kode_shift, label FROM shift_definisi ORDER BY jam_mulai";
$shift_result = mysqli_query($koneksi, $shift_query);
$shift_list = mysqli_fetch_all($shift_result, MYSQLI_ASSOC);

$title = 'Edit Jadwal Shift';
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-edit mr-2"></i> Edit Jadwal Shift</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php?tanggal=<?= $jadwal['tanggal'] ?>" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
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
                            <input type="hidden" name="id" value="<?= $jadwal['id'] ?>">
                            <div class="form-group">
                                <label>Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= $jadwal['tanggal'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tim</label>
                                <select name="tim_id" class="form-control" required>
                                    <?php foreach ($tim_list as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $t['id'] == $jadwal['tim_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['nama_tim']) ?> (<?= $t['kode_tim'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Shift</label>
                                <select name="shift_definisi_id" class="form-control" required>
                                    <?php foreach ($shift_list as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $jadwal['shift_definisi_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['label']) ?> (<?= $s['kode_shift'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Catatan</label>
                                <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($jadwal['catatan'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="update_jadwal" class="btn btn-primary">Update</button>
                            <a href="index.php?tanggal=<?= $jadwal['tanggal'] ?>" class="btn btn-secondary">Batal</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
<style>.main-content { margin-left: 260px; } @media (max-width:768px){ .main-content{ margin-left:0; } }</style>
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';
$title = 'Form Pasien';
require '../template/header.php';
require '../template/sidebar.php';

$id_pasien = (int)($_GET['id'] ?? 0);
$kasus_id = (int)($_GET['kasus_id'] ?? 0);
$role = $_SESSION['nama_role'] ?? '';
$tim_user_id = $_SESSION['tim_id'] ?? null;

$is_edit = ($id_pasien > 0);
$pasien = null;
$kasus = null;

if ($is_edit) {
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM pasien WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_pasien);
    mysqli_stmt_execute($stmt);
    $pasien = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$pasien) {
        $_SESSION['error'] = 'Data pasien tidak ditemukan.';
        header('Location: index.php');
        exit;
    }
    $kasus_id = $pasien['kasus_id'];
}

// Ambil data kasus
$stmt_k = mysqli_prepare($koneksi, "SELECT id, nomor_kasus, status_terakhir, tim_id FROM kasus WHERE id = ?");
mysqli_stmt_bind_param($stmt_k, 'i', $kasus_id);
mysqli_stmt_execute($stmt_k);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_k));
mysqli_stmt_close($stmt_k);
if (!$kasus) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Cek akses
if ($role === 'tim_respon') {
    if (!$tim_user_id || $kasus['tim_id'] != $tim_user_id) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke pasien ini.';
        header('Location: index.php');
        exit;
    }
}

$kasus_final = in_array($kasus['status_terakhir'], ['selesai','dibatalkan']);
$disabled = $kasus_final ? 'disabled' : '';
$warning_msg = '';
if ($kasus_final) {
    $warning_msg = '<div class="alert alert-danger">⚠️ Kasus sudah ' . ($kasus['status_terakhir']=='selesai'?'selesai':'dibatalkan') . '. Tidak bisa mengubah atau menambah data pasien.</div>';
}
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-warning pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-user-edit mr-2"></i> <?= $is_edit ? 'Edit' : 'Tambah' ?> Pasien</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <button onclick="window.print()" class="btn btn-sm btn-neutral"><i class="fas fa-print mr-1"></i> Cetak</button>
                        <a href="index.php" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-xl-8 mb-5">
                <?= $warning_msg ?>
                <div class="card shadow">
                    <div class="card-body">
                        <form action="proses.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'add' ?>">
                            <?php if ($is_edit): ?>
                                <input type="hidden" name="id" value="<?= $pasien['id'] ?>">
                            <?php else: ?>
                                <input type="hidden" name="kasus_id" value="<?= $kasus_id ?>">
                            <?php endif; ?>
                            
                            <div class="alert alert-info">
                                <strong>Kasus:</strong> <?= htmlspecialchars($kasus['nomor_kasus']); ?><br>
                                <strong>Status Kasus:</strong> <?= ucfirst(str_replace('_',' ',$kasus['status_terakhir'])); ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Nama Pasien</label>
                                <input type="text" name="nama_pasien" class="form-control" value="<?= htmlspecialchars($pasien['nama_pasien'] ?? '') ?>" <?= $disabled ?>>
                            </div>
                            <div class="form-group">
                                <label>NIK (16 digit)</label>
                                <input type="text" name="nik" class="form-control" maxlength="16" value="<?= htmlspecialchars($pasien['nik'] ?? '') ?>" <?= $disabled ?>>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Umur (tahun)</label>
                                        <input type="number" name="umur" class="form-control" min="0" max="150" value="<?= $pasien['umur'] ?? '' ?>" <?= $disabled ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Jenis Kelamin</label>
                                        <select name="jenis_kelamin" class="form-control" <?= $disabled ?>>
                                            <option value="">-- Pilih --</option>
                                            <option value="L" <?= (($pasien['jenis_kelamin']??'')=='L')?'selected':'' ?>>Laki-laki</option>
                                            <option value="P" <?= (($pasien['jenis_kelamin']??'')=='P')?'selected':'' ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Kondisi Awal <span class="text-danger">*</span></label>
                                <textarea name="kondisi_awal" rows="4" class="form-control" required <?= $disabled ?>><?= htmlspecialchars($pasien['kondisi_awal'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- ================= UPLOAD FOTO ================= -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Foto Pasien</label>
                                        <?php if (!empty($pasien['foto_pasien'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?= htmlspecialchars($pasien['foto_pasien']) ?>" style="max-width:150px" class="img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="foto_pasien" class="form-control-file" accept="image/jpeg,image/png,image/jpg" <?= $disabled ?>>
                                        <small class="text-muted">Kosongkan jika tidak ingin mengubah. Maks 2MB (JPG/PNG).</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Foto KTP</label>
                                        <?php if (!empty($pasien['foto_ktp'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?= htmlspecialchars($pasien['foto_ktp']) ?>" style="max-width:150px" class="img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="foto_ktp" class="form-control-file" accept="image/jpeg,image/png,image/jpg" <?= $disabled ?>>
                                        <small class="text-muted">Kosongkan jika tidak ingin mengubah. Maks 2MB (JPG/PNG).</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">Batal</a>
                                <?php if (!$kasus_final): ?>
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary" disabled>Kasus Selesai/Dibatalkan</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
<style media="print">
    .sidebar, .navbar, .header, .btn, .card-footer, form .d-flex { display: none; }
    .main-content { margin: 0; padding: 0; }
    .card { border: none; }
</style>
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1,3])) { // hanya admin (1) atau koordinator (3)
    die("Akses ditolak.");
}

$kasus_id = (int)($_GET['id'] ?? 0);
if ($kasus_id <= 0) {
    $_SESSION['error'] = 'ID kasus tidak valid.';
    header('Location: index.php');
    exit;
}

// Ambil data kasus
$sql_kasus = "SELECT id, nomor_kasus, status_terakhir, tim_penanggung_jawab_id FROM kasus WHERE id = ?";
$stmt = mysqli_prepare($koneksi, $sql_kasus);
mysqli_stmt_bind_param($stmt, 'i', $kasus_id);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$kasus) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Jika kasus sudah selesai/dibatalkan, tidak boleh di-takeover
if (in_array($kasus['status_terakhir'], ['selesai','dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah selesai/dibatalkan, tidak bisa di-takeover.';
    header('Location: index.php');
    exit;
}

$tim_aktif = $kasus['tim_penanggung_jawab_id'];

// Ambil daftar tim yang aktif dan bukan tim penanggung jawab saat ini
$sql_tim = "SELECT id, nama_tim, kode_tim FROM tim WHERE is_aktif = 1 AND id != ? ORDER BY nama_tim";
$stmt_tim = mysqli_prepare($koneksi, $sql_tim);
mysqli_stmt_bind_param($stmt_tim, 'i', $tim_aktif);
mysqli_stmt_execute($stmt_tim);
$tim_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_tim), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_tim);

$title = 'Alihkan Kasus - Admin';
require '../template/header.php';
require '../template/sidebar.php';
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-warning pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-8 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">Alihkan Kasus (Takeover) — <?= htmlspecialchars($kasus['nomor_kasus']) ?></h6>
                    </div>
                    <div class="col-lg-4 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral shadow rounded-pill px-3">← Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-xl-6 mx-auto">
                <div class="card shadow">
                    <div class="card-body">
                        <form action="takeover_proses_admin.php" method="POST">
                            <input type="hidden" name="kasus_id" value="<?= $kasus_id ?>">
                            
                            <div class="alert alert-info">
                                <strong>Kasus:</strong> <?= htmlspecialchars($kasus['nomor_kasus']) ?><br>
                                <strong>Status saat ini:</strong> <?= ucfirst(str_replace('_',' ',$kasus['status_terakhir'])) ?><br>
                                <strong>Tim penanggung jawab saat ini:</strong> 
                                <?php 
                                    $sql_tim_now = "SELECT nama_tim FROM tim WHERE id = ?";
                                    $stmt_now = mysqli_prepare($koneksi, $sql_tim_now);
                                    mysqli_stmt_bind_param($stmt_now, 'i', $kasus['tim_penanggung_jawab_id']);
                                    mysqli_stmt_execute($stmt_now);
                                    $tim_now = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_now));
                                    mysqli_stmt_close($stmt_now);
                                    echo htmlspecialchars($tim_now['nama_tim'] ?? 'Tidak diketahui');
                                ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Alihkan ke Tim <span class="text-danger">*</span></label>
                                <select name="tim_tujuan" class="form-control" required>
                                    <option value="">-- Pilih Tim Tujuan --</option>
                                    <?php foreach ($tim_list as $tm): ?>
                                        <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['nama_tim']) ?> (<?= htmlspecialchars($tm['kode_tim']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Anda dapat memilih tim mana pun (tidak harus shift aktif).</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Alasan Takeover <span class="text-danger">*</span></label>
                                <textarea name="alasan" rows="3" class="form-control" required placeholder="Contoh: Tim penanggung jawab saat ini tidak bisa melanjutkan, atau koordinasi shift, dll."></textarea>
                            </div>
                            
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-warning">Alihkan Kasus</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
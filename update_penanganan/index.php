<?php
// ============================================================
//  FILE    : update_penanganan/index.php
//  FUNGSI  : Daftar kasus aktif untuk diupdate oleh tim bertugas
// ============================================================
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$user_id = $_SESSION['id'];

// Hanya tim yang bisa mengedit kasus sesuai aturan (tidak perlu shift aktif)
// Tapi kita tetap tampilkan daftar kasus yang bisa di-edit oleh user ini.
// Untuk itu kita query kasus yang memenuhi canUserEditKasus.
// Atau lebih sederhana: tampilkan semua kasus aktif, lalu beri tombol edit jika canUserEditKasus true.

$title = 'Update Penanganan - PSC 119';
require '../template/header.php';
require '../template/sidebar.php';

// Ambil semua kasus aktif (belum selesai/dibatalkan)
$sql = "SELECT k.id, k.nomor_kasus, k.status_terakhir, k.lokasi_kejadian, k.nama_pelapor,
               t.label as triase_label, t.warna_hex, t.urgensi,
               tm.nama_tim
        FROM kasus k
        JOIN triase t ON t.id = k.triase_id
        LEFT JOIN tim tm ON tm.id = k.tim_id
        WHERE k.status_terakhir NOT IN ('selesai','dibatalkan')
        ORDER BY t.urgensi ASC, k.waktu_kejadian ASC";
$result = mysqli_query($koneksi, $sql);
$kasus_list = mysqli_fetch_all($result, MYSQLI_ASSOC);

function badge_status($status)
{
    $map = [
        'menunggu_respon' => 'secondary',
        'menuju_lokasi' => 'info',
        'tiba_di_lokasi' => 'primary',
        'penanganan_di_tempat' => 'warning',
        'dirujuk_ke_rs' => 'info',
        'selesai' => 'success',
        'dibatalkan' => 'danger',
    ];
    $label_map = [
        'menunggu_respon' => 'Menunggu Respon',
        'menuju_lokasi' => 'Menuju Lokasi',
        'tiba_di_lokasi' => 'Tiba di Lokasi',
        'penanganan_di_tempat' => 'Penanganan',
        'dirujuk_ke_rs' => 'Dirujuk ke RS',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    $kelas = $map[strtolower($status)] ?? 'secondary';
    $label = $label_map[strtolower($status)] ?? $status;
    return "<span class=\"badge badge-pill badge-{$kelas}\">{$label}</span>";
}
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-danger pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <h2 class="text-white">Update Penanganan Kasus</h2>
                <p class="text-white">Pilih kasus yang akan diperbarui status, foto, atau rujukannya.</p>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="card shadow">
            <div class="card-header">
                <h3>Daftar Kasus Aktif (Belum Selesai)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($kasus_list)) : ?>
                    <div class="alert alert-light">Tidak ada kasus aktif saat ini.</div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No. Kasus</th>
                                    <th>Pelapor</th>
                                    <th>Lokasi</th>
                                    <th>Triase</th>
                                    <th>Tim Bertugas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 3): ?>
                                        <th>Takeover</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kasus_list as $k) :
                                    $boleh_edit = canUserEditKasus($koneksi, $user_id, $k['id']);
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($k['nomor_kasus']) ?></td>
                                        <td><?= htmlspecialchars($k['nama_pelapor']) ?></td>
                                        <td><?= htmlspecialchars($k['lokasi_kejadian']) ?></td>
                                        <td><span class="badge" style="background:<?= htmlspecialchars($k['warna_hex']) ?>;color:#fff"><?= htmlspecialchars($k['triase_label']) ?></span></td>
                                        <td><?= $k['nama_tim'] ? htmlspecialchars($k['nama_tim']) : '<span class="text-muted">Belum ditugaskan</span>' ?></td>
                                        <td><?= badge_status($k['status_terakhir']) ?></td>
                                        <td>
                                            <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 3): ?>
                                        <td>
                                            <a href="takeover_form.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-exchange-alt"></i> Alihkan Kasus
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($boleh_edit) : ?>
                                        <a href="edit.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-primary">Update Penanganan</a>
                                    <?php else : ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Tidak Bisa Update</button>
                                    <?php endif; ?>
                                    </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php require '../template/footer.php'; ?>
</div>
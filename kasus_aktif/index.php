<?php
// ============================================================
//  FILE    : kasus_aktif/index.php
//  SISTEM  : PSC 119 — Daftar Kasus Aktif
//  VERSI   : 1.2.0 (Tombol Edit diganti Update Penanganan)
// ============================================================
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';
$title = 'Kasus Aktif';
require '../template/header.php';
require '../template/sidebar.php';

$user_id = $_SESSION['id'] ?? 0;

// Tentukan shift aktif
$jam_sekarang = (int) date('H');
if ($jam_sekarang >= 8 && $jam_sekarang < 14) {
    $shift_default = 'PAGI';
} elseif ($jam_sekarang >= 14 && $jam_sekarang < 20) {
    $shift_default = 'SIANG';
} else {
    $shift_default = 'MALAM';
}
$filter_shift = trim($_GET['shift'] ?? $shift_default);
$shift_valid = ['PAGI', 'SIANG', 'MALAM'];
if (!in_array($filter_shift, $shift_valid)) {
    $filter_shift = $shift_default;
}
$filter_status = trim($_GET['status'] ?? '');

// Ambil tim yang bertugas pada shift ini
$stmt_tim = mysqli_prepare($koneksi, "
    SELECT t.id AS tim_id, t.kode_tim, t.nama_tim
    FROM jadwal_shift js
    JOIN tim t ON t.id = js.tim_id
    JOIN shift_definisi sd ON sd.id = js.shift_definisi_id
    WHERE js.tanggal = CURDATE() AND sd.kode_shift = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt_tim, 's', $filter_shift);
mysqli_stmt_execute($stmt_tim);
$tim_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tim));
mysqli_stmt_close($stmt_tim);

// Query kasus aktif
$where_parts = ["k.status_terakhir NOT IN ('selesai','dibatalkan')"];
$bind_types = '';
$bind_params = [];

if ($tim_aktif) {
    // Jika shift aktif, tampilkan kasus yang ditugaskan ke tim aktif atau belum ditugaskan
    $where_parts[] = "(k.tim_id = ? OR k.tim_id IS NULL)";
    $bind_types .= 'i';
    $bind_params[] = $tim_aktif['tim_id'];
}
if ($filter_status !== '') {
    $where_parts[] = "k.status_terakhir = ?";
    $bind_types .= 's';
    $bind_params[] = $filter_status;
}
$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

$sql_kasus = "
    SELECT
        k.id AS id_kasus,
        k.nomor_kasus AS no_kasus,
        k.status_terakhir AS status,
        k.tim_id,
        k.nama_pelapor,
        k.no_telp_pelapor,
        k.lokasi_kejadian,
        k.waktu_kejadian AS waktu_laporan,
        jk.kode AS kode_jenis,
        jk.nama AS nama_jenis,
        t.kode AS kode_triase,
        t.label AS nama_triase,
        t.warna_hex AS warna_triase,
        tm.nama_tim,
        tm.kode_tim,
        u.nama_lengkap AS nama_operator,
        TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, NOW()) AS menit_berlalu
    FROM kasus k
    JOIN jenis_kasus jk ON jk.id = k.jenis_kasus_id
    JOIN triase t ON t.id = k.triase_id
    JOIN users u ON u.id = k.operator_id
    LEFT JOIN tim tm ON tm.id = k.tim_id
    {$where_sql}
    ORDER BY t.urgensi ASC, k.waktu_kejadian ASC
";

$stmt_kasus = mysqli_prepare($koneksi, $sql_kasus);
if (!empty($bind_params)) {
    mysqli_stmt_bind_param($stmt_kasus, $bind_types, ...$bind_params);
}
mysqli_stmt_execute($stmt_kasus);
$daftar_kasus = mysqli_fetch_all(mysqli_stmt_get_result($stmt_kasus), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_kasus);

// Statistik
$sql_stat = "
    SELECT
        SUM(CASE WHEN k.status_terakhir = 'menunggu_respon' THEN 1 ELSE 0 END) AS menunggu_respon,
        SUM(CASE WHEN k.status_terakhir = 'menuju_lokasi' THEN 1 ELSE 0 END) AS menuju_lokasi,
        SUM(CASE WHEN k.status_terakhir = 'tiba_di_lokasi' THEN 1 ELSE 0 END) AS tiba_di_lokasi,
        SUM(CASE WHEN k.status_terakhir = 'penanganan_di_tempat' THEN 1 ELSE 0 END) AS penanganan,
        SUM(CASE WHEN k.status_terakhir = 'dirujuk_ke_rs' THEN 1 ELSE 0 END) AS dirujuk,
        COUNT(*) AS total_aktif
    FROM kasus k
    WHERE k.status_terakhir NOT IN ('selesai','dibatalkan')
";
$stat = mysqli_fetch_assoc(mysqli_query($koneksi, $sql_stat)) ?? [];

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
    $icon_map = [
        'menunggu_respon' => 'fa-phone-alt',
        'menuju_lokasi' => 'fa-ambulance',
        'tiba_di_lokasi' => 'fa-map-marker-alt',
        'penanganan_di_tempat' => 'fa-hand-holding-medical',
        'dirujuk_ke_rs' => 'fa-hospital',
        'selesai' => 'fa-check-circle',
        'dibatalkan' => 'fa-times-circle',
    ];
    $kelas = $map[strtolower($status)] ?? 'secondary';
    $label = $label_map[strtolower($status)] ?? $status;
    $ikon = $icon_map[strtolower($status)] ?? 'fa-circle';
    return "<span class=\"badge badge-pill badge-{$kelas}\"><i class=\"fas {$ikon} mr-1\"></i>{$label}</span>";
}

// Cek apakah user saat ini sedang bertugas (shift aktif)
// $can_edit = isTimBertugasSekarang($koneksi, $user_id);

function label_shift($shift)
{
    $map = ['PAGI' => 'Shift Pagi (08:00-14:00)', 'SIANG' => 'Shift Siang (14:00-20:00)', 'MALAM' => 'Shift Malam (20:00-08:00)'];
    return $map[$shift] ?? '—';
}
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-danger pb-8 pt-5 pt-md-8"> ... </div> <!-- bagian header tidak diubah -->
    <div class="container-fluid mt--7">
        <!-- Alert sukses/error -->
        <?php if (isset($_SESSION['success'])) : ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_SESSION['success']); ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
        <?php unset($_SESSION['success']);
        endif; ?>
        <?php if (isset($_SESSION['error'])) : ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['error']); ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
        <?php unset($_SESSION['error']);
        endif; ?>

        <div class="row">
            <div class="col-xl-9 mb-5">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center mb-3">
                            <div class="col">
                                <h3 class="mb-0"><i class="fas fa-fire mr-2 text-danger"></i> Kasus Aktif Hari Ini</h3>
                                <small class="text-muted">Menampilkan <strong><?= count($daftar_kasus); ?></strong> kasus aktif · <?= date('l, d F Y'); ?></small>
                            </div>
                        </div>
                        <!-- Form filter shift dan status (sama seperti sebelumnya) -->
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-lg-7 col-12 mb-2">
                                    <label class="small font-weight-bold mb-1"><i class="fas fa-clock mr-1"></i>Pilih Shift</label>
                                    <div class="btn-group btn-group-sm d-flex">
                                        <a href="?shift=PAGI<?= $filter_status ? '&status=' . $filter_status : ''; ?>" class="btn <?= $filter_shift === 'PAGI' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pagi</a>
                                        <a href="?shift=SIANG<?= $filter_status ? '&status=' . $filter_status : ''; ?>" class="btn <?= $filter_shift === 'SIANG' ? 'btn-primary' : 'btn-outline-primary'; ?>">Siang</a>
                                        <a href="?shift=MALAM<?= $filter_status ? '&status=' . $filter_status : ''; ?>" class="btn <?= $filter_shift === 'MALAM' ? 'btn-dark' : 'btn-outline-dark'; ?>">Malam</a>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-8 mb-2">
                                    <label class="small font-weight-bold mb-1"><i class="fas fa-filter mr-1"></i>Filter Status</label>
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="menunggu_respon" <?= $filter_status === 'menunggu_respon' ? 'selected' : ''; ?>>Menunggu Respon</option>
                                        <option value="menuju_lokasi" <?= $filter_status === 'menuju_lokasi' ? 'selected' : ''; ?>>Menuju Lokasi</option>
                                        <option value="tiba_di_lokasi" <?= $filter_status === 'tiba_di_lokasi' ? 'selected' : ''; ?>>Tiba di Lokasi</option>
                                        <option value="penanganan_di_tempat" <?= $filter_status === 'penanganan_di_tempat' ? 'selected' : ''; ?>>Penanganan</option>
                                        <option value="dirujuk_ke_rs" <?= $filter_status === 'dirujuk_ke_rs' ? 'selected' : ''; ?>>Dirujuk ke RS</option>
                                    </select>
                                    <input type="hidden" name="shift" value="<?= htmlspecialchars($filter_shift); ?>">
                                </div>
                                <div class="col-lg-2 col-4 mb-2">
                                    <a href="?shift=<?= $shift_default; ?>" class="btn btn-outline-secondary btn-sm btn-block">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>No. Kasus</th>
                                    <th>Pelapor</th>
                                    <th>Lokasi</th>
                                    <th class="text-center">Triase</th>
                                    <th class="text-center">Tim</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Waktu</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daftar_kasus)) : ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">Tidak ada kasus aktif pada <?= label_shift($filter_shift); ?> hari ini.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($daftar_kasus as $no => $k) :
                                        $menit = (int) $k['menit_berlalu'];
                                        $waktu_str = $menit < 60 ? $menit . ' mnt lalu' : floor($menit / 60) . 'j ' . ($menit % 60) . 'mnt lalu';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no + 1; ?></td>
                                            <!-- Nomor kasus tidak bisa diklik -->
                                            <td><span class="font-weight-bold" style="font-family:monospace;"><?= htmlspecialchars($k['no_kasus']); ?></span></td>
                                            <td><?= htmlspecialchars($k['nama_pelapor']); ?><br><small><?= htmlspecialchars($k['no_telp_pelapor']); ?></small><br><small class="text-muted"><?= htmlspecialchars($k['kode_jenis']); ?></small></td>
                                            <td><small><?= htmlspecialchars(substr($k['lokasi_kejadian'], 0, 30)); ?></small></td>
                                            <td class="text-center"><span class="badge badge-pill px-2 py-1" style="background:<?= htmlspecialchars($k['warna_triase'] ?? '#6c757d'); ?>; color:#fff;"><?= htmlspecialchars($k['nama_triase'] ?? $k['kode_triase']); ?></span></td>
                                            <td class="text-center"><?= $k['nama_tim'] ? '<span class="badge badge-warning">' . $k['nama_tim'] . '</span>' : '<span class="badge badge-light">Belum</span>'; ?></td>
                                            <td class="text-center"><?= badge_status($k['status']); ?></td>
                                            <td class="text-center"><small><?= $waktu_str; ?></small><br><small><?= date('H:i', strtotime($k['waktu_laporan'])); ?></small></td>
                                            <td class="text-center">
                                                <!-- Tombol Detail untuk semua user -->
                                                <a href="../laporan_masuk/detail_kasus.php?id=<?= $k['id_kasus']; ?>" class="btn btn-sm btn-outline-primary" title="Detail Kasus"><i class="fas fa-eye">Detail Kasus</i></a>
                                                <!-- Tombol Update Penanganan menggunakan canUserEditKasus per kasus -->
                                                <?php
                                                $boleh_edit = canUserEditKasus($koneksi, $user_id, $k['id_kasus']);
                                                if ($boleh_edit && !in_array($k['status'], ['selesai', 'dibatalkan'])) :
                                                ?>
                                                    <a href="../update_penanganan/edit.php?id=<?= $k['id_kasus']; ?>" class="btn btn-sm btn-outline-success" title="Update Penanganan"><i class="fas fa-hand-holding-medical">UPDATE Penanganan</i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 mb-5">
                <div class="card shadow mb-4">
                    <div class="card-header" style="background: linear-gradient(87deg, #f5365c 0, #f56036 100%);">
                        <h3 class="mb-0 text-white"><i class="fas fa-shield-alt mr-2"></i> Tim Bertugas</h3>
                        <small class="text-white"><?= label_shift($filter_shift); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if ($tim_aktif) : ?>
                            <div class="text-center mb-3">
                                <h4><?= htmlspecialchars($tim_aktif['nama_tim']); ?></h4>
                                <span class="badge badge-warning"><?= htmlspecialchars($tim_aktif['kode_tim']); ?></span>
                            </div>
                        <?php else : ?>
                            <p class="text-center text-muted">Belum ada tim dijadwalkan</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
<style>
    /* ===================================================== */
/* PERBAIKAN TABEL KASUS AKTIF - AGAR TIDAK SCROLL       */
/* ===================================================== */

/* Perkecil padding dan font */
.table td, .table th {
    padding: 0.4rem 0.3rem !important;
    font-size: 0.75rem !important;
    white-space: normal !important;
    word-break: break-word !important;
    vertical-align: middle !important;
}

/* Sembunyikan kolom kurang penting di layar kecil */
@media (max-width: 992px) {
    .table th:nth-child(3),  /* Pelapor */
    .table td:nth-child(3),
    .table th:nth-child(4),  /* Lokasi */
    .table td:nth-child(4) {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .table th:nth-child(5),  /* Triase */
    .table td:nth-child(5),
    .table th:nth-child(6),  /* Tim */
    .table td:nth-child(6),
    .table th:nth-child(7),  /* Status */
    .table td:nth-child(7) {
        display: none !important;
    }
    .table th:nth-child(8),  /* Waktu */
    .table td:nth-child(8) {
        display: none !important;
    }
}

/* Pastikan container tabel tidak overflow berlebihan */
.table-responsive {
    overflow-x: auto !important;
}

/* Tombol aksi diperkecil */
.btn-sm {
    padding: 0.2rem 0.4rem !important;
    font-size: 0.65rem !important;
}
</style>
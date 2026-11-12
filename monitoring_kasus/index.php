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

$title = 'Monitoring Kasus Aktif';
require '../template/header.php';
require '../template/sidebar.php';

$filter_status = $_GET['status'] ?? '';

$sql = "SELECT k.id, k.nomor_kasus, k.status_terakhir, k.lokasi_kejadian, k.nama_pelapor, 
               k.waktu_kejadian, k.created_at,
               t.label AS triase_label, t.warna_hex,
               tm.nama_tim,
               TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, NOW()) AS menit_berlalu
        FROM kasus k
        JOIN triase t ON t.id = k.triase_id
        LEFT JOIN tim tm ON tm.id = k.tim_id
        WHERE k.status_terakhir NOT IN ('selesai','dibatalkan')
        " . ($filter_status ? "AND k.status_terakhir = '$filter_status'" : "") . "
        ORDER BY t.urgensi ASC, k.waktu_kejadian ASC";

$kasus_list = mysqli_fetch_all(mysqli_query($koneksi, $sql), MYSQLI_ASSOC);

function badge_status($status) {
    $map = [
        'menunggu_respon' => 'secondary', 'menuju_lokasi' => 'info',
        'tiba_di_lokasi' => 'primary', 'penanganan_di_tempat' => 'warning',
        'dirujuk_ke_rs' => 'info', 'selesai' => 'success', 'dibatalkan' => 'danger'
    ];
    $label_map = [
        'menunggu_respon' => 'Menunggu Respon', 'menuju_lokasi' => 'Menuju Lokasi',
        'tiba_di_lokasi' => 'Tiba di Lokasi', 'penanganan_di_tempat' => 'Penanganan di Tempat',
        'dirujuk_ke_rs' => 'Dirujuk ke RS', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'
    ];
    $kelas = $map[strtolower($status)] ?? 'secondary';
    return "<span class='badge badge-pill badge-{$kelas}'>{$label_map[$status]}</span>";
}

// Tambahkan ini agar tidak error
$tanggal_cetak = date('d F Y');
$nama_pembuat  = $_SESSION['nama'] ?? 'Admin'; // Mengambil nama dari session
$jabatan_pembuat = 'Petugas Jaga';

// Ambil data user yang sedang login untuk keperluan pengesahan
$user_id = $_SESSION['id'];
$sql_user = "SELECT nama_lengkap, role_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($koneksi, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$nama_pembuat = $user_data['nama_lengkap'] ?? 'Administrator';

// Penentuan jabatan berdasarkan role_id
$role_id_user = $user_data['role_id'] ?? 0;
if ($role_id_user == 1) {
    $jabatan_pembuat = 'Administrator';
} elseif ($role_id_user == 2) {
    $jabatan_pembuat = 'Operator';
} elseif ($role_id_user == 3) {
    $jabatan_pembuat = 'Kepala Dinas';
} else {
    $jabatan_pembuat = 'Petugas PSC 119';
}
?>



<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <!-- HEADER UNTUK CETAK (HANYA MUNCUL SAAT PRINT) -->
<div class="print-header" style="display: none;">
    <div class="print-header-inner" style="text-align: center; margin-bottom: 20px;">
        <img src="<?= $base_url ?>assets/assets/img/Logo.png" alt="Logo PSC119" style="height: 70px;">
        <h2 style="margin: 10px 0 5px; font-weight: bold;">PSC 119</h2>
        <p style="margin: 0; font-size: 14px;">Sistem Penanggulangan Gawat Darurat</p>
        <p style="margin: 0; font-size: 12px; color: #666;">Dinas Kesehatan Kota Pematangsiantar</p>
        <hr style="margin: 10px 0; border: 1px solid #ccc;">
    </div>
</div>
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-tv mr-2"></i> Monitoring Kasus Aktif</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <button onclick="location.reload()" class="btn btn-sm btn-neutral"><i class="fas fa-sync-alt mr-1"></i> Refresh</button>
                        <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 10px; font-size: 10px; margin-left: 5px;">
                            <i class="fas fa-print mr-1"></i> Cetak Laporan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-0">Daftar Kasus Aktif</h3>
                            </div>
                            <div class="col text-right">
                                <form method="GET" class="form-inline float-right">
                                    <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="menunggu_respon" <?= $filter_status=='menunggu_respon'?'selected':'' ?>>Menunggu Respon</option>
                                        <option value="menuju_lokasi" <?= $filter_status=='menuju_lokasi'?'selected':'' ?>>Menuju Lokasi</option>
                                        <option value="tiba_di_lokasi" <?= $filter_status=='tiba_di_lokasi'?'selected':'' ?>>Tiba di Lokasi</option>
                                        <option value="penanganan_di_tempat" <?= $filter_status=='penanganan_di_tempat'?'selected':'' ?>>Penanganan di Tempat</option>
                                        <option value="dirujuk_ke_rs" <?= $filter_status=='dirujuk_ke_rs'?'selected':'' ?>>Dirujuk ke RS</option>
                                    </select>
                                    <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No. Kasus</th>
                                    <th>Pelapor / Waktu Lapor</th>
                                    <th>Lokasi Kejadian</th>
                                    <th>Triase</th>
                                    <th>Tim Bertugas</th>
                                    <th>Status</th>
                                    <th>Durasi (sejak kejadian)</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kasus_list)): ?>
                                    <tr><td colspan="9" class="text-center">Tidak ada kasus aktif saat ini</td></tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($kasus_list as $k): 
                                        $menit = (int)$k['menit_berlalu'];
                                        if ($menit < 60) $durasi = $menit . ' menit';
                                        elseif ($menit < 1440) $durasi = floor($menit/60) . ' jam ' . ($menit%60) . ' menit';
                                        else $durasi = floor($menit/1440) . ' hari';
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($k['nomor_kasus']) ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($k['nama_pelapor']) ?><br>
                                            <small class="text-muted">Lapor: <?= date('d-m-Y H:i', strtotime($k['created_at'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars(substr($k['lokasi_kejadian'], 0, 60)) ?><?= strlen($k['lokasi_kejadian'])>60?'...':'' ?></td>
                                        <td><span class="badge" style="background:<?= $k['warna_hex'] ?>;color:#fff"><?= $k['triase_label'] ?></span></td>
                                        <td><?= $k['nama_tim'] ?: '<span class="text-muted">Belum ditugaskan</span>' ?></td>
                                        <td><?= badge_status($k['status_terakhir']) ?></td>
                                        <td><?= $durasi ?></td>
                                        <td><a href="detail_kasus.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-info">Detail</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

       <!-- Ubah bagian ini -->
<div class="row legalization-section" style="display: none;">
    <!-- konten pengesahan Anda -->

            <div class="col-12">
                <div style="margin-top: 60px; margin-bottom: 30px;">
                    <div style="text-align: justify; margin-bottom: 40px; font-weight: bold;">
                        Pematangsiantar, <?= $tanggal_cetak ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <!-- Kiri -->
                        <div style="text-align: center; width: 45%;">
                            <div style="font-weight: bold; margin-bottom: 50px;">Pembuat Laporan,</div>
                            <div style="margin-top: 40px;">___________________</div>
                            <div style="margin-top: 8px; font-weight: bold;">(<?= htmlspecialchars($nama_pembuat) ?>)</div>
                            <div style="font-size: 11px; margin-top: 4px;">(<?= htmlspecialchars($jabatan_pembuat) ?>)</div>
                        </div>
                        
                        <!-- Kanan -->
                        <div style="text-align: center; width: 45%;">
                            <div style="font-weight: bold; margin-bottom: 50px;">Mengetahui,</div>
                            <div style="margin-top: 40px;">___________________</div>
                            <div style="margin-top: 8px; font-weight: bold;">(.................................)</div>
                            <div style="font-size: 11px; margin-top: 4px;">Pimpinan PSC 119</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<!-- CSS KHUSUS UNTUK CETAK (TIDAK MEMPENGARUHI LAYAR) -->
<style media="print">
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* DIPAKSA WARNA BACKGROUND BADGE TETAP MUNCUL */
    .triase-badge {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
        background-color: inherit !important;
    }
    
    /* SEMUA ELEMEN DENGAN BACKGROUND COLOR DIPAKSA MUNCUL */
    div, span, td, th, .badge, [style*="background-color"] {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* SEMUA TEKS MENJADI HITAM */
    body, .main-content, .container-fluid, .card, .card-body, 
table, th, td, div, span, p, h1, h2, h3, h4, h5, h6,
.card-title, .h2, .mb-0, .mt-3, .text-sm {
    color: #000000 !important;
    font-weight: bold !important;  /* ← Tambahkan baris ini */
}

    /* TABEL */
    table, th, td {
        border-color: #000000 !important;
        border-width: 1px !important;
        border-style: solid !important;
    }

    th, td {
        padding: 8px !important;
    }

    .table, .table-sm {
        border: 1px solid #000000 !important;
    }

    .table-sm th, .table-sm td {
        border: 1px solid #000000 !important;
    }

    /* CARD */
    .card {
        border: 1px solid #000000 !important;
        box-shadow: none !important;
    }

    .card-header {
        border-bottom: 1px solid #000000 !important;
    }

    /* SEMBUNYIKAN ELEMEN */
    .sidebar, .navbar, .header, .card-footer, button, .btn, .btn-secondary,
    .float-right, .text-right, form, .icon, .card-stats .col-auto {
        display: none !important;
    }

    body, .main-content, .container-fluid {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .col-md-6, .col-md-4 {
        width: 100% !important;
        float: none !important;
        margin-bottom: 15px;
    }

    /* GRAFIK */
    #triaseChart {
        display: block !important;
        max-width: 100% !important;
    }

    canvas {
        display: block !important;
        max-width: 100% !important;
        background: white !important;
    }

    /* PENGESAHAN */
    .legalization-section {
        display: block !important;
        margin-top: 60px !important;
        page-break-inside: avoid;
    }

    /* Tampilkan Header saat cetak */
.print-header {
    display: block !important;
}

/* Pastikan Tabel tidak terpotong */
.table-responsive {
    overflow: visible !important;
}

.table {
    width: 100% !important;
    border-collapse: collapse !important;
}

/* Hilangkan kolom aksi saat cetak */
th:last-child, td:last-child {
    display: none !important;
}
</style>
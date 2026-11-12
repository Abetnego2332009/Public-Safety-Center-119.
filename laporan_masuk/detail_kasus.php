<?php
ob_start(); // START OUTPUT BUFFERING (CEGAH HEADER ERROR)
// ============================================================
//  FILE    : laporan_masuk/detail_kasus.php
//  SISTEM  : PSC 119 — Detail Kasus (FIXED)
//  FUNGSI  : Menampilkan detail kasus, export, cetak
//  VERSI   : 2.0 (Akses tim_respon tanpa batasan tim)
// ============================================================
session_start();
require_once __DIR__ . '/../koneksi.php';

// Cek login
if (!isset($_SESSION['sudah_login']) || $_SESSION['sudah_login'] !== true || !isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

$id_kasus = (int) ($_GET['id'] ?? 0);
if ($id_kasus <= 0) {
    $_SESSION['error'] = 'ID kasus tidak valid.';
    header('Location: index.php');
    exit;
}

// Mode export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=kasus_{$id_kasus}.xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    require_once __DIR__ . '/export_excel_detail.php';
    exit;
}

$title = 'Detail Kasus';
require '../template/header.php';
require '../template/sidebar.php';

$id_pengguna_login = (int) $_SESSION['id'];
$id_role_login     = (int) ($_SESSION['role_id'] ?? 0);

// ============================================================
// OTORISASI TIM RESPON (role_id = 4)
// SEMUA TIM RESPON BOLEH LIHAT DETAIL KASUS (TANPA BATASAN TIM)
// ============================================================
if ($id_role_login === 4) {
    $stmt_cek_tim = mysqli_prepare($koneksi, "SELECT tim_id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL LIMIT 1");
    mysqli_stmt_bind_param($stmt_cek_tim, 'i', $id_pengguna_login);
    mysqli_stmt_execute($stmt_cek_tim);
    $id_tim_anggota = null;
    mysqli_stmt_bind_result($stmt_cek_tim, $id_tim_anggota);
    mysqli_stmt_fetch($stmt_cek_tim);
    mysqli_stmt_close($stmt_cek_tim);
    if ($id_tim_anggota === null) {
        $_SESSION['error'] = 'Anda belum terdaftar dalam tim manapun.';
        header('Location: index.php');
        exit;
    }
    // TIDAK ADA PENGECEKAN tim_id KASUS -> semua kasus boleh dilihat
}

// ============================================================
// QUERY DATA KASUS
// ============================================================
$stmt_kasus = mysqli_prepare($koneksi, "
    SELECT 
        k.id, k.nomor_kasus, k.nama_pelapor, k.no_telp_pelapor, 
        k.lokasi_kejadian, k.deskripsi, k.waktu_kejadian, 
        k.status_terakhir, k.waktu_selesai, k.catatan_penutup,
        jk.nama AS nama_jenis, jk.kode AS kode_jenis,
        tr.label AS nama_triase, tr.warna_hex AS warna_triase,
        t.id AS id_tim, t.nama_tim, t.kode_tim,
        sd.label AS shift_label, js.tanggal AS shift_tanggal,
        u.nama_lengkap AS nama_operator,
        (SELECT waktu FROM riwayat_status_kasus WHERE kasus_id = k.id AND status = 'tiba_di_lokasi' ORDER BY id ASC LIMIT 1) as waktu_tiba
    FROM kasus k
    JOIN jenis_kasus jk ON jk.id = k.jenis_kasus_id
    JOIN triase tr ON tr.id = k.triase_id
    JOIN users u ON u.id = k.operator_id
    LEFT JOIN tim t ON t.id = k.tim_id
    LEFT JOIN jadwal_shift js ON js.id = k.jadwal_shift_id
    LEFT JOIN shift_definisi sd ON sd.id = js.shift_definisi_id
    WHERE k.id = ?
");
mysqli_stmt_bind_param($stmt_kasus, 'i', $id_kasus);
mysqli_stmt_execute($stmt_kasus);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kasus));
mysqli_stmt_close($stmt_kasus);
if (!$kasus) {
    $_SESSION['error'] = 'Data kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Data pasien
$stmt_pasien = mysqli_prepare($koneksi, "
    SELECT id, nama_pasien, nik, umur, jenis_kelamin, kondisi_awal, foto_pasien, foto_ktp
    FROM pasien WHERE kasus_id = ?
");
mysqli_stmt_bind_param($stmt_pasien, 'i', $id_kasus);
mysqli_stmt_execute($stmt_pasien);
$daftar_pasien = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pasien), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_pasien);
$jumlah_pasien = count($daftar_pasien);

// Anggota tim bertugas (hanya untuk info)
$daftar_anggota = [];
if ($kasus['id_tim']) {
    $stmt_anggota = mysqli_prepare($koneksi, "
        SELECT u.nama_lengkap, a.jabatan
        FROM anggota_tim a
        JOIN users u ON u.id = a.user_id
        WHERE a.tim_id = ? AND a.tgl_keluar IS NULL
        ORDER BY FIELD(a.jabatan, 'dokter', 'perawat', 'bidan', 'operator_lapangan', 'supir')
    ");
    mysqli_stmt_bind_param($stmt_anggota, 'i', $kasus['id_tim']);
    mysqli_stmt_execute($stmt_anggota);
    $daftar_anggota = mysqli_fetch_all(mysqli_stmt_get_result($stmt_anggota), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_anggota);
}

// Helper functions
function badge_status(string $status): string {
    $map = [
        'menunggu_respon'      => ['badge-secondary', 'Menunggu Respon'],
        'menuju_lokasi'        => ['badge-info',      'Menuju Lokasi'],
        'tiba_di_lokasi'       => ['badge-primary',   'Tiba di Lokasi'],
        'penanganan_di_tempat' => ['badge-warning',   'Penanganan di Tempat'],
        'dirujuk_ke_rs'        => ['badge-warning',   'Dirujuk ke RS'],
        'selesai'              => ['badge-success',   'Selesai'],
        'dibatalkan'           => ['badge-danger',    'Dibatalkan'],
    ];
    [$kelas, $label] = $map[$status] ?? ['badge-light', $status];
    return "<span class=\"badge badge-pill {$kelas} px-3 py-2\">{$label}</span>";
}

function label_jk(?string $jk): string {
    return match ($jk) {
        'L' => '<i class="fas fa-mars mr-1 text-primary"></i>Laki-laki',
        'P' => '<i class="fas fa-venus mr-1 text-pink"></i>Perempuan',
        default => '<i class="fas fa-question-circle mr-1 text-muted"></i>Belum Teridentifikasi',
    };
}

function icon_status(string $status): string {
    $map = [
        'menunggu_respon'      => ['fas fa-phone-alt',     '#6c757d'],
        'menuju_lokasi'        => ['fas fa-ambulance',      '#17a2b8'],
        'tiba_di_lokasi'       => ['fas fa-map-marker-alt', '#007bff'],
        'penanganan_di_tempat' => ['fas fa-medkit',         '#ffc107'],
        'dirujuk_ke_rs'        => ['fas fa-hospital',       '#fd7e14'],
        'selesai'              => ['fas fa-check-circle',   '#28a745'],
        'dibatalkan'           => ['fas fa-times-circle',   '#dc3545'],
    ];
    [$ikon, $warna] = $map[$status] ?? ['fas fa-circle', '#6c757d'];
    return "<span style=\"color:{$warna}; font-size:1.1rem;\"><i class=\"{$ikon}\"></i></span>";
}

// Hitung response time dan durasi
$durasi_str = '—';
if ($kasus['waktu_kejadian'] && $kasus['waktu_selesai']) {
    $menit_total = (int) ((strtotime($kasus['waktu_selesai']) - strtotime($kasus['waktu_kejadian'])) / 60);
    $jam = intdiv($menit_total, 60);
    $menit = $menit_total % 60;
    $durasi_str = ($jam > 0 ? "{$jam} jam " : '') . "{$menit} menit";
}
$response_time = '—';
if ($kasus['waktu_kejadian'] && $kasus['waktu_tiba']) {
    $response_time = (int) ((strtotime($kasus['waktu_tiba']) - strtotime($kasus['waktu_kejadian'])) / 60) . ' menit';
}
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <!-- HEADER CETAK (hanya muncul saat print) -->
<div class="print-header" style="display: none;">
    <div class="print-header-inner">
        <img src="<?= $base_url ?>assets/assets/img/Logo.png" alt="Logo PSC119" style="height: 60px;">
        <h2 style="margin: 5px 0;">PSC 119</h2>
        <p>Sistem Penanggulangan Gawat Darurat</p>
        <p style="font-size: 12px;">Dinas Kesehatan Kota Pematangsiantar</p>
        <hr>
    </div>
</div>
<h4>Nomor Kasus: <?= htmlspecialchars($kasus['nomor_kasus']); ?></h4>
    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-8 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-file-medical-alt mr-2"></i> Detail Kasus —
                            <span class="font-weight-light"><?= htmlspecialchars($kasus['nomor_kasus']); ?></span>
                        </h6>
                    </div>
                    <div class="col-lg-4 col-5 text-right">
                        <a href="<?= $base_url ?>laporan_masuk/" class="btn btn-sm btn-neutral shadow"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                        <button onclick="window.print();" class="btn btn-sm btn-neutral shadow ml-2" title="Cetak / PDF"><i class="fas fa-print"></i> Cetak</button>
                        <a href="?id=<?= $id_kasus; ?>&export=excel" class="btn btn-sm btn-neutral shadow ml-2" title="Export Excel"><i class="fas fa-file-excel"></i> Excel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-xl-8 mb-5">
                <!-- INFORMASI LAPORAN -->
                <div class="card shadow mb-4">
                    <div class="card-header border-0"><h3 class="mb-0"><i class="fas fa-clipboard mr-2 text-primary"></i> Informasi Laporan</h3></div>
                    <div class="card-body pt-0">
                        <div class="row">
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Nama Pelapor</small><span><?= htmlspecialchars($kasus['nama_pelapor']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Telepon</small><span><?= htmlspecialchars($kasus['no_telp_pelapor']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Lokasi</small><span><?= htmlspecialchars($kasus['lokasi_kejadian']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Waktu Kejadian</small><span><?= date('d M Y, H:i', strtotime($kasus['waktu_kejadian'])); ?> WIB</span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Jenis Kasus</small><span>[<?= htmlspecialchars($kasus['kode_jenis']); ?>] <?= htmlspecialchars($kasus['nama_jenis']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Operator</small><span><?= htmlspecialchars($kasus['nama_operator']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Status Kasus</small><span><?= badge_status($kasus['status_terakhir']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Triase</small><span class="badge badge-pill px-2" style="background:<?= htmlspecialchars($kasus['warna_triase']); ?>; color:#fff;"><?= htmlspecialchars($kasus['nama_triase']); ?></span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Jumlah Pasien</small><span><?= $jumlah_pasien; ?> orang</span></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Response Time</small><span><?= $response_time; ?></span> <small class="text-muted">(Kejadian → Tim Tiba)</small></div>
                            <div class="col-lg-6 mb-3"><small class="text-muted d-block font-weight-bold">Durasi Total</small><span><?= $durasi_str; ?></span> <small class="text-muted">(Kejadian → Selesai)</small></div>
                        </div>
                        <?php if ($kasus['deskripsi']) : ?>
                            <hr><small class="text-muted d-block font-weight-bold mb-1">Deskripsi Kejadian</small><p class="mb-0"><?= nl2br(htmlspecialchars($kasus['deskripsi'])); ?></p>
                        <?php endif; ?>
                        <?php if ($kasus['catatan_penutup']) : ?>
                            <hr><small class="text-muted d-block font-weight-bold mb-1 text-success">Catatan Penutup Kasus</small><p class="mb-0"><?= nl2br(htmlspecialchars($kasus['catatan_penutup'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DATA PASIEN -->
                <div class="card shadow mb-4">
                    <div class="card-header border-0"><h3 class="mb-0"><i class="fas fa-procedures mr-2 text-info"></i> Data Pasien</h3></div>
                    <div class="card-body pt-0">
                        <?php if (empty($daftar_pasien)) : ?>
                            <div class="text-center py-4 text-muted">Belum ada data pasien diinput.</div>
                        <?php else : ?>
                            <?php foreach ($daftar_pasien as $idx => $pasien) : ?>
                                <div class="border rounded p-3 mb-3">
                                    <strong class="text-primary d-block mb-2">Pasien <?= $idx + 1; ?>: <?= htmlspecialchars($pasien['nama_pasien'] ?? 'Tidak Teridentifikasi'); ?></strong>
                                    <div class="row">
                                        <div class="col-lg-4 col-6 mb-2"><small class="text-muted d-block">Jenis Kelamin</small><?= label_jk($pasien['jenis_kelamin']); ?></div>
                                        <div class="col-lg-4 col-6 mb-2"><small class="text-muted d-block">Umur</small><?= $pasien['umur'] ? $pasien['umur'] . ' thn' : '—'; ?></div>
                                        <div class="col-lg-4 col-6 mb-2"><small class="text-muted d-block">NIK</small><?= $pasien['nik'] ? htmlspecialchars($pasien['nik']) : '—'; ?></div>
                                    </div>
                                    <div class="mt-2"><small class="text-muted d-block">Kondisi / Penanganan</small><p class="mb-0" style="font-size:.85rem;"><?= nl2br(htmlspecialchars($pasien['kondisi_awal'])); ?></p></div>
                                    <?php if ($pasien['foto_ktp']) : ?><a href="<?= $base_url . htmlspecialchars($pasien['foto_ktp']); ?>" target="_blank" class="badge badge-info mt-2"><i class="fas fa-id-card"></i> Lihat KTP</a><?php endif; ?>
                                    <?php if ($pasien['foto_pasien']) : ?><a href="<?= $base_url . htmlspecialchars($pasien['foto_pasien']); ?>" target="_blank" class="badge badge-success mt-2 ml-2"><i class="fas fa-camera"></i> Lihat Foto Pasien</a><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RUJUKAN -->
                <?php
                $stmt_rujukan = mysqli_prepare($koneksi, "SELECT r.waktu_rujukan, r.catatan, r.nama_rs_manual, rs.nama AS nama_rs_master FROM rujukan r LEFT JOIN rumah_sakit rs ON rs.id = r.rumah_sakit_id WHERE r.kasus_id = ?");
                mysqli_stmt_bind_param($stmt_rujukan, 'i', $id_kasus);
                mysqli_stmt_execute($stmt_rujukan);
                $daftar_rujukan = mysqli_fetch_all(mysqli_stmt_get_result($stmt_rujukan), MYSQLI_ASSOC);
                mysqli_stmt_close($stmt_rujukan);
                ?>
                <?php if (!empty($daftar_rujukan)) : ?>
                <div class="card shadow mb-4">
                    <div class="card-header border-0"><h3 class="mb-0"><i class="fas fa-hospital mr-2 text-warning"></i> Data Rujukan RS</h3></div>
                    <div class="card-body pt-0">
                        <?php foreach ($daftar_rujukan as $rjk) : ?>
                            <div class="alert alert-warning">
                                <strong>Dirujuk ke:</strong> <?= htmlspecialchars($rjk['nama_rs_master'] ?? $rjk['nama_rs_manual']); ?><br>
                                <small>Waktu: <?= date('d/m/Y H:i', strtotime($rjk['waktu_rujukan'])); ?></small><br>
                                <?php if($rjk['catatan']): ?><small>Catatan: <?= htmlspecialchars($rjk['catatan']); ?></small><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- SIDEBAR KANAN -->
            <div class="col-xl-4 mb-5">
                <div class="card shadow mb-4">
                    <div class="card-header border-0"><h3 class="mb-0"><i class="fas fa-shield-alt mr-2 text-warning"></i> Tim Bertugas</h3></div>
                    <div class="card-body pt-0">
                        <?php if ($kasus['id_tim']) : ?>
                            <div class="text-center mb-3">
                                <h4 class="mb-0"><?= htmlspecialchars($kasus['nama_tim']); ?> (<?= htmlspecialchars($kasus['kode_tim']); ?>)</h4>
                                <small class="text-muted">Shift: <?= htmlspecialchars($kasus['shift_label'] ?? '—'); ?> <?php if (!empty($kasus['shift_tanggal'])) echo '| ' . date('d M Y', strtotime($kasus['shift_tanggal'])); ?></small>
                            </div>
                            <?php if (!empty($daftar_anggota)) : ?>
                                <hr class="my-2">
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($daftar_anggota as $angg) : ?>
                                        <li class="d-flex align-items-center py-1 border-bottom" style="font-size:.82rem;">
                                            <span class="text-muted mr-2" style="width:110px;"><i class="fas fa-user-md"></i> <?= ucfirst($angg['jabatan']) ?></span>
                                            <span class="font-weight-600"><?= htmlspecialchars($angg['nama_lengkap']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="text-center py-3 text-muted">Belum ada tim yang ditugaskan.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TIMELINE STATUS -->
                <div class="card shadow mb-4">
                    <div class="card-header border-0"><h3 class="mb-0"><i class="fas fa-history mr-2 text-success"></i> Timeline Status</h3></div>
                    <div class="card-body pt-2">
                        <?php
                        $stmt_riwayat = mysqli_prepare($koneksi, "SELECT rs.status, rs.catatan, rs.waktu, u.nama_lengkap AS nama_petugas FROM riwayat_status_kasus rs JOIN users u ON u.id = rs.diubah_oleh WHERE rs.kasus_id = ? ORDER BY rs.waktu ASC");
                        mysqli_stmt_bind_param($stmt_riwayat, 'i', $id_kasus);
                        mysqli_stmt_execute($stmt_riwayat);
                        $daftar_riwayat = mysqli_fetch_all(mysqli_stmt_get_result($stmt_riwayat), MYSQLI_ASSOC);
                        mysqli_stmt_close($stmt_riwayat);
                        ?>
                        <?php if (empty($daftar_riwayat)) : ?>
                            <div class="text-center py-4 text-muted">Belum ada riwayat.</div>
                        <?php else : ?>
                            <div class="timeline timeline-one-side" data-timeline-axis-style="dashed">
                                <?php foreach ($daftar_riwayat as $idx => $rwyt) : $is_last = ($idx === count($daftar_riwayat) - 1); ?>
                                <div class="timeline-block mb-3 <?= $is_last ? 'pb-0' : ''; ?>">
                                    <span class="timeline-step" style="background-color:#fff; border:2px solid #dee2e6; width:30px; height:30px; line-height:26px; font-size:.75rem;"><?= icon_status($rwyt['status']); ?></span>
                                    <div class="timeline-content pl-3">
                                        <div class="d-flex justify-content-between"><?= badge_status($rwyt['status']); ?><small class="text-muted"><?= date('H:i', strtotime($rwyt['waktu'])); ?></small></div>
                                        <p class="text-muted mb-0 mt-1" style="font-size:.75rem;"><i class="fas fa-user fa-xs mr-1"></i> <?= htmlspecialchars($rwyt['nama_petugas']); ?></p>
                                        <?php if ($rwyt['catatan']) : ?><p class="mb-0 mt-1" style="font-size:.78rem;"><?= nl2br(htmlspecialchars($rwyt['catatan'])); ?></p><?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<style media="print">
    /* ============================================= */
    /* CETAK DETAIL KASUS - PSC119                   */
    /* ============================================= */
    
    /* Sembunyikan semua elemen navigasi & tombol */
    .sidebar,
    .navbar,
    .main-content .navbar,
    .template-sidebar,
    .template-footer,
    .btn, .btn-sm, .btn-neutral,
    .header, .bg-gradient-primary,
    .card-header .btn,
    .col-xl-4 .card .btn,
    .card-footer,
    .text-right,
    .text-right .btn,
    .text-right a,
    .breadcrumb,
    .nav-tabs,
    .page-item,
    .pagination,
    .alert,
    .badge a,
    .card-profile-image,
    .timeline-step,
    .icon-shape,
    .avatar,
    [data-toggle="tooltip"],
    .custom-file,
    .input-group,
    form,
    button {
        display: none !important;
    }

    /* Tampilkan header cetak */
    .print-header {
        display: block !important;
    }

    /* Reset margin & padding untuk cetak */
    body, .main-content, .container-fluid, .card, .card-body, .card-header {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }

    /* Pastikan card tidak ada bayangan */
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 1rem !important;
    }

    .card-header {
        background-color: #f5f5f5 !important;
        border-bottom: 1px solid #ccc !important;
        color: black !important;
    }

    /* Tabel */
    .table, .row .col-lg-6, .border, .alert {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    .table th, .table td, .border {
        border: 1px solid #aaa !important;
        padding: 6px !important;
        text-align: left !important;
        vertical-align: top !important;
    }

    /* Badge tetap tercetak */
    .badge {
        border: 1px solid #000 !important;
        background-color: #f8f9fa !important;
        color: #000 !important;
        padding: 2px 6px !important;
        border-radius: 12px !important;
        font-weight: normal !important;
    }
    .badge[style*="background"] {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        border: none !important;
        color: white !important;
    }
    /* Warna triase tertentu agar teks terbaca */
    .badge[style*="#D97706"], 
    .badge[style*="#16A34A"] {
        color: #000 !important;
    }

    /* Warna teks */
    body, p, span, div, h1, h2, h3, h4, h5, h6, .text-muted, .card-title, small, .font-weight-bold {
        color: #000000 !important;
        text-shadow: none !important;
    }

    /* Pastikan link tidak menampilkan URL */
    a[href]:after {
        content: none !important;
    }

    /* Page break */
    .page-break {
        page-break-before: always;
    }
</style>
<?php
ob_end_flush(); // END OUTPUT BUFFERING
?>
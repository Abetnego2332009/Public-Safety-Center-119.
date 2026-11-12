<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1, 3])) {
    header('Location: ../dashboard.php');
    exit;
}

$id_kasus = (int)($_GET['id'] ?? 0);
if ($id_kasus <= 0) {
    $_SESSION['error'] = 'ID kasus tidak valid.';
    header('Location: index.php');
    exit;
}

// Ambil data kasus
$sql = "SELECT k.*, t.label AS triase_label, t.warna_hex, t.kode AS triase_kode,
               jk.nama AS jenis_kasus_nama, jk.kode AS jenis_kasus_kode,
               tm.nama_tim, tm.kode_tim,
               u.nama_lengkap AS operator_nama
        FROM kasus k
        JOIN triase t ON t.id = k.triase_id
        JOIN jenis_kasus jk ON jk.id = k.jenis_kasus_id
        LEFT JOIN tim tm ON tm.id = k.tim_id
        LEFT JOIN users u ON u.id = k.operator_id
        WHERE k.id = ?";
$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_kasus);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$kasus) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Ambil pasien
$sql_pasien = "SELECT p.*, u.nama_lengkap AS diinput_oleh
               FROM pasien p
               LEFT JOIN users u ON u.id = p.diinput_oleh
               WHERE p.kasus_id = ?";
$stmt_p = mysqli_prepare($koneksi, $sql_pasien);
mysqli_stmt_bind_param($stmt_p, 'i', $id_kasus);
mysqli_stmt_execute($stmt_p);
$pasien_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_p), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_p);

// Ambil riwayat status
$sql_riwayat = "SELECT rs.*, u.nama_lengkap AS diubah_oleh
                FROM riwayat_status_kasus rs
                LEFT JOIN users u ON u.id = rs.diubah_oleh
                WHERE rs.kasus_id = ?
                ORDER BY rs.waktu ASC";
$stmt_r = mysqli_prepare($koneksi, $sql_riwayat);
mysqli_stmt_bind_param($stmt_r, 'i', $id_kasus);
mysqli_stmt_execute($stmt_r);
$riwayat = mysqli_fetch_all(mysqli_stmt_get_result($stmt_r), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_r);

// Ambil riwayat takeover
$sql_takeover = "SELECT ht.*, 
                        tim_asal.nama_tim AS tim_asal_nama,
                        tim_tujuan.nama_tim AS tim_tujuan_nama,
                        u.nama_lengkap AS pelaku_nama
                 FROM histori_takeover ht
                 LEFT JOIN tim tim_asal ON tim_asal.id = ht.tim_asal_id
                 LEFT JOIN tim tim_tujuan ON tim_tujuan.id = ht.tim_tujuan_id
                 LEFT JOIN users u ON u.id = ht.user_id
                 WHERE ht.kasus_id = ?
                 ORDER BY ht.created_at ASC";
$stmt_t = mysqli_prepare($koneksi, $sql_takeover);
mysqli_stmt_bind_param($stmt_t, 'i', $id_kasus);
mysqli_stmt_execute($stmt_t);
$takeover_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_t), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_t);

// Ambil data user yang sedang login untuk pengesahan
$user_id = $_SESSION['id'];
$sql_user = "SELECT nama_lengkap, role_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($koneksi, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
$nama_pembuat = $user_data['nama_lengkap'] ?? 'Admin';
$jabatan_pembuat = ($user_data['role_id'] == 1) ? 'Administrator' : 'Petugas';
$tanggal_cetak = date('d F Y');

$title = 'Detail Kasus - Monitoring';
require '../template/header.php';
require '../template/sidebar.php';
?>

<style>
    /* Perbaikan untuk layar (non-print) agar sidebar tidak nabrak */
    .main-content {
        margin-left: 260px !important;
        width: auto !important;
        position: relative !important;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0 !important;
        }
    }

    /* Tabel responsif */
    .table-responsive {
        overflow-x: auto;
    }

    th,
    td {
        word-wrap: break-word;
        vertical-align: top;
    }

    .badge-triase {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        color: #fff;
        font-weight: bold;
    }
</style>

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
    <div class="header bg-gradient-info pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-file-alt mr-2"></i> Detail Kasus</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Monitoring</a>
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
            <div class="col-xl-12 mb-5">
                <div class="card shadow">
                    <div class="card-body">
                        <!-- Informasi Kasus -->
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Informasi Kasus</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th style="width:40%">Nomor Kasus</th>
                                        <td><strong><?= htmlspecialchars($kasus['nomor_kasus']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td><?= ucfirst(str_replace('_', ' ', $kasus['status_terakhir'])) ?></td>
                                    <tr>
                                        <th>Triase</th>
                                        <td><span class="badge" style="background:<?= $kasus['warna_hex'] ?>;color:#fff"><?= $kasus['triase_label'] ?> (<?= $kasus['triase_kode'] ?>)</span></td>
                                    </tr>
                                    <tr>
                                        <th>Jenis Kasus</th>
                                        <td><?= htmlspecialchars($kasus['jenis_kasus_nama']) ?> (<?= $kasus['jenis_kasus_kode'] ?>)</td>
                                    <tr>
                                        <th>Waktu Kejadian</th>
                                        <td><?= date('d-m-Y H:i', strtotime($kasus['waktu_kejadian'])) ?></td>
                                    <tr>
                                        <th>Lokasi Kejadian</th>
                                        <td><?= nl2br(htmlspecialchars($kasus['lokasi_kejadian'])) ?></td>
                                    <tr>
                                        <th>Deskripsi</th>
                                        <td><?= nl2br(htmlspecialchars($kasus['deskripsi'])) ?></td>
                                    <tr>
                                        <th>Operator Input</th>
                                        <td><?= htmlspecialchars($kasus['operator_nama']) ?></td>
                                    <tr>
                                        <th>Tim Bertugas</th>
                                        <td><?= htmlspecialchars($kasus['nama_tim'] ?? '-') ?> (<?= htmlspecialchars($kasus['kode_tim'] ?? '-') ?>)</td>
                                    <tr>
                                        <th>Dibuat Pada</th>
                                        <td><?= date('d-m-Y H:i', strtotime($kasus['created_at'])) ?></td>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Data Pasien</h5>
                                <?php if (empty($pasien_list)): ?>
                                    <p class="text-muted">Belum ada data pasien.</p>
                                <?php else: ?>
                                    <?php foreach ($pasien_list as $idx => $p): ?>
                                        <div class="card mb-2" style="page-break-inside: avoid;">
                                            <div class="card-body p-3">
                                                <strong>Pasien #<?= $idx + 1 ?></strong>
                                                <table class="table table-sm table-borderless">
                                                    <tr>
                                                        <th>Nama</th>
                                                        <td><?= htmlspecialchars($p['nama_pasien'] ?? '-') ?></td>
                                                    <tr>
                                                        <th>NIK</th>
                                                        <td><?= htmlspecialchars($p['nik'] ?? '-') ?></td>
                                                    <tr>
                                                        <th>Umur/JK</th>
                                                        <td><?= $p['umur'] ?? '-' ?> thn / <?= $p['jenis_kelamin'] == 'L' ? 'Laki' : ($p['jenis_kelamin'] == 'P' ? 'Perempuan' : '-') ?></td>
                                                    <tr>
                                                        <th>Kondisi Awal</th>
                                                        <td><?= nl2br(htmlspecialchars($p['kondisi_awal'])) ?></td>
                                                        <?php if ($p['foto_pasien']): ?>
                                                    <tr>
                                                        <th>Foto Pasien</th>
                                                        <td><img src="../<?= $p['foto_pasien'] ?>" style="max-width:100px" class="img-thumbnail"></td>
                                                    <?php endif; ?>
                                                    <?php if ($p['foto_ktp']): ?>
                                                    <tr>
                                                        <th>Foto KTP</th>
                                                        <td><img src="../<?= $p['foto_ktp'] ?>" style="max-width:100px" class="img-thumbnail"></td>
                                                    <?php endif; ?>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                        <h5>Riwayat Perubahan Status</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Status</th>
                                        <th>Diubah Oleh</th>
                                        <th>Catatan</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($riwayat)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Belum ada riwayat</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $rno = 1;
                                        foreach ($riwayat as $r): ?>
                                            <tr>
                                                <td><?= $rno++ ?></td>
                                                <td><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></td>
                                                <td><?= htmlspecialchars($r['diubah_oleh'] ?? 'Sistem') ?></td>
                                                <td><?= nl2br(htmlspecialchars($r['catatan'] ?? '-')) ?></td>
                                                <td><?= date('d-m-Y H:i', strtotime($r['waktu'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <hr>
                            <h5>Riwayat Takeover / Pengalihan Kasus</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Waktu</th>
                                            <th>Tim Sebelumnya</th>
                                            <th>Tim Tujuan</th>
                                            <th>Alasan</th>
                                            <th>Dilakukan Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($takeover_list)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Belum ada riwayat takeover</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $tno = 1;
                                            foreach ($takeover_list as $to): ?>
                                                <tr>
                                                    <td><?= $tno++ ?></td>
                                                    <td><?= date('d-m-Y H:i', strtotime($to['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($to['tim_asal_nama'] ?? '?') ?></td>
                                                    <td><?= htmlspecialchars($to['tim_tujuan_nama'] ?? '?') ?></td>
                                                    <td><?= nl2br(htmlspecialchars($to['alasan'])) ?></td>
                                                    <td><?= htmlspecialchars($to['pelaku_nama'] ?? 'Sistem') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

<style media="print">
    /* Pastikan background warna triase tercetak */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .legalization-section {
        display: block !important;
        margin-top: 20px !important;
    }
    .print-header {
        display: block !important;
    }

    /* Sembunyikan elemen yang tidak perlu saat print */
    .sidebar,
    .navbar,
    .header,
    .card-footer,
    .form-inline,
    button,
    .btn,
    .btn-neutral,
    .btn-secondary,
    .btn-sm,
    .main-content .header,
    footer,
    .footer,
    #sidenav-main,
    .navbar-vertical,
    .navbar-top,
    .breadcrumb,
    .alert,
    .close,
    .btn-group,
    .float-right,
    .text-right form,
    .text-right button,
    .text-right a,
    .btn-refresh {
        display: none !important;
    }

    /* Halaman utama */
    body,
    .main-content,
    .container-fluid,
    .card,
    .card-body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow-x: hidden !important;
    }

    /* Mencegah card pasien terpotong antar halaman */
    .card,
    .card-body {
        page-break-inside: avoid;
    }

    /* Tabel dan teks rapi */
    .table-responsive {
        overflow: visible !important;
        width: 100% !important;
    }

    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }
    /* Tambahkan tanda titik di depan card-body */
    .card-body .badge {
        background-color: <?= $kasus['warna_hex'] ?> !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        border: 1px solid <?= $kasus['warna_hex'] ?> !important;
        display: inline-block !important;
        padding: 4px 8px !important; /* Tambahkan padding agar kotak badge terlihat */
        border-radius: 4px !important;
    }

    th,
    td {
        border: 1px solid #aaa !important;
        padding: 6px !important;
        word-wrap: break-word !important;
        word-break: break-word !important;
        vertical-align: top !important;
        font-size: 9pt !important;
    }

    th {
        background-color: #e9ecef !important;
        font-weight: bold !important;
    }

    /* Mengatur lebar kolom riwayat agar tidak nabrak */
    .table th:nth-child(1),
    .table td:nth-child(1) {
        width: 5%;
    }

    /* No */
    .table th:nth-child(2),
    .table td:nth-child(2) {
        width: 15%;
    }

    /* Status */
    .table th:nth-child(3),
    .table td:nth-child(3) {
        width: 15%;
    }

    /* Diubah Oleh */
    .table th:nth-child(4),
    .table td:nth-child(4) {
        width: 45%;
    }

    /* Catatan */
    .table th:nth-child(5),
    .table td:nth-child(5) {
        width: 20%;
    }

    /* Waktu */

    /* Mengatur gambar pasien tetap rapi */
    img.img-thumbnail {
        max-width: 80px !important;
        height: auto !important;
    }
</style>
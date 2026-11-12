<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';
$title = 'Detail Pasien';
require '../template/header.php';
require '../template/sidebar.php';

$id_pasien = (int)($_GET['id'] ?? 0);
if ($id_pasien <= 0) {
    $_SESSION['error'] = 'ID pasien tidak valid.';
    header('Location: index.php');
    exit;
}

// Ambil data pasien lengkap + kasus + riwayat
$sql = "SELECT p.*, k.nomor_kasus, k.status_terakhir, k.lokasi_kejadian, k.deskripsi as deskripsi_kasus,
               u1.nama_lengkap AS diinput_oleh, u2.nama_lengkap AS diperbaharui_oleh,
               t.nama_tim
        FROM pasien p
        JOIN kasus k ON p.kasus_id = k.id
        LEFT JOIN tim t ON t.id = k.tim_id
        JOIN users u1 ON u1.id = p.diinput_oleh
        LEFT JOIN users u2 ON u2.id = p.updated_by
        WHERE p.id = ?";
$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_pasien);
mysqli_stmt_execute($stmt);
$pasien = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$pasien) {
    $_SESSION['error'] = 'Data pasien tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Hak akses: tim_respon hanya boleh lihat jika timnya
$role = $_SESSION['nama_role'] ?? '';
$tim_user_id = $_SESSION['tim_id'] ?? null;
if ($role === 'tim_respon') {
    $stmt_cek = mysqli_prepare($koneksi, "SELECT 1 FROM pasien p JOIN kasus k ON p.kasus_id = k.id WHERE p.id = ? AND k.tim_id = ?");
    mysqli_stmt_bind_param($stmt_cek, 'ii', $id_pasien, $tim_user_id);
    mysqli_stmt_execute($stmt_cek);
    $akses = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));
    mysqli_stmt_close($stmt_cek);
    if (!$akses) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke data ini.';
        header('Location: index.php');
        exit;
    }
}
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <!-- ======================================================= -->
<!-- HEADER UNTUK CETAK (HANYA MUNCUL SAAT PRINT)            -->
<!-- ======================================================= -->
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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-user-injured mr-2"></i> Detail Pasien</h6>
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
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Informasi Kasus</h5>
                        <table class="table table-sm table-borderless">
                            <tr><th>Nomor Kasus</th><td><a href="../laporan_masuk/detail_kasus.php?id=<?= $pasien['kasus_id'] ?>"><?= htmlspecialchars($pasien['nomor_kasus']) ?></a></td></tr>
                            <tr><th>Status Kasus</th><td><?= ucfirst(str_replace('_',' ',$pasien['status_terakhir'])) ?></td></tr>
                            <tr><th>Tim Penanganan</th><td><?= htmlspecialchars($pasien['nama_tim'] ?? '-') ?></td></tr>
                            <tr><th>Lokasi Kejadian</th><td><?= htmlspecialchars($pasien['lokasi_kejadian']) ?></td></tr>
                            <tr><th>Deskripsi Kasus</th><td><?= nl2br(htmlspecialchars($pasien['deskripsi_kasus'])) ?></td></tr>
                        </table>
                        <hr>
                        <h5 class="card-title">Data Pasien</h5>
                        <table class="table table-sm table-borderless">
                            <tr><th>Nama Pasien</th><td><?= htmlspecialchars($pasien['nama_pasien'] ?? '-') ?></td></tr>
                            <tr><th>NIK</th><td><?= htmlspecialchars($pasien['nik'] ?? '-') ?></td></tr>
                            <tr><th>Umur</th><td><?= $pasien['umur'] ? $pasien['umur'].' tahun' : '-' ?></td></tr>
                            <tr><th>Jenis Kelamin</th><td><?= $pasien['jenis_kelamin'] == 'L' ? 'Laki-laki' : ($pasien['jenis_kelamin'] == 'P' ? 'Perempuan' : '-') ?></td></tr>
                            <tr><th>Kondisi Awal</th><td><?= nl2br(htmlspecialchars($pasien['kondisi_awal'])) ?></td></tr>
                            <tr><th>Diinput Oleh</th><td><?= htmlspecialchars($pasien['diinput_oleh']) ?> pada <?= date('d-m-Y H:i', strtotime($pasien['created_at'])) ?></td></tr>
                            <?php if ($pasien['updated_by']): ?>
                            <tr><th>Diperbaharui Oleh</th><td><?= htmlspecialchars($pasien['diperbaharui_oleh']) ?> pada <?= date('d-m-Y H:i', strtotime($pasien['updated_at'])) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($pasien['foto_pasien']): ?>
                            <tr><th>Foto Pasien</th><td><img src="../<?= $pasien['foto_pasien'] ?>" style="max-width:200px" class="img-thumbnail"></td></tr>
                            <?php endif; ?>
                            <?php if ($pasien['foto_ktp']): ?>
                            <tr><th>Foto KTP</th><td><img src="../<?= $pasien['foto_ktp'] ?>" style="max-width:200px" class="img-thumbnail"></td></tr>
                            <?php endif; ?>
                        </table>
                        <div class="mt-3">
                            <a href="edit.php?id=<?= $pasien['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Edit Pasien</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>
<style media="print">
    /* Sembunyikan elemen navigasi */
    .sidebar, .navbar, .header, .btn, .card-footer, .d-flex, .mt-3 a, 
    .text-right, .btn-sm, .btn-neutral, .breadcrumb, .col-5, .col-lg-6.col-5 {
        display: none !important;
    }
    
    /* Tampilkan header cetak */
    .print-header {
        display: block !important;
    }
    
    /* Reset margin */
    .main-content, .container-fluid, .card, .card-body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    /* Tabel rapi */
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #aaa;
        padding: 5px;
        text-align: left;
    }
    
    /* Gambar di cetak */
    img {
        max-width: 150px;
        page-break-inside: avoid;
    }
    
    /* Header cetak */
    .print-header-inner {
        text-align: center;
        margin-bottom: 15px;
    }
    .print-header-inner img {
        height: 60px;
    }
</style>
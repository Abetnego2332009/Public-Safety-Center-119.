<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

// Ambil data user yang sedang login untuk keperluan pengesahan
$user_id = $_SESSION['id'];
$sql_user = "SELECT nama_lengkap, role_id FROM users WHERE id = ?";
$stmt_user = mysqli_prepare($koneksi, $sql_user);
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);

$nama_pembuat = $user_data['nama_lengkap'] ?? 'Administrator';
$role_id_user = $user_data['role_id'] ?? 0;
if ($role_id_user == 1) {
    $jabatan_pembuat = 'Administrator';
} elseif ($role_id_user == 2) {
    $jabatan_pembuat = 'Operator';
} elseif ($role_id_user == 3) {
    $jabatan_pembuat = 'Koordinator';
} else {
    $jabatan_pembuat = 'Petugas PSC 119';
}

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1, 3, 4])) {
    header('Location: ../dashboard.php');
    exit;
}

$title = 'Jadwal Shift Tim';
require '../template/header.php';
require '../template/sidebar.php';

// ========== FILTER ==========
$tgl_awal   = isset($_GET['tgl_awal'])   ? mysqli_real_escape_string($koneksi, $_GET['tgl_awal'])   : date('Y-m-d', strtotime('monday this week'));
$tgl_akhir  = isset($_GET['tgl_akhir'])  ? mysqli_real_escape_string($koneksi, $_GET['tgl_akhir'])  : date('Y-m-d', strtotime('sunday this week'));
$filter_tim = isset($_GET['tim_id'])     ? (int)$_GET['tim_id']     : 0;
$filter_shift = isset($_GET['shift_id']) ? (int)$_GET['shift_id']   : 0;

// Data dropdown
$tim_dropdown   = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id, nama_tim, kode_tim FROM tim WHERE is_aktif = 1 ORDER BY id"), MYSQLI_ASSOC);
$shift_dropdown = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id, kode_shift, label FROM shift_definisi ORDER BY jam_mulai"), MYSQLI_ASSOC);

// Query
$sql = "SELECT js.*, t.nama_tim, t.kode_tim, sd.label as shift_label, sd.kode_shift,
               u.nama_lengkap as dibuat_oleh_nama
        FROM jadwal_shift js
        JOIN tim t ON js.tim_id = t.id
        JOIN shift_definisi sd ON js.shift_definisi_id = sd.id
        LEFT JOIN users u ON js.dibuat_oleh = u.id
        WHERE js.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir'";

if ($filter_tim > 0)   $sql .= " AND js.tim_id = $filter_tim";
if ($filter_shift > 0) $sql .= " AND js.shift_definisi_id = $filter_shift";
$sql .= " ORDER BY js.tanggal ASC, sd.jam_mulai ASC";

$result = mysqli_query($koneksi, $sql);
$jadwal_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total = count($jadwal_list);

$tanggal_cetak = date('d F Y');
?>

<style>
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
    .legalization-section {
        display: none;
    }
</style>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    
    <!-- HEADER UNTUK CETAK -->
    <div class="print-header" style="display: none;">
        <div class="print-header-inner" style="text-align: center; margin-bottom: 20px;">
            <img src="<?= $base_url ?>assets/assets/img/Logo.png" alt="Logo PSC119" style="height: 70px;">
            <h2 style="margin: 10px 0 5px; font-weight: bold;">PSC 119</h2>
            <p style="margin: 0; font-size: 14px;">Sistem Penanggulangan Gawat Darurat</p>
            <p style="margin: 0; font-size: 12px;">Dinas Kesehatan Kota Pematangsiantar</p>
            <hr style="margin: 10px 0; border: 1px solid #000;">
        </div>
    </div>
    
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-calendar-alt mr-2"></i> Jadwal Shift Tim</h6>
                    </div>
                    <?php if (
                        $_SESSION['nama_role'] == 'admin' ||
                        $_SESSION['nama_role'] == 'koordinator'
                    ): ?>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="tambah.php" class="btn btn-sm btn-neutral"><i class="fas fa-plus mr-1"></i> Tambah Jadwal</a>
                        <a href="tambah_range.php" class="btn btn-sm btn-info"><i class="fas fa-calendar-week mr-1"></i> Tambah Satu Minggu</a>
                        <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 10px; font-size: 10px; margin-left: 5px;">
                            <i class="fas fa-print mr-1"></i> Cetak Laporan
                        </button>
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid mt--7">
        <!-- FILTER JADWAL (hanya tampil di web, tidak saat cetak) -->
        <div class="row filter-section">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">Filter Jadwal</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <div class="row w-100">
                                <div class="col-md-3 mb-2">
                                    <label>Dari Tanggal</label>
                                    <input type="date" name="tgl_awal" class="form-control form-control-sm w-100" value="<?= $tgl_awal ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label>Sampai Tanggal</label>
                                    <input type="date" name="tgl_akhir" class="form-control form-control-sm w-100" value="<?= $tgl_akhir ?>">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>Tim</label>
                                    <select name="tim_id" class="form-control form-control-sm w-100">
                                        <option value="0">-- Semua Tim --</option>
                                        <?php foreach ($tim_dropdown as $td): ?>
                                            <option value="<?= $td['id'] ?>" <?= $filter_tim == $td['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($td['nama_tim']) ?> (<?= $td['kode_tim'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label>Shift</label>
                                    <select name="shift_id" class="form-control form-control-sm w-100">
                                        <option value="0">-- Semua Shift --</option>
                                        <?php foreach ($shift_dropdown as $sd): ?>
                                            <option value="<?= $sd['id'] ?>" <?= $filter_shift == $sd['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sd['label']) ?> (<?= $sd['kode_shift'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-primary mr-2">Filter</button>
                                    <a href="?" class="btn btn-sm btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">Daftar Jadwal Shift</h3>
                        <small class="text-muted periode-info">Menampilkan <?= $total ?> jadwal dari <?= date('d-m-Y', strtotime($tgl_awal)) ?> s/d <?= date('d-m-Y', strtotime($tgl_akhir)) ?></small>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush jadwal-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Tim</th>
                                    <th>Shift</th>
                                    <th>Catatan</th>
                                    <th>Dibuat Oleh</th>
                                    <?php if (
                                        $_SESSION['nama_role'] == 'admin' ||
                                        $_SESSION['nama_role'] == 'koordinator'
                                    ): ?>
                                    <th class="aksi-col">Aksi</th>
                                    <?php endif;?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;?>
                                <?php if (empty($jadwal_list)): ?>
                                    <tr><td colspan="7" class="text-center">Tidak ada jadwal</td></tr>
                                <?php else: ?>
                                    <?php foreach ($jadwal_list as $j): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= date('d-m-Y', strtotime($j['tanggal'])) ?></span></td>
                                        <td><?= htmlspecialchars($j['nama_tim']) ?> (<?= $j['kode_tim'] ?>)</span></td>
                                        <td><?= htmlspecialchars($j['shift_label']) ?> (<?= $j['kode_shift'] ?>)</span></td>
                                        <td><?= htmlspecialchars($j['catatan'] ?? '-') ?></span></td>
                                        <td><?= htmlspecialchars($j['dibuat_oleh_nama'] ?? '-') ?></span></td>
                                        <?php if (
                                            $_SESSION['nama_role'] == 'admin' ||
                                            $_SESSION['nama_role'] == 'koordinator'
                                        ): ?>
                                        <td>
                                            <a href="edit.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="proses.php?hapus=<?= $j['id'] ?>" onclick="return confirm('Yakin hapus?')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Hapus</a>
                                        </span>
                                         <?php endif;?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- BAGIAN PENGESAHAN - HANYA MUNCUL SAAT CETAK -->
        <!-- ============================================ -->
        <div class="row legalization-section">
            <div class="col-12">
                <div style="margin-top: 60px; margin-bottom: 30px;">
                    <div style="text-align: center; margin-bottom: 40px; font-weight: bold;">
                        Pematangsiantar, <?= $tanggal_cetak ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <div style="text-align: center; width: 45%;">
                            <div style="font-weight: bold; margin-bottom: 50px;">Pembuat Laporan,</div>
                            <div style="margin-top: 40px;">___________________</div>
                            <div style="margin-top: 8px; font-weight: bold;">(<?= htmlspecialchars($nama_pembuat) ?>)</div>
                            <div style="font-size: 11px; margin-top: 4px;">(<?= htmlspecialchars($jabatan_pembuat) ?>)</div>
                        </div>
                        
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

<!-- CSS PRINT -->
<style media="print">
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* SEMUA TEKS MENJADI HITAM DAN TEBAL */
    body, .main-content, .container-fluid, .card, .card-body, 
    table, th, td, div, span, p, h1, h2, h3, h4, h5, h6,
    .card-title, .h2, .mb-0, .mt-3, .text-sm, .text-muted,
    .periode-info, small, .thead-light th {
        color: #000000 !important;
        font-weight: bold !important;
    }

    /* SEMUA GARIS TABEL MENJADI TEBAL HITAM */
    table, th, td, .table, .jadwal-table,
    .jadwal-table th, .jadwal-table td {
        border-color: #000000 !important;
        border-width: 2px !important;
        border-style: solid !important;
    }

    /* CARD BORDER */
    .card {
        border: 2px solid #000000 !important;
        box-shadow: none !important;
    }

    .card-header {
        border-bottom: 2px solid #000000 !important;
    }

    /* HILANGKAN FILTER JADWAL */
    .filter-section, .filter-section .card, .filter-section .card-header,
    .filter-section .card-body, .filter-section form,
    .filter-section input, .filter-section select, .filter-section button,
    .filter-section .btn, .filter-section a {
        display: none !important;
    }

    /* HILANGKAN AKSI (EDIT/HAPUS) DI TABEL */
    .aksi-col, th:last-child, td:last-child .btn,
    .btn-warning, .btn-danger, .btn-sm,
    td:last-child, th:last-child {
        display: none !important;
    }

    /* HILANGKAN TOMBOL-TomBOL */
    .header .text-right, .header .btn, .header .btn-sm,
    .btn-neutral, .btn-info, .btn-secondary,
    .float-right, .text-right button, .text-right a {
        display: none !important;
    }

    /* HILANGKAN SIDEBAR DAN NAVBAR */
    .sidebar, .navbar, .navbar-top, .nav, .sidenav,
    .main-content .navbar, .main-content .sidebar {
        display: none !important;
    }

    /* TAMPILKAN PRINT HEADER */
    .print-header {
        display: block !important;
    }

    /* LAYOUT */
    body, .main-content, .container-fluid {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .col-md-6, .col-xl-4, .col-md-4, .col-lg-8, .col-lg-4 {
        width: 100% !important;
        float: none !important;
        margin-bottom: 15px;
    }

    /* PENGESAHAN */
    .legalization-section {
        display: block !important;
        margin-top: 60px !important;
        page-break-inside: avoid;
    }

    /* HEADER TABEL */
    th {
        background-color: #e0e0e0 !important;
    }

    /* RINGKASAN INFO */
    .periode-info {
        font-size: 12px !important;
        margin-top: 5px !important;
    }
</style>
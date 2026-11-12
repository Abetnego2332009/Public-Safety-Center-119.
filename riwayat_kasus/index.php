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
    $jabatan_pembuat = 'Kepala Dinas';
} else {
    $jabatan_pembuat = 'Petugas PSC 119';
}

$role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($role_id, [1,3])) {
    header('Location: ../dashboard.php');
    exit;
}

$title = 'Riwayat Kasus';
require '../template/header.php';
require '../template/sidebar.php';

// Filter
$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Query dasar
$where = ["k.status_terakhir IN ('selesai','dibatalkan')"];
$params = [];
$types = "";

if ($filter_status !== '') {
    $where[] = "k.status_terakhir = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($search !== '') {
    $where[] = "(k.nomor_kasus LIKE ? OR k.nama_pelapor LIKE ? OR k.lokasi_kejadian LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// Hitung total
$count_sql = "SELECT COUNT(*) as total FROM kasus k $where_sql";
$stmt_count = mysqli_prepare($koneksi, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
mysqli_stmt_close($stmt_count);
$total_pages = ceil($total / $limit);

// Ambil data
$sql = "SELECT k.id, k.nomor_kasus, k.status_terakhir, k.lokasi_kejadian, k.nama_pelapor, 
               k.waktu_kejadian, k.waktu_selesai,
               t.label AS triase_label, t.warna_hex,
               tm.nama_tim
        FROM kasus k
        JOIN triase t ON t.id = k.triase_id
        LEFT JOIN tim tm ON tm.id = k.tim_id
        $where_sql
        ORDER BY k.waktu_selesai DESC
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($koneksi, $sql);
$bind_types = $types . "ii";
$bind_params = array_merge($params, [$limit, $offset]);
if (!empty($bind_params)) {
    mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$kasus_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$tanggal_cetak = date('d F Y');
?>

<style>
    /* Fix sidebar tidak menabrak konten */
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
    .table td, .table th {
        white-space: normal !important;
        word-wrap: break-word;
    }
    
    /* Sembunyikan pengesahan saat tampil di web */
    .legalization-section {
        display: none;
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
            <p style="margin: 0; font-size: 12px;">Dinas Kesehatan Kota Pematangsiantar</p>
            <hr style="margin: 10px 0; border: 1px solid #000;">
        </div>
    </div>
    
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-history mr-2"></i> Riwayat Kasus</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <button onclick="window.print()" class="btn btn-secondary" style="padding: 8px 10px; font-size: 10px; margin-left: 5px;">
                            <i class="fas fa-print mr-1"></i> Cetak Laporan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid mt--7">
        <!-- FILTER SECTION - hanya tampil di web -->
        <div class="row filter-section">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-0">Kasus Selesai / Dibatalkan</h3>
                            </div>
                            <div class="col text-right">
                                <form method="GET" class="form-inline float-right">
                                    <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Cari kasus..." value="<?= htmlspecialchars($search) ?>">
                                    <select name="status" class="form-control form-control-sm mr-2">
                                        <option value="">Semua Status</option>
                                        <option value="selesai" <?= $filter_status=='selesai'?'selected':'' ?>>Selesai</option>
                                        <option value="dibatalkan" <?= $filter_status=='dibatalkan'?'selected':'' ?>>Dibatalkan</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                                    <a href="?" class="btn btn-sm btn-outline-secondary ml-1">Reset</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">Daftar Riwayat Kasus</h3>
                        <small class="text-muted periode-info">Menampilkan <?= $total ?> kasus</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush riwayat-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>No. Kasus</th>
                                    <th>Pelapor</th>
                                    <th>Lokasi Kejadian</th>
                                    <th>Triase</th>
                                    <th>Tim</th>
                                    <th>Status</th>
                                    <th>Waktu Selesai</th>
                                    <th class="aksi-col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kasus_list)): ?>
                                    <tr><td colspan="9" class="text-center">Tidak ada riwayat kasus</span></td></tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1; foreach ($kasus_list as $k): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($k['nomor_kasus']) ?></strong></td>
                                        <td><?= htmlspecialchars($k['nama_pelapor']) ?></td>
                                        <td><?= htmlspecialchars(substr($k['lokasi_kejadian'], 0, 40)) ?><?= strlen($k['lokasi_kejadian'])>40?'...':'' ?></td>
                                        <td><span class="badge riwayat-badge" style="background:<?= $k['warna_hex'] ?>;color:#fff"><?= $k['triase_label'] ?></span></td>
                                        <td><?= $k['nama_tim'] ?: '-' ?></td>
                                        <td><?= $k['status_terakhir'] == 'selesai' ? '<span class="badge badge-success">Selesai</span>' : '<span class="badge badge-danger">Dibatalkan</span>' ?></td>
                                        <td><?= $k['waktu_selesai'] ? date('d-m-Y H:i', strtotime($k['waktu_selesai'])) : '-' ?></td>
                                        <td><a href="detail.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-info">Detail</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer pagination-section">
                        <nav><ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul></nav>
                    </div>
                    <?php endif; ?>
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

<!-- CSS PRINT - SEMUA TEKS DAN GARIS JADI HITAM/TEBAL -->
<style media="print">
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* WARNA BADGE TETAP MUNCUL */
    .riwayat-badge, .badge-success, .badge-danger {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* SEMUA TEKS MENJADI HITAM DAN TEBAL */
    body, .main-content, .container-fluid, .card, .card-body, 
    table, th, td, div, span, p, h1, h2, h3, h4, h5, h6,
    .card-title, .h2, .mb-0, .mt-3, .text-sm, .text-muted,
    .periode-info, small, .thead-light th, strong,
    .badge, .badge-success, .badge-danger {
        color: #000000 !important;
        font-weight: bold !important;
    }

    /* SEMUA GARIS TABEL MENJADI TEBAL HITAM */
    table, th, td, .table, .riwayat-table,
    .riwayat-table th, .riwayat-table td {
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

    /* HILANGKAN FILTER SECTION */
    .filter-section, .filter-section .card, 
    .filter-section .card-header, .filter-section form,
    .filter-section input, .filter-section select, 
    .filter-section button, .filter-section .btn,
    .filter-section a, .float-right, .text-right form {
        display: none !important;
    }

    /* HILANGKAN AKSI (DETAIL) DI TABEL */
    .aksi-col, th:last-child, td:last-child .btn,
    td:last-child, th:last-child,
    .btn-info, .btn-sm {
        display: none !important;
    }

    /* HILANGKAN PAGINATION */
    .pagination-section, .card-footer, .pagination,
    .page-item, .page-link {
        display: none !important;
    }

    /* HILANGKAN TOMBOL CETAK */
    .header .text-right, .header .btn, .header .btn-sm,
    .btn-secondary, .float-right, .text-right button {
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
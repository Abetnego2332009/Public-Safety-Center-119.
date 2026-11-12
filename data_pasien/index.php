<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';
$title = 'Data Pasien';
require '../template/header.php';
require '../template/sidebar.php';

$role = $_SESSION['nama_role'] ?? '';
$tim_user_id = $_SESSION['tim_id'] ?? null;

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "(p.nama_pasien LIKE ? OR k.nomor_kasus LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

// Hak akses: tim_respon hanya lihat pasien dari timnya
if ($role === 'tim_respon') {
    if ($tim_user_id) {
        $where[] = "k.tim_id = ?";
        $params[] = $tim_user_id;
        $types .= "i";
    } else {
        $where[] = "1=0";
    }
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Hitung total
$count_sql = "SELECT COUNT(*) as total FROM pasien p JOIN kasus k ON p.kasus_id = k.id $where_sql";
$stmt_count = mysqli_prepare($koneksi, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
mysqli_stmt_close($stmt_count);
$total_pages = ceil($total / $limit);

// Query data
$sql = "SELECT p.id, p.nama_pasien, p.nik, p.umur, p.jenis_kelamin, p.kondisi_awal, p.created_at,
               k.id AS kasus_id, k.nomor_kasus, k.status_terakhir,
               u1.nama_lengkap AS diinput_oleh, u2.nama_lengkap AS diperbaharui_oleh
        FROM pasien p
        JOIN kasus k ON p.kasus_id = k.id
        JOIN users u1 ON u1.id = p.diinput_oleh
        LEFT JOIN users u2 ON u2.id = p.updated_by
        $where_sql
        ORDER BY p.created_at DESC
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
$pasien_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
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
    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-users mr-2"></i> Data Pasien</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <button onclick="window.print()" class="btn btn-sm btn-neutral"><i class="fas fa-print mr-1"></i> Cetak Laporan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']);
                                                unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']);
                                            unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <h3 class="mb-0">Daftar Pasien</h3>
                            </div>
                            <div class="col text-right">
                                <form method="GET" class="form-inline float-right">
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / nomor kasus" value="<?= htmlspecialchars($search); ?>">
                                    <button class="btn btn-sm btn-outline-primary ml-1" type="submit"><i class="fas fa-search"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush" id="printTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nomor Kasus</th>
                                    <th>Nama Pasien</th>
                                    <th>NIK</th>
                                    <th>Umur</th>
                                    <th>JK</th>
                                    <th>Kondisi Awal</th>
                                    <th>Status Kasus</th>
                                    <th>Diinput Oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pasien_list)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = $offset + 1;
                                    foreach ($pasien_list as $p): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><a href="../laporan_masuk/detail_kasus.php?id=<?= $p['kasus_id'] ?>"><?= htmlspecialchars($p['nomor_kasus']) ?></a></td>
                                            <td><a href="detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_pasien'] ?? '-') ?></a></td>
                                            <td><?= htmlspecialchars($p['nik'] ?? '-') ?></td>
                                            <td><?= $p['umur'] ? $p['umur'] . ' thn' : '-' ?></td>
                                            <td><?= $p['jenis_kelamin'] == 'L' ? 'Laki' : ($p['jenis_kelamin'] == 'P' ? 'Perempuan' : '-') ?></td>
                                            <td><?= htmlspecialchars(substr($p['kondisi_awal'], 0, 50)) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $p['status_terakhir'])) ?></td>
                                            <td><?= htmlspecialchars($p['diinput_oleh']) ?></td>
                                            <td>
                                                <a href="detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info" title="Detail Pasien">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i>Edit</a>
                                                <?php if ($_SESSION['role_id'] == 1): ?>
                                                    <button class="btn btn-sm btn-danger btn-hapus" data-id="<?= $p['id'] ?>" data-nama="<?= htmlspecialchars($p['nama_pasien']) ?>"><i class="fas fa-trash"></i></button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(function() {
        $('.btn-hapus').click(function() {
            let id = $(this).data('id');
            let nama = $(this).data('nama');
            if (confirm('Hapus pasien "' + nama + '"? Data akan dihapus permanen.')) {
                $.post('proses.php', {
                    action: 'delete',
                    id: id
                }, function(res) {
                    if (res.status === 'success') location.reload();
                    else alert(res.message);
                }, 'json');
            }
        });
    });
</script>
<style media="print">
    /* Sembunyikan elemen navigasi & tombol */
    .sidebar, .navbar, .header, .btn, .card-footer, .pagination, .form-inline,
    .text-right, .col-5, .col-lg-6.col-5, .breadcrumb, .btn-sm, .btn-neutral {
        display: none !important;
    }

    /* Tampilkan header cetak */
    .print-header {
        display: block !important;
    }

    /* Reset margin & padding */
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
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #aaa;
        padding: 6px;
        text-align: left;
        vertical-align: top;
    }
    thead th {
        background-color: #f2f2f2;
    }

    /* Header cetak */
    .print-header-inner {
        text-align: center;
        margin-bottom: 15px;
    }
    .print-header-inner img {
        height: 60px;
    }
    hr {
        margin: 10px 0;
        border: 0.5px solid #ccc;
    }
</style>
<?php
// ============================================================
//  FILE    : laporan_masuk/index.php
//  SISTEM  : PSC 119 — Sistem Pendataan Kasus Gawat Darurat
//  INSTANSI: PSC 119 Dinas Kesehatan Kota Pematangsiantar
//  FUNGSI  : Menampilkan daftar laporan / kasus masuk
//            dengan filter, statistik, dan pagination
//
//  DATABASE: Menggunakan skema PSC 119 resmi
//  Tabel   : kasus, users, roles, jenis_kasus, triase,
//            jadwal_shift, shift_definisi, tim, anggota_tim,
//            pasien, riwayat_status_kasus
// ============================================================

// ============================================================
// [1] INISIALISASI
// ============================================================
session_start();
require_once __DIR__ . '/../koneksi.php';
// Guard: wajib login
if (
    !isset($_SESSION['sudah_login']) ||
    $_SESSION['sudah_login'] !== true ||
    !isset($_SESSION['id'])
) {
    header("Location: ../index.php");
    exit;
}
$title = 'Laporan Masuk';
require '../template/header.php';
require '../template/sidebar.php';


$id_user_login  = (int) $_SESSION['id'];
$nama_role      = $_SESSION['nama_role'] ?? '';       // admin|operator|koordinator|tim_respon

// ============================================================
// [2] PARAMETER FILTER & PAGINATION
// ============================================================
$filter_dari    = trim($_GET['dari']   ?? '');
$filter_sampai  = trim($_GET['sampai'] ?? '');
$filter_status  = trim($_GET['status'] ?? '');
$filter_triase  = trim($_GET['triase'] ?? '');
$halaman        = max(1, (int) ($_GET['hal'] ?? 1));
$per_halaman    = 15;
$offset         = ($halaman - 1) * $per_halaman;
// Default: awal bulan sampai hari ini
if ($filter_dari === '' && $filter_sampai === '') {

    // Tanggal awal bulan
    $filter_dari = date('Y-m-01');

    // Tanggal hari ini
    $filter_sampai = date('Y-m-d');
}

// ============================================================
// [3] DETEKSI TIM LOGIN (khusus role tim_respon)
//     Kolom anggota_tim: user_id, tim_id, tgl_keluar IS NULL
// ============================================================
$id_tim_login = null;

if ($nama_role === 'tim_respon') {
    $stmt_tim_saya = mysqli_prepare($koneksi,
        "SELECT tim_id FROM anggota_tim
         WHERE user_id = ? AND tgl_keluar IS NULL
         ORDER BY tgl_masuk DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt_tim_saya, 'i', $id_user_login);
    mysqli_stmt_execute($stmt_tim_saya);
    $r_tim = mysqli_stmt_get_result($stmt_tim_saya);
    if ($row_tim = mysqli_fetch_assoc($r_tim)) {
        $id_tim_login = (int) $row_tim['tim_id'];
    }
    mysqli_stmt_close($stmt_tim_saya);
}

// ============================================================
// [4] BANGUN KONDISI WHERE
// ============================================================
$where_parts  = ["DATE(k.created_at) BETWEEN ? AND ?"];
$bind_types   = 'ss';
$bind_params  = [$filter_dari, $filter_sampai];

// Filter status — nilai ENUM tabel kasus
$status_valid = [
    'menunggu_respon','menuju_lokasi','tiba_di_lokasi',
    'penanganan_di_tempat','dirujuk_ke_rs','selesai','dibatalkan'
];
if ($filter_status !== '' && in_array($filter_status, $status_valid, true)) {
    $where_parts[]  = "k.status_terakhir = ?";
    $bind_types    .= 's';
    $bind_params[]  = $filter_status;
}

// Filter triase
if ($filter_triase !== '' && ctype_digit($filter_triase)) {
    $where_parts[]  = "k.triase_id = ?";
    $bind_types    .= 'i';
    $bind_params[]  = (int) $filter_triase;
}

// Batasi ke tim sendiri jika tim_respon
if ($id_tim_login !== null) {
    $where_parts[]  = "k.tim_id = ?";
    $bind_types    .= 'i';
    $bind_params[]  = $id_tim_login;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// ============================================================
// [5] QUERY TOTAL (untuk pagination)
// ============================================================
$sql_total = "
    SELECT COUNT(k.id) AS total
    FROM kasus k
    {$where_sql}
";

$stmt_total = mysqli_prepare($koneksi, $sql_total);
mysqli_stmt_bind_param($stmt_total, $bind_types, ...$bind_params);
mysqli_stmt_execute($stmt_total);
$total_data    = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total))['total'] ?? 0);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
mysqli_stmt_close($stmt_total);

// ============================================================
// [6] QUERY DATA UTAMA
//     Tabel utama : kasus
//     JOIN        : jenis_kasus, triase, users (operator),
//                   tim, jadwal_shift, shift_definisi
//     LEFT JOIN   : pasien (hitung jumlah pasien per kasus)
// ============================================================
$bind_types_paged  = $bind_types . 'ii';
$bind_params_paged = array_merge($bind_params, [$per_halaman, $offset]);

$sql_data = "
    SELECT
        k.id                            AS id_kasus,
        k.nomor_kasus,
        k.nama_pelapor,
        k.no_telp_pelapor,
        k.lokasi_kejadian,
        k.waktu_kejadian,
        k.status_terakhir,
        k.created_at,
        jk.kode                         AS kode_jenis,
        jk.nama                         AS nama_jenis,
        tr.kode                         AS kode_triase,
        tr.label                        AS label_triase,
        tr.warna_hex                    AS warna_triase,
        tr.urgensi,
        t.id                            AS id_tim,
        t.nama_tim,
        t.kode_tim,
        sd.label                        AS label_shift,
        sd.kode_shift,
        js.tanggal                      AS tanggal_shift,
        u.nama_lengkap                  AS nama_operator,
        COUNT(p.id)                     AS jumlah_pasien
    FROM kasus k
    INNER JOIN jenis_kasus jk   ON jk.id = k.jenis_kasus_id
    INNER JOIN triase tr         ON tr.id = k.triase_id
    INNER JOIN users u           ON u.id  = k.operator_id
    LEFT JOIN  tim t             ON t.id  = k.tim_id
    LEFT JOIN  jadwal_shift js   ON js.id = k.jadwal_shift_id
    LEFT JOIN  shift_definisi sd ON sd.id = js.shift_definisi_id
    LEFT JOIN  pasien p          ON p.kasus_id = k.id
    {$where_sql}
    GROUP BY k.id
    ORDER BY k.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt_data = mysqli_prepare($koneksi, $sql_data);
mysqli_stmt_bind_param($stmt_data, $bind_types_paged, ...$bind_params_paged);
mysqli_stmt_execute($stmt_data);
$result_data = mysqli_stmt_get_result($stmt_data);

// ============================================================
// [7] STATISTIK RINGKASAN HARI INI
// ============================================================
$hari_ini = date('Y-m-d');

if ($id_tim_login !== null) {
    $sql_stat = "
        SELECT
            COUNT(*)                                                                                   AS total,
            SUM(CASE WHEN k.status_terakhir = 'menunggu_respon'    THEN 1 ELSE 0 END)                 AS menunggu,
            SUM(CASE WHEN k.status_terakhir IN ('menuju_lokasi','tiba_di_lokasi') THEN 1 ELSE 0 END)  AS proses,
            SUM(CASE WHEN k.status_terakhir = 'penanganan_di_tempat' THEN 1 ELSE 0 END)               AS ditangani,
            SUM(CASE WHEN k.status_terakhir IN ('selesai','dirujuk_ke_rs') THEN 1 ELSE 0 END)         AS selesai
        FROM kasus k
        WHERE DATE(k.created_at) = ? AND k.tim_id = ?
    ";
    $stmt_stat = mysqli_prepare($koneksi, $sql_stat);
    mysqli_stmt_bind_param($stmt_stat, 'si', $hari_ini, $id_tim_login);
} else {
    $sql_stat = "
        SELECT
            COUNT(*)                                                                                   AS total,
            SUM(CASE WHEN k.status_terakhir = 'menunggu_respon'    THEN 1 ELSE 0 END)                 AS menunggu,
            SUM(CASE WHEN k.status_terakhir IN ('menuju_lokasi','tiba_di_lokasi') THEN 1 ELSE 0 END)  AS proses,
            SUM(CASE WHEN k.status_terakhir = 'penanganan_di_tempat' THEN 1 ELSE 0 END)               AS ditangani,
            SUM(CASE WHEN k.status_terakhir IN ('selesai','dirujuk_ke_rs') THEN 1 ELSE 0 END)         AS selesai
        FROM kasus k
        WHERE DATE(k.created_at) = ?
    ";
    $stmt_stat = mysqli_prepare($koneksi, $sql_stat);
    mysqli_stmt_bind_param($stmt_stat, 's', $hari_ini);
}
mysqli_stmt_execute($stmt_stat);
$stat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stat));
mysqli_stmt_close($stmt_stat);

// ============================================================
// [8] DROPDOWN TRIASE untuk form filter
// ============================================================
$query_triase_filter = mysqli_query($koneksi,
    "SELECT id, kode, label, warna_hex FROM triase ORDER BY urgensi ASC"
);

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Badge status kasus — nilai ENUM tabel kasus
 */
function badge_status(string $status): string
{
    $map = [
        'menunggu_respon'      => ['badge-secondary', 'fas fa-clock',           'Menunggu Respon'],
        'menuju_lokasi'        => ['badge-info',      'fas fa-ambulance',        'Menuju Lokasi'],
        'tiba_di_lokasi'       => ['badge-primary',   'fas fa-map-marker-alt',   'Tiba di Lokasi'],
        'penanganan_di_tempat' => ['badge-warning',   'fas fa-procedures',       'Penanganan'],
        'dirujuk_ke_rs'        => ['badge-orange',    'fas fa-hospital',         'Dirujuk ke RS'],
        'selesai'              => ['badge-success',   'fas fa-check-circle',     'Selesai'],
        'dibatalkan'           => ['badge-danger',    'fas fa-times-circle',     'Dibatalkan'],
    ];
    [$kelas, $ikon, $label] = $map[$status] ?? ['badge-light', 'fas fa-circle', $status];
    return "<span class=\"badge badge-pill {$kelas}\" style=\"font-size:.75rem;\">
                <i class=\"{$ikon} mr-1\"></i>{$label}
            </span>";
}

/**
 * Label shift dari kode shift_definisi
 */
function label_shift(?string $kode, ?string $label_db = null): string
{
    if ($label_db) {
        return "<small class=\"text-muted\">{$label_db}</small>";
    }
    $map = [
        'PAGI'          => 'Shift Pagi (08–14)',
        'SIANG'         => 'Shift Siang (14–20)',
        'MALAM'         => 'Shift Malam (20–08)',
        'PANJANG_SIANG' => 'Shift Siang (08–20)',
        'PANJANG_MALAM' => 'Shift Malam (20–08)',
    ];
    return $map[$kode] ?? '<span class="text-muted">—</span>';
}

/**
 * Bangun URL filter sambil mempertahankan parameter lain
 */
function url_filter(array $params = []): string
{
    $default = [
        'dari'   => $_GET['dari']   ?? '',
        'sampai' => $_GET['sampai'] ?? '',
        'status' => $_GET['status'] ?? '',
        'triase' => $_GET['triase'] ?? '',
        'hal'    => 1,
    ];
    $merged = array_merge($default, $params);
    return '?' . http_build_query(array_filter($merged, fn($v) => $v !== ''));
}
?>

<div class="main-content">

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
    <?php require '../template/navbar.php'; ?>

    <!-- ======================================================= -->
    <!-- HEADER                                                   -->
    <!-- ======================================================= -->
    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">

                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-clipboard-list mr-2"></i>
                            <?= $id_tim_login !== null ? 'Dashboard Tim — Kasus Saya' : 'Laporan Masuk — PSC 119'; ?>
                        </h6>
                        <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
                            <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
                                <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i></a></li>
                                <li class="breadcrumb-item active">Laporan Masuk</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <?php if ($nama_role === 'operator' || $nama_role === 'admin') : ?>
                            <a href="../inputan_laporan/" class="btn btn-sm btn-neutral shadow">
                                <i class="fas fa-plus mr-1"></i> Input Laporan Baru
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CARD STATISTIK -->
                <div class="row">

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">
                                            Total Hari Ini
                                            <?= $id_tim_login ? '<br><small class="text-info">(Tim Anda)</small>' : ''; ?>
                                        </h5>
                                        <span class="h2 font-weight-bold mb-0"><?= (int)($stat['total'] ?? 0); ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                            <i class="fas fa-phone-alt"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    <span class="text-nowrap">Laporan masuk hari ini</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Menunggu</h5>
                                        <span class="h2 font-weight-bold mb-0"><?= (int)($stat['menunggu'] ?? 0); ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-danger text-white rounded-circle shadow">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    <span class="text-nowrap">Menunggu respon tim</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Dalam Proses</h5>
                                        <span class="h2 font-weight-bold mb-0"><?= (int)($stat['proses'] ?? 0); ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-warning text-white rounded-circle shadow">
                                            <i class="fas fa-ambulance"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    <span class="text-nowrap">Menuju / Tiba di Lokasi</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Selesai</h5>
                                        <span class="h2 font-weight-bold mb-0"><?= (int)($stat['selesai'] ?? 0); ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    <span class="text-nowrap">Kasus selesai / dirujuk</span>
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- CONTENT                                                  -->
    <!-- ======================================================= -->
    <div class="container-fluid mt--7">

        <?php if (isset($_SESSION['success'])) : ?>
            <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])) : ?>
            <div class="alert alert-danger alert-dismissible fade show shadow" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($id_tim_login !== null) : ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-shield-alt mr-2"></i>
                <strong>Mode Tim:</strong>
                Menampilkan hanya kasus yang ditugaskan ke tim Anda.
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12 mb-5">
                <div class="card shadow">

                    <!-- Card Header + Filter -->
                    <div class="card-header border-0">
                        <div class="row align-items-center mb-3">
                            <div class="col">
                                <h3 class="mb-0">
                                    <i class="fas fa-clipboard-list mr-2 text-primary"></i>
                                    Daftar Laporan Masuk
                                </h3>
                                <small class="text-muted">
                                    Total <strong><?= number_format($total_data); ?></strong> kasus
                                    ditemukan untuk filter yang dipilih
                                </small>
                            </div>
                        </div>

                        <!-- Form Filter -->
                        <form method="GET" action="" class="row align-items-end">

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <label class="form-control-label small font-weight-bold mb-1">
                                    <i class="fas fa-calendar mr-1"></i>Dari Tanggal
                                </label>
                                <input type="date" name="dari"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($filter_dari); ?>">
                            </div>

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <label class="form-control-label small font-weight-bold mb-1">
                                    <i class="fas fa-calendar mr-1"></i>Sampai Tanggal
                                </label>
                                <input type="date" name="sampai"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($filter_sampai); ?>">
                            </div>

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <label class="form-control-label small font-weight-bold mb-1">
                                    <i class="fas fa-filter mr-1"></i>Status
                                </label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">Semua Status</option>
                                    <option value="menunggu_respon"      <?= $filter_status === 'menunggu_respon'      ? 'selected' : ''; ?>>Menunggu Respon</option>
                                    <option value="menuju_lokasi"        <?= $filter_status === 'menuju_lokasi'        ? 'selected' : ''; ?>>Menuju Lokasi</option>
                                    <option value="tiba_di_lokasi"       <?= $filter_status === 'tiba_di_lokasi'       ? 'selected' : ''; ?>>Tiba di Lokasi</option>
                                    <option value="penanganan_di_tempat" <?= $filter_status === 'penanganan_di_tempat' ? 'selected' : ''; ?>>Penanganan di Tempat</option>
                                    <option value="dirujuk_ke_rs"        <?= $filter_status === 'dirujuk_ke_rs'        ? 'selected' : ''; ?>>Dirujuk ke RS</option>
                                    <option value="selesai"              <?= $filter_status === 'selesai'              ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="dibatalkan"           <?= $filter_status === 'dibatalkan'           ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <label class="form-control-label small font-weight-bold mb-1">
                                    <i class="fas fa-heartbeat mr-1"></i>Triase
                                </label>
                                <select name="triase" class="form-control form-control-sm">
                                    <option value="">Semua Triase</option>
                                    <?php while ($tr = mysqli_fetch_assoc($query_triase_filter)) : ?>
                                        <option value="<?= (int)$tr['id']; ?>"
                                            <?= $filter_triase == $tr['id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($tr['kode']); ?> — <?= htmlspecialchars($tr['label']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-search mr-1"></i> Cari
                                </button>
                            </div>

                            <div class="col-lg-2 col-md-4 col-6 mb-2">
                                <a href="index.php" class="btn btn-outline-secondary btn-sm btn-block">
                                    <i class="fas fa-undo mr-1"></i> Reset
                                </a>
                            </div>

                        </form>
                    </div>

                    <!-- =========================================== -->
                    <!-- TABEL DATA                                   -->
                    <!-- =========================================== -->
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col" class="text-center" style="width:40px;">#</th>
                                    <th scope="col">No. Kasus</th>
                                    <th scope="col">Waktu Input</th>
                                    <th scope="col">Pelapor</th>
                                    <th scope="col">Jenis Kasus</th>
                                    <th scope="col" class="text-center">Triase</th>
                                    <th scope="col" class="text-center">Shift</th>
                                    <th scope="col" class="text-center">Tim Bertugas</th>
                                    <th scope="col" class="text-center">Pasien</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php if (mysqli_num_rows($result_data) === 0) : ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Tidak ada laporan ditemukan untuk filter yang dipilih.
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_data)) : ?>
                                        <tr>

                                            <!-- Nomor urut -->
                                            <td class="text-center text-muted" style="font-size:.8rem;">
                                                <?= $no++; ?>
                                            </td>

                                            <!-- Nomor kasus -->
                                            <td>
                                                <span class="font-weight-bold text-primary"
                                                      style="font-size:.82rem; font-family:monospace;">
                                                    <?= htmlspecialchars($row['nomor_kasus']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted" style="font-size:.72rem;">
                                                    <i class="fas fa-calendar-alt fa-xs mr-1"></i>
                                                    <?= date('d/m/Y', strtotime($row['created_at'])); ?>
                                                </small>
                                            </td>

                                            <!-- Waktu kejadian & input -->
                                            <td style="white-space:nowrap; font-size:.82rem;">
                                                <?= date('d/m/Y', strtotime($row['waktu_kejadian'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock fa-xs mr-1"></i>
                                                    <?= date('H:i', strtotime($row['waktu_kejadian'])); ?> WIB
                                                </small>
                                            </td>

                                            <!-- Pelapor -->
                                            <td style="max-width:160px;">
                                                <span class="font-weight-600" style="font-size:.85rem;">
                                                    <?= htmlspecialchars($row['nama_pelapor']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone fa-xs mr-1"></i>
                                                    <?= htmlspecialchars($row['no_telp_pelapor']); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted d-block text-truncate"
                                                       title="<?= htmlspecialchars($row['lokasi_kejadian']); ?>"
                                                       style="max-width:150px;">
                                                    <i class="fas fa-map-marker-alt fa-xs mr-1"></i>
                                                    <?= htmlspecialchars($row['lokasi_kejadian']); ?>
                                                </small>
                                            </td>

                                            <!-- Jenis kasus -->
                                            <td style="font-size:.82rem;">
                                                <span class="badge badge-light border">
                                                    <?= htmlspecialchars($row['kode_jenis']); ?>
                                                </span>
                                                <?= htmlspecialchars($row['nama_jenis']); ?>
                                            </td>

                                            <!-- Triase (warna dari DB) -->
                                            <td class="text-center">
                                                <span class="badge badge-pill px-2"
                                                      style="background-color:<?= htmlspecialchars($row['warna_triase']); ?>;
                                                             color:#fff; font-size:.75rem;">
                                                    <?= htmlspecialchars($row['kode_triase']); ?> —
                                                    <?= htmlspecialchars($row['label_triase']); ?>
                                                </span>
                                            </td>

                                            <!-- Shift bertugas -->
                                            <td class="text-center">
                                                <?php if ($row['kode_shift']) : ?>
                                                    <small class="text-muted d-block"
                                                           style="font-size:.75rem; white-space:nowrap;">
                                                        <i class="fas fa-clock fa-xs mr-1"></i>
                                                        <?= htmlspecialchars($row['label_shift'] ?? $row['kode_shift']); ?>
                                                    </small>
                                                    <?php if ($row['tanggal_shift']) : ?>
                                                        <small class="text-muted" style="font-size:.68rem;">
                                                            <?= date('d/m/Y', strtotime($row['tanggal_shift'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Tim bertugas -->
                                            <td class="text-center">
                                                <?php if ($row['nama_tim']) : ?>
                                                    <span class="badge badge-pill badge-warning px-2"
                                                          style="font-size:.8rem;">
                                                        <i class="fas fa-shield-alt mr-1"></i>
                                                        <?= htmlspecialchars($row['nama_tim']); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="badge badge-pill badge-secondary px-2"
                                                          style="font-size:.8rem;">
                                                        <i class="fas fa-minus mr-1"></i> Belum Ditugaskan
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Jumlah pasien -->
                                            <td class="text-center">
                                                <span class="badge badge-pill badge-info px-2"
                                                      style="font-size:.8rem;">
                                                    <i class="fas fa-user-injured mr-1"></i>
                                                    <?= (int)$row['jumlah_pasien']; ?>
                                                </span>
                                            </td>

                                            <!-- Status -->
                                            <td class="text-center">
                                                <?= badge_status($row['status_terakhir']); ?>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-center" style="white-space:nowrap;">
                                                <a href="detail_kasus.php?id=<?= (int)$row['id_kasus']; ?>"
                                                   class="btn btn-icon-only btn-sm btn-outline-primary"
                                                   title="Lihat Detail Kasus"
                                                   data-toggle="tooltip">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>

                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>

                    <!-- =========================================== -->
                    <!-- PAGINATION                                   -->
                    <!-- =========================================== -->
                    <?php if ($total_halaman > 1) : ?>
                        <div class="card-footer py-3">
                            <div class="row align-items-center">
                                <div class="col-sm-6 text-muted small">
                                    Menampilkan
                                    <?= ($offset + 1); ?>–<?= min($offset + $per_halaman, $total_data); ?>
                                    dari <?= number_format($total_data); ?> laporan
                                </div>
                                <div class="col-sm-6">
                                    <nav aria-label="Pagination">
                                        <ul class="pagination pagination-sm justify-content-end mb-0">

                                            <li class="page-item <?= $halaman <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link"
                                                   href="<?= url_filter(['hal' => $halaman - 1]); ?>">
                                                    <i class="fas fa-angle-left"></i>
                                                </a>
                                            </li>

                                            <?php
                                            $mulai = max(1, $halaman - 2);
                                            $akhir = min($total_halaman, $halaman + 2);
                                            if ($mulai > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="' . url_filter(['hal' => 1]) . '">1</a></li>';
                                                if ($mulai > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                            }
                                            for ($p = $mulai; $p <= $akhir; $p++) {
                                                $aktif = ($p === $halaman) ? 'active' : '';
                                                echo "<li class=\"page-item {$aktif}\">
                                                        <a class=\"page-link\" href=\"" . url_filter(['hal' => $p]) . "\">{$p}</a>
                                                      </li>";
                                            }
                                            if ($akhir < $total_halaman) {
                                                if ($akhir < $total_halaman - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="' . url_filter(['hal' => $total_halaman]) . '">' . $total_halaman . '</a></li>';
                                            }
                                            ?>

                                            <li class="page-item <?= $halaman >= $total_halaman ? 'disabled' : ''; ?>">
                                                <a class="page-link"
                                                   href="<?= url_filter(['hal' => $halaman + 1]); ?>">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>

                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <?php require '../template/footer.php'; ?>

    </div>
</div>
<style>
    /* ===================================================== */
/* PERBAIKAN TABEL AGAR TIDAK SCROLL HORIZONTAL          */
/* ===================================================== */

/* Untuk semua layar, buat tabel lebih ringkas */
.table td, .table th {
    padding: 0.5rem 0.3rem !important;
    font-size: 0.75rem !important;
    white-space: normal !important;
    word-break: break-word !important;
}

/* Sembunyikan kolom yang kurang penting di layar kecil */
@media (max-width: 992px) {
    .table th:nth-child(4),  /* Pelapor */
    .table td:nth-child(4),
    .table th:nth-child(7),  /* Shift */
    .table td:nth-child(7),
    .table th:nth-child(9),  /* Pasien */
    .table td:nth-child(9) {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .table th:nth-child(5),  /* Jenis Kasus */
    .table td:nth-child(5),
    .table th:nth-child(8),  /* Tim Bertugas */
    .table td:nth-child(8) {
        display: none !important;
    }
    .table th:nth-child(3),  /* Waktu Input */
    .table td:nth-child(3) {
        display: none !important;
    }
}

/* Pastikan container tabel tidak overflow */
.table-responsive {
    overflow-x: auto !important; /* tetap izinkan scroll jika sangat kecil, tapi dengan konten minimal */
}
</style>
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>



<!-- ============================================================ -->
<!-- END OF FILE — laporan_masuk/index.php                       -->
<!-- PSC 119 — Pematangsiantar                                   -->
<!-- ============================================================ -->
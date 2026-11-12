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

// Nama user (pembuat laporan) dari session login
$nama_pembuat = $user_data['nama_lengkap'] ?? 'Administrator';

// Jabatan user yang mencetak laporan
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
if (!in_array($role_id, [1, 3])) {
    header('Location: ../dashboard.php');
    exit;
}

$title = 'Rekap & Statistik Kasus';
require '../template/header.php';
require '../template/sidebar.php';

// Filter bulan dan tahun
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Statistik berdasarkan triase
$sql_triase = "SELECT t.label, t.warna_hex, COUNT(k.id) as jumlah
               FROM kasus k
               JOIN triase t ON t.id = k.triase_id
               WHERE MONTH(k.waktu_kejadian) = ? AND YEAR(k.waktu_kejadian) = ?
               GROUP BY t.id
               ORDER BY t.urgensi ASC";
$stmt_t = mysqli_prepare($koneksi, $sql_triase);
mysqli_stmt_bind_param($stmt_t, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_t);
$stat_triase = mysqli_fetch_all(mysqli_stmt_get_result($stmt_t), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_t);

// Top 5 jenis kasus
$sql_jenis = "SELECT j.nama, COUNT(k.id) as jumlah
              FROM kasus k
              JOIN jenis_kasus j ON j.id = k.jenis_kasus_id
              WHERE MONTH(k.waktu_kejadian) = ? AND YEAR(k.waktu_kejadian) = ?
              GROUP BY j.id
              ORDER BY jumlah DESC
              LIMIT 5";
$stmt_j = mysqli_prepare($koneksi, $sql_jenis);
mysqli_stmt_bind_param($stmt_j, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_j);
$stat_jenis = mysqli_fetch_all(mysqli_stmt_get_result($stmt_j), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_j);

// Total kasus
$sql_total = "SELECT COUNT(*) as total FROM kasus WHERE MONTH(waktu_kejadian)=? AND YEAR(waktu_kejadian)=?";
$stmt_total = mysqli_prepare($koneksi, $sql_total);
mysqli_stmt_bind_param($stmt_total, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_total);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_total))['total'];
mysqli_stmt_close($stmt_total);

// Kasus selesai
$sql_selesai = "SELECT COUNT(*) as jml FROM kasus WHERE MONTH(waktu_kejadian)=? AND YEAR(waktu_kejadian)=? AND status_terakhir='selesai'";
$stmt_selesai = mysqli_prepare($koneksi, $sql_selesai);
mysqli_stmt_bind_param($stmt_selesai, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_selesai);
$selesai = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_selesai))['jml'];
mysqli_stmt_close($stmt_selesai);

// Rata-rata response time
$sql_response = "SELECT AVG(TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, rs.waktu)) as rata_menit
                 FROM kasus k
                 JOIN riwayat_status_kasus rs ON rs.kasus_id = k.id AND rs.status = 'tiba_di_lokasi'
                 WHERE MONTH(k.waktu_kejadian)=? AND YEAR(k.waktu_kejadian)=?
                 AND rs.waktu IS NOT NULL";
$stmt_resp = mysqli_prepare($koneksi, $sql_response);
mysqli_stmt_bind_param($stmt_resp, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_resp);
$response = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_resp));
mysqli_stmt_close($stmt_resp);
$rata_response = isset($response['rata_menit']) ? round($response['rata_menit']) : 0;

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

    .card-stats .card-body {
        padding: 1rem;
    }

    .legalization-section {
        display: none;
    }
    
    /* Style untuk badge triase */
    .triase-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        color: white !important;
        font-weight: bold;
        text-align: center;
        min-width: 70px;
    }
</style>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    
    <div class="print-header" style="display: none;">
        <div class="print-header-inner" style="text-align: center; margin-bottom: 20px;">
            <img src="<?= $base_url ?>assets/assets/img/Logo.png" alt="Logo PSC119" style="height: 70px;">
            <h2 style="margin: 10px 0 5px; font-weight: bold;">PSC 119</h2>
            <p style="margin: 0; font-size: 14px;">Laporan Data Penangganan Kasus Gawat Darurat</p>
            <p style="margin: 0; font-size: 12px;">Dinas Kesehatan Kota Pematangsiantar</p>
            <hr style="margin: 10px 0; border: 1px solid #000;">
        </div>
    </div>
    
    <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-chart-line mr-2"></i> Rekap & Statistik Kasus</h6>
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
        <!-- Filter -->
        <div class="row mb-3">
            <div class="col">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="GET" class="form-inline">
                            <label class="mr-2">Bulan:</label>
                            <select name="bulan" class="form-control form-control-sm mr-3">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $bulan ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <label class="mr-2">Tahun:</label>
                            <select name="tahun" class="form-control form-control-sm mr-3">
                                <?php $thn_skrg = date('Y');
                                for ($th = $thn_skrg - 2; $th <= $thn_skrg + 1; $th++): ?>
                                    <option value="<?= $th ?>" <?= $th == $tahun ? 'selected' : '' ?>><?= $th ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary mr-2">Tampilkan</button>
                            <a href="?bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" class="btn btn-sm btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ringkasan angka -->
        <div class="row">
            <div class="col-md-4">
                <div class="card card-stats shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Total Kasus</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $total ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-danger text-white rounded-circle shadow">
                                    <i class="fas fa-ambulance"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm mb-0 text-muted">Bulan <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stats shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Kasus Selesai</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $selesai ?></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-success text-white rounded-circle shadow">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm mb-0 text-muted"><?= $total > 0 ? round(($selesai / $total) * 100, 1) : 0 ?>% dari total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stats shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title text-uppercase text-muted mb-0">Rata-rata Response</h5>
                                <span class="h2 font-weight-bold mb-0"><?= $rata_response ?> <small class="text-muted">menit</small></span>
                            </div>
                            <div class="col-auto">
                                <div class="icon icon-shape bg-warning text-white rounded-circle shadow">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm mb-0 text-muted">Waktu kejadian s/d tiba di lokasi</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Triase -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h4>Statistik Berdasarkan Triase</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Triase</th>
                                    <th>Jumlah Kasus</th>
                                    <th>Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stat_triase as $st):
                                    $persen = $total > 0 ? round(($st['jumlah'] / $total) * 100, 1) : 0;
                                    // Tentukan warna teks berdasarkan background
                                    $warna_hex = $st['warna_hex'];
                                    $warna_text = '#fff';
                                    if ($warna_hex == '#FFFF00' || $warna_hex == '#FFEB3B') {
                                        $warna_text = '#000';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="triase-badge" style="background-color: <?= $warna_hex ?> !important; color: <?= $warna_text ?> !important;">
                                                <?= htmlspecialchars($st['label']) ?>
                                            </div>
                                        </span>
                                        <td><?= $st['jumlah'] ?></td>
                                        <td><?= $persen ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h4>Top 5 Jenis Kasus</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Jenis Kasus</th><th>Jumlah</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stat_jenis as $sj): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sj['nama']) ?></td>
                                        <td><?= $sj['jumlah'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($stat_jenis)): ?>
                                    <tr><td colspan="2" class="text-center">Tidak ada</span></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Grafik Kasus per Triase</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart" style="height: 250px;">
                            <canvas id="triaseChart" class="chart-canvas"></canvas>
                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
    Chart.register(ChartDataLabels);
    var labels = [<?php foreach ($stat_triase as $st) echo '"' . $st['label'] . '",'; ?>];
    var dataJumlah = [<?php foreach ($stat_triase as $st) echo $st['jumlah'] . ','; ?>];
    var bgColors = [<?php foreach ($stat_triase as $st) echo '"' . $st['warna_hex'] . '",'; ?>];
    var ctx = document.getElementById('triaseChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Jumlah Kasus', data: dataJumlah, backgroundColor: bgColors, borderWidth: 0, borderRadius: 4, barPercentage: 0.6, categoryPercentage: 0.8, clip: false }] },
        options: {
            responsive: true, maintainAspectRatio: true,
            layout: { padding: { top: 20 } },
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                datalabels: { anchor: 'end', align: 'top', offset: 4, color: '#000', font: { weight: 'bold', size: 12 }, formatter: (value) => value > 0 ? value : '' }
            },
            scales: {
                y: { beginAtZero: true, stepSize: 1, grid: { color: "rgba(0,0,0,0.05)" }, title: { display: true, text: 'Jumlah Kasus', font: { size: 11 } }, ticks: { font: { size: 10 }, precision: 0 } },
                x: { title: { display: true, text: 'Triase', font: { size: 11 } }, ticks: { font: { size: 10 } } }
            }
        }
    });
</script>

<!-- CSS PRINT - DIPAKSA WARNA BADGE KELUAR -->
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
</style>
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}
require_once 'koneksi.php';

$user_id  = (int) $_SESSION['id'];
$role_name = $_SESSION['nama_role'] ?? '';

// ambil tim kalau tim_respon
$tim_user = null;
if ($role_name === 'tim_respon') {
    $stmt = mysqli_prepare($koneksi, "SELECT tim_id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL ORDER BY tgl_masuk DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($r)) $tim_user = (int)$row['tim_id'];
    mysqli_stmt_close($stmt);
}

// filter periode
$periode = $_GET['periode'] ?? 'bulan_ini';
$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if ($periode == 'hari_ini') {
    $tgl_mulai = $tgl_selesai = date('Y-m-d');
} elseif ($periode == 'minggu_ini') {
    $tgl_mulai = date('Y-m-d', strtotime('monday this week'));
    $tgl_selesai = date('Y-m-d', strtotime('sunday this week'));
} elseif ($periode == 'tahun_ini') {
    $tgl_mulai = date('Y-01-01');
    $tgl_selesai = date('Y-12-31');
} elseif ($periode == 'custom' && $tgl_mulai && $tgl_selesai) {
    // keep
} else {
    $periode = 'bulan_ini';
    $tgl_mulai = date('Y-m-01');
    $tgl_selesai = date('Y-m-t');
}

$where_tgl = "DATE(created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'";

// statistik dasar
$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus"); 
$total_kasus = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE status_terakhir NOT IN ('selesai','dibatalkan')");
$kasus_aktif = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE DATE(created_at) = CURDATE()");
$kasus_hari_ini = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE status_terakhir = 'menunggu_respon'");
$menunggu = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE status_terakhir = 'selesai' AND DATE(created_at) = CURDATE()");
$selesai_hari_ini = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE status_terakhir IN ('menuju_lokasi','tiba_di_lokasi','penanganan_di_tempat','dirujuk_ke_rs')");
$ditugaskan = (int)mysqli_fetch_assoc($q)['c'];

$q = mysqli_query($koneksi, "SELECT AVG(TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, rs.waktu)) rata FROM riwayat_status_kasus rs JOIN kasus k ON k.id = rs.kasus_id WHERE rs.status = 'tiba_di_lokasi'");
$rata_respon = round(mysqli_fetch_assoc($q)['rata'] ?? 0);

// Kasus merah yang aktif dan sudah >5 menit sejak kejadian (belum tiba di lokasi atau belum selesai)
$q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus k 
    WHERE k.triase_id = (SELECT id FROM triase WHERE label='Merah' LIMIT 1) 
    AND k.status_terakhir NOT IN ('selesai','dibatalkan') 
    AND TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, NOW()) > 5");
$terlambat_merah = (int)mysqli_fetch_assoc($q)['c'];

// kasus tim
$kasus_tim = 0;
if ($tim_user) {
    $q = mysqli_query($koneksi, "SELECT COUNT(*) c FROM kasus WHERE tim_id = $tim_user AND status_terakhir NOT IN ('selesai','dibatalkan')");
    $kasus_tim = (int)mysqli_fetch_assoc($q)['c'];
}

// tren kasus - bisa per hari/bulan/tahun tergantung pilihan
$tren_mode = $_GET['tren_mode'] ?? 'harian';
$tren_labels = $tren_data = [];

if ($tren_mode == 'bulanan') {
    $res = mysqli_query($koneksi, "SELECT DATE_FORMAT(created_at, '%Y-%m') tgl, COUNT(*) jml FROM kasus WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY tgl");
    while ($r = mysqli_fetch_assoc($res)) { $tren_labels[] = date('M Y', strtotime($r['tgl'].'-01')); $tren_data[] = $r['jml']; }
} elseif ($tren_mode == 'tahunan') {
    $res = mysqli_query($koneksi, "SELECT YEAR(created_at) tgl, COUNT(*) jml FROM kasus GROUP BY YEAR(created_at) ORDER BY tgl");
    while ($r = mysqli_fetch_assoc($res)) { $tren_labels[] = $r['tgl']; $tren_data[] = $r['jml']; }
} else {
    $tren_mode = 'harian';
    $res = mysqli_query($koneksi, "SELECT DATE(created_at) tgl, COUNT(*) jml FROM kasus WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY tgl");
    while ($r = mysqli_fetch_assoc($res)) { $tren_labels[] = date('d/m', strtotime($r['tgl'])); $tren_data[] = $r['jml']; }
}

// data grafik triase
$triase_labels = $triase_data = $triase_colors = [];
$color_map = ['Merah'=>'#f5365c','Kuning'=>'#fb6340','Hijau'=>'#2dce89','Hitam'=>'#212529'];
$res = mysqli_query($koneksi, "SELECT t.label, COUNT(k.id) jml FROM kasus k JOIN triase t ON t.id = k.triase_id WHERE $where_tgl GROUP BY t.id ORDER BY t.urgensi");
while ($r = mysqli_fetch_assoc($res)) { $triase_labels[] = $r['label']; $triase_data[] = $r['jml']; $triase_colors[] = $color_map[$r['label']] ?? '#adb5bd'; }

// 5 kasus terbaru
$res = mysqli_query($koneksi, "SELECT k.nomor_kasus, k.nama_pelapor, t.label triase, k.status_terakhir, k.created_at FROM kasus k JOIN triase t ON t.id = k.triase_id ORDER BY k.created_at DESC LIMIT 5");
$kasus_terbaru = mysqli_fetch_all($res, MYSQLI_ASSOC);

// kasus menunggu respon (operator)
$kasus_menunggu = [];
if ($role_name == 'operator') {
    $res = mysqli_query($koneksi, "SELECT k.nomor_kasus, k.nama_pelapor, t.label triase, k.created_at FROM kasus k JOIN triase t ON t.id = k.triase_id WHERE k.status_terakhir = 'menunggu_respon' ORDER BY k.created_at DESC LIMIT 8");
    $kasus_menunggu = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

// kasus aktif tim respon
$kasus_tim_aktif = [];
if ($tim_user) {
    $res = mysqli_query($koneksi, "SELECT k.id, k.nomor_kasus, k.lokasi_kejadian, t.label triase, k.status_terakhir, TIMESTAMPDIFF(MINUTE, k.waktu_kejadian, NOW()) menit FROM kasus k JOIN triase t ON t.id = k.triase_id WHERE k.tim_id = $tim_user AND k.status_terakhir NOT IN ('selesai','dibatalkan') ORDER BY k.created_at DESC");
    $kasus_tim_aktif = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

// log aktivitas (admin/koordinator)
$aktivitas = [];
if (in_array($role_name, ['admin','koordinator'])) {
    $res = mysqli_query($koneksi, "SELECT u.nama_lengkap, la.aksi, la.deskripsi, la.created_at FROM log_aktivitas la JOIN users u ON u.id = la.user_id ORDER BY la.created_at DESC LIMIT 8");
    $aktivitas = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

// jadwal shift hari ini (koordinator)
$jadwal = [];
if ($role_name == 'koordinator') {
    $res = mysqli_query($koneksi, "SELECT t.nama_tim, sd.label shift, js.catatan FROM jadwal_shift js JOIN tim t ON t.id = js.tim_id JOIN shift_definisi sd ON sd.id = js.shift_definisi_id WHERE js.tanggal = CURDATE() ORDER BY sd.jam_mulai");
    $jadwal = mysqli_fetch_all($res, MYSQLI_ASSOC);
}

$label_status = [
    'menunggu_respon' => 'Menunggu', 'menuju_lokasi' => 'Menuju',
    'tiba_di_lokasi' => 'Tiba', 'penanganan_di_tempat' => 'Penanganan',
    'dirujuk_ke_rs' => 'Dirujuk RS', 'selesai' => 'Selesai', 'dibatalkan' => 'Batal'
];

$title = 'Dashboard';
require 'template/header.php';
require 'template/sidebar.php';
?>

<div class="main-content">
  <?php require 'template/navbar.php'; ?>

  <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
      <div class="header-body">

        <?php if (in_array($role_name, ['admin','koordinator'])): ?>
        <div class="row align-items-center mb-4">
          <div class="col">
            <h6 class="h2 text-white mb-0">Dashboard <?= ucfirst(str_replace('_',' ',$role_name)) ?></h6>
          </div>
          <div class="col text-right">
            <!-- <form method="GET" class="form-inline justify-content-end">
              <select name="periode" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="hari_ini"   <?= $periode=='hari_ini'   ?'selected':'' ?>>Hari Ini</option>
                <option value="minggu_ini" <?= $periode=='minggu_ini' ?'selected':'' ?>>Minggu Ini</option>
                <option value="bulan_ini"  <?= $periode=='bulan_ini'  ?'selected':'' ?>>Bulan Ini</option>
                <option value="tahun_ini"  <?= $periode=='tahun_ini'  ?'selected':'' ?>>Tahun Ini</option>
                <option value="custom"     <?= $periode=='custom'     ?'selected':'' ?>>Custom</option>
              </select>
              <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="form-control form-control-sm mr-1">
              <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="form-control form-control-sm mr-1">
              <button type="submit" class="btn btn-sm btn-neutral">Filter</button>
              <a href="?" class="btn btn-sm btn-neutral ml-1">Reset</a>
            </form> -->
          </div>
        </div>
        <?php endif; ?>

        <!-- stat cards -->
        <div class="row">

          <?php if ($role_name == 'admin'): ?>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Total Kasus</h5>
                      <span class="h2 font-weight-bold mb-0"><?= number_format($total_kasus) ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-danger text-white rounded-circle shadow">
                        <i class="fas fa-database"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Semua periode</span>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Kasus Aktif</h5>
                      <span class="h2 font-weight-bold mb-0"><?= number_format($kasus_aktif) ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-warning text-white rounded-circle shadow">
                        <i class="fas fa-fire"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Belum selesai/batal</span>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Hari Ini</h5>
                      <span class="h2 font-weight-bold mb-0"><?= number_format($kasus_hari_ini) ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-yellow text-white rounded-circle shadow">
                        <i class="fas fa-calendar-day"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Kasus masuk hari ini</span>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Rata Respon</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $rata_respon ?> <small class="text-muted" style="font-size:14px">mnt</small></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-info text-white rounded-circle shadow">
                        <i class="fas fa-clock"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Waktu tiba di lokasi</span>
                  </p>
                </div>
              </div>
            </div>

          <?php elseif ($role_name == 'operator'): ?>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Menunggu Respon</h5>
                      <span class="h2 font-weight-bold mb-0 <?= $menunggu > 0 ? 'text-danger':'' ?>"><?= $menunggu ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-danger text-white rounded-circle shadow">
                        <i class="fas fa-clock"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Belum ditugaskan ke tim</span>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Ditugaskan</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $ditugaskan ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-warning text-white rounded-circle shadow">
                        <i class="fas fa-ambulance"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Sedang ditangani tim</span>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Selesai Hari Ini</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $selesai_hari_ini ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-success text-white rounded-circle shadow">
                        <i class="fas fa-check-circle"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm">
                    <span class="text-nowrap">Kasus ditutup hari ini</span>
                  </p>
                </div>
              </div>
            </div>

          <?php elseif ($role_name == 'koordinator'): ?>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Kasus Aktif</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $kasus_aktif ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-danger text-white rounded-circle shadow">
                        <i class="fas fa-ambulance"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Sedang berjalan</span></p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Menunggu</h5>
                      <span class="h2 font-weight-bold mb-0 <?= $menunggu > 0 ? 'text-warning':'' ?>"><?= $menunggu ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-warning text-white rounded-circle shadow">
                        <i class="fas fa-hourglass-half"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Belum ada tim</span></p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Rata Respon</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $rata_respon ?> <small style="font-size:13px" class="text-muted">mnt</small></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-info text-white rounded-circle shadow">
                        <i class="fas fa-stopwatch"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Rata-rata tiba lokasi</span></p>
                </div>
              </div>
            </div>
            <div class="col-xl-3 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Terlambat Merah</h5>
                      <span class="h2 font-weight-bold mb-0 <?= $terlambat_merah > 0 ? 'text-danger':'' ?>"><?= $terlambat_merah ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-dark text-white rounded-circle shadow">
                        <i class="fas fa-exclamation-triangle"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Respon &gt;5 menit</span></p>
                </div>
              </div>
            </div>

          <?php elseif ($role_name == 'tim_respon' && $tim_user): ?>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Kasus Tim Aktif</h5>
                      <span class="h2 font-weight-bold mb-0 <?= $kasus_tim > 0 ? 'text-danger':'' ?>"><?= $kasus_tim ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-danger text-white rounded-circle shadow">
                        <i class="fas fa-tasks"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Ditugaskan ke tim Anda</span></p>
                </div>
              </div>
            </div>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Selesai Hari Ini</h5>
                      <span class="h2 font-weight-bold mb-0"><?= $selesai_hari_ini ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-success text-white rounded-circle shadow">
                        <i class="fas fa-check-double"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Ditutup hari ini</span></p>
                </div>
              </div>
            </div>
            <div class="col-xl-4 col-lg-6">
              <div class="card card-stats mb-4 mb-xl-0">
                <div class="card-body">
                  <div class="row">
                    <div class="col">
                      <h5 class="card-title text-uppercase text-muted mb-0">Total Kasus</h5>
                      <span class="h2 font-weight-bold mb-0"><?= number_format($total_kasus) ?></span>
                    </div>
                    <div class="col-auto">
                      <div class="icon icon-shape bg-info text-white rounded-circle shadow">
                        <i class="fas fa-chart-line"></i>
                      </div>
                    </div>
                  </div>
                  <p class="mt-3 mb-0 text-muted text-sm"><span class="text-nowrap">Semua periode</span></p>
                </div>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid mt--7">

    <!-- charts: tren & triase -->
    <!-- <?php if (in_array($role_name, ['admin','koordinator'])): ?>
    <div class="row">
      <div class="col-xl-8 mb-5 mb-xl-0">
        <div class="card bg-gradient-default shadow">
          <div class="card-header bg-transparent">
            <div class="row align-items-center">
              <div class="col">
                <h6 class="text-uppercase text-light ls-1 mb-1">Statistik</h6>
                <h2 class="text-white mb-0">Tren Kasus Masuk</h2>
              </div>
              <div class="col">
                <ul class="nav nav-pills justify-content-end">
                  <li class="nav-item mr-2">
                    <a href="?<?= http_build_query(array_merge($_GET, ['tren_mode'=>'harian'])) ?>" class="nav-link py-2 px-3 <?= $tren_mode=='harian'?'active':'' ?>">
                      <span class="d-none d-md-block">Harian</span>
                      <span class="d-md-none">H</span>
                    </a>
                  </li>
                  <li class="nav-item mr-2">
                    <a href="?<?= http_build_query(array_merge($_GET, ['tren_mode'=>'bulanan'])) ?>" class="nav-link py-2 px-3 <?= $tren_mode=='bulanan'?'active':'' ?>">
                      <span class="d-none d-md-block">Bulanan</span>
                      <span class="d-md-none">B</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="?<?= http_build_query(array_merge($_GET, ['tren_mode'=>'tahunan'])) ?>" class="nav-link py-2 px-3 <?= $tren_mode=='tahunan'?'active':'' ?>">
                      <span class="d-none d-md-block">Tahunan</span>
                      <span class="d-md-none">T</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="chart-tren" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="card shadow">
          <div class="card-header bg-transparent">
            <div class="row align-items-center">
              <div class="col">
                <h6 class="text-uppercase text-muted ls-1 mb-1">Periode dipilih</h6>
                <h2 class="mb-0">Kasus per Triase</h2>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="chart-triase" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?> -->

    <!-- tabel kasus terbaru + menunggu/tim aktif -->
    <div class="row mt-5">
      <div class="col-xl-8 mb-5 mb-xl-0">
        <div class="card shadow">
          <div class="card-header border-0">
            <div class="row align-items-center">
              <div class="col">
                <h3 class="mb-0">Kasus Terbaru</h3>
              </div>
              <?php if (
                $_SESSION['nama_role'] == 'admin' ||
                $_SESSION['nama_role'] == 'koordinator'
            ): ?>
              <div class="col text-right">
                <a href="<?= $base_url ?>laporan_masuk/" class="btn btn-sm btn-primary">Lihat Semua</a>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-items-center table-flush">
              <thead class="thead-light">
                <tr>
                  <th>No. Kasus</th>
                  <th>Pelapor</th>
                  <th>Triase</th>
                  <th>Status</th>
                  <th>Waktu</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($kasus_terbaru as $k): 
                  $triase_cls = ['Merah'=>'danger','Kuning'=>'warning','Hijau'=>'success','Hitam'=>'dark'];
                  $cls = $triase_cls[$k['triase']] ?? 'secondary';
                ?>
                <tr>
                  <td><?= htmlspecialchars($k['nomor_kasus']) ?></td>
                  <td><?= htmlspecialchars($k['nama_pelapor']) ?></td>
                  <td><span class="badge badge-<?= $cls ?>"><?= $k['triase'] ?></span></td>
                  <td><?= $label_status[$k['status_terakhir']] ?? ucfirst(str_replace('_',' ',$k['status_terakhir'])) ?></td>
                  <td class="text-muted"><?= date('H:i', strtotime($k['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-xl-4">
        <?php if ($role_name == 'operator' && !empty($kasus_menunggu)): ?>
        <div class="card shadow">
          <div class="card-header border-0 bg-danger">
            <h3 class="mb-0 text-white"><i class="fas fa-bell mr-2"></i>Menunggu Respon</h3>
          </div>
          <div class="table-responsive">
            <table class="table align-items-center table-flush">
              <thead class="thead-light">
                <tr><th>No. Kasus</th><th>Triase</th><th>Pukul</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($kasus_menunggu as $k): 
                  $cls = ['Merah'=>'danger','Kuning'=>'warning','Hijau'=>'success','Hitam'=>'dark'][$k['triase']] ?? 'secondary';
                ?>
                <tr>
                  <td><?= htmlspecialchars($k['nomor_kasus']) ?></td>
                  <td><span class="badge badge-<?= $cls ?>"><?= $k['triase'] ?></span></td>
                  <td><?= date('H:i', strtotime($k['created_at'])) ?></td>
                  <td><a href="<?= $base_url ?>inputan_laporan/" class="btn btn-sm btn-outline-primary">Tugaskan</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php elseif ($role_name == 'tim_respon' && !empty($kasus_tim_aktif)): ?>
        <div class="card shadow">
          <div class="card-header border-0">
            <div class="row align-items-center">
              <div class="col"><h3 class="mb-0">Kasus Tim Anda</h3></div>
              <div class="col text-right"><a href="?refresh=1" class="btn btn-sm btn-neutral"><i class="fas fa-sync-alt"></i></a></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-items-center table-flush">
              <thead class="thead-light">
                <tr><th>No. Kasus</th><th>Triase</th><th>Durasi</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($kasus_tim_aktif as $k):
                  $cls = ['Merah'=>'danger','Kuning'=>'warning','Hijau'=>'success','Hitam'=>'dark'][$k['triase']] ?? 'secondary';
                  $dur = $k['menit'] < 60 ? $k['menit'].'m' : floor($k['menit']/60).'j';
                ?>
                <tr>
                  <td><?= htmlspecialchars($k['nomor_kasus']) ?></td>
                  <td><span class="badge badge-<?= $cls ?>"><?= $k['triase'] ?></span></td>
                  <td class="<?= $k['menit'] > 15 && $k['triase'] == 'Merah' ? 'text-danger font-weight-bold' : 'text-muted' ?>"><?= $dur ?></td>
                  <td><a href="<?= $base_url ?>update_penanganan/edit.php?id=<?= $k['id'] ?>" class="btn btn-sm btn-outline-success">Update</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php elseif ($role_name == 'koordinator' && !empty($jadwal)): ?>
        <div class="card shadow">
          <div class="card-header border-0">
            <div class="row align-items-center">
              <div class="col"><h3 class="mb-0">Shift Hari Ini</h3></div>
              <div class="col text-right"><a href="<?= $base_url ?>jadwal_tim/tambah_range.php" class="btn btn-sm btn-primary">+ Jadwal</a></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-items-center table-flush">
              <thead class="thead-light">
                <tr><th>Tim</th><th>Shift</th><th>Catatan</th></tr>
              </thead>
              <tbody>
                <?php foreach ($jadwal as $j): ?>
                <tr>
                  <td><?= htmlspecialchars($j['nama_tim']) ?></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($j['shift']) ?></span></td>
                  <td class="text-muted"><?= htmlspecialchars($j['catatan'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- log aktivitas (admin/koordinator) -->
    <?php if (!empty($aktivitas)): ?>
    <div class="row mt-5">
      <div class="col">
        <div class="card shadow">
          <div class="card-header border-0">
            <h3 class="mb-0">Log Aktivitas</h3>
          </div>
          <div class="table-responsive">
            <table class="table align-items-center table-flush">
              <thead class="thead-light">
                <tr><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>Waktu</th></tr>
              </thead>
              <tbody>
                <?php foreach ($aktivitas as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['nama_lengkap']) ?></td>
                  <td><code><?= htmlspecialchars($a['aksi']) ?></code></td>
                  <td class="text-muted"><?= htmlspecialchars($a['deskripsi'] ?: '-') ?></td>
                  <td class="text-muted"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php require 'template/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
<?php if (in_array($role_name, ['admin','koordinator'])): ?>
new Chart(document.getElementById('chart-tren'), {
    type: 'line',
    data: {
        labels: <?= json_encode($tren_labels) ?>,
        datasets: [{
            label: 'Kasus',
            data: <?= json_encode($tren_data) ?>,
            borderColor: '#fb6340',
            backgroundColor: 'rgba(251,99,64,0.15)',
            borderWidth: 2,
            pointRadius: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('chart-triase'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($triase_labels) ?>,
        datasets: [{
            label: 'Jumlah Kasus',
            data: <?= json_encode($triase_data) ?>,
            backgroundColor: <?= json_encode($triase_colors) ?>,
            borderWidth: 0,
            borderRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
<?php endif; ?>
</script>

<style>
.main-content { margin-left: 260px; }
@media (max-width: 768px) { .main-content { margin-left: 0; } }
.table td, .table th { font-size: 0.82rem; }
.badge { font-size: 0.72rem; }
</style>
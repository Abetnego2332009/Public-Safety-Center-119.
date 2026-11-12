<?php
// ============================================================
//  FILE    : jadwal_shift/index.php
//  SISTEM  : PSC 119 — Sistem Pendataan Kasus Gawat Darurat
//  FUNGSI  : Koordinator membuat jadwal shift tim selama seminggu
//  AKSES   : role koordinator (role_id = 3) & admin (role_id = 1)
//
//  TABEL YANG DIGUNAKAN:
//    - jadwal_shift     : simpan jadwal per baris (tim+tanggal+shift)
//    - tim              : daftar tim aktif (TIM P, TIM S, TIM C)
//    - shift_definisi   : jenis shift (PAGI, SIANG, MALAM, dll)
//    - users            : untuk kolom dibuat_oleh
//
//  UNIQUE KEY di jadwal_shift: (tim_id, tanggal, shift_definisi_id)
//  → 1 tim tidak bisa punya shift yang sama di hari yang sama
// ============================================================

session_start();
require_once __DIR__ . '/../koneksi.php';

// ------------------------------------------------------------
// Guard: wajib login & hanya koordinator / admin
// ------------------------------------------------------------
if (
    !isset($_SESSION['sudah_login']) ||
    $_SESSION['sudah_login'] !== true ||
    !isset($_SESSION['id'])
) {
    header('Location: ../index.php');
    exit;
}

$role_id = (int) ($_SESSION['role_id'] ?? 0);
if (!in_array($role_id, [1, 3], true)) {   // 1=admin, 3=koordinator
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini.';
    header('Location: ../laporan_masuk/');
    exit;
}

$id_user_login = (int) $_SESSION['id'];

// ------------------------------------------------------------
// Tentukan minggu yang ditampilkan
// Default = minggu berjalan (Senin s/d Minggu)
// Bisa navigasi dengan ?minggu=YYYY-MM-DD (tanggal Senin)
// ------------------------------------------------------------
$param_minggu = trim($_GET['minggu'] ?? '');
if ($param_minggu && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param_minggu)) {
    $senin = new DateTime($param_minggu);
    // Pastikan selalu hari Senin
    $hari_angka = (int) $senin->format('N'); // 1=Senin, 7=Minggu
    if ($hari_angka !== 1) {
        $senin->modify('last monday');
    }
} else {
    $senin = new DateTime();
    $hari_angka = (int) $senin->format('N');
    if ($hari_angka !== 1) {
        $senin->modify('last monday');
    }
}

// Buat array 7 hari (Senin–Minggu)
$minggu_ini = [];
for ($i = 0; $i < 7; $i++) {
    $tgl = clone $senin;
    $tgl->modify("+{$i} days");
    $minggu_ini[] = $tgl->format('Y-m-d');
}
$minggu_mulai = $minggu_ini[0];
$minggu_akhir = $minggu_ini[6];

// Navigasi minggu sebelum & sesudah
$senin_sebelum = (clone $senin)->modify('-7 days')->format('Y-m-d');
$senin_sesudah = (clone $senin)->modify('+7 days')->format('Y-m-d');

// Nama hari Indonesia
$nama_hari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// ------------------------------------------------------------
// Query: semua tim aktif
// ------------------------------------------------------------
$result_tim = mysqli_query($koneksi,
    "SELECT id, kode_tim, nama_tim FROM tim WHERE is_aktif = 1 ORDER BY id ASC"
);
$daftar_tim = mysqli_fetch_all($result_tim, MYSQLI_ASSOC);

// ------------------------------------------------------------
// Query: semua jenis shift
// ------------------------------------------------------------
$result_shift = mysqli_query($koneksi,
    "SELECT id, kode_shift, label, jam_mulai, jam_selesai, durasi_jam
     FROM shift_definisi
     ORDER BY jam_mulai ASC"
);
$daftar_shift = mysqli_fetch_all($result_shift, MYSQLI_ASSOC);

// ------------------------------------------------------------
// Query: jadwal yang sudah ada untuk minggu ini
// Bentuk array: $jadwal_ada[$tanggal][$tim_id] = [shift_definisi_id, jadwal_id, catatan]
// ------------------------------------------------------------
$stmt_jadwal = mysqli_prepare($koneksi,
    "SELECT js.id, js.tim_id, js.shift_definisi_id, js.tanggal, js.catatan,
            u.nama_lengkap AS dibuat_oleh_nama
     FROM jadwal_shift js
     JOIN users u ON u.id = js.dibuat_oleh
     WHERE js.tanggal BETWEEN ? AND ?
     ORDER BY js.tanggal ASC, js.tim_id ASC"
);
mysqli_stmt_bind_param($stmt_jadwal, 'ss', $minggu_mulai, $minggu_akhir);
mysqli_stmt_execute($stmt_jadwal);
$result_jadwal = mysqli_stmt_get_result($stmt_jadwal);

$jadwal_ada = [];      // $jadwal_ada[$tanggal][$tim_id] = row
while ($row = mysqli_fetch_assoc($result_jadwal)) {
    $jadwal_ada[$row['tanggal']][$row['tim_id']] = $row;
}
mysqli_stmt_close($stmt_jadwal);

// Warna badge shift untuk UI
$warna_shift = [
    'PAGI'          => ['bg' => '#FEF3C7', 'text' => '#92400E', 'border' => '#F59E0B'],
    'SIANG'         => ['bg' => '#DBEAFE', 'text' => '#1E3A8A', 'border' => '#3B82F6'],
    'MALAM'         => ['bg' => '#EDE9FE', 'text' => '#4C1D95', 'border' => '#7C3AED'],
    'PANJANG_SIANG' => ['bg' => '#D1FAE5', 'text' => '#064E3B', 'border' => '#059669'],
    'PANJANG_MALAM' => ['bg' => '#FCE7F3', 'text' => '#831843', 'border' => '#DB2777'],
];

$ikon_shift = [
    'PAGI'          => 'fas fa-sun',
    'SIANG'         => 'fas fa-cloud-sun',
    'MALAM'         => 'fas fa-moon',
    'PANJANG_SIANG' => 'fas fa-expand-arrows-alt',
    'PANJANG_MALAM' => 'fas fa-adjust',
];

$title = 'Jadwal Shift Mingguan';
require '../template/header.php';
require '../template/sidebar.php';
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>

    <!-- ===================================================== -->
    <!-- HEADER                                                 -->
    <!-- ===================================================== -->
    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-8 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            Jadwal Shift Mingguan Tim PSC 119
                        </h6>
                        <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
                            <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
                                <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i></a></li>
                                <li class="breadcrumb-item active">Jadwal Shift</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-lg-4 col-5 text-right">
                        <span class="badge badge-pill badge-light px-3 py-2" style="font-size:.85rem;">
                            <i class="fas fa-user-tie mr-1"></i>
                            <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Koordinator'); ?>
                        </span>
                    </div>
                </div>

                <!-- KARTU STATISTIK MINGGU INI -->
                <div class="row">
                    <?php
                    $total_slot  = count($daftar_tim) * 7;
                    $total_terisi = 0;
                    foreach ($jadwal_ada as $tgl_row) $total_terisi += count($tgl_row);
                    $total_kosong = $total_slot - $total_terisi;
                    ?>
                    <div class="col-xl-4 col-md-4">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Total Slot Minggu Ini</h5>
                                        <span class="h2 font-weight-bold mb-0"><?= $total_slot; ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-primary text-white rounded-circle shadow">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    <?= count($daftar_tim); ?> tim × 7 hari
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Sudah Dijadwalkan</h5>
                                        <span class="h2 font-weight-bold mb-0 text-success"><?= $total_terisi; ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    Slot terisi jadwal shift
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-4">
                        <div class="card card-stats mb-4 mb-xl-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <h5 class="card-title text-uppercase text-muted mb-0">Belum Dijadwalkan</h5>
                                        <span class="h2 font-weight-bold mb-0 <?= $total_kosong > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?= $total_kosong; ?>
                                        </span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="icon icon-shape bg-gradient-<?= $total_kosong > 0 ? 'danger' : 'success'; ?> text-white rounded-circle shadow">
                                            <i class="fas fa-<?= $total_kosong > 0 ? 'exclamation-triangle' : 'check'; ?>"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 mb-0 text-muted text-sm">
                                    Slot belum ada jadwal
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- CONTENT                                                -->
    <!-- ===================================================== -->
    <div class="container-fluid mt--7">

        <?php if (isset($_SESSION['success'])) : ?>
            <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?= $_SESSION['success']; ?>
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

        <div class="row">
            <div class="col-xl-12 mb-5">
                <div class="card shadow">

                    <!-- CARD HEADER: Navigasi Minggu -->
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <h3 class="mb-0">
                                    <i class="fas fa-calendar-week mr-2 text-primary"></i>
                                    Jadwal Minggu:
                                    <span class="text-primary">
                                        <?= (new DateTime($minggu_mulai))->format('d M Y'); ?> —
                                        <?= (new DateTime($minggu_akhir))->format('d M Y'); ?>
                                    </span>
                                </h3>
                                <small class="text-muted">
                                    Klik sel kosong untuk menjadwalkan, klik jadwal yang ada untuk menghapus/mengubah.
                                </small>
                            </div>
                            <div class="col-auto d-flex align-items-center">
                                <a href="?minggu=<?= $senin_sebelum; ?>"
                                   class="btn btn-sm btn-outline-primary mr-2">
                                    <i class="fas fa-chevron-left mr-1"></i> Minggu Lalu
                                </a>
                                <a href="?minggu=<?= (new DateTime())->modify('last monday')->format('Y-m-d'); ?>"
                                   class="btn btn-sm btn-primary mr-2">
                                    <i class="fas fa-calendar-day mr-1"></i> Minggu Ini
                                </a>
                                <a href="?minggu=<?= $senin_sesudah; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    Minggu Depan <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- FORM INPUT JADWAL MINGGUAN                      -->
                    <!-- ============================================== -->
                    <div class="card-body">

                        <form action="proses.php" method="POST" id="formJadwal">
                            <input type="hidden" name="aksi" value="simpan_minggu">
                            <input type="hidden" name="minggu_mulai" value="<?= $minggu_mulai; ?>">
                            <input type="hidden" name="minggu_akhir" value="<?= $minggu_akhir; ?>">

                            <!-- LEGENDA SHIFT -->
                            <div class="mb-3 d-flex flex-wrap" style="gap:.5rem;">
                                <small class="text-muted mr-2 align-self-center"><strong>Legenda:</strong></small>
                                <?php foreach ($daftar_shift as $sd) :
                                    $w = $warna_shift[$sd['kode_shift']] ?? ['bg'=>'#f0f0f0','text'=>'#333','border'=>'#ccc'];
                                ?>
                                    <span class="badge px-3 py-2"
                                          style="background:<?= $w['bg']; ?>;color:<?= $w['text']; ?>;border:1px solid <?= $w['border']; ?>;font-size:.75rem;">
                                        <i class="<?= $ikon_shift[$sd['kode_shift']] ?? 'fas fa-circle'; ?> mr-1"></i>
                                        <?= htmlspecialchars($sd['kode_shift']); ?> (<?= htmlspecialchars($sd['label']); ?>)
                                    </span>
                                <?php endforeach; ?>
                                <span class="badge px-3 py-2 badge-secondary ml-auto" style="font-size:.75rem;">
                                    <i class="fas fa-minus mr-1"></i> — = Tidak Bertugas (Libur)
                                </span>
                            </div>

                            <!-- TABEL JADWAL -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0"
                                       style="min-width:900px; font-size:.84rem;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:110px; min-width:100px;">
                                                <i class="fas fa-shield-alt mr-1 text-warning"></i> Tim
                                            </th>
                                            <?php foreach ($minggu_ini as $idx => $tgl) :
                                                $dt     = new DateTime($tgl);
                                                $isToday = ($tgl === date('Y-m-d'));
                                            ?>
                                                <th class="text-center <?= $isToday ? 'bg-primary text-white' : ''; ?>"
                                                    style="min-width:120px;">
                                                    <div><?= $nama_hari[$idx]; ?></div>
                                                    <div style="font-size:.8rem; font-weight:400;">
                                                        <?= $dt->format('d M'); ?>
                                                    </div>
                                                    <?php if ($isToday) : ?>
                                                        <span class="badge badge-light badge-pill" style="font-size:.65rem;">HARI INI</span>
                                                    <?php endif; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daftar_tim as $tim) : ?>
                                        <tr>
                                            <!-- Kolom nama tim -->
                                            <td class="text-center font-weight-bold" style="vertical-align:middle;">
                                                <span class="badge badge-warning badge-pill px-3 py-2"
                                                      style="font-size:.85rem; letter-spacing:.5px;">
                                                    <i class="fas fa-shield-alt mr-1"></i>
                                                    <?= htmlspecialchars($tim['nama_tim']); ?>
                                                </span>
                                            </td>

                                            <!-- 7 kolom hari -->
                                            <?php foreach ($minggu_ini as $tgl) : ?>
                                            <?php
                                                $jadwal_baris = $jadwal_ada[$tgl][$tim['id']] ?? null;
                                                $shift_terpilih = $jadwal_baris ? (int)$jadwal_baris['shift_definisi_id'] : 0;
                                                $isToday = ($tgl === date('Y-m-d'));
                                                $isLampau = ($tgl < date('Y-m-d'));
                                            ?>
                                            <td class="text-center p-2 <?= $isToday ? 'bg-light' : ''; ?>"
                                                style="vertical-align:middle;">

                                                <select name="jadwal[<?= $tim['id']; ?>][<?= $tgl; ?>]"
                                                        class="form-control form-control-sm select-shift text-center"
                                                        data-tim="<?= htmlspecialchars($tim['nama_tim']); ?>"
                                                        data-tgl="<?= $tgl; ?>"
                                                        style="font-size:.78rem; border-radius:.5rem;"
                                                        <?= $isLampau ? 'disabled' : ''; ?>>
                                                    <option value="0">— Libur —</option>
                                                    <?php foreach ($daftar_shift as $sd) : ?>
                                                        <option value="<?= $sd['id']; ?>"
                                                                data-kode="<?= $sd['kode_shift']; ?>"
                                                                <?= $shift_terpilih == $sd['id'] ? 'selected' : ''; ?>>
                                                            <?= htmlspecialchars($sd['kode_shift']); ?>
                                                            (<?= htmlspecialchars($sd['label']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <?php if ($jadwal_baris) : ?>
                                                    <small class="text-muted d-block mt-1" style="font-size:.68rem;">
                                                        <i class="fas fa-user fa-xs mr-1"></i>
                                                        <?= htmlspecialchars($jadwal_baris['dibuat_oleh_nama']); ?>
                                                    </small>
                                                    <!-- Hidden field untuk menyimpan jadwal_id (untuk update/delete) -->
                                                    <input type="hidden"
                                                           name="jadwal_id[<?= $tim['id']; ?>][<?= $tgl; ?>]"
                                                           value="<?= (int)$jadwal_baris['id']; ?>">
                                                <?php endif; ?>

                                                <?php if ($isLampau) : ?>
                                                    <small class="text-muted d-block" style="font-size:.65rem;">
                                                        <i class="fas fa-lock fa-xs"></i> Terkunci
                                                    </small>
                                                <?php endif; ?>

                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Catatan (opsional) -->
                            <div class="row mt-4">
                                <div class="col-lg-8">
                                    <div class="form-group mb-0">
                                        <label class="form-control-label small font-weight-bold">
                                            <i class="fas fa-sticky-note mr-1"></i>
                                            Catatan Jadwal (Opsional)
                                        </label>
                                        <input type="text" name="catatan_global"
                                               class="form-control form-control-sm"
                                               placeholder="cth: Jadwal reguler minggu ke-3 Mei 2026"
                                               maxlength="255">
                                    </div>
                                </div>
                                <div class="col-lg-4 d-flex align-items-end justify-content-end mt-3 mt-lg-0">
                                    <a href="?" class="btn btn-outline-secondary btn-sm mr-2">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm" id="btnSimpan">
                                        <i class="fas fa-save mr-1"></i> Simpan Jadwal Minggu Ini
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div><!-- /card-body -->

                    <!-- INFO TAMBAHAN -->
                    <div class="card-footer py-3 bg-lighter">
                        <div class="row">
                            <div class="col-lg-6">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle mr-1 text-primary"></i>
                                    <strong>Catatan:</strong>
                                    Kolom hari yang sudah lewat (<i class="fas fa-lock fa-xs"></i> Terkunci) tidak bisa diubah.
                                    Setiap tim bisa mendapat satu jenis shift per hari.
                                </small>
                            </div>
                            <div class="col-lg-6 text-right">
                                <small class="text-muted">
                                    <i class="fas fa-database mr-1"></i>
                                    Jadwal disimpan ke tabel <code>jadwal_shift</code> •
                                    Unique key: <code>(tim_id, tanggal, shift_definisi_id)</code>
                                </small>
                            </div>
                        </div>
                    </div>

                </div><!-- /card -->
            </div>
        </div>

        <!-- ================================================== -->
        <!-- TABEL RINGKASAN JADWAL (READ ONLY)                  -->
        <!-- ================================================== -->
        <div class="row">
            <div class="col-xl-12 mb-5">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <h3 class="mb-0">
                            <i class="fas fa-list mr-2 text-info"></i>
                            Ringkasan Jadwal —
                            <?= (new DateTime($minggu_mulai))->format('d M'); ?> s/d
                            <?= (new DateTime($minggu_akhir))->format('d M Y'); ?>
                        </h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-items-center table-flush table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th>Tim</th>
                                    <th>Shift</th>
                                    <th>Jam</th>
                                    <th>Durasi</th>
                                    <th>Dibuat Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ada_data = false;
                                foreach ($minggu_ini as $idx => $tgl) :
                                    if (!isset($jadwal_ada[$tgl])) continue;
                                    foreach ($jadwal_ada[$tgl] as $tim_id => $jd) :
                                        $ada_data = true;
                                        // Cari nama shift dari daftar_shift
                                        $sd_row = null;
                                        foreach ($daftar_shift as $sd) {
                                            if ($sd['id'] == $jd['shift_definisi_id']) { $sd_row = $sd; break; }
                                        }
                                        // Cari nama tim
                                        $tim_row = null;
                                        foreach ($daftar_tim as $t) {
                                            if ($t['id'] == $tim_id) { $tim_row = $t; break; }
                                        }
                                        $kode = $sd_row['kode_shift'] ?? '-';
                                        $w    = $warna_shift[$kode] ?? ['bg'=>'#f0f0f0','text'=>'#333','border'=>'#ccc'];
                                        $isToday = ($tgl === date('Y-m-d'));
                                ?>
                                <tr class="<?= $isToday ? 'table-primary' : ''; ?>">
                                    <td style="font-size:.82rem;">
                                        <?= (new DateTime($tgl))->format('d/m/Y'); ?>
                                        <?php if ($isToday) : ?>
                                            <span class="badge badge-primary badge-pill ml-1" style="font-size:.65rem;">Hari Ini</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.82rem;"><?= $nama_hari[$idx]; ?></td>
                                    <td>
                                        <span class="badge badge-warning badge-pill px-2" style="font-size:.78rem;">
                                            <?= htmlspecialchars($tim_row['nama_tim'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge px-2 py-1"
                                              style="background:<?= $w['bg']; ?>;color:<?= $w['text']; ?>;border:1px solid <?= $w['border']; ?>;font-size:.75rem;">
                                            <i class="<?= $ikon_shift[$kode] ?? 'fas fa-circle'; ?> mr-1"></i>
                                            <?= htmlspecialchars($kode); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:.82rem;">
                                        <?= htmlspecialchars($sd_row['label'] ?? '-'); ?>
                                    </td>
                                    <td style="font-size:.82rem;">
                                        <?= ($sd_row['durasi_jam'] ?? 0); ?> jam
                                    </td>
                                    <td style="font-size:.82rem;">
                                        <i class="fas fa-user fa-xs mr-1 text-muted"></i>
                                        <?= htmlspecialchars($jd['dibuat_oleh_nama']); ?>
                                    </td>
                                </tr>
                                <?php
                                    endforeach;
                                endforeach;
                                if (!$ada_data) :
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times fa-3x mb-3 d-block"></i>
                                        Belum ada jadwal untuk minggu ini.
                                        Gunakan form di atas untuk menjadwalkan shift tim.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php require '../template/footer.php'; ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Warna select berdasarkan pilihan shift
    const warnaShift = {
        'PAGI'          : { bg: '#FEF3C7', text: '#92400E' },
        'SIANG'         : { bg: '#DBEAFE', text: '#1E3A8A' },
        'MALAM'         : { bg: '#EDE9FE', text: '#4C1D95' },
        'PANJANG_SIANG' : { bg: '#D1FAE5', text: '#064E3B' },
        'PANJANG_MALAM' : { bg: '#FCE7F3', text: '#831843' },
    };

    function updateWarna(selectEl) {
        const opt  = selectEl.options[selectEl.selectedIndex];
        const kode = opt.dataset.kode || '';
        const w    = warnaShift[kode];
        if (w) {
            selectEl.style.background  = w.bg;
            selectEl.style.color       = w.text;
            selectEl.style.fontWeight  = '600';
        } else {
            // Libur — warna default
            selectEl.style.background  = '';
            selectEl.style.color       = '';
            selectEl.style.fontWeight  = '';
        }
    }

    // Inisialisasi warna semua select saat halaman load
    document.querySelectorAll('.select-shift').forEach(function (sel) {
        updateWarna(sel);
        sel.addEventListener('change', function () {
            updateWarna(this);
        });
    });

    // Konfirmasi + disable tombol saat submit
    const form      = document.getElementById('formJadwal');
    const btnSimpan = document.getElementById('btnSimpan');

    form.addEventListener('submit', function (e) {
        if (!confirm('Simpan jadwal shift untuk minggu ini?\nJadwal yang sudah ada akan diperbarui.')) {
            e.preventDefault();
            return;
        }
        btnSimpan.disabled  = true;
        btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';
    });

})();
</script>
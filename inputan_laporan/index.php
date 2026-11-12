<?php
// ============================================================
//  PSC 119 — input_laporan/index.php
//  Form input laporan darurat masuk (Operator)
//  Penugasan tim sudah otomatis berdasarkan shift aktif
// ============================================================
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';
$title = 'Input Laporan Masuk';
require '../template/header.php';
require '../template/sidebar.php';


// ============================================================
// Query dropdown: jenis_kasus
// ============================================================
$query_jenis = mysqli_query(
    $koneksi,
    "SELECT id, nama, kode
     FROM jenis_kasus
     ORDER BY nama ASC"
);

// ============================================================
// Query dropdown: triase
// ============================================================
$query_triase = mysqli_query(
    $koneksi,
    "SELECT id, kode, label, warna_hex, urgensi
     FROM triase
     ORDER BY urgensi ASC"
);

// ============================================================
// [OTOMATIS] Informasi tim yang akan bertugas berdasarkan jam
// ============================================================
$info_tim_otomatis = "Sistem akan menentukan tim secara otomatis berdasarkan shift (PAGI/SIANG/MALAM).";
$tim_terdeteksi    = null;
$shift_terdeteksi  = null;

// Ambil shift aktif berdasarkan jam sekarang
$jam_sekarang = date('H:i:s');
$kode_shift   = '';

if ($jam_sekarang >= '08:00:00' && $jam_sekarang < '14:00:00') {
    $kode_shift = 'PAGI';
} elseif ($jam_sekarang >= '14:00:00' && $jam_sekarang < '20:00:00') {
    $kode_shift = 'SIANG';
} else {
    // 20:00:00 sampai 08:00:00 keesokan hari
    $kode_shift = 'MALAM';
}

// Coba cari tim yang bertugas hari ini sesuai shift terdeteksi
$hari_ini = date('Y-m-d');
$query_tim = mysqli_query(
    $koneksi,
    "SELECT t.nama_tim, t.kode_tim, sd.label as shift_label
     FROM jadwal_shift js
     JOIN tim t ON t.id = js.tim_id
     JOIN shift_definisi sd ON sd.id = js.shift_definisi_id
     WHERE js.tanggal = '$hari_ini' AND sd.kode_shift = '$kode_shift'
     LIMIT 1"
);
if ($row_tim = mysqli_fetch_assoc($query_tim)) {
    $tim_terdeteksi   = $row_tim['nama_tim'] . ' (' . $row_tim['kode_tim'] . ')';
    $shift_terdeteksi = $row_tim['shift_label'];
    $info_tim_otomatis = "<span class='text-success'><i class='fas fa-check-circle'></i> Tim <strong>{$tim_terdeteksi}</strong> akan ditugaskan (Shift {$shift_terdeteksi}).</span>";
} else {
    $info_tim_otomatis = "<span class='text-danger'><i class='fas fa-exclamation-triangle'></i> Tidak ada jadwal shift untuk {$kode_shift} hari ini. Hubungi koordinator.</span>";
}

// Preset waktu kejadian = sekarang
$waktu_default = date('Y-m-d\TH:i');
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>

    <!-- Header halaman (sama seperti asli) -->
    <div class="header bg-gradient-primary pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">
                            <i class="fas fa-phone-alt mr-2"></i>
                            Input Laporan Masuk — PSC 119
                        </h6>
                        <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
                            <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
                                <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i></a></li>
                                <!-- <li class="breadcrumb-item"><a href="<?= $base_url ?>dashboard.php" class="text-black">Dashboard</a></li> -->
                                <li class="breadcrumb-item active">Input Laporan</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <span class="badge badge-pill badge-white px-3 py-2" id="jam-sekarang" style="font-size:.85rem;"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        <!-- Flash messages (sama) -->
        <?php if (isset($_SESSION['success'])) : ?>
            <div class="alert alert-success alert-dismissible fade show shadow"><?= $_SESSION['success']; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])) : ?>
            <div class="alert alert-danger alert-dismissible fade show shadow"><?= $_SESSION['error']; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Info operator -->
        <div class="row mb-3">
            <div class="col-xl-12">
                <div class="alert alert-default border-0 shadow-sm mb-0 d-flex align-items-center" style="background:linear-gradient(87deg,#172b4d,#1a174d);border-radius:.5rem;">
                    <div class="mr-3"><span class="badge badge-info badge-pill px-3 py-2"><i class="fas fa-user-shield mr-1"></i>Operator</span></div>
                    <div class="text-white">
                        <strong>Login sebagai:</strong> <?= htmlspecialchars($_SESSION['nama_pengguna'] ?? $_SESSION['username'] ?? 'Operator'); ?>
                        &mdash; <span class="text-info"><i class="fas fa-calendar-day mr-1"></i> <?= date('l, d F Y'); ?></span><br>
                        <small class="text-light"><i class="fas fa-info-circle mr-1"></i> Nomor kasus akan dibuat otomatis saat laporan disimpan.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 mb-5">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <h3 class="mb-0"><i class="fas fa-file-medical-alt mr-2 text-primary"></i> Form Input Laporan Darurat PSC 119</h3>
                                <small class="text-muted">Isi form berikut saat menerima panggilan darurat via telepon 119. Kolom bertanda <span class="text-danger">*</span> wajib diisi.</small>
                            </div>
                            <div class="col text-right">
                                <a href="../laporan_masuk" class="btn btn-sm btn-outline-primary"><i class="fas fa-list mr-1"></i> Daftar Laporan</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="proses.php" method="POST" id="formLaporan" novalidate enctype="multipart/form-data">
                            <!-- Data Pelapor (sama) -->
                            <h6 class="heading-small text-muted mb-4"><i class="fas fa-user mr-1"></i> Data Pelapor</h6>
                            <div class="pl-lg-4">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Nama Pelapor <span class="text-danger">*</span></label>
                                            <input type="text" name="nama_pelapor" id="nama_pelapor" class="form-control form-control-alternative" placeholder="Nama orang yang menelepon" maxlength="100" value="<?= htmlspecialchars($_POST['nama_pelapor'] ?? ''); ?>" required>
                                            <div class="invalid-feedback">Nama pelapor wajib diisi.</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">No. Telepon Pelapor <span class="text-danger">*</span></label>
                                            <input type="tel" name="no_telp_pelapor" id="no_telp_pelapor" class="form-control form-control-alternative" placeholder="cth: 081234567890" maxlength="20" value="<?= htmlspecialchars($_POST['no_telp_pelapor'] ?? ''); ?>" required>
                                            <div class="invalid-feedback">Nomor telepon wajib diisi.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">

                            <!-- Data Kejadian (sama) -->
                            <h6 class="heading-small text-muted mb-4"><i class="fas fa-map-marker-alt mr-1"></i> Data Kejadian</h6>
                            <div class="pl-lg-4">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Jenis Kasus <span class="text-danger">*</span></label>
                                            <select name="jenis_kasus_id" id="jenis_kasus_id" class="form-control form-control-alternative" required>
                                                <option value="">-- Pilih Jenis Kasus --</option>
                                                <?php $selected_jenis = (int) ($_POST['jenis_kasus_id'] ?? 0);
                                                while ($jenis = mysqli_fetch_assoc($query_jenis)) : ?>
                                                    <option value="<?= (int) $jenis['id']; ?>" <?= ($jenis['id'] == $selected_jenis) ? 'selected' : ''; ?>>
                                                        [<?= htmlspecialchars($jenis['kode']); ?>] <?= htmlspecialchars($jenis['nama']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="invalid-feedback">Jenis kasus wajib dipilih.</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Tingkat Triase <span class="text-danger">*</span></label>
                                            <select name="triase_id" id="triase_id" class="form-control form-control-alternative" required>
                                                <option value="">-- Pilih Triase --</option>
                                                <?php $selected_triase = (int) ($_POST['triase_id'] ?? 0);
                                                while ($triase = mysqli_fetch_assoc($query_triase)) :
                                                    $ikon = match ((int)$triase['urgensi']) {
                                                        1 => '🔴',
                                                        2 => '🟡',
                                                        3 => '🟢',
                                                        4 => '⚫',
                                                        default => '⚪'
                                                    }; ?>
                                                    <option value="<?= (int) $triase['id']; ?>" <?= ($triase['id'] == $selected_triase) ? 'selected' : ''; ?> data-warna="<?= htmlspecialchars($triase['warna_hex']); ?>" data-label="<?= htmlspecialchars($triase['label']); ?>" data-urgensi="<?= (int) $triase['urgensi']; ?>">
                                                        <?= $ikon; ?> <?= htmlspecialchars($triase['kode']); ?> — <?= htmlspecialchars($triase['label']); ?> (Prioritas <?= (int) $triase['urgensi']; ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div id="badge-triase" class="mt-2" style="display:none;"><span id="teks-triase" class="badge badge-pill px-3 py-2"></span></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Waktu Kejadian <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="waktu_kejadian" id="waktu_kejadian" class="form-control form-control-alternative" value="<?= htmlspecialchars($_POST['waktu_kejadian'] ?? $waktu_default); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label class="form-control-label">Lokasi / Alamat Kejadian <span class="text-danger">*</span></label>
                                            <textarea name="lokasi_kejadian" id="lokasi_kejadian" rows="3" class="form-control form-control-alternative" placeholder="Tulis alamat lengkap lokasi kejadian, sertakan patokan jika ada" maxlength="500" required><?= htmlspecialchars($_POST['lokasi_kejadian'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label class="form-control-label">Deskripsi Kejadian <span class="text-danger">*</span></label>
                                            <textarea name="deskripsi" id="deskripsi" rows="4" class="form-control form-control-alternative" placeholder="Ceritakan situasi kejadian secara singkat berdasarkan laporan pelapor" required><?= htmlspecialchars($_POST['deskripsi'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">

                            <!-- ============================================ -->
                            <!-- PENUGASAN TIM : OTOMATIS (tanpa pilihan manual) -->
                            <!-- ============================================ -->
                            <h6 class="heading-small text-muted mb-4"><i class="fas fa-shield-alt mr-1"></i> Penugasan Tim (Otomatis)</h6>
                            <div class="pl-lg-4">
                                <div class="alert alert-info border-0 shadow-sm bg-secondary">
                                    <i class="fas fa-clock mr-2"></i> <?= $info_tim_otomatis; ?>
                                </div>
                                <!-- Hidden field untuk mengirimkan flag bahwa penugasan otomatis -->
                                <input type="hidden" name="auto_assign_tim" value="1">
                            </div>
                            <hr class="my-4">

                            <!-- Data Pasien (Opsional) -->
                            <h6 class="heading-small text-muted mb-2"><i class="fas fa-procedures mr-1"></i> Data Pasien <span class="badge badge-pill badge-light border ml-2">Opsional — sistem akan membuat satu data default jika kosong</span></h6>
                            <div id="kontainer-pasien"></div>
                            <div class="pl-lg-4 mb-4">
                                <button type="button" class="btn btn-outline-info btn-sm" id="btnTambahPasien"><i class="fas fa-plus mr-1"></i> Tambah Pasien</button>
                                <span class="text-muted small ml-2" id="keterangan-pasien">Belum ada data pasien ditambahkan.</span>
                            </div>
                            <template id="tpl-pasien">
                                <div class="pl-lg-4 mb-3 border rounded p-3 bg-light pasien-baris" data-index="__IDX__">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong class="text-primary"><i class="fas fa-user-injured mr-1"></i> Pasien #<span class="nomor-pasien">__NO__</span></strong>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-pasien"><i class="fas fa-times"></i> Hapus</button>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <div class="form-group mb-2"><label class="form-control-label small">Nama Pasien</label><input type="text" name="pasien[__IDX__][nama_pasien]" class="form-control form-control-sm" placeholder="Jika tidak diketahui, kosongkan"></div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group mb-2"><label class="form-control-label small">Umur (thn)</label><input type="number" name="pasien[__IDX__][umur]" class="form-control form-control-sm" min="0" max="150" placeholder="—"></div>
                                        </div>
                                        <div class="col-lg-2">
                                            <div class="form-group mb-2"><label class="form-control-label small">Jenis Kelamin</label><select name="pasien[__IDX__][jenis_kelamin]" class="form-control form-control-sm">
                                                    <option value="">— Pilih —</option>
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                </select></div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="form-group mb-2"><label class="form-control-label small">NIK (opsional)</label><input type="text" name="pasien[__IDX__][nik]" class="form-control form-control-sm" maxlength="16" placeholder="16 digit NIK"></div>
                                        </div>
                                    </div>

                                    <!-- <div class="row">
                                        <div class="col-lg-6">
                                            <div class="form-group mb-2">
                                                <label class="form-control-label small">Foto KTP</label>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input foto-ktp-input" name="foto_ktp[__IDX__]" accept="image/jpeg,image/png,image/jpg" data-index="__IDX__">
                                                    <label class="custom-file-label" for="foto_ktp___IDX__">Pilih file...</label>
                                                </div>
                                                <small class="text-muted">Max 2MB (JPG/PNG)</small>
                                                <div class="mt-2 preview-foto-ktp-__IDX__" style="display:none;">
                                                    <img src="#" class="img-thumbnail" style="max-height:80px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="form-group mb-2">
                                                <label class="form-control-label small">Foto Pasien</label>
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input foto-pasien-input" name="foto_pasien[__IDX__]" accept="image/jpeg,image/png,image/jpg" data-index="__IDX__">
                                                    <label class="custom-file-label" for="foto_pasien___IDX__">Pilih file...</label>
                                                </div>
                                                <small class="text-muted">Max 2MB (JPG/PNG)</small>
                                                <div class="mt-2 preview-foto-pasien-__IDX__" style="display:none;">
                                                    <img src="#" class="img-thumbnail" style="max-height:80px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div> -->

                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group mb-0">
                                                <label class="form-control-label small">Kondisi / Keluhan <span class="text-danger">*</span></label>
                                                <textarea name="pasien[__IDX__][kondisi_awal]" rows="2" class="form-control form-control-sm kondisi-wajib" placeholder="Jelaskan kondisi pasien secara singkat" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <hr class="my-4">

                            <!-- Tombol Aksi -->
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div><span class="text-muted small"><i class="fas fa-info-circle mr-1"></i> Kolom <span class="text-danger">*</span> wajib diisi. Status awal otomatis: <code>menunggu_respon</code>.</span></div>
                                        <div>
                                            <a href="../laporan_masuk" class="btn btn-secondary mr-2" onclick="return confirm('Batalkan input laporan ini?')"><i class="fas fa-times mr-1"></i> Batal</a>
                                            <button type="reset" class="btn btn-outline-warning mr-2" onclick="return confirm('Reset semua isian form?')"><i class="fas fa-undo mr-1"></i> Reset</button>
                                            <button type="submit" class="btn btn-primary" id="btnSimpan"><i class="fas fa-save mr-1"></i> Simpan Laporan</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<script>
(function() {
    // ========== FUNGSI JAM REAL-TIME ==========
    function updateJam() {
        const now = new Date();
        const el = document.getElementById('jam-sekarang');
        if (el) el.innerHTML = '<i class="fas fa-clock mr-1"></i>' + now.toLocaleString('id-ID', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    updateJam();
    setInterval(updateJam, 1000);

    // ========== BADGE TRIASE ==========
    const selectTriase = document.getElementById('triase_id');
    const badgeTriase = document.getElementById('badge-triase');
    const teksTriase = document.getElementById('teks-triase');
    const kelasTriase = {1:'badge-danger',2:'badge-warning',3:'badge-success',4:'badge-dark'};

    function tampilkanBadgeTriase() {
        const opt = selectTriase.options[selectTriase.selectedIndex];
        const val = parseInt(selectTriase.value, 10);
        if (!isNaN(val) && val > 0) {
            teksTriase.textContent = opt.dataset.label || opt.text;
            teksTriase.className = 'badge badge-pill px-3 py-2 ' + (kelasTriase[parseInt(opt.dataset.urgensi, 10)] || 'badge-secondary');
            badgeTriase.style.display = '';
        } else {
            badgeTriase.style.display = 'none';
        }
    }
    if (selectTriase) {
        selectTriase.addEventListener('change', tampilkanBadgeTriase);
        if (selectTriase.value) tampilkanBadgeTriase();
    }

    // ========== MANAJEMEN PASIEN DINAMIS ==========
    const kontainer = document.getElementById('kontainer-pasien');
    const btnTambah = document.getElementById('btnTambahPasien');
    const keterangan = document.getElementById('keterangan-pasien');
    const tpl = document.getElementById('tpl-pasien');
    let jumlahPasien = 0;

    function updateKeterangan() {
        keterangan.textContent = jumlahPasien === 0 ? 'Belum ada data pasien ditambahkan.' : jumlahPasien + ' pasien ditambahkan.';
    }

    function renumberPasien() {
        document.querySelectorAll('.pasien-baris').forEach((el, idx) => {
            el.querySelector('.nomor-pasien').textContent = idx + 1;
            el.setAttribute('data-index', idx);
        });
    }

    // ========== FUNGSI PREVIEW GAMBAR (UNTUK SATU BARIS) ==========
    function initPreview(container) {
        // Preview untuk Foto KTP
        container.querySelectorAll('.foto-ktp-input').forEach(input => {
            input.addEventListener('change', function(e) {
                let idx = this.dataset.index;
                let previewDiv = container.querySelector(`.preview-foto-ktp-${idx}`);
                let label = this.nextElementSibling; // <label class="custom-file-label">
                if (this.files && this.files[0]) {
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        if (previewDiv) {
                            previewDiv.style.display = 'block';
                            previewDiv.querySelector('img').src = e.target.result;
                        }
                    }
                    reader.readAsDataURL(this.files[0]);
                    if (label && label.classList.contains('custom-file-label')) {
                        label.innerHTML = this.files[0].name;
                    }
                } else {
                    if (previewDiv) previewDiv.style.display = 'none';
                    if (label && label.classList.contains('custom-file-label')) {
                        label.innerHTML = 'Pilih file...';
                    }
                }
            });
        });

        // Preview untuk Foto Pasien
        container.querySelectorAll('.foto-pasien-input').forEach(input => {
            input.addEventListener('change', function(e) {
                let idx = this.dataset.index;
                let previewDiv = container.querySelector(`.preview-foto-pasien-${idx}`);
                let label = this.nextElementSibling;
                if (this.files && this.files[0]) {
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        if (previewDiv) {
                            previewDiv.style.display = 'block';
                            previewDiv.querySelector('img').src = e.target.result;
                        }
                    }
                    reader.readAsDataURL(this.files[0]);
                    if (label && label.classList.contains('custom-file-label')) {
                        label.innerHTML = this.files[0].name;
                    }
                } else {
                    if (previewDiv) previewDiv.style.display = 'none';
                    if (label && label.classList.contains('custom-file-label')) {
                        label.innerHTML = 'Pilih file...';
                    }
                }
            });
        });
    }

    // ========== TAMBAH BARIS PASIEN BARU ==========
    function tambahPasien() {
        const idx = jumlahPasien;
        let html = tpl.innerHTML;
        // Ganti placeholder __IDX__ dan __NO__
        html = html.replace(/__IDX__/g, idx).replace(/__NO__/g, idx + 1);
        // Perbaiki atribut id dan for pada custom-file
        html = html.replace(/for="foto_ktp___IDX__"/g, `for="foto_ktp_${idx}"`);
        html = html.replace(/for="foto_pasien___IDX__"/g, `for="foto_pasien_${idx}"`);
        html = html.replace(/name="foto_ktp\[__IDX__\]"/g, `name="foto_ktp[${idx}]" id="foto_ktp_${idx}"`);
        html = html.replace(/name="foto_pasien\[__IDX__\]"/g, `name="foto_pasien[${idx}]" id="foto_pasien_${idx}"`);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        const baris = div.firstElementChild;
        
        // Event hapus pasien
        baris.querySelector('.btn-hapus-pasien').addEventListener('click', () => {
            baris.remove();
            jumlahPasien--;
            renumberPasien();
            updateKeterangan();
        });
        
        kontainer.appendChild(baris);
        initPreview(baris);  // Aktifkan preview untuk baris baru
        jumlahPasien++;
        updateKeterangan();
        baris.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    btnTambah.addEventListener('click', tambahPasien);

    // ========== VALIDASI FORM SEBELUM SUBMIT ==========
    const form = document.getElementById('formLaporan');
    form.addEventListener('submit', function(e) {
        let pasienValid = true;
        document.querySelectorAll('.kondisi-wajib').forEach(ta => {
            if (ta.value.trim() === '') {
                ta.classList.add('is-invalid');
                pasienValid = false;
            } else {
                ta.classList.remove('is-invalid');
            }
        });
        if (!form.checkValidity() || !pasienValid) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            if (!pasienValid) alert('Kolom Kondisi/Keluhan pada data pasien wajib diisi.');
            return;
        }
        const btn = document.getElementById('btnSimpan');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';
    });

    // Reset form
    form.addEventListener('reset', function() {
        form.classList.remove('was-validated');
        kontainer.innerHTML = '';
        jumlahPasien = 0;
        updateKeterangan();
        if (badgeTriase) badgeTriase.style.display = 'none';
    });

    // Inisialisasi preview untuk baris pasien yang sudah ada (jika ada)
    document.querySelectorAll('.pasien-baris').forEach(baris => initPreview(baris));
})();
</script>